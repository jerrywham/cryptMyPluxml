<?php
/**
 * Plugin cryptMyPluxml
 *
 *  Based on code of ZeroBin
 *  @link http://sebsauvage.net/wiki/doku.php?id=php:zerobin
 *  @author sebsauvage
 *
 * ZeroBin is a minimalist, opensource online pastebin where the server 
 * has zero knowledge of pasted data. Data is encrypted/decrypted in the 
 * browser using 256 bits AES. 
 * 
 * More information on the project page:
 * http://sebsauvage.net/wiki/doku.php?id=php:zerobin
 * 
 * ------------------------------------------------------------------------------
 * 
 * Copyright (c) 2012 Sébastien SAUVAGE (sebsauvage.net)
 * 
 * This software is provided 'as-is', without any express or implied warranty.
 * In no event will the authors be held liable for any damages arising from 
 * the use of this software.
 * 
 * Permission is granted to anyone to use this software for any purpose, 
 * including commercial applications, and to alter it and redistribute it 
 * freely, subject to the following restrictions:
 * 
 *     1. The origin of this software must not be misrepresented; you must 
 *        not claim that you wrote the original software. If you use this 
 *        software in a product, an acknowledgment in the product documentation
 *        would be appreciated but is not required.
 * 
 *     2. Altered source versions must be plainly marked as such, and must 
 *        not be misrepresented as being the original software.
 * 
 *     3. This notice may not be removed or altered from any source distribution.
 * 
 * ------------------------------------------------------------------------------
 * 
 *
 *
 * @version	1.1
 * @date	21/05/2014
 * @author	Cyril MAGUIRE
 **/
include 'functions.php';
class cryptMyPluxml extends plxPlugin {

	public $VERSION='Alpha 0.19';
	public $CIPHERDATA='';
	public $ERRORMESSAGE='';
	public $STATUS='';

