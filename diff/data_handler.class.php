<?php
/**
 * Data handling class (saves data)
 *
 * This file contains the DataHandler class definition. The purpose of this
 * class is to handle all forms of data saving to the database.
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
 * @version        SVN: $Revision: 1640 $
 * @last-modified  SVN: $Date: 2009-05-20 19:40:50 +0200 (Śr, 20 maj 2009) $
 */

include_once INCLUDE_PATH . '/web_util.php'; //required until dbQuote is moved to general_util.php

class DataHandler
{
var $moduleID;
var $PKFields                = array(); //Primary Key fields. (WAS $recordIDFields)
var $tableFields             = array(); //all the table fields in module
var $remoteFields            = array(); //remote fields
var $remoteFieldAliasKeys    = array(); //the remote fields, grouped by their join key.
var $resolvableFields        = array(); //foreign fields that can be used to look up matching ID fields
var $uniquenessIndexes       = array(); //uniqueness info
var $autoIncrement           = true;    //the PK field is an auto-increment field (can only have single PK field when true)
var $relatedRecordFields     = array();
var $useRemoteIDCheck        = false;   //set this to true if saving remote fields, otherwise checks existence by record ID values
var $originatingModuleID;               //if module is a remote one, check permission for the originating module
var $ownerOrgField;

var $dbValues                 = array();
var $PKFieldValues            = array();
var $relatedRecordFieldValues = array();
var $errmsg                   = null;

/**private**/
var $_selects        = array();
var $_joins          = array();
var $isPopulated     = false;


/**
 *  constructor is called at generating time only. To get a DataHandler object at run-time, use the GetDataHandler() global function
 */
function DataHandler($moduleID){
    if(!defined('EXEC_STATE') || EXEC_STATE != 4){
        trigger_error("Cannot instantiate a DataHandler object at run-time.", E_USER_ERROR);
    }

    $this->moduleID = $moduleID;

    $moduleFields =& GetModuleFields($moduleID);
    $moduleInfo = GetModuleInfo($moduleID);

    $this->PKFields = $moduleInfo->getProperty('primaryKeys');
    $this->autoIncrement  = $moduleInfo->getProperty('autoIncrement');
    $this->ownerOrgField = $moduleInfo->getProperty('ownerField');
    $this->uniquenessIndexes = $moduleInfo->getProperty('uniquenessIndexes');

    foreach($this->PKFields as $PKFieldName){
        $PKModuleField = $moduleFields[$PKFieldName];
    }

    $fields = array_keys($moduleFields);

    if(!empty($this->ownerOrgField)){
        if(!in_array($this->ownerOrgField, $fields)){
            $fields[] = $this->ownerOrgField;
        }
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;
    $this->tableFields = array();
    foreach($fields as $fieldName){
        $moduleField = $moduleFields[$fieldName];
        switch(strtolower(get_class($moduleField))){
        case 'tablefield':
            $this->tableFields[$fieldName] = $moduleField->dataType;
            $this->_selects[$fieldName] = $moduleField->makeSelectDef($this->moduleID);
            break;
        case 'remotefield':
            $this->remoteFields[$fieldName] = $moduleField;
            $aliasKey = $moduleField->getTableAliasKey($this->moduleID);
            $this->remoteFieldAliasKeys[$aliasKey][] = $fieldName;
            $this->_selects[$fieldName] = GetSelectDef($fieldName);
            $this->_joins[$fieldName] = GetJoinDef($fieldName);
            break;
        case 'foreignfield':
        case 'codefield':
            //determine whether field is resolvable, by itself or together with another
            if('_' != substr($fieldName, 0, 1)){ //skip meta fields
                $resolvingInfo = $moduleField->getResolvingInfo();
                if($resolvingInfo){
                    $this->resolvableFields[$fieldName] = $resolvingInfo;
//trace($this->resolvableFields, 'resolvableFields');
                }
            }

            //no break: proceed to default clause
        default:
            //other than saveable fields above, we might need the ownerOrgField:
            if(!empty($this->ownerOrgField) && $fieldName == $this->ownerOrgField){
                $this->_selects[$fieldName] = GetSelectDef($fieldName, $this->moduleID);
                $this->_joins[$fieldName] = GetJoinDef($fieldName, $this->moduleID);
            }
            break;
        }
    }
    if(count($this->resolvableFields > 0)){
        //save to another generated file, for meta info.
        SaveGeneratedFile(
            'CustomModel.php',
            $this->moduleID.'_Resolvable.gen',
            array('/**custom**/' => '$resolvableFields = unserialize(\''.escapeSerialize($this->resolvableFields) .'\')'),
            $this->moduleID
        );
    }
}



/*******************
 Private functions
*******************/

function _buildLogSQL(){

    $fieldNames = implode(",", array_keys($this->tableFields));

    $logSQL = "INSERT INTO {$this->moduleID}_l ($fieldNames) SELECT $fieldNames FROM `{$this->moduleID}` WHERE ";

    $atFirst = true;
    foreach($this->PKFields as $PKField){
        if($atFirst){
            $atFirst = false;
        } else {
            $logSQL .= ' AND ';
        }
        $logSQL .= $PKField . " = '[*pk*$PKField*]'"; //get value from the $this->PKFieldValues property
    }
    return $logSQL;
}


function _getPKSQLSnip()
{
    $SQL = '';
    if(!empty($this->_PKSQLSnip)){
        return $this->_PKSQLSnip;
    }

    $atFirst = true;

    foreach($this->PKFields as $PKField){
        if($atFirst){
            $atFirst = false;
        } else {
            $SQL .= ' AND ';
        }
        $SQL .=  "`{$this->moduleID}`.$PKField = '[*pk*$PKField*]'";
    }

    $this->_PKSQLSnip = $SQL;
//print debug_r($SQL);
    return $SQL;
}


function _buildRelatedSQLSnip()
{
    $SQL = '';
//print "_buildRelatedSQLSnip\n";
//print debug_r($this->relatedRecordFields);

trace($this->relatedRecordFieldValues, "DataHandler->_buildRelatedSQLSnip: relatedRecordFieldValues");

    $atFirst = true;
    foreach($this->relatedRecordFieldValues as $relatedRecordField => $relatedRecordFieldValue){
        if($atFirst){
            $atFirst = false;
        } else {
            $SQL .= ' AND ';
        }
        //$SQL .= $relatedRecordField . " = '[*rf*$relatedRecordField*]'";
        $SQL .= "`{$this->moduleID}`.{$relatedRecordField} = '$relatedRecordFieldValue'";
    }

trace($SQL, "DataHandler->_buildRelatedSQLSnip: SQL");
//print debug_r($SQL);
    return $SQL;

}


function _buildGetSQL($fields)
{
//print_r($fields);
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;

    if(!empty($this->ownerOrgField)){
        if(!in_array($this->ownerOrgField, $fields)){
            $fields[] = $this->ownerOrgField;
        }
    }

    $SQL = 'SELECT ';
    foreach($fields as $field){
        if(isset($this->_selects[$field])){
            $SQL .= $this->_selects[$field] . ',';
        } else {
            //trigger_error("The field $field is not defined.", E_USER_ERROR);
        }

    }
    $SQL = substr($SQL, 0, -1); //trims the comma

    $SQL .= ' FROM `' . $this->moduleID . '` ';
    $joins = array();

    if(isset($this->_joins[$this->ownerOrgField])){
        $joins = array_merge($joins, $this->_joins[$this->ownerOrgField]);
    }

    if(count($this->remoteFields) > 0){
        $affectedRemoteFields = array_intersect(array_keys($this->remoteFields), $fields);
        if(count($affectedRemoteFields) > 0){
            foreach($affectedRemoteFields as $affectedRemoteField){
                if(!isset($this->_joins[$affectedRemoteField])){
                    continue;
                }
                $joins = array_merge($joins, $this->_joins[$affectedRemoteField]);
            }
        }
    }

    if(count($joins) > 1){
        $joins = SortJoins($joins);
    }
    if(count($joins) > 0){
        $SQL .= implode("\n   ", $joins);
    }

    //use remote field checking when the PK field values haven't been looked up yet.
    if($this->useRemoteIDCheck && 0 == count($this->PKFieldValues)){
        $SQL .= ' WHERE ';
        $SQL .= $this->_buildRelatedSQLSnip();
    } else {
        $SQL .= ' WHERE ';
        $SQL .= $this->_getPKSQLSnip();
    }
    $SQL = TranslateLocalDateSQLFormats($SQL);

    return $SQL;
} //end function DataHandler::buildGetSQL()



/**
 * retrieves the record from the database, and populates the internal dbValues and PKFieldValues arrays. 
 * Returns true if the record exists.
 **/
function _populate($fields)
{
    if($this->isPopulated){
        return $this->exists;
    }
    $fields = array_merge($this->PKFields, $fields);
    $SQL = $this->_buildGetSQL($fields);
    $debug_prefix = "DataHandler({$this->moduleID})->_populate";
//    trace("$debug_prefix useRemoteIDCheck = '{$this->useRemoteIDCheck}'");

//    trace($this->PKFieldValues, $debug_prefix . ' PKFieldValues');
    if($this->useRemoteIDCheck && 0 == count($this->PKFieldValues)){
        //make sure related fields are populated, if using remote ID check.
        if(count($this->relatedRecordFieldValues) == 0){
            trigger_error("Must populate related record values before getting data for {$this->moduleID} fields", E_USER_ERROR);
        }

        //populate related field values
        foreach($this->relatedRecordFieldValues as $relatedRecordField => $relatedRecordFieldValue){
            $SQL = str_replace("[*rf*$relatedRecordField*]", $relatedRecordFieldValue, $SQL);
        }
    } else {
        //populate PK field values
        foreach($this->PKFieldValues as $PKField => $PKFieldValue){
            $SQL = str_replace("[*pk*$PKField*]", $PKFieldValue, $SQL);
        }
    }
    trace($SQL, $debug_prefix.' getSQL');

    $mdb2 =& GetMDB2();

    $data = $mdb2->queryRow($SQL);
    mdb2ErrorCheck($data);


    if(!empty($data)){
        $exists = true;
        $this->dbValues = $data;

        if($this->useRemoteIDCheck){
            foreach($this->PKFields as $pkField){
                $this->PKFieldValues[$pkField] = $data[$pkField];
            }
        }
    } else {
        $exists = false;
        trace($debug_prefix.' no matching record found');
    }

    $this->isPopulated = true;
    $this->exists = $exists;

    return $exists;
} //end function DataHandler->_populate()


function _update($values)
{
    $debug_prefix = "DataHandler({$this->moduleID})->_update";
    global $g_transaction_id;

    $tableFieldNames = array_keys($this->tableFields);

    $SQL = "UPDATE `{$this->moduleID}` SET ";
    foreach($values as $field => $value){
        if(in_array($field, $tableFieldNames) && !in_array($field, $this->PKFields)){
            $qValue = dbQuote($value, $this->tableFields[$field]);
            $SQL .= "$field = $qValue,";
        }
    }

    $SQL .= '_ModDate=NOW(),_Deleted=0,_TransactionID =\''.$g_transaction_id.'\'';
    $SQL .= ' WHERE ';
    $SQL .= $this->_getPKSQLSnip();

    $mdb2 =& GetMDB2();

    //populate PK field values
    foreach($this->PKFieldValues as $PKField => $PKFieldValue){
        $SQL = str_replace("[*pk*$PKField*]", $PKFieldValue, $SQL);
        $recordID = $PKFieldValue;
    }

trace($SQL, $debug_prefix.' SQL');

    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r);

    $this->trackDataChange($recordID, 2);

    return true;

} //end function DataHandler->_update()


function _insert($values)
{
    $debug_prefix = "DataHandler({$this->moduleID})->_insert";

    global $g_transaction_id;

    $tableFieldNames = array_keys($this->tableFields);

    $recordIDSpecified = false;

    if($this->autoIncrement){
        //assumes one PK field:
        $PKField = end($this->PKFields);
        if(isset($values[$PKField]) && 0 !== $values[$PKField]){
            $recordIDSpecified = true;
            $recordID = $values[$PKField];
            $this->PKFieldValues[$PKField] = $recordID;
        }
    } else {
        foreach($this->PKFields as $PKField){
            $values[$PKField] = $this->PKFieldValues[$PKField];
            $recordID = $values[$PKField];
        }
    }

    if(count($this->relatedRecordFieldValues) > 0){
        foreach($this->relatedRecordFieldValues as $relatedRecordField => $relatedRecordFieldValue){
            $values[$relatedRecordField] = $relatedRecordFieldValue;
        }
    }
//$fields: combine $values, $pk fields (if not autoIncrement), $related fields

    $SQL = "INSERT INTO `{$this->moduleID}` (\n";
    $valueSQL = ''; //second part of sql statement
    foreach($values as $field => $value){
        if(in_array($field, $tableFieldNames)){
            $qValue = dbQuote($value, $this->tableFields[$field]);
            $SQL .= "$field,";
            $valueSQL .= "$qValue,";
        }
    }

    $SQL .= "_ModDate,_Deleted, _TransactionID) VALUES ($valueSQL NOW(),0, '$g_transaction_id')";

    trace($SQL, $debug_prefix.' SQL');

    //execute query
    $mdb2 =& GetMDB2();
    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r);

