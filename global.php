<?php
/**
 * Handles content for 'global' screens
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

//classes
require_once CLASSES_PATH . '/grids.php';
include_once CLASSES_PATH . '/modulefields.php';

//startup
require_once INCLUDE_PATH . '/page_startup.php';
include_once $theme .'/component_html.php';

$messages = array(); //init

//get the record ID
$recordID = intval($_GET['rid']);

$tabsQSargs = $qsArgs;
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);

$tabsQS = MakeQS($tabsQSargs);

//get the GlobalModule ID
$GlobalModuleID = substr(addslashes($_GET['gmd']), 0, 5); //any valid module id
unset( $_GET['gmd'] );

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

//shortcuts functionality
$linkHere = "global.php?mdl=$ModuleID&amp;rid=$recordID&amp;gmd=$GlobalModuleID";
$encodedLinkHere = 'frames.php?dest='.base64_encode( $linkHere );

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


//sets $editGrid, $gridPluralName:
$grid_filename = GENERATED_PATH . "/{$GlobalModuleID}/{$GlobalModuleID}_GlobalEditGrid.gen";

//check for cached page
if (!file_exists($grid_filename)){
    trigger_error("Could not find grid file '$grid_filename'.", E_USER_ERROR);
}

//sets $editGrid, $gridPluralName
include $grid_filename;

//insert dynamic data
$replFields = array('/**DynamicModuleID**/', '/**PR-ID**/');
$replValues = array($ModuleID, $recordID);

$editGrid->insertSQL    = str_replace($replFields, $replValues, $editGrid->insertSQL);
$editGrid->updateSQL    = str_replace($replFields, $replValues, $editGrid->updateSQL);
$editGrid->deleteSQL    = str_replace($replFields, $replValues, $editGrid->deleteSQL);
$editGrid->logSQL       = str_replace($replFields, $replValues, $editGrid->logSQL);
$editGrid->ParentRowSQL = str_replace($replFields, $replValues, $editGrid->ParentRowSQL);
$editGrid->SMCSQL       = str_replace($replFields, $replValues, $editGrid->SMCSQL);

//handle grid form
$editGrid->handleForm();

//get label data
$labels = array(
        'act' => gettext("Actions"),
        'att' => gettext("Attachments"),
        'cos' => gettext("Tags"),
        'lnk' => gettext("Links"),
		'rmd' => gettext("Reminders"),
        'nts' => gettext("Notes")
    );

//display edit grid
$linkHere = "global.php?mdl=$ModuleID&amp;rid=$recordID&amp;gmd=$GlobalModuleID";
$moduleInfo =& GetModuleInfo($ModuleID);
$content = renderLabelFields( $ModuleID, $recordID, $linkHere, $labels[$GlobalModuleID], $User->PersonID );
$content .= '<div id="enfl"></div>';
$content .= $editGrid->render('global.php', $qsArgs);

if( !$User->Client['is_Mobile'] ){
	//$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar_stripped.js"></script>'."\n";
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar.js"></script>'."\n";
	$LangPrefix = substr($User->Lang, 0, 2);
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/lang/calendar-'.$LangPrefix.'.js"></script>'."\n";
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar-setup_stripped.js"></script>'."\n";
}

if( $moduleInfo->getProperty('globalDiscussionAddress') ){
	$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
}
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

if( 0 < strlen($_GET['sr']) ){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'global.php');
	$nextLink = preg_replace( '/grw=\d+/','',  $nextLink );
	$nextLink = preg_replace( '/gid=\d+/','',  $nextLink );
	$prevLink = preg_replace( '/grw=\d+/','',  $prevLink );
	$prevLink = preg_replace( '/gid=\d+/','',  $prevLink );	
}
$siteNavigationSnip = null;
if( !empty($prevLink) ){
	$siteNavigationSnip .= '<div id="stNvSn"><a  id="arrowLeft" href="'.$prevLink.'" title="'.gettext("previous").'">&nbsp;&nbsp;<img src="'.$theme_web.'/img/seq_prev.png" alt="'.gettext("previous").'"/></a> ';
}else{ 
	$siteNavigationSnip .=  '<div id="stNvSn">&nbsp;&nbsp;&nbsp;<img src="'.$theme_web.'/img/transparent.png"/> ';
} 
if( isset($tabs['List'][0]) ){
	$siteNavigationSnip .= '<a class="arrowTab" href="'.$tabs['List'][0].'" title="&nbsp; &nbsp; '.gettext("List").' &nbsp; &nbsp;"><img src="'.$theme_web.'/img/list_viewlist.png" alt="'.gettext("List").'"/>&nbsp;&nbsp;</a>';
}
if( !empty($nextLink) ){
	$siteNavigationSnip .= ' <a  id="arrowRight" href="'.$nextLink.'" title="'.gettext("next").'"><img src="'.$theme_web.'/img/seq_next.png" alt="'.gettext("next").'"/>&nbsp;&nbsp;</a></div>';
}else{ 
	$siteNavigationSnip .=  ' <img src="'.$theme_web.'/img/transparent.png"/></div>';
} 
$content .= $siteNavigationSnip;


//$jsIncludes
$title = gettext($singularRecordName).'<span class="esgn"> &#10143; </span>'.gettext($gridPluralName);
$subtitle = sprintf(gettext("Manage %s for this %s:"), gettext($gridPluralName), gettext($singularRecordName));
//$user_info
$screenPhrase = ShortPhrase($screenPhrase);

//shortcuts functionality
//$linkHere = "global.php?mdl=$ModuleID&amp;rid=$recordID&amp;gmd=$GlobalModuleID";
$plainLink = str_replace('&amp;', '&', $linkHere);
//$scTitle = reduce_whitespace($title.': '.$recordLabel);
$scTitle = reduce_whitespace( $recordLabel );

$JSredirect= '';
if(!empty($_GET['shortcut'])){
    if(!empty($_GET['sctitle'])){
        $scTitle = $_GET['sctitle'];
    }
    switch($_GET['shortcut']){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $plainLink, $labels[$GlobalModuleID], $ModuleID);
		$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>';
        break;
    case 'remove':
        RemoveDesktopShortcut($User->PersonID, $plainLink);
		$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>';
        break;
    default:
        //nada
    }
}

$dash_shortcutTitle = '';
if(isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$plainLink])){
    $dash_shortcutTitle = $_SESSION['desktopShortcuts'][$plainLink];
}
if(empty($dash_shortcutTitle)){
    $dash_shortcutTitle = $scTitle;
    $hasShortcut = false;
} else {
    $hasShortcut = true;
}


//$messages  //any error messages, acknowledgements etc.
//$content
//$globalDiscussions
//$localDiscussions

//debug stuff:
//$content .= debug_r($labelSQL);
$parentInfo = GetParentInfo( $ModuleID );
$existing = true;

include_once $theme . '/edit.template.php';

AddBreadCrumb("$recordLabel <br/>($title)");
