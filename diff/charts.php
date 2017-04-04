<?php
/**
 * Handles content for the Charts Screen
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
include_once CLASSES_PATH . '/search.class.php';

include_once CLASSES_PATH . '/components.php';

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

$tabsQSargs = $qsArgs;
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);
$tabsQS = MakeQS($tabsQSargs);

$tabs = array();
$tabs['List'] = array("list.php?$tabsQS", gettext("List|View the list"));
$tabs['Search'] = array("search.php?$tabsQS", gettext("Search|Go to the search screen"));
$tabs['Charts'] = array('', gettext("Charts|View charts"));

$jsIncludes = '';
$dashCharts = '++';

/**
 *  Whether to display the chart according to saved dashboard chart settings
 */
$use_dsbc = false;

if(!empty($_GET['dsbc'])){
    $dashboardChartID = intval($_GET['dsbc']);
    $SQL = "SELECT ChartName FROM dsbc WHERE DashboardChartID = $dashboardChartID AND ModuleID = '$ModuleID' AND UserID = {$User->PersonID}";
//print debug_r($SQL);
    $selectedChartName = $dbh->getOne($SQL);
    dbErrorCheck($selectedChartName);
    if(empty($selectedChartName)){
        trigger_error(gettext("Invalid Dashboard Chart ID"), E_USER_WARNING);
    } else {
        $use_dsbc = true;
    }
}

if($use_dsbc){

    $search = GetNewSearch($ModuleID);
    $search->loadChartConditions($User->PersonID, $dashboardChartID);
    $_SESSION['Search_'.$ModuleID] = $search; 

} else {

    if(!empty($_GET['chn'])) {
        $selectedChartName = addslashes($_GET['chn']);
    } else {
        $selectedChartName = '';
    }
    $search = null;
    if(isset($_SESSION['Search_'.$ModuleID])){
        $search = $_SESSION['Search_'.$ModuleID];
    }
}


if(!is_object($search)){
    $search = GetNewSearch($ModuleID);
    $_SESSION['Search_'.$ModuleID] = $search;
}



//prepares conditions for existing dashboard charts
$ConditionSQL = '';
if(0 < count($search->postData)){
    $Conditions = array();
    foreach($search->postData as $postKey => $postValue){
        if(is_array($postValue)){
            $postValue = join(',',$postValue);
        }
        $Conditions[] = "(dsbcc.ConditionField = '$postKey' AND dsbcc.ConditionValue = '$postValue')";
    }
    $ConditionSQL .= "INNER JOIN dsbcc ON
            dsbc.DashboardChartID = dsbcc.DashboardChartID
            AND dsbcc._Deleted = 0\n";
    $ConditionSQL .= " AND (\n";
    //$ConditionSQL .= join("\n OR ", $Conditions);
    $ConditionSQL .= join("\n AND ", $Conditions);
    $ConditionSQL .= ') ';
}
$noConditionSQL = '';
if(empty($ConditionSQL)){
    //$ConditionSQL .= ''; //make a condition for when there are no matching dsbcc records
    $noConditionSQL = "\n AND 0 = (SELECT COUNT(*) FROM dsbcc WHERE dsbc.DashboardChartID = dsbcc.DashboardChartID AND dsbcc._Deleted = 0)";
}

//gets the list of module charts and indicates whether any match the user's dashboard charts
$SQL = "SELECT 
    modch.Name,
    modch.Title,
    modch.Type,
    d.DashboardChartID
FROM modch
    LEFT OUTER JOIN
        (SELECT
            dsbc.ChartName,
            dsbc.ModuleID,
            dsbc.DashboardChartID
        FROM dsbc
            $ConditionSQL
        WHERE
            dsbc.UserID = {$User->PersonID}
            AND dsbc.ModuleID = '$ModuleID'
            AND dsbc._Deleted = 0
            $noConditionSQL
        ) as d
    ON (modch.Name = d.ChartName and modch.ModuleID = d.ModuleID)
WHERE modch.moduleID = '$ModuleID'
ORDER BY modch.Title, modch.Type";


$chartList = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($chartList);

