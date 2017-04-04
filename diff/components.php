<?php
/**
 * Renderable screen component classes
 *
 * This file contains abstract class definitions for screen components,
 * as well as concrete implementations of screen field classes.  For
 * grids, see the grids.php file.
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
 * @version        SVN: $Revision: 1499 $
 * @last-modified  SVN: $Date: 2009-02-06 06:35:05 +0100 (Pt, 06 lut 2009) $
 */




/**
 *  Root class for all renderable controls, i.e. screen fields and grids
 */
class ScreenControl
{
var $name;

/**
 * Abstract factory class
 */
function Factory($element, $moduleID)
{
    return false;
}


/**
 * Whether the control is editable.
 *
 * This is implemented as a method in order simulate a class property.
 */
function isEditable()
{
    return false;
}


/**
 *  Checks whether there is a (display) condition assigned to the control, and evaluates it.
 *
 * Caution: This method is not much used, if at all. May need some overhaul if conditional
 * displays are needed.
 */
function checkCondition(&$values)
{
    return true; //phasing this method out | MJT
}


/**
 * Abstract render function. This would generate the HTML code that displays the control.
 */
function render(&$values, &$phrases, $flags = array())
{
    return "//undefined\n";
}


/**
 * Determines whether the control should be displayed or not.
 */
function isVisible()
{
    return true;
}
} //end class ScreenControl





/**
 *  Root class for all renderable fields
 */
class ScreenField extends ScreenControl {
var $parentName; //name of containing parent field: blank if not a sub-field
var $Fields = array(); //a collection of subfields (objects) to be displayed together with the current field
var $phrase; //only used in grids, really
var $dataType;
var $validate;
var $invalid;
var $formName; //'mainForm' if not in a grid
var $gridAlign; //left, right or center: the alignment of the data in a grid cell
var $displayFormat;  //sprintf-style formatting string.
var $defaultValue;
var $isDefault;
var $renderMode; //set to 'search' when rendering in search mode
var $inlinePreContent;
var $inlinePostContent;

/**
 * Creates a documentation object
 */
function DocFactory($element, $moduleID){
    return new ScreenFieldDoc($element, $moduleID);
}


function isVisible()
{
    //function to determine whether a control should be displayed
    return true;
}


function needsReGet()
{
    //determines whether an EditScreen needs to re-load data after saving
    return false;
}


//generates the output to be displayed in a form
function render(&$values, &$phrases)
{

    //get content from subfields
    switch (count($this->Fields)){
    case 0:
        //just use the data for this field
        $data = $this->simpleRender($values);
        break;
    case 1:
        $data = $this->simpleRender($values);
        $subField = end($this->Fields);
        $data .= ' '.$subField->simpleRender($values);
        break;
    default:
        //just use the data from the subfields - this field is repeated here
        $data = '';
        foreach($this->Fields as $key => $subField){
            if ('Self' == $key) {
                $data .= $this->simpleRender($values);
            } else {
                $data .= $subField->simpleRender($values);
            }
            $data .= ' ';
        }
        break;
    }

    if($this->isSubField()){
        //simply return the content padded w/ spaces
        $content = ' '.$data.' ';
    } else {
        if(strlen($this->validate)){
            if($this->invalid){
                $required = 'flblh flbl';
            } else {
                if(FALSE !== strpos($this->validate, 'notEmpty')){
                    $required = 'flblr flbl';
                } elseif(FALSE !== strpos($this->validate, 'RequireSelection')) {
                    $required = 'flblr flbl';
                } else {
                    $required = 'flbl';
                }
            }

        } else {
            //print $this->name . ": nonreq<br>";
            $required = 'flbl';
        }
        $phrase = $phrases[$this->name];
        if(empty($phrase)){
            $phrase = $this->phrase;
        }
		$phrase = gettext($phrase);
		
        $formName = $this->formName;
        if(empty($formName)){
            $formName = 'mainForm';
        }
        if($this->isDefault){
            $unsavedClass = 'unsaved';
        } else {
            $unsavedClass = '';
        }
		
        $content = sprintf(
            FIELD_HTML,
            LongPhrase($phrase),
            ShortPhrase($phrase),
            $data,
            $required,
            $formName.'.'.$this->name,
            $unsavedClass
            );
    }
    return $content;
}


function simpleRender(&$values)
{
    return '';//override this
}


function gridHeaderPhrase()
{
    if(empty($this->phrase)){
        return $this->name;
    } else {
        return ShortPhrase(gettext($this->phrase));
    }
}


function gridViewRender(&$pRow)
{
    return $this->viewRender($pRow);
}


function gridEditRender(&$pRow)
{

    if(!is_array($pRow)){
        $pRow = array($this->name => '');
    }
    return $this->simpleRender($pRow);
}


function checkGridRender(&$pRow)
{
    return $this->viewRender($pRow);
}


function gridFormRender(&$pRow)
{

    if(!is_array($pRow)){
        $pRow = array($this->name => '');
    }
    //we create a temporary array just to pass it on to the render function.
    //this is somewhat ineffective but allows code reuse and not having to change the render() function.
    $phrases = array();
    $phrases[$this->name] = $this->phrase;

    return $this->render($pRow, $phrases);
}


//used in grids and by ViewField
function viewRender(&$values)
{
    global $User;

    $rawValue = null;
    if(isset($values[$this->name])){
        $rawValue = $values[$this->name];
    }

    switch ($this->dataType){
    case 'bool':

        //now uses joins to code type 1
        switch(strval($rawValue)) {
        case '1':
            $content = gettext("Yes");
            break;
        case '0':
        case '-1': //ought to be saved as zero
            $content = gettext("No");
            break;
        default:
            $content = gettext("No data") . $rawValue;
        }
        break;
    case 'date':

        //check for nulls and empty values
        if ((null == $values[$this->name]) || ('0000-00-00' == $rawValue)) {
            $content = gettext("(no date set)");
        } else {
            $content = $rawValue;
        }
        break;
    case 'datetime':

        //check for nulls and empty values
        if ((null == $rawValue) || ('0000-00-00' == $rawValue)) {
            $content = gettext("(no date/time set)");
        } else {
            $content = $rawValue;
        }
        break;
    case 'time':
        if ((null == $rawValue)) {
            $content = gettext("(no time set)");
        } else {
            $content = $rawValue;
        }
        break;
    case 'money':
        //we want to display only two decimals unless last two aren't zero.
        $nVal = 100 * $rawValue;

        if(floor($nVal) == $nVal){
            $content = MASTER_CURRENCY.' '.number_format($rawValue, 2);
        } else { //there are more than 2 decimals so display the whole thing
            $content = MASTER_CURRENCY.' '.number_format($rawValue, 4);
        }
        break;
    case 'varchar(128)':
    case 'varchar(255)':
    case 'text':
        //longer character fields
        $content = nl2br($rawValue);
        break;
    default:
        if(is_numeric($rawValue) && ('' != $this->displayDecimals)){
            if(isset($this->roundingMethod) && 'round' != $this->roundingMethod){
                $tempMultiplier = pow(10, $this->displayDecimals);
                $tempValue = $rawValue * $tempMultiplier;
                switch($this->roundingMethod){
                case 'ceil':
                    $tempValue = ceil($tempValue);
                    break;
                case 'floor':
                default:
                    $tempValue = floor($tempValue);
                    break;
                }
                $rawValue = $tempValue / $tempMultiplier;
            }
            $content = number_format($rawValue, $this->displayDecimals);
        } else {
            $content = $rawValue;
        }
    }

    if(!empty($this->displayFormat)){
        //$content = $content . $this->displayFormat;
        $content = sprintf('%'.$this->displayFormat, $content);
    }

    if('viewfield' != strtolower(get_class($this))){ //avoid duplicate rendering of subfields
        if(count($this->Fields) > 0){
            foreach($this->Fields as $key => $subField){
                if('Self' != $key){
                    $content .= ' ' .$subField->viewRender($values);
                }
            }
        }
    }

    return $content;
}


/**
 *  Causes different rendering behavior for e.g. Search screens
 */
function searchRender(&$values, &$phrases)
{
    $this->defaultValue = '';
    $this->renderMode = 'search';
    return $this->render($values, $phrases);
}


/**
 * Used when rendering paper forms for data collection purposes
 */
function dataCollectionRender(&$values, $format = 'html')
{
    return '';//override this
}


/**
 *  Returns a default value for a field
 */
function getDefaultValue($data = null)
{
    if(empty($this->defaultValue)){
        return null;
    }
    switch($this->defaultValue){
    case 'userID':
        global $User;
        return $User->PersonID;
        break;
    case 'defaultorgID':
        global $User;
        return $User->defaultOrgID;
        break;
    default:
        if(false !== strpos($this->defaultValue, '#')){
            return str_replace('#', '', $this->defaultValue);
        }
        if(false !== strpos($this->defaultValue, '[*')){
            //global $data;
            return PopulateValues($this->defaultValue, $data);
        } else {
            return null;
        }
        break;
    }
}


/**
 *  Determines whether the user entered a search expression for this field.
 *
 *  This function can be overriden for field types where this is determined in a different way.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name])){
        return false;
    }

    if ('' != trim($data[$this->name]) ){
        return true;
    } else {
        return false;
    }
}


function quoteValue($value)
{
    //overridable function for proper quoting of POSTed values
    return dbQuote($value);
}


function getSearchPhrase(&$data, &$moduleFields)
{
    $content = '';
    //global $phrases;
    if(empty($this->phrase)){
        $phrase = $moduleFields[$this->name]->phrase;
    } else {
        $phrase = $this->phrase;
    }
	$phrase =gettext($phrase);
	
    $content .= ShortPhrase($phrase) . ': ' . $data[$this->name];
    return $content;
}


function &handleSearch(&$data, &$moduleFields, $overrideModuleID = '')
{
    //check whether the user entered an expression for this field
    if ($this->checkSearch($data)){
/*        $moduleField =& $moduleFields[$this->name];

        if(empty($overrideModuleID)){
            global $ModuleID;
        } else {
            $ModuleID = $overrideModuleID;
        }*/

        $from = array();
        $where = array();
        $phrase = array();

//        $name = GetQualifiedName($this->name);
        $from = GetJoinDef($this->name);

//        $value = $this->quoteValue($data[$this->name]);
//        $where[$this->name] = "$name = $value";

        $where[$this->name] = $this->getSearchCondition($data);
        $phrase[$this->name] = $this->getSearchPhrase($data, $moduleFields);

        $searchDef = array(
            'f' => $from,
            'w' => $where,
            'p' => $phrase,
            'v' => array($this->name => $data[$this->name])
        );
    } else {
        $searchDef = NULL;
    }
    return $searchDef;
}


function getSearchCondition(&$data)
{
    $name = GetQualifiedName($this->name);
    $value = $this->quoteValue($data[$this->name]);
    return "$name = $value";
}


function isSubField()
{
    if (!empty($this->parentName)){
        return true;
    } else {
        return false;
    }
}


//this function returns the field names to be added to a SELECT
//statement on account of this field. defaults to own name only.
function getSelectFields()
{
    $fields = array();
    if(count($this->Fields) > 0){
        foreach($this->Fields as $field_name => $field){
            if('Self' != $field_name){
                $fields = $field->getSelectFields();
            }
        }
    }
    if(isset($this->linkField) && !empty($this->linkField)){
        if(!array_key_exists($this->linkField, $fields)){
            $fields[$this->linkField] = true;
        }
    }
    $fields[$this->name] = true;
    return $fields;
}


//returns an array of names of subfields (recursively), including this field
function getRecursiveFields()
{
    $fields = array();
    if(count($this->Fields) > 0){
//print_r($this->Fields);
        foreach($this->Fields as $field_name=>$subField){
            if('Self' != $field_name){
                $fields = array_merge($fields, $subField->getRecursiveFields());
            }
        }
    }
    $fields[$this->name] = $this;
    return $fields;
}


/**
 * Some simple string replacement for formatting the inlinePreContent and inlinePostContent
 */
function _inlineFormat($content)
{
    $content = str_replace(array('[', ']'), array('<', '>'), $content);
    $content = str_replace(':shortPhrase:', ShortPhrase($this->phrase), $content);
    return $content;
}
} //end class ScreenField





class EditableField extends ScreenField
{

function isEditable()
{
    return true;
}
} //end class EditableField




//field class for data that should not be displayed on a screen but needs to be retrieved
class InvisibleField extends ScreenField
{

function &Factory($element, $moduleID)
{
    $field = new InvisibleField($element, $moduleID);
    return $field;
}


function InvisibleField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }
}


function isVisible()
{
    //function to determine whether a control should be displayed
    return false;
}


function simpleRender(&$values)
{
    return '';
}


function viewRender(&$values)
{
    return '';
}


function render(&$values, &$phrases)
{
    return $this->simpleRender($values);
}
} //end class InvisibleField