	/**
	 * Constructeur de la classe inMyPluxml
	 *
	 * @param	default_lang	langue par défaut utilisée par PluXml
	 * @return	null
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

		# Appel du constructeur de la classe plxPlugin (obligatoire)
		parent::__construct($default_lang);
		if(defined('PLX_ADMIN')) {
			if (!empty($_GET['deletetoken']) && !empty($_GET['pasteid'])) // Delete an existing paste
			{
			    list ($this->CIPHERDATA, $this->ERRORMESSAGE, $this->STATUS) = cmp_processPasteDelete(plxUtils::strCheck(plxUtils::nullbyteRemove($_GET['pasteid'])),plxUtils::strCheck(plxUtils::nullbyteRemove($_GET['deletetoken'])));
			}
			elseif (!empty($_SERVER['QUERY_STRING']))  // Return an existing paste.
			{	
				$zb = preg_replace('!(a=[0-9]+&?)*(zb=)?!', '', plxUtils::getGets($_SERVER['QUERY_STRING']));
				if (!empty($zb)) {
					 list ($this->CIPHERDATA, $this->ERRORMESSAGE, $this->STATUS) = cmp_processPasteFetch($zb);
				}
			}
		}

		# Déclarations des hooks		
		$this->addHook('ThemeEndHead', 'ThemeEndHead');
		$this->addHook('plxMotorPreChauffageBegin', 'plxMotorPreChauffageBegin');
		$this->addHook('plxMotorDemarrageBegin', 'plxMotorDemarrageBegin');
		$this->addHook('plxShowConstruct', 'plxShowConstruct');
		$this->addHook('AdminPrepend', 'Prepend');
		$this->addHook('IndexBegin', 'Prepend');
		$this->addHook('AdminTopEndHead', 'AdminTopEndHead');
		$this->addHook('AdminArticleTop', 'AdminArticleTop');
		// Pour n'enregistrer des données que via ZB, décommenter ces lignes
		// $this->addHook('AdminArticleContent', 'AdminArticleContent');
		// $this->addHook('AdminArticleFoot', 'AdminArticleFoot');
	}

	public function onActivate() {
		if (!is_file(PLX_ROOT.'data/configuration/plugins/cryptMyPluxml.admin.css')) {
			$css = file_get_contents(PLX_PLUGINS.'cryptMyPluxml/css/admin.css');
			plxUtils::write($css, PLX_ROOT.'data/configuration/plugins/cryptMyPluxml.admin.css');
		}
		if (!is_file(PLX_ROOT.'data/configuration/plugins/cryptMyPluxml.site.css')) {
			$css = file_get_contents(PLX_PLUGINS.'cryptMyPluxml/css/site.css');
			plxUtils::write($css, PLX_ROOT.'data/configuration/plugins/cryptMyPluxml.site.css');
		}
	}

	public function ThemeEndHead() {
		$string = '
		<?php if ((isset($plxMotor) && $plxMotor->mode == "zerobin") || (isset($plxAdmin) && preg_match(\'!^core/admin/article(.*)!\',$plxAdmin->path_url,$capture) )) {?>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/jquery.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/sjcl.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/base64.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/rawdeflate.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/rawinflate.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/zerobin.js"></script>
		<script src="'.PLX_PLUGINS.'cryptMyPluxml/js/highlight.pack.js"></script>
		<?php }?>';
		echo $string;
	}

	public function AdminTopEndHead() {
		$this->ThemeEndHead();
	}

	public function plxMotorPreChauffageBegin() {
		$string = '
		if($this->get AND preg_match(\'/\A[a-f\d]{16}\z/\',str_replace(array(\'zb=\',\'zb/\'),\'\',$this->get),$capture)) {
			$this->mode = \'zerobin\';
			$this->template = \'static.php\';
			$this->cible = \'../../plugins/cryptMyPluxml/zb\';
			return true;
		}';
		echo '<?php '.$string.' ?>';
	}

	public function plxShowConstruct() {

		# infos sur la page statique
		$string  = "
		if(\$this->plxMotor->mode=='zerobin') {
						\$array = array();
						\$array[\$this->plxMotor->cible] = array(
			'name'		=> 'cryptMyPluxml',
			'menu'		=> '',
			'url'		=> 'template',
			'readable'	=> 1,
			'active'	=> 1,
			'group'		=> ''
		);
			\$this->plxMotor->aStats = array_merge(\$this->plxMotor->aStats, \$array);
		}";
		echo "<?php ".$string." ?>";
	}

	public function plxMotorDemarrageBegin() {
		$string = '
		if($this->mode == \'zerobin\') {
			return true;
		}
		';
		echo '<?php '.$string.' ?>';
	}

	public function AdminArticleTop() {
		$string = '
		<div id="zb">
		<a id="toggler_message" href="javascript:void(0)" onclick="toggleDiv(\'toggle_message\', \'toggler_message\', \''.$this->getLang('L_DISPLAY_ZB_FORM').'\',\''.$this->getLang('L_HIDE_ZB_FORM').'\');">'.$this->getLang('L_DISPLAY_ZB_FORM').'</a> 
			<div id="infoZB">(?)<br/>
				<div id="aboutbox">
					'.$this->getLang('L_ZB_DESC').'<br />
				</div>
			</div>
		<br/>
	    <div id="status">'.$this->STATUS.'</div>
	    <div id="errormessage" style="display:none">'.$this->ERRORMESSAGE.'</div>

		<div id="toggle_message" style="display:none">
	    <h2>Because ignorance is bliss</h2><br>
	    <noscript><div class="nonworking">'.$this->getLang('L_JS_REQUIRED').'</div></noscript>
	    <div id="oldienotice" class="nonworking">'.$this->getLang('L_MODERN_BROWSER').'</div>
	    <div id="ienotice">'.$this->getLang('L_STILL_IE').' 
	        <a href="http://www.mozilla.org/firefox/">Firefox</a>, 
	        <a href="http://www.opera.com/">Opera</a>, 
	        <a href="http://www.google.com/chrome">Chrome</a>, 
	        <a href="http://www.apple.com/safari">Safari</a>...
	    </div>
		<div id="toolbar">
			<button id="newbutton" class="zbButton" onclick="send_data();return false;" style="display:none;">'.$this->getLang('L_NEW').'</button>
			<button id="sendbutton" class="zbButton" onclick="send_data();return false;" style="display:none;">'.$this->getLang('L_SEND').'</button>
			<button id="clonebutton" class="zbButton" onclick="clonePaste();return false;" style="display:none;">'.$this->getLang('L_CLONE').'</button>
			<button id="rawtextbutton" class="zbButton" onclick="rawText();return false;" style="display:none; ">'.$this->getLang('L_RAW_TEXT').'</button>
			<div id="expiration" style="display:none;">'.$this->getLang('L_EXPIRES').': 
				<select id="pasteExpiration" name="pasteExpiration">
					<option value="5min">'.$this->getLang('L_5_MINUTES').'</option>
					<option value="10min">'.$this->getLang('L_10_MINUTES').'</option>
					<option value="1hour">'.$this->getLang('L_1_HOUR').'</option>
					<option value="1day">'.$this->getLang('L_1_DAY').'</option>
					<option value="1week">'.$this->getLang('L_1_WEEK').'</option>
					<option value="1month" selected="selected">'.$this->getLang('L_1_MONTH').'</option>
					<option value="1year">'.$this->getLang('L_1_YEAR').'</option>
					<option value="never">'.$this->getLang('L_NEVER').'</option>
				</select>
			</div>
			<div id="burnafterreadingoption" class="button zbButton" style="display:none;">
				<input type="checkbox" id="burnafterreading" name="burnafterreading" />
				<label for="burnafterreading">'.$this->getLang('L_BURN_AFTER_READING').'</label>
			</div>
			<div id="opendisc" class="button zbButton" style="display:none;">
				<input type="checkbox" id="opendiscussion" name="opendiscussion" />
				<label for="opendiscussion">'.$this->getLang('L_OPEN_DISCUSSION').'</label>
			</div>
			<div id="syntaxcoloringoption" class="button zbButton" style="display:none;">
				<input type="checkbox" id="syntaxcoloring" name="syntaxcoloring" />
				<label for="syntaxcoloring">'.$this->getLang('L_SYNTAX_COLORING').'</label>
			</div>
		</div>
	    <div id="pasteresult" style="display:none;">
	      <div id="deletelink"></div>
	      <div id="pastelink"></div>
	    </div>
	    <div id="cleartext" style="display:none;"></div>
		<textarea id="message" name="message" cols="80" rows="25" style="display:none;"></textarea></div>
	    <div id="cipherdata" style="display:none;">'.$this->CIPHERDATA.'</div>
    	</div>
		';

		echo $string;
	}

	public function Prepend() {
		
		if (!empty($_POST['data'])) // Create new paste/comment
		{
		    /* POST contains:
		         data (mandatory) = json encoded SJCL encrypted text (containing keys: iv,salt,ct)

		         All optional data will go to meta information:
		         expire (optional) = expiration delay (never,5min,10min,1hour,1day,1week,1month,1year,burn) (default:never)
		         opendiscusssion (optional) = is the discussion allowed on this paste ? (0/1) (default:0)
		         syntaxcoloring (optional) = should this paste use syntax coloring when displaying.
		         nickname (optional) = son encoded SJCL encrypted text nickname of author of comment (containing keys: iv,salt,ct)
		         parentid (optional) = in discussion, which comment this comment replies to.
		         pasteid (optional) = in discussion, which paste this comment belongs to.
		    */

