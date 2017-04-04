<?php
/**
 * HTML/PHP layout template for the Home (Dashboard) screen
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
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo $title;?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css" />
    <!--[if lt IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" />
    <![endif]-->
    <!--[if gte IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie7.css" />
    <![endif]-->
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/menuG5.css" />


    <!-- yahoo user interface library -->
    <script type="text/javascript" src="3rdparty/yui/yahoo/yahoo.js"></script>
    <script type="text/javascript" src="3rdparty/yui/event/event.js" ></script>

    <script type="text/javascript" src="js/lib.js"></script>
    <script type="text/javascript" src="3rdparty/DataRequestor.js"></script>
    <script type="text/javascript" src="js/home.js"></script>
    <script type="text/javascript">
        var openedDashMenu;
    </script>
</head>
<body >
    <div id="content_notitle">
        <div id="dashMenuSection">
            <h1 id="dashboard_title"><?php echo $title;?></h1>
            <div class="dashMenu">
                <a id="act" href="#" onclick="togglePopOver('act')"><?php echo gettext("Actions"); ?> (<span id="act_count"><?php echo $dashGridInfo['act'];?></span>)</a>
            </div>
            <div class="dashMenu">
                <a id="acc" href="#" onclick="togglePopOver('acc')"><?php echo gettext("New Accountabilities"); ?> (<span id="acc_count"><?php echo $dashGridInfo['acc'];?></span>)</a>
            </div>
            <div class="dashMenu">
                <a id="usrds" href="#" onclick="togglePopOver('usrds')"><?php echo gettext("Shortcuts"); ?> (<span id="usrds_count"><?php echo $dashGridInfo['usrds'];?></span>)</a>
            </div>
            <div id="dashStretch">&nbsp;</div>
        </div>
        <?php echo $content;?>
    <?php
        if($User->IsAdmin){
            echo '<div style="clear:both;"><a href="#" onclick="open(\'checkServer.php\', \'checkServer\', \'toolbar=0,width=600,height=600\');">'.gettext("Check server configuration").'</a></div>';
        }
    ?>
    </div>
    <div id="popover" style="position:absolute; display:none; z-index:2; color: #0c2578">
        <div id="popover_close">
            <a id="close_link" href="#" onclick="closePopOver()" title="Close">
            <img src="<?php echo $theme_web; ?>/img/dashboard_popover_close.png" alt="Close" onmouseover="imgOver(this, '<?php echo $theme_web; ?>/img/dashboard_popover_close_o.png')" onmouseout="imgOut(this)"/>
            </a>
        </div>
        <div id="popover_bar">
            &nbsp;
        </div>
        <div id="popover_content">

        </div>
    </div>
<?
    include 'footer.snip.php';
?>
</body>
</html>