//field class for data that should not be visibly displayed on a screen but needs to be posted
class HiddenField extends InvisibleField {

function &Factory($element, $moduleID)
{
    $field = new HiddenField($element, $moduleID);
    return $field;
}


function HiddenField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }
}


function isEditable()
{
    return true;
}


function simpleRender(&$values)
{
    return "<input type=\"hidden\" name=\"{$this->name}\" value=\"{$values[$this->name]}\"/>";
}


function viewRender(&$values)
{
    return '';
}
} //end class HiddenField




class ViewField extends ScreenField
{
var $linkField; //name of (optional) module field that holds a URL
var $parentField; //updating parent
var $displayDecimals;
var $roundingMethod; //only if using displayDecimals: 'floor', 'ceil', 'round' (default)

function &Factory($element, $moduleID)
{
    $field = new ViewField($element, $moduleID);
    return $field;
}


function ViewField(&$element, $moduleID)
{

    $moduleField = GetModuleField($moduleID, $element->name);
    $debug_prefix = debug_indent("ViewField-constructor() {$moduleID}.{$element->name}:");

    $this->name = $element->getAttr('name', true);

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->dataType = $element->getAttr('dataType');
    if(empty($this->dataType)){
        $this->dataType = $moduleField->dataType;
    }

    $this->displayDecimals = $element->getAttr('displayDecimals');
    $this->roundingMethod  = $element->getAttr('roundingMethod');
    $this->linkField = $element->getAttr('link');
    $this->parentField = $element->getAttr('parentField');

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->needsReGet = $moduleField->needsReGet();

    //allows phrase attribute to override module field phrase
    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase) && ('IsBestPractice' != $this->name)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }

    $this->formatField = $element->getAttr('formatField');

    //generate cached SQL statement that retrieves data update...
    if(!empty($element->attributes['parentField'])){
        $this->needsReGet = true;
        $SQL = $this->makeUpdateSQL($moduleID, $element->attributes['parentField']);

        $createFileName = $moduleID.'_ViewFieldSQL.gen';
        $modelFileName = 'ViewFieldSQLModel.php';
        $createFilePath = GENERATED_PATH ."/$moduleID/$createFileName";
        if (file_exists($createFilePath)){
            include($createFilePath); //not include_once...
        } else {
            $viewFieldSQLs = array();
        }
        $viewFieldSQLs[$this->name] = $SQL;
        $replaceValues = array('/**viewFieldSQLs**/' => escapeSerialize($viewFieldSQLs));

        SaveGeneratedFile($modelFileName, $createFileName, $replaceValues, $moduleID);

    }
    debug_unindent();
}


function needsReGet()
{
    if(isset($this->needsReGet)){
        return $this->needsReGet;
    }
}


function _simpleRender(&$values)
{

    if (!empty($this->linkField)){
        $link = $values[$this->linkField];
        $newWin = '';
        $internal = false;
        if(!empty($link)){
            list($link, $internal, $newWin) = linkFormat($link);

            if($internal){
                $poplink = base64_encode($link);
                global $theme_web;
				$RelatedRecordText =$this->viewRender($values);
				$RelatedRecordText = preg_replace('/(.*) Module \(Record ID: (.)\)/e', "gettext('\\1').' '.gettext('Module (Record ID:').' \\2)'", $RelatedRecordText);
				$RelatedRecordText = preg_replace('/(.*) \(general\)/e', "gettext('\\1').' '.gettext('(general)')", $RelatedRecordText);
				
                $content = '';

                $content .= '<div style="float:right">';
                $content .= '<a href="#" onclick="window.open(\'frames_popup.php?dest=\\\''.$poplink.'\\\'\', \''.$this->moduleID.'RelRec\', \'toolbar=0,resizable=1\')">';
                $content .= '<img src="'.$theme_web.'/img/open_new_window.gif" title="'.gettext("View in new window").'" alt="'.gettext("new window").'" />';
                $content .= '</a> ';
                $content .= '</div>';

                $content .= '<div style="margin-right:12px">';
                $content .= '<a href="'.$link.'">';
				$content .= $RelatedRecordText; 

//                $content .= $this->viewRender($values);
                $content .= '</a>';
                $content .= '</div>';
            } else {
                if($newWin){
                    $str_target = ' target="_blank"';
                } else {
                    $str_target = '';
                }
                $content = '<a href="'.$link.'"'.$str_target.'>';
                $content .= $this->viewRender($values);
                $content .= '</a>';
            }
        } else {
            $content = $this->viewRender($values);
        }
    } else {
        $content = $this->viewRender($values);
    }
    return $content;
}


function simpleRender(&$values)
{
    if (!empty($this->formatField)){
        $className = ' class="'.$values[$this->formatField].'" ';
    } else {
        $className = '';
    }
    $formName = $this->formName;
    if(empty($formName)){
        $formName = 'mainForm';
    }

    $idDiv = '<div id="'.$formName.'_'.$this->name.'"'.$className.'>/*content*/</div>';

    return $this->inlinePreContent . str_replace('/*content*/', $this->_simpleRender($values), $idDiv) . $this->inlinePostContent;
}


function gridViewRender(&$values)
{
    return $this->_simpleRender($values);
}


function makeUpdateSQL($moduleID, $parentFieldName)
{
    $debug_prefix = debug_indent("ViewField-makeUpdateSQL() {$moduleID}.{$this->name}:");

    $parentForeignFieldName = substr($parentFieldName, 0, -2);
    $parentForeignField = GetModuleField($moduleID, $parentForeignFieldName);
    if(empty($parentForeignField)){
        die("$debug_prefix The field $moduleID.$parentForeignFieldName is required for generating the viewfield update sql");
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $parentForeignField->getForeignModuleID();
    $foreignModuleFields = GetModuleFields($SQLBaseModuleID);

    $localModuleField = GetModuleField($moduleID, $this->name);
    if(!is_a($localModuleField, 'foreignfield')){
        indent_print_r($localModuleField, true, 'localModuleField');
        die("$debug_prefix must be a ForeignField in order to use ViewField Updates");
    }

    $foreignModuleFieldName = $localModuleField->foreignField;
    if($localModuleField->foreignTable == $SQLBaseModuleID){
        $foreignModuleField = $foreignModuleFields[$foreignModuleFieldName];
    } else {

        //the localModuleField must be a foreignfield in the $foreignModuleFields
        foreach($foreignModuleFields as $lookupField){
            if(is_a($lookupField, 'foreignfield')){

                $localListConditions = '';
                $lookupListConditions = '';
                if(count($localModuleField->listConditions) > 0){
                    foreach($localModuleField->listConditions as $listCondition){
                        $localListConditions .= $listCondition->getExpression($localModuleField->foreignTable);
                    }
                }
                if(count($lookupField->listConditions) > 0){
                    foreach($lookupField->listConditions as $listCondition){
                        $lookupListConditions .= $listCondition->getExpression($lookupField->foreignTable);
                    }
                }

                if($localModuleField->foreignTable == $lookupField->foreignTable
                    && $localModuleField->foreignField == $lookupField->foreignField
                    && $localModuleField->listCondition == $lookupField->listCondition
                    && $localListConditions == $lookupListConditions
                ){
                    $foreignModuleField = $lookupField;
                    break;
                }
            }
        }
    }
    if(empty($foreignModuleField)){
        die("$debug_prefix cannot find a matching field in $SQLBaseModuleID");
    }

    $moduleInfo = GetModuleInfo($SQLBaseModuleID);
    $foreignPK = $moduleInfo->getPKField();

    $select = GetQualifiedName($foreignModuleField->name);
    $joins = GetJoinDef($foreignModuleField->name);

    $joins = SortJoins($joins);

    $SQL = "SELECT\n$select AS Value\n";
    $SQL .= "FROM `$SQLBaseModuleID`\n";
    foreach($joins as $alias => $def){
        $SQL .= "$def\n";
    }
    $SQL .= "WHERE\n";
    $SQL .= "`{$SQLBaseModuleID}`._Deleted = 0";
    $SQL .= "\nAND `{$SQLBaseModuleID}`.{$foreignPK} = '/*recordID*/'";

    CheckSQL($SQL);
    debug_unindent();
    return $SQL;
}
}//end class ViewField



class EditField extends EditableField
{
var $size;
var $maxLength;
var $align = 'left';
var $validate;

function &Factory($element, $moduleID)
{
    $field = new EditField($element, $moduleID);
    return $field;
}


function EditField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $this->name      = $element->getAttr('name', true);
    $this->size      = $element->getAttr('size');
    $this->maxLength = $element->getAttr('maxLength');

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }
    $this->validate  = $validate;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->dataType = $element->getAttr('dataType');
    if(empty($this->dataType)){
        $this->dataType = $moduleField->dataType;
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }

}


function simpleRender(&$values, $overrideName = '')
{

    if(!empty($overrideName)){
        $name = $overrideName;
    } else {
        $name = $this->name;
    }

    $value = null;
    if(!empty($values[$name])){
        $value = $values[$name];
    } else {
        $value = $this->getDefaultValue($values);
        if(!empty($value)){
            $this->isDefault = true;
        }
    }

    if(!empty($_POST)){
        $value = htmlentities($value, ENT_COMPAT, 'UTF-8');
        $value = stripslashes($value);
    }


    return $this->inlinePreContent.' '.
        sprintf(
            FORM_EDIT_HTML,
            $name,
            $value,
            $this->size,
            $this->maxLength,
            $this->align,
            ''
        )
        .' '.$this->inlinePostContent;
}

function dataCollectionRender(&$values, $format = 'html')
{
    return '';
}


function useFromToFields()
{
    //this is a helper function used for determining how the field is
    //rendered on a search screen
    switch ($this->dataType){
    case 'int':
    case 'float':
    case 'money':
        return true;
        break;
    default:
        return false;
    }
}

//EditField
function render(&$values, &$phrases)
{
    switch($this->renderMode){
    case 'search':
        if($this->useFromToFields()) {
            $content = $this->simpleRender($values, $this->name.'_f');
            $content .= ' - ';
            $content .= $this->simpleRender($values, $this->name.'_t');

            $content = sprintf(
                FIELD_HTML,
                addslashes(LongPhrase($phrases[$this->name])),
                ShortPhrase($phrases[$this->name]),
                $content,
                'flbl',
                $this->formName.'.'.$this->name,
                ''
            );

        } else {
            $content = parent::render($values, $phrases);
        }
        break;
    default:
            $content = parent::render($values, $phrases);
        break;
    }
    return $content;
}


