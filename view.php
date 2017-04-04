<?php
/**
 * Handles the View Screen
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
include_once INCLUDE_PATH.'/viewscreensection.php';
include_once $theme .'/component_html.php';

setTimeStamp('view_after_classdefs');

//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = substr($_GET['rid'], 0, 5);
    }
}
unset( $_GET['gmd'] );
unset( $qsArgs['gmd'] );

unset( $_GET['scr'] );
unset( $qsArgs['scr'] );

//verify that a record id was passed
if (empty($recordID)){
    trigger_error("No record was selected.", E_USER_ERROR);
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


//check for cached page for this module and the included file sets $content variable used by template below
$filename = GENERATED_PATH . '/'.$ModuleID.'/'.$ModuleID.'_View.gen';
if( !file_exists($filename) ){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$filename'.", E_USER_ERROR);
}
include($filename);

if ( $User->checkRecordPermission( $ModuleID, $recordID) == 0 ){
	trigger_error(gettext("You don't have permission to view this record."), E_USER_ERROR);
};

setTimeStamp('after_gen_screen');

if( !isset($disableGlobalModules) || !$disableGlobalModules ){
	$content .= $siteNavigationSnip.'<h1 id="Global"></a><span class="h1glb">'.gettext("Global").'</span></h1>';
	
	$globalModules = array( 'nts','lnk','cos','rmd' );	
	foreach( $globalModules as $GlobalModuleID ){	
		$grid_filename = GENERATED_PATH . "/{$GlobalModuleID}/{$GlobalModuleID}_GlobalEditGrid.gen";
		//check for cached page
		if ( !file_exists($grid_filename) ){
			trigger_error("Could not find grid file '$grid_filename'.", E_USER_ERROR);
		}
		include $grid_filename;

		//insert dynamic data
		$replFields = array( '/**DynamicModuleID**/', '/**PR-ID**/' );
		$replValues = array( $ModuleID, $recordID );

		$editGrid->insertSQL    = str_replace($replFields, $replValues, $editGrid->insertSQL);
		$editGrid->updateSQL    = str_replace($replFields, $replValues, $editGrid->updateSQL);
		$editGrid->deleteSQL    = str_replace($replFields, $replValues, $editGrid->deleteSQL);
		$editGrid->logSQL       = str_replace($replFields, $replValues, $editGrid->logSQL);
		$editGrid->ParentRowSQL = str_replace($replFields, $replValues, $editGrid->ParentRowSQL);
		$editGrid->SMCSQL       = str_replace($replFields, $replValues, $editGrid->SMCSQL);

		//handle grid form
		$editGrid->handleForm();

		//display edit grid
		$content .= $editGrid->render( 'global.php', $qsArgs );
		$content .= '<div class="topshrk"></div>';
	}
}
setTimeStamp('view_after_global_editgrids');

$moduleInfo = GetModuleInfo($ModuleID);
//$moduleglobalDiscussion= 
if( $moduleInfo->getProperty('globalDiscussionAddress') ){
	$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
}
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

// XMLbase doesn't need this module
//include_once(GENERATED_PATH . '/moddr/moddr_GlobalViewGrid.gen');
if( !empty($ownerField) && isset($grid) ){ //unfortunate name returned by include above
    $directionCount = $grid->getRecordCount();
    if(intval($directionCount) > 0){
        $content .= sprintf(
            POPOVER_DIRECTIONS,
            ShortPhrase($grid->phrase),
            $grid->render('view.php', $qsArgs)
        );
    }
} else {
    $directionCount = 0;
}

//$NoEditScrPermission = '';

//shortcuts functionality
$linkHere = "view.php?mdl=$ModuleID&amp;rid=$recordID";
$encodedLinkHere = 'frames.php?dest='.base64_encode( $linkHere );

$plainLink = str_replace('&amp;', '&', $linkHere);
$JSredirect= '';
if(empty($_GET['sctitle'])){
    $scTitle = $pageTitle;
} else {
    $scTitle = $_GET['sctitle'];
}
if(!empty($_GET['shortcut'])){
    switch( $_GET['shortcut'] ){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $plainLink, gettext('View'), $ModuleID);
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
    $dash_shortcutTitle = $recordLabelField;
    $hasShortcut = false;
} else {
    $hasShortcut = true;
}

$parentInfo = GetParentInfo($ModuleID);
$title = '<span class="rcrdLbl">'.$recordLabelField.'</span>';
$titleCrumbs = $recordLabelField;
$recordLabel = $pageTitle;

//$tabs;
$screenPhrase = ShortPhrase($screenPhrase);
$moduleID = $ModuleID;
//$recordID;
//$content;
//$globalDiscussions;
//$localDiscussions;
//$xmlExportLink = "xmlExport.php?mdl=$ModuleID&amp;rid=$recordID";

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

include_once $theme . '/view.template.php';

AddBreadCrumb("$recordLabel $titleCrumbs &#10143; ".gettext("View"));
?>