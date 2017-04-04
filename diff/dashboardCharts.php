<?php
/**
 * Displays the image link to the live dashboard charts
 *
 * PHP version 5
 *
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
 * @author         Mattias Thorslund <mthorslund@activeagenda.net>
 * @copyright      2003-2009 Active Agenda Inc.
 * @license        http://www.activeagenda.net/license  RPL 1.5
 * @version        SVN: $Revision: 1406 $
 * @last-modified  SVN: $Date: 2009-01-27 07:56:18 +0100 (Wt, 27 sty 2009) $
 */

if(!isset($content)){
    $content = '';
}

include_once $theme . '/dashboard_html.php';

$SQL = "SELECT 
    dsbc.DashboardChartID, 
    modch.Title,
    dsbc.ModuleID,
    dsbc.ConditionPhrases
FROM dsbc 
    INNER JOIN modch 
    ON dsbc.ChartName = modch.Name AND dsbc.ModuleID = modch.ModuleID
WHERE dsbc.UserID = {$User->PersonID} 
AND dsbc._Deleted = 0 
ORDER BY dsbc.SortOrder";


$dashCharts = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
$loadChartJS = '';
$load_url = $theme_web . '/img/chart_loading.png';

if(count($dashCharts) > 0){
    foreach($dashCharts as $dashChart){
        $url = 'chartViewer.php?dsbc='.$dashChart['DashboardChartID'].'&mini=1&t='.time();

        $chartID = 'dc'.$dashChart['DashboardChartID'];
        $content .= sprintf(
            CHART_TRIM,
            $dashChart['DashboardChartID'],
            gettext($dashChart['Title']),
            $dashChart['ConditionPhrases'],
            '<a href="charts.php?mdl='.$dashChart['ModuleID'].'&amp;dsbc='.$dashChart['DashboardChartID'].'&amp;t='.time().'"><img id="'.$chartID.'" width="300" height="200" src="'.$load_url.'" alt="'.gettext("chart").'" /></a>'
        ); //appending the date integer ensures that the browser cache isn't used

        //$loadChartJS .= "loadChart('$chartID', '$url');\n";
        $loadChartJS .= "var chartloader_$chartID = function(e){loadChart('$chartID', '$url')}\n";
        $loadChartJS .= " YAHOO.util.Event.addListener(window, 'load', chartloader_$chartID)\n";
    }

    $content .= '<script type="text/javascript">
    <!--
    '.$loadChartJS.'
    --></script>';
} else {
    $content .= '<p>';
    $content .= gettext("You have no charts yet. You can add charts to this dashboard from the Charts screen in any module.");
    $content .= '</p>';
}


?>