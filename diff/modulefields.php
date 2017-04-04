<?php
/**
 * Module Fields class definitions
 *
 * This file contains the class definitions for the module fields. They
 * correspond with the elements in the ModuleFields section of the module
 * definition XML documents.  Each class represent a type of field, either
 * "real" (TableField) or "virtual" (all others). For data retrieval, the 
 * "virtual" fields can be referenced in screen and grid definitions much
 * like the "real" ones. The individual implementations of the makeJoinDef
 * and makeSelectDef methods in each class provide the necessary SQL snippets
 * to build the correct SQL SELECT statement. For saving, the RemoteField
 * supports saving data into tables other than the one that belongs to the
 * module that is defined in a particular XML definition.
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
 * @version        SVN: $Revision: 1675 $
 * @last-modified  SVN: $Date: 2009-06-19 11:46:53 +0200 (Pt, 19 cze 2009) $
 */


/**
 * abstract parent class for fields that appear in ModuleFields section of Module Definition File
 */
class ModuleField
{
    var $name;
    var $phrase;
    var $moduleID;
    var $dataType;
    var $defaultValue; //default form value
    var $displayFormat; //sptintf-type formatting string

function Factory($element, $moduleID)
{
    return false;
}


function getQualifiedName($pModuleID)
{
    return $this->makeSelectDef($pModuleID, false);
}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    //override this
    return "--missing field {$this->name} in statement (unhandled field type)";
}


function makeJoinDef($pModuleID)
{
    //override this
    return "--missing field {$this->name} in statement (unhandled field type)";
}


function assignParentJoinAlias($dependentAlias, $parentAlias)
{
    global $SQLBaseModuleID;
    global $gTableAliasParents;

    print "{$this->moduleID}.{$this->name}: setting gTableAliasParents['$SQLBaseModuleID']['$dependentAlias'] = $parentAlias\n";
    $gTableAliasParents[$SQLBaseModuleID][$dependentAlias] = $parentAlias;

}


/**
 * returns a string which is unique for each join, but the same for different fields that share the same join.
 */
function getTableAliasKey()
{
    return '';
}


/**
 * whether or not this field needs to be retrieved after the record has been saved
 *
 * true for calculated fields (always), and viewfields (if using RPC updates)
 */
function needsReGet()
{
    return false;
}


/**
 * simply returns a list of directly dependent fields, not recursive
 */
function getDependentFields()
{
    return array();
}


/**
 * creates a trigger def to update the RDC for fields that depend on this field
 *
 */
function makeRDCTrigger($callerModuleID, $callerDefs= array())
{
    $debug_prefix = debug_indent("ModuleField-makeRDCTrigger() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix callerModuleID = $callerModuleID\n";

    //bailout: PK field
    $trigger_moduleInfo = GetModuleInfo($this->moduleID);
    $trigger_pkField = $trigger_moduleInfo->getPKField();

    if(($trigger_pkField == $this->name)){
        print "$debug_prefix skipping triggermaking - this is a PK field\n";
        debug_unindent();
        return true;
    }


    if('tablefield' == strtolower(get_class($this))){ //create the trigger

        $joins = array();
        $newModuleFields = array();
        $rangeFieldSelectDef = '';
        if(count($callerDefs)){
            foreach($callerDefs as $callerDef){
                print "$debug_prefix the callerDef element:\n";
                indent_print_r($callerDef);
                $newModuleFields[] = $callerDef;
            }
            if('rangefield' == strtolower(get_class($callerDef))){
                $rangeFieldSelectDef = $callerDef->makeSelectDef($callerModuleID, false);
            }
        }

        //make the SQL statement (finds the records that need to ne updated)
        global $SQLBaseModuleID;
        $SQLBaseModuleID = $callerModuleID;

        $callerModule = GetModule($callerModuleID);
        $callerPKFieldName = end($callerModule->PKFields);

        $joins = array();
print "$debug_prefix newModuleFields\n";
indent_print_r($newModuleFields);

        $alias = $callerModuleID;
        if(count($newModuleFields) > 0){
            foreach($newModuleFields as $name => $mf){
print "$debug_prefix calling makeJoinDef for new modulefield $name. alias is $alias\n";
                $joins = array_merge($mf->makeJoinDef($alias), $joins);
                $alias = GetTableAlias($mf, $alias);
            }
        }
        $triggerAlias = $alias; //last alias is expected to be the alias of the triggering module
        $joins = SortJoins($joins);

        //$SQL = "SELECT $callerAlias.$callerPKFieldName\n";
        $SQL = "SELECT `$callerModuleID`.$callerPKFieldName\n";
        //$SQL .= "FROM `{$this->moduleID}` -- $callerModuleID\n";
        $SQL .= "FROM `$callerModuleID`\n";

        print "$debug_prefix made these joins for {$this->moduleID}.{$this->name} (trigger alias: $triggerAlias):\n";
        indent_print_r($joins);

        foreach($joins as $join){
            $SQL .= $join."\n";
        }
        $SQL .= "WHERE `$callerModuleID`._Deleted = 0\n";
        $SQL .= "AND `$triggerAlias`._Deleted = 0\n";

        if(!empty($rangeFieldSelectDef)){
            print "this selectDef: $rangeFieldSelectDef\n";
            $SQL .= "AND $rangeFieldSelectDef = '/**RecordID**/'\n";
        } else {
            $SQL .= "AND `$triggerAlias`.$trigger_pkField = '/**RecordID**/'\n";
        }

        print "$debug_prefix triggerSQL\n$SQL\n";

        if(CheckSQL($SQL)){
            print "$debug_prefix verified SQL statement\n";

            //open RDCTriggersModel file
            $modelFileName = "RDCTriggersModel.php";
            $CreateFileName = "{$this->moduleID}_RDCTriggers.gen";
            $CreateFilePath = GENERATED_PATH. "/{$this->moduleID}/$CreateFileName";
            //open (or create) a RDCTriggers file for the callerModuleID and save the trigger SQL

            if(file_exists($CreateFilePath)){
                include($CreateFilePath); //sets $RDCtriggers
            } else {
                $RDCtriggers = array();
            }
            $RDCtriggers[$callerModuleID] = $SQL;

            $codeArray = array('/**RDCtriggers**/' => escapeSerialize($RDCtriggers));
            print "$debug_prefix Saving trigger\n";

            //file creation code...
            SaveGeneratedFile($modelFileName, $CreateFileName, $codeArray, $this->moduleID);
        }
print "\n";
        //restore normal module
        // $foreignModules[$this->moduleID] = $bakModule;

    } else { //continue recursion:


        $defs = $callerDefs;
        $defs[] = $this;
        /*if(!empty($def)){
            $defs[] = $def;
        }*/

        //3. recursively calls dependent fields
        $deps = $this->getDependentFields();
        print "$debug_prefix deps\n";
        indent_print_r($deps);
        if(count($deps) > 0){
            //foreach dependent field (ignore local tablefields), call $moduleField->makeRDCTrigger()
            foreach($deps as $dep){
                $moduleField = GetModuleField($dep['moduleID'], $dep['name']);
                if($moduleField->moduleID == $this->moduleID){
                    $moduleField->makeRDCTrigger($callerModuleID, $callerDefs);
                } else {
                    $moduleField->makeRDCTrigger($callerModuleID, $defs);
                }
            }
        }

    }
    debug_unindent();
}


/**
 * should return a definition how the calling field connects to the dependent field (which is to contain the trigger)
 */
function makeRDCCallerDef()
{
    return null; //override
}


function getGridAlign()
{
    switch ($this->getDataType()){
    case 'bool':
    case 'date':
    case 'datetime':
    case 'time':
        $gridAlign = 'center';
        break;
    case 'float':
    case 'int':
    case 'money':
        $gridAlign = 'right';
        break;
    case 'text':
        $gridAlign = 'justify';
        break;
    default:
        $gridAlign = 'left';
        break;
    }

    return $gridAlign;
}

function getDataType()
{
    return $this->dataType;
}

function getLocalModuleID()
{
    return $this->moduleID;
}


/**
 *  adds DB-level formatting to a Select string, based on the datatype of the field
 */
function prepareSelectWithDBFormat($select)
{
    switch($this->dataType){
    case 'money':
        return "ROUND($select, 2)";
        break;
    case 'date':
        return "DATE_FORMAT($select, GET_FORMAT(DATE,/*localDate*/'ISO'))";
        break;
    case 'datetime':
        return "DATE_FORMAT($select, GET_FORMAT(DATETIME,/*localDateTime*/'ISO'))";
        break;
    case 'time':
        return "DATE_FORMAT($select, GET_FORMAT(TIME,/*localTime*/'ISO'))";
        break;
    default:
        return $select;
    }
}

} //end class ModuleField





class TableField extends ModuleField
{
var $dbFlags;
var $validate;
var $defaultValue;                    //to be passed on to ScreenFields that need them
var $orgListOptions;                  //to be passed on to ScreenFields that need them
var $listConditions = array();        //to be passed on to ScreenFields that need them


function &Factory($element, $moduleID)
{
    $field =& new TableField($element, $moduleID);
    return $field;
}


function TableField(&$element, $moduleID)
{

    //don't validate if it contains the string 'noValidation'
    $validate = $element->getAttr('validate');
    if(false !== strpos($validate, 'noValidation')){
        $validate = '';
    }

    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type', true);
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->dbFlags = $element->getAttr('dbFlags');
    $this->phrase = $element->getAttr('phrase');
    $this->validate = $validate;
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;
    $this->orgListOptions = $element->getAttr('orgListOptions');

    if(empty($this->validate)){
        switch($this->dataType){
        case 'date':
        case 'datetime':
        case 'time':
            $this->validate = 'dateFormat';
            break;
        default:
            break;
        }
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('ListCondition' == $sub_element->type){
                $conditionObj = $sub_element->createObject($moduleID);
                $this->listConditions[$conditionObj->name] = $conditionObj;
            }
        }
    }
}



/**
 *  Returns a table field definition in MDB2-format.
 */
function getMDB2Def()
{
    $def = array();

    $pattern = '/\((.*)\)/';
    $length = 0;
    $scale = 0;
    $matches = array();
    $dataType = $this->dataType;
    if(preg_match($pattern, $dataType, $matches)){
        $length = $matches[1];
        $dataType = str_replace($matches[0], '', $dataType);
    }

    switch($dataType){
    case 'bool':
        $def['type'] = 'boolean';
        $def['length'] = 1;
        break;
    case 'bigint':
        $def['type'] = 'integer';
        $def['length'] = 8;
        break;
    case 'int':
        $def['type'] = 'integer';
        $def['length'] = 4;
        break;
    case 'tinyint':
        $def['type'] = 'integer';
        $def['length'] = 1;
        break;
    case 'decimal':
        $def['type'] = 'decimal';
        $def['length'] = 12;
        $def['scale'] = $length;
        break;
    case 'float':
        $def['type'] = 'float';
        break;
    case 'money':
        $def['type'] = 'decimal';
        $def['length'] = 12;
        $def['scale'] = 4;
        break;
    case 'varchar':
        $def['type'] = 'text';
        $def['length'] = $length;
        break;
    case 'text':
        $def['type'] = 'clob';
        break;
    case 'date':
        $def['type'] = 'date';
        break;
    case 'time':
        $def['type'] = 'time';
        break;
    case 'datetime':
        $def['type'] = 'timestamp'; //mdb2 translates "timestamp" to datetime type in MySQL!
        break;
    default:
        trigger_error("Data type $dataType is not known.", E_USER_ERROR);
        break;
    }

    //various options, gleaned from dbFlags (separate attributes would look less mysql-centric!)
    if(false !== strpos($this->dbFlags, 'auto_increment')){
        $def['autoincrement'] = true;
    }
    if(false !== strpos($this->dbFlags, 'not null')){
        $def['notnull'] = true;
    }
    if(false !== strpos($this->dbFlags, 'unsigned')){
        $def['unsigned'] = true;
    }
    if(false !== strpos($this->dbFlags, 'default')){

        //a regex might be more elegant
        $tmp = explode(' ', $this->dbFlags);
        $tmp_val = '0';
        foreach($tmp as $tmp_idx => $tmp_content){
            if('default' == $tmp_content){
                if(!isset($tmp[$tmp_idx + 1])){
                    trigger_error("The default declaration needs a value.", E_USER_ERROR);
                }
                $tmp_val = $tmp[$tmp_idx + 1];
                break;
            }
        }
        $def['default'] = $tmp_val;
    } else {
        if(isset($def['notnull']) && $def['notnull']){
            //need a default when notnull is true
            switch($def['type']){
            case 'boolean':
            case 'decimal':
            case 'float':
            case 'integer':
                $def['default'] = 0;
                break;
            case 'clob':
            case 'text':
                $def['default'] = '';
                break;
            case 'date':
            case 'time':
            case 'timestamp':
                $def['default'] = '0000-00-00 00:00:00';
                break;
            default:
                break;
            }
        }
    }
    //trace($def, 'field definition for '.$this->name);
    return $def;
}


