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
?>
        <script type="text/javascript" src="js/dashcut.js"></script>
        <div class="ds">
            <p><a href="<?php echo $linkHere;?>" title="<?php echo gettext("Useful for shortcuts, bookmarks, etc.");?>"><?php echo  gettext("Direct link to this page");?></a></p>
            <?php if(!empty($xmlExportLink)){  ?>
                <p><a href="<?php echo $xmlExportLink;?>" title="<?php echo gettext("Click here to download this record as an XML data file."); ?>"><?php echo gettext("XML Download"); ?></a>
                </p>
            <?php } ?>
            <p>
            <?php  if($hasShortcut){
                print gettext("Dashboard Shortcut: "). $dash_shortcutTitle;
            ?>
            <a href="<?php echo $linkHere . '&amp;shortcut=remove';?>" title="<?php echo gettext("Click here to remove this page from the shortcuts on your home page."); ?>">(<?php echo gettext("remove"); ?>)</a>
            <?php  } else {
            ?>
            <a href="javascript:dash_showShortcutForm()" title="<?php echo gettext("Click here to add this page as a shortcut on your home page."); ?>"><?php echo  gettext("Add shortcut"); ?></a>
            <?php  } ?>
            </p>
            <div id="dash_shortcutForm" style="display:none">
                <input id="dash_shortcutTitle" name="dash_shortcutTitle" class="edt" size="20" maxlength="50" value="<?php echo  $dash_shortcutTitle; ?>"/>
                <input type="button" onclick="dash_saveShortcut('<?php echo $linkHere;?>')" value="<?php echo gettext("Save") ?>" class="btn" />
                <input type="button" onclick="dash_hideShortcutForm()" value="<?php echo gettext("Cancel") ?>" class="btn" />
            </div>
        </div>