    if($this->autoIncrement && !$recordIDSpecified){
        $recordID = $mdb2->lastInsertID();
        mdb2ErrorCheck($recordID);

        $PKField = end($this->PKFields);
        $this->PKFieldValues[$PKField] = $recordID;
    }

    $this->trackDataChange($recordID, 1);
    $this->exists = true;

    return true;
} //end function DataHandler->_insert()


function _saveRemoteFields($values)
{
    $save_success = true;
    if(count($this->remoteFields) > 0){

        $localPKField = end($this->PKFields);

        foreach($this->remoteFieldAliasKeys as $aliasKey =>$remoteFields){
            $remoteValues = array();  //values to be saved to the remote module
            $relatedValues = array(); //"related" fields are those required to look up the record
            $remoteFieldDataTypes = array(); //used for overriding data type in the remote module fields
            foreach($remoteFields as $remoteFieldName){
                $remoteField = $this->remoteFields[$remoteFieldName];
                if(isset($values[$remoteFieldName]) || isset($this->relatedRecordFieldValues[$remoteFieldName])){
                    if(empty($values[$remoteFieldName])) {
                        $remoteValues[$remoteField->remoteField] = $this->relatedRecordFieldValues[$remoteFieldName];
                        $remoteFieldDataTypes[$remoteField->remoteField] = $remoteField->dataType;
                    } else {
                        $remoteValues[$remoteField->remoteField] = $values[$remoteFieldName];
                        $remoteFieldDataTypes[$remoteField->remoteField] = $remoteField->dataType;
                    }
                }
            }

            if(count($remoteValues) > 0){
                if(!empty($remoteField->remoteModuleIDField)){
                    $relatedValues[$remoteField->remoteModuleIDField] = $this->moduleID;
                }

                $relatedValues[$remoteField->remoteRecordIDField] = $this->PKFieldValues[$localPKField];
                if(!empty($remoteField->remoteDescriptor)){
                    $relatedValues[$remoteField->remoteDescriptorField] = $remoteField->remoteDescriptor;
                }

                $remoteDataHandler = GetDataHandler($remoteField->remoteModuleID, false, true);
                $remoteDataHandler->originatingModuleID = $this->moduleID;

                //applies local data type to the remote tablefield or remote field. this is of significance to date validation.
                foreach($remoteFieldDataTypes as $remoteFieldName => $remoteFieldDataType){
                    if(isset($remoteDataHandler->tableFields[$remoteFieldName])){
                        $remoteDataHandler->tableFields[$remoteFieldName] = $remoteFieldDataType;
                    } else {
                        $remoteDataHandler->remoteFields[$remoteFieldName]->dataType = $remoteFieldDataType;
                    }
                }
                if(!$remoteDataHandler->saveRowWithRelatedValues($remoteValues, $relatedValues)){
                    $this->errmsg = array_merge($remoteDataHandler->errmsg, (array)$this->errmsg);
                    $save_success = false;
                }
            }
        }
    }
    return $save_success;
} //end function DataHandler->_saveRemoteFields()


