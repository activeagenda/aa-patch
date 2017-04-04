<?php
/**
 * Data View class
 *
 * This file contains the DataView class definition. The purpose of this
 * class is to generate SELECT SQL statements combining several modules.
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

class DataView
{
var $id;
var $name;
var $moduleID;
var $statement;

function &Factory($element, $moduleID)
{
    $view =& new DataView($element, $moduleID);
    return $view;
}

function DataView($element, $moduleID)
{
    $this->id = $element->attributes['ID'];
    $this->name = $element->attributes['name'];
    $this->moduleID = $moduleID;

    $subElement = reset($element->c);
    if(empty($subElement)){
        trigger_error("DataView must have a Statement or a Union sub-element)", E_USER_ERROR);
    }

    switch($subElement->type){
    case 'Union':
        $subElement->attributes['alias'] = $this->id;
    case 'Statement':
        $this->statement =& $subElement->createObject($this->moduleID);
        break;
    default:
        trigger_error("DataView must have a Statement or a Union as the first sub-element)", E_USER_ERROR);
        break;
    }
}


function generateListSQL($grid)
{
    return $this->statement->generateListSQL();
}

function generateListCountSQL($grid)
{
    return $this->statement->generateListCountSQL();
}



function getModuleFields()
{
    return $this->statement->getModuleFields();
}

}



class AbstractStatement
{

function generateListSQL()
{
    return ''; //override this
}
}



class Statement extends AbstractStatement
{
var $fields = array();
var $moduleRefs = array();
var $joins = array();
var $filters = array();
var $_rootModuleID; //first moduleRef

function &Factory(&$element, $moduleID)
{
    $statement =& new Statement($element, $moduleID);
    return $statement;
}

function Statement(&$element, $moduleID)
{
    foreach($element->c as $subElement){
        switch($subElement->type){
        case 'Field':
        case 'Value':
            $this->fields[$subElement->name] = $subElement->createObjectWithRef($moduleID, null, $this);
            break;
        case 'ModuleRef':
            if(empty($this->_rootModuleID)){
                $this->_rootModuleID = $subElement->attributes['moduleID'];
            }
            $this->moduleRefs[$subElement->attributes['alias']] = $subElement->createObjectWithRef($moduleID, null, $this);
            break;
        case 'FilterCondition':
            $this->filters[] = $subElement->createObjectWithRef($moduleID, null, $this);
            break;
        default:
            break;
        }
    }

    //print_r($element);
}

function generateListSQL()
{
    //based on fields, moduleRefs, joins
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->_rootModuleID;
    $selects = array();
    $joins = array();

    $debug_prefix = debug_indent("Statement-generateListSQL $SQLBaseModuleID:");

    foreach($this->fields as $field){
        $selects[] = $field->makeSelectDef();
        $joins = array_merge($joins, $field->makeJoinDef());
    }
    
    foreach($this->moduleRefs as $moduleAlias => $moduleRef){
        indent_print_r($moduleRef->joins, true, "moduleRef->joins $moduleAlias");
        if(count($moduleRef->joins) > 0){
            $joins = array_merge($joins, $moduleRef->joins);
        }
    }

    $joins = SortJoins($joins);

    $SQL = 'SELECT ';
    $SQL .= join(',',$selects);
    $SQL .= " FROM `{$this->_rootModuleID}` " . join(' ',$joins);
    $SQL .= " WHERE `{$this->_rootModuleID}`._Deleted = 0 ";
    if(count($this->filters) > 0){
        foreach($this->filters as $filter){
            $SQL .= " AND ";
            $SQL .= $filter->getCondition();
        }
    }
    
    debug_unindent();
    return $SQL;
}

function generateListCountSQL()
{

    //based on fields, moduleRefs, joins
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->_rootModuleID;
    $joins = array();

    foreach($this->fields as $field){
        //$joins = array_merge($joins, $field->makeJoinDef());
        $joins = array_merge($joins, GetJoinDef($field->name, $SQLBaseModuleID));
    }
    
    foreach($this->moduleRefs as $moduleAlias => $moduleRef){
        //print_r($moduleRef->joins);
        if(count($moduleRef->joins) > 0){
            $joins = array_merge($joins, $moduleRef->joins);
        }
    }

    $joins = SortJoins($joins);

    $SQL = "SELECT COUNT(*)";
    $SQL .= " FROM `{$this->_rootModuleID}` " . join(' ',$joins);
    return $SQL;
}

function getModuleFields()
{
    $moduleFields = array();
    foreach($this->fields as $field){
        if('field' == strtolower(get_class($field))){
            $moduleFields[$field->name] = $field->getModuleField();
        } else {
            $moduleFields[$field->name] = $field; //for "value" fields, it doesn't matter that it's not a real modulefield
        }
    }
    return $moduleFields;
}

}



class Union extends AbstractStatement
{
var $statements = array();
var $alias;

function &Factory($element, $moduleID)
{
    $union =& new Union($element, $moduleID);
    return $union;
}

function Union($element, $moduleID)
{
    if(count($element->c) < 2){
        trigger_error("Union needs at least two Statements", E_USER_ERROR);
    }
    foreach($element->c as $subElement){
        $this->statements[] = $subElement->createObject($moduleID);
    }

    $this->alias = $element->attributes['alias'];

    //verify that all statements have same number of fields
    $atFirst = true;
    foreach($this->statements as $statement){
        if($atFirst){
            $nFields = count($statement->fields);
            $atFirst = false;
        } else {
            if($nFields != count($statement->fields)){
                $nFieldsCurrent = count($statement->fields);
                trigger_error("Field count mismatch in UNION: First statement has $nFields fields, the current statement has $nFieldsCurrent fields.");
            }
        }
    }

    //set up fields
    $firstStatement = end($this->statements);
    foreach($firstStatement->fields as $stmtField){
        $unionField =& new UnionField($stmtField->name, $stmtField->getDataType());
        $this->fields[$stmtField->name] =& $unionField;
        unset($unionField);
    }
}

function generateListSQL()
{
    //based on the fields of contained Statements. All statements should have same number or fields, but the Union object will add dummy Value objects if a statement has fewer fields than another.
    $SQLs = array();
    foreach($this->statements as $statement){
        $SQLs[] = $statement->generateListSQL();
    }
    
    $SQL = 'SELECT ';
    $SQL .= join(', ', array_keys($this->fields));
    $SQL .= ' FROM ( ';
    $SQL .= join(' UNION ', $SQLs);
    $SQL .= ' ) AS ' . $this->alias;
    $SQL .= ' WHERE 1=1 ';
//print "UNION SQL: $SQL";
    return $SQL;
}

function generateListCountSQL()
{
    $SQLs = array();
    foreach($this->statements as $statement){
        $SQLs[] = $statement->generateListSQL();
    }
    
    $SQL = 'SELECT COUNT(*)';
    $SQL .= ' FROM ( ';
    $SQL .= join(' UNION ', $SQLs);
    $SQL .= ' ) AS ' . $this->alias;
    $SQL .= ' WHERE 1=1 ';

    return $SQL;
}

function getModuleFields()
{
    /*$first_statement = reset($this->statements);
    return $first_statement->getModuleFields();*/

    return $this->fields;
}

}


