<?php
/**
 * Navigation frame and navigation menu setup
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


//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

if ($User->IsAdmin) {
    $user_info = $User->DisplayName . ' ( <span style="color:orange">'.gettext("Admin").'</span> )';
} else {
    $user_info = $User->DisplayName.' ('.$User->Login.')';
}

include_once CLASSES_PATH . '/navigator.class.php';
include_once CLASSES_PATH . '/shortcuts.class.php';
include_once INCLUDE_PATH . '/navigation_include.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="<?php echo $lang639_1;?>">
<head>
    <title>Navigation</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="<?php echo TINY_MCE_LANG; ?>">
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/menuG5.css">
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" >
    <![endif]-->
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print">
    <script type="text/javascript" src="3rdparty/menuG5/menuG5FX.js"></script>
    <script type="text/javascript">
        <?php echo $menuCode;?>

        addStylePad("pad", "item-offset:-1; offset-top:1;");
        addStylePad("padSub", "item-offset:-1; offset-top:6; offset-left:-6;scroll:y-only");

        addStyleItem("itemTop", "css:itemTopOff, itemTopOn;");
        addStyleItem("itemSub", "css:itemSubOff, itemSubOn;");
        addStyleItem("itemCat", "css:itemCatOff, itemCatOn;");

        addStyleFont("fontTop", "css:fontOff, fontOn;");
        addStyleFont("fontSub", "css:fontOff, fontOn;");
        addStyleFont("fontCat", "css:fontCatOff, fontCatOn;");

        addStyleTag("tag", "css:tagOff, tagOn;");

        addStyleMenu("menu", "pad", "itemTop", "fontTop", "", "", "");
        addStyleMenu("sub", "pad", "itemSub", "fontSub", "tag", "", "");
        addStyleMenu("catSM", "", "itemCat", "fontCat", "tag", "", "");

        addStyleGroup("group", "menu", 'nav-top');
        addStyleGroup("group", "sub", 'nav-top_1');
        addStyleGroup("group", "catSM", "cat");

        addInstance("Nav Menu", "Nav", "position:slot 6; menu-form:bar; offset-left:5; align:left; valign:bottom;style:group");
        
		<?php echo $shortcutsCode;?>
		addStyleGroup("group", "menu", 'dsc-top');
        addStyleGroup("group", "sub", 'dsc-top_1');
		addInstance("Dsc Menu", "Dsc", "position:relative nav_shortcuts; menu-form:bar; offset-top:50; align:left; valign:bottom; style:group");
		
		function refreshThePage(){
			parent.frames[1].location.href = parent.frames[1].location.href.replace( /\&iVlp=\d+/g, '' ).replace( /\#\w+$/g, '' );
		}
		</script>
</head>
<body id="main_navframe" onload="initMenu('Nav Menu', 'top'); setSubFrame('Nav Menu', parent.main); showMenu('Nav Menu');
 initMenu('Dsc Menu', 'top'); setSubFrame('Dsc Menu', parent.main); showMenu('Dsc Menu');">
<?php
    include_once($theme . '/navigation.snip.php');
?>
</body>
</html>