function _deleteRemoteFields()
{
    if(count($this->remoteFields) > 0){
        $localPKField = end($this->PKFields);

        foreach($this->remoteFieldAliasKeys as $aliasKey =>$remoteFields){

            $relatedValues = array();
            foreach($remoteFields as $remoteFieldName){
                $remoteField = $this->remoteFields[$remoteFieldName];
            }
            if(!empty($remoteField->remoteModuleIDField)){
                $relatedValues[$remoteField->remoteModuleIDField] = $this->moduleID;
            }

            $relatedValues[$remoteField->remoteRecordIDField] = $this->PKFieldValues[$localPKField];
            if(!empty($remoteField->remoteDescriptor)){
                $relatedValues[$remoteField->remoteDescriptorField] = $remoteField->remoteDescriptor;
            }

            $remoteDataHandler = GetDataHandler($remoteField->remoteModuleID, false, true);
            $remoteDataHandler->deleteRowWithRelatedValues($relatedValues);
        }
    }
    return true;
} //end function DataHandler->_deleteRemoteFields()



function _saveCaches($values, $delete = false)
{
    $recordID = end($this->PKFieldValues);
    $PKField = end($this->PKFields);

    $this->_saveRDC($recordID, $PKField, $delete);

    $this->_saveSMC($recordID, $PKField);

    $this->_saveCSC($recordID, $PKField);

} //end function DataHandler->_saveCaches()


function _saveRDC($recordID, $PKField, $delete = false)
{
    $mdb2 =& GetMDB2();
    //get triggers file
    $triggerFile = GENERATED_PATH . "/{$this->moduleID}/{$this->moduleID}_RDCTriggers.gen";

    if(file_exists($triggerFile)){
        $RDCtriggers = array();
        include ($triggerFile); //sets $RDCtriggers

        if(count($RDCtriggers) > 0){
            foreach($RDCtriggers as $triggerModuleID => $triggerSQL){

                if(false !== strpos($triggerSQL, '/**RecordID**/')){
                    $triggerSQL = str_replace('/**RecordID**/', $recordID, $triggerSQL);
                } else {
                    //this can be removed once all installations have been fully parsed
                    $triggerSQL .= " AND {$moduleID}.$PKFieldName = '$recordID'";
                }

                $triggerRecordIDs = $mdb2->queryCol($triggerSQL);
                $errcodes = mdb2ErrorCheck($triggerRecordIDs, false, false);

                if(0 == $errcodes['code']){
                    if(count($triggerRecordIDs)>0){
                        $strTriggerRecordIDs = join(',', $triggerRecordIDs);

                        if($delete){
                            $SQL = "UPDATE `rdc` SET _Deleted = 1 WHERE ModuleID = '$triggerModuleID' AND RecordID IN ($strTriggerRecordIDs)";

                            trace($SQL, "RDC delete");
                        } else {
                            //get existing cached records       
                            $SQL = "SELECT RecordID FROM `rdc` WHERE ModuleID = '$triggerModuleID' AND RecordID IN ($strTriggerRecordIDs)";

                            $cachedRecordIDs = $mdb2->queryCol($SQL);
                            mdb2ErrorCheck($cachedRecordIDs);
                            $strCachedRecordIDs = join(',',$cachedRecordIDs);

                            $insertIDs = array_diff($triggerRecordIDs, $cachedRecordIDs);

                            //get cached SQL file:
                            $RDCUpdateFile = GENERATED_PATH . "/{$triggerModuleID}/{$triggerModuleID}_RDCUpdate.gen";
                            if(file_exists($RDCUpdateFile)){
                                include $RDCUpdateFile; //imports $RDCinsert and $RDCupdate
//trace($RDCupdate, '$RDCupdate is');
//trace($strCachedRecordIDs, '$strCachedRecordIDs are');
                                if(!empty($strCachedRecordIDs)){ //should always be something?
                                    //update existing
                                    $RDCupdate = str_replace('[*updateIDs*]', $strCachedRecordIDs, $RDCupdate);
//trace($RDCupdate, '$RDCupdate is now');
                                    $r = $mdb2->exec($RDCupdate);
//trace($r, '$RDCupdate query result');
                                    mdb2ErrorCheck($r);
                                }
                                //insert new, if any
                                if(count($insertIDs)>0){
                                    $RDCinsert = str_replace('[*insertIDs*]', join(',', $insertIDs), $RDCinsert);

                                    $r = $mdb2->exec($RDCinsert);
                                    mdb2ErrorCheck($r);

                                }
                            }
                        }
                    }
                } else {
                    trigger_error("Warning: RDC update for module ($triggerModuleID) failed in $triggerFile.", E_USER_NOTICE);
                }
            }
        } 
    } else {
       // print "DEBUG: No triggers for $moduleID<br>\n";
    }
}


function _saveSMC($recordID, $PKField)
{
    $mdb2 =& GetMDB2();

    //get triggers file
    $triggerFile = GENERATED_PATH . "/{$this->moduleID}/{$this->moduleID}_SMCTriggers.gen";

    if(file_exists($triggerFile)){
        include $triggerFile; //sets $SMCtriggers

        foreach($SMCtriggers as $triggerModuleID => $triggerSQL){

            //possible to consolidate these 3 SQL statements?

            //get parent data
            $triggerSQL = str_replace(array('/*SubModuleID*/', '/*SubRecordID*/'), array($this->moduleID, $recordID), $triggerSQL);

            $data = $mdb2->queryRow($triggerSQL);
            mdb2ErrorCheck($data);

            if(!empty($data)){

                trace($data, "updating SMC trigger");

                $lookupSQL = "SELECT COUNT(*) FROM `smc` WHERE ModuleID = '{$data['ModuleID']}' AND RecordID = '{$data['RecordID']}' AND SubModuleID = '{$data['SubModuleID']}' AND SubRecordID = '{$data['SubRecordID']}'";

                $exists = $mdb2->queryOne($lookupSQL);
                mdb2ErrorCheck($exists);

                if(empty($exists)){
                    if(!defined('EXEC_STATE') || EXEC_STATE == 1){
                        global $User;
                        $user_id = $User->PersonID;
                    } else {
                        $user_id = 0;
                    }
                    $insertSQL = "INSERT INTO `smc` (ModuleID, RecordID, SubModuleID, SubRecordID, _ModBy, _ModDate, _Deleted)
                    VALUES ('{$data['ModuleID']}', '{$data['RecordID']}', '{$data['SubModuleID']}', '{$data['SubRecordID']}', '$user_id', NOW(), 0)";

                    $r = $mdb2->exec($insertSQL);
                    mdb2ErrorCheck($r);
                }
            }
        }
    }


} //end function DataHandler->_saveSMC()



