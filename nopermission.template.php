<?php
/**
 * HTML/PHP layout template for the NoPermission screen
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
setTimeStamp('template_rendering_startup');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="<?php echo $lang639_1;?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="<?php echo TINY_MCE_LANG; ?>">
	<meta name="robots" content="noindex, nofollow">
    <title><?php echo $title;?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css">
    <!--[if lt IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" >
    <![endif]-->
    <!--[if gte IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie7.css" >
    <![endif]-->
    
	<?php if( $User->Client['is_Mobile'] ){ ?>
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/mobile.css">
	<?php }else{ ?>
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/not_mobile.css">
	<?php } //end if ?>
	
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/menuG5.css">
	<!--Reload the page to renew the naviagation frame-->
	<?php echo $JSredirect; ?>
	
    <script type="text/javascript" src="js/lib.js"></script>
    <script type="text/javascript" src="js/tabs.js"></script>
</head>
<body>
    <div id="content" style="float:left;padding-left:25px">
        <div id="logo">
            <a href="<?php echo PROVIDER_LINK ?>" target="_blank"><img src="<?php echo $theme_web; ?>/img/logo_thumb.png" alt="logo"/></a>
        </div>
        <div class="tabclear">&nbsp;</div>
        <div style="width:500px">
            <h1 id="errtmp" class="pageTitle"><?php echo $title;?></h1>
            <?php echo $content;?>
           <br/><p><b><a href="#" onclick="history.go(-1);return false;"><?php echo gettext("Go Back"); ?></a>&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;&nbsp;<a href="home.php"><?php echo gettext("Go to the Home / Dashboard page"); ?></a></b></p>
        </div>
    </div> <!-- #content -->
<?php 
    include 'footer.snip.php';
?>
</body>
</html>
<?php  echo getDuration();?>
