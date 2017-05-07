<?php
/**
 * HTML/PHP snippet for displying the "add shortcut" form
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2009 Active Agenda Inc., All Rights Reserved.
 *
 * Unless explicitly acquired and licensed from Licensor under another license, the
 * contents of this file are subject to the Reciprocal Public License ("RPL")
 * Version 1.5, or subsequent versions as allowed by the RPL, and You may not copy
 * or use this file in either source code or executable form, except in compliance
 * with the terms and conditions of the RPL. You may obtain a copy of the RPL from
 * Active Agenda Inc. at http://www.activeagenda.net/license.
 *
 * All software distributed under the RPL is provided strictly on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED, AND LICENSOR HEREBY
 * DISCLAIMS ALL SUCH WARRANTIES, INCLUDING WITHOUT LIMITATION, ANY WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, QUIET ENJOYMENT, OR
 * NON-INFRINGEMENT. See the RPL for specific language governing rights and
 * limitations under the RPL.
 *
 * author         Mattias Thorslund <mthorslund@activeagenda.net>
 * copyright      2003-2009 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

 
if(!defined('EXEC_STATE') || EXEC_STATE != 1){
    print gettext("This file should not be accessed directly.");
    trigger_error("This file should not be accessed directly.", E_USER_ERROR);
    exit;
}

$email_Subject = SanitizeEmailSubject( $dash_shortcutTitle );
$dash_shortcutTitle = preg_replace( '/[\x00-\x1F\x7F]/', '', strip_tags( $dash_shortcutTitle ) );
$dash_shortcutTitle = preg_replace( "/&#?[a-z0-9]+;/i","", $dash_shortcutTitle );
$protocol_server_ext_adrr = 'http://';
if( isset($_SERVER['HTTPS']) ) {
	$protocol_server_ext_adrr = 'https://'.SERVER_EXT_ADRR;
}else{
	$protocol_server_ext_adrr = 'http://'.SERVER_EXT_ADRR;
}
?>
        <script type="text/javascript" src="js/dashcut.js"></script>
        <div class="ds">
           
           
            <?php  if($hasShortcut){ ?>
			
            <p><a href="<?php echo $linkHere . '&amp;shortcut=remove';?>" title="&nbsp; &nbsp; <?php echo gettext("Click here to remove this page from the shortcuts on your home page."); ?> &nbsp; &nbsp;"><?php echo gettext("Remove shortcut"); ?>
			&#171;&nbsp;<img src="<?php echo $theme_web; ?>/img/nav_bugreport.png"/>&nbsp;<?php echo gettext("my Shortcuts"); ?>&nbsp;&#187;
			</p><p><?php echo $dash_shortcutTitle  ?></a></p>
			<?php  } else {
            ?>
            <p><a href="javascript:dash_showShortcutForm()" title="&nbsp; &nbsp; <?php echo gettext("Click here to add this page as a shortcut on your home page."); ?>&nbsp; &nbsp;"><?php echo  gettext("Add shortcut"); ?>
			&#171;&nbsp;<img src="<?php echo $theme_web; ?>/img/nav_bugreport.png"/>&nbsp;<?php echo gettext("my Shortcuts"); ?>&nbsp;&#187;</a></p>
            <?php  } ?>
            
            <div id="dash_shortcutForm" style="display:none">
                <input id="dash_shortcutTitle" name="dash_shortcutTitle" class="edts" size="15" maxlength="50" value="<?php echo  $dash_shortcutTitle; ?>"/>
                <input type="button" onclick="dash_saveShortcut('<?php echo $linkHere;?>')" value="<?php echo gettext("Save") ?>" class="btns" />
                <input type="button" onclick="dash_hideShortcutForm()" value="<?php echo gettext("Cancel") ?>" class="btns" />
            </div>
        </div>
		
		<div class="ds">
		 <p>
			<a href="<?php echo $encodedLinkHere;?>" target="_blank" title="<?php echo gettext("Useful for shortcuts, bookmarks, etc.");?>"><?php echo  gettext("Direct link to this page");?></a>:</p>
			<a href="mailto:<?php echo '?subject='.$email_Subject.'&body='.$protocol_server_ext_adrr.'/'.$encodedLinkHere ?>" title="&nbsp; &nbsp; <?php echo gettext("Send the link by email");?> &nbsp; &nbsp;">
			<img src="<?php echo $theme_web; ?>/img/email.png" id="emlnk"/></a>
			<p><input type="text" class="edtl" id="dspurl" size="32" onclick="this.select()" value="<?php echo $protocol_server_ext_adrr.'/'.$encodedLinkHere;?>" /></p>
            <?php if(!empty($xmlExportLink)){  ?>
                <p><a href="<?php echo $xmlExportLink;?>" <?php if( $User->browserInfo['is_IE'] ){echo 'target="_blank"';} ?> title="&nbsp; &nbsp; <?php echo gettext("Click here to download this record as an XML data file."); ?> &nbsp; &nbsp;"><?php echo gettext("XML Download"); ?></a>
                </p>
            <?php } ?>
		</div>