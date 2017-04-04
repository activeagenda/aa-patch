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

if(!empty($_GET['rpc'])){
    define('IS_RPC', true);
}

//main include file - performs all general application setup
require_once(INCLUDE_PATH . '/page_startup.php');

//initialize tabs
$tabs = Array();

//module documentation intro
$introText = '';
if((empty($_SESSION['noIntro']['allModules'])) && (empty($_SESSION['noIntro'][$ModuleID]))){
    $dissmissID = '';

    //check database for dismissals
    $SQL = "SELECT ModuleID FROM usrdi WHERE PersonID = {$User->PersonID} AND ModuleID IN ('---', '$ModuleID') ORDER BY ModuleID LIMIT 1";
    $dbVal = $dbh->getOne($SQL);
    dbErrorCheck($dbVal);

    if(!empty($dbVal)){
        if('---' == $dbVal){
            $_GET['noIntro'] = 'allModules';
        } else {
            $_GET['noIntro'] = $dbVal; 
        }
    }

    if(!empty($_GET['noIntro'])){
        $dissmissID = stripslashes($_GET['noIntro']);
        $_SESSION['noIntro'][$dissmissID] = true;

        //save to database
        if('allModules' == $dissmissID){
            $dismissModuleID = '---';
        } else {
            $dismissModuleID = $dissmissID;
        }

        if(empty($dbVal)){
            $SQL = "INSERT INTO usrdi (PersonID, ModuleID, Dismiss, _ModDate, _ModBy) VALUES ({$User->PersonID}, '$dismissModuleID', 1, NOW(), {$User->PersonID})";

            $r = $dbh->query($SQL);
            dbErrorCheck($r);
        }
    }
    if(empty($dissmissID)){

        //get intro text
        $SQL = "SELECT spts.Title, spts.SectionText, spts.SectionID FROM spts INNER JOIN spt ON spts.SupportDocumentID = spt.SupportDocumentID WHERE spts._Deleted = 0 AND spt.ModuleID = '$ModuleID' AND spts.SectionText > '' ORDER BY spts.SortOrder LIMIT 1";

        //get data
        $r = $dbh->getRow($SQL, DB_FETCHMODE_ASSOC);
        dbErrorCheck($r);
        $introTitle = $r['Title'];
        $introText = $r['SectionText'];
    }
}

$listFieldsFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ListFields.gen";

//main business here
$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_List.gen";

//check for cached page for this module
if (!file_exists($listFieldsFileName)){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$listFieldsFileName'.", E_USER_ERROR);
}
if (!file_exists($filename)){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$filename'.", E_USER_ERROR);
}


//the included file sets $content variable used by template below
include_once $listFieldsFileName;
include_once $filename;


$moduleInfo = GetModuleInfo($ModuleID);
$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

if(!empty($introText)){
    $introText = sprintf('<div id="intro" class="sz2tabs">
        <h2>%s</h2>
        %s
        <input class="btn" type="button" name="ReadMore" value="%s" onclick="%s"/>
        <input class="btn" type="button" name="Dismiss" value="%s" onclick="%s"/>
        <input class="btn" type="button" name="DismissAll" value="%s" onclick="%s"/>
    </div>', 
        $pageTitle.' '.$introTitle, 
        $introText, 
        gettext("Read More..."), "open('supportDocView.php?mdl=$ModuleID', 'documentation', 'toolbar=1,scrollbars=1,width=550,height=600');", 
        gettext("Dismiss for this module"), 'dismissIntro(moduleID, false)', 
        gettext("Dismiss for all modules"), 'dismissIntro(moduleID, true)'
    );
}

//shortcuts functionality
$linkHere = "list.php?mdl=$ModuleID";
if(empty($_GET['sctitle'])){
    $scTitle = $pageTitle;
} else {
    $scTitle = $_GET['sctitle'];
}
//TODO: Add check for shorcut
if(!empty($_GET['shortcut'])){
    switch($_GET['shortcut']){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $linkHere, gettext('List'), $ModuleID);
        break;
    case 'remove':
        RemoveDesktopShortcut($User->PersonID, $linkHere);
        break;
    default:
        //nada
    }
}

$dash_shortcutTitle = '';
if(isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$linkHere])){
    $dash_shortcutTitle = $_SESSION['desktopShortcuts'][$linkHere];
}
if(empty($dash_shortcutTitle)){
    $dash_shortcutTitle = $scTitle;
    $hasShortcut = false;
} else {
    $hasShortcut = true;
}

$origPageTitle = $pageTitle;

if($search->hasConditions()){
    if($search->isUserDefault){
        $pageTitle .= ' ['.gettext("default filter").'] ';
    } else {
        $pageTitle .= ' ['.gettext("custom filter").'] ';
    }
} else {
    $pageTitle .= ' ['.gettext("all").'] ';
}

$parentInfo = GetParentInfo($ModuleID);

//names required by template
$title = $pageTitle;
$moduleID = $ModuleID;

//$linkHere
include_once $theme . '/list.template.php';

AddBreadCrumb($origPageTitle . ' - '. gettext("List"));
?>