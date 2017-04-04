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

if($recordID == 0){
    $level = 'list';
    $str_recordID = '';

    if(isset($_SESSION['Search_'.$ModuleID])){
        $search = $_SESSION['Search_'.$ModuleID];
    } else {
        /*$search = new Search(
            $ModuleID,
            array() //$listFields
        );*/
        $search = GetNewSearch($ModuleID);
        $search->loadUserDefault($User->PersonID);
    }
    if(is_object($search)){
        $content .= "<br />\n";
        $content .= '<div class="searchFilter"><b>'.gettext("Search Filter Conditions").':</b><br />'."\n";
        $content .= $search->getPhrases();
        $content .= '</div>';

        $recordLabel = gettext("List Reports");
    }

} else {

    $level = 'record';
    $content = renderLabelFields($ModuleID, $recordID);
    $str_recordID = '&amp;rid='.$recordID;
}

$SQL = "SELECT Name, Title, LocationLevel, LocationGroup, Format FROM modrp WHERE ModuleID = '$ModuleID' AND LocationLevel = '$level' ORDER BY LocationGroup, Title";
//print debug_r($SQL);
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
        switch($row['Format']){
        case 'pdf':
        case 'PDF':
            $viewerURL = "reportPDFViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $target = '';
            $fileicon = '<img src="'.$theme_web.'/img/dl-pdf.png" alt="pdf"/>';
            break;
		case 'xls':
		case 'XLS':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $target = '';
            $fileicon = '<img src="'.$theme_web.'/img/dl-spreadsheet.png" alt="xls"/>';
            break;
		case 'doc':
		case 'DOC':
            $viewerURL = "reportMSXMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $target = '';
            $fileicon = '<img src="'.$theme_web.'/img/dl-xml" alt="doc"/>';
            break;	
        case 'html-linear':
        default:
            $viewerURL = "reportHTMLViewer.php?mdl=$ModuleID&amp;rpt={$row['Name']}$str_recordID";
            $target = 'target="_blank"';
			$fileicon = '<img src="'.$theme_web.'/img/dl-csv.png" alt="html"/>';
            break;
        }
        $content .= '<p><div class="dl_icon">'."<a href=\"$viewerURL\" $target>".$fileicon." <br/> ".gettext($row['Title'])." </a></div></p>";
    }
} else {
    $content = gettext("Could not find any reports for this module");
}

//generic tabs
$tabs = array(); 
$tabs['List'] = Array("list.php?$tabsQS", gettext("List|View the list"));
if('list' == $level){
    //insert list level tabs...
    $tabs['Search'] = Array("search.php?$tabsQS", gettext("Search|Search to select different data"));
 //   $tabs['Charts'] = Array("charts.php?$tabsQS", gettext("Charts|View the selected data in charts"));
    $title = gettext("Reports");
} else {
    include_once GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Tabs.gen";
    $title = gettext($singularRecordName) . ' - ' . gettext("Reports");
}
$ScreenName = addslashes($_GET['scr']);
if(in_array($ScreenName, array_keys($tabs))){
    $tabs[$ScreenName] = array('', $tabs[$ScreenName][1]);
} else {
    $tabs['Reports'] = array('', gettext('Reports'));
}

require_once(CLASSES_PATH . '/moduleinfo.class.php');
$moduleInfo = GetModuleInfo($ModuleID);
$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');


$linkHere = "reports.php?mdl=$ModuleID&amp;rid=$recordID";
if(empty($_GET['sctitle'])){
    $scTitle = $pageTitle;
} else {
    $scTitle = $_GET['sctitle'];
}
if(!empty($_GET['shortcut'])){
    switch($_GET['shortcut']){
    case 'set':
        SaveDesktopShortCut($User->PersonID, $scTitle, $linkHere, 'Reports', $ModuleID);
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
    $dash_shortcutTitle = $recordLabel;
    $hasShortcut = false;
} else {
    $hasShortcut = true;
}

if(0 < strlen($_GET['sr'])){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'reports.php');
}

//$tabs;
//$generalTabs;
//$screenPhrase = '';
//$content;

include_once $theme . '/view.template.php';

AddBreadCrumb("$recordLabel <br/>($title)");
?>