/**
 *  Determines whether the user entered a search expression for this field.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name])){
        $value = '';
    } else {
        $value = $data[$this->name];
    }
    if(!isset($data[$this->name.'_f'])){
        $value_from = '';
    } else {
        $value_from = $data[$this->name.'_f'];
    }
    if(!isset($data[$this->name.'_t'])){
        $value_to = '';
    } else {
        $value_to = $data[$this->name.'_t'];
    }

    if('' != trim($value) || '' != trim($value_from) || '' != trim($value_to)){
        return true;
    } else {
        return false;
    }
}


function getSearchPhrase(&$data, &$moduleFields)
{
    $content = '';
    //global $phrases;
    if(empty($this->phrase)){
        $phrase = $moduleFields[$this->name]->phrase;
    } else {
        $phrase = $this->phrase;
    }
	$phrase =gettext($phrase);
	
    if($this->useFromToFields()) {

        if (!empty($data[$this->name.'_f'])){
            $content .= ShortPhrase($phrase) . ' >= ' . $data[$this->name.'_f'];
        }
        if (!empty($data[$this->name.'_t'])){
            if(strlen($content) > 0){
                $content .= "<br />\n";
            }
            $content .= ShortPhrase($phrase) . ' <= ' . $data[$this->name.'_t'];
        }
    } else {
        $content .= ShortPhrase($phrase) . ': ' . $data[$this->name];
    }
    return $content;
}


function &handleSearch(&$data, &$moduleFields)
{
    //check whether the user entered an expression for this field
    if ($this->checkSearch($data)){
        $moduleField =& $moduleFields[$this->name];

        global $ModuleID;

        $from = array();
        $where = array();
        $values = array();

        $name = GetQualifiedName($this->name);
        $from = GetJoinDef($this->name);

        //check whether uses from-to values
        if($this->useFromToFields()) {

            if (!empty($data[$this->name.'_f'])){
                $value = $this->quoteValue($data[$this->name.'_f']);
                $where[$this->name.'_f'] = "$name >= $value";
                $values[$this->name.'_f'] = $data[$this->name.'_f'];
            }
            if (!empty($data[$this->name.'_t'])){
                $value = $this->quoteValue($data[$this->name.'_t']);
                $where[$this->name.'_t'] = "$name <= $value";
                $values[$this->name.'_t'] = $data[$this->name.'_t'];
            }

        } else {
            $value = str_replace('*', '%', $this->quoteValue($data[$this->name]));
            $where[$this->name] = "$name LIKE $value";
        }

        $phrase[$this->name] = $this->getSearchPhrase($data, $moduleFields);

        $searchDef = array(
            'f' => $from,
            'w' => $where,
            'p' => $phrase,
            'v' => $values
        );

    } else {
        $searchDef = null;
    }
    return $searchDef;
}
}//end class EditField



class PasswordField extends EditField
{
var $confirm;

function &Factory($element, $moduleID)
{
    $field =& new PasswordField($element, $moduleID);
    return $field;
}


function PasswordField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name           = $element->getAttr('name', true);
    $this->size           = $element->getAttr('size');
    $this->maxLength      = $element->getAttr('maxLength');
    $this->dataType       = $moduleField->dataType;
    $this->validate       = $moduleField->validate;
    $this->confirm        = $element->getAttr('confirm');

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values, $overrideName = '')
{
    $content = ' '.
        sprintf(
            FORM_PWD_HTML,
            $this->name,
            '',
            $this->size,
            $this->maxLength,
            $this->align
        )
        .' ';

    if($this->confirm){
        $name = $this->name . '_confirm';

        $content .= "<br />\n";
        $content .= ' '.
        sprintf(
            FORM_PWD_HTML,
            $name,
            '',
            $this->size,
            $this->maxLength,
            $this->align
        )
        .' ';
        $content .= gettext("(confirm)") . "\n";
    }

    return $this->inlinePreContent . $content . $this->inlinePostContent;
}
} //end PasswordField



class UploadField extends EditField
{


function &Factory($element, $moduleID)
{
    $field =& new UploadField($element, $moduleID);
    return $field;
}


function UploadField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->size = $element->getAttr('size');
    $this->maxLength = $element->getAttr('maxLength');
    $this->dataType = $moduleField->dataType;
    $this->validate = $moduleField->validate;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values, $overrideName = '')
{
    return $this->inlinePreContent.' '.
        sprintf(
            FORM_FILE_HTML,
            UPLOAD_MAX_FILE_SIZE,
            $this->name,
            htmlspecialchars(stripslashes($values[$this->name]), ENT_QUOTES),
            $this->size,
            $this->maxLength
        )
        .' '.$this->inlinePostContent;
}


//viewRender function should render the download link
function gridViewRender(&$values){

    global $ModuleID;
    global $recordID;

    $link = "download.php?mdl=$ModuleID&amp;rid=$recordID&amp;fid={$values['AttachmentID']}";
    $content = '<a href="'.$link.'" target="_blank">';
    $content .= $values[$this->name];
    $content .= '</a>';
    return $content;
}
} //end UploadField





class DateField extends EditableField
{
var $align = 'right';
var $validate;
var $dataType;
var $defaultValue;


function &Factory($element, $moduleID)
{
    $field =& new DateField($element, $moduleID);
    return $field;
}


function DateField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }


    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->dataType = $moduleField->dataType;
    $this->defaultValue = $moduleField->defaultValue;
    $this->validate = $validate;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values, $overrideName = '')
{
    global $User;

    if(!empty($overrideName)){
        $name = $overrideName;
    } else {
        $name = $this->name;
    }

    if('datetime' == $this->dataType){
        $size = '22';
        $maxlength = '19';
        $formatString = $User->getDateTimeFormatPHP(); //'%x %X'
    } else {

        $size = '12';
        $maxlength = '10';
        $formatString = $User->getDateFormatPHP(); //'%x';
    }

    $value = null;
    if(isset($values[$name])){
        $value = $values[$name];
    }

    //check for nulls and empty values
    if((null == $value) || ('0000-00-00' == $value)) {
        switch($this->defaultValue){
        case 'today':
            $value = strftime($formatString); //defaults to current date/time
            break;
        default:
            $value = '';
        }
    } else {
        $value = $values[$name];
    }


    return $this->inlinePreContent.' '.
        sprintf(
            FORM_DATE_HTML,
            $name,
            $value,
            $size,
            $maxlength,
            $this->align,
            $User->getDateFormat()
        )
        .' '.$this->inlinePostContent;
}


//DateField
function render(&$values, &$phrases)
{
    switch($this->renderMode){
    case 'search':
        $content = $this->simpleRender($values, $this->name.'_f');
        $content .= ' - ';
        $content .= $this->simpleRender($values, $this->name.'_t');

        $content = sprintf(
            FIELD_HTML,
            addslashes(LongPhrase($phrases[$this->name])),
            ShortPhrase($phrases[$this->name]),
            $content,
            'flbl',
            $this->formName.'.'.$this->name,
            ''
            );
        break;
    default:
        $content = parent::render($values, $phrases);
        break;
    }
    return $content;
}


/**
 *  Determines whether the user entered a search expression for this field.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name.'_f'])){
        $value_from = '';
    } else {
        $value_from = $data[$this->name.'_f'];
    }
    if(!isset($data[$this->name.'_t'])){
        $value_to = '';
    } else {
        $value_to = $data[$this->name.'_t'];
    }

    if('' != trim($value_from) || '' != trim($value_to)){
        return true;
    } else {
        return false;
    }
}


function quoteValue($value)
{
    //overridable function for proper quoting of POSTed values
    return DateToISO($value);
}


function getSearchPhrase(&$data, &$moduleFields)
{
    $content = '';
    //global $phrases;
    if(empty($this->phrase)){
        $phrase = $moduleFields[$this->name]->phrase;
    } else {
        $phrase = $this->phrase;
    }
	$phrase =gettext($phrase);
	
    if (!empty($data[$this->name.'_f'])){
        $content .= ShortPhrase($phrase) . ' '.gettext("on or after").' ' . $data[$this->name.'_f'];
    }
    if (!empty($data[$this->name.'_t'])){
        if(strlen($content) > 0){
            $content .= "<br />\n";
        }
        $content .= ShortPhrase($phrase) . ' '.gettext("on or before").' ' . $data[$this->name.'_t'];
        }

    return $content;
}


function &handleSearch(&$data, &$moduleFields)
{

    if ($this->checkSearch($data)){
        $moduleField =& $moduleFields[$this->name];
        global $ModuleID;

        $from = array();
        $where = array();
        $values = array();

        $name = GetQualifiedName($this->name);
        $from = GetJoinDef($this->name);

        //add from - to values
        if (!empty($data[$this->name.'_f'])){
            $value = $this->quoteValue($data[$this->name.'_f']);
            $where[$this->name.'_f'] = "$name >= $value";
        }
        if (!empty($data[$this->name.'_t'])){
            $value = $this->quoteValue($data[$this->name.'_t']);
            //adding one day in order to include the ending date
            $datevalue = strtotime('+1 day', strtotime(substr($value, 1, -1)));
            $value = '\''.date('Y-m-d', $datevalue).'\'';
            $where[$this->name.'_t'] = "$name < $value";
        }

        $phrase[$this->name] = $this->getSearchPhrase($data, $moduleFields);

        $searchDef = array(
            'f' => $from,
            'w' => $where,
            'p' => $phrase
        );

    } else {
        $searchDef = null;
    }
    return $searchDef;

}
}//end class DateField



class TimeField extends DateField
{
var $align = 'right';
var $validate;
var $dataType = 'time';


function &Factory($element, $moduleID)
{
    $field =& new TimeField($element, $moduleID);
    return $field;
}


function TimeField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->validate = $moduleField->validate;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values)
{
    return $this->inlinePreContent.' '.
        sprintf(
            FORM_TIME_HTML,
            $this->name,
            $values[$this->name],
            $this->align
        )
        .' '.$this->inlinePostContent;
}


function quoteValue($value)
{
    //overridable function for proper quoting of POSTed values
    return dbQuote($value);
}
}//end class TimeField



class MoneyField extends EditField
{
var $localAmountField;
var $localCurrencyIDField;
var $dataType = 'money';


function &Factory($element, $moduleID)
{
    $field = new MoneyField($element, $moduleID);
    return $field;
}


function MoneyField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->size = $element->getAttr('size');
    $this->maxLength = $element->getAttr('maxLength');
    $this->localAmountField = $element->getAttr('localAmountField');
    $this->localCurrencyIDField = $element->getAttr('localCurrencyIDField');
    $this->validate = $validate;
    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values, $overrideName = '')
{

    if(!empty($overrideName)){
        $name = $overrideName;
    } else {
        $name = $this->name;
    }

    $content = '';

    if($values[$this->name] != ''){
        //we want to display only two decimals unless last two aren't zero. 
        $val = floatval(str_replace(',','',$values[$this->name]));
        //print "string value: {$values[$this->name]}<br>";
        //print "floated value: $val<br>";
        $nVal = 10000 * $val;
        $fudge = 1; //fudge factor to account for some floating-point fuzzy math
        $mod = fmod(($nVal), 100);

        if($mod < $fudge){ //there are max 2 (non-zero) decimals
            //print "2 decimals<br>";
            //$content = " ".number_format($values[$name], 2)." ";
            $content = number_format($val, 2);
        } else { //there are more than 2 decimals so display the whole thing
            //print "4 decimals<br>";
            $content = $values[$name];
        }
    } else {
        $val = '';
    }

    return $this->inlinePreContent.' '.MASTER_CURRENCY.' '.
        sprintf(
            FORM_EDIT_HTML,
            $name,
            htmlspecialchars(stripslashes($content), ENT_QUOTES),
            $this->size,
            $this->maxLength,
            $this->align,
            ''
        )
        .' '.$this->inlinePostContent;
}
} //end class MoneyField



class CheckBoxField extends EditableField
{
var $ShortPhrase;
var $validate;
var $dataType = 'bool';


function &Factory($element, $moduleID)
{
    $field = new CheckBoxField($element, $moduleID);
    return $field;
}


function CheckBoxField(&$element, $moduleID)
{
    if('Checked' != $element->name){
        $moduleField = GetModuleField($moduleID, $element->name);
    }

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->validate = $validate;
    $this->moduleID = $moduleID;
    $this->defaultValue = $moduleField->defaultValue;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function getSearchPhrase(&$data, &$moduleFields)
{
    $content = '';
    //global $phrases;
    if(empty($this->phrase)){
        $phrase = $moduleFields[$this->name]->phrase;
    } else {
        $phrase = $this->phrase;
    }
	$phrase =gettext($phrase);
	
    switch($data[$this->name]){
    case '1':
        $valuePhrase = gettext("Yes");
        break;
    case '-1':
        $valuePhrase = gettext("No");
        break;
    default:
        $valuePhrase = gettext("Unknown");
    }
    $content .= ShortPhrase($phrase) . ': ' . $valuePhrase;
    return $content;
}


function simpleRender(&$values)
{

    //if data comes from the DB, massage it into "form" mode
    //(uses -1 for no)
    if(empty($_POST['Save']) && ('search' != $this->renderMode)){
        if(isset($values[$this->name])){
            $values[$this->name] = ChkUnFormat($values[$this->name]);
        }
    }

    //create a code radio field
    $crf =& MakeObject(
        $this->moduleID,
        $this->name,
        'CodeRadioField',
        array(
            'name' => $this->name,
            'orientation' => 'horizontal',
            'validate' => $this->validate,
            'formName' => $this->formName,
            'phrase' => $this->phrase,
            'bool' => 'yes'
        )
    );

    $crf->renderMode = $this->renderMode;

    $content = $crf->simpleRender($values);
//$content.= debug_r($values);
    unset($crf);
    return $this->inlinePreContent.$content.$this->inlinePostContent;
}


function dataCollectionRender(&$values, $format = 'html')
{
    return gettext('[Yes] [No]');
}


/**
 *  Determines whether the user entered a search expression for this field.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name])){
        return false;
    }

    $value = trim($data[$this->name]);
    if(!empty($value)){
        return true;
    } else {
        return false;
    }
}


function quoteValue($value){
    //overridable function for proper quoting of POSTed values
    return ChkFormat($value);
}


//CheckBoxField
function render(&$values, &$phrases)
{

    $phrase = $phrases[$this->name];
    if(empty($phrase)){
        $phrase = $this->phrase;
    }
	$phrase = gettext($phrase);
    if(strlen($this->validate)){
        if($this->invalid){
            $required = 'flblh flbl';
        } else {
            if(FALSE !== strpos($this->validate, 'notEmpty')){
                $required = 'flblr flbl';
            } elseif(FALSE !== strpos($this->validate, 'RequireSelection')) {
                $required = 'flblr flbl';
            } else {
                $required = 'flbl';
            }
        }
    } else {
        //print $this->name . ": nonreq<br>";
        $required = 'flbl';
    }


    $content = $this->simpleRender($values);
    $content = sprintf(
        FIELD_HTML,
        LongPhrase($phrase),
        ShortPhrase($phrase),
        $content,
        $required,
        $this->formName.'.'.$this->name,
        ''
        );
    return $content;
}


function checkGridRender(&$pRow){

    if(!empty($pRow[$this->name])){
        $checked = 'checked="checked"';
        $origChecked = ''; //'(in DB)';
    } else {
        $checked = '';
        $origChecked = '';
    }

    //parameters: field name, field ID, short phrase, checked ("checked" or ''), value
    $data = sprintf(
        FORM_CHECKBOX,
        $this->name.'[]',
        $this->name.'_'.$pRow['RowID'],
        $origChecked,
        $checked,
        $pRow['RowID']
    );

    return $data;
}
}//end class CheckBoxField



class MemoField extends EditableField
{
var $rows;
var $cols;
var $validate;

function &Factory($element, $moduleID)
{
    $field = new MemoField($element, $moduleID);
    return $field;
}


function MemoField(&$element, $moduleID)
{
    $moduleField = GetModuleField($moduleID, $element->name);

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->rows = $element->getAttr('rows');
    $this->cols = $element->getAttr('cols');
    $this->validate = $moduleField->validate;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    $moduleField = GetModuleField($moduleID, $this->name);
    if('text' != $moduleField->getDataType()){
        die("The ModuleField corresponding with the MemoField `$moduleID`.{$this->name} must have the type 'text'.");
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } else {
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
        }
    }
}


function simpleRender(&$values)
{
    $value = '';
    if(isset($values[$this->name])){
        $value = $values[$this->name];
    }

    //do the stripslashes only if data came from DB (and not from the form)
    if(isset($_POST[$this->name]) && $_POST[$this->name] != $value){
        $value = stripslashes($values[$this->name]);
    }
    return $this->inlinePreContent.' '.
        sprintf(
            FORM_MEMO_HTML,
            $this->name,
            $this->rows,
            $this->cols,
            $value
        )
        .' '.$this->inlinePostContent;
}


function dataCollectionRender(&$values, $format = 'html')
{
    return str_repeat("\r\n",$this->rows);
}
}//end class MemoField




class ComboField extends EditableField
{
var $listField;  //name of module field that provides the list items
var $foreignTable;
var $foreignKey;
var $foreignField;
var $listCondition; //only used by CodeComboField -- could be retired after migrating to listConditions
var $listConditions = array();
var $SQL;
var $getSQL; //sql statement to retrieve one single item
var $validate;
var $parentField;
var $parentListModuleField; //table field in the module that provides the list. Must be used when parser can't figure this from $parentField
var $childFields = array();
var $moduleID;
var $findMode = '';
var $ownerFieldFilter;
var $defaultValue;
var $suppressItemAdd = false; //whether the "plus" add item should be hidden or not
var $orderByFields = array();


function &Factory($element, $moduleID)
{
    $field = new ComboField($element, $moduleID);
    return $field;
}


function ComboField(&$element, $moduleID)
{
    $debug_prefix = debug_indent("ComboField [constructor]:");

    $this->name = $element->getAttr('name', true);
    $this->moduleID = $moduleID;

    $moduleFields = GetModuleFields($moduleID);
    $moduleField = $moduleFields[$this->name];

    $listfield_name = $element->getAttr('listField');
    if(empty($listfield_name)){
        $listfield_name = substr($this->name, 0, -2);
    }
    if(!isset($moduleFields[$listfield_name])){
        trigger_error("ComboField {$this->name} requires a module field named $listfield_name.", E_USER_ERROR);
    }
    $this->listField = $listfield_name;
    $list_moduleField = $moduleFields[$listfield_name];

    if('codefield' == strtolower(get_class($list_moduleField))){
        trigger_error("ModuleDef validation issue in module {$moduleID}: The CodeField $listfield_name requires that the ComboField named {$this->name} be converted into a CodeComboField.", E_USER_ERROR);
    }

    //assign list condition/filter
    /** this could be retired? **/
    $list_filter = $element->getAttr('listFilter');
    if (empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $formName = $element->getAttr('formName');
    if(empty($formName)){
        $formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if(isset($element->attributes['foreignTable'])){
        $this->foreignTable = $element->getAttr('foreignTable');
        $this->foreignKey   = $element->getAttr('foreignKey', true);
        $this->foreignField = $element->getAttr('foreignField', true);
    } else {
        $this->foreignTable = $list_moduleField->foreignTable;
        $this->foreignKey   = $list_moduleField->foreignKey;
        $this->foreignField = $list_moduleField->foreignField;
    }
    $this->listCondition = $list_filter;
    $this->listConditions = $list_moduleField->listConditions;
    $this->parentField = $element->getAttr('parentField');
    $this->parentListModuleField = $element->getAttr('parentListModuleField');

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }
    $this->validate = $validate;
    $this->formName = $formName;
    $this->suppressItemAdd = strtolower($element->getAttr('suppressItemAdd')) == 'yes';

    switch(strtolower($element->getAttr('findMode'))) {
    case 'text':
    case 'alpha':
        $this->findMode = 'text'; //was alpha, but 'text' works well for 'alpha' purposes, too
        break;
    default:
        $this->findMode = '';
    }

    $this->defaultValue = $moduleField->defaultValue;

    $this->_handleSubElements($element->c);
    $this->SQL = $this->buildSQL();

    print "$debug_prefix Created. {$this->name}\n";
    debug_unindent();
}