function _saveCSC($recordID, $PKField)
{
    switch($this->moduleID){
    case 'cos':
    case 'lcod':
        //continue below
        break;
    default:
        return true;
        break;
    }

    $rskxaFeederModules = array(
        'hza',
        'ire',
        'len',
        'lin',
        'lit',
        'lpa',
        'lpd',
        'lppb',
        'lppe',
        'lppo',
        'lppv'
    );
    $str_rskxaFeederModules = "'hza','ire','len','lin','lit','lpa','lpd','lppb','lppe','lppo','lppv'";

    $mdb2 =& GetMDB2();

//trace($this, 'datahandler properties');

    //identify relvant parent records, ordered by moduleID
    if('cos' == $this->moduleID){
        //possible to get cos.RelatedRecordID and cos.RelatedModuleID from DataHandler
        $SQL = "SELECT ModuleID, RecordID FROM `smc` WHERE SubModuleID = '{$this->relatedRecordFieldValues['RelatedModuleID']}' AND SubRecordID = '{$this->relatedRecordFieldValues['RelatedRecordID']}' AND ModuleID IN ($str_rskxaFeederModules)";

        $parents = $mdb2->queryAll($SQL);
        mdb2ErrorCheck($parents);

        if(in_array($this->relatedRecordFieldValues['RelatedModuleID'], $rskxaFeederModules)){
            $directParent = array(
                'ModuleID' => $this->relatedRecordFieldValues['RelatedModuleID'],
                'RecordID' => $this->relatedRecordFieldValues['RelatedRecordID']
            );
            $parents[] = $directParent;
        }

    } elseif('lcod' == $this->moduleID) {

        //select 
        $SQL = "SELECT clm.RelatedModuleID AS ModuleID, clm.RelatedRecordID AS RecordID, clm.IncidentReportID
        FROM lcod
        INNER JOIN lco ON lcod.LossCostID = lco.LossCostID
        INNER JOIN clm ON lco.ClaimID = clm.ClaimID
        WHERE lcod.LossCostDetailID = '$recordID'";

        $r = $mdb2->queryRow($SQL);
        mdb2ErrorCheck($r);

        $parents = array(
            array('ModuleID' => 'ire', 'RecordID' => $r['IncidentReportID']),
            array('ModuleID' => $r['ModuleID'], 'RecordID' => $r['RecordID'])
        );

    } else {
        return true;
    }

    trace($parents, 'cos relevant parents');

    if(count($parents) > 0){
        foreach($parents as $parent){
            //look up generated *_CostSeveritySQL.gen file
            $fileName = GENERATED_PATH . "/{$parent['ModuleID']}/{$parent['ModuleID']}_CostSeveritySQL.gen";
            if(file_exists($fileName)) {
                include($fileName); //sets $csSQL

                $csSQL = str_replace('/**RecordID**/', $parent['RecordID'], $csSQL);
                trace($csSQL, 'csSQL');

                //for each parent record, calculate the CostSeverity and update the csc table
                //calculate

                $cachedData = $mdb2->queryRow($csSQL);
                mdb2ErrorCheck($cachedData);

                //check presence in csc
                $SQL = "SELECT COUNT(*) > 0 FROM `csc` WHERE ModuleID='{$parent['ModuleID']}' AND RecordID='{$parent['RecordID']}'";
                $exists = $mdb2->queryOne($SQL);
                mdb2ErrorCheck($exists);

                if($exists){
                    //update
                    $SQL = "UPDATE `csc` SET 
                        SeverityValue = '{$cachedData['CostSeverityValue']}',
                        TotalCost = '{$cachedData['TotalCost']}'
                        WHERE ModuleID='{$parent['ModuleID']}' AND RecordID='{$parent['RecordID']}'";

                } else {
                    //insert
                    if(!defined('EXEC_STATE') || EXEC_STATE == 1){
                        global $User;
                        $user_id = $User->PersonID;
                    } else {
                        $user_id = 0;
                    }

                    $SQL = "INSERT INTO `csc` (ModuleID, RecordID, SeverityValue, TotalCost, _ModBy, _ModDate, _Deleted) VALUES ('{$parent['ModuleID']}', '{$parent['RecordID']}', '{$cachedData['CostSeverityValue']}', '{$cachedData['TotalCost']}', $user_id, NOW(), 0)";
                }

                $r = $mdb2->exec($SQL);
                mdb2ErrorCheck($r);
            }
        }
    } else {
        return true;
    }
    return true;

}


function _saveLog()
{
    $SQL = $this->_buildLogSQL();

    foreach($this->PKFieldValues as $PKField => $PKFieldValue){
        $SQL = str_replace("[*pk*$PKField*]", $PKFieldValue, $SQL);
    }

    $mdb2 =& GetMDB2();

    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r);

    return true;
} //end function DataHandler->_saveLog()



/**
 *  Checks record consistency
 *
 *  Will look for a cached consistency file, and use it to check record consistency if it exists. Will
 *  update record consistency state (in the consistency table) as appropriate.
 */
function _checkConsistency()
{
//return true; //turn off temporarily
    $file_path = GENERATED_PATH.'/'.$this->moduleID.'/'.$this->moduleID.'_Consistency.gen';
    if(file_exists($file_path)){
        include $file_path; //returns $consistencySQLs
        trace($consistencySQLs, '$consistencySQLs');

        $mdb2 = GetMDB2();

        $triggerSQL = $consistencySQLs['triggerSQL'];
        $SQLwhere = "\nWHERE " . $this->_getPKSQLSnip();
        foreach($this->PKFieldValues as $PKField => $PKFieldValue){
            $SQLwhere = str_replace("[*pk*$PKField*]", $PKFieldValue, $SQLwhere);
        }
        $triggerSQL .= $SQLwhere;
        trace($triggerSQL, 'triggerSQL');

        $triggerResult = $mdb2->queryRow($triggerSQL);
        mdb2ErrorCheck($triggerResult);

        trace($triggerResult, 'triggerResult');

        //loop through all *columns* to determine the state of each consistency condition
        $condition_triggered = false;
        $unmet_targets = array();
        foreach($triggerResult as $condition_name => $triggered){
            if(!empty($triggered)){
                $condition_triggered = true;

                //look up condition targets
                $targetSQL = 'SELECT ';
                $targetSQL .= join(",\n", $consistencySQLs['targets'][$condition_name]['selects']);
                $targetSQL .= "\nFROM `{$this->moduleID}`\n";
                if(!empty($consistencySQLs['targets'][$condition_name]['joins'])){
                    $targetSQL .= join("\nAND ", $consistencySQLs['targets'][$condition_name]['joins']);
                }
                $targetSQL .= $SQLwhere;

                trace($targetSQL, 'targetSQL');

                $targetResult = $mdb2->queryRow($targetSQL);
                mdb2ErrorCheck($targetResult);

                trace($targetResult, 'targetResult');

                //for each triggered condition, check whether it is satisfied => mark as inconsistent
                foreach($targetResult as $targetName => $satisfied){
                    if(!$satisfied){
                        $unmet_targets[$triggered][] =  $targetName;

                        //mark as inconsistent
                        trace("Unmet consistency condition: $condition_name, target $targetName is unsatisfied");
                    }
                }
            }
        }

        $recordID = end($this->PKFieldValues);
        $remoteDataHandler = GetDataHandler('ccs', false, true);
        $remoteDataHandler->originatingModuleID = $this->moduleID;
        $relatedValues = array();
        $relatedValues['RecordID'] = $recordID;
        $relatedValues['ModuleID'] = $this->moduleID;
        $values['Triggers'] = '';
        $values['Targets'] = '';

        if(count($unmet_targets) > 0){
            trace($unmet_targets, 'unmet targets, need to be logged');

            $values = array();
            foreach($unmet_targets as $triggered => $condition_targets){
                $values['Inconsistent'] = '1';
                $values['Triggers'] .= ','.$triggered;
                $values['Targets'] .= ','.join(',', $condition_targets);

            }
            //trace($values, '$values');
            //trims the leading comma
            $values['Triggers'] = substr($values['Triggers'], 1);
            $values['Targets'] = substr($values['Targets'], 1);
            $remoteDataHandler->saveRowWithRelatedValues($values, $relatedValues);
        } else {
            //if no conditions were triggered, check whether the record is marked as inconsistent in the database => mark as consistent...
            $SQL = "SELECT Inconsistent FROM ccs WHERE ModuleID = '{$this->moduleID}' AND RecordID = '$recordID'";
            $inconsistent = $mdb2->queryOne($SQL);
            mdb2ErrorCheck($isConsistent);

            if($inconsistent){
                $values['Inconsistent'] = '0';
                $values['Triggers'] = '';
                $values['Targets'] = '';
                $remoteDataHandler->saveRowWithRelatedValues($values, $relatedValues);
            }
        }

    }
}