//save
if(isset($_GET['dashchart']) && 'set' == $_GET['dashchart']){
    $r = $dbh->query('START TRANSACTION');
    dbErrorCheck($r);

    $ChartName = addslashes($_GET['chn']);
    $qChartName = dbQuote($_GET['chn']);
    $existingChartID = 0;

    if(count($chartList) > 0){
        foreach($chartList as $row){
            if($ChartName == $row['Name']){
                if(!empty($row['DashboardChartID'])){
                    $existingChartID = $row['DashboardChartID'];
                }
            }
        }
    }

    //make search phrases
    if(count($search->phrases) > 0){
        $phrases = join('<br />', $search->phrases);
    } else {
        $phrases = gettext("All records");
    }

    //insert/update
    if(empty($existingChartID)){

        //determine SortOrder:
        switch($_GET['place']){
        case 'top':
            //   make it 1, and increment SortOrder for all others
            $SortOrder = 1;
            $SQL = "UPDATE dsbc SET SortOrder = SortOrder + 1 WHERE UserID = {$User->PersonID}";
            $r = $dbh->query($SQL);
            dbErrorCheck($r);
            break;
        case 'bottom':
        default:
            // get the max current SortOrder and increment by one
            $SQL = "SELECT MAX(SortOrder) FROM dsbc WHERE UserID = {$User->PersonID}";
            $SortOrder = $dbh->getOne($SQL);
            dbErrorCheck($SortOrder);
            $SortOrder = intval($SortOrder) + 1;
            break;
        }

        $SQL = "INSERT INTO dsbc (UserID, ChartName, ModuleID, SortOrder, ConditionPhrases, _ModBy, _ModDate) VALUES ({$User->PersonID}, $qChartName, '$ModuleID', $SortOrder, '$phrases', {$User->PersonID}, NOW())";

        $r = $dbh->query($SQL);
        dbErrorCheck($r);

        $ChartID = $dbh->getOne('SELECT LAST_INSERT_ID()');
        dbErrorCheck($ChartID);

        $dashCharts .= $ChartName.'+';
    } else {
        $SQL = "UPDATE dsbc SET ConditionPhrases = '$phrases', _ModBy = {$User->PersonID}, _ModDate = NOW(), _Deleted = 0 WHERE DashboardChartID = $existingChartID";

        $r = $dbh->query($SQL);
        dbErrorCheck($r);

        $ChartID = $existingChartID;
    }

    //call search->saveChartConditions
    $search->saveChartConditions($User->PersonID, $ChartID);
    $r = $dbh->query('COMMIT');
    dbErrorCheck($r);

    //how to provide feedback that chart has been saved??
    $use_dsbc = true;

}


$content = '';
$optionContent = '';
$chartImgTag = '';


foreach($chartList as $rowID=>$row){
    if(0 == $rowID){
        if(empty($selectedChartName)){
            $selectedChartName = $row['Name']; //show first chart if none specified
        }
    }
    if($selectedChartName == $row['Name']){
        $optionContent .= "<option selected=\"selected\" value=\"{$row['Name']}\">".gettext($row['Title'])." (".gettext($row['Type']).")</option>\n";
        $chartImgTag = "<img id=\"chartimg\" src=\"chartViewer.php?mdl=$ModuleID&amp;chn={$row['Name']}\" alt=\"".gettext("chart")."\"/>\n";
    } else {
        $optionContent .= "<option value=\"{$row['Name']}\">".gettext($row['Title'])." (".gettext($row['Type']).")</option>\n";
    }

    //list of matching dashboard charts in the form '++1+2+10+12+'
    //Using a string for this to avoid hacks to make Array.indexOf() to work in non-suporting browsers
    if(!empty($row['DashboardChartID'])){
        $dashCharts .= $row['Name'].'+';
    }
}

$ChartDefined = True;
if ( $optionContent == '' ){
	$ChartDefined = False;
	$optionContent .= "<option selected=\"selected\">".gettext("No chart defined for this module")."</option>\n";
}

