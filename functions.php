<?php
/*
ZeroBin - a zero-knowledge paste bin
Please see project page: http://sebsauvage.net/wiki/doku.php?id=php:zerobin
*/
$VERSION='Alpha 0.19';
if (version_compare(PHP_VERSION, '5.2.6') < 0) die('ZeroBin requires php 5.2.6 or above to work. Sorry.');
require_once PLX_PLUGINS."cryptMyPluxml/lib/serversalt.php";
require_once PLX_PLUGINS."cryptMyPluxml/lib/vizhash_gd_zero.php";

// In case stupid admin has left magic_quotes enabled in php.ini:
if (get_magic_quotes_gpc())
{
    function stripslashes_deep($value) { $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value); return $value; }
    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
}

// trafic_limiter : Make sure the IP address makes at most 1 request every 10 seconds.
// Will return false if IP address made a call less than 10 seconds ago.
function cmp_trafic_limiter_canPass($ip)
{
    $tfilename=PLX_ROOT.'data/zb/trafic_limiter.php';
    if (!is_file($tfilename))
    {
        file_put_contents($tfilename,"<?php\n\$GLOBALS['trafic_limiter']=array();\n?>", LOCK_EX);
        chmod($tfilename,0705);
    }
    require $tfilename;
    $tl=$GLOBALS['trafic_limiter'];
    if (!empty($tl[$ip]) && ($tl[$ip]+10>=time()))
    {
        return false;
        // FIXME: purge file of expired IPs to keep it small
    }
    $tl[$ip]=time();
    file_put_contents($tfilename, "<?php\n\$GLOBALS['trafic_limiter']=".var_export($tl,true).";\n?>", LOCK_EX);
    return true;
}

