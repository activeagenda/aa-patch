<?php
/**
 * Class Definitions for Charts
 *
 * These are wrapper classes for the PEAR Image/Graph.php classes
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
 * @version        SVN: $Revision: 1658 $
 * @last-modified  SVN: $Date: 2009-05-24 23:58:56 +0200 (N, 24 maj 2009) $
 * @package        web_time
 **/


/**
 * Abstract base class for charts
 *
 * @package        web_time
 */
class Chart
{
var $name;
var $title;
var $moduleID;
var $subModuleID; //if chart displays submodule data
var $mode;        //naming? Either 'categorize' or blank
var $joins = array();
var $conditions = array();
var $useLegend = false;
var $_Graph; //PEAR Image_Graph object being wrapped
var $_Canvas; //PEAR Image_Canvas object being wrapped
var $width;
var $height;

var $groupByFields = array();
var $summaryFields = array();
var $labelField;
var $valueField;
var $dateInterval;

var $fileName;

function Factory($element, $moduleID)
{
    $chart =& new $element->type(
        $moduleID,
        $element->getAttr('name'),
        $element->getAttr('title'),
        $element->getAttr('subModuleID'),
        $element->getAttr('mode'),
        $element->getAttr('valueLabels')
    );

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            switch($sub_element->type){
            case 'GroupByField':
                $chart->groupByFields[$sub_element->name] = $sub_element->getAttr('dateInterval');
                $chart->labelField = $sub_element->name;
                $chart->dateInterval = $sub_element->getAttr('dateInterval');
                break;
            case 'SummaryField':
                $chart->summaryFields[$sub_element->name] = $sub_element->getAttr('type');
                $chart->valueField = $sub_element->name;
                break;
            default:
                die('PieChart: Unknown sub-element');
                break;
            }
        }
    }

    //copying some submodule properties at parse time so we don't have to retrieve them at runtime
    if(isset($element->attributes['subModuleID'])){
        $subModuleID = $element->getAttr('subModuleID');

        $subModule = GetModule($subModuleID);

        if('submodule' != strtolower(get_class($subModule))){
            trigger_error("The chart {$chart->name} requires a SubModule definition for $subModuleID.", E_USER_ERROR);
        }

        $subModuleFields = $subModule->ModuleFields;
        $labelMF = $subModuleFields[$chart->labelField];
        $labelFieldSelect = $labelMF->makeSelectDef($subModuleID, false) . " AS {$chart->labelField}";
        $labelTableAlias = GetTableAlias($labelMF, $subModuleID);
        $labelTable = $labelMF->foreignTable;
        $aSubModuleConditions = array();
        if(count($subModule->conditions) > 0){
            foreach($subModule->conditions as $conditionField => $conditionValue){
                $aSubModuleConditions[] = "`$subModuleID`.$conditionField = '$conditionValue'";
            }
        }
        $aSubModuleConditions[] = "`$subModuleID`._Deleted = 0";
        $strSubModuleConditions = implode(' AND ', $aSubModuleConditions);

        if('ParetoChart' == $element->type || 'pareto' == $element->getAttr('mode') ){
            $orderByField = $chart->valueField . ' DESC';
        } else {
            $orderByField = $chart->labelField;
        }

        $SQL = "SELECT 
                $labelFieldSelect,
                COUNT(`$subModuleID`.{$chart->valueField}) AS {$chart->valueField}
            FROM
                `$labelTable` AS $labelTableAlias
                LEFT OUTER JOIN `$subModuleID`
                    ON $labelTableAlias.{$labelMF->foreignKey} = `$subModuleID`.{$labelMF->localKey} 
                    AND $strSubModuleConditions
                    AND `$subModuleID`.{$subModule->localKey} IN (/**SearchSQL**/)
                    AND `$subModuleID`._Deleted = 0
            WHERE
                $labelTableAlias.{$labelMF->listCondition} AND
                $labelTableAlias._Deleted = 0
            GROUP BY {$chart->labelField}
            ORDER BY {$orderByField}";

        $chart->categorizeSQL = $SQL;
    }
    return $chart;
}