/**
 *  Checks parent record consistency
 *
 *  Will look for a cached parent consistency file, and use it to check the consistency of the parent
 *  record. Updates the parent record's consistency state as appropriate
 */
function _checkParentConsistency()
{
    $recordID = end($this->PKFieldValues);
    $mdb2 = GetMDB2();

    //figure out parent record (moduleID, recordID)
    $SQL = "SELECT ModuleID, RecordID FROM `smc` WHERE SubModuleID = '{$this->moduleID}' AND SubRecordID = '$recordID' AND _Deleted = 0";
    $parentRecords = $mdb2->queryAll($SQL);
    mdb2ErrorCheck($parentRecords);
    if(0 == count($parentRecords)){
        return null;
    }

    //check whether parent record has consistency conditions?
    foreach($parentRecords as $parentRecordValues){
        $parentModuleID = $parentRecordValues['ModuleID'];
        $parentRecordID = $parentRecordValues['RecordID'];
        $file_path = GENERATED_PATH.'/'.$parentModuleID.'/'.$parentModuleID.'_Consistency.gen';
        if(!file_exists($file_path)){
            trace("Skipping parent consistency check - no parent consistency condition file.");
            continue;
        }

        //update consistency of parent record
        $parentDataHandler = GetDataHandler($parentModuleID);
        $parentDataHandler->setRecordID($parentRecordID);
        $parentDataHandler->_checkConsistency();
    }
}


function verifyUniqueness(&$values, $exists)
{
    if(count($this->uniquenessIndexes) > 0){
trace($this->uniquenessIndexes, '$this->uniquenessIndexes '.$exists);
        $presql = 'SELECT '.join(',',$this->PKFields).' FROM `'.$this->moduleID.'` ';
        $joins = array();
        $wsql = '';
        foreach($this->uniquenessIndexes as $ixName => $ixFields){
            $sql = $presql;
            $nIxFields = count($ixFields);
            $nFoundFields = 0;
            foreach($ixFields as $field => $fieldInfo){
                if(!isset($values[$field]) && !$exists){
                    //value is required
                    $errmsg = array(sprintf(gettext("The field '%s' is required (because of a uniqueness constraint) when inserting a new record into %s."), $field, $this->moduleID) => 'uniq01');
                    $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                    return false;
                }
                //only if field is in $values...
                if(isset($values[$field])){
                    $nFoundFields += 1;
                    $wsql .= ' AND '.$fieldInfo['s'].' LIKE '.dbQuote($values[$field], $this->tableFields[$field]);
                    if(isset($fieldInfo['j'])){
                        $joins = array_merge($joins, $fieldInfo['j']);
                    }
                }
            }
            if(count($joins) > 0){
                $joins = SortJoins($joins);
                $sql .= implode(' ', $joins);
            }
            $sql .= " WHERE `{$this->moduleID}`._Deleted = 0 $wsql";
trace($sql, 'verifyUniqueness sql', true);
            if($nIxFields == $nFoundFields){
//              trace($sql, 'DataHandler verifyUniqueness '.$this->moduleID);
                $mdb2 =& GetMDB2();
                mdb2ErrorCheck($mdb2);
                //handle both $existing and not
                $verifyResult = $mdb2->queryAll($sql);
                mdb2ErrorCheck($verifyResult);

                if(count($verifyResult) > 1){
                    $msg = "";
                    trigger_error("Warning: The uniqueness index '$ixName' (fields: ".join(',',array_keys($ixFields)).") of the '{$this->moduleID}' module is violated. Please clean the data.", E_USER_WARNING);
                }

                if($exists){
                    if(count($verifyResult) > 0){
                        $matched = false;
                        foreach($verifyResult as $verifyRow){
                            $found = true;
                            foreach($this->PKFieldValues as $pkField => $pkValue){
                                if($verifyRow[$pkField] != $pkValue){
                                    $found = false;
                                } 
                            }
                            if($found){
                                $matched = true;
                            }
                        }
                        if(!$matched){
                            $msgValues = array(); 
                            foreach(array_keys($ixFields) as $fieldName){
                                $msgValues[] = "$fieldName = '{$values[$fieldName]}'";
                            }
                            $moduleInfo = GetModuleInfo($this->moduleID);
                            $moduleName = $moduleInfo->getProperty('moduleName');
                            $errmsg = array(sprintf(gettext("The supplied data (%s) matches a different record in the %s module. To save this record, you must choose a different value."), join(' and ',$msgValues), $moduleName) => 'uniq02');
                            $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                            return false;
                        }
                    }
                } else {
                    //we expect an empty result
                    if(count($verifyResult) > 0){
trace($verifyResult, 'verifyResult: we expected empty');
                        $msgValues = array(); 
                        foreach(array_keys($ixFields) as $fieldName){
                            $msgValues[] = "$fieldName = '{$values[$fieldName]}'";
                        }

                        $errmsg = array(sprintf(gettext("The supplied data (%s) matches an existing record in %s."), join(' and ',$msgValues), $this->moduleID) => 'uniq03');
                        $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                        return false;
                    }
                }
            } else {
                if($nFoundFields > 0){
                    $errmsg = array(sprintf(gettext("The uniqueness index '%s' (fields: %s) requires data in all (or none) of the fields when updating or inserting data in %s."), $ixName, join(', ',array_keys($ixFields)), $this->moduleID) => 'uniq04');
                    $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                    return false;
                }

                if(!$exists){
                    if(0 == $nFoundFields){
                        if(1 == $nIxFields){
                            $errmsg = array(sprintf(gettext("The uniqueness index '%s' requires the field '%s' to be supplied when inserting data into %s."), $ixName, reset(array_keys($ixFields)), $this->moduleID) => 'uniq05');
                            $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                        } else {
                            $errmsg = array(sprintf(gettext("The uniqueness index '%s' (fields: %s) requires all the fields to be supplied when inserting data into %s."), $ixName, join(', ',array_keys($ixFields)), $this->moduleID) => 'uniq05'); //this is just the plural messages
                            $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                        }
                        return false;
                    }
                }
            }
        }
    }
    return true;
}