function _handleSubElements($sub_elements = array())
{
    if(count($sub_elements) > 0){
        foreach($sub_elements as $sub_element){
            if('Field' == substr($sub_element->type, -5)){
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            } elseif('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } elseif('UpdateFieldRef' == $sub_element->type){
                $this->childFields[$sub_element->name] = $sub_element->attributes;
            } elseif('SampleItem' == $sub_element->type){
                //ignore sample items
            } elseif('ListConditions' == $sub_element->type){

                foreach($sub_element->c as $condition_element){
                    $conditionObj = $condition_element->createObject($moduleID);
                    $this->listConditions[$conditionObj->name] = $conditionObj;
                }

            } elseif('OrderBy' == $sub_element->type) {
                $fieldName = $sub_element->getAttr('name', true);
                $desc = false;
                if('desc' == strtolower($sub_element->getAttr('direction'))){
                    $desc = true;
                }
                $this->orderByFields[$fieldName] = $desc;

            } else {
                trigger_error("Unknown sub-element type {$sub_element->type}.", E_USER_WARNING);
            }
        }
    }

}


/**
 * This handles the special case with cascading filtering CBs that depend on the child for the items
 *
 * ...and although this works, it's sort of a kludge.
 */
function _buildSQLwChildDep(){
    $debug_prefix = debug_indent("ComboField-_buildSQLwChildDep: {$this->moduleID}.{$this->name}");
    print "$debug_prefix foreignTable {$this->foreignTable}\n";
    print "$debug_prefix foreignKey {$this->foreignKey}\n";
    print "$debug_prefix foreignField {$this->foreignField}\n";

    //this include contains utility functions
    require_once(INCLUDE_PATH . '/general_util.php');

    $localMFs = GetModuleFields($this->moduleID);  //module fields of the local module (i.e. same as the form this field is part of)

    foreach($this->childFields as $childFieldName => $childFieldAttrs){
        if(isset($childFieldAttrs['listParentField'])){

            //getting the local foreign/remote field that corresponds with the child field
            $childListFieldName = substr($childFieldName, 0, -2);
            $childListField = $localMFs[$childListFieldName];

            //determines the base moduleID for the SQL statement
            $childListForeignModuleID = $childListField->getForeignModuleID();
            global $SQLBaseModuleID;
            $SQLBaseModuleID = $childListForeignModuleID;

            //this will provide the ID column in the SQL statement, and required joins
            $IDModuleField = GetModuleField($childListForeignModuleID, $childFieldAttrs['listParentField']);

            //$selects['ID'] = $IDModuleField->makeSelectDef($childListForeignModuleID, false);
            //$joins = $IDModuleField->makeJoinDef($childListForeignModuleID);
            $selects['ID'] = GetQualifiedName($IDModuleField->name, $childListForeignModuleID);
            $joins = GetJoinDef($IDModuleField->name, $childListForeignModuleID);

            //this will provide the "Name" column in the SQL statement, and required joins
            $NameModuleField = GetModuleField($childListForeignModuleID, substr($childFieldAttrs['listParentField'], 0, -2));

            //$selects['Name'] = $NameModuleField->makeSelectDef($childListForeignModuleID, false);
            //$joins = array_merge($joins, $NameModuleField->makeJoinDef($childListForeignModuleID));
            $selects['Name'] = GetQualifiedName($NameModuleField->name, $childListForeignModuleID);
            $joins = array_merge($joins, GetJoinDef($NameModuleField->name, $childListForeignModuleID));

            //this will provide the "ParentID" column in the SQL statement, and required joins
            if(!empty($this->parentListModuleField)){
                $parentFieldName = $this->parentListModuleField;
            } else {
                $parentFieldName = $this->parentField;
            }
            print "parentField: `$childListForeignModuleID`.{$parentFieldName}\n";
            $ParentModuleField = GetModuleField($childListForeignModuleID, $parentFieldName);

            //$selects['ParentID'] = $ParentModuleField->makeSelectDef($childListForeignModuleID, false);
            //$joins = array_merge($joins, $ParentModuleField->makeJoinDef($childListForeignModuleID));
            $selects['ParentID'] = GetQualifiedName($ParentModuleField->name, $childListForeignModuleID);
            $joins = array_merge($joins, GetJoinDef($ParentModuleField->name, $childListForeignModuleID));

            //appends field aliases
            foreach($selects as $selectName => $select){
                $selects[$selectName] = $select . ' AS ' . $selectName;
            }

            $SQL = "SELECT DISTINCT\n";
            $SQL .= join(', ', $selects);
            $SQL .= "\nFROM `{$childListForeignModuleID}`\n";

            if(count($joins) > 0){
                $joins = SortJoins($joins);
                $SQL .= join("\n", $joins);
            }

            //branch off getSQL here
            $idField = $IDModuleField->getQualifiedName($childListForeignModuleID);
            $this->getSQL = $SQL . "\nWHERE $idField = '/*recordID*/'";
            CheckSQL($this->getSQL);

            $SQL .= "\nWHERE `{$childListForeignModuleID}`._Deleted = 0\n";
            $SQL .= "AND $idField IS NOT NULL\n";

            $SQL .= " ORDER BY Name, ID, ParentID;";

            CheckSQL($SQL);

            print "$debug_prefix SQL = \n";
            indent_print_r($SQL);

            debug_unindent();
            return $SQL;
        }
    }
    debug_unindent();
}


