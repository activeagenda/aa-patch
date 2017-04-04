<?php
/**
 * Streams an uploaded file to the user, after checking login and permissions
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

//first check that the user is logged in and such
//main include file - performs all general application setup
require_once(INCLUDE_PATH . '/page_startup.php');
//(this gave us $ModuleID from the 'mdl' GET parameter)
// Library for mime type handling
require_once(INCLUDE_PATH . '/mime_type_lib.php');
require_once(INCLUDE_PATH . '/utf8.class.php');

//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = "'".substr($_GET['rid'], 0, 5)."'";
    }
}

//get the file ID (AttachmentID)
$fileID = intval($_GET['fid']);

//next check the user's permission to the associated module & record 
if(0 == $User->PermissionToViewRecord($ModuleID, $recordID)){
    trigger_error(gettext("You don't have permission to view this file."), E_USER_ERROR);
}

if ( $User->checkRecordPermission( $ModuleID, $recordID) == 0 ){
	trigger_error(gettext("You don't have permission to view this file."), E_USER_ERROR);
};

if( $fileID == 0){
	$fh = fopen( $theme_web.'/img/transparent.gif', 'r' ) or die( "can't open file transparent.gif: $php_errormsg" );
	header("Content-Type: image/gif");
	while(! feof($fh)){
        print fgets($fh, 1024);
    }
    fclose($fh) or die("can't close file $fileName: $php_errormsg");
	exit;
}

//build the file Name
$fileName = UPLOAD_PATH . "/{$ModuleID}/att_{$ModuleID}_{$recordID}_{$fileID}.dat";

//get the file info from database

//we could have simply asked for $fileID but we want to make sure ModuleID and RecordID match, especially for permission purposes
$SQL = "SELECT FileName FROM att WHERE RelatedModuleID = '$ModuleID' AND RelatedRecordID = $recordID AND AttachmentID = $fileID";

$r = $dbh->getRow($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

$saveAsName = $r['FileName'];
// hack due to browser problems
$saveAsName = str_replace( array('"', "'", ' ', ','), '_', $saveAsName );
if( $User->browserInfo['is_IE'] ){
	switch( $User->Lang ){
	case 'pl_PL':
		$utfConverter = new utf8( CP1250 );
		$saveAsName = $utfConverter->utf8ToStr( $saveAsName );		
		break;
	}
	
}
if( file_exists($fileName) ){

    $fh = fopen( $fileName, 'r' ) or die( "can't open file $fileName: $php_errormsg" );

    $mimeType = get_file_mime_type( $fileName );
    //trace("Mime type for file $fileName is $mimeType.");
	
	if( $_GET['pmb'] == 1 ){
		header("Content-Disposition: inline; filename=$saveAsName");
		switch( $mimeType ){
			case "image/gif":
			case "image/jpeg":
			case "image/png":
			case "image/x-icon":
				header("Content-Type: $mimeType");
				header('Pragma:');
				header('Cache-Control: must-revalidate');
				$etag = '"'.md5( filemtime($fileName).$saveAsName ) .'"';
				header('ETag: '.$etag);
				$headers = getallheaders();
				if( $etag === $headers['If-None-Match']  ){
					header('HTTP/1.1 304 Not Modified');
					exit;
				}	
				break;
			default:
				$fh = fopen( $theme_web.'/img/no_picture.png', 'r' ) or die( "can't open file no_picture.png: $php_errormsg" );
				header("Content-Type: image/png");
		}		
	}else{ 		 
		header("Content-Type: $mimeType");
		header("Content-Description: File Transfer");
		header('Content-Disposition: attachment; filename="'.$saveAsName.'"', true);
	}
	
    //print_r(headers_sent());
    while(! feof($fh)){
        print fgets($fh, 1024);
    }
    fclose($fh) or die("can't close file $fileName: $php_errormsg");

} else {
	$title = gettext("There was a problem");
    $content = '<p>&nbsp;&nbsp;'.gettext("The file could not be found on the system.").'</p>';
    $content .= '<p>&nbsp;&nbsp;'.gettext("This is probably not because of something you did, but a problem on the server.  This problem has been logged, and an administrator will hopefully fix it soon.").'</p>';

    trigger_error("File not found: $fileName", E_USER_WARNING);
    include_once($theme . '/popup.template.php');
}
?>