function getQualifiedName($pModuleID)
{
    $debug_prefix = debug_indent("TableField-getQualifiedName() {$this->moduleID}.{$this->name}:");

    $name = "`$pModuleID`.{$this->name}";

    trace( "$debug_prefix returned $name\n", null, true);
    debug_unindent();
    return $name;
}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $def = "`{$pModuleID}`.{$this->name}";

    if($localizeOutput){
        $def = $this->prepareSelectWithDBFormat($def);
    }

    if($pIncludeFieldAlias){
        $def .= ' AS ' . $this->name;
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("TableField-makeJoinDef() {$this->moduleID}.{$this->name}:");

    $joins = array();

    debug_unindent();
    return $joins;
}
} //end class TableField







class ForeignField extends ModuleField
{
var $localTable;
var $localKey;
var $foreignTable;
var $foreignTableAlias;
var $foreignKey;
var $foreignField;
var $listCondition;  //deprecated - to be removed
var $listConditions = array();
var $joinType;
var $validate;       //needed for filtering fields
var $orgListOptions;           //to be passed on to ScreenFields that need them

//new key structure
var $keys = array(); //minimum one key


function &Factory($element, $moduleID)
{
    $field =& new ForeignField($element, $moduleID);
    return $field;
}

function ForeignField(&$element, $moduleID)
{
    $localTable = $element->getAttr('localTable');
    if(empty($localTable)){
        $localTable = $moduleID;
    }

    $this->name = $element->getAttr('name', true);

    //handles multiple fields in key attributes
    $localKeyStr = $element->getAttr('key');
    if(empty($localKeyStr)){
        $localKeyStr = $element->getAttr('localKey', true);
    }
    $localKeys = explode(' ', $localKeyStr);
    $foreignKeys = explode(' ', $element->getAttr('foreignKey', true));

    if(count($localKeys) != count($foreignKeys)){
        die("ForeignField $moduleID.{$this->name}: Inconsistent number of local keys vs. foreign keys.");
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    $this->localTable = $localTable;
    $this->localKey = $localKeys[0];
    if(count($localKeys) == 2){ //only supports a second join, not a third
        $this->localKey2 = $localKeys[1];
    }

    $this->foreignTable = $element->getAttr('foreignTable');
    if(empty($this->foreignTable)){
        $this->foreignTable = $element->getAttr('foreignModuleID', true);
    }
    $this->foreignKey = $foreignKeys[0];
    if(count($foreignKeys) == 2){
        $this->foreignKey2 = $foreignKeys[1];
    }
    $this->foreignField = $element->getAttr('foreignField', true);

    //new key structure (temporary: the constructor should be entirely rewritten)
    $this->keys[] = array($localKeys[0], $foreignKeys[0]);
    if(count($localKeys) == 2){
        $this->keys[] = array($localKeys[1], $foreignKeys[1]);
    }

    $this->listCondition = $element->getAttr('listCondition');
    $this->triggerCondition = $element->getAttr('triggerCondition');
    $this->joinType = $element->getAttr('joinType');
    $this->phrase = $element->getAttr('phrase');
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;
    $this->dataType = $element->getAttr('type');
    $this->validate = $element->getAttr('validate');
    $this->orgListOptions = $element->getAttr('orgListOptions');
    if(empty($this->orgListOptions) && 'ID' == substr($this->foreignField, -2)){
        $foreignModuleField = GetModuleField($this->foreignTable, $this->foreignField);
        $this->orgListOptions = $foreignModuleField->orgListOptions;
    }

    //simple sanity check to avoid infinite loops
    if($this->name == $this->localKey){
        print "The foreignField:\n";
        print_r($this);
        die("ForeignField '{$this->name}': Name and (local) Key can't be the same (error)");
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('ListCondition' == $sub_element->type){
                $conditionObj = $sub_element->createObject($moduleID);
                $this->listConditions[$conditionObj->name] = $conditionObj;
            }
        }
    }

}


function getQualifiedName($pModuleID)
{
    $debug_prefix = debug_indent("ForeignField-getQualifiedName() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix pModuleID = $pModuleID\n";

    $foreignAlias = GetTableAlias($this, $pModuleID);

    $foreignField = GetModuleField($this->foreignTable, $this->foreignField);
    $type = strtolower(get_class($foreignField));

    print "$debug_prefix foreignField type: $type\n";

    if('tablefield' == $type){
        $name = "`{$foreignAlias}`.{$this->foreignField}";
    } else {

        $name = $foreignField->getQualifiedName($foreignAlias);
    }

    print "$debug_prefix returned $name\n";

    debug_unindent();
    return $name;
}


//return the ModuleField type of the field that the foreignField property points to
function getForeignFieldType()
{
    $debug_prefix = debug_indent("ForeignField-getForeignFieldType() {$this->moduleID}.{$this->name}:");

    $t_mf = GetModuleField($this->foreignTable, $this->foreignField);
    $type = strtolower(get_class($t_mf));

    print "$debug_prefix returns $type (end)\n";
    debug_unindent();
    return $type;
}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $debug_prefix = debug_indent("ForeignField-makeSelectDef() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix pModuleID = {$pModuleID}\n";
    print "$debug_prefix pIncludeFieldAlias = {$pIncludeFieldAlias}\n";

    print "$debug_prefix Making select def for: {$this->foreignTable}\n";
    print "$debug_prefix   foreign Module: {$this->foreignTable} \n";
    print "$debug_prefix   foreign field name: {$this->foreignField}\n";


    $foreignAlias = GetTableAlias($this, $pModuleID);
    $t_mf = GetModulefield($this->foreignTable, $this->foreignField);

    if(!is_object($t_mf)){
        die("$debug_prefix ModuleField '{$this->foreignField}' appears not to exist in module '{$this->foreignTable}' (error)");
    }

    //if the foreignField field is not a TableField, create its selectDef
    if(strtolower(get_class($t_mf)) != 'tablefield'){
        print "$debug_prefix {$t_mf->name} is a ".get_class($t_mf)."\n";
        $def = $t_mf->makeSelectDef($foreignAlias, false, $localizeOutput);
    } else {
        $def = "`{$foreignAlias}`.{$this->foreignField}";
        if($localizeOutput){
            $def = $this->prepareSelectWithDBFormat($def);
        }
    }

    if($pIncludeFieldAlias){
        $def .= " AS {$this->name}";
    }

    print "$debug_prefix returning $def\n";
    debug_unindent();
    return $def;
}


/**
 *  Determines whether the field is resolvable, and if so, what the pertinent info is
 *
 *  Returns false, or if true, returns an array with properties
 */
function getResolvingInfo()
{
    $foreignModule = GetModule($this->getForeignModuleID());
    $foreignFieldName = $this->getTarget();
    $indexes = array();
    foreach($foreignModule->uniquenessIndexes as $indexName => $fields){
        foreach($fields as $fieldName => $type){
            if($foreignFieldName == $fieldName){
                $indexes[] = $indexName;
            }
        }
    }
    if(0 == count($indexes)){
        return false;
    }

    $module = GetModule($this->getLocalModuleID());

    $info = array();
    $info['mdl'] = $this->getForeignModuleID();
    $info['field'] = $foreignFieldName;
    $info['lookup'] = $this->getForeignKey();
    $info['resolvesTo'] = $this->getLocalKey();

$targetMF = $module->ModuleFields[$foreignFieldName];
$info['fieldType'] = get_class($targetMF);

    //look for siblings
    $indexSiblings = array();
    foreach($indexes as $indexName){
        if(count($foreignModule->uniquenessIndexes[$indexName]) > 1){
            $field_ix = 1;
            foreach($foreignModule->uniquenessIndexes[$indexName] as $fieldName => $type){
                if($fieldName != $foreignFieldName){
                    $indexSiblings[$indexName][$field_ix]['foreignName'] = $fieldName;
                    $siblingField = '';
                    //look for a local name
                    foreach($module->ModuleFields as $mFieldName => $mField){
                        switch(strtolower(get_class($mField))){
                        case 'codefield':
                        case 'foreignfield':
                            if($fieldName == $mField->getTarget()){
                                $siblingField = $mFieldName;
                            }
                            break;
                        default:
                            //skips field
                        }
                    }
                    if(empty($siblingField)){
                        $indexSiblings[$indexName][$field_ix]['localName'] = $this->name.':'.$fieldName;
                    } else {
                        $indexSiblings[$indexName][$field_ix]['localName'] = $siblingField;
                    }
                    if('codefield' == strtolower(get_class($this))){
                        $indexSiblings[$indexName][$field_ix]['constantValue'] = $this->codeTypeID;
                    }
                }
                $field_ix++;
            }
            $info['siblings'] = $indexSiblings;
        }
    }
    return $info;
}


/**
 *  New makeJoinDef that uses the keys array...? work in progress
 */
function new_makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("ForeignField-makeJoinDef() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix \$pModuleID = '$pModuleID'\n";
    $joins = array();

    $foreignAlias = GetTableAlias($this, $pModuleID);
    $localAlias = $pModuleID;  //uses the local alias passed from the calling field

    $foreignField = GetModuleField($this->foreignTable, $this->foreignField);



    indent_print_r($joins);
    debug_unindent();
    return $joins;
}

    function makeJoinDef($pModuleID)
    {
        $debug_prefix = debug_indent("ForeignField-makeJoinDef() {$this->moduleID}.{$this->name}:");
        print "$debug_prefix \$pModuleID = '$pModuleID'\n";
        //indent_print_r($this);
        
        $joins = array();
        
        //aliases
        $foreignAlias = GetTableAlias($this, $pModuleID);
        if(empty($pModuleID)){
            global $SQLBaseModuleID;
            $localAlias = $SQLBaseModuleID; //suppose we're at the base module
        } else {
            $localAlias = $pModuleID;  //uses the local alias passed from the calling field
        }
        
        //import dependent fields:
        $localKeyField = GetModuleField($this->moduleID, $this->localKey);
        $foreignKeyField = GetModuleField($this->foreignTable, $this->foreignKey);
        $foreignField = GetModuleField($this->foreignTable, $this->foreignField);
        
        //make sure they are real moduleFields
        if(empty($localKeyField)){
            die("$debug_prefix local key {$this->moduleID}.{$this->localKey} not found in ModuleFields\n");
        }
        if(empty($foreignKeyField)){
            die("$debug_prefix foreign key {$this->foreignTable}.{$this->foreignKey} not found in ModuleFields\n");
        }
        if(empty($foreignField)){
            die("$debug_prefix foreign field {$this->foreignTable}.{$this->foreignField} not found in ModuleFields\n");
        }
        
        //determine their classes
        $dt_localKeyField = strtolower(get_class($localKeyField));
        $dt_foreignKeyField = strtolower(get_class($foreignKeyField));
        $dt_foreignField = strtolower(get_class($foreignField));
        
        if('tablefield' != strtolower(get_class($localKeyField))){
            $parentAlias = GetTableAlias($localKeyField, $pModuleID);
        } else {
            $parentAlias = $pModuleID;
        }
        $this->assignParentJoinAlias($foreignAlias, $parentAlias);


        //those that aren't tablefields need another joinDef
        
        if('tablefield' != $dt_foreignField){
            //get joindef for foreignField
            $foreignFieldJoin = $foreignField->makeJoinDef($foreignAlias);
            $joins = array_merge($foreignFieldJoin, $joins);
            
            $dt_foreignField = strtolower(get_class($foreignField));
            print "$debug_prefix dt_foreignField = $dt_foreignField\n";
            
            indent_print_r($foreignFieldJoin);
        }
        if('tablefield' != $dt_foreignKeyField){
            //get joindef for foreignKeyField
            $foreignKeyJoin = $foreignKeyField->makeJoinDef($foreignAlias);
            $joins = array_merge($foreignKeyJoin, $joins);

            $dt_foreignKey = strtolower(get_class($dt_foreignKeyField));
            print "$debug_prefix dt_foreignKey = $dt_foreignKey\n";
        } 
        if('tablefield' != $dt_localKeyField){
            //get joindef for localKeyField
            $localJoin = $localKeyField->makeJoinDef($localAlias);
            $joins = array_merge($localJoin, $joins);
            indent_print_r($localJoin);
        }
        if($dt_localKeyField == 'foreignfield' && $localKeyField->getForeignFieldType() != 'tablefield'){

            //check whether the localKeyField's foreign field, in turn, is a tablefield or not
            $dt_localKey_ForeignField = $localKeyField->getForeignFieldType();
            print "$debug_prefix dt_localKey_ForeignField = $dt_localKey_ForeignField\n";

            print "$debug_prefix skipping ordinary join because local key isn't a TableField\n";

            if('tablefield' == $dt_localKey_ForeignField){
            } else {

                switch($dt_localKey_ForeignField){
                case 'foreignfield';
                    die("$debug_prefix Can't handle ForeignField");
                    break;
                case 'remotefield':
                    print "$debug_prefix adding a special bridge join\n";

                    $localKeyAlias = GetTableAlias($localKeyField, $localAlias);

                    //get the bridge field
                    $bridgeField = GetModuleField($localKeyField->foreignTable, $localKeyField->foreignField);

                    print "$debug_prefix localAlias = $localAlias: localKeyAlias = $localKeyAlias, pModuleID $pModuleID\n";
                    print "$debug_prefix the bridge field:\n";
                    //$bridgeField->moduleID = $this->moduleID;
                    indent_print_r($bridgeField);
                    //$bridgeAlias = GetTableAlias($bridgeField, $localAlias);
                    $bridgeAlias = GetTableAlias($bridgeField, $localKeyAlias);

                    //add the bridge join
                    $bridgejoin = "LEFT OUTER JOIN `{$this->foreignTable}` AS {$foreignAlias} \n";
                    $bridgeJoinConditions = array("`{$bridgeAlias}`.{$bridgeField->remoteField} = `{$foreignAlias}`.{$this->foreignKey}");

                    if (!empty($this->listCondition)){
                        $bridgeJoinConditions[] = "`{$foreignAlias}`.{$this->listCondition}";
                    }
                    //$bridgejoin .= "ON (`{$bridgeAlias}`.{$bridgeField->remoteField} = `{$foreignAlias}`.{$this->foreignKey} )";
                    $bridgejoin .= "ON (".join(' AND ', $bridgeJoinConditions).")";

                    print "$debug_prefix made bridge join\n";
                    indent_print_r($bridgejoin);

                    //$joins = array_merge($bridgeField->makeJoinDef($localAlias), $joins);
                    $joins = array_merge($bridgeField->makeJoinDef($localKeyAlias), $joins);
                    $joins[$foreignAlias] = $bridgejoin;


                    break;
                case 'dynamicforeignfield':


                    $localKey_ForeignField = GetModuleField($localKeyField->getForeignModuleID(), $localKeyField->getTarget());

                    $localKeyAlias = GetTableAlias($localKeyField, $localAlias);
                    $joins = array_merge($joins, $localKey_ForeignField->makeJoinDef($localKeyAlias));

                    break;
                default:
                    die("$debug_prefix Can't handle $dt_localKey_ForeignField");
                    break;
                }

            }

        } else {

            //(create local joindef)

            if ($this->joinType == "left"){
                //list condition need to be attached in join statement for MySQL
                $join = "LEFT OUTER JOIN `{$this->foreignTable}` AS {$foreignAlias} ";
            } else {
                $join = "INNER JOIN `{$this->foreignTable}` AS {$foreignAlias} ";
            }

            $localKeyName = $localKeyField->getQualifiedName($localAlias);
            $foreignKeyName = $foreignKeyField->getQualifiedName($foreignAlias);
            print "$debug_prefix local key name $localKeyName\n";
            print "$debug_prefix foreign key name $foreignKeyName\n";

            $join .= "\n   ON ($localKeyName = $foreignKeyName ";

            if(!empty($this->localKey2)){
                $localKeyField2 = GetModuleField($this->moduleID, $this->localKey);
                $localKeyName2 = $localKeyField2->getQualifiedName($localAlias);
                $foreignKeyName2 = $foreignAlias.'.'.$this->foreignKey2; //assumes tablefield
                $join .= "\n     AND $localKeyName2 = $foreignKeyName2";
            }


            if (!empty($this->triggerCondition)){
                //look for local module id, replace with localAlias
                $condition = str_replace("={$this->foreignTable}.", "=$foreignAlias.", $this->triggerCondition);
                $join .= "\n     AND `{$localAlias}`.$condition";
            } elseif (!empty($this->listCondition)){
                $condition = $this->listCondition;
                $join .= "\n     AND `{$foreignAlias}`.$condition";
            }

            $join .= ")";

            $joins[$foreignAlias] = $join;
        }

        indent_print_r($joins);
        debug_unindent();
        return $joins;
    }


    //for making "extended" Get and List statements
    function makeExtendedJoinDef($pModuleID){
        print "\n\n";
        print ">>> ===-===-===-===-===-===-===-===-===-===-===-===-===-===-=== >>>\n";
        print "          f o r e i g n f i e l d         b e g i n\n";
        print "                {$this->moduleID} . {$this->name} \n";
        print ">>> ===-===-===-===-===-===-===-===-===-===-===-===-===-===-=== >>>\n";
        print "ForeignField-makeExtendedJoinDef(): {$this->name} (begin)\n\n";
        $joins = array();
        
        //register this field on the joinField stack
        //if($this->useJoinFieldStack) PushJoinFieldStack(&$this);
        
        print "ForeignField: foreign Module:      {$this->foreignTable} \n";
        print "ForeignField: foreign field name:  {$this->foreignField}\n";
        print "ForeignField: foreign table alias: {$this->foreignTableAlias}\n";
        print "ForeignField: local key:           {$this->localKey}\n";
        print "ForeignField: foreign key:         {$this->foreignKey}\n";
        print "ForeignField: local module:        {$this->moduleID}\n";
        print "ForeignField: local table:         {$this->localTable}\n";

        $plainFTAlias = GetTableAlias($this, $pModuleID);
        //$plainFTAlias = $pModuleID;
        $foreignTableAlias = $plainFTAlias;
        
        print "ForeignField: foreignTableAlias = '$foreignTableAlias', plainFTAlias = '$plainFTAlias'\n";

        //foreign field ('&' removed)
        $t_ModuleFields = GetModulefields($this->foreignTable);
        $t_mf = $t_ModuleFields[$this->foreignField];

        //local key field
        $local_ModuleFields = GetModulefields($this->moduleID);
        if(isset($local_ModuleFields[$this->localKey])){
            $t_key = $local_ModuleFields[$this->localKey];
        } else {
            print "looked for localKey {$this->moduleID}.{$this->localKey}\n";
            
            global $foreignModules;
            print_r($foreignModules);
            
            die("localKey {$this->moduleID}.{$this->localKey} not found in local moduleFields");
        }
        //if there's a problem finding the key field
        if (!is_object($t_key)){
            print "ForeignField: this:\n";
            print_r ($this);
            die( "ForeignField: .....localKey = ". $this->localKey."\nlocalKey is empty\n");
        }
        
        
        if(empty($pModuleID)){
            global $SQLBaseModuleID;
            $localAlias = $SQLBaseModuleID; //suppose we're at the base module
        } else {
            $localAlias = $pModuleID;
        }
            
        print "ForeignField {$this->name}: localAlias = $localAlias\n";
        
        
        
        if(strtolower(get_class($t_key)) == 'ForeignField' && $t_key->getForeignFieldType() == 'remotefield'){
            print "iii. we avoid making a local join since the key does not point to a TableField.\n";
            print "iii. localAlias: $localAlias\n";
        
            //print "ForeignField: the key field is a RemoteField\n";
            //$joins = $t_key->makeJoinDef($pModuleID, $pDFF, $this, 'key');
            //print_r($joins);
            
            
            
            //get the RemoteField
            $t_rmfs = GetModuleFields($t_key->foreignTable);
            $t_rmf = $t_rmfs[$t_key->foreignField];
            $remoteTblAlias = GetTableAlias($t_rmf, $localAlias);

            
            //add the join towards the RemoteField...
            $t_join = "LEFT OUTER JOIN `{$this->foreignTable}` AS {$foreignTableAlias} \n";
            $t_join .= "ON ({$remoteTblAlias}.{$t_rmf->remoteField} = {$foreignTableAlias}.{$this->foreignKey} )";
                        
            $joins = array_merge($joins, $t_rmf->makeJoinDef($localAlias));
            $joins[$foreignTableAlias] = $t_join;
            
        } else { 
            print "iv. key type: ".strtolower(get_class($t_key))."\n";
        
            //build join statement:
            if ($this->joinType == "left"){
                //list condition need to be attached in join statement for MySQL
                $t_join = "LEFT OUTER JOIN `{$this->foreignTable}` AS {$foreignTableAlias} ";
            } else {
                $t_join = "INNER JOIN `{$this->foreignTable}` AS {$foreignTableAlias} ";
            }
                    
            $qKeyName = $t_key->getQualifiedName($localAlias);
            print "ForeignField-makeJoinDef(): key qualified name = $qKeyName\n";
            
            $t_join .= "\n   ON (".$qKeyName." = {$foreignTableAlias}.{$this->foreignKey} ";
            
            
            if ($this->listCondition != ''){
                $t_join .= "\n     AND {$foreignTableAlias}.{$this->listCondition})";
            } else {
                $t_join .= ")";
            }
            
            print "ForeignField-makeExtendedJoinDef(): join is:\n $t_join\n";
            $joins[$foreignTableAlias] = $t_join;
        

            //if the localKey is not a TableField, create its joinDef
            switch(strtolower(get_class($t_key))){
            case 'tablefield':
                
                break;
            case 'remotefield':
    print "ForeignField-makeExtendedJoinDef(): Key is RemoteField: DFF=$pDFF";
                //$joins = array_merge($t_key->makeJoinDef($t_alias, $pDFF, $this, 'key'), $joins);
                $joins = array_merge($t_key->makeJoinDef($localAlias), $joins);
        
            
                break;
            case 'foreignfield':
    print "ForeignField-makeExtendedJoinDef(): Key is ForeignField: \n";
   
                $joins = array_merge($t_key->makeJoinDef($localAlias), $joins);
                
                break;
            default:
                print "ForeignField: Auto-join not implemented: ". get_class($t_key) ." as key of a ForeignField\n";
                print "ForeignField: This is OK if the referenced field is also part of the selected fields\n\n";
                die("end");
            }
        }


        
        if(!is_object($t_mf)){
            print_r($t_ModuleFields);
        //  print_r($this);
            print "\n$t_mf\n";
            die("mf is empty");
        }
        
        //if the foreign (pointed to) field is not a TableField, create its joinDef
        if(strtolower(get_class($t_mf)) != 'tablefield'){
            //$t_alias = $t_mf->getTableAlias($pModuleID);
            $t_alias = $plainFTAlias; //$this->foreignTableAlias;
            
            
            print("***{$this->name}*********** alias = $t_alias \n\n");
            //$joins = $t_mf->makeJoinDef($pModuleID, &$t_ModuleFields);
            
            print "ForeignField-makeExtendedJoinDef({$this->name}): calling makeJoinDef for foreignField ".get_class($t_mf)." {$t_mf->name}\n";
            
            //the foreign field's joinDef should generally be preceded by the previous joins
            if($pRole == 'field'){
                $t_Role = 'field';
            } else {
                $t_Role = 'key';
            }
            $joins = array_merge($joins, $t_mf->makeJoinDef($t_alias));
            
        } else {
            print "ForeignField-makeExtendedJoinDef(): {$this->name}->foreignField is a TableField\n";
        }

        print "ForeignField-makeExtendedJoinDef(): {$this->name} <begin joins> created the following joins: \n";
        print_r( $joins );
        print "ForeignField-makeExtendedJoinDef(): {$this->name} <end joins>\n";
        
       // if($this->useJoinFieldStack) PopJoinFieldStack(&$this);
        
        print "<<< ===-===-===-===-===-===-===-===-===-===-===-===-===-===-=== <<<\n";
        print "\nForeignField-makeExtendedJoinDef(): {$this->name} (end)\n";
        print "<<< ===-===-===-===-===-===-===-===-===-===-===-===-===-===-=== <<<\n";
        return $joins;
    }


function getDependentFields(){
    $deps = array(
        array(
            'moduleID' => $this->moduleID,
            'name' => $this->localKey
            ),
        array(
            'moduleID' => $this->foreignTable,
            'name' => $this->foreignKey
            ),
        array(
            'moduleID' => $this->foreignTable,
            'name' => $this->foreignField
            )
    );

    return $deps;
}


    function makeRDCCallerDef(){
        print "ForeignField-makeRDCCallerDef() {$this->moduleID}.{$this->name}: \n";
        $t_module = GetModule($this->moduleID);
        $pkField = end($t_module->PKFields); //picks "most local" PK field
        
        //creates an element object of the "reverse" foreignfield of this one.
        /*$attributes = array(
            'name' => $this->foreignField, //$pkField, 
            'localTable' => $this->foreignTable,
            'key' => $this->foreignKey,
            'foreignTable' => $this->moduleID,
            'foreignField' => $pkField,
            'foreignKey' => $this->localKey, //make sure this does not point to a FF????????? Or, support it?
            'joinType' => 'inner',
            'triggerCondition' => $this->listCondition,
            'madeby' => "{$this->moduleID}.{$this->name} (foreignfield)"
        );*/
        
        //"un-flipped" version
        $attributes = array(
            'name' => $pkField, 
            'localTable' => $this->moduleID,
            'key' => $this->localKey,
            'foreignTable' => $this->foreignTable,
            'foreignField' => $this->foreignField,
            'foreignKey' => $this->foreignKey,
            'joinType' => 'inner',
            'triggerCondition' => $this->listCondition,
            'madeby' => "{$this->moduleID}.{$this->name} (foreignfield)"
        );
        return new Element($this->foreignField, 'ForeignField', $attributes);
    }

    function getListSQL(){
        $SQL = "SELECT {$this->foreignKey} AS ID, {$this->foreignField} AS Name \n";
        $SQL .= "FROM {$this->foreignTable} \n";
        if (!empty($this->listCondition)){
            $SQL .= "WHERE {$this->listCondition} \n";
        }
        $SQL .= "ORDER BY {$this->foreignField}; \n";


        return $SQL;
    }

function getLocalModuleID()
{
    return $this->localTable;
}

function getLocalKey()
{
    return $this->localKey;
}

function getForeignModuleID()
{
    return $this->foreignTable;
}

function getForeignKey()
{
    return $this->foreignKey;
}

function getTarget()
{
    return $this->foreignField;
}

    function getCondition($foreignAlias){
        if(!empty($foreignAlias)){
            return "`$foreignAlias`.{$this->listCondition}";
        } else {
            return $this->listCondition;
        }
    }

    function getTableAliasKey($parentAlias)
    {
        global $SQLBaseModuleID;
        $localModuleID = $this->moduleID;

        if(!empty($parentAlias)){
            $localModuleAlias = $parentAlias;
        } else {
            $localModuleAlias = $localModuleID;
        }


        //$condition is used by CodeField
        $condition = $this->listCondition;

        return
            $localModuleAlias.'.'.$this->localKey.'|'.
            $this->foreignTable.'.'.$this->foreignKey.'|'.
            $condition;
    }

/**
 * returns the data type
 *
 * Preferrably not called at run-time.
 */
function getDataType()
{
    if(empty($this->dataType)){
        $foreignField = GetModuleField($this->foreignTable, $this->foreignField);
        return $foreignField->getDataType();
    } else {
        return $this->dataType;
    }
}
} //end class ForeignField







class CodeField extends ForeignField
{
var $codeTypeID;
var $sampleItems = array();
var $displayValueField = false;

function &Factory($element, $moduleID)
{
    $field =& new CodeField($element, $moduleID);
    return $field;
}

function CodeField(&$element, $moduleID)
{
    $localTable = $element->getAttr('localTable');
    if(empty($localTable)){
        $localTable = $moduleID;
    }

    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type');
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->localTable = $localTable;
    $this->localKey = $element->getAttr('key');
    $this->foreignTable = 'cod';
    $this->foreignKey = 'CodeID';

    if('yes' == strtolower($element->getAttr('displayValueField'))){
        $this->displayValueField = true;
    }
    if($this->displayValueField){
        $this->foreignField = 'Value';
    } else {
        $this->foreignField = 'Description'; //this should be based on current language
    }

    $this->joinType = 'left';
    $this->codeTypeID = $element->getAttr('codeTypeID', true);
    $this->listCondition = "CodeTypeID = '{$this->codeTypeID}'";
    $this->phrase = $element->getAttr('phrase');
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('ListCondition' == $sub_element->type){
                $conditionObj = $sub_element->createObject($moduleID);
                $this->listConditions[$conditionObj->name] = $conditionObj;
            }
        }
    }
}
} //end class CodeField