function buildSQL($options = null){
    $debug_prefix = debug_indent("ComboField-buildSQL: {$this->moduleID}.{$this->name}");
    print "$debug_prefix foreignTable {$this->foreignTable}\n";
    print "$debug_prefix foreignKey {$this->foreignKey}\n";
    print "$debug_prefix foreignField {$this->foreignField}\n";
    trace($options, 'buildSQLoptions');

    //this include contains utility functions
    require_once(INCLUDE_PATH . '/general_util.php');

    $localMFs = GetModuleFields($this->moduleID);  //module fields of the local module (i.e. same as the form this field is part of)

    if(!empty($this->listField)){
        $localField = $localMFs[$this->listField];

        //list module is the one that provides the list
        $listModuleID = $localField->foreignTable;
        $keyName = $localField->foreignKey;
        $fieldName = $localField->foreignField;
    } else {
        if('ID' == substr($this->name, -2)){
            $localValueFieldName = substr($this->name, 0, -2);
            $localField = $localMFs[$localValueFieldName];

            //list module is the one that provides the list
            $listModuleID = $localField->foreignTable;
            $keyName = $localField->foreignKey;
            $fieldName = $localField->foreignField;
        } else {
            //the org list in a personComboField would use this (ends with '_org')
            $localValueFieldName = $this->foreignField;

            $listModuleID = $this->foreignTable;
            $keyName = $this->foreignKey;
            $fieldName = $this->foreignField;
        }
    }

    if(!empty($this->parentField)){
        $hasParent = true;
    } else {
        $hasParent = false;
    }


    print "$debug_prefix local value field: $localValueFieldName.\n";
    print "$debug_prefix list module id: $listModuleID\n";

    $listModuleFields = GetModuleFields($listModuleID);
    print "$debug_prefix got list module fields.\n";

    $listFields = array();
    $listFields['ID']   = $listModuleFields[$keyName];
    if(!is_object($listFields['ID'])){
        die("$debug_prefix List field '$listModuleID.$keyName' is invalid.");
    }

    $listFields['Name'] = $listModuleFields[$fieldName];
    if(!is_object($listFields['Name'])){
        die("$debug_prefix List field '$listModuleID.$fieldName' is invalid.");
    }

    if($hasParent){
        //this is required for Dan's "three-level filtering" (a misnomer)
        //the purpose is to add a temporary ForeignField to the listModuleFields that emulates the parent ID
        if(count($this->childFields) == 1){
            $SQL = $this->_buildSQLwChildDep();
            if(!empty($SQL)){
                debug_unindent();
                return $SQL;
            }
        }

        //if there is a parentListModuleField, use it
        if(!empty($this->parentListModuleField)){
            $listFields['ParentID'] = $listModuleFields[$this->parentListModuleField];
            if(empty($listFields['ParentID'])){
                die("$debug_prefix parentListModuleField {$this->parentListModuleField} does not match a module field in module '{$localFields['Name']->moduleID}'");
            }
        } else {
            //otherwise, guess the name, with some assumptions

            //see if the name is the same in local and list modules
            if(isset($listModuleFields[$this->parentField])){
                $listFields['ParentID'] = $listModuleFields[$this->parentField];
            }
            if(empty($listFields['ParentID'])){

                //get the parent field in the local module
                $parentField = $localMFs[$this->parentField];
                $localParentField = $localMFs[$parentField->name];

print "$debug_prefix localParentField = {$localParentField->name}\n";
//print_r($localParentField);

                //get the foreignField of that field in the list module
                if(isset($localParentField->foreignField)){
                    $localParentFieldName = $localParentField->foreignField;
                    if(isset($listModuleFields[$localParentFieldName])){
                        $listFields['ParentID'] = $listModuleFields[$localParentFieldName];
                    }
                }
            }

            //a different try:
            if(empty($listFields['ParentID'])){
                //parent field name w/o ID 
                $localParentValueFieldName = substr($localParentField->name, 0, -2);
                $localParentValueField = $localMFs[$localParentValueFieldName];

                //get the foreignKey of that field in the list module
                $localParentFieldName = $localParentValueField->foreignKey;
                $listFields['ParentID'] = $listModuleFields[$localParentFieldName];
            }

            if(empty($listFields['ParentID'])){
                die("$debug_prefix parent field {$this->parentField} does not match a module field in module '{$listModuleID}'. Try adding a parentListModuleField to {$this->name}");
            }
        }
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $listModuleID;
    print "$debug_prefix Base ModuleID used when generating SQL: {$SQLBaseModuleID}\n";

    $selects = array();
    $joins = array();

print "$debug_prefix listModuleID: {$listModuleID}\n";
//print_r($localFields['Name']);
//print "$debug_prefix fieldName = $fieldName\n";
//print_r($listModuleFields);
//print_r($listFields);

    foreach($listFields as $name => $field){
        //$selects[] = $field->makeSelectDef($listModuleID, false)." AS $name";
        //$joins = array_merge($field->makeJoinDef($listModuleID), $joins);
        $selects[] = GetQualifiedName($field->name, $listModuleID) . " AS $name";
        $joins = array_merge($joins, GetJoinDef($field->name, $listModuleID));
    }

    $orderBys = array();
    if(count($this->orderByFields) > 0){
        foreach($this->orderByFields as $orderByFieldName => $desc){
            $joins = array_merge($joins, GetJoinDef($orderByFieldName, $listModuleID));
            $order = '';
            if($desc){
                $order = ' DESC';
            } 
            $orderBys[] = GetQualifiedName($orderByFieldName, $listModuleID) . $order;
        }
    }

    //check for a OwnerOrganization field
    $moduleInfo = GetModuleInfo($listModuleID);
    $ownerField = $moduleInfo->getProperty('ownerField');

    if(!empty($ownerField)){
        $ownerMF = $listModuleFields[$ownerField];

        if(empty($ownerMF)){
            print_r($listModuleFields);
            die("$debug_prefix OwnerField $ownerField does not match a field in $listModuleID ModuleFields");
        }

        //$this->ownerFieldFilter = $ownerMF->getQualifiedName($listModuleID) . ' IN (%s)';
        //$joins = array_merge($ownerMF->makeJoinDef($listModuleID), $joins);
        $this->ownerFieldFilter = GetQualifiedName($ownerMF->name, $listModuleID) . ' IN (%s)';
        $joins = array_merge($joins, GetJoinDef($ownerMF->name, $listModuleID));
    }

    $wheres = array();
    if(count($this->listConditions) > 0){
        foreach($this->listConditions as $listCondition){
            $exprArray = $listCondition->getExpression($listModuleID);
            $joins = array_merge($exprArray['joins'], $joins);
            $wheres[] = $exprArray['expression'];
        }
    }

    $SQL = "SELECT \n";// {$keySelect} AS ID, $fieldSelect AS NAME\n";
    $SQL .= join(', ', $selects);

    if(!empty($options) && in_array('orgListOption', $options)){
        if('org' != $listModuleID){
            $orgAlias = GetTableAlias($listFields['Name'], $listModuleID);
            foreach($options as $optKey => $optVal){
                $options[$optKey] = str_replace('`org`', "`$orgAlias`", $optVal);
            }
        }
        $SQL .= ",\n".$options['case'];
    }

    $SQL .= " FROM `{$listModuleID}`\n";

    if(count($joins) > 0){
        $joins = SortJoins($joins);
        indent_print_r($joins);
        foreach($joins as $j){
            $SQL .= " $j\n";
        }
    }

    //branch off getSQL here
    $idField = $listFields['ID']->getQualifiedName($listModuleID);
    $this->getSQL = $SQL . "\nWHERE $idField = '/*recordID*/'";
    CheckSQL($this->getSQL);


    $SQL .= "WHERE {$listModuleID}._Deleted = 0\n";
    if (!empty($localField->listCondition)){
        $SQL .= " AND {$localField->listCondition}\n";
    }
    if(count($wheres) > 0){
        foreach($wheres as $where){
            $SQL .= " AND {$where}\n";
        }
    }
    if(!empty($options) && in_array('orgListOption', $options) && !empty($options['where'])){
        $SQL .= ' AND '.$options['where']."\n";
    }

    if(!empty($options) && in_array('orgListOption', $options) && $options['use_orderby']){
        $SQL .= " ORDER BY orgListOption, Name, ID;";
    } else {
        if(count($orderBys) > 0){
            $SQL .= ' ORDER BY ' .join(',',$orderBys). ', Name, ID;';
        } else {
            $SQL .= ' ORDER BY Name, ID;';
        }
    }

    CheckSQL($SQL);

    print "$debug_prefix SQL = \n";
    indent_print_r($SQL);

    debug_unindent();
    return $SQL;
}


//adds filters based on permissions and list conditions
function getFilteredSQL(){
    global $User;

    $SQL = $this->SQL;

    $filterSQL = sprintf($this->ownerFieldFilter, join(',', $User->getPermittedOrgs($this->foreignTable)));
    $SQL = str_replace('ORDER', ' AND '.$filterSQL . ' ORDER', $SQL);
    return $SQL;
}


function getFindHTML(){
    if('text' == $this->findMode){
        return sprintf(FORM_FINDMODE_TEXT_HTML, $this->name);
    } else {
        return '';
    }
}


function getAddNewLink()
{
    $addNewLink = '';
    if(!$this->suppressItemAdd && 'search' != $this->renderMode){
        $addNewLink = "<a href=\"#\" onclick=\"window.open('frames_popup.php?dest=edit&amp;mdl={$this->foreignTable}', '{$this->name}AddItem', 'toolbar=0,resizable=1')\">+</a>";
    }

    return $addNewLink;
}


function getListData(&$selected, &$values, $format = null, $isDefault = false){
    //connects to database and retrieves the data
    //think of some form of caching of list items in the browser
    global $dbh;
    global $User;
    global $recordID;

    $content = '';
    $SQL = $this->SQL;

    //check the user's permission to the list module
    switch(intval($User->PermissionToView($this->foreignTable))){
    case 2:
        break;
    case 1:
        $SQL = $this->getFilteredSQL();
        break;
    default:
        $moduleInfo = GetModuleInfo($this->foreignTable);
        $foreignModuleName = $moduleInfo->getProperty('moduleName');
        $msg = sprintf(gettext("Permission Error:  You have no permission to view records in the %s module"), $foreignModuleName);
        if(empty($this->parentField)){
            $content = "<option value=\"0\">$msg</option>\n";
        } else {
            $content = "ar{$this->name}[0] =  new Array(0, \"$msg\", 0);\n";
        }
        trigger_error($msg, E_USER_WARNING);
        return $content;
    }

    $systemItems = array(0 => array(gettext("(unselected)")));
    switch($this->renderMode){
    case 'search':
        if(empty($this->validate)){
            $systemItems[-1] = array(gettext("(match all empty values)"));
            $systemItems[-2] = array(gettext("(match all non-empty values)"));
        }
        break;
    default:
        break;
    }

    $SQL = PopulateValues($SQL, $values);

    $listItems = $dbh->getAssoc($SQL, true);
    dbErrorCheck($listItems, false);
    if(!$isDefault && !empty($selected) && !array_key_exists($selected, $listItems)){
        $missingItem = $dbh->getAssoc(str_replace('/*recordID*/', $selected, $this->getSQL), true);
        dbErrorCheck($missingItem);
        $listItems = $listItems + $missingItem; //array_merge would re-sequence the index IDs
    }
    $listItems = $systemItems + $listItems;

    //this handles lookups by list values instead of ID
    if(!empty($selected) && false !== strpos($selected, 'lookup:')){
        list(,$lookupValue) = explode(':', $selected, 2);
        foreach($listItems as $id=>$item){
            if(strtolower($item[0]) == strtolower($lookupValue)){
                $selected = $id;
                break;
            }
        }
    }

    if('php' == $format){
        foreach($listItems as $item_ix => $listItem){
            $listItems[$item_ix] = str_replace('&amp;', '&', $listItem);
        }
        return $listItems;
    } else {
        if(empty($this->parentField)){
            if($selected === null){
                $noselect = true;
            } else {
                $noselect = false;
            }
            foreach($listItems as $id=>$arItem){
                if (!$noselect && $id == $selected){
                    $content .= "<option selected=\"selected\" value=\"$id\">$arItem[0]</option>\n";
                } else {
                    $content .= "<option value=\"$id\">$arItem[0]</option>\n";
                }
            }
        } else {
            //we're building a javaScript array
            //put together JavaScript array definiton for the items
            $i = 0;
            foreach($listItems as $id=>$arItem){
                $content .= "\tar{$this->name}[{$i}] =  new Array({$id}, \"". str_replace(array("\n", "\r"), '', $arItem[0]) ."\", \"{$arItem[1]}\");\n";
                $i++;
            }

        }
    }
    return $content;
}


//override: Combo box type grid fields must display their related foreignField when viewed.
function viewRender(&$values)
{

    //$relatedField = substr($this->name, 0, -2);
    //return ' '.htmlspecialchars(stripslashes($values[$relatedField]), ENT_QUOTES).' ';
    return ' '.htmlspecialchars(stripslashes($values[$this->listField]), ENT_QUOTES).' ';
}


function simpleRender(&$values)
{
    if(0 == count($this->childFields)){
        $refreshJS = '';
    } else {
        $refreshJS = "UpdateFields('{$this->formName}', this, new Array('" .join(array_keys($this->childFields), "','"). "')); return true;";
    }
    $this->isDefault = false;
    $selected = null;
    $listName = substr($this->name, 0, -2);
    if(!empty($values[$this->name])){
        $selected = $values[$this->name];
    } elseif(!empty($_GET[$listName])) {
        $selected = 'lookup:'.$_GET[$listName];
    } else {
        $selected = $this->getDefaultValue($values);
        if(!empty($selected)){
            $this->isDefault = true;
        }
    }

    $addNewLink = $this->getAddNewLink();

    if(empty($this->parentField)){
        //this is a regular combo field, without child fields to update
        return $this->inlinePreContent.' '.
            sprintf(
                FORM_DROPLIST_HTML,
                $this->name,
                $this->getListData($selected, $values, null, $this->isDefault),
                $refreshJS,
                $this->getFindHTML(),
                $addNewLink,
                ''
            )
            .' '.$this->inlinePostContent;
    } else {

        //FormName, FieldName, CurrentIDValue (at page load), ChildNames, JavaScript Array definition
        return $this->inlinePreContent.' '.
            sprintf(
                FORM_FILTER_CB_HTML,
                $this->formName,
                $this->name,
                $selected,
                '',
                $this->getListData($selected, $values),
                $this->parentField,
                $refreshJS,
                $this->getFindHTML(),
                $addNewLink,
                ''
            )
            .' '.$this->inlinePostContent;
    }
}


function dataCollectionRender(&$values, $format = 'html')
{
    $selected = 0;
    $items = $this->getListData($selected, $values, 'php');
    if(count($items) > 10){
        return '';
    } else {
        $content = '';
        if(count($items) > 0){
            foreach($items as $item){
                if(is_array($item)){
                    $content .= ' ['.$item[0].'] ';
                } else {
                    $content .= ' ['.$item.'] ';
                }
            }
        }
        return trim($content);
    }
}



//this function returns the field names to be added to a SELECT
//statement on account of this field. defaults to own name only.
function getSelectFields()
{
    $fields = array();
    if(count($this->Fields) > 0){
        foreach($this->Fields as $field_name => $field){
            if('Self' != $field_name){
                $fields = $field->getSelectFields();
            }
        }
    }
    //$listFieldName = substr($this->name, 0, -2);
$listFieldName = $this->listField;
    if(!empty($listFieldName)){
        if(!array_key_exists($listFieldName, $fields)){
            $fields[$listFieldName] = true;
        }
    }
    $fields[$this->name] = true;
    return $fields;
}


function getSearchPhrase(&$data, &$moduleFields)
{
    $content = '';

    //global $phrases;
    if(empty($this->phrase)){
        $phrase = $moduleFields[$this->name]->phrase;
    } else {
        $phrase = $this->phrase;
    }
	$phrase =gettext($phrase);

    global $dbh;
    $SQL = $this->SQL;

    $selected = null;
    $items = $this->getListData($selected, $data, 'php');
    $items = array(-1 => gettext("All empty values"), -2 => gettext("All non-empty values")) + (array)$items;

    $item = $items[$data[$this->name]];
    if(is_array($item)){
        $item = $item[0];
    }
    $content .= ShortPhrase($phrase) . ': ' . $item;

    return $content;
}


/**
 *  Determines whether the user entered a search expression for this field.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name])){
        return false;
    }

    $value = trim($data[$this->name]);
    if(!empty($value)){
        return true;
    } else {
        return false;
    }
}


/**
 *  Returns the expression used in the WHERE clause in searches
 */
function getSearchCondition(&$data)
{
    $name = GetQualifiedName($this->name);

    $value = intval($data[$this->name]);
    switch($value){
    case -1:
        return "$name IS NULL";
    case -2:
        return "$name IS NOT NULL";
    default:
        $value = $this->quoteValue($data[$this->name]);
        return "$name = $value";
    }
}
}//end class ComboField