		    header('Content-type: application/json');
		    $error = false;

		    // Create storage directory if it does not exist.
		    if (!is_dir(PLX_ROOT.'data/zb'))
		    {
		        mkdir(PLX_ROOT.'data/zb',0705);
		        file_put_contents(PLX_ROOT.'data/zb/.htaccess',"Allow from none\nDeny from all\n", LOCK_EX);
		    }

		    // Make sure last paste from the IP address was more than 10 seconds ago.
		    if (!cmp_trafic_limiter_canPass($_SERVER['REMOTE_ADDR']))
		        { echo json_encode(array('status'=>1,'message'=>'Please wait 10 seconds between each post.')); exit; }

		    // Make sure content is not too big.
		    $data = $_POST['data'];
		    if (strlen($data)>2000000)
		        { echo json_encode(array('status'=>1,'message'=>'Paste is limited to 2 Mb of encrypted data.')); exit; }

		    // Make sure format is correct.
		    if (!cmp_validSJCL($data))
		        { echo json_encode(array('status'=>1,'message'=>'Invalid data.')); exit; }

		    // Read additional meta-information.
		    $meta=array();

		    // Read expiration date
		    if (!empty($_POST['expire']))
		    {
		        $expire=$_POST['expire'];
		        if ($expire=='5min') $meta['expire_date']=time()+5*60;
		        elseif ($expire=='10min') $meta['expire_date']=time()+10*60;
		        elseif ($expire=='1hour') $meta['expire_date']=time()+60*60;
		        elseif ($expire=='1day') $meta['expire_date']=time()+24*60*60;
		        elseif ($expire=='1week') $meta['expire_date']=time()+7*24*60*60;
		        elseif ($expire=='1month') $meta['expire_date']=time()+30*24*60*60; // Well this is not *exactly* one month, it's 30 days.
		        elseif ($expire=='1year') $meta['expire_date']=time()+365*24*60*60;
		    }

