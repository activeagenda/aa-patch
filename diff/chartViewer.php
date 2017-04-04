<?php
/**
 * Handles content for displaying a chart
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
require_once(CLASSES_PATH . '/chart.class.php');

//main include file - performs all general application setup
require_once(INCLUDE_PATH . '/page_startup.php');



if(!empty($_GET['dsbc'])){ //passed a Dashboard Chart ID
    $DashboardChartID = intval($_GET['dsbc']);
    $isDashboardChart = true;
    $SQL =
    "SELECT
        dsbc.ChartName,
        dsbc.ModuleID,
        dsbcc.ConditionField,
        dsbcc.ConditionValue
    FROM
        dsbc
        LEFT OUTER JOIN dsbcc
        ON (dsbc.DashboardChartID = dsbcc.DashboardChartID
        AND dsbcc._Deleted = 0)
    WHERE
        dsbc.UserID = {$User->PersonID} AND
        dsbc.DashboardChartID = $DashboardChartID AND
        dsbc._Deleted = 0";

    $chartInfo = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($chartInfo);

    if(count($chartInfo) > 0){
        $chartname = $chartInfo[0]['ChartName'];
        $ModuleID = $chartInfo[0]['ModuleID'];
    } else {
        die('Invalid dashboard chart.');
    }
} else {
    $chartname = addslashes($_GET['chn']);
    $isDashboardChart = false;
}

if(empty($ModuleID)){
    die('no module ID passed');
}

$cachedChartLocation = GENERATED_PATH ."/{$ModuleID}/{$ModuleID}_{$chartname}_Chart.gen";

include_once($cachedChartLocation); //returns $chart

if($isDashboardChart){
    $chart->dashboardChartID = $DashboardChartID;
}

if(!empty($_GET['mini'])){
    $width = 300;
    $height = 200;
    $title = false;
} else {
    $width = 500;
    $height = 400;
    $title = true;
}
$chart->render($width, $height, $title);

?>