class CombinedField extends ModuleField
{
var $content = array();
var $dataType = 'text';
var $separator = '';


function &Factory($element, $moduleID)
{
    $field =& new CombinedField($element, $moduleID);
    return $field;
}


function CombinedField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase');
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;
    $this->separator = $element->getAttr('separator');

    $mfs = GetModuleFields($moduleID);

    //look through children of element object and append field refs and text data
    foreach($element->c as $contentItem){
        if(is_object($contentItem)){
            switch(strtolower(strtolower(get_class($contentItem)))){
            case 'element':
                if('CombinedFieldRef' == $contentItem->type){
                    if($contentItem->name == $this->name){
                        trigger_error("The CombinedField {$this->name} cannot include a reference to itself.", E_USER_ERROR);
                    }
                    if(!isset($mfs[$contentItem->name])){
                        trigger_error("The CombinedField {$this->name} contains an invalid CombinedFieldRef, named '{$contentItem->name}'.", E_USER_ERROR);
                    }

                    $fieldRef = $mfs[$contentItem->name];
                    $this->AddFieldRef($fieldRef, $contentItem->getAttr('prepend'), $contentItem->getAttr('append'));
                } else {
                    //pass on HTML elements, and whatever
                    $this->AddTextData($contentItem->getContent());
                }
                break;
            case 'characterdata':
                $this->AddTextData($contentItem->content);
                break;
            default:
                print_r($contentItem);
                die('CombinedField constructor: class type not handled');
            }
        } else {
            $this->AddTextData($contentItem);
        }
    }

}


