<?php
/**
 * Handles content for the Edit Screen
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
include_once CLASSES_PATH . '/grids.php';
//    include_once CLASSES_PATH . '/components.php';  //grids include this
include_once CLASSES_PATH . '/modulefields.php';

include_once $theme .'/component_html.php';

// Default value rendering only on new
global $existing;

//get the record ID
$recordID = 0;
if( isset($_GET['rid']) ){
    $recordID = intval($_GET['rid']);
    if( $recordID == 0 ){
        if( strlen($_GET['rid']) >= 3 ){
            $recordID = substr($_GET['rid'], 0, 5);
        }
    }
}


$ScreenName = addslashes($_GET['scr']);
$jsIncludes = '';
$screenPhrase = '';

$moduleInfo = GetModuleInfo($ModuleID);

//if no screen name was supplied, go to the first screen
if( empty($ScreenName) ){
    $ScreenName = $moduleInfo->getProperty('firstEditScreen');
}else{
    //validate the supplied ScreenName
    $includeFile = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ScreenList.gen";
    if( file_exists($includeFile) ){
        include $includeFile; //provides $screenList
        if( !isset($screenList[$ScreenName]) ){
            trigger_error(gettext("The address that you typed or clicked is invalid.|This module has no screen by the name")." '$ScreenName'.", E_USER_ERROR);
        }
        if( 'editscreen' != $screenList[$ScreenName] ){
            trigger_error("'$ScreenName' ".gettext("is not an EditScreen (it's a")." '{$screenList[$ScreenName]}').", E_USER_ERROR);
        }
    }else{
        trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find a Screen List to verify the requested screen."), E_USER_ERROR);
    }
}

$NoEditScrPermission = '';
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

if( !empty( $recordID) ){
	if ( $User->checkRecordPermission( $ModuleID, $recordID) == 0 ){
		trigger_error(gettext("You don't have permission to view this record."), E_USER_ERROR);
	};
};

$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Edit{$ScreenName}.gen";
//check for cached page for this module
if( !file_exists($filename) ){
    trigger_error(gettext("Could not find file:")." '$filename'. ", E_USER_ERROR);
}

$messages = array(); //init

//the included file sets $content variable used by template below
include($filename);
trace($getSQL, 'getSQL');

// XMLbase doesn't need this module
//include_once(GENERATED_PATH . '/moddr/moddr_GlobalViewGrid.gen');

if( !empty($ownerField) && isset($grid) ){ //unfortunate name returned by include above
    $directionCount = $grid->getRecordCount();
    if(intval($directionCount) > 0){
        $content .= sprintf(
            POPOVER_DIRECTIONS,
            ShortPhrase($grid->phrase),
            $grid->render('edit.php', $qsArgs)
            );
    }
} else {
    $directionCount = 0;
}
//print "Owner org: {$data[$ownerField]}\n";

//shortcuts functionality
$linkHere = "edit.php?mdl=$ModuleID&amp;rid=$recordID&amp;scr=$ScreenName";
$encodedLinkHere = 'frames.php?dest='.base64_encode( $linkHere );
$plainLink = str_replace('&amp;', '&', $linkHere);
if( empty( $recordLabelField ) ){
	$scTitle = gettext( $moduleInfo->getProperty('moduleName') );
	$scType = gettext('New record');
} else{
	$scTitle = $recordLabelField;
	$scType  = ShortPhrase($screenPhrase);
}
$JSredirect='';
if( !empty($_GET['shortcut']) ){
    if(!empty($_GET['sctitle'])){
        $scTitle = $_GET['sctitle'];
    }
    switch($_GET['shortcut']){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $plainLink, $scType, $ModuleID);
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
if( isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$plainLink]) ){
    $dash_shortcutTitle = $_SESSION['desktopShortcuts'][$plainLink];
}
if( empty($dash_shortcutTitle) ){
    $dash_shortcutTitle = $scTitle;
    $hasShortcut = false;
}else{
    $hasShortcut = true;
}

if( isset($guidanceGrid) ){
    $content .= sprintf(
        POPOVER_GUIDANCE,
        ShortPhrase($guidanceGrid->phrase),
        $guidanceGrid->render('edit.php', $qsArgs)
    );
}

unset($grid);
//DEV:include_once(GENERATED_PATH . '/res/res_GlobalViewGrid.gen');
if( !empty($ownerField) && isset($grid) ){ //unfortunate name returned by include above
    $resourceCount = $grid->getRecordCount();
    if(intval($resourceCount) > 0){
        $content .= sprintf(
            POPOVER_RESOURCES,
            ShortPhrase($grid->phrase),
            $grid->render('edit.php', $qsArgs)
            );
    }
}else{
    $resourceCount = 0;
}

if( !$User->Client['is_Mobile'] ){
	//$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar_stripped.js"></script>'."\n";
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar.js"></script>'."\n";
	$LangPrefix = substr($User->Lang, 0, 2);
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/lang/calendar-'.$LangPrefix.'.js"></script>'."\n";
	$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar-setup_stripped.js"></script>'."\n";
}
	
$jsIncludes .= '<script type="text/javascript">
function confirmDelete(sender){
	if(confirm(\''.gettext("Delete this record?").'\')){
		sender.form[\'Delete\'].value = "Delete";
		sender.form.submit();
	}
}
</script>'."\n";

if( $moduleInfo->getProperty('globalDiscussionAddress') ){
	$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
}
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

$screenPhrase = ShortPhrase($screenPhrase);

if( isset($_GET['sr']) ){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'edit.php');
	foreach( $tabs as $tabFieldName => $tabValue ){
		if( !empty( $tabs[$tabFieldName][0] ) ){
			if( $tabFieldName !== 'List' ){
				$tabs[$tabFieldName][0]= $tabs[$tabFieldName][0].'&amp;sr='.$_GET['sr'];
			}
		}
	}
}

$siteNavigationSnip = null;
if( !empty($prevLink) ){
	$siteNavigationSnip .= '<div id="stNvSn"><a  id="arrowLeft" href="'.$prevLink.'" title="'.gettext("previous").'"></a> ';
}else{ 
	$siteNavigationSnip .=  '<div id="stNvSn"><div class="plchldr"></div>';
} 
if( isset($tabs['List'][0]) ){
	$siteNavigationSnip .= '<a class="arrowTab" href="'.$tabs['List'][0].'" title="&nbsp; &nbsp; '.gettext("List").' &nbsp; &nbsp;"></a>';
}
if( !empty($nextLink) ){
	$siteNavigationSnip .= ' <a  id="arrowRight" href="'.$nextLink.'" title="'.gettext("next").'"></a></div>';
}else{ 
	$siteNavigationSnip .=  '</div>';
} 
$content .= $siteNavigationSnip;

$moduleID = $ModuleID;
if( $existing ){
    $recordLabel = $pageTitle;
    $title = '<span class="rcrdLbl">'.$recordLabelField.'</span><span class="esgn">&nbsp; &#9654; &nbsp;</span>'.strip_tags($screenPhrase);
	$titleCrumbs = $recordLabelField.'<span class="esgn"> &#9654; </span>'.strip_tags($screenPhrase);
}else{
    $recordLabel = sprintf(gettext("Entering a new %s record"), $singularRecordName);
    $title = '<span class="rcrdLblnr">'.$pageTitle.'</span><span class="esgn">&nbsp; &#9654; &nbsp;</span>'.gettext("Add new record");
}
//$recordID;
//$content;
//$globalDiscussions;
//$localDiscussions;

$parentInfo = GetParentInfo( $ModuleID );

include_once $theme . '/edit.template.php';

AddBreadCrumb("$recordLabel $titleCrumbs");
?>