		    // Destroy the paste when it is read.
		    if (!empty($_POST['burnafterreading']))
		    {
		        $burnafterreading = $_POST['burnafterreading'];
		        if ($burnafterreading!='0' && $burnafterreading!='1') { $error=true; }
		        if ($burnafterreading!='0') { $meta['burnafterreading']=true; }
		    }

		    // Read open discussion flag
		    if (!empty($_POST['opendiscussion']))
		    {
		        $opendiscussion = $_POST['opendiscussion'];
		        if ($opendiscussion!='0' && $opendiscussion!='1') { $error=true; }
		        if ($opendiscussion!='0') { $meta['opendiscussion']=true; }
		    }

		    // Should we use syntax coloring when displaying ?
		    if (!empty($_POST['syntaxcoloring']))
		    {
		        $syntaxcoloring = $_POST['syntaxcoloring'];
		        if ($syntaxcoloring!='0' && $syntaxcoloring!='1') { $error=true; }
		        if ($syntaxcoloring!='0') { $meta['syntaxcoloring']=true; }
		    }    

		    // You can't have an open discussion on a "Burn after reading" paste:
		    if (isset($meta['burnafterreading'])) unset($meta['opendiscussion']);

		    // Optional nickname for comments
		    if (!empty($_POST['nickname']))
		    {
		        $nick = $_POST['nickname'];
		        if (!cmp_validSJCL($nick))
		        {
		            $error=true;
		        }
		        else
		        {
		            $meta['nickname']=$nick;

		            // Generation of the anonymous avatar (Vizhash):
		            // If a nickname is provided, we generate a Vizhash.
		            // (We assume that if the user did not enter a nickname, he/she wants
		            // to be anonymous and we will not generate the vizhash.)
		            $vz = new cmp_vizhash16x16();
		            $pngdata = $vz->generate($_SERVER['REMOTE_ADDR']);
		            if ($pngdata!='') $meta['vizhash'] = 'data:image/png;base64,'.base64_encode($pngdata);
		            // Once the avatar is generated, we do not keep the IP address, nor its hash.
		        }
		    }

		    if ($error)
		    {
		        echo json_encode(array('status'=>1,'message'=>'Invalid data.'));
		        exit;
		    }

		    // Add post date to meta.
		    $meta['postdate']=time();