function needsReGet()
{
    $needsReGet = false;
    foreach($this->content as $content){
        if (is_array($content)){
            $contentField = $content['field'];
            if($contentField->needsReGet()){
                $needsReGet = true;
            }
        }
    }
    return $needsReGet;
}


function AddFieldRef($pField, $pPrepend, $pAppend)
{

    $this->content[] = array(
        'field'   => $pField,
        'prepend' => $pPrepend,
        'append'  => $pAppend
    );

}


function AddTextData($pData)
{

    $this->content[] = $pData;

}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $debug_prefix = debug_indent("CombinedField-makeSelectDef() {$this->moduleID}.{$this->name}:");

    $translations = array('_' => ' ', ';' => ' |', '"' => '\"');

    if(strlen($this->separator) > 0){
        $use_nullif = false;
        $separator = strtr($this->separator, $translations);
        $concatFunction = "CONCAT_WS('$separator',";
    } else {
        $use_nullif = true;
        $concatFunction = 'CONCAT(';
    }

    $ar_selects = array();
    $staticContent = '';
    foreach($this->content as $content){
        if (is_array($content)){
            $contentField = $content['field'];
            if(!empty($content['prepend']) || !empty($content['append'])){
                $preContent = strtr("'{$content['prepend']}'", $translations).',';
                $appContent = ','.strtr("'{$content['append']}'", $translations);
                $def = 'CONCAT('.
                    $preContent.
                    $contentField->makeSelectDef($pModuleID, false, $localizeOutput).
                    $appContent.
                    ')';
            } else {
                $def = $contentField->makeSelectDef($pModuleID, false, $localizeOutput);
            }
            if($use_nullif){
                $ar_selects[] = 'IFNULL('.$def.',\'\')';
            } else {
                $ar_selects[] = $def;
            }
        } else {
            if(0 < strlen(trim($content))){
                //translate underscore into space, semicolon to space + pipe, quote to escaped quote
                $translatedContent = strtr("'$content'", $translations);
                $ar_selects[] = $translatedContent;
                $staticContent .= addslashes(str_replace('\'', '', $translatedContent));
            }
        }
    }

    print "$debug_prefix staticContent: '$staticContent'\n";

    $select = "NULLIF($concatFunction";
    $select .= join($ar_selects , ',');
    $select .= "),'$staticContent')";

    if($pIncludeFieldAlias){
        $select .= " AS {$this->name}";
    }

    print "$debug_prefix returns: '$select'\n";
    debug_unindent();
    return $select;
}


function makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("CombinedField-makeJoinDef() {$this->moduleID}.{$this->name}:");

    print "$debug_prefix pModuleID = $pModuleID\n";

    $joins = array();

    $modFields = GetModuleFields($this->moduleID);
    foreach($this->content as $content){
        if (is_array($content)){
            $contentField = $content['field'];

            print "$debug_prefix calls " . get_class($contentField) . ' ' .$contentField->name." (pModuleID = $pModuleID)\n\n";

            $joins = array_merge($contentField->makeJoinDef($pModuleID), $joins);
        }
    }

    //print "$debug_prefix Joins in CombinedField:\n";
    //indent_print_r($joins);

    //this is to make sure the chain of joins isn't broken      
    if(empty($joins[$pModuleID])){
        //print "$debug_prefix need to create a join def for $pModuleID\n";

        $moduleFields = GetModuleFields($this->moduleID);

        //this would not work if there's absolutely no tablefield in the module 
        foreach($moduleFields as $mf){
            if(strtolower(get_class($mf)) == 'tablefield'){
                break; //exit the foreach loop
            }
        }

        //print "$debug_prefix found a table field to create a connecting join: {$mf->name}\n";
        $addJoin = $mf->makeJoinDef($pModuleID);

        //print "$debug_prefix connecting join: {$mf->name}\n";
        //indent_print_r($addJoin);

        $joins = array_merge($addJoin, $joins);
        indent_print_r($joins);
    }

    debug_unindent();
    return $joins;
}


function getDependentFields()
{
    $deps = array();

    foreach($this->content as $content){
        if (is_array($content)){
            $contentField = $content['field'];

            $deps[] = array(
                'moduleID' => $contentField->moduleID,
                'name' => $contentField->name
            );
        }
    }

    return $deps;
}
} //end class CombinedField







class DynamicForeignField extends ModuleField
{
var $key;
var $moduleIDField;
var $foreignField;
var $condition;
var $joinType;
var $cacheTable;


function &Factory($element, $moduleID)
{
    $field =& new DynamicForeignField($element, $moduleID);
    return $field;
}


function DynamicForeignField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type');
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->key = $element->getAttr('key');
    $this->moduleIDField = $element->getAttr('moduleIDField');
    $this->foreignField = $element->getAttr('foreignField');
    $this->condition = $element->getAttr('condition');
    $this->joinType = $element->getAttr('joinType');
    $this->phrase = $element->getAttr('phrase');
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;

    $this->cacheTable = $element->getAttr('cacheTable');
    if(empty($this->cacheTable)){
        $this->cacheTable = 'rdc';
    }

    foreach($element->c as $contentItem){
        if(is_object($contentItem)){
            switch(strtolower(get_class($contentItem))){
            case 'element':
                //add ref'd module ID to the DynamicForeignField
                $this->addRelatedModuleRef(
                    $element->getAttr('moduleID')
                );
                break;
            default:
                print_r($contentItem);
                die('DynamicForeignField constructor: class type not handled');
            }
        } else {
            //ignore anything else
        }
    }

}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


//makeSelectDef
function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $tableAlias = GetTableAlias($this, $pModuleID);

    switch($this->foreignField){
    case 'RecordDescription':
        $fieldName = 'Value';
        break;
    case 'OwnerOrganizationID':
        $fieldName = 'OrganizationID';
        break;
    default:
        $fieldName = $this->foreignField;
    }

    $def = "`$tableAlias`.$fieldName";
    if($localizeOutput){
        $def = $this->prepareSelectWithDBFormat($def);
    }

    if($pIncludeFieldAlias){
        $def .= " AS {$this->name}";
    }
    return $def;
}


function makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("DFF-makeJoinDef() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix pModuleID = $pModuleID\n";

    $joins = array();
    $tableAlias = GetTableAlias($this, $pModuleID);

    //check that key and moduleID field are TableFields:
    $keyField = GetModuleField($this->moduleID, $this->key);
    print "$debug_prefix key {$this->key} is ".get_class($keyField)."\n";
    $moduleIDField = GetModuleField($this->moduleID, $this->moduleIDField);
    print "$debug_prefix moduleIDField {$this->key} is ".get_class($moduleIDField)."\n";

    //create joins to either field if not a TableField
    if('tablefield' != strtolower(get_class($keyField))){
        print "$debug_prefix getting join for keyField {$this->key}\n";
        $joins = array_merge($keyField->makeJoinDef($pModuleID), $joins);
    }
    if('tablefield' != strtolower(get_class($moduleIDField))){
        print "$debug_prefix getting join for moduleIDField {$this->moduleIDField}\n";
        $joins = array_merge($moduleIDField->makeJoinDef($pModuleID), $joins);

    }
    $keyQName = $keyField->getQualifiedName($pModuleID);
    $moduleIDFieldQName = $moduleIDField->getQualifiedName($pModuleID);

    if('tablefield' != strtolower(get_class($keyField))){
        $parentAlias = GetTableAlias($keyField, $pModuleID);
    } else {
        $parentAlias = $pModuleID;
    }
    $this->assignParentJoinAlias($tableAlias, $parentAlias);

    $joins[$tableAlias] = "LEFT OUTER JOIN `{$this->cacheTable}` AS $tableAlias ON ({$moduleIDFieldQName} = $tableAlias.ModuleID AND {$keyQName} =  $tableAlias.RecordID)";

    print "$debug_prefix Joins:\n";
    indent_print_r($joins);

    debug_unindent();
    return $joins;
}