class CodeComboField extends ComboField
{
var $codeTypeID;

function &Factory($element, $moduleID)
{
    $field = new CodeComboField($element, $moduleID);
    return $field;
}


function CodeComboField(&$element, $moduleID)
{
    $this->name =  $element->getAttr('name', true);

    $moduleFields = GetModuleFields($moduleID);
    $moduleField = $moduleFields[$this->name];

    //$listfield_name = substr($this->name, 0, -2);
    $listfield_name = $element->getAttr('listField');
    if(empty($listfield_name)){
        $listfield_name = substr($this->name, 0, -2);
    }
    $this->listField = $listfield_name;
    if(!isset($moduleFields[$listfield_name])){
        trigger_error("CodeComboField {$this->name} requires a module field named $listfield_name.", E_USER_ERROR);
    }
    $list_moduleField = $moduleFields[$listfield_name];

    //assign list condition/filter
    $list_filter = $element->getAttr('listFilter');
    if (empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $formName = $element->getAttr('formName');
    if(empty($formName)){
        $formName = 'mainForm';
    }

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    if(!isset($list_moduleField->codeTypeID)){
        $list_moduleField_type = get_class($list_moduleField);
        trigger_error("The CodeComboField {$this->name} requires the $list_moduleField_type $moduleID.{$list_moduleField->name} to be a CodeField.", E_USER_ERROR);
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    $this->foreignTable = 'cod';
    $this->foreignKey = 'CodeID';
    $this->foreignField = 'Description';
    $this->listCondition = $list_filter;
    $this->listConditions = $list_moduleField->listConditions;
    $this->parentField = $element->getAttr('parentField');
    $this->parentListModuleField = $element->getAttr('parentListModuleField');
    $this->codeTypeID = $list_moduleField->codeTypeID;
    $this->validate = $validate;
    $this->moduleID = $moduleID;
    $this->formName = $formName;
    $this->suppressItemAdd = strtolower($element->getAttr('suppressItemAdd')) == 'yes';
    $this->defaultValue = $moduleField->defaultValue;

    switch(strtolower($element->getAttr('findMode'))) {
    case 'text':
    case 'alpha':
        $this->findMode = 'text';
        break;
    default:
    }


    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }

    $this->_handleSubElements($element->c);
    $this->SQL = $this->buildSQL();

    //add sort order to sort conditions
    $this->SQL = str_replace(' ORDER BY Name, ID;', ' ORDER BY SortOrder, Name, ID;', $this->SQL);
}


function getAddNewLink()
{
    $addNewLink = '';
    if(!$this->suppressItemAdd && 'search' != $this->renderMode){
        $dest = "edit.php?mdl=codt&amp;scr=Items&amp;rid={$this->codeTypeID}";
        $dest = base64_encode($dest);
        $addNewLink = "<a href=\"#\" onclick=\"window.open('frames_popup.php?dest=$dest', '{$this->name}AddItem', 'toolbar=0,resizable=1')\">+</a>";
    }

    return $addNewLink;
}
}//end class CodeComboField



class OrgComboField extends ComboField
{

function &Factory($element, $moduleID)
{
    $field = new OrgComboField($element, $moduleID);
    return $field;
}


function OrgComboField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);

    if('_org' == substr($this->name, -4)){
        $moduleFields = GetModuleFields('ppl');
        $moduleField = $moduleFields['OrganizationID'];
        $listfield_name = 'Organization';
    } else {
        $moduleFields = GetModuleFields($moduleID);
        $moduleField = $moduleFields[$this->name];
        $listfield_name = $element->getAttr('listField');
        if(empty($listfield_name)){
            $listfield_name = substr($this->name, 0, -2);
        }
    }
    if(!isset($moduleFields[$listfield_name])){
        trigger_error("OrgComboField {$this->name} requires a module field named $listfield_name.", E_USER_ERROR);
    }
    $list_moduleField = $moduleFields[$listfield_name];
    $this->listField = $listfield_name;

    //assign list condition/filter
    $list_filter = $element->getAttr('listFilter');
    if(empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $formName = $element->getAttr('formName');
    if(empty($formName)){
        $formName = 'mainForm';
    }

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    $this->foreignTable = 'org';
    $this->foreignKey = 'OrganizationID';
    $this->foreignField = 'Name';
    $this->listCondition = $list_filter;
    $this->parentField = $element->getAttr('parentField');
    $this->validate = $validate;
    $this->moduleID = $moduleID;
    $this->formName = $formName;
    $this->suppressItemAdd = strtolower($element->getAttr('suppressItemAdd')) == 'yes';
    $this->findMode = 'text';

    $this->defaultValue = $moduleField->defaultValue;
    $this->listConditions = $list_moduleField->listConditions;

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    $this->_handleSubElements($element->c);
    $orgListOptions = $element->getAttr('orgListOptions');
    if(!empty($moduleField->orgListOptions)){
        $orgListOptions = $moduleField->orgListOptions;
    }

    $this->SQL = $this->buildSQL($this->parseOrgListOptions($orgListOptions));
}

/**
 *  Parses an orgListOptions value and returns an array with the necessary SELECT and WHERE modifications
 */
function parseOrgListOptions($strListOptions)
{
    if(empty($strListOptions)){
        return null;
    }

    //initialization
    $cases = array();
    $wheres = array();
    $orderby = array();

    //keywords
    $internal_keywords = array(
        'int' => '`org`.Internal = 1',
        'ext' => '`org`.Internal = 0',
        'all' => '');
    $participant_keywords = array(
        'par' => '`org`.Participant = 1',
        'non' => '`org`.Participant = 0',
        'all' => '');

    /**
     * These must not overlap, i.e. one keyword must not be a substring of another. Also
     * characters 1-3 must not match one of the "internal keywords, and characters 4-6 must
     * not match one of the "participant" keywords.
     */
    $special_keywords = array(
        'accrediting_body' => '`org`.AccreditingBody = 1',
        'contractor' => '`org`.Contractor = 1',
        'customer' => '`org`.Customer = 1',
        'disposal_facility' => '`org`.DisposalFacility = 1',
        'government_agency' => '`org`.GovAgency = 1',
        'insurance_broker' => '`org`.InsuranceBroker = 1',
        'insurance_carrier' => '`org`.InsuranceCarrier = 1',
        'law_firm' => '`org`.LawFirm = 1',
        'manufacturer' => '`org`.Manufacturer = 1',
        'medical_provider' => '`org`.MedicalProvider = 1',
        'reinsurer' => '`org`.Reinsurer = 1',
        'standards_provider' => '`org`.StandardsProvider = 1',
        'supplier' => '`org`.Supplier = 1',
        'waste_transporter' => '`org`.WasteTransporter = 1'
        );

    $use_wheres = (false === strpos($strListOptions, 'all_others'));
    if(!$use_wheres){
        $strListOptions = str_replace('all_others', '', $strListOptions);
        $strListOptions = trim($strListOptions);
    }

    $arListOptions = explode(' ', $strListOptions);

    $use_orderby = false;
    if(count($arListOptions) > 1){
        $use_orderby = true;
    }

    foreach($arListOptions as $key => $listOption){
        $listOption = trim($listOption);
        $statuses = array();

        $strInt = substr($listOption, 0, 3);
        if(!empty($internal_keywords[$strInt])){
            $statuses[] = $internal_keywords[$strInt];
        }

        $strPar = substr($listOption, 3, 3);
        if(!empty($participant_keywords[$strPar])){
            $statuses[] = $participant_keywords[$strPar];
        }

        foreach($special_keywords as $special => $special_keyword){
            if(false !== strpos($listOption, $special)){
                $statuses[] = $special_keyword;
            }
        }

        $cases[] = 'WHEN '. join(' AND ', $statuses) .' THEN '.$key;;
        if($use_wheres){
            $wheres[] = '('.join(' AND ', $statuses).')';
        }
    }

    $case = 'CASE '.join("\n", $cases)."\nELSE ".count($arListOptions).' END AS orgListOption';
    $where = '';
    if(count($wheres) > 0){
        $where = '('.join("\nOR ", $wheres).')';
    }
    return array('type'=>'orgListOption', 'case'=>$case, 'where'=>$where, 'use_orderby'=>$use_orderby);
}
}



class PersonComboField extends ComboField
{
var $orgField; //limiting org field


function &Factory($element, $moduleID)
{
    $field = new PersonComboField($element, $moduleID);
    return $field;
}


function PersonComboField(&$element, $moduleID)
{
    $fieldName = $element->getAttr('name', true);

    $moduleFields = GetModuleFields($moduleID);
    $moduleField = $moduleFields[$fieldName];

    $listfield_name = substr($fieldName, 0, -2);
    if(!isset($moduleFields[$listfield_name])){
        trigger_error("PersonComboField {$fieldName} requires a module field named $listfield_name.", E_USER_ERROR);
    }
    $list_moduleField = $moduleFields[$listfield_name];

    //assign list condition/filter
    $list_filter = $element->getAttr('listFilter');
    if(empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $formName = $element->getAttr('formName');
    if(empty($formName)){
        $formName = 'mainForm';
    }

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->foreignTable = 'ppl';
    $this->foreignKey = 'PersonID';
    $this->foreignField = 'DisplayName';
    $this->listCondition = $list_filter;
    $this->formName = $formName;
    $this->validate = $validate;
    $this->defaultValue = $moduleField->defaultValue;
    $this->moduleID = $moduleID;
    $this->suppressItemAdd = strtolower($element->getAttr('suppressItemAdd')) == 'yes';
    $this->findMode = 'text';

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    if (!empty($pSQL)){
        $this->SQL = $pSQL;
    } else {
        $this->SQL = $this->buildSQL();
    }

    $orgListOptions = '';
    if(!empty($moduleField->orgListOptions)){
        $orgListOptions = $moduleField->orgListOptions;
    }

    $orgField =& MakeObject(
        'ppl',
        $this->name.'_org',
        'OrgComboField',
        array(
            'name' => $this->name.'_org',
            'foreignTable' => 'org',
            'foreignKey' => 'OrganizationID',
            'foreignField' => 'Name',
            'listCondition' => $element->getAttr('orgListCondition'),
            'formName' => $this->formName,
            'findMode' => $findMode,
            'orgListOptions' => $orgListOptions
        )
    );

    if('defaultorgID' == $this->defaultValue){
        $orgField->defaultValue = $this->defaultValue; //pass on defaultOrgID to org combo
        $this->defaultValue = null;
    }
    $this->orgField =& $orgField;

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Field' == substr($sub_element->type, -5)){
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
            if('UpdateFieldRef' == $sub_element->type){
                $this->childFields[$sub_element->name] = $sub_element->attributes;
            }
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            }
        }
    }
}


