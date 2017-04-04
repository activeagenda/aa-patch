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

unset( $_GET['iVlp'] );
unset( $qsArgs['iVlp'] );
$passViewPosition = '';
if( isset( $_GET['oVlp'] ) ){
	$passViewPosition = '&oVlp='.$_GET['oVlp'];
}
unset( $_GET['oVlp'] );
unset( $qsArgs['oVlp'] );

$popupDestination = $_GET;
unset( $popupDestination['dest'] );
unset( $popupDestination['scr'] );
$popupDestinationParams = MakeQS( $popupDestination );
switch($_GET['dest']){
case 'list':
    $dest = "list.php?$popupDestinationParams";
	$CloseRefresh = 'clrf=0';
    break;
case 'view':
    $dest = "view.php?$popupDestinationParams";
	$CloseRefresh = 'clrf=0';
    break;
case 'edit':
    $dest = "edit.php?$popupDestinationParams";
	$CloseRefresh = 'clrf=1';
    break;
case 'reportHTMLViewer':
    $dest = "reportHTMLViewer.php?$popupDestinationParams";
	$CloseRefresh = 'clrf=0';
    break;
default:
    if(0 < strlen($_GET['dest'])){
        $dest = base64_decode($_GET['dest']);
		$CloseRefresh = 'clrf=0';
    } else {
        $dest = 'home.php';
		$CloseRefresh = 'clrf=0';
    }
}

$clrf_passViewPosition=$CloseRefresh.$passViewPosition;

// check that user is logged in, otherwise ask user to re-login from main page
if (empty($_SESSION['User']) || intval($_SESSION['Timeout']) < time()) {
    die(gettext("Your session has timed out (or you have logged out). Please close this window and log in from the main window."));
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="<?php echo TINY_MCE_LANG; ?>">
	<meta name="robots" content="noindex, nofollow">
    <title>\</title>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon"> 
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0">   
    <!--[if IE]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" >
    <![endif]-->
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print">
</head>
<frameset id="wrapper" rows="30, *" bordercolor="#0c2577" framespacing="0" frameborder="0" border="0">
    <frame name="nav" src="popframe_close.php?<?php echo $clrf_passViewPosition; ?>" framespacing="0" frameborder="0" border="0" noresize scrolling="no">
    <frame name="main" src="<?php echo $dest; ?>" frameborder="0" framespacing="0">
</frameset>
<?
//debug section: 
//print $dest;
?>
</html>