function getForeignModuleID()
{
    return $this->cacheTable;
}


function getTableAliasKey($parentAlias)
{
    global $SQLBaseModuleID;
    $localModuleID = $this->moduleID;

    if(!empty($parentAlias)){
        $localModuleAlias = $parentAlias;
    } else {
        $localModuleAlias = $localModuleID;
    }

    $condition = "`{$this->cacheTable}`.ModuleID='{$this->moduleID}'";

    return
        $localModuleAlias.'.'.$this->key.'|'.
        '`'.$this->cacheTable.'`.RecordID|'.
        $condition;
}
} //end class DynamicForeignField




class RemoteField extends ModuleField
{
var $localTable;
var $localRecordIDField;
var $remoteModuleID;
var $remoteTableAlias;      //used by Search at run-time, set by parser
var $remoteModuleIDField;
var $remoteRecordIDField;   //field in remote that matches local record ID
var $remoteField;
var $remoteDescriptorField;
var $remoteDescriptor;
var $remoteFieldType;       //data type of the remote field
var $remotePKField;         //actual recordID field of remote module
var $validate;
var $defaultValue;          //to be passed on to ScreenFields that need them
var $orgListOptions;             //to be passed on to ScreenFields that need them
var $listConditions = array(); //to be passed on to ScreenFields that need them
var $conditionModuleID;     //overriding module id for the module id to be passed to the remote module
var $reversed;              //special "hack" for makeJoinDef


function &Factory($element, $moduleID)
{
    $field =& new RemoteField($element, $moduleID);
    return $field;
}


function RemoteField(&$element, $moduleID)
{
    $debug_prefix = debug_indent("Remotefield constructor $moduleID:");
//trace($element, $debug_prefix);
    $localTable = $element->getAttr('localTable');
    if(empty($localTable)){
        $localTable = $moduleID;
    }

    //don't validate if it contains the string 'noValidation'
    $validate = $element->getAttr('validate');
    if(false !== strpos($validate, 'noValidation')){
        $validate = '';
    }

    //when extending a module, it's important to get the correct field name...
    $conditionModuleID = $element->getAttr('conditionModuleID');
    if(empty($conditionModuleID)){
        $conditionModuleID = $moduleID;
    }
    $moduleInfo = GetModuleInfo($conditionModuleID);

    $local_pkField = $moduleInfo->getPKField();

    $t_remoteModuleFields = GetModuleFields($element->getAttr('remoteModuleID', true));
    $t_remoteModuleField = $t_remoteModuleFields[$element->getAttr('remoteField', true)];
    $remote_pkField = reset($t_remoteModuleFields);

    $this->name = $element->getAttr('name', true);
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->localTable = $localTable;
    $this->localRecordIDField = $local_pkField;
    $this->remoteModuleID = $element->getAttr('remoteModuleID', true);
    $this->remoteModuleIDField = $element->getAttr('remoteModuleIDField', true);
    $this->remoteRecordIDField = $element->getAttr('remoteRecordIDField', true);
    $this->remoteField = $element->getAttr('remoteField', true);
    $this->remoteDescriptorField = $element->getAttr('remoteDescriptorField');
    $this->remoteDescriptor = $element->getAttr('remoteDescriptor');
    $this->remoteFieldType = $t_remoteModuleField->dataType;
    $this->remotePKField = $remote_pkField->name;
    $this->phrase = $element->getAttr('phrase');
    $this->validate = $validate;
    $this->defaultValue = $element->getAttr('defaultValue');
    $this->moduleID = $moduleID;
    $this->orgListOptions = $element->getAttr('orgListOptions');
    if(empty($this->orgListOptions)){
        $this->orgListOptions = $t_remoteModuleField->orgListOptions;
    }
    $this->conditionModuleID = $conditionModuleID;
    $this->dataType = $element->getAttr('type');

    if(empty($this->validate)){
        switch($this->dataType){
        case 'date':
        case 'datetime':
        case 'time':
            $this->validate = 'dateFormat';
            break;
        default:
            break;
        }
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('ListCondition' == $sub_element->type){
                $conditionObj = $sub_element->createObject($moduleID);
                $this->listConditions[$conditionObj->name] = $conditionObj;
            }
        }
    }

    global $gUniqueRemoteFields;
    if(!is_array($gUniqueRemoteFields)){
        $gUniqueRemoteFields = array();
    }
    $uniqueKey = $this->moduleID.':'.$this->remoteModuleID.'.'.$this->remoteField.' '.$this->remoteDescriptorField.'='.$this->remoteDescriptor;
    $keyDescriptor = "{$this->moduleID}.{$this->name}";
    if(isset($gUniqueRemoteFields[$uniqueKey])){
        if($keyDescriptor != $gUniqueRemoteFields[$uniqueKey]){
            trigger_error("The RemoteField {$gUniqueRemoteFields[$uniqueKey]} has the same definition as $keyDescriptor.", E_USER_ERROR);
        }
    }
    $gUniqueRemoteFields[$uniqueKey] = $keyDescriptor;

    debug_unindent();
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $foreignAlias = GetTableAlias($this, $pModuleID);

    $remoteModuleField = GetModuleField($this->remoteModuleID, $this->remoteField);
    if('tablefield' != strtolower(get_class($remoteModuleField))){
        $def = $remoteModuleField->makeSelectDef($foreignAlias, false, $localizeOutput);
    } else {
        $def = "`{$foreignAlias}`.{$this->remoteField}";
        if($localizeOutput){
            $def = $this->prepareSelectWithDBFormat($def);
        }
    }

    if($pIncludeFieldAlias){
        $def .= " AS {$this->name}";
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("RemoteField-makeJoinDef() {$this->moduleID}.{$this->name}:");
    print "$debug_prefix pModuleID = $pModuleID\n";
    indent_print_r($this);

    $foreignAlias = GetTableAlias($this, $pModuleID);
    global $SQLBaseModuleID;
    print "$debug_prefix SQLBaseModuleID is $SQLBaseModuleID\n";
    print "$debug_prefix this->moduleID is {$this->moduleID}\n";

    //when to use the passed $pModuleID
    if($this->localTable == $pModuleID || preg_match("/{$this->localTable}[\d]/", $pModuleID)){
        $localTable = $pModuleID;
        print "$debug_prefix localTable - using pModuleID since {$this->localTable} matches $pModuleID\n";
    } else {
        $localTable = $this->localTable . '1'; //if needed, figure out how to determine this properly
        print "$debug_prefix localTable - using guessed value $localTable since '{$this->localTable}' does not match '$pModuleID'\n";
    }

    $joins = array();

    $remoteModuleField = GetModuleField($this->remoteModuleID, $this->remoteField);
    $joins = array_merge($joins, $remoteModuleField->makeJoinDef($foreignAlias));

    $localRecordIDField = GetModuleField($this->moduleID, $this->localRecordIDField);
    $qualifiedLocalRecordIDField = $localRecordIDField->getQualifiedName($pModuleID);
    $joins = array_merge($joins, $localRecordIDField->makeJoinDef($pModuleID));

    if($this->reversed){
        $t_join = "INNER JOIN `{$pModuleID}` AS {$localTable} ";
        $t_join .= "\n    ON (`{$foreignAlias}`.{$this->remoteRecordIDField} = $qualifiedLocalRecordIDField";
    } else {
        $t_join = "LEFT OUTER JOIN `{$this->remoteModuleID}` AS {$foreignAlias} ";
        $t_join .= "\n    ON ($qualifiedLocalRecordIDField = `{$foreignAlias}`.{$this->remoteRecordIDField} ";
    }
    $t_join .= "\n     AND `{$foreignAlias}`.{$this->remoteModuleIDField} = '{$this->conditionModuleID}'";
    $t_join .= "\n     AND `{$foreignAlias}`._Deleted = 0";

    if (!empty($this->remoteDescriptor)){
        $t_join .= "\n     AND `{$foreignAlias}`.{$this->remoteDescriptorField} = '{$this->remoteDescriptor}')";
    } else {
        $t_join .= ")";
    }

    $joins = array_merge(array($foreignAlias => $t_join), $joins);

    print "$debug_prefix {$this->name} <begin joins> created the following joins: \n";
    indent_print_r( $joins );
    print "$debug_prefix {$this->name} <end joins>\n";

    if('tablefield' != strtolower(get_class($localRecordIDField))){
        $parentAlias = GetTableAlias($localRecordIDField, $pModuleID);
    } else {
        $parentAlias = $pModuleID;
    }

    //$this->assignParentJoinAlias($foreignAlias, $localTable);
    $this->assignParentJoinAlias($foreignAlias, $pModuleID);

    debug_unindent();
    return $joins;
}


function getDependentFields(){
    $deps = array(
        array(
            'moduleID' => $this->remoteModuleID,
            'name' => $this->remoteModuleIDField
        ),
        array(
            'moduleID' => $this->remoteModuleID,
            'name' => $this->remoteRecordIDField
        ),
        array(
            'moduleID' => $this->remoteModuleID,
            'name' => $this->remoteField
        )
    );

    return $deps;
}


    function makeRDCCallerDef(){
        print "RemoteField-makeRDCCallerDef() {$this->moduleID}.{$this->name}: \n";
        
        $t_module = GetModule($this->moduleID);
        $pkField = end($t_module->PKFields); //picks "most local" PK field
        
        if($pkField == $this->localRecordIDField){
            return null;
        }
        
        //creates an element object of the "reverse" foreignfield of this one.
        /*$attributes = array(
            'name' => $this->remoteField, //$pkField,
            'localTable' => $this->remoteModuleID,
            'key' => $this->remoteRecordIDField,
            'foreignTable' => $this->moduleID,
            'foreignField' => $pkField,
            'foreignKey' => $this->localRecordIDField,
            'joinType' => 'inner',
            //'listCondition' => "{$this->remoteModuleIDField} = '{$this->moduleID}'",
            'triggerCondition' => "{$this->remoteModuleIDField} = '{$this->moduleID}'",
            'madeby' => "{$this->moduleID}.{$this->name} (remotefield)"
        );
        */

        //"un-flipped" version
        $attributes = array(
            'name' => $this->name,//$pkField,
            'localTable' => $this->moduleID,
            'key' => $this->localRecordIDField,
            'foreignTable' => $this->remoteModuleID,
            'foreignField' => $this->remoteField,
            'foreignKey' => $this->remoteRecordIDField,
            'joinType' => 'inner',
            'triggerCondition' => "{$this->remoteModuleIDField} = '{$this->moduleID}'", //?
            'madeby' => "{$this->moduleID}.{$this->name} (remotefield)"
        );
        
        return new Element($this->remoteField, 'ForeignField', $attributes);
    }


function getLocalModuleID()
{
    return $this->localTable;
}

function getLocalKey()
{
    return $this->localRecordIDField;
}

function getForeignModuleID()
{
    return $this->remoteModuleID;
}

function getForeignKey()
{
    return $this->remoteRecordIDField;
}

function getTarget()
{
    return $this->remoteField;
}

    function getCondition($foreignAlias){
        $condition = "`{$foreignAlias}`.{$this->remoteModuleIDField} = '{$this->localTable}'";
        if (!empty($this->remoteDescriptor)){
            $condition .= "\n     AND `{$foreignAlias}`.{$this->remoteDescriptorField} = '{$this->remoteDescriptor}'";
        }
        return $condition;
    }
    
    
    function getTableAliasKey($parentAlias)
    {
        global $SQLBaseModuleID;
        $localModuleID = $this->moduleID;

        if(!empty($parentAlias)){
            $localModuleAlias = $parentAlias;
        } else {
            $localModuleAlias = $localModuleID;
        }


        $condition = $this->getCondition($this->remoteModuleID);

        //minor beautfication: avoiding newlines
        $condition = str_replace("\n", '', $condition);

        return
            $localModuleAlias.'.'.$this->localRecordIDField.'|'.
            $this->remoteModuleID.'.'.$this->remoteRecordIDField.'|'.
            $condition;
    }

/**
 * returns the data type
 *
 * Preferrably not called at run-time.
 */
function getDataType()
{
    if(empty($this->dataType)){
        $remoteField = GetModuleField($this->remoteModuleID, $this->remoteField);
        return $remoteField->getDataType();
    } else {
        return $this->dataType;
    }
}
} //end class RemoteField




