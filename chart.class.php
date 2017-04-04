<?php
/**
 * Class Definitions for Charts
 *
 * These are wrapper classes for the PEAR Image/Graph.php classes
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2010 Active Agenda Inc., All Rights Reserved.
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
 * @copyright      2003-2010 Active Agenda Inc.
 * @license        http://www.activeagenda.net/license  RPL 1.5
 * @version        SVN: $Revision: 1863 $
 * @last-modified  SVN: $Date: 2010-03-02 23:28:17 +0100 (Wt, 02 mar 2010) $
 * @package        web_time
 **/


if(!defined('EZC_PATH')){
    define('EZC_PATH', PEAR_PATH . '/ezc');
}

require EZC_PATH.'/Base/base.php';

function __autoload( $className )
{
    ezcBase::autoload( $className );
}


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
var $_ezg;
var $width;
var $height;

var $rowLimit;

var $groupByFields = array();
var $summaryFields = array();
var $labelField;
var $valueField;
var $dateInterval;

var $fileName;

function Factory($element, $moduleID)
{
    $chart = new $element->type(
        $moduleID,
        $element->getAttr('name'),
        $element->getAttr('title'),
        $element->getAttr('subModuleID'),
        $element->getAttr('mode'),
        $element->getAttr('valueLabels')
    );
	
	
	if( isset(	$element->attributes['rowLimit'] ) ){
		$chart->rowLimit = $element->getAttr('rowLimit');
	}
    if(isset($element->attributes['subModuleID'])){
        $baseModuleID = $element->getAttr('subModuleID');
    } else {
        $baseModuleID = $moduleID;
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            //verify that field exists in module
            $mf = GetModuleField($baseModuleID, $sub_element->name);

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
        CheckSQL(str_replace('/**SearchSQL**/', 'SELECT 1', $SQL));
    }
    return $chart;
}


/**
 * Sets up the proper palette for the chart.
 *
 * This method cannot be called before the $_ezg graph object has been created.
 *
 * @return null
 */
protected function assignPalette()
{
    if(!defined('CHART_PALETTE')) {
        define('CHART_PALETTE', 'custom');
    }
    switch (CHART_PALETTE) {
        case 'custom':
            $this->_ezg->palette = new ezcGraphPaletteTango();
            break;
		case 'red':
            $this->_ezg->palette = new ezcGraphPaletteEzRed();
            break;
        case 'green':
            $this->_ezg->palette = new ezcGraphPaletteEzGreen();
            break;
        case 'blue':
            $this->_ezg->palette = new ezcGraphPaletteEzBlue();
            break;
		case 'black':
            $this->_ezg->palette = new ezcGraphPaletteBlack();
            break;
		case 'ez':
            $this->_ezg->palette = new ezcGraphPaletteEz();
            break;
        default:
            //don't assign a palette: this will use the default (Tango) one.
            break;
    }
    return;
}


function render($width = 500, $height = 400, $showTitle = true){
    return false;
}


/**
 * Returns a string that describes the chart type.
 */
function getDisplayType()
{
    return gettext("Unknown"); //override
}


/**
 * Now only used when displaying error messages
 */
function _renderSetup()
{
    // include libraries
    require_once PEAR_PATH . '/Image/Graph.php';
    require_once PEAR_PATH . '/Image/Canvas.php';

    // create a PNG canvas and enable antialiasing (canvas implementation)
    $this->_Canvas =& Image_Canvas::factory('png', array('width' => $this->width, 'height' => $this->height, //'antialias' => 'driver'
    ));

    // create the graph
    $this->_Graph =& Image_Graph::factory('graph', $this->_Canvas);
    // add a TrueType font
    $Font =& $this->_Graph->addNew('font', 'Verdana');
    // set the font size to 11 pixels
    $Font->setSize(8);
    $this->_Graph->setFont($Font);

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
        $msg = "\n\n\n".gettext("There is no matching data to render this chart.");
        break;
    case 'not_enough_data':
        $isError = false;
        $msg = "\n\n\n".gettext("Not enough data to render this chart.");
        break;
	 case 'to_much_data':
        $isError = false;
        $msg = "\n\n\n".gettext("To much data to render this chart.");
        break;
    case 'SQL_error':
        $msg = "\n\n\n".gettext("Error: SQL error.");
        break;
    default:
        $msg = "\n\n\n".gettext("Error: Unknown error type.");
        break;
    }
    if($isError){
        trigger_error('chart showError: '.$errorInfo." ($msg)", E_USER_WARNING);
    }
    $this->_renderSetup();
    $this->_Graph->addNew('title', array($msg, 10));

    $params = null;
    if(!empty($this->fileName)){
        $params = array('filename' => $this->fileName);
    }
    $this->_Graph->done($params);
}

