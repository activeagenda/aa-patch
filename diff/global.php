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
$CrumbModuleID = $ModuleID;
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if('list' == $_GET['rid']){
        $listModuleID = $ModuleID;
        $SQL = "SELECT RecordID FROM `mod` WHERE ModuleID = '$listModuleID'";
        $mdb2 =& GetMDB2();
        $ModuleID = 'mod';
        $recordID = $mdb2->queryOne($SQL);
        mdb2ErrorCheck($recordID);
        $listLevel = true;
    } elseif(strlen($_GET['rid']) >= 3){
        //$recordID = "'".substr($_GET['rid'], 0, 5)."'";
        $recordID = substr($_GET['rid'], 0, 5);
        $listLevel = false;
    }
}
$tabsQSargs = $qsArgs;
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);
$tabsQS = MakeQS($tabsQSargs);

//get the GlobalModule ID
$GlobalModuleID = substr(addslashes($_GET['gmd']), 0, 5); //any valid module id

//generic tabs
$tabs = array();
$tabs['List'] = Array("list.php?$tabsQS", gettext("List|View the list"));
if($listLevel){
    //add the list level tabs here
} else {
    include_once GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Tabs.gen";
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
if($listLevel){
    $moduleInfo =& GetModuleInfo($listModuleID);
    $moduleName = $moduleInfo->getProperty('moduleName');
    $singularRecordName = $moduleName;

    $labels = array(
        'act' => gettext("Actions"),
        'att' => gettext("Attachments"),
        'cos' => gettext("Costs"),
        'lnk' => gettext("Links"),
        'nts' => gettext("Notes")
    );

    $recordLabel = sprintf(gettext("General %s for %s"), $labels[$GlobalModuleID], gettext($moduleName));
    $content = '';
} else {
    $moduleInfo =& GetModuleInfo($ModuleID);
    $content = renderLabelFields($ModuleID, $recordID);
}

//display edit grid
$content .= $editGrid->render('global.php', $qsArgs);

$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar_stripped.js"></script>'."\n";
$LangPrefix = substr($User->Lang, 0, 2);
$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/lang/calendar-'.$LangPrefix.'.js"></script>'."\n";
$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar-setup_stripped.js"></script>'."\n";


$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

if(!$listLevel && 0 < strlen($_GET['sr'])){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'global.php');
}

//$jsIncludes
$title = gettext($singularRecordName) . ' - ' . gettext($gridPluralName);
$subtitle = sprintf(gettext("Manage %s for this %s:"), $gridPluralName, $singularRecordName);
//$user_info
$screenPhrase = ShortPhrase($screenPhrase);
//$messages  //any error messages, acknowledgements etc.
//$content
//$globalDiscussions
//$localDiscussions

//debug stuff:
//$content .= debug_r($labelSQL);
// DEV:
//$linkHere = "global.php?mdl=$ModuleID&amp;rid=$recordID&amp;gmd=$GlobalModuleID";
if ( $listLevel == true ){
	$linkHere = "global.php?mdl=$CrumbModuleID&amp;rid=list&amp;gmd=$GlobalModuleID";
} else {
	$linkHere = "global.php?mdl=$ModuleID&amp;rid=$recordID&amp;gmd=$GlobalModuleID";
}

include_once $theme . '/edit.template.php';

AddBreadCrumb("$recordLabel <br/>($title)");
?>