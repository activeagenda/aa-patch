<?php
/**
 * Handles redirection for the popup frame
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


//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = "'".substr($_GET['rid'], 0, 5)."'";
    }
}

switch($_GET['dest']){
case 'list':
    $dest = "list.php?mdl=$ModuleID";
    break;
case 'view':
    $dest = "view.php?mdl=$ModuleID&rid=$recordID";
    break;
case 'edit':
    $dest = "edit.php?mdl=$ModuleID&rid=$recordID";
    break;
default:
    if(0 < strlen($_GET['dest'])){
        $dest = base64_decode($_GET['dest']);
    } else {
        $dest = 'home.php';
    }
}

//special case for Notifications
if('ntf' == $ModuleID){
    $RelatedModuleID = substr($_GET['relm'], 0, 5);
    $dest = "edit.php?mdl=ntf&relm=$RelatedModuleID&relr=".intval($_GET['relr']);
}

// check that user is logged in, otherwise ask user to re-login from main page
if (empty($_SESSION['User']) || intval($_SESSION['Timeout']) < time()) {
    die(gettext("Your session has timed out (or you have logged out). Please close this window and log in from the main window."));
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title>Active Agenda | <?php echo SITE_NAME; ?></title>
    <link rel="shortcut icon" href="<?php echo $theme_web;?>/img/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css" />
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" />
    <![endif]-->
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print">
</head>
<frameset id="wrapper" rows="30, *" bordercolor="#0c2577" framespacing="0" frameborder="0" border="0">
    <frame name="nav" src="popframe_close.php" framespacing="0" frameborder="0" border="0" noresize scrolling="no">
    <frame name="main" src="<?php echo $dest; ?>" frameborder="0" framespacing="0">
</frameset>
<?
//debug section: 
//print $dest;
?>
</html>