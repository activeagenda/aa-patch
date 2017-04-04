<?php
/**
 * Displays Support Documentation
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

//this causes session timeouts to display a message instead of redirecting to the login screen
define('IS_POPUP', true);

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

if($User->PermissionToEdit('spt') == 2){
    $allowEdit = true;
} else {
    $allowEdit = false;
}
if($User->PermissionToEdit('spts') == 2){
    $allowEditSection = true;
} else {
    $allowEditSection = false;
}

$selectedSectionName = '';

//default to 'paged'
if(isset($_GET['mode']) && 'all' == $_GET['mode']){
    $mode = 'all';
    unset($qsArgs['sectionID']);
} else {
    $mode = 'paged';
}
unset($qsArgs['sectionID']);
unset($qsArgs['mode']);
$qs = MakeQS($qsArgs);

$tabs = array();
if('paged' == $mode){
    $tabs['paged'] = array('', gettext("One page per section"));
    $tabs['all']   = array('supportDocView.php?mode=all&amp;'.$qs, gettext("All on one page"));
} else {
    $tabs['paged'] = array('supportDocView.php?mode=paged&amp;'.$qs, gettext("One page per section"));
    $tabs['all']   = array('', gettext("All on one page"));
}

$SQL = "SELECT Name FROM `mod` WHERE moduleid = '$ModuleID'";
$moduleName = $dbh->getOne($SQL);
dbErrorCheck($moduleName);

$SQL = "SELECT 
    spts.SupportDocumentSectionID,
    spt.SupportDocumentID,
    spts.Title,
    spts.SectionText,
    spts.SectionID
FROM spts
INNER JOIN spt
    ON spts.SupportDocumentID = spt.SupportDocumentID
WHERE 
    spts._Deleted = 0
    AND spt.ModuleID = '$ModuleID'
    AND spt._Deleted = 0 
    AND IFNULL(spts.Display, 1) = 1
    AND spts.SectionText > ''
ORDER BY spts.SortOrder";

//get data
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);


//append screen info...
include(GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ScreenInfo.gen");
$r[] = array(
    'Title' => gettext('Screens'),
    'SectionText' => unserialize($editScreens),
    'SectionID' => 'Screens'
);

if($allowEdit){
    $editLink = "<p><a href=\"frames_popup.php?dest=edit&amp;mdl=spt&amp;rid={$r[0]['SupportDocumentID']}\" target=\"_blank\">".gettext("Edit documentation for this module")."</a></p>\n";
} else {
    $editLink = '';
}

$toc = '<b>'.gettext("Table of Contents").":</b><br />\n";
$content = '';
$counter = 1;
if('paged' == $mode){
    $tocLink = "<a href=\"supportDocView.php?{$qs}&amp;mode=paged&amp;sectionID=";
    $selectedSectionID = 0;
    if(isset($_GET['sectionID'])){
        $selectedSectionID = intval($_GET['sectionID']);
    }
    if(0 == $selectedSectionID){
        if(strlen($_GET['sectionID']) <= 1){
            $selectedSectionID = 1;
        } else {
            $selectedSectionName = addslashes($_GET['sectionID']);
        }
    }
} else {
    $tocLink = "<a href=\"#section";
}
foreach($r as $row){
    $toc     .= $tocLink . $counter ."\">{$counter}. {$row['Title']}</a><br />\n";

    if('all' == $mode || $row['SectionID'] == $selectedSectionName || $selectedSectionID == $counter){
        $content .= "<a name=\"section{$counter}\">&nbsp;</a>\n";
        $content .= "<h2>{$counter}. {$row['Title']}</h2>\n";
        if($allowEditSection && (!empty($row['SupportDocumentSectionID']))){
            $content .= "<p><a href=\"frames_popup.php?dest=edit&amp;mdl=spts&amp;rid={$row['SupportDocumentSectionID']}\" target=\"_blank\">".gettext("Edit this section")."</a></p>\n";
        }
        $content .= nl2br($row['SectionText'])."\n";
    }

    $counter++;
}


$externalLinks = '<b>'.gettext("External").":</b><br />\n";
$SQL = "SELECT spt.WikiArticle, spt.WikiGuide FROM spt WHERE spt.ModuleID = '$ModuleID' AND spt._Deleted = 0";
$r = $dbh->getRow($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

$externalLinks .= "<a href=\"{$r['WikiArticle']}\" target=\"_blank\">".gettext("Online Documentation")."</a>\n";

$title = gettext("Support Documentation");
//$tabs;
//$moduleName;
//$messages; //any error messages, acknowledgements etc.
//$toc;
//$externalLinks
//$editLink
//$content;
$closeLink = 'javascript:self.close();opener.focus();';
$closeLabel = gettext("Close");


include_once $theme . '/popupSupportDoc.template.php';
?>