function render($width = 500, $height = 400, $showTitle = true)
{
    return false;
}


/**
 * Returns a string that describes the chart type.
 */
function getDisplayType()
{
    return gettext("Unknown"); //override
}


function &_makeColors($nColors)
{
    if($nColors < 1){
        $nColors = 1;
    }
    require_once PEAR_PATH . '/Image/Color.php';
    $color =& new Image_Color();

    // set a standard fill style
    //$FillArray =& Image_Graph::factory('Image_Graph_Fill_Array');

    $startHue = 50; //30,40,100
    $step = intval(256/$nColors);
    if(0 == $step){
        $step = 1;
    }
    $hue = $startHue;

    $colors = array();
    for($i = 0; $i < $nColors; $i++){

        if($i < ($nColors/2)){
            $part = 0;
        } else {
            $part = 1;
        }
        $colors[$part][] = $color->hsv2hex($hue, 102, 255);

        $hue += $step;
        $hue = $hue % 256;
    }

    //re-orders colors so that "most different" colors are next to each other
    foreach($colors[0] as $key => $value){
        $reorderColors[] = $value;
        if(isset($colors[1][$key])){ //avoids a notice at odd numbers of colors
            $reorderColors[] = $colors[1][$key];
        }
    }

    return $reorderColors;
}


function _renderSetup()
{
    // include libraries
    require_once PEAR_PATH . '/Image/Graph.php';
    require_once PEAR_PATH . '/Image/Canvas.php';

    // create a PNG canvas and enable antialiasing (canvas implementation)
    $this->_Canvas =& Image_Canvas::factory('png', array('width' => $this->width, 'height' => $this->height, //'antialias' => 'driver'
    ));

    // create the graph
    //$Graph =& Image_Graph::factory('graph', array(400, 300));
    $this->_Graph =& Image_Graph::factory('graph', $this->_Canvas);
    // add a TrueType font
    $Font =& $this->_Graph->addNew('font', 'Verdana');
    // set the font size to 11 pixels
    $Font->setSize(8);
    $this->_Graph->setFont($Font);

}


function getInterval($max)
{
    //returns 1, 10, 100, etc. Would be nice to return 1, 5, 10, 50, 100, etc.

    $interval = 1;
    $nMaxTicks = 5;

    $exp = floor(log10($max));
    if($exp < 0){
        $exp = 0;
    }

    $interval = pow(10, $exp);

    if(($nMaxTicks * $interval) > $max){
        $interval = pow(10, $exp-1) * 5;
    }
    if($interval < 1){
        $interval = 1;
    }

    return $interval;
}


function getMinimumDataPoints()
{
    return 0; //override
}


function showError($errorType, $errorInfo = '')
{
    $isError = true;
    switch($errorType){
    case 'no_data':
        $isError = false;
        $msg = gettext("There is no matching data to render this chart.");
        break;
    case 'not_enough_data':
        $msg = gettext("Error: Not enough data to render this chart.");
        break;
    case 'SQL_error':
        $msg = gettext("Error: SQL error.");
        break;
    default:
        $msg = gettext("Error: Unknown error type.");
        break;
    }
    if($isError){
        trigger_error('chart showError: '.$errorInfo, E_USER_WARNING);
    }
    $this->_Graph->addNew('title', array($msg, 10));

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }
    $this->_Graph->done($params);
}
} //end class Chart





class PieChart extends Chart
{

function PieChart($moduleID, $name, $title, $subModuleID, $mode, $valueLabels)
{
    $this->moduleID = $moduleID;
    $this->name = $name;
    $this->title = $title;
    $this->subModuleID = $subModuleID;
    $this->mode = $mode;
    $this->valueLabels = $valueLabels;
}


function getDisplayType()
{
    return gettext("Pie");
}


function getMinimumDataPoints()
{
    return 1;
}


function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    $this->_renderSetup();

