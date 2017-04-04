<?php
/**
 *  Defines the Search class
 *
 *  This file contains the definition of the Search class, which generates the
 *  necessary SQL statements on-the-fly, in response to searches made by the
 *  user. It also supports charts and saved Default searches, which means that 
 *  it loads the search conditions from the database. Run-time only.
 *
 *  PHP version 5
 *
 *
 *  LICENSE NOTE:
 *
 *  Copyright  2003-2009 Active Agenda Inc., All Rights Reserved.
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
 * @version        SVN: $Revision: 1621 $
 * @last-modified  SVN: $Date: 2009-05-11 04:21:51 +0200 (Pn, 11 maj 2009) $
 */

class Search
{
var $froms = array();  //from clauses resulting from search criteria
var $wheres = array(); //where clauses resulting from search criteria
var $moduleID;
var $phrases = array(); //verbal search expressions to be displayed to user
var $listFields = array();
var $listSelects = array();
var $listFroms = array();
var $postData = array();
var $isUserDefault = false;

function Search(
    $moduleID,
    $listFields = array(),
    $formFields = null,
    $postdata = null
)
{
    $this->moduleID = $moduleID;
    $this->listFields = $listFields;
    $this->prepareFroms($formFields, $postdata);
}



function prepareFroms(&$formFields, &$postdata)
{
    $moduleFields =& GetModuleFields($this->moduleID);
    $this->postData = array();
    if(count($postdata) > 0){
        foreach($postdata as $postKey => $postValue){
            if(!empty($postValue)){
                switch($postKey){
                case 'Search':
                case 'Chart':
                    break;
                default:
                    $this->postData[$postKey] = $postValue;
                    break;
                }
            }
        }
    }

    //clear the SELECTs, FROMs and WHEREs
    $this->listSelects = array();
    $this->listFroms = array();
    $this->froms = array();
    $this->wheres = array();

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;

    global $ModuleID;
    $origModuleID = $ModuleID;
    $ModuleID = $this->moduleID; //yes, an ugly hack...

    if(count($formFields)>0){
        foreach($formFields as $fieldname => $field){

            //allow field objects to determine how to handle whether andf
            //how a field is being searched on
            $searchDef = $field->handleSearch($postdata, $moduleFields);

            if(!empty($searchDef)){

                //$fromLines is an array with either 0 or 1 values
                //added to $froms as needed
                $fromLines = $searchDef['f'];
                foreach($fromLines as $alias => $fromLine){
                    $this->froms[$alias] = $fromLine;
                }

                $this->wheres[] = $searchDef['w'];

                //adds human-readable search expressions to be displayed to user...
                foreach($searchDef['p'] as $searchPhrase){
                    $this->phrases[] = $searchPhrase;
                }
            }
        }
        $this->froms = SortJoins($this->froms);
    }
    //list field data
    foreach($this->listFields as $listFieldAlias => $listField){ //$listField key is numbered
        if(is_int($listFieldAlias)){
            $listFieldAlias = $listField;
        }
        $this->listSelects[] = GetQualifiedName($listField, $this->moduleID) . " AS $listFieldAlias";

        $this->listFroms = array_merge($this->listFroms, GetJoinDef($listField, $this->moduleID));

    }
    $this->listFroms = SortJoins($this->listFroms);

    $ModuleID = $origModuleID;
}



function getListSQL($orderByField = null, $split_statement = false)
{

    $selectSQL = "SELECT \n";
    $selectSQL .= join(",\n", $this->listSelects);
    $selectSQL .= "\n";

    $selectSQL = TranslateLocalDateSQLFormats($selectSQL);

    $froms = array_merge($this->froms, $this->listFroms);
    $froms = SortJoins($froms);

    $fromSQL = "FROM \n `{$this->moduleID}`\n";
    foreach($froms as $alias => $def){
        $fromSQL .= "$def\n";
    }

    $fromSQL .= "WHERE\n";
    $fromSQL .= "{$this->moduleID}._Deleted = 0";

    foreach($this->wheres as $fields){
        foreach($fields as $fieldname => $def){
            $fromSQL .= "\nAND $def";
        }
    }
    $fromSQL .= "\n";


    if(!empty($orderByField)){
        $fromSQL .= "ORDER BY $orderByField\n"; //need to qualify name?
    }
    if($split_statement){
        $recordIDSelect = $this->listSelects[0];
        return array($selectSQL, $fromSQL, $recordIDSelect);
    } else {
        return $selectSQL . $fromSQL;
    }
} //end getListSQL


function getCustomListSQL($listFields)
{
//print "getCustomListSQL";
    $moduleFields = GetModuleFields($this->moduleID);
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;
    $listSelects = array();
    $listFroms = array();

    $boolFieldTranslation = 'CASE %s WHEN 1 THEN \'Yes\' WHEN 0 THEN \'No\' ELSE NULL END AS %s';

    foreach($listFields as $listField){ //key is numbered
        $moduleField =& $moduleFields[$listField];
        if('bool' == $moduleField->dataType){
            $listSelects[] = sprintf(
                $boolFieldTranslation, 
                GetQualifiedName($listField, $this->moduleID),
                $listField
                ) . " AS $listField";
        } else {
            $listSelects[] = GetQualifiedName($listField, $this->moduleID) . " AS $listField";
        }
        $listFroms = array_merge($listFroms, GetJoinDef($listField, $this->moduleID));
    }

    $SQL = "SELECT \n";
    $SQL .= join(",\n", $listSelects);
    $SQL .= "\n";

    $SQL = TranslateLocalDateSQLFormats($SQL);

    $froms = array_merge($this->froms, $listFroms);
    $froms = SortJoins($froms);

    $SQL .= "FROM \n `{$this->moduleID}`\n";
    foreach($froms as $alias => $def){
        $SQL .= "$def\n";
    }

    $SQL .= "WHERE\n";
    $SQL .= "{$this->moduleID}._Deleted = 0";

    foreach($this->wheres as $fields){
        foreach($fields as $fieldname => $def){
            $SQL .= "\nAND $def";
        }
    }

    $SQL .= "\n";

    return $SQL;
} //end getCustomListSQL



function getCountSQL()
{
    $SQL = "SELECT COUNT(*) \n";

    $froms = array_merge($this->froms, $this->listFroms);
    $SQL .= "FROM \n `{$this->moduleID}`\n";
    $froms = SortJoins($froms);

    foreach($froms as $alias => $def){
        $SQL .= "$def\n";
    }

    $SQL .= "WHERE {$this->moduleID}._Deleted = 0";

    foreach($this->wheres as $fields){
        foreach($fields as $fieldname => $def){
            $SQL .= "\nAND $def";
        }
    }
    $SQL .= "\n";

    return $SQL;
} //end getCountSQL



function getSummarySQL($summaryFields, $groupByFields, $orderBy = 'value')
{
//print "getSummarySQL<br />";

    $froms = array();
    $selectStrings = array();
    $moduleFields = GetModuleFields($this->moduleID); //this could be optimized if select defs etc were cached
//print debug_r($moduleFields);
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;

    $boolFieldTranslation = 'CASE %s WHEN 1 THEN \'Yes\' WHEN 0 THEN \'No\' ELSE NULL END';

    $dateFieldTranslation['year'] = 'DATE_FORMAT([*field*], \'%Y\')';
    $dateFieldTranslation['monthnum'] = 'DATE_FORMAT([*field*], \'%m\')';
    $dateFieldTranslation['week'] = 'DATE_FORMAT([*field*], \'%v\')'; //week starts Monday
    $dateFieldTranslation['yearweek'] = 'DATE_FORMAT([*field*], \'%x-W%v\')'; //week starts Monday
    $dateFieldTranslation['yearmonth'] = 'DATE_FORMAT([*field*], \'%Y-%m\')';
    $dateFieldTranslation['yearmonthday'] = 'DATE_FORMAT([*field*], \'%Y-%m-%d\')';
    $dateFieldTranslation['yearquarter'] = 'CONCAT(YEAR([*field*]), \' q\', QUARTER([*field*]))';

    $groupBySelects = array();
    foreach($groupByFields as $fieldName => $date_interval){
        $groupByField = $moduleFields[$fieldName];
        if(empty($groupByField)){
            trigger_error("Cannot find module field `{$this->moduleID}`.`$fieldName`.",  E_USER_ERROR);
        }

        switch($groupByField->dataType){
        case 'bool':
            $groupBySelect = sprintf(
                $boolFieldTranslation, 
                GetQualifiedName($fieldName, $this->moduleID)
            );
            break;
        case 'date':
        case 'datetime':
            if(empty($date_interval)){
                $date_interval = 'yearmonthday';
            }
            $groupBySelect = str_replace(
                '[*field*]',
                GetQualifiedName($fieldName, $this->moduleID),
                $dateFieldTranslation[$date_interval]
            );
            break;
        default:
            $groupBySelect = GetQualifiedName($fieldName, $this->moduleID);
            break;
        }

        $froms = array_merge($froms, GetJoinDef($fieldName, $this->moduleID));
        $selectStrings[] = $groupBySelect . ' AS ' . $fieldName;
        $groupBySelects[$fieldName] = $groupBySelect;
    }

    foreach($summaryFields as $fieldName => $summaryType){
        $summarizeField = $moduleFields[$fieldName];
        $selectStrings[] = $summaryType.'('.GetQualifiedName($fieldName, $this->moduleID).') AS '.$fieldName;
        $froms = array_merge($froms, GetJoinDef($fieldName, $this->moduleID));
    }

    $SQL = "SELECT \n";
    $SQL .= implode(', ', $selectStrings) . "\n";
    $SQL .= "FROM \n `{$this->moduleID}`\n";
    $froms = array_merge($froms, $this->froms);

    $froms = SortJoins($froms);

    foreach($froms as $alias => $def){
        $SQL .= "$def\n";
    }
    $SQL .= "WHERE {$this->moduleID}._Deleted = 0";

    foreach($this->wheres as $fields){
        foreach($fields as $fieldname => $def){
            $SQL .= "\nAND $def";
        }
    }
    $SQL .= "\n";
    $SQL .= "GROUP BY ";
    $SQL .= implode(', ', array_values($groupBySelects));

    switch($orderBy){
    case 'value':
        $SQL .= "\nORDER BY ";
        reset($summaryFields);
        $SQL .= key($summaryFields).' DESC ';
        break;
    case 'label':
        $SQL .= "\nORDER BY ";
        reset($groupByFields);
        $SQL .= key($groupByFields);
        break;
    default:
        //original order
    }
//trace($SQL);
    return $SQL;
} //end getSummarySQL



function getPhrases()
{
    if(count($this->phrases) > 0){
        $content = join($this->phrases, "<br />\n");
    } else {
        $content = gettext("None");
    }

    return $content;
}



function hasConditions()
{
    return count($this->phrases) > 0;
}



/**
 * Saves the search conditions to a table.
 *
 * Conditions can be saved to one of the following tables:
 * usrsd - User Search Defaults
 * dsbcc - Dashboard Chart Conditions
 *
 * @param int       $userID       The UserID of the user for whom to save conditions
 * @param string    $table        Name of the table (module) where to save the conditions. 
 *                                 Currently supported values are 'usrsd' and 'dsbcc'
 * @param string    $chartID      ID of chart to save conditions for (if $table == 'dsbcc')
 */
function _saveConditions($userID, $table, $chartID = null)
{
    global $dbh;
    //$moduleID from $this->moduleID

    $userID = intval($userID);

    $chartWhereSnip = '';
    $chartInsertSnip = '';
    $chartInsertValSnip = '';

    switch($table){
    case 'usrsd':
        break;
    case 'dsbcc':
        if(empty($userID)){
            $dbh->query('ROLLBACK');
            trigger_error("Search->_saveConditions: No userID specified.", E_USER_ERROR);
        }
        if(empty($chartID)){
            $dbh->query('ROLLBACK');
            trigger_error("Search->_saveConditions: No chartID specified.", E_USER_ERROR);
        }
        $chartWhereSnip = " AND DashboardChartID = '$chartID'";
        $chartInsertSnip = ",\n DashboardChartID";
        $chartInsertValSnip = ",\n '$chartID'";
        break;
    default:
        trigger_error("Search->_saveConditions: Table $table not supported.", E_USER_ERROR);
        break;
    }


    //start transaction

    //SQL to check for existing conditions:
    $SQL = "SELECT ConditionID, ConditionField, ConditionValue FROM `$table` WHERE UserID = $userID AND ModuleID = '{$this->moduleID}'$chartWhereSnip";

    $existingSet = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    $removes = array();
    $updates = array();

    if(count($existingSet) > 0){
        //figure out what conditions are currently saved, and decide which to update or remove
        foreach($existingSet as $existingRow){
            if(array_key_exists($existingRow['ConditionField'], $this->postData)){
                $updates[$existingRow['ConditionField']] = $existingRow['ConditionID'];
            } else {
                $removes[] = $existingRow['ConditionID'];
            }
        }
    }

    foreach($this->postData as $postField => $postValue){
        if(is_array($postValue)){
            $postValue = join(',',$postValue);
        }
        $postField = mysql_escape_string($postField);
        $postValue = dbQuote($postValue);
        if(array_key_exists($postField, $updates)){
            $SQL = "UPDATE `$table`\n";
            $SQL .= "SET\n";
            $SQL .= "    ConditionField = '$postField',\n";
            $SQL .= "    ConditionValue = $postValue,\n";
            $SQL .= "    _Deleted = 0\n";
            $SQL .= "WHERE\n";
            $SQL .= "    ConditionID = {$updates[$postField]}\n";
        } else {
            $SQL = "INSERT INTO `$table` (\n";
            $SQL .= "    ModuleID,\n";
            $SQL .= "    UserID,\n";
            $SQL .= "    ConditionField,\n";
            $SQL .= "    ConditionValue{$chartInsertSnip}\n";
            $SQL .= ") VALUES (\n";
            $SQL .= "    '{$this->moduleID}',\n";
            $SQL .= "    $userID,\n";
            $SQL .= "    '$postField',\n";
            $SQL .= "    $postValue {$chartInsertValSnip}\n";
            $SQL .= ")";
        }
//print debug_r($SQL);
        $r = $dbh->query($SQL);
        dbErrorCheck($r);
    }

    if(count($removes) > 0){
        $strRemoves = join(',', $removes);
        $SQL = "UPDATE `$table`\n";
        $SQL .= "SET\n";
        $SQL .= "    _Deleted = 1\n";
        $SQL .= "WHERE\n";
        $SQL .= "    ConditionID IN ({$strRemoves})\n";
        $r = $dbh->query($SQL);
        dbErrorCheck($r);
    }

    //end transaction
} //end _saveConditions



/**
 * Saves the search conditions to the User Search Defaults table.
 *
 * @param int       $userID       The UserID of the user for whom to save conditions
 */
function saveUserDefault($userID)
{
    $this->_saveConditions($userID, 'usrsd');
    $this->isUserDefault = true;
    $_SESSION['Search_'.$this->moduleID] = $this;
}



/**
 * Saves the chart conditions to the Dashboard Chart Conditions table.
 *
 * @param int       $userID       The UserID of the user for whom to save conditions
 * @param string    $chartID      ID of chart to save conditions for
 */
function saveChartConditions($userID, $chartID)
{
    $this->_saveConditions($userID, 'dsbcc', $chartID);
}



/**
 * Loads saved conditions from a table
 *
 * Conditions can be loaded from one of the following tables:
 * usrsd - User Search Defaults
 * dsbcc - Dashboard Chart Conditions
 *
 * @param int       $userID       The UserID of the user for whom to load conditions
 * @param string    $table        Name of the table (module) from where to load the conditions. 
 *                                 Currently supported values are 'usrsd' and 'dsbcc'
 * @param string    $chartID      ID of chart to load conditions for (if $table == 'dsbcc')
 */
function _loadConditions($userID, $table, $chartID = null){

    global $dbh;

    $chartWhereSnip = '';
    if(!empty($chartID)){
        $chartWhereSnip = " AND DashboardChartID = $chartID";
    }

    $SQL = "SELECT ConditionID, ConditionField, ConditionValue FROM `$table` WHERE _Deleted = 0 AND UserID = $userID AND ModuleID = '{$this->moduleID}'$chartWhereSnip";

    $conditionsSet = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    $conditions = array();

    if(count($conditionsSet) > 0){
        //figure out what conditions are currently saved, and decide which to update or remove
        foreach($conditionsSet as $conditionsRow){
            $conditions[$conditionsRow['ConditionField']] = $conditionsRow['ConditionValue'];
        }
    }

    //get searchFields:
    $searchFields_file = GENERATED_PATH . "/{$this->moduleID}/{$this->moduleID}_SearchFields.gen";
    if(file_exists($searchFields_file)){
        include_once(CLASSES_PATH . '/components.php');
        include($searchFields_file); //returns $searchFields
    } else {
        $searchFields = array();
    }
    $this->prepareFroms($searchFields, $conditions);
} //end _loadConditions


/**
 * Loads the search conditions from the User Search Defaults table.
 *
 * @param int       $userID       The UserID of the user for whom to load conditions
 */
function loadUserDefault($userID)
{
    $this->_loadConditions($userID, 'usrsd');
    $this->isUserDefault = true;
    $_SESSION['Search_'.$this->moduleID] = $this;
} //end loadUserDefault


/**
 * Loads the search conditions from the Dashboard Chart Conditions table.
 *
 * @param int       $userID       The UserID of the user for whom to load conditions
 * @param string    $chartID      ID of chart to load conditions for
 */
function loadChartConditions($userID, $chartID)
{
    $this->_loadConditions($userID, 'dsbcc', $chartID);
} //end loadUserDefault


/**
 * Loads search conditions from the $_GET superglobal.
 */
function loadURLFilter()
{
    $searchFields_file = GENERATED_PATH . "/{$this->moduleID}/{$this->moduleID}_SearchFields.gen";
    if(file_exists($searchFields_file)){
        include_once(CLASSES_PATH . '/components.php');
        include($searchFields_file); //returns $searchFields
    } else {
        trigger_error("Module '{$this->moduleID}' doesn't have a SearchFields file.", E_USER_ERROR);
    }

    $filterConditions = array();
    foreach($_GET as $name => $value){
        if(isset($searchFields[$name])){
            $filterConditions[$name] = $value; //need sanitizing?
        }
    }

    $this->prepareFroms($searchFields, $filterConditions);
} //end loadURLFilter

} //end class Search



?>
