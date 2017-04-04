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

require_once CLASSES_PATH . '/grids.php';
require_once CLASSES_PATH . '/lists.php';

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

include $theme .'/component_html.php';

$subModuleID = substr(addslashes($_GET['smd']), 0, 5);

$startrow = intval($_GET['sr']);

//get the (parent) record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = substr($_GET['rid'], 0, 5);
    }
}
//print debug_r($qsArgs);
if(!empty($ModuleID)){
    if(in_array($subModuleID, array('act','att','cos','lnk','nts', 'modnr'))){
        $isGlobal = true;
        
        switch($_GET['grt']){
        case 'view':
            $CachedFileName = GENERATED_PATH . "/{$subModuleID}/{$subModuleID}_GlobalViewGrid.gen";
            break;
        case 'edit':
            $CachedFileName = GENERATED_PATH . "/{$subModuleID}/{$subModuleID}_GlobalEditGrid.gen";
            break;
        default:
            print "Invalid request";
            trigger_error(gettext("ERROR: No 'grid type' parameter passed."), E_USER_ERROR);
            break;
        }
    } else {
        $isGlobal = false;
        switch($_GET['grt']){
        case 'view':
            $CachedFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_{$subModuleID}ViewGrid.gen";
            break;
        case 'edit':
            $CachedFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_{$subModuleID}EditGridRPC.gen";
            break;
        default:
            print "Invalid request";
            trigger_error(gettext("ERROR: No 'grid type' parameter passed."), E_USER_ERROR);
            break;
        }
    }

    if(file_exists($CachedFileName)){

        //returns $grid
        include_once($CachedFileName);
        if($isGlobal && 'edit' == $_GET['grt']){
            $grid =& $editGrid;
        }
        $content .= $grid->render('view.php', $qsArgs);

    } else {
        $content = 'No file: '.$CachedFileName.'<br/>';
        //$content .= debug_r($_GET);
        trigger_error(gettext("ERROR: The following file could not be found:").$CachedFileName, E_USER_WARNING);
    }
} else {

    $content = 'No module ID';
    trigger_error(gettext("ERROR: No module ID."), E_USER_WARNING);
}

print $content;
?>