class ModuleRef
{
var $moduleID;
var $alias;
var $name;
var $joins = array();
var $parentStatement;

function &Factory($element, $moduleID, &$callerRef)
{
    $mr =& new ModuleRef();
    $mr->name = $element->attributes['name'];
    $mr->alias = $element->attributes['alias'];
    $mr->moduleID = $element->attributes['moduleID'];
    $mr->parentStatement =& $callerRef;

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $callerRef->_rootModuleID;
    global $gTableAliasParents;

    if(count($element->c) > 0){
        foreach($element->c as $subElement){
            $joins = array();
            $strJoins = '';
            $joinToAlias = $subElement->attributes['joinToAlias'];
            foreach($subElement->c as $sub2Element){
                $gTableAliasParents[$SQLBaseModuleID][$mr->alias] = $joinToAlias;
                $joins[] = "`$joinToAlias`.{$sub2Element->attributes['joinToKey']} = `{$mr->alias}`.{$sub2Element->attributes['localKey']}";
            }
            $type = $subElement->attributes['type'];
            $strJoins = join(' AND ', $joins);
            
            $mr->joins[$joinToAlias] = "$type JOIN `{$mr->moduleID}` AS `{$mr->alias}` ON ($strJoins)";
        }
    }

    return $mr;
}


}


