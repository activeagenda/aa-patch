<?php
/**
 * Handles redirection into two frames
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
require_once(INCLUDE_PATH . '/page_startup.php');

$dest = '';
if(!empty($_GET['dest'])){
    $dest = $_GET['dest'];
}
if(0 < strlen($dest)){
    $dest = base64_decode($dest);
    if(false !== strpos($dest, 'frames.php')){
        $dest = 'home.php';
    } else {
        //block some XSS (possible?) possibilities
        $dest = urldecode($dest);
        $dest = strtr($dest, "\r\n<>();", '       ');
        $dest = str_replace('&', '&amp;', $dest);
    }
} else {
    $dest = 'home.php';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Active Agenda | <?php echo SITE_NAME; ?></title>
    <link rel="shortcut icon" href="<?php echo $theme_web;?>/img/favicon.ico" />
	<script type="text/javascript" >
		var refreshinterval=<?php echo SESSION_DEFAULT_TIMEOUT*60-30; ?>;
	</script>
	<script type="text/javascript" src="js/keepalive.js"></script>	
    <script type="text/javascript">
        var scriptPath="3rdparty/menuG5/";
        var menuTimer=300;
        var showMessage=1;
        var inheritStyle=1;
    </script>
    <script type="text/javascript" src="3rdparty/menuG5/menuG5LoaderFSX.js"></script>
</head>
<frameset rows="50, *" framespacing="0" frameborder="0" border="0">
    <frame name="nav" src="navigation.php" scrolling="no" />
    <frame name="main" src="<?php echo $dest; ?>" />
</frameset>
</html>