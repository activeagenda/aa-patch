<?php
/**
 * Defines the List rendering classes
 *
 * This file contains the definition of the ListData and ListRenderer classes.
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
 *
 * @author         Mattias Thorslund <mthorslund@activeagenda.net>
 * @copyright      2003-2009 Active Agenda Inc.
 * @license        http://www.activeagenda.net/license  RPL 1.5
 * @version        SVN: $Revision: 1548 $
 * @last-modified  SVN: $Date: 2009-03-05 16:27:47 +0100 (Cz, 05 mar 2009) $
 */


class ListData
{
var $moduleID;
var $listSQL;
var $fromSQL;
var $countSQL;
var $recordIDSelect;
var $rowsPerPage;
var $fieldTypes = array();
var $fieldFormats = array();
var $useListSequence;
var $_count = -1; //cached row count

function ListData(
    $moduleID,
    $SQL,
    $rowsPerPage = 10,
    $fieldTypes = null,
    $useListSequence = false,
    $countSQL = null,
    $fieldFormats = null
    )
{
    $this->moduleID = $moduleID;
    if(is_array($SQL)){
        $this->listSQL = $SQL[0] . $SQL[1];
        $this->fromSQL = $SQL[1];
        $this->recordIDSelect = $SQL[2];
    } else {
        $this->listSQL = $SQL;
    }
    if(empty($rowsPerPage)){
        $this->rowsPerPage = 10;
    } else {
        $this->rowsPerPage = $rowsPerPage;
    }
    $this->fieldTypes = $fieldTypes;
    $this->fieldFormats = $fieldFormats;
    $this->useListSequence = $useListSequence;
    $this->countSQL = $countSQL;
} //end ListData constructor


/**
 *  Returns the total number of rows in the result.
 */
function getCount()
{
    if($this->_count == -1){
        global $dbh;
        $SQL = $this->getCountSQL();
        $result = $dbh->getOne($SQL);
        dbErrorCheck($result);
        $this->_count = $result;
        return $result;
    } else {
        return $this->_count;
    }
} //end getCount


/**
 *  Returns all the rows in the result.
 */
function getData($orderBys = null)
{
    global $dbh;

    $SQL = $this->getListSQL();
    $SQL .= $this->getOrderBySQL($orderBys);

    $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($result);

    return $result;
} //end getData


/**
 *  Returns the rows of the data that belongs to a page.
 */
function getPageData($startRow, $orderBys = null)
{
    global $dbh;

    $SQL = $this->getListSQL();
    $SQL .= $this->getOrderBySQL($orderBys);
    $SQL .= " LIMIT $startRow, {$this->rowsPerPage}";

    $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($result);

    if($this->useListSequence){
        $this->saveListSequence($result, $startRow);
    }

    return $result;
} //end getPageData


/**
 *  Returns an ORDER BY clause
 */
function getOrderBySQL($orderBys)
{
    $SQL = '';
    if(count($orderBys) > 0){
        $SQL .= ' ORDER BY ';
        $obSQLs = array();
        foreach($orderBys as $obField => $desc){
            if($desc){
                $obSQLs[] = $obField . ' DESC';
            } else {
                $obSQLs[] = $obField;
            }
        }
        $SQL .= join(',',$obSQLs);
    }
    return $SQL;
} //end getOrderBySQL


/**
 *  Re-writes the listSQL into a SELECT COUNT(*) statement
 */
function getCountSQL()
{
    global $User;
    $listFilterSQL = $User->getListFilterSQL($this->moduleID);
    if(empty($this->countSQL)){
        if(empty($this->fromSQL)){
            $SQL = 'SELECT COUNT(*) FROM ('. $this->listSQL . $listFilterSQL . ') as row_count';
        } else {
            $SQL = 'SELECT Count(*) ' . $this->fromSQL . $listFilterSQL;
        }
    } else {
        $SQL = $this->countSQL . $listFilterSQL;
    }
    return $SQL;
} //end getCountSQL


/**
 *  Returns the list SQL with the filter conditions if applicable
 */
function getListSQL()
{
    global $User;
    $listFilterSQL = $User->getListFilterSQL($this->moduleID);
    return $this->listSQL . $listFilterSQL;
} //end getListSQL


/**
 *  Saves an array of the current page to the user's Session
 */
function saveListSequence(&$result, $startRow)
{
    if(count($result) > 0){
        $listSequence['count'] = $this->getCount();
        $firstColName = reset(array_keys($result[0]));
        foreach($result as $row_ix => $row){
            $listSequence['rows'][$row_ix + $startRow] = $row[$firstColName];
        }

        $listSequence['sql'] = $this->getSeqSQL();
        $listSequence['rpp'] = $this->rowsPerPage;
        $_SESSION[$this->moduleID . '_ListSeq'] = $listSequence;
    }
} //end saveListSequence


/**
 *  Returns list sequence SQL statement for caching in session
 */
function getSeqSQL()
{
    global $User;
    $listFilterSQL = $User->getListFilterSQL($this->moduleID);

    //need the record ID column here
    $SQL = 'SELECT ' . $this->recordIDSelect . ' ' . $this->fromSQL . $listFilterSQL;
    return $SQL;
} //end getSeqSQL

} //end class ListData