		    // We just want a small hash to avoid collisions: Half-MD5 (64 bits) will do the trick.
		    $dataid = substr(hash('md5',$data),0,16);

		    $is_comment = (!empty($_POST['parentid']) && !empty($_POST['pasteid'])); // Is this post a comment ?
		    $storage = array('data'=>$data);
		    if (count($meta)>0) $storage['meta'] = $meta;  // Add meta-information only if necessary.

		    if ($is_comment) // The user posts a comment.
		    {
		        $pasteid = $_POST['pasteid'];
		        $parentid = $_POST['parentid'];
		        if (!preg_match('/\A[a-f\d]{16}\z/',$pasteid)) { echo json_encode(array('status'=>1,'message'=>'Invalid data.')); exit; }
		        if (!preg_match('/\A[a-f\d]{16}\z/',$parentid)) { echo json_encode(array('status'=>1,'message'=>'Invalid data.')); exit; }

		        unset($storage['expire_date']); // Comment do not expire (it's the paste that expires)
		        unset($storage['opendiscussion']);
		        unset($storage['syntaxcoloring']);

		        // Make sure paste exists.
		        $storagedir = cmp_dataid2path($pasteid);
		        if (!is_file($storagedir.$pasteid)) { echo json_encode(array('status'=>1,'message'=>'Invalid data.')); exit; }

		        // Make sure the discussion is opened in this paste.
		        $paste=json_decode(file_get_contents($storagedir.$pasteid));
		        if (!$paste->meta->opendiscussion) { echo json_encode(array('status'=>1,'message'=>'Invalid data.')); exit; }

		        $discdir = cmp_dataid2discussionpath($pasteid);
		        $filename = $pasteid.'.'.$dataid.'.'.$parentid;
		        if (!is_dir($discdir)) mkdir($discdir,$mode=0705,$recursive=true);
		        if (is_file($discdir.$filename)) // Oups... improbable collision.
		        {
		            echo json_encode(array('status'=>1,'message'=>'You are unlucky. Try again.'));
		            exit;
		        }

		        file_put_contents($discdir.$filename,json_encode($storage), LOCK_EX);
		        echo json_encode(array('status'=>0,'id'=>$dataid)); // 0 = no error
		        exit;
		    }
		    else // a standard paste.
		    {
		        $storagedir = cmp_dataid2path($dataid);
		        if (!is_dir($storagedir)) mkdir($storagedir,$mode=0705,$recursive=true);
		        if (is_file($storagedir.$dataid)) // Oups... improbable collision.
		        {
		            echo json_encode(array('status'=>1,'message'=>'You are unlucky. Try again.'));
		            exit;
		        }
		        // New paste
		        file_put_contents($storagedir.$dataid,json_encode($storage), LOCK_EX);

		        // Generate the "delete" token.
		        // The token is the hmac of the pasteid signed with the server salt.
		        // The paste can be delete by calling http://myserver.com/zerobin/?pasteid=<pasteid>&deletetoken=<deletetoken>
		        $deletetoken = hash_hmac('sha1', $dataid , cmp_getServerSalt());

		        echo json_encode(array('status'=>0,'id'=>$dataid,'deletetoken'=>$deletetoken)); // 0 = no error
		        exit;
		    }