    $Plotarea =& $this->_Graph->addNew('plotarea');
    $Plotarea->hideAxis();
    $Dataset =& Image_Graph::factory('dataset');

    $SQL = $this->generateSQL();

    if($showTitle){
        $this->_Graph->addNew('title', array(gettext($this->title), 11));
    }

    //get data, count resulting rows
    global $dbh;
    $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    if(!dbErrorCheck($result, false, false)){

        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result)){
        $this->showError('no_data');
    }

    $rowCounter = 0;
    $otherValue = 0.0;
    $showValues = 20;
    foreach($result as $row){
        $rowCounter++;
        if($rowCounter <= $showValues){
            $value = $row[$this->valueField];
            if(intval($value) > 0){
                if(empty($row[$this->labelField])){
                    $label = gettext("(no data)");
                } else {
                    $label = trim($row[$this->labelField]);
					if ($label == 'Yes' or $label == 'No'){
						$label = gettext($label);				
					}
                }
                $Dataset->addPoint($label, $value);
            }
        } else {
            $otherValue += $row[$this->valueField];
        }
    }
    if($otherValue > 0.0){
        $Dataset->addPoint(gettext("other"), $otherValue); 
        $nColors = $showValues;
    } else {
        $nColors = count($result);
    }

    // create the 1st plot as smoothed area chart using the 1st dataset
    $Plot =& $Plotarea->addNew('Image_Graph_Plot_Pie', $Dataset);

    // set a line color
    $Plot->setLineColor('silver');

    $FillArray =& Image_Graph::factory('Image_Graph_Fill_Array');
    $colors = $this->_makeColors($nColors);
    $Plot->setFillStyle($FillArray);

    foreach($colors as $color){
        $FillArray->addColor('#'.$color);
    }
    if($otherValue > 0.0){
        $FillArray->addColor('#f3f3f3');
    }

    /*$FillArray->addColor('#ffcc99'); //hsv: 30,40,100
    $FillArray->addColor(array(201,255,153));
    $FillArray->addColor('green@0.2');*/
    /*$FillArray->addNew('gradient', array(IMAGE_GRAPH_GRAD_RADIAL, 'white', 'green'));
    $FillArray->addNew('gradient', array(IMAGE_GRAPH_GRAD_RADIAL, 'white', 'blue'));
    */

    // create a Y data value marker
    $Marker =& $Plot->addNew('Image_Graph_Marker_Value', IMAGE_GRAPH_VALUE_X);
    // fill it with white
    $Marker->setFillColor('#ffffff@0.6');
    // and use black border
    $Marker->setBorderColor('#dddddd@0.6');
    // and format it using a data preprocessor
    //$Marker->setDataPreprocessor(Image_Graph::factory('Image_Graph_DataPreprocessor_Formatted', '%0.1f%%'));
    $Marker->setFontSize(8);
    // create a pin-point marker type
    $PointingMarker =& $Plot->addNew('Image_Graph_Marker_Pointing_Angular', array(20, $Marker));
    $PointingMarker->setLineColor('#dddddd@0.6');
    // and use the marker on the plot
    $Plot->setMarker($PointingMarker);
    //$Plot->setMarker($Marker);

    $Plot->setStartingAngle(-90);

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }

    // output the Graph
    $this->_Graph->done($params);
}


/**
 * Generates the SQL statement for the chart, in run-time
 *
 *  @package        run_time
 */