class ListRenderer
{
var $moduleID;
var $showRowPerPageSelector = true; //whether to render the list of links where user can select number of rows per page
var $showRowLinks = true;
var $rowLink; //pattern for the row links
var $pagingLink;
var $headerPhrases;
var $fieldAlignments = array();
var $listData;
var $useBestPractices = false;
var $gridType; //list, view, edit, etc.
var $linkFields = array();
var $formatOptions = array();

function ListRenderer(
    $moduleID,
    &$listData,
    $headerPhrases,
    $rowLink = null,
    $pagingLink = null,
    $fieldAlignments = null,
    $gridType = 'list',
    $linkFields = null,
    $formatOptions = null
    )
{
    $this->moduleID = $moduleID;
    $this->headerPhrases = $headerPhrases;
    $this->fieldAlignments = $fieldAlignments;
    if(empty($rowLink)){
        $this->showRowLinks = false;
    } else {
        $this->rowLink = $rowLink;
    }
    $this->pagingLink = $pagingLink;
    $this->listData = $listData;
    $this->gridType = $gridType;
    if(!empty($linkFields)){
        $this->linkFields = $linkFields;
    }
    $this->formatOptions = $formatOptions;
    if(isset($this->formatOptions['suppressRecordIcons']) && $this->formatOptions['suppressRecordIcons']){
        $this->showRowLinks = false;
    }
    if(isset($this->formatOptions['suppressPaging']) && $this->formatOptions['suppressPaging']){
        $this->showRowPerPageSelector = false;
    }
} //end ListRenderer constructor


function render($startRow = null, $defaultOrderBys = null)
{
    global $theme;
    include_once $theme .'/component_html.php';

    $nRows = $this->listData->getCount();
    $orderBys = array();

    if(!empty($_GET['ob'])){
        $inputOBs = split(',', $_GET['ob']);
        $fieldNames = array_keys($this->headerPhrases);

        foreach($inputOBs as $inputOB){
            if('-' == $inputOB[0]){
                $desc = true;
                $inputOB = substr($inputOB, 1);
            } else {
                $desc = false;
            }
            if(in_array($inputOB, $fieldNames)){
                $orderBys[$inputOB] = $desc;
            }
        }

    } else {
        if(!empty($_SESSION['ListOrder_'.$this->moduleID.'_'.$this->gridType])){
            $orderBys = $_SESSION['ListOrder_'.$this->moduleID.'_'.$this->gridType];
        } else {
            if(!empty($defaultOrderBys)){
                $orderBys = $defaultOrderBys;
            }
        }
        if(count($orderBys) > 0){
            foreach($orderBys as $orderByField => $desc){
                if(!isset($this->headerPhrases[$orderByField])){
                    if(!isset($this->fieldAlignments[$orderByField])){ //a workaround to allow pre-defined sorting by a hidden column
                        unset($orderBys[$orderByField]);
                        trigger_error("Cannot order by the field $orderByField.", E_USER_WARNING);
                    }
                }
            }
        }
    }

    $_SESSION['ListOrder_'.$this->moduleID.'_'.$this->gridType] = $orderBys;

    if(-1 == $this->listData->rowsPerPage){
        $paging = false;
        $pageStartRow = 0;
    } else {
        $paging = true;
        $pageStartRow = $this->getPageStartRow($startRow, $nRows);
    }

    if($nRows > 0){
        if($this->showRowPerPageSelector && $nRows > 5){
            $rppSelector = $this->renderRowsPerPageSelector();
        } else {
            $rppSelector = '';
        }

        if($paging && $nRows > $this->listData->rowsPerPage){
            $pageSelector = $this->renderPageSelector($nRows, $pageStartRow, $orderBys);
            $pageSelector .= ' &nbsp;&nbsp;&nbsp; '. $rppSelector;
        } else {
            $pageSelector = $rppSelector;
        }
        $col_count = count($this->headerPhrases)+1; //adjust to deduct hidden columns
        $pageSelector = sprintf(
            GRID_NAV_ROW,
            $col_count,
            $pageSelector
        );

        $headerHTML = $this->renderHeaders($orderBys);
        $rowHTML = $this->renderRows($pageStartRow, $orderBys); //rendered rows

        if($this->gridType == 'edit' || $this->gridType == 'edit_nfe' || $this->gridType == 'edit_noadd'){
            $content = sprintf(
                VIEWGRID_TABLE,
                $pageSelector . $headerHTML . $rowHTML
            );
        } elseif($this->listData->rowsPerPage > 10){ //arbitrary number to decide when page selector needs to appear both at the top and bottom
            $content = sprintf(
                VIEWGRID_TABLE,
                $pageSelector . $headerHTML . $rowHTML . $pageSelector
            );
        } else {
            $content = sprintf(
                VIEWGRID_TABLE,
                $headerHTML . $rowHTML . $pageSelector
            );
        }

        switch($this->gridType){
        case 'view':
        case 'edit':
        case 'edit_nfe':
        case 'edit_noadd':
            break;
        case 'list':
        default:
            if(!defined('IS_RPC') || !IS_RPC) {
                $content = '<div id="list_'.$this->moduleID.'" class="listwrap sz2tabs">' . $content . '</div>';;
            }
            break;
        }
    } else {
        switch($this->gridType){
        case 'view':
        case 'edit':
        case 'edit_nfe':
        case 'edit_noadd':
            $headerHTML = $this->renderHeaders($orderBys);
            $content = sprintf(
                VIEWGRID_TABLE,
                $headerHTML
            );
            break;
        case 'list':
        default:
            $content .= gettext("The request returned no data. Please try a different search.<br><br>");
            break;
        }
    }
    return $content;
} //end render


function renderHeaders($orderBys)
{
    global $theme_web;

    global $qsArgs;
    $obArgs = $qsArgs;
    unset($obArgs['ob']);
    $obQS = MakeQS($obArgs);
    $link = $this->pagingLink . ''. $obQS;

    $fieldNames = array_keys($this->headerPhrases);

    //first header cell is special
    switch($this->gridType){
    case 'edit':
        $content = sprintf(EDITGRID_HEADER_CELL_ADDNEW, gettext("Add New"), $theme_web);
        break;
    case 'edit_nfe':
    case 'edit_noadd':
    case 'view':
    case 'list':
    default:
        $content = sprintf(GRID_HEADER_CELL, '', '');
        break;
    }

    foreach($fieldNames as $fieldName){
        $alignment = '';
        if(isset($this->fieldAlignments[$fieldName])){
            $alignment = $this->fieldAlignments[$fieldName];
        }
        if('hide' != $alignment){
            $desc = false;
            if(isset($orderBys[$fieldName])){
                $desc = $orderBys[$fieldName];
                if(!$desc){
                    $obName = '-'.$fieldName;
                    $obImg = "&nbsp;<img src=\"$theme_web/img/order_asc.gif\" height=\"16\" width=\"16\" alt=\"".gettext("ascending")."\" />";
                } else {
                    $obName = $fieldName;
                    $obImg = "&nbsp;<img src=\"$theme_web/img/order_desc.gif\" height=\"16\" width=\"16\" alt=\"".gettext("descending")."\" />";
                }
            } else {
                $obName = $fieldName;
                $obImg = '';
            }
            $content .= sprintf(
                GRID_HEADER_CELL,
                'javascript:updateList(\'list_'.$this->moduleID.'\',\''.$link . '&amp;ob='.$obName.'&amp;rpc=1\');',
                ShortPhrase($this->headerPhrases[$fieldName]).$obImg
                //str_replace(' ', '&nbsp;', $this->headerPhrases[$fieldName]).$obImg
            );
        }
    }
    $content = sprintf(GRID_HEADER_ROW, $content);
    return $content;
} //end renderHeaders


function renderRows($startRow, $orderBys)
{
    global $theme_web;
    global $qsArgs;
    $sqQSargs = $qsArgs;
    unset($sqQSargs['sr']);
    unset($sqQSargs['rpc']);
    unset($sqQSargs['rid']);

    $qeJS = '';
    $qeMsg = '';
    $vgModuleID = '';
    switch($this->gridType){
    case 'view':
        $linkTemplate = VIEW_GRID_NAVLINK;
        $sqQSargs['mdl'] = $this->moduleID;
        $msg = gettext("View this record in a new window");
        $vgModuleID = '-'.$this->moduleID.'-';
        break;
    case 'edit':
    case 'edit_noadd':
        $linkTemplate = EDIT_GRID_NAVLINK;
        $sqQSargs['mdl'] = $this->moduleID;
        $msg = gettext("Full Edit");
        $qeMsg = gettext("Quick Edit");
        break;
    case 'edit_nfe':
        $linkTemplate = EDIT_GRID_NAVLINK_NOFULLEDIT;
        $sqQSargs['mdl'] = $this->moduleID;
        $qeMsg = gettext("Quick Edit");
        break;
    case 'list':
    default:
        $linkTemplate = LIST_GRID_NAVLINK;
        $msg = gettext("View this record");
    }
    $sqQS = MakeQS($sqQSargs);

    if(-1 == $this->listData->rowsPerPage){
        $paging = false;
        $rows = $this->listData->getData($orderBys);
    } else {
        $paging = true;
        $rows = $this->listData->getPageData($startRow, $orderBys);
    }


    //alternating CSS classes
    $tdClasses = array("l", "l2");
    $content = '';

    foreach($rows as $row_ix => $row){
        $rowID = reset($row);
        $tdClass = $tdClasses[$row_ix % 2];
        $rowContent = '';

        if($this->showRowLinks){
            if($paging){
                //$sequenceID = '&sq='.($startRow + $row_ix);
                $sequenceID = '&amp;sr='.($startRow + $row_ix);
            } else {
                $sequenceID = '';
            }

            if('edit_nfe' == $this->gridType){
                $rowLink = '';
            } else {
                $rowLink = $this->rowLink . $sqQS .'&amp;rid='. $rowID . $sequenceID;
            }

            $linkCode = sprintf(
                $linkTemplate,
                $rowLink,
                $tdClass,
                $theme_web,
                $msg,
                $rowID,
                $qeMsg,  //quickEdit msg
                $vgModuleID
            );
        } else {
            $linkCode = '';
        }
        foreach($row as $fieldName => $fieldValue){
            if(isset($this->headerPhrases[$fieldName])){
                $alignment = '';
                if(isset($this->fieldAlignments[$fieldName])){
                    $alignment = $this->fieldAlignments[$fieldName];
                }
                if('hide' != $alignment){
                    if($this->useBestPractices && 'IsBestPractice' == $fieldName){
                        if(1 == $fieldValue){
                            $fieldContent = '<img src="'.$theme_web.'/img/best_practice.png" alt="'.gettext("A best practice!").'" alt="'.gettext("best practice").'"/>';
                        } else {
                            $fieldContent = '';
                        }
                    } else {
                        $fieldType = '';
                        if(isset($this->listData->fieldTypes[$fieldName])){
                            $fieldType = $this->listData->fieldTypes[$fieldName];
                        }
                        $fieldFormat = '';
                        if(isset($this->listData->fieldFormats[$fieldName])){
                            $fieldFormat = $this->listData->fieldFormats[$fieldName];
                        }
                        $fieldContent = fldFormat($fieldType, $fieldValue, $fieldFormat);
                        if(!empty($this->linkFields[$fieldName])){
                            $linkValue = $row[$this->linkFields[$fieldName]];
                            if(!empty($linkValue)){
                                list($link, $internal, $newWin) = linkFormat($linkValue);
                                $fieldContent = "<a href=\"$link\">$fieldContent</a>";
                            }
                        }
                    }
                    $rowContent .= sprintf(
                        GRID_VIEW_CELL,
                        $alignment,
                        $tdClass,
                        $fieldContent
                    );
                }
            }
        }
        $content .= sprintf(
            VIEWGRID_ROW,
            $tdClass,
            $rowID,
            $linkCode,
            $rowContent,
            $vgModuleID
        );

    }

    return $content;
} //end renderRows


function renderPageSelector($nRows, $currentRow = 0, $orderBys = null)
{
    global $theme_web;
    global $qsArgs;
    $srQSargs = $qsArgs;
    unset($srQSargs['sr']);
    $srQS = MakeQS($srQSargs);

    $nPages = ceil($nRows / $this->listData->rowsPerPage);
    $currentPage = ceil(($currentRow +1) / $this->listData->rowsPerPage);
    if($nPages > 15){
        $selectedPages = $this->_getSelectedPageNumbers($currentPage, $nPages);
    } else {
        $selectedPages = $this->_getAllPageNumbers($currentPage, $nPages);
    }

    $stats = sprintf(
        gettext("Pages: %s Rows: %s"),
        $nPages,
        $nRows
        );

    if($currentPage == 1){
        $prevContent = sprintf(
            GRID_NAV_ONFIRSTPAGE,
            $theme_web
            );
    } else {
        //firstLink, firstPhrase, prevLink, prevPhrase
        $prevContent = sprintf(
            GRID_NAV_PREVLINKS,
            'javascript:updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.''.$srQS.'&sr=0&rpc=1\');',
            'javascript:updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.''.$srQS.'&sr='.($currentRow - $this->listData->rowsPerPage).'&amp;rpc=1\');',
            $theme_web
            );
    }
    if($currentPage == $nPages){
        $postContent = sprintf(
            GRID_NAV_ONLASTPAGE,
            $theme_web
            );
    } else {
        //parameters: nextLink, nextPhrase, lastLink, lastPhrase
        $postContent = sprintf(
            GRID_NAV_NEXTLINKS,
            'javascript:updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.$srQS.'&amp;sr='.($currentRow + $this->listData->rowsPerPage).'&amp;rpc=1\');',
            'javascript:updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.$srQS.'&amp;sr='.$nRows.'&amp;rpc=1\');',
            $theme_web
            );
    }
    $innerContent = '<select class="edt" name="pager" onchange="updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.'\'+this.value+\'&amp;rpc=1\');">';

    if(!empty($orderBys)){
        $pageLabels = $this->getPagerLabels($orderBys);
    }

    foreach($selectedPages as $pageNum => $info){
        if('current' == $info){
            $selected = ' selected="selected" style="font-weight:bold"';
            $label = gettext("page")." $pageNum";
        } else {
            $selected = '';
            $label = gettext("page")." $pageNum";
        }
        $startRow = $this->listData->rowsPerPage * ($pageNum-1);
        $innerContent .= "<option value=\"$srQS&amp;sr=$startRow\" $selected>$label</option>";
    }
    $innerContent .= '</select>';
    $content = $stats . ' &nbsp;&nbsp;&nbsp; ' . $prevContent . $innerContent . $postContent;

    return $content;
} //end renderPageSelector


function getPagerLabels($orderBys)
{
    //$SQL = 
    foreach($orderBys as $orderByField => $desc){
    
    }
    return null;

} //end getPagerLabels


function renderRowsPerPageSelector()
{
    $arPerPage = array(
        5 => 5,
        10 => 10,
        15 => 15,
        20 => 20,
        50 => 50,
        100 => 100,
        'all' => -1,
        );
    global $qsArgs;
    $ppQSargs = $qsArgs;
    unset($ppQSargs['pp']);
    $ppQS = MakeQS($ppQSargs);

    $pageRows = gettext("Rows per page: ");
    $pageRows .= ' <select class="edt" name="rpp" onchange="updateList(\'list_'.$this->moduleID.'\',\''.$this->pagingLink.'\'+this.value+\'&amp;rpc=1\');">';

    foreach($arPerPage as $setting_label => $setting){
        if($this->listData->rowsPerPage == $setting){
            $selected = ' selected="selected" style="font-weight:bold"';
        } else {
            $selected = '';
        }
        $pageRows .= "<option value=\"$ppQS&amp;pp=$setting\" $selected>$setting_label</option>";
    }
    $pageRows .= '</select>';

    return $pageRows;
} //end renderRowsPerPageSelector


/**
 *  Returns the first row number of the page where $startRow is present 
 */
function getPageStartRow($startRow, $nRows)
{
    if($startRow > $nRows){
        $startRow = $nRows;
    }
    $page = floor(($startRow) / $this->listData->rowsPerPage);
    $pageStartRow = ($page) * $this->listData->rowsPerPage;

    return $pageStartRow;
} //end getPageStartRow


/**
 *  Returns an array with page numbers.
 */
function _getAllPageNumbers($current_page, $last_page)
{
    $page_ix = 1;
    $pages = array();
    while($page_ix <= $last_page){
        if($page_ix == $current_page){
            $pages[$page_ix] = 'current';
        } else {
            $pages[$page_ix] = $page_ix;
        } 
        $page_ix++;
    }
    return $pages;
} //end _getAllPageNumbers


/**
 * Returns an array with cleverly chosen page numbers.
 *
 * Useful when there are too many pages to display a link to each page.
 */
function _getSelectedPageNumbers($current_page, $last_page)
{
    //-----------//
    // constants //
    //-----------//

    //defines the offset pattern to repeat for each level of $base
    //array(1, 2, 5) will make offsets into 1, 2, 5, 10, 20, 50, etc.
    //array(1, 5) will make offsets into 1, 5, 10, 50, 100, 500, etc.
    $multipliers = array(1, 2, 5);

    $base = 10; //10 looks best

    //----------------//
    // initialization //
    //----------------//
    $exponent = 0;
    $pages = array($current_page => 0);
    $offsets = array();
    $offset = 1;
    $multiplier = reset($multipliers);
    $max_offset = $last_page - $current_page;
    if($current_page > $max_offset){
        $max_offset = $current_page;
    }

    //calculate offsets that increase (semi-)logarithmically
    while($offset <= $max_offset){
        $offset = $multiplier * pow($base, $exponent);
        $offsets[] = $offset;
        if(!($multiplier = next($multipliers))){
            $multiplier = reset($multipliers);
            $exponent++;
        }
    }

    foreach($offsets as $offset){
        if(0 == $offset){
            $pages[$pagenum] = 'current';
        } else {
            $exact_pagenum = $current_page - $offset;
            $adj_pagenum = round($exact_pagenum / $offset) * $offset;
            if($adj_pagenum > 0){
                $pages[$adj_pagenum] = $offset;
            }

            $exact_pagenum = $current_page + $offset;
            $adj_pagenum = round($exact_pagenum / $offset) * $offset;
            if($adj_pagenum <= $last_page){
                $pages[$adj_pagenum] = $offset;
            }
        }
    }

    if(!isset($pages[1])){
        $pages[1] = 'first';
    }
    if(!isset($pages[$last_page])){
        $pages[$last_page] = 'last';
    }
    $pages[$current_page] = 'current';

    ksort($pages);
    return $pages;

} //end _getSelectedPageNumbers

} //end class ListRenderer

?>