class LinkField extends ModuleField
{
var $moduleIDField;
var $recordIDField;
var $foreignModuleID;


function &Factory($element, $moduleID)
{
    $field =& new LinkField($element, $moduleID);
    return $field;
}


function LinkField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type');
    if(empty($this->dataType)){
        $this->dataType = 'varchar(128)';
    }
    $this->phrase = $element->getAttr('phrase');
    $this->moduleID = $moduleID;

    $this->moduleIDField = $element->getAttr('moduleIDField');
    $this->recordIDField = $element->getAttr('recordIDField');
    $this->foreignModuleID = $element->getAttr('foreignModuleID');

    if(empty($this->moduleIDField) && empty($this->foreignModuleID)){
        die("LinkField {$this->name} requires either a moduleIDField or a foreignModuleID");
    }
} //end constructor


function needsReGet()
{
    return true;
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    if(empty($this->moduleIDField)){
        $hasModuleIDField = false;
    } else {
        $hasModuleIDField = true;
    }
    if(empty($this->recordIDField)){
        $hasRecordIDField = false;
    } else {
        $hasRecordIDField = true;
    }

    if($hasModuleIDField){
        $moduleIDField = GetModuleField($this->moduleID, $this->moduleIDField);
    }
    if($hasRecordIDField){
        $recordIDField = GetModuleField($this->moduleID, $this->recordIDField);
    }

    $def = 'CONCAT(\'internal:';
    if($hasRecordIDField){
        $def .= 'view.php?mdl=';
    } else {
        $def .= 'list.php?mdl=';
    }
    $def .= '\',';

    if($hasModuleIDField){
        $def .= $moduleIDField->makeSelectDef($pModuleID, false, false);
    } else {
        $def .= "'{$this->foreignModuleID}'";
    }

    if($hasRecordIDField){
        $def .= ',\'&rid=\','.$recordIDField->makeSelectDef($pModuleID, false, false);
    }
    $def .= ')';

    if($pIncludeFieldAlias){
        $def .= ' AS '.$this->name;
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    $joins = array();

    if(!empty($this->moduleIDField)){
        $moduleIDField = GetModuleField($this->moduleID, $this->moduleIDField);
        $joins = array_merge($moduleIDField->makeJoinDef($pModuleID), $joins);
    }
    if(!empty($this->recordIDField)){
        $recordIDField = GetModuleField($this->moduleID, $this->recordIDField);
        $joins = array_merge($recordIDField->makeJoinDef($pModuleID), $joins);
    }

    return $joins;
}


function getDependentFields(){
    $deps = array();
    $deps[] = array(
        'moduleID' => $this->moduleID,
        'name' => $this->moduleIDField
    );

    if(!empty($this->recordIDField)){
        $deps[] = array(
            'moduleID' => $this->moduleID,
            'name' => $this->recordIDField
        );
    }

    return $deps;
}
} //end class LinkField




class CalculatedField extends ModuleField
{
var $calcFunction;
var $params = array();
var $paramTypes = array();


function &Factory($element, $moduleID)
{
    $field =& new CalculatedField($element, $moduleID);
    return $field;
}


function CalculatedField(&$element, $moduleID)
{
    $this->name          = $element->getAttr('name', true);
    $this->dataType      = $element->getAttr('type');
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->displayDecimals = $element->getAttr('displayDecimals');
    $this->calcFunction  = $element->getAttr('calcFunction', true);
    $this->phrase        = $element->getAttr('phrase');
    $this->moduleID      = $moduleID;

    $params = explode(' ', $element->getAttr('params'));

    foreach($params as $param){
        switch(substr($param, 0, 1)){
        case '#': //literal number
            $this->params[] = str_replace('#', '', $param); //exclude the #
            $this->paramTypes[] = 'l'; //literal
            break;
        case '+': //literal string
            $this->params[] = str_replace(array('+', '_'), array('', ' '), $param); //exclude the +
            $this->paramTypes[] = 's'; //literal string
            break;
        case '*': //variable
            $this->params[] = str_replace('*', '', $param); //exclude the *
            $this->paramTypes[] = 'v'; //variable
            break;
        default:
            $this->params[] = $param;
            $this->paramTypes[] = 'f'; //field
        }
    }
}


function needsReGet()
{
    return true;
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function checkParameters($minParams, $maxParams = null)
{
    if(count($this->params) < $minParams){
        trigger_error("CalculatedField {$this->name}: expects at least $minParams parameters, but found ".count($this->params). '.', E_USER_ERROR);
    }
    if(!is_null($maxParams)){
        if(count($this->params) > $maxParams){
            trigger_error("CalculatedField {$this->name}: expects no more than $maxParams parameters, but found ".count($this->params). '.', E_USER_ERROR);
        }
    }
}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $params_translated = array();

    $localModuleFields   = GetModuleFields($this->moduleID);

    foreach($this->params as $ix => $param){
        print "makeSelectDef: processing parameter $param\n";
        switch($this->paramTypes[$ix]){
        case 'f':

            //Inserts select defs of fields referenced. Note we pass false to the localizeOutput parameter in order to prevent date calculations to fail
            $params_translated[] = $localModuleFields[$param]->makeSelectDef($pModuleID, false, false);
            break;
        case 'v':
            $params_translated[] = "*{$param}*";
            break;
        case 'l':
            $params_translated[] = $param;
            break;
        case 's':
            $params_translated[] = '\''.addslashes($param) . '\'';
            break;
        default:
            //
        }
    }


    switch($this->calcFunction){
    case 'if':
        $this->checkParameters(3, 3);
        $content = "IF({$params_translated[0]}, {$params_translated[1]}, {$params_translated[2]})";
        break;
    case 'ifnull':
        $this->checkParameters(2, 2);
        $content = "IFNULL({$params_translated[0]}, {$params_translated[1]})";
        break;
    case 'isnull':
        $this->checkParameters(1, 1);
        $content = "ISNULL({$params_translated[0]})";
        break;
    case 'isnotnull':
        $this->checkParameters(1, 1);
        $content = "!ISNULL({$params_translated[0]})";
        break;
    case 'datediff':
        $this->checkParameters(2, 2);
        $content = "DATEDIFF({$params_translated[0]}, {$params_translated[1]})";
        break;
    case 'datediff_inclusive':  //used for date intervals where the last day should be counted whole
        $this->checkParameters(2, 2);
        $content = "DATEDIFF({$params_translated[0]}, {$params_translated[1]}) + 1";
        break;
    case 'datediff_year_month':  //returns number of years and months
        $str_years = gettext("years");
        $str_months = gettext("months");
        $this->checkParameters(2, 2);
        $content = "CONCAT(FLOOR(DATEDIFF({$params_translated[0]}, {$params_translated[1]})/365.24), ' $str_years, ', ROUND(MOD(DATEDIFF({$params_translated[0]}, {$params_translated[1]}), 365.24)/30.44), ' $str_months')";
        break;
    case 'datediff_day_hour':  //returns number of years and months
        $str_days = gettext("days");
        $str_hours = gettext("hours");
        $this->checkParameters(2, 2);
        //days: floor((unix_timestamp(now())-unix_timestamp('2006-03-31'))/(86400))
        //hours: round(mod((unix_timestamp(now())-unix_timestamp('2006-03-31')),86400)/3600)

        $content = "CONCAT(FLOOR((UNIX_TIMESTAMP({$params_translated[0]})-UNIX_TIMESTAMP({$params_translated[1]}))/86400), ' $str_days, ', ROUND(MOD((UNIX_TIMESTAMP({$params_translated[0]})-UNIX_TIMESTAMP({$params_translated[1]})),86400)/3600), ' $str_hours')";
        break;
    case 'daysremaining':
        $this->checkParameters(1, 1);
        $content = "DATEDIFF({$params_translated[0]}, NOW())";
        break;
    case 'daysremaining_not_negative':
        $this->checkParameters(1, 1);
        $content = "CASE WHEN DATEDIFF({$params_translated[0]}, NOW()) > 0 THEN DATEDIFF({$params_translated[0]}, NOW()) ELSE 0 END";
        break;
    case 'dateadd':
        $this->checkParameters(3, 3);

        //hard coded IDs to uts - YUCK!
        $content = "CASE {$params_translated[1]} 
            WHEN 48 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} SECOND)
            WHEN 5 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} MINUTE)
            WHEN 19 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} HOUR)
            WHEN 53 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} DAY)
            WHEN 54 THEN 
                DATE_ADD({$params_translated[0]}, INTERVAL (7 * {$params_translated[2]}) DAY)
            WHEN 55 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} MONTH)
            WHEN 56 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL (3 * {$params_translated[2]}) MONTH)
            WHEN 57 THEN
                DATE_ADD({$params_translated[0]}, INTERVAL {$params_translated[2]} YEAR)
            ELSE
                NULL
            END";
        break;
    case 'extractdate':
        $this->checkParameters(1, 1);
        $content = "DATE({$params_translated[0]})";
        break;
    case 'extracttime':
        $this->checkParameters(1, 1);
        $content = "TIME({$params_translated[0]})";
        break;
    case 'timediff':
        $this->checkParameters(2, 2);
        $content = "TIMEDIFF({$params_translated[0]}, {$params_translated[1]})";
        break;
    case 'timeremaining':
        $this->checkParameters(1, 1);
        $content = "TIMEDIFF({$params_translated[0]}, NOW())";
        break;
    case 'filesize':
        $this->checkParameters(1, 1);
        $str_bytes = gettext("bytes");
        $str_kilobytes = gettext("kilobytes");
        $str_megabytes = gettext("megabytes");
        $content = "CASE 
        WHEN {$params_translated[0]} >= 1048576 THEN CONCAT(ROUND(({$params_translated[0]}/1048576), 1), ' $str_megabytes')
        WHEN {$params_translated[0]} >= 1024 THEN CONCAT(ROUND(({$params_translated[0]}/1024), 1), ' $str_kilobytes')
        ELSE CONCAT({$params_translated[0]}, ' $str_bytes') END";
        break;
    case 'add':
        $this->checkParameters(2, 2);
        $content = "IFNULL({$params_translated[0]}, 0) + IFNULL({$params_translated[1]}, 0)";
        break;
    case 'sum':
        $this->checkParameters(2); //two or more parameters, no upper limit
        $params_treated = array();
        foreach($params_translated as $paramID => $param){
            $params_treated[$paramID] = "IFNULL({$param}, 0)";
        }
        $content = join(' + ', $params_treated);
        break;
    case 'subtract':
        $this->checkParameters(2, 2);
        $content = "IFNULL({$params_translated[0]},0) - IFNULL({$params_translated[1]},0)";
        break;
    case 'multiply':
        $this->checkParameters(2, 2);
        $content = "IFNULL({$params_translated[0]},0) * IFNULL({$params_translated[1]},0)";
        break;
    case 'divide':
        $this->checkParameters(2, 2);
        $content = "IFNULL({$params_translated[0]},0) / {$params_translated[1]}"; //no need to add IFNULL on divisor: division by null *should* result in null
        break;
    case 'duedateformat':
        $this->checkParameters(1, 1);
        $content = "CASE WHEN (IFNULL({$params_translated[0]},0) < 0) THEN 'od' ELSE '' END";
        break;
    case 'is_recent':
        $this->checkParameters(2, 2);
        $content = "CASE WHEN {$params_translated[0]} > '{$params_translated[1]}' THEN 1 ELSE 0 END";
        break;
    case 'is_equal':
        $this->checkParameters(2, 2);
        $content = "{$params_translated[0]} = {$params_translated[1]}";
        break;
    case 'is_greater_than':
        $this->checkParameters(2, 2);
        $content = "{$params_translated[0]} > {$params_translated[1]}";
        break;
    case 'is_less_than':
        $this->checkParameters(2, 2);
        $content = "{$params_translated[0]} < {$params_translated[1]}";
        break;
    case 'max':
        $this->checkParameters(2, 2);
        $content = "IF({$params_translated[0]} > {$params_translated[1]}, {$params_translated[0]}, {$params_translated[1]})";
        break;
    case 'greatest':
    case 'least':
        $this->checkParameters(2); //two or more parameters, no upper limit
        $params_treated = array();
        foreach($params_translated as $paramID => $param){
            $params_treated[$paramID] = "IFNULL({$param}, 0)";
        }
        if('greatest' == $this->calcFunction){
            $content = 'GREATEST(' . join(', ', $params_treated).')';
        } else {
            $content = 'LEAST(' . join(', ', $params_treated).')';
        }
        break;
    default:
        trigger_error("CalculatedField {$this->name}: calcFunction '{$this->calcFunction}' not supported\n", E_USER_ERROR);
    }

    //this expects a numeric data type, obviously
    if('' != $this->displayDecimals){
        $content = 'ROUND(/**/'.$content.', '.$this->displayDecimals.')';
    }

    if($localizeOutput){
        $content = $this->prepareSelectWithDBFormat($content);
    }

    if($pIncludeFieldAlias){
        $content .= " AS {$this->name}";
    }

    return $content;
}


