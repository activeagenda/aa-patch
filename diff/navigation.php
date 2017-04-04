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
    $user_info = $User->DisplayName . ' ('.gettext("Admin").')';
} else {
    $user_info = gettext("User") .': '. $User->DisplayName;
}

include_once CLASSES_PATH . '/navigator.class.php';
include_once INCLUDE_PATH . '/navigation_include.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>Navigation</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/menuG5.css" />
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" />
    <![endif]-->
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print" />
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

        addInstance("Nav Menu", "Nav", "position:slot 6; menu-form:bar; align:left; valign:bottom;style:group");
        </script>
</head>
<body id="navframe" onload="initMenu('Nav Menu', 'top'); setSubFrame('Nav Menu', parent.main); showMenu('Nav Menu');">
<?php
    include_once($theme . '/navigation.snip.php');
?>
</body>
</html>