function resolveForeignValues(&$values)
{
    global $gResolvedValues;
    foreach($values as $fieldName => $value){
        if(!isset($this->resolvableFields[$fieldName])){
            continue;
        }

        $resolveInfo = $this->resolvableFields[$fieldName];

        //global lookup cache. No need to look for the same record more than once.
        if(isset($gResolvedValues[$resolveInfo['mdl']][$resolveInfo['field']][$values[$fieldName]])){
            $values[$resolveInfo['resolvesTo']] = $gResolvedValues[$resolveInfo['mdl']][$resolveInfo['field']][$values[$fieldName]];
            continue;
        }

        if(empty($values[$fieldName])){
            //don't resolve empty values
            continue;
        }

        $dh =& GetDataHandler($resolveInfo['mdl']);
        $foreignValues = array($resolveInfo['field'] => $values[$fieldName]);
        if(!isset($resolveInfo['siblings'])){  //try the simpler case first
/*
[ParentOrganization] => Array
(
    [mdl] => org
    [field] => Name
    [lookup] => OrganizationID
    [resolvesTo] => ParentOrganizationID
)*/
        } else {
            //there are sibling fields, so we need to look for them and include them in the process
trace($resolveInfo['siblings'], "siblings");
            foreach($resolveInfo['siblings'] as $indexName => $indexSiblings){
                foreach($indexSiblings as $siblingInfo){
                    if(isset($siblingInfo['constantValue'])){
                        $foreignValues[$siblingInfo['foreignName']] = $siblingInfo['constantValue'];
                        continue;
                    }
                    if(isset($values[$siblingInfo['localName']])){
                        $foreignValues[$siblingInfo['foreignName']] = $values[$siblingInfo['localName']];
                    } else {
                        //missing a sibling value -- don't use this field to resolve a value.
                        continue 3;
                    }
                }
            }

/*
[OrganizationCategory] => Array
(
    [mdl] => cod
    [field] => Description
    [lookup] => CodeID
    [resolvesTo] => OrganizationCategoryID
    [siblings] => Array
        [cod_Description_CodeTypeID] => Array
        (
            [1] => Array
                (
                    [foreignName] => CodeTypeID
                    [localName] => OrganizationCategory:CodeTypeID
                    [constantValue] => 38
                )

        )
    )
*/
        }
trace($foreignValues, "passed foreignValues to be resolved");

        $foreignRowID = $dh->findRecordID($foreignValues, $resolveInfo['lookup']);
trace($foreignRowID, "resolved foreignRowID");
        unset($dh);

        if(false === $foreignRowID){
            if(count($this->errmsg)){
                trigger_error(join("\n",$this->errmsg), E_USER_ERROR);
            }
            continue;
        }

        if(0 == $foreignRowID){
            //matching record was not found
            $foreign_value_strings = array();
            foreach($foreignValues as $field => $value){
                $foreign_value_strings[] = "$field='$value'";
            }
            //die("Resolving {$resolveInfo['resolvesTo']} by ".join(' and ', $foreign_value_strings)." did not locate anything.\n");
            $errmsg = array(sprintf(gettext("Could not resolve the value of %s by matching against %s.  You may need to change the input data, or insert a matching record in the '%s' module."), $resolveInfo['resolvesTo'], join(' and ', $foreign_value_strings), $resolveInfo['mdl']) => 'reslv01');
            $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
            return false;
        }

        if(isset($values[$resolveInfo['resolvesTo']])){ //if the resolvable value was actually supplied, verify it
            if($values[$resolveInfo['resolvesTo']] != $foreignRowID){
                $errmsg = array("Supplied resolvable field '$fieldName' resolved to '$foreignRowID', but the explicit field '{$resolveInfo['resolvesTo']}' contained '{$values[$resolveInfo['resolvesTo']]}'." => 'resolve_mismatch');
                $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                return false;
                //trigger_error("Supplied resolvable field '$fieldName' resolved to '$foreignRowID', but the explicit field '{$resolveInfo['resolvesTo']}' contained '{$values[$resolveInfo['resolvesTo']]}'.", E_USER_ERROR);
            }
        } else {
            $gResolvedValues[$resolveInfo['mdl']][$resolveInfo['field']][$values[$fieldName]] = $foreignRowID;
            $values[$resolveInfo['resolvesTo']] = $foreignRowID;
        }
trace($values, 'resolved ForeignValues');
    }
    return true;
}


function findRecordID(&$values, $fieldName = null)
{
    if(1 < count($this->PKFields)){
        return false;
    }

    if(empty($fieldName)){
        $pkFieldName = end($this->PKFields);
    } else {
        $pkFieldName = $fieldName;
    }
    if(!empty($this->PKFieldValues)){
        if(!empty($this->PKFields[$pkFieldName])){
            trace("returning row ID from PKFieldValues");
            return $this->PKFields[$pkFieldName];
        }
    }

    //look up by PK field value, if any
    if(!empty($values[$pkFieldName])){
        trace("returning row ID from supplied values");
        return $values[$pkFieldName];
    }

    //look up by uniqueness indexes
    if(count($this->uniquenessIndexes) > 0){
        //try to match supplied values against indexes
        $matchedIndexes = array();
        foreach($this->uniquenessIndexes as $ixName => $fields){
            $found = true;
            foreach($fields as $fieldName => $fieldInfo){
                if(!isset($values[$fieldName])){
                    $found = false;
                }
            }
            if($found){
                $matchedIndexes[] = $ixName;
            }
        }
        if(count($matchedIndexes) > 0){
            $sqls = array();
            global $SQLBaseModuleID;
            $SQLBaseModuleID = $this->moduleID;
            foreach($matchedIndexes as $matchedIndex){
                $sqls[$matchedIndex] = "SELECT $pkFieldName, '$matchedIndex' AS IndexName FROM `{$this->moduleID}` ";
                $wsql = '';
                $joins = array();
                foreach($this->uniquenessIndexes[$matchedIndex] as $fieldName => $fieldInfo){

trace($this->uniquenessIndexes[$matchedIndex], 'findRecordID uniquenessIndexes');
trace($fieldInfo, $fieldName.' fieldInfo');
                    /*switch($type){
                    case 'tablefield':
                    $sqls[$matchedIndex] .= ' AND '.$fieldName.' LIKE '.dbQuote($values[$fieldName], $this->tableFields[$fieldName]);
                        break;
                    case 'remotefield':
                        trigger_error("Remotefields not yet handled..", E_USER_WARNING);
                        break;
                    default:
                        break;
                    }*/
                    $wsql .= ' AND '.$fieldInfo['s'].' LIKE '.dbQuote($values[$fieldName], $this->tableFields[$fieldName]);
                    if(isset($fieldInfo['j'])){
                        $joins = array_merge($joins, $fieldInfo['j']);
                    }
                }
                if(count($joins) > 0){
                    $joins = SortJoins($joins);
                    $sqls[$matchedIndex] .= join("\n", $joins);
                }
                $sqls[$matchedIndex] .= " WHERE `{$this->moduleID}`._Deleted = 0 $wsql";
            }
            $sql = join(' UNION ', $sqls);
trace($sql, 'DataHandler-findRecordID');

            $mdb2 = GetMDB2();
            $lookupResult = $mdb2->queryAll($sql);
            mdb2ErrorCheck($lookupResult);

            if(0 < count($lookupResult)){
                $keysByValue = array();
                $matchedIndexes = array(); //to check for duplicates returned by the same index
                foreach($lookupResult as $lookupResultRow){
                    $keysByValue[$lookupResultRow[$pkFieldName]] = $lookupResultRow['IndexName'];
                    if(isset($matchedIndexes[$lookupResultRow['IndexName']])){
                        trigger_error("The data in '{$this->moduleID}' uniqueness index {$lookupResultRow['IndexName']} is not unique. You should clean up the data.", E_USER_WARNING);
                    }
                    $matchedIndexes[$lookupResultRow['IndexName']] = $lookupResultRow[$pkFieldName];
                }
trace($lookupResult, 'lookupResult');
trace($keysByValue, 'keysByValue');
                if(count($keysByValue) > 1){
                    $errmsg = array(sprintf(gettext("Looking up the record ID by uniqueness indexes in '%s' was inconclusive. The indexes %s matched different records."), $this->moduleID, join(', ', $keysByValue)) => 'findrow01');
                    $this->errmsg = array_merge($errmsg, (array)$this->errmsg);
                    return false;
                }
                return $lookupResult[0][$pkFieldName];
            }
        }
    }

    return 0; //new record
} //end function findRecordID()