function makeJoinDef($pModuleID)
{
    print "CalculatedField-makeJoinDef {$this->name}\n";
    $joins = array();

    $localModuleFields   = GetModuleFields($this->moduleID);

    foreach($this->params as $ix => $param){
        switch($this->paramTypes[$ix]){
        case 'f':
            //insert select defs of fields referenced
            $joins = array_merge($joins, $localModuleFields[$param]->makeJoinDef($pModuleID)); //pass on calling field status
            break;
        case 'l':
            //do nothing
            break;
        default:
            //do nothing
        }
    }

    return $joins;
}


function getDependentFields()
{
    $deps = array();

    foreach($this->params as $ix => $param){
        if('f' == $this->paramTypes[$ix]){
            $deps[] = array(
                'moduleID' => $this->moduleID,
                'name' => $param
            );
        }
    }

    return $deps;
}
} //end class CalculatedField




class SummaryField extends ModuleField
{
var $summaryFunction;
var $summaryField;
var $summaryKey;
var $summaryModuleID;
var $summaryCondition;
var $summaryRankField; //only used by summaryFunction latest_id
var $localKey;
var $conditions; //copied from submodule
var $isGlobal = false;


function &Factory($element, $moduleID)
{
    $field =& new SummaryField($element, $moduleID);
    return $field;
}


function SummaryField(&$element, $moduleID)
{
    $this->name             = $element->getAttr('name', true);
    $this->dataType         = $element->getAttr('type');
    $this->displayFormat    = $element->getAttr('displayFormat');
    $this->phrase           = $element->getAttr('phrase');
    $this->moduleID         = $moduleID;

    $this->summaryFunction  = $element->getAttr('summaryFunction', true);
    $this->summaryField     = $element->getAttr('summaryField', true);
    $this->summaryKey       = $element->getAttr('summaryKey', true);
    $this->summaryModuleID  = $element->getAttr('summaryModuleID', true);
    $this->summaryCondition = $element->getAttr('summaryCondition');
    $this->summaryRankField = $element->getAttr('summaryRankField');

    $this->localKey         = $element->getAttr('localKey');
    if('yes' == strtolower($element->getAttr('isGlobal'))){
        $this->isGlobal         = true;
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Condition' == $sub_element->type){
                $this->addCondition(
                    $sub_element->getAttr('field', true),
                    $sub_element->getAttr('value'),
                    $sub_element->getAttr('operator')
                );
            }
        }
    }

}


function needsReGet()
{
    return true;
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $tableAlias = GetTableAlias($this, $pModuleID);
    $def = "`$tableAlias`.{$this->name}";

    if($localizeOutput){
        $def = $this->prepareSelectWithDBFormat($def);
    }

    if($pIncludeFieldAlias){
        $def .= " AS {$this->name}";
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    $debug_prefix = debug_indent("SummaryField-makeJoinDef {$this->moduleID}.{$this->name}:");
    print "$debug_prefix localKey = {$this->localKey}\n";

    global $SQLBaseModuleID;
    $backup_SQLBaseModuleID = $SQLBaseModuleID;
    $joins = array();

    $tableAlias = GetTableAlias($this, $pModuleID);

    $localKeyMF = GetModuleField($this->moduleID, $this->localKey);
    $localKeyQualified = $localKeyMF->getQualifiedName($pModuleID);
    if('tablefield' != strtolower(get_class($localKeyMF))){
        $parentAlias = GetTableAlias($localKeyMF, $pModuleID);
        $joins = array_merge($joins, $localKeyMF->makeJoinDef($pModuleID));
    } else {
        $parentAlias = $pModuleID;
    }
    $this->assignParentJoinAlias($tableAlias, $parentAlias);

    print "$debug_prefix localKeyQualified = {$localKeyQualified}\n";
    print "$debug_prefix parentAlias = {$parentAlias}\n";

    $summaryModuleFields = GetModuleFields($this->summaryModuleID);

    $standardSummary = true;

    //list of fields to be combined
    static $selectFields = array();
    $selectFields[$tableAlias][$this->name] = $this->summaryField;

    print "$debug_prefix selectFields array:\n";
    print_r($selectFields);

    $summarySelectDefs = array();
    $summaryJoinDefs = array();

    switch($this->summaryFunction){
    case 'lowest_ranked':
    case 'highest_ranked':
        $conditionTableAlias = "{$tableAlias}_sub2";
        if(empty($this->summaryRankField)){
            trigger_error("SummaryField {$this->name} uses summaryFunction {$this->summaryFunction} and therefore requires a summaryRankField.", E_USER_ERROR);
        }
        $summaryRankModuleField = $summaryModuleFields[$this->summaryRankField];
        $summaryRankSelect = $summaryRankModuleField->makeSelectDef($conditionTableAlias, false, false);
        if('tablefield' != strtolower(get_class($summaryRankModuleField))){
            $summaryJoinDefs = array_merge($summaryJoinDefs, $summaryRankModuleField->makeJoinDef($conditionTableAlias));
        }
        break;
    default:
        $conditionTableAlias = $this->summaryModuleID;
    }

    //temporary change this to make the subquery
    $SQLBaseModuleID = $this->summaryModuleID;

    $summaryConditionSQL = '';
    if(strlen($this->summaryCondition) > 0){
        $summaryConditionSQL .= " AND `$conditionTableAlias`.{$this->summaryCondition} \n";
    }
    $fieldValueConditions = array(); //necessary because conditions against parent module fields (joins) need to go outside the subquery
    if(count($this->conditions) > 0){
        foreach($this->conditions as $conditionField => $arConditionValue){

//trace($arConditionValue, "conditionValue of {$this->name}");
            if(is_array($arConditionValue)){
                switch($arConditionValue[1]){
                case 'greater_than':
                    $operator = '>';
                    break;
                case 'less_than':
                    $operator = '<';
                    break;
                case 'equals':
                default:
                    $operator = '=';
                    break;
                }
                $rawConditionValue = $arConditionValue[0];
            } else {
                $operator = '=';
                $rawConditionValue = $arConditionValue;
            }

            $innerCondition = true; //whether to apply the condition inside the subquery or outside
            switch(substr($rawConditionValue, 0, 1)){
            case '#': //literal number
                $conditionValue = str_replace('#', '', $rawConditionValue); //exclude the #
                break;
            case '+': //literal string - MySQL function call
                $conditionValue = str_replace(array('+', '_'), array('', ' '), $rawConditionValue);
                break;
            case '[': //parent record field reference
                $innerCondition = false;
                $conditionValueFieldName = str_replace(array('[', '*', ']'), '', $rawConditionValue);

                //just to avoid duplication in the parent join
                if(!($conditionField == $this->localKey && $conditionValueFieldName == $this->summaryKey)){
                    $selectFields[$tableAlias][$conditionValueFieldName] = $conditionValueFieldName;
                    $fieldValueConditions[$conditionField] = array($operator, $conditionValueFieldName);
                }
                break;
            default:
                $conditionValue = "'$rawConditionValue'";
            }

            if($innerCondition){
                $conditionModuleField = $summaryModuleFields[$conditionField];
                $conditionFieldQualified = $conditionModuleField->getQualifiedName($conditionTableAlias);
                $summaryConditionSQL .= " AND $conditionFieldQualified $operator $conditionValue\n";

                if('tablefield' != strtolower(get_class($conditionModuleField))){
                    $summarySelectDefs[$conditionModuleField->name] = $conditionModuleField->makeSelectDef($conditionTableAlias, false, false);
                    $summaryJoinDefs = array_merge($summaryJoinDefs, $conditionModuleField->makeJoinDef($conditionTableAlias));
                }
            }
        }
    }


    switch($this->summaryFunction){
    case 'average':
        $function = "AVG(%s)";
        break;
    case 'count':
        $function = "COUNT(%s)";
        break;
    case 'sum':
        $function = "SUM(%s)";
        break;
    case 'min':
        $function = "MIN(%s)";
        break;
    case 'max':
        $function = "MAX(%s)";
        break;
    case 'list':
        $function = 'GROUP_CONCAT(%1$s ORDER BY %1$s SEPARATOR \', \')';
        break;
    case 'lowest_ranked':
        $rank_order = 'ASC';
    case 'highest_ranked':
        if(!isset($rank_order)){
            $rank_order = 'DESC';
        }
        $standardSummary = false;
        $strSummaryJoinDefs = join("\n", $summaryJoinDefs);

        $join = "LEFT OUTER JOIN (SELECT
   `{$tableAlias}_sub1`.{$this->summaryField} AS {$this->name},
   `{$tableAlias}_sub1`.{$this->summaryKey}
FROM
   `{$this->summaryModuleID}` AS `{$tableAlias}_sub1`
WHERE
    `{$tableAlias}_sub1`._Deleted = 0
   AND `{$tableAlias}_sub1`.{$this->summaryField} = (
      SELECT `{$tableAlias}_sub2`.{$this->summaryField}
      FROM `{$this->summaryModuleID}` AS `{$tableAlias}_sub2`
        $strSummaryJoinDefs
      WHERE `{$tableAlias}_sub2`._Deleted = 0
        $summaryConditionSQL
        AND `{$tableAlias}_sub2`.{$this->summaryKey} = `{$tableAlias}_sub1`.{$this->summaryKey}
      ORDER BY $summaryRankSelect $rank_order
      limit 1
   )) AS `$tableAlias` ON
        $localKeyQualified = $tableAlias.{$this->summaryKey}\n";

        break;
    case 'lco_latest_id':
        $standardSummary = false;

        $join = "LEFT OUTER JOIN (SELECT
   `lco_sub1`.LossCostID AS {$this->name},
   `lco_sub1`.ClaimID
FROM 
   `lco` AS `lco_sub1`
WHERE
    `lco_sub1`._Deleted = 0
   AND `lco_sub1`.LossCostID = (
      select `lco_sub2`.LossCostID
      from `lco` AS `lco_sub2`
      where `lco_sub2`._Deleted = 0
        and `lco_sub2`.ClaimID = `lco_sub1`.ClaimID
      order by `lco_sub2`.ValuationDate DESC
      limit 1
   )) AS `$tableAlias` ON 
        $localKeyQualified = $tableAlias.{$this->summaryKey}\n";
        break;
    case 'cos_rollup_sum': //summarize cos
        $standardSummary = false;

    $join = "LEFT OUTER JOIN (SELECT
   SUM(`cos_r`.{$this->summaryField}) AS {$this->name},
   `smc`.ModuleID,
   `smc`.RecordID
FROM 
   `smc`
    INNER JOIN `cos` AS cos_r
    ON `smc`.SubModuleID = `cos_r`.RelatedModuleID
    AND `smc`.SubRecordID = `cos_r`.RelatedRecordID
WHERE
    `cos_r`._Deleted = 0
GROUP BY `smc`.ModuleID, `smc`.RecordID
   ) AS `$tableAlias` ON 
    $tableAlias.{$this->summaryKey} = $localKeyQualified
    AND $tableAlias.{$this->summaryCondition}
