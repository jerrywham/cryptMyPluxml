<?php if(!defined('PLX_ROOT')) exit;
$plxMotor = plxMotor::getInstance();
$plxShow = plxShow::getInstance();
$plxPlugin=$plxMotor->plxPlugins->getInstance('cryptMyPluxml');

if (!empty($_GET['deletetoken']) && !empty($_GET['pasteid'])) // Delete an existing paste
{
    list ($plxPlugin->CIPHERDATA, $plxPlugin->ERRORMESSAGE, $plxPlugin->STATUS) = cmp_processPasteDelete(plxUtils::strCheck(plxUtils::nullbyteRemove($_GET['pasteid'])),plxUtils::strCheck(plxUtils::nullbyteRemove($_GET['deletetoken'])));
}
elseif (!empty($_SERVER['QUERY_STRING']))  // Return an existing paste.
{	
	$zb = preg_replace('!(a=[0-9]+&)*zb=!', '', plxUtils::getGets($_SERVER['QUERY_STRING']));
	$zb = str_replace(array('zb=','zb/'), '', $zb);
    list ($plxPlugin->CIPHERDATA, $plxPlugin->ERRORMESSAGE, $plxPlugin->STATUS) = cmp_processPasteFetch($zb);
}

?>

<div id="infoZB">(?)<br/>
	<div id="aboutbox">
		<?php echo $plxPlugin->getLang('L_ZB_DESC') ?>

	</div>
</div>
<noscript><div class="nonworking"><?php echo $plxPlugin->getLang('L_JS_REQUIRED'); ?></div></noscript>
<div id="oldienotice" class="nonworking"><?php echo $plxPlugin->getLang('L_MODERN_BROWSER'); ?></div>
<div id="ienotice"><?php echo $plxPlugin->getLang('L_STILL_IE'); ?> 
	<a href="http://www.mozilla.org/firefox/">Firefox</a>, 
	<a href="http://www.opera.com/">Opera</a>, 
	<a href="http://www.google.com/chrome">Chrome</a>, 
	<a href="http://www.apple.com/safari">Safari</a>...
</div>

<div id="status"><?php echo $plxPlugin->STATUS?></div>
<div id="errormessage" style="display:none"><?php echo $plxPlugin->ERRORMESSAGE?></div>
<div id="remainingtime" style="display:none;"></div>
<div id="pasteresult" style="display:none;">
	<div id="deletelink"></div>
	<div id="pastelink"></div>
</div>
<div id="cleartext" style="display:none;"></div>
<div id="discussion" style="display:none;">
	<h4>Discussion</h4>
	<div id="comments"></div>
</div>
<div id="cipherdata" style="display:none;"><?php echo $plxPlugin->CIPHERDATA?></div>