function simpleRender(&$values)
{
    global $dbh;

    if(!empty($values[$this->name])){
        $value = $values[$this->name];
    } else {
        $value = $this->getDefaultValue($values);
    }

    //get all orgs
    $selected = null;
    $orgList = $this->orgField->getListData($selected, $values, 'php');

    //put together JavaScript array definiton for Orgs
    $jsCode = '';
    $i = 0;
    foreach($orgList as $orgID => $row){
        $jsCode .= "\tar{$this->name}Orgs[{$i}] =  new Array({$orgID}, \"{$row[0]}\");\n";
        $i++;
    }

    $systemItems = array(0 => array(0, gettext("(unselected)"), 0));
    switch($this->renderMode){
    case 'search':
        if(empty($this->validate)){
            $systemItems[] = array(-1, gettext("(match all empty values)"), 0);
            $systemItems[] = array(-2, gettext("(match all non-empty values)"), 0);
        }
        break;
    default:
        break;
    }

    //get all people (but filter by orgListCondition)
    $SQL = 'SELECT PersonID, DisplayName, OrganizationID FROM ppl WHERE _Deleted = 0 ORDER BY DisplayName';

    //put together JavaScript array definiton for People
    //get data
    $r = $dbh->getAll($SQL);
    dbErrorCheck($r);
    $r = array_merge($systemItems, $r); //numeric keys mean items will be appended, not overwritten

    $personListFieldName = substr($this->name, 0, -2);
    if(empty($values[$this->name]) && !empty($_GET[$personListFieldName])) {
        $personName = $_GET[$personListFieldName];
        foreach($r as $row){
            if($personName == $row[1]){
                $value = $row[0];
                break;
            }
        }
    }

    //put together JavaScript array definiton for Orgs
    $i = 0;
    foreach($r as $row){
        $jsCode .= "\tar{$this->name}People[{$i}] = new Array({$row[0]}, \"".addslashes($row[1])."\", {$row[2]});\n";
        $i++;
    }

    $addNewLink = $this->getAddNewLink();

    //format the whole thing
    //FormName, PersonFieldName, PersonIDValue, JavaScript Array definition
    $content = sprintf(
            FORM_PERSON_CB_HTML,
            $this->formName,
            $this->name,
            $value,
            $jsCode,
            $this->getFindHTML(),
            $this->orgField->getFindHTML(),
            $addNewLink
        );

    return $this->inlinePreContent.$content.$this->inlinePostContent;
}
}



class RadioField extends ComboField
{
var $orientation;


function &Factory($element, $moduleID)
{
    $field = new RadioField($element, $moduleID);
    return $field;
}


function RadioField(&$element, $moduleID)
{
    $fieldName = $element->getAttr('name', true);

    $moduleFields = GetModuleFields($moduleID);
    $moduleField = $moduleFields[$fieldName];
    $listfield_name = substr($fieldName, 0, -2);
    if(!isset($moduleFields[$listfield_name])){
        trigger_error("RadioField {$fieldName} requires a module field named $listfield_name.", E_USER_ERROR);
    }
    $list_moduleField = $moduleFields[$listfield_name];

    //assign list condition/filter
    $list_filter = $element->getAttr('listFilter');
    if(empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->foreignTable = $list_moduleField->foreignTable;
    $this->foreignKey = $list_moduleField->foreignKey;
    $this->foreignField = $list_moduleField->foreignField;
    $this->listCondition = $list_filter;
    $this->orientation = $element->getAttr('orientation');
    $this->validate = $moduleField->validate;
    $this->moduleID = $moduleID;

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    $this->defaultValue = $moduleField->defaultValue;

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Field' == substr($sub_element->type, -5)){
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            } elseif('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            } elseif('OrderBy' == $sub_element->type) {
                $fieldName = $sub_element->getAttr('name', true);
                $desc = false;
                if('desc' == strtolower($sub_element->getAttr('direction'))){
                    $desc = true;
                }
                $this->orderByFields[$fieldName] = $desc;
            }
        }
    }

    $this->listConditions = $list_moduleField->listConditions;
    if (!empty($list_moduleField->sql)){
        $this->SQL = $list_moduleField->sql;
    } else {
        $this->SQL = $this->buildSQL();
    }
}


function getListData($selected, &$values, $format = null)
{
    //connects to database and retrieves the data
    global $dbh;
    $SQL = $this->SQL;

    //populate any dynamic field conditions with the proper values
    $SQL = PopulateValues($SQL, $values);
trace($SQL, 'RadioField SQL');
    $r = $dbh->getAssoc($SQL);
    dbErrorCheck($r);

    if('search' == $this->renderMode || empty($this->validate)){
        $r['0'] = gettext("no value");
    }

    if('php' == $format){
        return $r;
    }

    $content = '<input type="hidden" name="'.$this->name.'" id="'.$this->name.'" value="'.$selected.'" class="edt" />';

    $checked = '';
    foreach($r as $id=>$name){
        if ($id == $selected){
            $checked = 'checked="checked"';
        } else {
            $checked = '';
        }

        $content .= sprintf(
            FORM_RADIOBUTTON,
            'r_'.$this->name,
            gettext($name),
            $checked .  ' onclick="setRadioValue(this, \''.$this->name.'\')"',
            $id
        );
        if('vertical' == $this->orientation){
            $content .= "<br \>\n";
        } else {
            $content .= "&nbsp;&nbsp;\n";
        }
    }
    //format the output as <option> values
    return $content;
}


function simpleRender(&$values)
{
    $value = null;
    if(isset($values[$this->name])){
        $value = $values[$this->name];
    } else {
        if('search' != $this->renderMode){
            $value = $this->getDefaultValue($values);
            if(!empty($value)){
                $this->isDefault = true;
            }
        }
    }

    return $this->inlinePreContent.' '.$this->getListData($value, $values).' '.$this->inlinePostContent;
}
}//end class RadioField



class CodeRadioField extends RadioField {


function &Factory($element, $moduleID)
{
    $field = new CodeRadioField($element, $moduleID);
    return $field;
}


function CodeRadioField(&$element, $moduleID)
{
    $fieldName = $element->getAttr('name', true);
    $moduleField = GetModuleField($moduleID, $fieldName);

    if('yes' == $element->getAttr('bool')){
        $listfield_name = $fieldName;

        $this->SQL = "SELECT CodeID AS ID, Description AS NAME FROM cod\n";
        $this->SQL .= " WHERE _Deleted = 0 AND cod.CodeTypeID = 10\n";
        $this->SQL .= " ORDER BY SortOrder, Name, ID;";
    } else {
        $listfield_name = substr($fieldName, 0, -2);
    }

    $list_moduleField = GetModuleField($moduleID, $listfield_name);

    //assign list condition/filter
    $list_filter = $element->getAttr('listFilter');
    if(empty($list_filter)){
        $list_filter = $list_moduleField->listCondition;
    }

    $validate = '';
    if(isset($moduleField->validate)){
        $validate = $moduleField->validate;
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->foreignTable = 'cod';
    $this->foreignKey = 'CodeID';
    $this->foreignField = 'Description';
    $this->listCondition = $list_filter;
    $this->orientation = $element->getAttr('orientation');
    $this->validate = $validate;
    $this->moduleID = $moduleID;

    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        $this->phrase = $moduleField->phrase;
    }
    $this->inlinePreContent  = $this->_inlineFormat($element->getAttr('inlinePreContent'));
    $this->inlinePostContent = $this->_inlineFormat($element->getAttr('inlinePostContent'));

    $this->formName = $element->getAttr('formName');
    if(empty($this->formName)){
        $this->formName = 'mainForm';
    }

    if(empty($this->SQL)){
        $this->SQL = $this->buildSQL();

        //add sort order to sort conditions
        $this->SQL = str_replace(' ORDER BY Name, ID;', ' ORDER BY SortOrder, Name, ID;', $this->SQL);
    }

    $this->defaultValue = $moduleField->defaultValue;

    $this->gridAlign = $element->getAttr('gridAlign');
    if(empty($this->gridAlign)){
        $this->gridAlign = $list_moduleField->getGridAlign();
    }

    $this->displayFormat = $element->getAttr('displayFormat');
    if(empty($this->displayFormat)){
        $this->displayFormat = $list_moduleField->displayFormat;
    }


    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Field' == substr($sub_element->type, -5)){
                $sub_element->attributes['formName'] = $this->formName;
                $this->Fields[$sub_element->name] = $sub_element->createObject($moduleID);
            }
            if('Self' == $sub_element->type){
                $this->Fields['Self'] = $this;
            }
        }
    }

}
}//end class CodeRadioField






////////////////////////////////////
//
// Search-Only classes
//
////////////////////////////////////

//abstract base class
class SearchField extends ScreenControl
{
var $conditionField;
var $conditionValue;
var $name;
var $formName = 'mainForm';

var $subModuleID;
var $listModuleID;
var $subModuleKey;
var $listKey;
var $listField;
var $listSQL;
var $isSearchOnly = true;
var $phrase;
var $moduleID;


function isEditable()
{
    return true;
}


function searchRender(&$values, &$phrases)
{
    return false; //override this
}


function handleSearch(&$data, &$moduleFields)
{
    return null; //override this
}


function checkSearch(&$data)
{
    return null; //to be overridden
}


//returns a human-readable expression of the search criteria.  (won't use $moduleFields)
function getSearchPhrase(&$selectedValues)
{
    return false; //override this
}

//adds filters based on permissions and list conditions
function getFilteredSQL(){
    global $User;

    $SQL = $this->listSQL;

    $filterSQL = sprintf($this->ownerFieldFilter, join(',', $User->getPermittedOrgs($this->subModuleID)));
    $SQL = str_replace('ORDER', ' AND '.$filterSQL . ' ORDER', $SQL);
    return $SQL;
}


function isSubField()
{
    return FALSE; //this field has no subfields
}
} //SearchField





class ComboSearchField extends SearchField
{

function &Factory($element, $moduleID)
{
    $field = new ComboSearchField($element, $moduleID);
    return $field;
}


function ComboSearchField(&$element, $moduleID)
{
    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);
    $this->subModuleID = $element->getAttr('subModuleID');
    $this->listModuleID = $element->getAttr('listModuleID');
    $this->subModuleKey = $element->getAttr('subModuleKey');
    $this->listKey = $element->getAttr('listKey');
    $this->listField = $element->getAttr('listField');
    $this->phrase = $element->getAttr('phrase');
    $this->moduleID = $moduleID;

    $this->listSQL = $this->_buildSQL();
}