// Constant time string comparison.
// (Used to deter time attacks on hmac checking. See section 2.7 of https://defuse.ca/audits/zerobin.htm)
function cmp_slow_equals($a, $b)
{
    $diff = strlen($a) ^ strlen($b);
    for($i = 0; $i < strlen($a) && $i < strlen($b); $i++)
    {
        $diff |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $diff === 0;
}


/* Convert paste id to storage path.
   The idea is to creates subdirectories in order to limit the number of files per directory.
   (A high number of files in a single directory can slow things down.)
   eg. "f468483c313401e8" will be stored in "data/f4/68/f468483c313401e8"
   High-trafic websites may want to deepen the directory structure (like Squid does).

   eg. input 'e3570978f9e4aa90' --> output 'data/e3/57/'
*/
function cmp_dataid2path($dataid)
{
    return PLX_ROOT.'data/zb/'.substr($dataid,0,2).'/'.substr($dataid,2,2).'/';
}

/* Convert paste id to discussion storage path.
   eg. 'e3570978f9e4aa90' --> 'data/e3/57/e3570978f9e4aa90.discussion/'
*/
function cmp_dataid2discussionpath($dataid)
{
    return cmp_dataid2path($dataid).$dataid.'.discussion/';
}

// Checks if a json string is a proper SJCL encrypted message.
// False if format is incorrect.
function cmp_validSJCL($jsonstring)
{
    $accepted_keys=array('iv','v','iter','ks','ts','mode','adata','cipher','salt','ct');

    // Make sure content is valid json
    $decoded = json_decode($jsonstring);
    if ($decoded==null) return false;
    $decoded = (array)$decoded;

    // Make sure required fields are present
    foreach($accepted_keys as $k)
    {
        if (!array_key_exists($k,$decoded))  { return false; }
    }

    // Make sure some fields are base64 data
    if (base64_decode($decoded['iv'],$strict=true)==null) { return false; }
    if (base64_decode($decoded['salt'],$strict=true)==null) { return false; }
    if (base64_decode($decoded['cipher'],$strict=true)==null) { return false; }

    // Make sure no additionnal keys were added.
    if (count(array_intersect(array_keys($decoded),$accepted_keys))!=10) { return false; }

    // Reject data if entropy is too low
    $ct = base64_decode($decoded['ct'], $strict=true);
    if (strlen($ct) > strlen(gzdeflate($ct))) return false;

    // Make sure some fields have a reasonable size.
    if (strlen($decoded['iv'])>24) return false;
    if (strlen($decoded['salt'])>14) return false;
    return true;
}

// Delete a paste and its discussion.
// Input: $pasteid : the paste identifier.
function cmp_deletePaste($pasteid)
{
    // Delete the paste itself
    unlink(cmp_dataid2path($pasteid).$pasteid);

    // Delete discussion if it exists.
    $discdir = cmp_dataid2discussionpath($pasteid);
    if (is_dir($discdir))
    {
        // Delete all files in discussion directory
        $dhandle = opendir($discdir);
        while (false !== ($filename = readdir($dhandle)))
        {
            if (is_file($discdir.$filename))  unlink($discdir.$filename);
        }
        closedir($dhandle);

        // Delete the discussion directory.
        rmdir($discdir);
    }
    $subdir = cmp_dataid2path($pasteid);
    $maindir = substr($subdir,0,-4);
    rmdir($subdir);
    rmdir($maindir);
}

/* Process a paste deletion request.
   Returns an array ('',$ERRORMESSAGE,$STATUS)
*/
function cmp_processPasteDelete($pasteid,$deletetoken)
{
    if (preg_match('/\A[a-f\d]{16}\z/',$pasteid))  // Is this a valid paste identifier ?
    {
        $filename = cmp_dataid2path($pasteid).$pasteid;
        if (!is_file($filename)) // Check that paste exists.
        {
            return array('','Paste does not exist, has expired or has been deleted.','');
        }
    }
    else
    {
        return array('','Invalid data','');
    }

    if (!cmp_slow_equals($deletetoken, hash_hmac('sha1', $pasteid , getServerSalt()))) // Make sure token is valid.
    {
        return array('','Wrong deletion token. Paste was not deleted.','');
    }

    // Paste exists and deletion token is valid: Delete the paste.
    cmp_deletePaste($pasteid);
    return array('','','Paste was properly deleted.');
}

/* Process a paste fetch request.
   Returns an array ($CIPHERDATA,$ERRORMESSAGE,$STATUS)
*/
function cmp_processPasteFetch($pasteid)
{
    $pasteid = str_replace(array('zb=','zb/'), '', $pasteid);
    if (preg_match('/\A[a-f\d]{16}\z/',$pasteid))  // Is this a valid paste identifier ?
    {
        $filename = cmp_dataid2path($pasteid).$pasteid;
        if (!is_file($filename)) // Check that paste exists.
        {
            return array('','Paste does not exist, has expired or has been deleted.','');
        }
    }
    else
    {
        return array('','Invalid data','');
    }

    // Get the paste itself.
    $paste=json_decode(file_get_contents($filename));

    // See if paste has expired.
    if (isset($paste->meta->expire_date) && $paste->meta->expire_date<time())
    {
        cmp_deletePaste($pasteid);  // Delete the paste
        return array('','Paste does not exist, has expired or has been deleted.','');
    }


    // We kindly provide the remaining time before expiration (in seconds)
    if (property_exists($paste->meta, 'expire_date')) $paste->meta->remaining_time = $paste->meta->expire_date - time();

    $messages = array($paste); // The paste itself is the first in the list of encrypted messages.
    // If it's a discussion, get all comments.
    if (property_exists($paste->meta, 'opendiscussion') && $paste->meta->opendiscussion)
    {
        $comments=array();
        $datadir = cmp_dataid2discussionpath($pasteid);
        if (!is_dir($datadir)) mkdir($datadir,$mode=0705,$recursive=true);
        $dhandle = opendir($datadir);
        while (false !== ($filename = readdir($dhandle)))
        {
            if (is_file($datadir.$filename))
            {
                $comment=json_decode(file_get_contents($datadir.$filename));
                // Filename is in the form pasteid.commentid.parentid:
                // - pasteid is the paste this reply belongs to.
                // - commentid is the comment identifier itself.
                // - parentid is the comment this comment replies to (It can be pasteid)
                $items=explode('.',$filename);
                $comment->meta->commentid=$items[1]; // Add some meta information not contained in file.
                $comment->meta->parentid=$items[2];
                $comments[$comment->meta->postdate]=$comment; // Store in table
            }
        }
        closedir($dhandle);
        ksort($comments); // Sort comments by date, oldest first.
        $messages = array_merge($messages, $comments);
    }
    $CIPHERDATA = json_encode($messages);
    # si affichage des articles coté visiteurs:
    if(!defined('PLX_ADMIN')) {
        // If the paste was meant to be read only once, delete it.
        if (property_exists($paste->meta, 'burnafterreading') && $paste->meta->burnafterreading) {cmp_deletePaste($pasteid);}
    }

    return array($CIPHERDATA,'','');
}
?>