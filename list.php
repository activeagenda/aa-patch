<?php
/**
 * Handles content for the List Screen
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

//this include contains the search class
include_once(CLASSES_PATH . '/search.class.php');

//contains the list rendering classes
include_once(CLASSES_PATH . '/lists.php');

if( !empty($_GET['rpc']) ){
    define('IS_RPC', true);
}

//main include file - performs all general application setup
require_once(INCLUDE_PATH . '/page_startup.php');

//initialize tabs
$tabs = Array();

$listFieldsFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ListFields.gen";

//main business here
$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_List.gen";

//check for cached page for this module
if( !file_exists($listFieldsFileName) ){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$listFieldsFileName'.", E_USER_ERROR);
}
if( !file_exists($filename) ){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$filename'.", E_USER_ERROR);
}

$downloadtarget = '';
if( $User->browserInfo['is_IE'] ){
	$downloadtarget = ' target="_blank"';
}

//the included file sets $content variable used by template below
include_once $listFieldsFileName;
include_once $filename;


$moduleInfo = GetModuleInfo($ModuleID);
if( $moduleInfo->getProperty('globalDiscussionAddress') ){
	$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
}
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

//shortcuts functionality
$linkHere = "list.php?mdl=$ModuleID";
$encodedLinkHere = 'frames.php?dest='.base64_encode( $linkHere );
if( empty($_GET['sctitle']) ){
    $scTitle = $pageTitle;
}else{
    $scTitle = $_GET['sctitle'];
}
//TODO: Add check for shorcut
$JSredirect='';
if( !empty($_GET['shortcut']) ){
    switch($_GET['shortcut']){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $linkHere, gettext('List'), $ModuleID);
		$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>';
        break;
    case 'remove':
        RemoveDesktopShortcut($User->PersonID, $linkHere);
		$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>'; 
		break;
    default:
        //nada
    }
}

$dash_shortcutTitle = '';
if( isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$linkHere]) ){
    $dash_shortcutTitle = $_SESSION['desktopShortcuts'][$linkHere];
}
if( empty($dash_shortcutTitle) ){
    $dash_shortcutTitle = $scTitle;
    $hasShortcut = false;
}else{
    $hasShortcut = true;
}

$origPageTitle = $pageTitle;


if( $search->hasConditions() ){
    if($search->isUserDefault){
        $pageTitle .= '&nbsp; [&nbsp;'.gettext("default filter").'&nbsp;] ';
    } else {
        $pageTitle .= '&nbsp; [&nbsp;'.gettext("custom filter").'&nbsp;] ';
    }
}else{
    $pageTitle .= '&nbsp; [&nbsp;'.gettext("all records").'&nbsp;] ';
}

$parentInfo = GetParentInfo($ModuleID, DISPLAY_RELATIONS);

//names required by template &Xi;
$title = $pageTitle;
$moduleID = $ModuleID;

//module footnotes
if( $User->PermissionToEdit( 'modfn') ==  2 ){
	$fnAllowEdit = true;
}else{
	$fnAllowEdit = false;
}

$modfnSQL = "SELECT Footnote FROM `modfn` WHERE _Deleted=0 AND FootnoteVisibility=1 AND RelatedModuleID='".$ModuleID
."' ORDER BY FootnoteDisplayorder";
$r = $dbh->getAll( $modfnSQL, DB_FETCHMODE_ASSOC );
dbErrorCheck($r);
$modFootnotes = '';
if( count( $r )>0 ){
	$modFootnotes = '<div id="modfn">';
	$modFootnotes .= '<img src="'.$theme_web.'/img/megaphone.png"/>';
	$modFootnotes .= '<ul>';
	foreach( $r as $row){
		$modFootnotes .= '<li>'.$row['Footnote'].'</li>';
	}
	$modFootnotes .= '</ul></div>';
}

// SDev
$target = '';
if( $User->browserInfo['is_IE'] OR $User->Client['is_Mobile'] ){
	$target = ' target="_blank"';
}
$listReports = '<div><h1 class="srpr"></h1>';
$listReports .= '<div class="dl_icon">';
$listReports .= '<a href="dataDownload.php?type=1&amp;mdl='.$ModuleID.'"'.$target.' title="&nbsp; &nbsp; '.gettext("Download as Comma-separated Values (flat file)").' &nbsp; &nbsp;"><img src="'.$theme_web.'/img/dl-csv.png"/><br />';
$listReports .= gettext("CSV");
$listReports .= '</a></div>';

$listReports .= '<div class="dl_icon">';
$listReports .= '<a href="dataDownload.php?type=3&amp;mdl='.$ModuleID.'"'.$target.' title="&nbsp; &nbsp; '.gettext("Download as a spreadsheet file").' &nbsp; &nbsp;"><img src="'.$theme_web.'/img/dl-spreadsheet.png"/><br />';
$listReports .= gettext("Spreadsheet");
$listReports .= '</a></div></div>';

$SQL = "SELECT Name, Title, LocationLevel, LocationGroup, Format FROM modrp 
 WHERE ModuleID = '$ModuleID' AND LocationLevel = 'list' ORDER BY LocationGroup, Title";
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

if(count($r) > 0){
    $currentGroup = $r[0]['LocationGroup'];
    $listReports .= '<h1 class="srpr"></h1>';	
    foreach($r as $row){
        if($currentGroup != $row['LocationGroup']){
            $currentGroup = $row['LocationGroup'];
            $listReports .= '<h1>'.gettext($currentGroup).'</h1>';
        }
        switch( strtolower( $row['Format'] ) ){
        case 'pdf':
            $viewerURL = "reportPDFViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-pdf.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Download list as a Acrobat pdf file")." &nbsp; &nbsp;\" href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'xls':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-spreadsheet.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Download list as a Microsoft Excel 2003 XML file")." &nbsp; &nbsp;\" href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'mm':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-mindmap.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Download list as a Freemind file")." &nbsp; &nbsp;\"href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'doc':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-editor.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Download list as a Microsoft Word 2003 XML file")." &nbsp; &nbsp;\" href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'mp':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-project.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Download list as a Microsoft Project file")." &nbsp; &nbsp;\"href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;	 
        case 'html-linear':
        default:
            $viewerURL = "frames_popup.php?dest=reportHTMLViewer&amp;mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            //$target = 'target="_blank"';
			$fileicon = '<img src="'.$theme_web.'/img/dl-html.png"/>';
			$listReports .= '<p><div class="dl_icon">'.'<a '.' title="&nbsp; &nbsp; '.gettext("Open list as a web report")." &nbsp; &nbsp;\"href=\"#\" onclick=\"javascript:window.open('$viewerURL', '_newtab', 'toolbar=0,resizable=1,scrollbars=1')\">".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
        }
       
    }
} 

//EDev

if( $_GET['rdct'] == 'charts' ){
	header( 'Location:charts.php?mdl='.$ModuleID );
    exit;
}
//$linkHere
include_once $theme . '/list.template.php';

AddBreadCrumb($origPageTitle . ' &#9654; '. gettext("List"));
?>