function _buildSQL()
{
    $SQL = '';

    $listFields = array();
    $listFields['ID']   = GetModuleField($this->listModuleID, $this->listKey);
    if(!is_object($listFields['ID'])){
        die("ComboSearchField {$this->name}: List field '$listModuleID.$keyName' is invalid.");
    }

    $listFields['Name'] = GetModuleField($this->listModuleID, $this->listField);
    if(!is_object($listFields['Name'])){
        die("ComboSearchField {$this->name}: List field '$listModuleID.$fieldName' is invalid.");
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->listModuleID;
    print "ComboSearchField->_buildSQL: Base ModuleID used when generating SQL: {$SQLBaseModuleID}\n";

    $selects = array();
    $joins = array();

    foreach($listFields as $name => $field){
        if($name == 'ID'){
            $role = 'key'; 
        } else {
            $role = 'field';
        }

        //$selects[] = $field->makeSelectDef($SQLBaseModuleID, false)." AS $name";
        //$joins = array_merge($field->makeJoinDef($SQLBaseModuleID), $joins);
        $selects[] = GetQualifiedName($field->name, $SQLBaseModuleID) . " AS $name";
        $joins = array_merge($joins, GetJoinDef($field->name, $SQLBaseModuleID));
    }

    //check for a OwnerOrganization field
    $moduleInfo = GetModuleInfo($this->listModuleID);
    $ownerField = $moduleInfo->getProperty('ownerField');
    if(!empty($ownerField)){
        $ownerMF = GetModuleField($this->listModuleID, $ownerField);

        if(empty($ownerMF)){
            die("OwnerField $ownerField does not match a field in $this->listModuleID ModuleFields");
        }

        //$this->ownerFieldFilter = $ownerMF->getQualifiedName($SQLBaseModuleID) . ' IN (%s)';
        //$joins = array_merge($ownerMF->makeJoinDef($SQLBaseModuleID), $joins);
        $this->ownerFieldFilter = GetQualifiedName($ownerMF->name, $SQLBaseModuleID) . ' IN (%s)';
        $joins = array_merge($joins, GetJoinDef($ownerMF->name, $SQLBaseModuleID));
    }

    $SQL = "SELECT \n";// {$keySelect} AS ID, $fieldSelect AS NAME\n";
    $SQL .= join(', ', $selects);
    $SQL .= " FROM `{$this->listModuleID}`\n";

    if(count($joins) > 0){
        foreach($joins as $j){
            $SQL .= " $j\n";
        }
    }
    $SQL .= "WHERE {$this->listModuleID}._Deleted = 0\n";
    if (!empty($localField->listCondition)){
        $SQL .= " AND {$localField->listCondition}\n";
    }

    $SQL .= " ORDER BY Name, ID;";
    CheckSQL($SQL);

    print "ComboSearchField->_buildSQL: \n\t\tSQL = \n" . $SQL."\n";
    print "ComboSearchField->_buildSQL: {$this->name} (end)\n";

    return $SQL;
}


function _getListData($selected, &$values)
{
    //connects to database and retrieves the data
    //think of some form of caching of list items
    global $dbh;
    global $User;
    $SQL = $this->listSQL;

    //check the user's permission to the list module
    switch(intval($User->PermissionToView($this->listModuleID))){
    case 2:
        break;
    case 1:
        $SQL = $this->getFilteredSQL();
        break;
    default:
        //die('no permission');
        $moduleInfo = GetModuleInfo($this->listModuleID);
        $foreignModuleName = $moduleInfo->getProperty('moduleName');
        $msg = sprintf(gettext("Permission Error:  You have no permission to view records in the %s module"), $foreignModuleName);
        if(empty($this->parentField)){
            $content = "<option value=\"0\">$msg</option>\n";
        } else {
            $content = "ar{$this->name}[0] =  new Array(0, \"$msg\", 0);\n";
        }
        return $content;
    }

    global $recordID;

    //populate any dynamic field conditions with the proper values
    $SQL = PopulateValues($SQL, $values);
    $r = $dbh->getAssoc($SQL);
    dbErrorCheck($r, false);
    if(empty($this->parentField)){
        $content .= "<option value=\"0\">".gettext("(unselected)")."</option>\n";
        foreach($r as $id=>$name){
            if ($id == $selected){
                $content .= "<option selected=\"selected\" value=\"$id\">$name</option>\n";
            } else {
                $content .= "<option value=\"$id\">$name</option>\n";
            }
        }
    } else {
        $content = "ar{$this->name}[0] =  new Array(0, \"".gettext("(unselected)")."\", 0);\n";
        $i = 1;
        foreach($r as $id=>$name){
            $content .= "\tar{$this->name}[{$i}] =  new Array({$id}, \"". str_replace(array("\n", "\r"), '', $name[0]) ."\", \"{$name[1]}\");\n";
            $i++;
        }

    }
    //format the output as <option> values
    return $content;
}


//ComboSearchField
function searchRender(&$values, &$phrases)
{
    $this->defaultValue = '';

    if(0 == count($this->childFields)){
        $refreshJS = '';
    } else {
        $refreshJS = "UpdateChildCBs('{$this->formName}', this, new Array('" .join(array_keys($this->childFields), "','"). "')); return true;";
    }

    if(empty($this->parentField)){
        //this is a regular combo field, without child fields to update
        $content =  ' '.
            sprintf(
                FORM_DROPLIST_HTML,
                $this->name,
                $this->_getListData($values[$this->name], $values),
                $refreshJS,
                $this->getFindHTML(),
                '',
                '' //additional class
            )
            .' ';
    } else {

        //FormName, FieldName, CurrentIDValue (at page load), ChildNames, JavaScript Array definition
        $content = ' '.
            sprintf(
                FORM_FILTER_CB_HTML,
                $this->formName,
                $this->name,
                $values[$this->name],
                '',
                $this->getListData($values[$this->name], $values),
                $this->parentField,
                $refreshJS,
                $this->getFindHTML(),
                ''
            )
            .' ';
    }

    //format as field row
    $content = sprintf(
        FIELD_HTML,
        addslashes(LongPhrase($phrases[$this->name])),
        ShortPhrase($phrases[$this->name]),
        $content,
        'flbl',
        $this->formName.'.'.$this->name,
        ''
    );
    return $content;
}


function handleSearch(&$data, &$moduleFields)
{
    return null;
}


function getFindHTML(){
    if('text' == $this->findMode){
        return sprintf(FORM_FINDMODE_TEXT_HTML, $this->name);
    } else {
        return '';
    }
}
} //ComboSearchField



class CodeCheckSearchField extends SearchField
{
var $conditionField;
var $conditionValue;
var $subModuleID;
var $subModuleModuleIDField;
var $subModuleRecordIDField;
var $codeIDField;
var $codeTypeID;
var $recordIDField;
var $listCondition;
var $listSQL;
var $isSearchOnly = true;
var $phrase;
var $tableAlias;


function &Factory($element, $moduleID)
{
    $field = new CodeCheckSearchField($element, $moduleID);
    return $field;
}


function CodeCheckSearchField(&$element, $moduleID)
{
    $moduleInfo = GetModuleInfo($moduleID);
    $recordIDField = $element->getAttr('keyField');
    if(empty($recordIDField)){
        $recordIDField = $moduleInfo->getPKField();
    }

    $this->conditionField = $element->getAttr('conditionField');
    $this->conditionValue = $element->getAttr('conditionValue');
    $this->name = $element->getAttr('name', true);

    $this->subModuleID = $element->getAttr('subModuleID');
    $this->subModuleModuleIDField = $element->getAttr('subModuleModuleIDField');
    $this->subModuleRecordIDField = $element->getAttr('subModuleRecordIDField');
    $this->codeIDField = $element->getAttr('codeIDField');
    $this->codeTypeID = $element->getAttr('codeTypeID');
    $this->recordIDField = $recordIDField;
    $this->phrase = $element->getAttr('phrase');

    $this->listCondition = $element->getAttr('listCondition');
    $this->moduleID = $moduleID;

    $this->listSQL = $element->getAttr('listSQL');
    if (empty($this->listSQL)){
        $this->listSQL = "SELECT CodeID as ID, Description as NAME FROM cod\n";
        $this->listSQL .= " WHERE _Deleted = 0 AND CodeTypeID = {$this->codeTypeID} \n";
        if(!empty($this->listCondition)){
            $this->listSQL .= " AND {$this->listCondition}\n";
        }
        $this->listSQL .= " ORDER BY SortOrder, Name, ID;";
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;
    $this->assignParentJoinAlias($this->subModuleID.'_sub', $this->moduleID);
}


//CodeCheckSearchField
function render(&$values, &$phrases){
    return $this->searchRender($values, $phrases);
}


function searchRender(&$values, &$phrases)
{
    $this->defaultValue = '';
    $this->renderMode = 'search';
    $content = '';

    $checkedValues = array();
    if(!empty($values[$this->name])){
        $checkedValues = $values[$this->name];
    }

    //render as a list of checkboxes
    global $dbh;
    $r = $dbh->getAssoc($this->listSQL);
    dbErrorCheck($r);

    foreach($r as $id=>$name){
        $checked = '';
        if(in_array($id, $checkedValues)){
            $checked = 'checked';
        }
        $content .= sprintf(
            FORM_CHECKBOX,
            $this->name.'[]', //field name, 
            $this->name.'_'.$id,   //field ID, 
            $name, //short phrase, 
            $checked, //checked ("checked" or ''), 
            $id //value
        );
        $content .= "<br />\n";
    }

    //format as field row
    $content = sprintf(
        FIELD_HTML,
        addslashes(LongPhrase($phrases[$this->name])),
        ShortPhrase($phrases[$this->name]),
        $content,
        'flbl',
        $this->formName.'.'.$this->name,
        ''
    );

    return $content;
}


function &handleSearch(&$data, &$moduleFields)
{
    //check whether the user entered an expression for this field
    if ($this->checkSearch($data)){

        $from = array();
        //$where = array();
        $phrase = array();

        $values = array();  //list of checked values

        if(is_array($data[$this->name])){
            foreach($data[$this->name] as $value){
                $values[] = intval($value);
            }
        } else {
            $values[] = addslashes($data[$this->name]);
        }

        $selectFields = array();
        $selectFields[] = $this->subModuleRecordIDField;
        $selectFields[] = $this->codeIDField;
        $subJoins = array();
        $qualNames = array();
        foreach($selectFields as $selectField){
            $qualNames[$selectField] = GetQualifiedName($selectField, ($this->subModuleID));
            $subJoins = array_merge($subJoins, GetJoinDef($selectField, ($this->subModuleID)));
        }

        if(empty($this->subModuleModuleIDField)){
            $moduleIDCondition = '';
        } else {
            $subModuleModuleIDField = GetQualifiedName($this->subModuleModuleIDField, ($this->subModuleID));
            $moduleIDCondition = "AND {$subModuleModuleIDField} = '{$this->moduleID}' ";
            $subJoins = array_merge($subJoins, GetJoinDef($this->subModuleModuleIDField, ($this->subModuleID)));
        }

        $joinSQL = "INNER JOIN (SELECT DISTINCT {$qualNames[$this->subModuleRecordIDField]} FROM `{$this->subModuleID}` ";
        foreach($subJoins as $alias => $def){
            $joinSQL .= "$def\n";
        }
        $joinSQL .= "WHERE `{$this->subModuleID}`._Deleted = 0 {$moduleIDCondition}AND {$qualNames[$this->codeIDField]} IN (".join(',', $values).")) 
        AS {$this->subModuleID}_sub 
        ON (`{$this->moduleID}`.{$this->recordIDField} = {$this->subModuleID}_sub.{$this->subModuleRecordIDField})";

        $from[$this->subModuleID.'_sub'] = $joinSQL;

        $phrase[$this->name] = $this->getSearchPhrase($values);

        $searchDef = array(
            'f' => $from,
            'w' => array('1=1'),
            'p' => $phrase,
            'v' => array($this->name => $data[$this->name])
        );
    } else {
        $searchDef = NULL;
    }

    return $searchDef;
}


function assignParentJoinAlias($dependentAlias, $parentAlias)
{
    global $SQLBaseModuleID;
    global $gTableAliasParents;

    print "{$this->moduleID}.{$this->name}: setting gTableAliasParents['$SQLBaseModuleID']['$dependentAlias'] = $parentAlias\n";
    $gTableAliasParents[$SQLBaseModuleID][$dependentAlias] = $parentAlias;
}


/**
 *  Determines whether the user checked any value.
 *
 *  note: this could be improved to ignore both if NONE or ALL options are selected.
 */
function checkSearch(&$data)
{
    if(!isset($data[$this->name])){
        return false;
    }
    if(is_array($data[$this->name])){
        return count($data[$this->name]) > 0;
    }
    $value = trim($data[$this->name]);
    return !empty($value);
}


/**
 *  Returns a human-readable expression of the search criteria.
 */
function getSearchPhrase(&$selectedValues)
{
    $phrase = $this->phrase;
	$phrase =gettext($phrase);
	
    global $dbh;
    $labels = array();

    $r = $dbh->getAssoc($this->listSQL);
    dbErrorCheck($r);

    foreach($selectedValues as $selectedID){
        $labels[] = $r[$selectedID];
    }

    return ShortPhrase($phrase) . ': '.join(', ', $labels);
}


function isSubField()
{
    return false; //this field has no subfields
}
}//end class CodeCheckSearchField



///
/// ListCondition classes
///

class ListCondition
{
var $name;
var $mode;
var $values = array();


function Factory($element, $moduleID)
{
    $field = new ListCondition($element, $moduleID);
    return $field;
}


function ListCondition(&$element, $moduleID)
{
    $this->name = $element->getAttr('fieldName', true);
    $this->mode = $element->getAttr('mode');
    foreach($element->c as $sub_element){
        if('StaticValue' == $sub_element->type){
            $value = $sub_element->getAttr('value');
            switch(strtolower($value)){
            case 'true':
                $value = 'true';
                break;
            case 'false':
                $value = 'false';
                break;
            case 'null':
                $value = 'null';
                break;
            default:
                $value = '\''.$sub_element->getAttr('value').'\'';
                break;
            }
            $this->values[] = $value;
        }
        if('FieldValue' == $sub_element->type){
            $this->values[] = '\'[*'.$sub_element->getAttr('value').'*]\'';
        }
    }
}


function getExpression($listModuleID)
{
    $expression = '';
    global $SQLBaseModuleID;

    $fieldExpression = GetQualifiedName($this->name, $listModuleID);
    $joins = GetJoinDef($this->name, $listModuleID);


    switch($this->mode){
    case 'in':
        $strValues = join(',',$this->values);
        $expression = "$fieldExpression IN ($strValues)";
        break;
    case 'equals':
        $strValue = reset($this->values);
        $expression = "$fieldExpression = $strValue";
        break;
    default:
        trigger_error("Don't know how to handle ListCondition mode '{$this->mode}'.", E_USER_WARNING);
        break;
    }
    return array('expression' => $expression, 'joins' => $joins);
}
} //end class ListCondition
?>