function getETag()
{
    return null; //override
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


function getETag()
{
	$SQL = $this->generateSQL();
    $mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        //$this->showError('SQL_error', $SQL);
        return null;
    }

    if(empty($result) || count($result) == 0){
        //$this->showError('no_data');
        return null;
    }
    $data = array();
    foreach($result as $row){
		if( !isset( $row[$this->valueField] ) ){
			$row[$this->valueField] = 0;
		}
        $data[$row[$this->labelField]] = $row[$this->valueField];
    } 
	return md5( serialize( $data ) );
}

function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;

    $this->_ezg = new ezcGraphPieChart();
    $this->assignPalette();

    $SQL = $this->generateSQL();
	
    if($showTitle){
        $this->_ezg->title = gettext($this->title);
    }

    $this->_ezg->legend = false;

    $mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result) || count($result) == 0){
        $this->showError('no_data');
        return false;
    }
    $data = array();
    foreach($result as $row){
		if( !isset( $row[$this->valueField] ) ){
			$row[$this->valueField] = 0;
		}
        $data[$row[$this->labelField]] = $row[$this->valueField];
    }   

    $this->_ezg->data[$this->title] = new ezcGraphArrayDataSet($data);

    if(defined('CHART_IMAGE_PIE_3D') && CHART_IMAGE_PIE_3D){
        $this->_ezg->renderer = new ezcGraphRenderer3d();
        $this->_ezg->renderer->options->pieChartShadowSize = 10;
        $this->_ezg->renderer->options->pieChartGleam = .2;
        $this->_ezg->renderer->options->dataBorder = .2;
        $this->_ezg->renderer->options->pieChartHeight = 16;
        $this->_ezg->renderer->options->legendSymbolGleam = .5;
        $this->_ezg->renderer->options->pieChartRotation = .75;
        $this->_ezg->renderer->options->pieChartOffset = 180;
    }

    $chart_image_driver = 'gd';
    if(defined('CHART_IMAGE_DRIVER')){
        $chart_image_driver = CHART_IMAGE_DRIVER;
    }


    switch ($chart_image_driver) {
    case 'cairo':
        //determine which driver to use...
        $this->_ezg->driver = new ezcGraphCairoOODriver();

        break;
    case 'gd':
    default:

        require_once PEAR_PATH.'/Image/Canvas/Tool.php';
        $fontMap = Image_Canvas_Tool::fontMap('Verdana');

        $this->_ezg->options->font = $fontMap;
        $this->_ezg->driver = new ezcGraphGdDriver();
        $this->_ezg->driver->options->supersampling = 2;
        $this->_ezg->driver->options->imageFormat = IMG_PNG;

        break;
    }
    $this->_ezg->options->percentThreshold = 0.01;
    $this->_ezg->options->summarizeCaption = gettext('Others');
    $this->_ezg->options->font->minFontSize = 5;
    $this->_ezg->options->font->maxFontSize = 15;
    $this->_ezg->title->font->maxFontSize = 16;

    if(empty($this->fileName)){
		try{
			$this->_ezg->renderToOutput($this->width, $this->height);
		}catch( Exception $e ){			
			$this->showError('to_much_data');
			//trigger_error('chart showError: '.$errorInfo.$e->getMessage(), E_USER_WARNING);
		}
    } else {
        $this->_ezg->render($this->width, $this->height, $this->fileName);
    }
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
		// not active due to problems with nested SELECT calls
        //$SQL = str_replace('GROUP BY', $listFilterSQL."\nGROUP BY", $SQL);
    }

	if( isset( $this->rowLimit ) ){
		$SQL .= " LIMIT {$this->rowLimit}";
	}
	
    return $SQL;
}
} //end class PieChart