		echo json_encode(array('status'=>1,'message'=>'Server error.'));
		exit;
		}
	}

	/**
	 * Pour n'enregistrer des données que via ZB, décommenter ces lignes et remplacer les ? > par ?> et < ?php par <?php
	 */
	// public function AdminArticleContent() {
	// 	$string = "</div>

	// 					<div class=\"form_bottom\">
	// 						<p class=\"center\">
	// 							<?php echo plxToken::getTokenPostMethod() ? >
	// 							<input class=\"button preview\" type=\"submit\" name=\"preview\" onclick=\"this.form.target='_blank';return true;\" value=\"<?php echo L_ARTICLE_PREVIEW_BUTTON ? >\"/>
	// 							< ?php
	// 								if(\$_SESSION['profil']>PROFIL_MODERATOR AND \$plxAdmin->aConf['mod_art']) {
	// 									if(in_array('draft', \$catId)) { # brouillon
	// 										if(\$artId!='0000') # nouvel article
	// 											echo '<input class=\"button delete\" type=\"submit\" name=\"delete\" value=\"'.L_DELETE.'\" onclick=\"Check=confirm(\''.L_ARTICLE_DELETE_CONFIRM.'\');if(Check==false) {return false;} else {this.form.target=\'_self\';return true;}\" />';
	// 										echo '<input class=\"button\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"draft\" value=\"'.L_ARTICLE_DRAFT_BUTTON.'\"/>';
	// 										echo '<input class=\"button submit\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"moderate\" value=\"'.L_ARTICLE_MODERATE_BUTTON.'\"/>';
	// 									} else {
	// 										if(isset(\$_GET['a']) AND preg_match('/^_[0-9]{4}$/',\$_GET['a'])) { # en attente
	// 											echo '<input class=\"button delete\" type=\"submit\" name=\"delete\" value=\"'.L_DELETE.'\" onclick=\"Check=confirm(\''.L_ARTICLE_DELETE_CONFIRM.'\');if(Check==false) {return false;} else {this.form.target=\'_self\';return true;}\" />';
	// 											echo '<input class=\"button\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"draft\" value=\"'.L_ARTICLE_DRAFT_BUTTON.'\"/>';
	// 											echo '<input class=\"button update\" onclick=\"send_data();return false;\" type=\"submit\" name=\"update\" value=\"' . L_ARTICLE_UPDATE_BUTTON . '\"/>';
	// 										} else {
	// 											echo '<input class=\"button\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"draft\" value=\"'.L_ARTICLE_DRAFT_BUTTON.'\"/>';
	// 											echo '<input class=\"button submit\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"moderate\" value=\"'.L_ARTICLE_MODERATE_BUTTON.'\"/>';
	// 										}
	// 									}
	// 								} else {
	// 									if(\$artId!='0000')
	// 										echo '<input class=\"button delete\" type=\"submit\" name=\"delete\" value=\"'.L_DELETE.'\" onclick=\"Check=confirm(\''.L_ARTICLE_DELETE_CONFIRM.'\');if(Check==false) {return false;} else {this.form.target=\'_self\';return true;}\" />';
	// 									if(in_array('draft', \$catId)) {
	// 										echo '<input class=\"button\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"draft\" value=\"' . L_ARTICLE_DRAFT_BUTTON . '\"/>';
	// 										echo '<input class=\"button submit\" onclick=\"send_data();return false;\" type=\"submit\" name=\"publish\" value=\"' . L_ARTICLE_PUBLISHING_BUTTON . '\"/>';
	// 									} else {
	// 										if(!isset(\$_GET['a']) OR preg_match('/^_[0-9]{4}$/',\$_GET['a']))
	// 											echo '<input class=\"button submit\" onclick=\"send_data();return false;\" type=\"submit\" name=\"publish\" value=\"' . L_ARTICLE_PUBLISHING_BUTTON . '\"/>';
	// 										else
	// 											echo '<input class=\"button\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"draft\" value=\"' . L_ARTICLE_OFFLINE_BUTTON . '\"/>';
	// 										echo '<input class=\"button update\" onclick=\"this.form.target=\'_self\';return true;\" type=\"submit\" name=\"update\" value=\"' . L_ARTICLE_UPDATE_BUTTON . '\"/>';
	// 									}
	// 								}
	// 							? >
	// 						</p>
	// 					</div>

	// 				</div><!-- extra-content -->

	// 			</div><!-- extra container -->

	// 		</form>
	// 		<?php ob_start();? >";

	// 	echo $string;
	// }
	// public function AdminArticleFoot() {
	// 	echo '<?php ob_end_clean();? >';
	// }
}
?>