\n";

        break;
    default:
        die("SummaryField {$this->name}: summaryFunction '{$this->summaryFunction}' not supported\n");
    }

    if($standardSummary){
        $summaryKeyModuleField = $summaryModuleFields[$this->summaryKey];
//trace($selectFields, 'selectFieldsstandardSummary');
        foreach($selectFields[$tableAlias] as $selectKey=>$selectField){
            $summaryModuleField = $summaryModuleFields[$selectField];
            if(empty($summaryModuleField)){
                die("SummaryField-makeJoinDef {$this->name}: Could not find a module field that matches the summaryField attribute ({$selectField})");
            }
            $summarySelectDefs[$selectKey] = $summaryModuleField->makeSelectDef($this->summaryModuleID, false, false);

            $summaryJoinDefs = array_merge(
                $summaryJoinDefs,
                $summaryModuleField->makeJoinDef($this->summaryModuleID)
            );
        }

        $summaryJoinDefs = array_merge(
            $summaryJoinDefs,
            $summaryKeyModuleField->makeJoinDef($this->summaryModuleID)
        );
        $summaryJoinDefs = SortJoins($summaryJoinDefs);


        $summaryKeyQualified = $summaryKeyModuleField->getQualifiedName($this->summaryModuleID);

        $subSQL = "SELECT \n";
        foreach($summarySelectDefs as $selectField => $summarySelectDef){
//$function needs to be separate for each summarySelectDef!!!
            $subSQL .= sprintf($function, $summarySelectDef) . " AS {$selectField},\n";
        }
        $subSQL .= "$summaryKeyQualified\nFROM `{$this->summaryModuleID}` \n";
        foreach($summaryJoinDefs as $def){
            $subSQL .= $def."\n";
        }
        $subSQL .= " WHERE `{$this->summaryModuleID}`._Deleted = 0 \n";
        $subSQL .= $summaryConditionSQL."\n";
        $subSQL .= "GROUP BY $summaryKeyQualified \n";

        //verify the statement is OK so far:
        if(defined('EXEC_STATE') && EXEC_STATE == 4){
            CheckSQL($subSQL);
        }
        //trace($subSQL, 'subSQL');

        $join = "LEFT OUTER JOIN ($subSQL) AS $tableAlias \n";
        $join .= "ON (\n$localKeyQualified = $tableAlias.{$this->summaryKey}";
//trace($fieldValueConditions, '$fieldValueConditions');
        if(count($fieldValueConditions)){
            $SQLBaseModuleID = $backup_SQLBaseModuleID;
            foreach($fieldValueConditions as $localFieldName => $arConditionValue){
                $localFieldMF = GetModuleField($this->moduleID, $localFieldName);
                $localFieldQualified = $localFieldMF->getQualifiedName($pModuleID);
                $join .= "\nAND $localFieldQualified {$arConditionValue[0]} $tableAlias.{$arConditionValue[1]}";
            }
        }
        $join .= "\n) ";
    }

    $joins[$tableAlias] = $join;

    //restore base moduleID
    $SQLBaseModuleID = $backup_SQLBaseModuleID;

    debug_unindent();
    return $joins;
}


//ought to return fields of submodule?
function getDependentFields()
{
    return array();
}


function getForeignModuleID()
{
    return $this->summaryModuleID;
}


function getTableAliasKey($parentAlias = null)
{
    global $SQLBaseModuleID;
    $localModuleID = $this->moduleID;

    if(!empty($parentAlias)){
        $localModuleAlias = $parentAlias;
    } else {
        $localModuleAlias = $localModuleID;
    }

    $strConditions = '';
    if(count($this->conditions) > 0){
        $strConditions = '+'.join('-',$this->conditions).'_'.join('-',array_keys($this->conditions));
    }

    $condition = $this->summaryFunction.'+'.$this->summaryCondition.$strConditions;
    //$condition = $this->summaryCondition.$strConditions; //summaryFunction not needed as soon as the SummaryFunction is better implemented in makeSelectDef.

    return
        $localModuleAlias.'.'.$this->localKey.'|'.
        $this->summaryModuleID.'.'.$this->summaryKey.'|'.
        $condition;
}


function addCondition($fieldName, $fieldValue, $operator = 'equals')
{
    $this->conditions[$fieldName] = array($fieldValue, $operator);
}

} //end class SummaryField



class RecordMetaField extends ModuleField
{
var $lookupType; //either 'created' or 'modified'
var $returnType; //either 'date' or 'userID'


function &Factory($element, $moduleID)
{
    $field =& new RecordMetaField($element, $moduleID);
    return $field;
}


function RecordMetaField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase');
    $this->moduleID = $moduleID;

    $lookupType = $element->getAttr('lookupType', true);
    if(empty($lookupType)){
        $lookupType = 'created';
    }
    if(in_array($lookupType, array('created', 'modified'))){
        $this->lookupType = $lookupType;
    } else {
        die("RecordMetaField lookupType unknown: $lookupType");
    }

    $returnType = $element->getAttr('returnType');
    if(empty($returnType)){
        $returnType = 'date';
    }
    if(in_array($returnType, array('date', 'userID'))){
        $this->returnType = $returnType;
    } else {
        die("RecordMetaField returnType unknown: $returnType");
    }

    $this->dataType = $element->getAttr('type');
    if(empty($this->dataType)){
        if('date' == $returnType){
            $this->dataType = 'datetime';
        } else {
            $this->dataType = 'int';
        }
    }
}


function needsReGet()
{
    return true;
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    switch($this->returnType){
    case 'userID':
        $type = 'By';
        break;
    case 'date':
        $type = 'Date';
        break;
    default:
        $type = 'By';
        break;
    }

    switch($this->lookupType){
    case 'created':
        $tableAlias = $this->moduleID . '_l';  //GetTableAlias($this, $pModuleID);  --- if we need it...
        $def = "`$tableAlias`.create{$type}";
        break;
    case 'modified':
        $def = "`$pModuleID`._Mod{$type}";
        break;
    default:
        trigger_error('RecordMetaField lookupType is not recognized.', E_USER_ERROR);
    }

    if($localizeOutput){
        $def = $this->prepareSelectWithDBFormat($def);
    }

    if($pIncludeFieldAlias){
        $def .= ' AS '. $this->name;
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    print "RecordMetaField-makeJoinDef {$this->name}";
    $joins = array();

    switch($this->lookupType){
    case 'created':
        $tableAlias = $this->moduleID . '_l'; //GetTableAlias($this, $pModuleID);  --- if we need it...

        $moduleFields = GetModuleFields($this->moduleID);
        $recordIDField = reset(array_keys($moduleFields));

        $this->assignParentJoinAlias($tableAlias, $pModuleID);

        $logTable = "`{$this->moduleID}_l`";
        $minTable = "`{$this->moduleID}_min`";
        $SQL = "LEFT OUTER JOIN (
            SELECT
                $logTable.{$recordIDField},
                $logTable._ModDate AS createDate,
                $logTable._ModBy AS createBy
            FROM $logTable
            INNER JOIN (
                SELECT
                    {$recordIDField},
                    MIN(_RecordID) AS MinRecordID
                FROM $logTable
                GROUP BY {$recordIDField}) as $minTable
            ON $logTable._RecordID = $minTable.MinRecordID
            ) AS $tableAlias
            ON (`{$pModuleID}`.{$recordIDField} = $tableAlias.{$recordIDField})";

        return array($tableAlias => $SQL);
        break;
    case 'modified':
        return array();
        break;
    default:
        die('RecordMetaField lookupType is not recognized.');
    }
}


function getDependentFields()
{
    return array();
}
} //end class RecordMetaField



//like a ForeignField but matches several values to one.
class RangeField extends ForeignField
{
var $localTable;
var $localKey;
var $foreignTable;
var $foreignTableAlias;
var $foreignKey;
var $foreignField;
var $listCondition;
var $joinType;


function &Factory($element, $moduleID)
{
    $field =& new RangeField($element, $moduleID);
    return $field;
}


function RangeField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type');
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->localKey = $element->getAttr('key');
    $this->foreignTable = $element->getAttr('foreignTable');
    $this->foreignKey = $element->getAttr('foreignKey');
    $this->foreignField = $element->getAttr('foreignField');
    $this->phrase = $element->getAttr('phrase');
    $this->moduleID = $moduleID;

    //sanity check to avoid infinite loops
    if($this->name == $this->localKey){
        die("RangeField '{$this->name}': Name and (local) Key can't be the same (error)");
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('ListCondition' == $sub_element->type){
                $conditionObj = $sub_element->createObject($moduleID);
                $this->listConditions[$conditionObj->name] = $conditionObj;
            }
        }
    }
}


// re-defined here because parent class ForeignField uses a special implementation
function getQualifiedName($pModuleID)
{
    return $this->makeSelectDef($pModuleID, false);
}


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    $foreignModuleFields = GetModuleFields($this->foreignTable);
    $foreignKey        = $foreignModuleFields[$this->foreignKey];
    $foreignKeyName    = $foreignKey->getQualifiedName($this->foreignTable);
    $localModuleFields = GetModuleFields($this->moduleID);
    $localKeyExpr      = $localModuleFields[$this->localKey]->makeSelectDef($pModuleID, false, false);

    $selectDef = $this->foreignField; //could use makeSelectDef on module field?
    if($localizeOutput){
        $selectDef = $this->prepareSelectWithDBFormat($selectDef);
    }
    $joins = $foreignKey->makeJoinDef($this->foreignTable);

    $str_wheres = '';
    if(count($this->listConditions) > 0){
        foreach($this->listConditions as $listCondition){
            $exprArray = $listCondition->getExpression($this->foreignTable);
            $joins = array_merge($exprArray['joins'], $joins);
            $str_wheres .= ' AND ' .$exprArray['expression'];
        }

        $pattern = '/\'\[\*(\w*)\*\]\'/';
        $matches = array();
        if(preg_match_all ( $pattern, $str_wheres, $matches)){
            trace($matches[1], 'PopulateValues');
            foreach($matches[1] as $fieldName){
                if(!isset($foreignModuleFields[$fieldName])){
                    trigger_error("RangeField {$this->moduleID}.{$this->name}: ListCondition field $fieldName not found in module '{$this->foreignTable}'.", E_USER_ERROR);
                }
                $str_wheres = str_replace(
                    '\'[*'.$fieldName.'*]\'',
                    $foreignModuleFields[$fieldName]->makeSelectDef($this->moduleID, false, false),
                    $str_wheres
                );
                $joins = array_merge($foreignModuleFields[$fieldName]->makeJoinDef($this->foreignTable), $joins);
            }
        }

    }

    $subSQL  = "SELECT $selectDef\n";
    $subSQL .= "FROM `{$this->foreignTable}`\n";
    if(count($joins) > 0){
        $joins = SortJoins($joins);
    }
    $subSQL .= join("\n", $joins)."\n";
    $subSQL .= "WHERE `{$this->foreignTable}`._Deleted = 0 $str_wheres AND {$foreignKeyName} <= $localKeyExpr\n";
    $subSQL .= "ORDER BY {$foreignKeyName} DESC\n";
    $subSQL .= "LIMIT 1";

    $def = '('.$subSQL.')';

    if($pIncludeFieldAlias){
        $def .= " AS {$this->name}";
    }

    return $def;
}


function makeJoinDef($pModuleID)
{
    print "RangeField-makeJoinDef {$this->name}\n";
    $localKeyJoins = array();

    $localKey = GetModuleField($this->moduleID, $this->localKey);
    $localKeyJoins = $localKey->makeJoinDef($pModuleID);

    return $localKeyJoins;
}


function getDependentFields()
{
    $deps = array(
        array(
            'moduleID' => $this->moduleID,
            'name' => $this->localKey
            ),
        array(
            'moduleID' => $this->foreignTable,
            'name' => $this->foreignKey
            ),
        array(
            'moduleID' => $this->foreignTable,
            'name' => $this->foreignField
            )
    );

    return $deps;
}


function getLocalModuleID()
{
    return $this->moduleID;
}
} //end class RangeField


/**
 *  A simple field that always contains the same value
 */
class StaticField extends ModuleField
{
//     var $name;
//     var $phrase;
//     var $moduleID;
//     var $dataType;
//     var $defaultValue; //default form value
//     var $displayFormat; //sptintf-type formatting string
var $content;

function &Factory($element, $moduleID)
{
    $field =& new StaticField($element, $moduleID);
    return $field;
}

//PHP 4 constructor
function StaticField(&$element, $moduleID)
{
    $this->__construct($element, $moduleID);
}


function __construct(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->dataType = $element->getAttr('type', true);
    $this->displayFormat = $element->getAttr('displayFormat');
    $this->phrase = $element->getAttr('phrase');
    $this->moduleID = $moduleID;
    include_once INCLUDE_PATH . '/web_util.php';
    $this->content = dbQuote($element->getAttr('content'), $this->dataType);
}


// function getQualifiedName($pModuleID)
// (uses inherited function from ModuleField)


function makeSelectDef($pModuleID, $pIncludeFieldAlias = true, $localizeOutput = true)
{
    if($pIncludeFieldAlias){
        return $this->prepareSelectWithDBFormat($this->content). " AS {$this->name}";
    } else {
        return $this->prepareSelectWithDBFormat($this->content);
    }
}


function makeJoinDef($pModuleID)
{
    return array();
}
}
?>