class ParetoChart extends PieChart
{

function getDisplayType()
{
    return gettext("Pareto");
}

function getETag()
{	
	$SQL = $this->generateSQL();
	$mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if( !mdb2ErrorCheck($result, false) ){
        //$this->showError('SQL_error', $SQL);
        return null;
    }

    if( empty($result) || count($result) == 0 ){
        //$this->showError('no_data');
        return null;
    }

    $data = array();
    foreach($result as $rowIx => $row){
        if($rowIx < 25){
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data[$row[$this->labelField]] = $row[$this->valueField];
        } else {
            if(!isset($data['Other'])){
                $data['Other'] = 0;
            }
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data['Other'] += $row[$this->valueField];
        }
    }
	return md5( serialize( $data ) );
}

function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
	//$this->_ezg = new ezcGraphBarChart();
    $this->_ezg = new ezcGraphHorizontalBarChart();
    $this->assignPalette();

    $SQL = $this->generateSQL();

    if($showTitle){
        $this->_ezg->title = gettext( $this->title );
    }

    $this->_ezg->legend = false;

    $mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result) || count($result) == 0){
        $this->showError('no_data');
        return false;
    }
    if(count($result) < 2){
        $this->showError('not_enough_data');
        return false;
    }


    $data = array();
    foreach($result as $rowIx => $row){
        if($rowIx < 25){
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data[$row[$this->labelField]] = $row[$this->valueField];
        } else {
            if(!isset($data['Other'])){
                $data['Other'] = 0;
            }
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data['Other'] += $row[$this->valueField];
        }
    }

    $rotateLabels = false;
    if(count($data) > 12){
        $rotateLabels = true;
    }

    $this->_ezg->data[$this->title] = new ezcGraphArrayDataSet($data);
    if('yes' == $this->valueLabels){
        $this->_ezg->data[$this->title]->highlight = true;
    }
/* // Due to change to ezcGraphHorizontalBarChart //
    $this->_ezg->xAxis->labelCount = count( $this->_ezg->data[$this->title] );
    $this->_ezg->yAxis->min = 0;
*/
    if($rotateLabels) {
        $this->_ezg->xAxis->axisLabelRenderer = new ezcGraphAxisRotatedLabelRenderer();

        // Define angle manually in degree
        $this->_ezg->xAxis->axisLabelRenderer->angle = 30;

        // Increase axis space
        $this->_ezg->xAxis->axisSpace = .25;
    }


    $chart_image_driver = 'gd';
    if(defined('CHART_IMAGE_DRIVER')){
        $chart_image_driver = CHART_IMAGE_DRIVER;
    }

/**
    //This makes the chart 3d:
    $this->_ezg->renderer = new ezcGraphRenderer3d();
    $this->_ezg->data[$this->title]->symbol = ezcGraph::NO_SYMBOL;
    $this->_ezg->renderer->options->barChartGleam = .2;
    $this->_ezg->renderer->options->depth = .05;
*/
    switch ($chart_image_driver) {
    case 'cairo':
        //determine which driver to use...
        $this->_ezg->driver = new ezcGraphCairoOODriver();

        break;
    case 'gd':
    default:

        require_once PEAR_PATH.'/Image/Canvas/Tool.php';
        $fontMap = Image_Canvas_Tool::fontMap('Verdana');

        $this->_ezg->options->font = $fontMap;
        $this->_ezg->driver = new ezcGraphGdDriver();
        $this->_ezg->driver->options->supersampling = 2;
        $this->_ezg->driver->options->imageFormat = IMG_PNG;

        break;
    }
    $this->_ezg->options->font->minFontSize = 5;
    $this->_ezg->options->font->maxFontSize = 15;
    $this->_ezg->title->font->maxFontSize = 16;
    
    if(empty( $this->fileName) ){
		try{
			$this->_ezg->renderToOutput($this->width, $this->height);
		}catch( Exception $e ){
			$this->showError('to_much_data');			
			//trigger_error('chart showError: '.$errorInfo.$e->getMessage(), E_USER_WARNING);
		}
    } else {
        $this->_ezg->render($this->width, $this->height, $this->fileName);
    }
}
} //end class ParetoChart





class BarChart extends ParetoChart
{

function getDisplayType()
{
    return gettext("Bar");
}

function getETag()
{
	$SQL = $this->generateSQL('label');
	$mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        //$this->showError('SQL_error', $SQL);
        return null;
    }
    if(empty($result) || count($result) == 0){
        //$this->showError('no_data');
        return null;
    }
    if(count($result) < 2){
        //$this->showError('not_enough_data');
        return null;
    }  
	