function setRecordID($recordID)
{
    if(1 == count($this->PKFields)){
        $pkFieldName = end($this->PKFields);
        $this->PKFieldValues[$pkFieldName] = $recordID;
    } else {
        trigger_error("Cannot set a single RecordID in a {$this->moduleID} record: Multiple fields define the primary key together.", E_USER_ERROR);
    }
}


function startTransaction()
{
    global $g_transaction_id;
    if(empty($g_transaction_id)){
        $g_transaction_id = false;
    }

    global $g_transaction_level;
    if(empty($g_transaction_level)){
        $g_transaction_level = 0;
    }
    $g_transaction_level++;
    trace("Transaction ID $g_transaction_id, level $g_transaction_level");

    if(!$g_transaction_id){
        $mdb2 =& GetMDB2();
        trace('startTransaction');

        if(!$mdb2->inTransaction()){
            trace('starting transaction');
            $r = $mdb2->beginTransaction();
            mdb2ErrorCheck($r);

            if(!defined('EXEC_STATE') || EXEC_STATE == 1){
                global $User;
                $user_id = $User->PersonID;
            } else {
                $user_id = 0;
            }

            //insert record in trx ("_" fields will be removed later)
            $SQL = "INSERT INTO `trx` (TransactionDate, UserID, _ModBy, _ModDate, _Deleted)\n";
            $SQL .= "VALUES (NOW(), '$user_id', '$user_id', NOW(), 0)";

            $r = $mdb2->exec($SQL);
            mdb2ErrorCheck($r);

            $g_transaction_id = $mdb2->lastInsertID('trx');
            mdb2ErrorCheck($g_transaction_id);

            trace("Got transaction ID $g_transaction_id, level $g_transaction_level");
        }
    }

    return true;

} //end function DataHandler->startTransaction()


function endTransaction()
{
    global $g_transaction_id;
    global $g_transaction_level;

    $mdb2 =& GetMDB2();
    trace('endTransaction');
    if(!$mdb2->inTransaction()){
        trigger_error("DataHandler::endTransaction() was called when there was no active transaction. The DataHandler's moduleID is {$this->moduleID}.", E_USER_WARNING);
        return false; //nothing to do
    }

    trace("Transaction ID $g_transaction_id, level $g_transaction_level");
    $g_transaction_level--;

    if(0 == $g_transaction_level){
        trace('ending transaction '.$g_transaction_id);

        $r = $mdb2->commit();
        mdb2ErrorCheck($r);

        $g_transaction_id = 0;
    }
    return true;
} //end function DataHandler->endTransaction()


function rollbackTransaction()
{
    global $g_transaction_id;
    global $g_transaction_level;

    $mdb2 =& GetMDB2();
    trace('rollbackTransaction');
    if(!$mdb2->inTransaction()){
        trigger_error("DataHandler::rollbackTransaction() was called when there was no active transaction. The DataHandler's moduleID is {$this->moduleID}.", E_USER_WARNING);
        return false; //nothing to do
    }

    trace('rolling back transaction '.$g_transaction_id);

    $r = $mdb2->rollback();
    mdb2ErrorCheck($r);

    $g_transaction_id = 0;
    $g_transaction_level = 0;

    return true;

} //end function DataHandler->rollbackTransaction()


/**
 *  Inserts a tracking reckord into trxr (Transaction Records) module
 */
function trackDataChange($recordID, $actionTypeID)
{
    global $g_transaction_id;
    global $g_transaction_level;

    if(1 == $g_transaction_level){
        $indirect = 0;
    } else {
        $indirect = 1;
    }

    $SQL = "INSERT INTO `trxr` (TransactionID, RelatedModuleID, RelatedRecordID, Indirect, ActionTypeID, _ModBy, _ModDate, _Deleted)\n";
    $SQL .= "VALUES ($g_transaction_id, '{$this->moduleID}', '$recordID', '$indirect', '$actionTypeID', 0, NOW(), 0)";

    $mdb2 =& GetMDB2();
    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r);
}


function _checkPermission($values = null)
{
    if(!defined('EXEC_STATE') || EXEC_STATE == 1){
        global $User;
        $userID = $User->PersonID;
        $orgID = null;
        $newOrgID = null;

        //checks save permissions
        if(!empty($this->ownerOrgField)){
            if($this->exists){
                if(empty($this->dbValues[$this->ownerOrgField])){
                    //trigger_error("DataHandler ({$this->moduleID}): Permission for the record cannot be determined because there is no database value for {$this->ownerOrgField}", E_USER_WARNING);
                }
                $orgID = $this->dbValues[$this->ownerOrgField];

                if(!empty($values) && !empty($values[$this->ownerOrgField])){
                    $newOrgID = $values[$this->ownerOrgField];
                }
            } else {
                if(empty($values) || empty($values[$this->ownerOrgField])){
                    /* these will need to be reinstated when the problem with finding relevane Organization ID has been fixed. */
                    //trigger_error("DataHandler ({$this->moduleID}): Permission for the record cannot be determined because there is no value for {$this->ownerOrgField}", E_USER_WARNING);
                }
                $orgID = $values[$this->ownerOrgField];
            }
        }

        $permission = false;

        if(!empty($this->originatingModuleID)){
            $permission = $User->PermissionToEdit($this->originatingModuleID, $orgID);
        }

        if(!$permission){
            $permission = $User->PermissionToEdit($this->moduleID, $orgID);
        }

        if(!$permission){
            trigger_error("User {$User->UserName} has no permission to edit records in the '{$this->moduleID}' module.", E_USER_ERROR);
        }
        if(!empty($newOrgID)){
            $permission = $User->PermissionToEdit($this->moduleID, $newOrgID);
            if(!$permission){
                trigger_error("User {$User->UserName} has no permission to edit records in the '{$this->moduleID}' module.", E_USER_ERROR);
            }
        }
    }
    return true;
}


/**
 *  Private, consolidated row saving function
 */
function _save(&$values)
{
    $this->startTransaction();

    //needs to be in $values to be uniqueness-checked
trace($this->relatedRecordFieldValues, $this->moduleID .' related record field values');
    if(count($this->relatedRecordFieldValues) > 0){
        foreach($this->relatedRecordFieldValues as $relatedRecordField => $relatedRecordFieldValue){
            $values[$relatedRecordField] = $relatedRecordFieldValue;
        }
    }
    $exists = $this->_populate(array_keys($values));
trace($exists, $this->moduleID .' record exists');
    if(!$this->verifyUniqueness($values, $exists)){
        $this->rollbackTransaction();
        return false;
    }

    //permission checking (does not apply to command-prompt import)
    if(!defined('EXEC_STATE') || EXEC_STATE == 1){
        global $User;
        $userID = $User->PersonID;

        $this->_checkPermission($values);

        $values['_ModBy'] = $userID;
    } else {
        $values['_ModBy'] = 0;
    }

    if($exists){
        //update record
        $this->_update($values);
    } else {
        //insert record
        $this->_insert($values);
    }

    //save remote fields
    if(!$this->_saveRemoteFields($values)){
        return false;
    }

    //handle RDC and SMC caches
    $this->_saveCaches($values);

    //save log
    $this->_saveLog();

    //check consistency
    $this->_checkConsistency();
    $this->_checkParentConsistency();

    $this->endTransaction();

    $PKField = end($this->PKFields);
    $recordID = $this->PKFieldValues[$PKField];

    return $recordID;

} //end function DataHandler->_save


