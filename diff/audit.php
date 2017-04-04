<?php
/**
 * Handles content for the Audit Screen
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2008 Active Agenda Inc., All Rights Reserved.
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
 * copyright      2003-2008 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = "'".substr($_GET['rid'], 0, 5)."'";
    }
}

//title
$pageTitle = gettext("Audit trail for "). $ModuleID;
if($recordID > 0){
    $pageTitle .= gettext(", record "). $recordID;
}

//generic tabs
$tabs['List'] = Array("list.php?$qs", gettext("List|View the list"));
$tabs['View'] = Array("view.php?$qs", gettext("View|The View Screen"));

$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Audit.gen";

//check for cached page for this module
if (!file_exists($filename)){
    trigger_error("Could not find file '$filename'. ", E_USER_ERROR);
}

$messages = array(); //init

//the included file sets $recordIDField
include($filename);

//get data
$logTable = $ModuleID.'_l';

$SQL = "SELECT $logTable.*, ppl.DisplayName as _ModifiedBy FROM $logTable LEFT OUTER JOIN ppl ON $logTable._ModBy = ppl.PersonID WHERE $logTable.$recordIDField = $recordID ORDER BY $logTable._ModDate ASC;"; 
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

if(count($r) > 0){
    //begin the audit grid..
    $content = '<table width="100%">';

    //add headers
    $tr = '<tr>%s</tr>';
    $th = '<th class="l" width="%s" title="%s">%s</th>';

    foreach($r[0] as $fh => $fd){
        if($fh[0] == '_'){
            if($fh != '_ModBy'){
                $headRow .= sprintf($th, '1%', $fh, $fh);
            }
        } else {
            if($fh == $recordIDField) {
                $headRow .= sprintf($th, '1%', $fh, $fh);
           // } else {
           //     $headRow .= sprintf($th, '1%', $fh, '&nbsp;');
            }
        }
    }
    $content .= sprintf($tr, $headRow);

    //add rows
    $td = '<td class="%s" title="%s">%s</td>';

    $tdFormatting = array("l", "l2");
    foreach($r as $rowNum => $row){
        $contentRow = '';
        if($rowNum > 0){
            $prevRow = $r[$rowNum-1];
        } else {
            //just to avoid error msgs
            $prevRow = $r[$rowNum];
        }
        $rowClass = 'l'; //$tdFormatting[$rowNum % 2];
        foreach($row as $fh => $fd){

            if($fd != $prevRow[$fh]){
                $tdClass = 'l2';
            } else {
                $tdClass = $rowClass;
            }

            if($fh[0] == '_'){
                if($fh != '_ModBy'){
                $contentRow .= sprintf($td, $rowClass, $fd, $fd);
                }
            } else {
                if($fh == $recordIDField) {
                    $contentRow .= sprintf($td, $tdClass, $fd, $fd);
               // } else {
               //     $contentRow .= sprintf($td, $tdClass, $fd, '&nbsp;');
                }
            }
        }
        $content .= sprintf($tr, $contentRow);
    }
    $content .= '</table>';
} else {
    $content .= gettext("There is no data to view here.");
}

$jsIncludes = '';
$title = $pageTitle;
//$user_info;
//$tabs;
$screenPhrase = ShortPhrase($screenPhrase);
//$messages); //any error messages, acknowledgements etc.
//$content;
$isAuditScreen = True;
include_once $theme . '/edit.template.php';
?>