	$dateLabels = false;
    $lastRow = end($result);
    $lastLabel = $lastRow[$this->labelField];
    if(false !== strtotime( $lastLabel )) {
        $dateLabels = true;
    }
    
    $data = array();

    //create dataset
    if(!empty($this->dateInterval) || $dateLabels){
        foreach($result as $rowIx => $row){
            if(!empty($row[$this->labelField])){
                switch ($this->dateInterval){
                case 'year':
                    //make it look like January 1st
                    $date = $row[$this->labelField].'-01-01';
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearquarter':
                    list($year, $quarter) = explode('q', $row[$this->labelField]);
                    $date = trim($year) .'-'. 3*$quarter .'-01';
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearweek':
                    list($year, $week) = explode('-W', $row[$this->labelField]);
                    $dateStamp = strtotime($year . '-01-04 +' . ($week - 1) . ' weeks');
                    $date = strftime('%Y-%m-%d', $dateStamp);
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearmonth':
                    //add the day part
                    $data[$row[$this->labelField].'-01'] = $row[$this->valueField];
                    break;
                default:
                    $data[$row[$this->labelField]] = $row[$this->valueField];
                    break;
                }
            }
        }
    } else {
        foreach($result as $rowIx => $row){
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data[$row[$this->labelField]] = $row[$this->valueField];
        }
    }

	return md5( serialize( $data ) );
}

function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    
    $this->_ezg = new ezcGraphBarChart();
    $this->assignPalette();

    $SQL = $this->generateSQL('label');

    if($showTitle){
        $this->_ezg->title = gettext( $this->title );
    }

    $this->_ezg->legend = false;

    $mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result) || count($result) == 0){
        $this->showError('no_data');
        return false;
    }
    if(count($result) < 2){
        $this->showError('not_enough_data');
        return false;
    }   


    $dateLabels = false;
    $lastRow = end($result);
    $lastLabel = $lastRow[$this->labelField];
    if(false !== strtotime( $lastLabel )) {
        $dateLabels = true;
    }
    
    $data = array();

    //create dataset
    if(!empty($this->dateInterval) || $dateLabels){
        foreach($result as $rowIx => $row){
            if(!empty($row[$this->labelField])){
                switch ($this->dateInterval){
                case 'year':
                    //make it look like January 1st
                    $date = $row[$this->labelField].'-01-01';
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearquarter':
                    list($year, $quarter) = explode('q', $row[$this->labelField]);
                    $date = trim($year) .'-'. 3*$quarter .'-01';
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearweek':
                    list($year, $week) = explode('-W', $row[$this->labelField]);
                    $dateStamp = strtotime($year . '-01-04 +' . ($week - 1) . ' weeks');
                    $date = strftime('%Y-%m-%d', $dateStamp);
                    $data[$date] = $row[$this->valueField];
                    break;
                case 'yearmonth':
                    //add the day part
                    $data[$row[$this->labelField].'-01'] = $row[$this->valueField];
                    break;
                default:
                    $data[$row[$this->labelField]] = $row[$this->valueField];
                    break;
                }
            }
        }

        $this->_ezg->xAxis = new ezcGraphChartElementDateAxis();
        $this->_ezg->data[$this->title] = new ezcGraphArrayDataSet($data);
        $this->_ezg->data[$this->title]->displayType = ezcGraph::LINE;
    } else {
        foreach($result as $rowIx => $row){
			if( !isset( $row[$this->valueField] ) ){
				$row[$this->valueField] = 0;
			}
            $data[$row[$this->labelField]] = $row[$this->valueField];
        }
        $this->_ezg->data[$this->title] = new ezcGraphArrayDataSet($data);
    }

    if('yes' == $this->valueLabels){
        $this->_ezg->data[$this->title]->highlight = true;
    }

    if(count($this->_ezg->data[$this->title]) >= 30){
        //change to line graph if data has too many values
        $this->_ezg->data[$this->title]->displayType = ezcGraph::LINE;
    }

    $this->_ezg->yAxis->min = 0;

    $chart_image_driver = 'gd';
    if(defined('CHART_IMAGE_DRIVER')){
        $chart_image_driver = CHART_IMAGE_DRIVER;
    }

    switch ($chart_image_driver) {
    case 'cairo':
        //determine which driver to use...
        $this->_ezg->driver = new ezcGraphCairoOODriver();

        break;
    case 'gd':
    default:

        require_once PEAR_PATH.'/Image/Canvas/Tool.php';
        $fontMap = Image_Canvas_Tool::fontMap('Verdana');

        $this->_ezg->options->font = $fontMap;
        $this->_ezg->driver = new ezcGraphGdDriver();
        $this->_ezg->driver->options->supersampling = 2;
        $this->_ezg->driver->options->imageFormat = IMG_PNG;

        break;
    }

    $this->_ezg->options->font->minFontSize = 5;
    $this->_ezg->options->font->maxFontSize = 15;
    $this->_ezg->title->font->maxFontSize = 16;
    if( empty($this->fileName) ){
		try{
			$this->_ezg->renderToOutput($this->width, $this->height);
		}catch( Exception $e ){
			$this->showError('to_much_data');			
			//trigger_error('chart showError: '.$errorInfo.$e->getMessage(), E_USER_WARNING);
		}
    } else {
        $this->_ezg->render($this->width, $this->height, $this->fileName);
    }
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