function generateSQL($orderBy = 'value')
{
    global $User;

    if(!empty($this->dashboardChartID)){

        $ModuleInfo = GetModuleInfo($this->moduleID);
        $listPK = $ModuleInfo->getPKField();
        $search = new Search(
            $this->moduleID,
            array($listPK)
        );

        $search->loadChartConditions($User->PersonID, $this->dashboardChartID);

    } else {
        if (!empty($_SESSION['Search_'.$this->moduleID])){
            $search = $_SESSION['Search_'.$this->moduleID];
            $listPK = reset($search->listFields); //simple way of getting module PK (without loading ModuleInfo, etc)
        } else {

            //making an empty search object
            $ModuleInfo = GetModuleInfo($this->moduleID);
            $listPK = $ModuleInfo->getPKField();
            $search = new Search(
                $this->moduleID,
                array($listPK)
            );
        }
    }
    $listFilterSQL = $User->getListFilterSQL($this->moduleID);

    if(!empty($this->subModuleID)){
        $SQL = $search->getCustomListSQL(array($listPK), $orderBy);
        $SQL .= $listFilterSQL;
        $SQL = str_replace('/**SearchSQL**/', $SQL, $this->categorizeSQL);
    } else {
        if('categorize' == $this->mode){
            $SQL = $search->getSummarySQL($this->summaryFields, $this->groupByFields, 'label');
        } else {
            $SQL = $search->getSummarySQL($this->summaryFields, $this->groupByFields, $orderBy);
        }
        $SQL = str_replace('GROUP BY', $listFilterSQL."\nGROUP BY", $SQL);
    }
//workaround to avoid double-wrapping of date fields with DATE_FORMAT()
//    $SQL = TranslateLocalDateSQLFormats($SQL);

    return $SQL;
}
} //end class PieChart





class ParetoChart extends PieChart
{

function getDisplayType()
{
    return gettext("Pareto");
}


function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    $this->_renderSetup();

    //$Plotarea =& $this->_Graph->addNew('plotarea', array('category', 'axis', 'horizontal'));
    $Plotarea =& $this->_Graph->addNew('plotarea');
    //$Plotarea->hideAxis();
    $AxisX =& $Plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
    //$AxisX->setFontAngle(330);

    $Dataset =& Image_Graph::factory('dataset');

    $SQL = $this->generateSQL();

    //get data, count resulting rows
    global $dbh;
    $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    if(!dbErrorCheck($result, false, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result)){
        $this->showError('no_data');
        return false;
    }

    //add data to graph
    $rowCounter = 0;
    $otherValue = 0.0;
    $showValues = 20;
    foreach($result as $row){
        $rowCounter++;
        if($rowCounter <= $showValues){
            if(empty($row[$this->labelField])){
                $label = gettext("(no data)");
            } else {
                $label = trim($row[$this->labelField]);
				if ($label == 'Yes' or $label == 'No'){
					$label = gettext($label);				
				}
            }
            $Dataset->addPoint($label, $row[$this->valueField]); 
        } else {
            $otherValue += $row[$this->valueField];
        }
    }

    $max = $Dataset->maximumY();
    $interval = $this->getInterval($max);
    $AxisY =& $Plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
    $AxisY->setLabelInterval($interval, 1);

    // create the 1st plot as smoothed area chart using the 1st dataset
    $Plot =& $Plotarea->addNew('Image_Graph_Plot_Bar', $Dataset);

    // set a line color
    $Plot->setLineColor('blue');
    //$Plot->setFillStyle($FillArray);
    $Plot->setFillColor('yellow@0.2');

    if('yes' == $this->valueLabels){
        //value markers on bars
        // creates a Y data value marker
        $Marker =& $Plot->addNew('Image_Graph_Marker_Value', IMAGE_GRAPH_VALUE_Y);
        // and use the marker on the plot
        $Plot->setMarker($Marker);
    }

    if($showTitle){
        $this->_Graph->addNew('title', array(gettext($this->title), 11));
    }

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }

    // output the Graph
    $this->_Graph->done($params);
}
} //end class ParetoChart





class BarChart extends ParetoChart
{

function getDisplayType()
{
    return gettext("Bar");
}

function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    $this->_renderSetup();

    $Plotarea =& $this->_Graph->addNew('plotarea');

    $Dataset =& Image_Graph::factory('dataset'); 

    $SQL = $this->generateSQL('label');

