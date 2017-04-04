<?php
/**
 * Handles XMLHttpRequest messages (AJAX) from EditGrids
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
require_once '../../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//causes session timeouts to return a catchable response
define('IS_RPC', true);

//yes we need all of these unfortunately
require_once CLASSES_PATH . '/grids.php'; //for field classes, (A)EditGrid class
include_once CLASSES_PATH . '/modulefields.php'; //for RemoteField class only

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

include $theme .'/component_html.php';


// create a new instance of JSON
require_once THIRD_PARTY_PATH . '/JSON.php'; //use PEAR package when released
$json = new JSON();

$error = ''; //initate error string

$subModuleID = substr(addslashes($_GET['smd']), 0, 5);

$rowID = intval($_GET['grw']);
if($rowID == 0){
    if(strlen($_GET['grw']) >= 3){
        $rowID = "'".substr($_GET['grw'], 0, 5)."'";
    }
}

//get the (parent) record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = substr($_GET['rid'], 0, 5);
    }
}

if(!empty($ModuleID)){
    if(in_array($subModuleID, array('act','att','cos','lnk','nts', 'modnr'))){
        $isGlobal = true;
        $CachedFileName = GENERATED_PATH . "/{$subModuleID}/{$subModuleID}_GlobalEditGrid.gen";
    } else {
        $isGlobal = false;
        $CachedFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_{$subModuleID}EditGridRPC.gen";
    }

    if(file_exists($CachedFileName)){

        //returns $grid
        include_once($CachedFileName);
        if($isGlobal){
            $grid =& $editGrid; //naming inconsistency in global editgrids
            $replFields = array('/**DynamicModuleID**/', '/**RecordID**/');
            $replValues = array($ModuleID, $recordID);

            $grid->insertSQL    = str_replace($replFields, $replValues, $grid->insertSQL);
            $grid->updateSQL    = str_replace($replFields, $replValues, $grid->updateSQL);
            $grid->deleteSQL    = str_replace($replFields, $replValues, $grid->deleteSQL);
            $grid->logSQL       = str_replace($replFields, $replValues, $grid->logSQL);
            $grid->ParentRowSQL = str_replace($replFields, $replValues, $grid->ParentRowSQL);
        }

        //now check what action is requested: get form, save, or delete 
        switch($_GET['action']){
        case 'getform':
            //get data for row, generate form HTML
            $content = $grid->renderForm($rowID);
            $cells = '';
            break;
        case 'save':
        case 'add':
        case 'delete':
            //save data for row, return row HTML
            $content = '';
            $formResult = $grid->handleForm(); //debug_r($_POST); //"Save";
trace($formResult, '$formResult');
            $returnRowID= $formResult['rowID'];
            $cells = $formResult['cells'];
            $isConsistent = $formResult['consistent'];
            if(!empty($formResult['error'])){
                $error = $formResult['error'];
            }
            if(empty($returnRowID)){
                $returnRowID = $rowID;
            }
            if(count($cells) > 0){
                if($isConsistent){
                    $quickedit_img = "$theme_web/img/grid_quickedit.gif";
                    $quickedit_msg = gettext("Quick Edit");
                } else {
                    $quickedit_img = "$theme_web/img/grid_inconsistency.png";
                    $quickedit_msg = gettext("Quick Edit: This record is in an inconsistent state.");
                }
                $fulledit = '';
                if($formResult['fulledit']){
                    $fulledit = "<a class=\"l\" href=\"#\" onclick=\"window.open('frames_popup.php?dest=edit&amp;mdl={$subModuleID}&amp;rid={$returnRowID}', '', 'toolbar=0,resizable=1')\"><img src=\"$theme_web/img/grid_fulledit.gif\" title=\"". gettext("Full Edit (in new window)"). "\"/></a>&nbsp;";
                }
                $firstCell = array(
                    'className'=>'l',
                    'id'=>$returnRowID,
                    'innerHTML'=>"$fulledit<a class=\"l\" href=\"javascript:editRow('$returnRowID')\"><img src=\"$quickedit_img\" title=\"$quickedit_msg\"/></a>"
                );
                //array_unshift didn't give the right result (returned "5"???), so we use a workaround:
                $cells = array_merge(array($firstCell), $cells);
            } else {
                $cells = array();
            }

            break;
        default:
            $content = '';
            $error = "ERROR: Unknown action requested.";
            $error .= nl2br(debug_r($_GET));
        }

    } else {
        $content = 'No file';
        $content = debug_r($_GET);
        $error = gettext("ERROR: The following file could not be found:").$CachedFileName;
    }
} else {

    $content = 'No module ID';
    $error = gettext("ERROR: No module ID.");
}

$response = array();
$response['action'] = $_GET['action'];
$response['rowID'] = $_GET['grw'];
$response['content'] = $content;
$response['cells'] = $cells;
$response['error'] = $error;
print $json->encode($response);
?>