function getETag()
{
	$SQL = $this->generateSQL('label');
	$mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        //$this->showError('SQL_error', $SQL);
        return null;
    }

    if(empty($result) || count($result) == 0){
        //$this->showError('no_data');
        return null;
    }

    $data = array();
    foreach($result as $rowIx => $row){
		if( !isset( $row[$this->valueField] ) ){
			$row[$this->valueField] = 0;
		}
        $data[$row[$this->labelField]] = $row[$this->valueField];
    }
	return md5( serialize( $data ) );
}

function render($width = 500, $height = 400, $showTitle = true)
{
    $this->width = $width; 
    $this->height = $height;
    $this->_ezg = new ezcGraphRadarChart();
    $this->assignPalette();
    $this->_ezg->options->fillLines = 160;

    $SQL = $this->generateSQL('label');

    if($showTitle){
        $this->_ezg->title = gettext( $this->title );
    }

    $this->_ezg->legend = false;

    $mdb2 = GetMDB2();
    $result = $mdb2->queryAll($SQL);
    if(!mdb2ErrorCheck($result, false)){
        $this->showError('SQL_error', $SQL);
        return false;
    }

    if(empty($result) || count($result) == 0){
        $this->showError('no_data');
        return false;
    }

    $data = array();
    foreach($result as $rowIx => $row){
		if( !isset( $row[$this->valueField] ) ){
			$row[$this->valueField] = 0;
		}
        $data[$row[$this->labelField]] = $row[$this->valueField];
    }
    
    $this->_ezg->axis->min = 0;
    $this->_ezg->axis->minorStep = 1.0;
    $this->_ezg->axis->majorStep = 10.0;
    $this->_ezg->axis->axisSpace = 0.05;
    $this->_ezg->axis->labelMargin = 5;

    $dataset = new ezcGraphArrayDataSet( $data );
    $dataset[] = reset( $data );
    $this->_ezg->data[$this->title] = $dataset;

    $chart_image_driver = 'gd';
    if(defined('CHART_IMAGE_DRIVER')){
        $chart_image_driver = CHART_IMAGE_DRIVER;
    }

    switch ($chart_image_driver) {
    case 'cairo':
        //determine which driver to use...
        $this->_ezg->driver = new ezcGraphCairoOODriver();
        break;
    case 'gd':
    default:
        require_once PEAR_PATH.'/Image/Canvas/Tool.php';
        $fontMap = Image_Canvas_Tool::fontMap('Verdana');

        $this->_ezg->options->font = $fontMap;
        $this->_ezg->driver = new ezcGraphGdDriver();
        $this->_ezg->driver->options->supersampling = 2;
        $this->_ezg->driver->options->imageFormat = IMG_PNG;
        break;
    }

    $this->_ezg->options->font->minFontSize = 2;
    $this->_ezg->options->font->maxFontSize = 9;
    $this->_ezg->title->font->maxFontSize = 16;
    if( empty($this->fileName) ){
		try{
			$this->_ezg->renderToOutput($this->width, $this->height);
		}catch( Exception $e ){
			$this->showError('to_much_data');			
			//trigger_error('chart showError: '.$errorInfo.$e->getMessage(), E_USER_WARNING);
		}
    } else {
        $this->_ezg->render($this->width, $this->height, $this->fileName);
    }
}
} //end class RadarChart