    //get data, count resulting rows
    global $dbh;
    $result = $dbh->getAssoc($SQL);
    if(!dbErrorCheck($result, false, false)){

        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result)){
        $this->showError('no_data');
        return false;
    }

    //if it's a date range, insert any missing date entries, i.e. create an array of ALL entries
    if(!empty($this->dateInterval)){
        //get first and last dates
        $search = $_SESSION['Search_'.$this->moduleID];

        /* TODO: extract from-to date from search parameters if present
        foreach($search->wheres as $wName => $wDef){
            foreach($wDef as $subDef){
                print debug_r($subDef , 'wheres');
            }
        }*/

        //from data
        $resultKeys = array_keys($result);
        $fromLabel = reset($resultKeys);
        if(empty($fromLabel)){
            $fromLabel = next($resultKeys); //skip "no data" values
        }
        $toLabel  = end($resultKeys);

        //build range
        switch($this->dateInterval){
        case 'year':            //'2006'
            break;
        case 'monthnum':        //'11'
            break;
        case 'week':            //'52'
            break;
        case 'yearweek':        //'2006 w22'
            $separator = '-W';
            $nMaxLevel2 = 53; //max 53 weeks in a year
            break;
        case 'yearmonth':       //'2006-11'
            $separator = '-';
            $nMaxLevel2 = 12; //12 months in a year
            break;
        case 'yearquarter':     //'2006 q4'
            $separator = ' q';
            $nMaxLevel2 = 4; //4 quarters in a year
            break;
        case 'yearmonthday':    //'2006-11-12'
        default:
            $separator = '-';
            $nMaxLevel2 = 12; //12 months in a year
            break;
        }

        if(!empty($separator)){
            $startParts = explode($separator, $fromLabel);
            $endParts = explode($separator, $toLabel);
        } else {
            $startParts = array($fromLabel);
            $endParts   = array($toLabel);
        }

        $labelParts = count($startParts);
        $labels = array();

        if(!empty($fromLabel) && !empty($startParts)){
            if(intval($startParts[0]) == 0){
                trigger_error('Could not find a usable start date in the data set', E_USER_ERROR);
            }
            if(intval($endParts[0]) == 0){
                trigger_error('Could not find a usable end date in the data set', E_USER_ERROR);
            }
            for($label1 = intval($startParts[0]); $label1<=intval($endParts[0]);$label1++){
                if($labelParts > 1){
                    if($label1 == intval($startParts[0])){
                        $onFirst1 = true;
                    } else {
                        $onFirst1 = false;
                    }
                    if($label1 == intval($endParts[0])){
                        $onLast1 = true;
                    } else {
                        $onLast1 = false;
                    }

                    if(intval($startParts[1]) == 0){
                        trigger_error('Could not find a usable start date in the data set', E_USER_ERROR);
                    }
                    if(intval($endParts[1]) == 0){
                        trigger_error('Could not find a usable end date in the data set', E_USER_ERROR);
                    }
                    if($onFirst1){
                        $start2 = intval($startParts[1]);
                    } else {
                        $start2 = 1;
                    }
                    if($onLast1){
                        $end2 = intval($endParts[1]);
                    } else {
                        $end2 = $nMaxLevel2;
                    }

                    for($label2 = $start2; $label2<=$end2;$label2++){
                        if($labelParts > 2){
                            if($label2 == intval($startParts[1])){
                                $onFirst2 = true;
                            } else {
                                $onFirst2 = false;
                            }
                            if($label2 == intval($endParts[1])){
                                $onLast2 = true;
                            } else {
                                $onLast2 = false;
                            }
                            if(intval($startParts[2]) == 0){
                                trigger_error('Could not find a usable start date in the data set', E_USER_ERROR);
                            }
                            if(intval($endParts[2]) == 0){
                                trigger_error('Could not find a usable end date in the data set', E_USER_ERROR);
                            }
                            if($onFirst2){
                                $start3 = intval($startParts[2]);
                            } else {
                                $start3 = 1;
                            }
                            if($onLast2){
                                $end3 = intval($endParts[2]);
                            } else {
                                $end3 = date('t', mktime(0,0,0, $label2, 1, $label1));
                            }

                            for($label3 = $start3; $label3<=$end3;$label3++){
                                $labels[] = 
                                    $label1 
                                    . $separator .
                                    str_pad($label2, 2, '0', STR_PAD_LEFT)
                                    . $separator .
                                    str_pad($label3, 2, '0', STR_PAD_LEFT)
                                    ;
                            }

                        } else {
                            if($nMaxLevel2 >= 10){
                                $labels[] = $label1 . $separator . str_pad($label2, 2, '0', STR_PAD_LEFT);
                            } else {
                                $labels[] = $label1 . $separator . $label2;
                            }
                        }
                    }
                } else {
                    $labels[] = $label1;
                }
            }
        }
    }

    //create dataset
    if(!empty($this->dateInterval)){
        foreach($labels as $label){
            $value = $result[$label];
            $Dataset->addPoint($label, $value);
        }
    } else {
        foreach($result as $label => $value){
            $Dataset->addPoint($label, $value);
        }
    }

    $max = $Dataset->maximumY();
    $interval = $this->getInterval($max);
    $AxisX =& $Plotarea->getAxis(IMAGE_GRAPH_AXIS_X);
    $AxisX->setFontAngle(320);
    $AxisY =& $Plotarea->getAxis(IMAGE_GRAPH_AXIS_Y);
    $AxisY->setLabelInterval($interval, 1);

    // create the 1st plot as smoothed area chart using the 1st dataset
    if($Dataset->_count < 30){
        $Plot =& $Plotarea->addNew('Image_Graph_Plot_Bar', $Dataset);
    } else {
        $labelinterval = round($Dataset->_count / 15);
        $AxisX->setLabelInterval($labelinterval, 1);
        $Plot =& $Plotarea->addNew('Image_Graph_Plot_Line', $Dataset);
    }

    // set a line color
    $Plot->setLineColor('blue');
    //$Plot->setFillStyle($FillArray);
    $Plot->setFillColor('#4283c5@0.3');

    if($showTitle){
        $this->_Graph->addNew('title', array(gettext($this->title), 11));
    }

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }

    if('yes' == $this->valueLabels){
        //value markers on bars
        // creates a Y data value marker
        $Marker =& $Plot->addNew('Image_Graph_Marker_Value', IMAGE_GRAPH_VALUE_Y);
        // and use the marker on the plot
        $Plot->setMarker($Marker);
    }

    // output the Graph
    $this->_Graph->done($params);
}

} //end class BarChart





