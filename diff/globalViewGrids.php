<?php
/**
 * Handles display of global view grids on View screens
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

if(!isset($disableGlobalModules) || !$disableGlobalModules){
    //file: globalViewGrids.php
    //renders global ViewGrids in a View screen
    $globalModules = array('act','att','cos','lnk','nts');
    if(!in_array($ModuleID, $globalModules)){
        $content .= '<h1>'.gettext("Global").'</h1>';
        $grids = array();

        foreach($globalModules as $gmID){
            include_once(GENERATED_PATH."/{$gmID}/{$gmID}_GlobalViewGrid.gen");

            if(isset($grid)){
                $grids[] =& $grid;
                unset($grid);
            }
        }
        $fields = null;
        $phrases = null;
        $SQL = '';
        $content .= renderViewScreenSection($fields, $phrases, $SQL, $grids);
    }
}
?>