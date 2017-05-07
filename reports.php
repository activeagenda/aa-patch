<?php
/**
 * Reports screen: lists available reports
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

//must precede page_startup.php
include_once CLASSES_PATH . '/search.class.php';

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

include_once $theme .'/component_html.php';

//main business here
$pageTitle = '';

//if no record id, assume this is for list reports

//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        //$recordID = "'".substr($_GET['rid'], 0, 5)."'";
        $recordID = substr($_GET['rid'], 0, 5);
    }
}

$tabsQSargs = $qsArgs;
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);

$tabsQS = MakeQS($tabsQSargs);

$target = '';
if($User->browserInfo['is_IE']){
	$target = ' target="_blank"';
}

if( !isset( $_SESSION['User']->ModulesPerm->$ModuleID )  ){
	$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_EditScreenPermissions.gen";
	if ( !file_exists($filename) ){
		trigger_error(gettext("Could not find file:")." '$filename'. ", E_USER_ERROR);
	}
	include($filename);
	
	$_SESSION['User']->ModulesPerm->$ModuleID = '';
	$_SESSION['User']->ModulesNoPerm->$ModuleID = '';
	if( !empty($EditScrPermission ) ){
		foreach( $EditScrPermission as $EditScreenName => $PermissionModuleID ){
			if( 0 == $User->PermissionToEdit( $PermissionModuleID ) ){
				unset($EditScrPermission[$EditScreenName]);
				$NoEditScrPermission[$EditScreenName]= $PermissionModuleID;
			}	
		}	
		$_SESSION['User']->ModulesPerm->$ModuleID = $EditScrPermission;
		$_SESSION['User']->ModulesNoPerm->$ModuleID = $NoEditScrPermission;
	}
	$User = $_SESSION['User'];	
}else{
	$EditScrPermission = $User->ModulesPerm->$ModuleID;
	$NoEditScrPermission = $User->ModulesNoPerm->$ModuleID; 
}

//$content = renderLabelFields( $ModuleID, $recordID );
$linkHere = "reports.php?mdl=$ModuleID&amp;rid=$recordID";
$content = renderLabelFields( $ModuleID, $recordID, $linkHere, gettext('Record reports'), $User->PersonID );
$str_recordID = '&amp;rid='.$recordID;

$SQL = "SELECT Name, Title, LocationLevel, LocationGroup, Format FROM modrp 
 WHERE ModuleID = '$ModuleID' AND LocationLevel = 'record' ORDER BY LocationGroup, Title";
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

if(count($r) > 0){
    $currentGroup = $r[0]['LocationGroup'];
    $content .= '<h1>'.gettext($currentGroup).'</h1>';
    foreach($r as $row){
        if($currentGroup != $row['LocationGroup']){
            $currentGroup = $row['LocationGroup'];
            $content .= '<h1>'.gettext($currentGroup).'</h1>';
        }
        switch( strtolower( $row['Format'] ) ){
        case 'pdf':
            $viewerURL = "reportPDFViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-pdf.png" alt="pdf"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'xls':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-spreadsheet.png" alt="xls"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'mm':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-mindmap.png" alt="mm"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'doc':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-editor.png" alt="doc"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
		case 'mp':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $fileicon = '<img src="'.$theme_web.'/img/dl-project.png" alt="mpp"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;	 
        case 'html-linear':
        default:
            $viewerURL = "frames_popup.php?dest=reportHTMLViewer&amp;mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $target = 'target="_blank"';
			$fileicon = '<img src="'.$theme_web.'/img/dl-html.png" alt="html"/>';
			$content .= '<p><div class="dl_icon">'."<a href=\"#\" onclick=\"javascript:window.open('$viewerURL', '_newtab', 'toolbar=0,resizable=1,scrollbars=1')\">".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
            break;
        }
       
    }
} 

//generic tabs
$tabs = array(); 
$tabs['List'] = Array("list.php?$tabsQS", gettext("List|View the list"));

include_once GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Tabs.gen";
if ( !$User->BoolCheckEditScreenPermission() AND  empty( $NoEditScrPermission) ){
	$tabListTmp = $tabs['List'];
	$tabViewTmp = $tabs['View'];
	if( isset( $tabs['RecordReports'] ) ){
		$tabRecordReports = $tabs['RecordReports'];
	}
	$tabs = NULL;
	$tabs['List'] = $tabListTmp ;
	$tabs['View'] = $tabViewTmp;
	if( isset( $tabRecordReports ) ){
		 $tabs['RecordReports'] = $tabRecordReports;
	}
}else{	
	if( !empty($NoEditScrPermission) ){
		foreach( $NoEditScrPermission as $EditScreenName=> $NoPermissionModuleID){
			if( !empty($tabs[$EditScreenName]) ) unset( $tabs[$EditScreenName] );
		}
	}
}

$title = '<span class="rcrdLbl">'.$recordLabel.'</span><span class="esgn"> &#9654; </span>'.gettext('Record reports');
$titleCrumbs = $recordLabel.'<span class="esgn">&nbsp; &#9654; &nbsp;</span>'.gettext('Record reports');
$recordLabel = gettext($singularRecordName);
$scTitle = $recordLabel;
$scType = gettext('Record reports');

$ScreenName = addslashes($_GET['scr']);
if(in_array($ScreenName, array_keys($tabs))){
    $tabs[$ScreenName] = array('', $tabs[$ScreenName][1]);
} else {
    $tabs['Reports'] = array('', gettext('Reports'));
}

require_once(CLASSES_PATH . '/moduleinfo.class.php');
$moduleInfo = GetModuleInfo($ModuleID);
if( $moduleInfo->getProperty('globalDiscussionAddress') ){
	$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
}
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');


$linkHere = "reports.php?mdl=$ModuleID&amp;rid=$recordID";
$encodedLinkHere = 'frames.php?dest='.base64_encode( $linkHere );

$JSredirect= '';
if( !empty($_GET['sctitle'])){
    $scTitle = $_GET['sctitle'];
}
if(!empty($_GET['shortcut'])){
    switch($_GET['shortcut']){
    case 'set':
		$plainLink = str_replace('&amp;', '&', $linkHere);
        SaveDesktopShortCut($User->PersonID, $scTitle, $plainLink, $scType, $ModuleID);
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
if(isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$linkHere])){
    $dash_shortcutTitle = $_SESSION['desktopShortcuts'][$linkHere];
}
if( empty($dash_shortcutTitle) ){
    //$dash_shortcutTitle = $recordLabel;
	$dash_shortcutTitle = $scTitle;
    $hasShortcut = false;
}else{
    $hasShortcut = true;
}

if( 0 < strlen($_GET['sr']) ){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'reports.php');
}

//$tabs;
//$generalTabs;
//$screenPhrase = '';
//$content;
$parentInfo = GetParentInfo( $ModuleID );

include_once $theme . '/report.template.php';
AddBreadCrumb("$recordLabel $titleCrumbs");
?>