class RadarChart extends PieChart
{

function getDisplayType()
{
    return gettext("Radar");
}


function getMinimumDataPoints()
{
    return 3;
}


function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    $this->_renderSetup();

    $Dataset =& Image_Graph::factory('dataset');

    $SQL = $this->generateSQL('label');

    //get data, count resulting rows
    global $dbh;
    $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    if(!dbErrorCheck($result, false, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }
    trace($result, 'chart data');

    //add data to graph
    if(!empty($result)){
        $hasData = false;
        foreach($result as $row){
            if(end($row) > 0){
                $hasData = true;
            }
        }

        if($hasData){
            $Plotarea =& $this->_Graph->addNew('Image_Graph_Plotarea_Radar');
            foreach($result as $row){
                if(empty($row[$this->labelField])){
                    $label = gettext("(no data)");
                } else {
                    $label = trim($row[$this->labelField]);
					if ($label == 'Yes' or $label == 'No'){
						$label = gettext($label);				
					}
                }
                $Dataset->addPoint($label, $row[$this->valueField]); 
            }
            $Plotarea->addNew('Image_Graph_Grid_Polar', IMAGE_GRAPH_AXIS_Y);
            $Plot =& $Plotarea->addNew('Image_Graph_Plot_Smoothed_Radar', $Dataset);

            $Plot->setLineColor('blue@0.4');
            $Plot->setFillColor('blue@0.2');
        }
    } else {
        $this->showError('no_data');
    }

    if($showTitle){
        $this->_Graph->addNew('title', array(gettext($this->title), 11));
    }

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }

    // output the Graph
    $this->_Graph->done($params);
}
} //end class RadarChart

?>