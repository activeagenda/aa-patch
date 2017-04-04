<?php
/**
 * HTML/PHP layout template for Popup screens
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

if(empty($screenPhrase)){
    $displayTitle = $title;
} else {
    $displayTitle = $title . " - " . $screenPhrase;
}

if(!isset($closeLink)){
    $closeLink = 'javascript:self.close();opener.focus();';
}
if(!isset($closeLabel)){
    $closeLabel = gettext("Close");
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
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print" />

    <!-- yahoo user interface library -->
    <script type="text/javascript" src="3rdparty/yui/yahoo/yahoo.js"></script>
    <script type="text/javascript" src="3rdparty/yui/event/event.js" ></script>
    <script type="text/javascript" src="3rdparty/yui/dom/dom.js" ></script>
    <script type="text/javascript" src="3rdparty/yui/container/container.js" ></script>
    <link type="text/css" rel="stylesheet" href="3rdparty/yui/container/assets/container.css" />

    <script type="text/javascript" src="js/lib.js"></script>
    <script type="text/javascript" src="js/tabs.js"></script>
    <?php echo  $jsIncludes; ?>
    <script type="text/javascript">
        YAHOO.namespace("activeagenda");
        YAHOO.util.Event.addListener(window, "load", attachTabEffects);
        YAHOO.util.Event.addListener(window, "load", setupTabTooltips);
    </script>
</head>
<body>
    <div id="popup_header">
        <div id="popupTitle"><?php echo $displayTitle;?></div>
        <div id="popupClose">
            <a href="<?php echo $closeLink;?>"><?php echo $closeLabel;?></a>
        </div>
    </div>
    <div id="fakebody">
    <div id="content_notitle">
        <div id="sideshim">
        <div id="sidearea">
            <div id="logo">
                <a href="http://www.activeagenda.net" target="_blank"><img src="<?php echo $theme_web; ?>/img/logo_thumb.png" /></a>
            </div>
<?php
            if(count($tabs) > 0){
?>
            <div id="tabwrapper">
                <div id="tabcontainer">
                    <div id="tabcontainer_inner">
<?php
                foreach ($tabs as $tab_key=>$tab_value) {
                    if(empty($tab_value[0])){
?>
                        <div class="tabsel">
                            <div class="tabb">
                                <?php echo  ShortPhrase($tab_value[1]); ?>
                            </div>
                        </div>
<?php
                    } else {
?>
                        <div class="tabunsel">
                            <a class="tabb" id="<?php echo 'tab'.$tab_key; ?>" href="<?php echo $tab_value[0]; ?>" title="<?php echo addslashes(LongPhrase($tab_value[1])); ?>"><?php echo ShortPhrase($tab_value[1]);?></a>
                        </div>
<?php
                    }
                }
?>
                    </div><!-- end tabcontainer_inner -->
                </div><!-- end tabcontainer -->
            </div><!-- end tabwrapper -->
<?php
            } //end count($tabs)
?>
        </div><!-- end sidearea -->
        </div><!-- end sideshim -->

        <?php echo  $content; ?>

    </div>
    </div>
</body>
</html>