$content .= '<script type="text/javascript">
<!--
    var moduleID = \''.$ModuleID.'\';
    var dashCharts = \''.$dashCharts.'\';
    function replaceChart(val){
        d = new Date();  //appending the date integer ensures that the browser cache isn\'t used
        img_chart = document.getElementById("chartimg");
        img_chart.src="chartViewer.php?mdl="+moduleID+"\&chn="+val+"\&t="+d.getTime();

        if(dashCharts.length > 0){
            oDashChartExists = document.getElementById(\'is_dsbc\');
            oAddDashChart = document.getElementById(\'not_dsbc\');
            if(dashCharts.indexOf(\'+\' + val + \'+\') > 0){
                oDashChartExists.style.display = \'block\';
                oAddDashChart.style.display = \'none\';
            } else {
                oDashChartExists.style.display = \'none\';
                oAddDashChart.style.display = \'block\';
            }
        }
    }
-->
</script>';

$content .= "<div class=\"sz2tabs chart_form\">\n";
$content .= "<div style=\"display:table\">\n";
$content .= "<form method=\"post\" action=\"popChart.php?$qs\">\n";
$content .= "<select class=\"edt\" id=\"ChartName\" name=\"ChartName\" onchange=\"replaceChart(this.value);\" onkeyup=\"replaceChart(this.value);\">";
$content .= $optionContent;
$content .= "</select>";
$content .= "</form>";

$content .= $chartImgTag;
$content .= "</div>\n";
$content .= "</div>\n";



$content .= "<div class=\"searchFilter\"><b>".gettext("Filter Conditions").":</b><br />\n";
$content .= $search->getPhrases();
$content .= "<br />\n"; 
$content .= "</div><br />\n";

$moduleInfo = GetModuleInfo($ModuleID);

$title = gettext($moduleInfo->getProperty('moduleName'));

if($search->hasConditions()){
    if($search->isUserDefault){
        $title .= ' ['.gettext("default filter").'] ';
    } else {
        $title .= ' ['.gettext("custom filter").'] ';
    }
} else {
    $title .= ' ['.gettext("all").'] ';
}
$linkHere = "charts.php?mdl=$ModuleID";
$subtitle = gettext("Select one");
$moduleID = $ModuleID;

if ($ChartDefined){
	$chartToDashHTML = 
	'<div class="ds">
	<script type="text/javascript">
	<!--
		function showDashChartForm(){
			dc = document.getElementById(\'dashChartForm\');
			dcPlace = document.getElementById(\'dashChartPlace\');
			dc.style.display = \'block\';
			dcPlace.focus();
		}
		function hideDashChartForm(){
			dc = document.getElementById(\'dashChartForm\');
			dc.style.display = \'none\';
		}
		function saveDashChart(){

			dcSelect = document.getElementById(\'ChartName\');
			dcPlace = document.getElementById(\'dashChartPlace\');
			theLink = "'.$linkHere.'"+\'&dashchart=set&place=\'+dcPlace.value+\'&chn=\'+dcSelect.value;
			document.location = theLink;
		}
	-->
	</script>';


	if($use_dsbc){
		$hide_add_dashchart = ' style="display:none"';
		$hide_existing_dashchart = '';
	} else {
		$hide_add_dashchart = '';
		$hide_existing_dashchart = ' style="display:none"';
	}

	$chartToDashHTML .= '<div id="is_dsbc"'.$hide_existing_dashchart.'>'.gettext("This chart is on your dashboard").'</div>';
	$chartToDashHTML .= '<div id="not_dsbc"'.$hide_add_dashchart.'><a href="javascript:showDashChartForm()" title="'.gettext("Click here to add this chart to your dashboard.").'">'.gettext("Add this chart to your dashboard").'</a></div>
	<div id="dashChartForm" style="display:none">
		Place: <select id="dashChartPlace" name="dashChartPlace" class="edt">
		<option value="top">'.gettext("Top").'</option>
		<option value="bottom">'.gettext("Bottom").'</option>
		</select>
		<input type="button" onclick="saveDashChart()" value="'.gettext("Save").'" class="btn" />
		<input type="button" onclick="hideDashChartForm()" value="'.gettext("Cancel").'" class="btn" />
	</div>
	</div>';
}

$moduleInfo = GetModuleInfo($ModuleID);

$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');;
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');;
//$recordID;
//$messages; //any error messages, acknowledgements etc.
//$content;

include_once $theme . '/search.template.php';
?>