/**
 *  Private, consolidated row deletion function
 */
function _delete()
{
    //start transaction
    $this->startTransaction();

    $exists = $this->_populate(array());

    if($exists){
        //check permission
        if(!defined('EXEC_STATE') || EXEC_STATE == 1){
            global $User;
            $userID = $User->PersonID;

            $this->_checkPermission();
        } else {
            $userID = 0;
        }

        //mark selected row as deleted
        $SQL = "UPDATE `{$this->moduleID}` SET _ModBy = $userID, _ModDate = NOW(), _Deleted = 1 WHERE ";
        $SQL .= $this->_getPKSQLSnip();
        foreach($this->PKFieldValues as $PKField => $PKFieldValue){
            $SQL = str_replace("[*pk*$PKField*]", $PKFieldValue, $SQL);
            $recordID = $PKFieldValue;
        }

        trace($SQL, $this->moduleID.' deleteSQL');

        $mdb2 =& GetMDB2();
        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);

        //mark all remote records as deleted
        $this->_deleteRemoteFields();

        //undo RDC, SMC
        $this->_saveCaches($values, true);

        //log
        $this->_saveLog();

        $this->trackDataChange($recordID, 3);
    }

    //end transaction
    $this->endTransaction();
}


function _getNextRecordID($conditionTags, $conditions)
{
    $mdb2 =& GetMDB2();
    $recordIDField = end($this->PKFields);

    //we need the next available record ID
    $SQL = "SELECT MAX($recordIDField) +1 FROM `{$this->moduleID}`";
    if(count($this->PKFields) > 1){
        $SQL .= " WHERE ";
        $SQL .= $this->_buildRelatedSQLSnip();
    }
    trace($SQL, "DataHandler->_getNextRecordID() SQL");

    $newRecordID = $mdb2->queryOne($SQL);
    mdb2ErrorCheck($newRecordID);
    return $newRecordID;

}


/******************
 Public Functions
******************/


/**
 *   The normal row saving function
 *
 *   @param $values            : Associative array of field names and values to be saved
 *   @param $recordIDs         : Simple array of supplied record IDs (typically one entry)
 *   @param $skipFields        : Fields for which a value may be passed (e.g. PK field values)
 *                               but should not be saved
 *
 *   Returns the new or existing record ID, or FALSE if saving failed. In the latter case,
 *   the error message can be found in the errmsg property.
 */
function saveRow($values, $recordID, $skipFields = array())
{
trace($values, "values passed to saveRow");

    $resolved = $this->resolveForeignValues($values);
    if(!$resolved){
        return false;
    }
    trace($values, 'resolved values');

    //applies to cod and maybe others
    if(empty($recordID) && !$this->autoIncrement){
        $recordIDField = end($this->PKFields);
        if((!isset($values[$recordIDField])) || empty($values[$recordIDField])){
            $recordID = $this->_getNextRecordID(array_keys($this->relatedRecordFieldValues), $this->relatedRecordFieldValues);
        } else {
            $recordID = intval($values[$recordIDField]);
        }
    }

    foreach($this->PKFields as $PKField){
        $this->PKFieldValues[$PKField] = $recordID;
    }

    if(count($this->PKFields) > 1){
        foreach($this->relatedRecordFieldValues as $fieldName => $fieldValue){
            $this->PKFieldValues[$fieldName] = $fieldValue;
        }
    }
trace($skipFields, '$skipFields');
    if(count($skipFields) > 0){
        foreach($skipFields as $skipField){
            unset($values[$skipField]);
        }
    }
trace($values, "values passed to _save");
    return $this->_save($values);
} //end function DataHandler->saveRow()


/**
 *  Saves the row based on "related" values.
 *
 *  This is used when saving remote fields. Note that if all the remote fields are
 *  empty, this function will implicitly DELETE the record instead!
 */
function saveRowWithRelatedValues($values, $relatedValues)
{
    $this->useRemoteIDCheck = true;
    $this->relatedRecordFieldValues = $relatedValues;

    $debug_prefix = "DataHandler({$this->moduleID})->saveRowWithRelatedValues";
trace($this->relatedRecordFieldValues, "$debug_prefix relatedRecordFieldValues", true);
trace($values, "$debug_prefix passed values");

    //check existence
    $exists = $this->_populate(array_keys($values));

    //check if any values were actually passed for saving
    if(count($values) > 0){
        $found_value = false;
        foreach($values as $value){
            //if(!empty($value)){
            if(isset($value)){
                $found_value = true;
            }
        }
    }

    //don't implicitly delete the record if it also has remotefields
    //this could be expanded to count submodule records too (i.e. don't implicitly delete records that have submodules)
    $has_dependents = (count($this->remoteFields) > 0);

    if($found_value){
        return $this->_save($values);
    } else {
        return true;
        /*if($exists && !$has_dependents){
            return $this->_delete();
        } else {
            return true; //do nothing
        }*/
    }
} //end function DataHandler->saveRowWithRelatedValues()


function importRow(&$values)
{
    $this->isPopulated = false; //force re-populating the values

    $recordID = 0;
    foreach($this->PKFields as $PKField){
        $recordID = $this->findRecordID($values);
        if(false === $recordID){
            if(count($this->errmsg) > 0){
                foreach($this->errmsg as $msg => $id){
                    print "Data could not be saved because:\n";
                    print wordwrap("$msg (error: $id)\n");
                }
            }
        }

        if(!$recordID){
            unset($this->PKFieldValues[$PKField]);
        }
    }

    //translate 0 for false to -1 in bool fields
    static $moduleFields = array();
    if(!isset($moduleFields[$this->moduleID])){
        $moduleFields[$this->moduleID] = GetModuleFields($this->moduleID);
    }

    foreach($values as $fieldName => $value){
        if('bool' == $moduleFields[$this->moduleID][$fieldName]->dataType){
            if('0' === $values[$fieldName]){

                $values[$fieldName] = '-1';
            }

        }
    }


    return $this->saveRow($values, $recordID);
} //end function DataHandler->importRow()


function deleteRow($recordID)
{

    foreach($this->PKFields as $PKid => $PKField){
        $this->PKFieldValues[$PKField] = $recordID;
    }

    if(count($this->PKFields) > 1){
        foreach($this->relatedRecordFieldValues as $fieldName => $fieldValue){
            $this->PKFieldValues[$fieldName] = $fieldValue;
        }
    }
trace($this->PKFieldValues, "PKFieldValues");
    return $this->_delete();
}


function deleteRowWithRelatedValues($relatedValues)
{
    $this->useRemoteIDCheck = true;
    $this->relatedRecordFieldValues = $relatedValues;

$debug_prefix = "DataHandler({$this->moduleID})->deleteRowWithRelatedValues";
trace($this->relatedRecordFieldValues, "$debug_prefix relatedRecordFieldValues");

    return $this->_delete();
}
} //end class DataHandler
?>