class Field
{
var $name; //local name
var $field; //name of referenced modulefield
var $moduleAlias;
var $parentStatement;
var $moduleField;

function &Factory(&$element, $moduleID, &$callerRef)
{
    $field =& new Field($element, $moduleID, $callerRef);
    return $field;
}

function Field(&$element, $moduleID, &$callerRef)
{
    $this->name = $element->attributes['name'];
    $this->field = $element->attributes['field'];
    $this->parentStatement =& $callerRef;
    $this->moduleAlias = $element->attributes['moduleAlias'];

}

function &_getModuleRef(){
print "field {$this->name} called _getModuleRef()\n";
//print_r($this->parentStatement->moduleRefs);

    return $this->parentStatement->moduleRefs[$this->moduleAlias];
}


function getModuleID()
{
    $moduleRef = $this->_getModuleRef();
    return $moduleRef->moduleID;
}

function getModuleField()
{
    if(!empty($this->moduleField)){
        return $this->moduleField;
    }

    $moduleID = $this->getModuleID();
    print "field {$this->name} moduleID= $moduleID\n";

    $this->moduleField = GetModuleField($moduleID, $this->field);

    if($this->parentStatement->_rootModuleID != $moduleID){
        print "need to re-make module field for $moduleID.{$this->name}\n";
    }
    return $this->moduleField;
}

function makeSelectDef($includeFieldAlias = true)
{
    $mf = $this->getModuleField();

    $def = $this->moduleField->makeSelectDef($this->moduleAlias, false);
    if($includeFieldAlias){
        $def .= ' AS '.$this->name;
    }

    return  $def;
}

function makeJoinDef()
{
    return $this->moduleField->makeJoinDef($this->moduleAlias);
}


function getGridAlign()
{
    return $this->moduleField->getGridAlign();
}

function getDataType()
{
    $mf = $this->getModuleField();
    return $mf->getDataType();
}
}


class Value
{
var $name; //local name
var $value;

function &Factory($element, $moduleID, &$callerRef)
{
    $field =& new Value();
    $field->name = $element->attributes['name'];
    $field->value = $element->attributes['value'];

    return $field;
}

function makeSelectDef()
{
    return "'{$this->value}' AS {$this->name}";
}

function makeJoinDef()
{
    return array();
}

function needsReGet()
{
    return false;
}

function getDataType()
{
    return '';
}
}


class UnionField
{
var $name;
var $dataType;
var $phrase;
var $displayFormat;

function UnionField($name, $dataType)
{
    $this->name = $name;
    $this->dataType = $dataType;
}

function makeSelectDef($alias)
{
    return "`$alias`.{$this->name}";
}

function needsReGet()
{
    return false;
}

function getGridAlign()
{
    switch ($this->dataType){
    case 'bool':
    case 'date':
    case 'datetime':
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

}


class FilterCondition
{
var $filterField;
var $operator;
var $value;
var $parentStatement;

function &Factory($element, $moduleID, &$callerRef)
{
    $cond =& new FilterCondition($element, $moduleID, $callerRef);
    return $cond;
}

function FilterCondition($element, $moduleID, &$callerRef)
{
    $this->filterField = $element->attributes['filterField'];
    $this->operator = $element->attributes['operator'];
    $this->value = $element->attributes['value'];
    $this->parentStatement =& $callerRef;
}

function getCondition()
{
    //get field
    $field = $this->parentStatement->fields[$this->filterField];
    $SQLSnip = $field->makeSelectDef(false);
    switch($this->operator){
    case 'greater':
        $SQLSnip .= ' > ';
        break;
    default:
        trigger_error("Unknown operator for FilterCondition: {$this->operator}", E_USER_ERROR);
        break;
    }
    $SQLSnip .= "'{$this->value}'";
    return $SQLSnip;
}
}

?>