<?php
/**
 * Renderable grid classes
 *
 * This file contains class definitions for grids, i.e. components that 
 * provide a multi-record user interface to data in a (sub)module.
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
 * @version        SVN: $Revision: 1584 $
 * @last-modified  SVN: $Date: 2009-04-28 07:24:06 +0200 (Wt, 28 kwi 2009) $
 */



/**
 * Include the renderable field classes
 */
include_once CLASSES_PATH . '/components.php';



/**
 * abstract class, for properties and methods common to all grids
 */
class Grid extends ScreenControl
{
var $moduleID; //ModuleID of related sub-module
var $phrase;
var $number; //sequence number on the parent screen
var $Fields = array(); //list of fields
var $countSQL; //SELECT COUNT (*)
var $listSQL;
var $listExtended = false;
var $localKey; //name of field in the grid that corresponds with the parent record
var $parentKey; //name of key field in parent record
var $PKFields;
var $formatOptions = array(); //catch-all array for formatting modifications
var $fieldTypes = array(); //data types of select fields
var $fieldFormats = array(); //sprintf formatting strings

/**
 * Abstract factory method
 *
 * usage: parse-time
 */
function Factory($element, $moduleID)
{
    return false;
}


/**
 * Returns a documentation object that has the right data for a grid
 *
 * usage: parse-time
 */
function DocFactory($element, $moduleID)
{
    return new GridDoc($element, $moduleID);
}


/**
 * overridden for editable grids
 *
 * usage: parse-time
 */
function isEditable()
{
    return false;
}


/**
 * adds a field (descendant of ScreenField) to the grid
 *
 * usage: parse-time
 */
function AddField($field)
{
    $this->_checkFieldClass($field);
    $this->Fields[$field->name] = $field;
}


function _checkFieldClass(&$field)
{
    if(!is_a($field, 'screenfield')){
        trigger_error("The field (".get_class($field)." {$field->name}) in the grid (".get_class($this)." {$this->moduleID} ".ShortPhrase($this->phrase).") must be a screen field.", E_USER_ERROR);
    }
}


/**
 * display the grid (abstract)
 *
 * usage: run-time
 */
function render()
{
    return gettext($this->phrase) . " ".gettext("grid");
}

function prepareCountSQL($pRecordID = null)
{
    global $data;
    global $recordID;

    $countSQL = $this->countSQL;

    if(is_null($pRecordID)){
        $useReordID = $recordID;
    } else {
        $useReordID = $pRecordID;
    }

    //replace field value placeholders
    $countSQL = PopulateValues($countSQL, $data);
    $countSQL = str_replace('/**RecordID**/', $useReordID, $countSQL);

    return $countSQL;
}


/**
 * populates the listSQL statement with values from the parent record
 *
 * usage: run-time
 */
function prepareListSQL($pRecordID = null)
{
    global $data;
    global $recordID;

    if(is_null($pRecordID)){
        $useReordID = $recordID;
    } else {
        $useReordID = $pRecordID;
    }

    $listSQL = $this->listSQL;

    //replace field value placeholders
    $listSQL = PopulateValues($listSQL, $data);
    $listSQL = str_replace('/**RecordID**/', $useReordID, $listSQL);
    $listSQL = TranslateLocalDateSQLFormats($listSQL);
    return $listSQL;
}

function getRecordCount($recordID = null)
{
    global $dbh;
    $countSQL = $this->prepareCountSQL($recordID);
    $r = $dbh->getOne($countSQL);
    if (DB::isError($r)) {
        return 'error';
    }
    return intval($r);
}

function setUpFieldTypes(&$moduleFields){
    foreach(array_keys($this->Fields) as $fieldName){
        $mf = $moduleFields[$fieldName];
        $this->fieldTypes[$fieldName] = $mf->dataType;
        if(empty($this->fieldAlign[$fieldName])){
            $this->fieldAlign[$fieldName] = $mf->getGridAlign();
        }
        if(!empty($mf->displayFormat)){
            $this->fieldFormats[$fieldName] = $mf->displayFormat;
        }
    }
}


} //end class Grid



/**
 *  A class that provides a view-only HTML table of submodule data
 */
class ViewGrid extends Grid
{
var $orderByFields = array();
var $isInfo = false;
var $isGuidance = false;
var $isVertical = false;
var $verticalFormats = array();

function &Factory($element, $moduleID)
{
    $grid = new ViewGrid($element, $moduleID);
    return $grid;
}


function ViewGrid($element, $moduleID)
{
    $subModuleID = $element->getAttr('moduleID');
    if('yes' == strtolower($element->getAttr('isDataView'))){
        $this->isDataView = true;
    }
    print "moduleID = $moduleID\n";

    $debug_prefix = debug_indent("ViewGrid [constructor] $subModuleID:");

    //when building Global Grids, there's no parent module 
    if(1 == $element->getAttr('isGlobalGrid')){
        $subModule = GetModule($subModuleID);
        if(1 == $element->getAttr('isGlobalGridWithConditions')){ //used by the res module
            $localKey = '';
        } elseif(1 == $element->getAttr('hasNoParentRecordID')){
            $localKey = '';
            $conditions = array(
                'RelatedModuleID' => '/**DynamicModuleID**/'
            );
        } else {
            $localKey = 'RelatedRecordID';
            $conditions = array(
                'RelatedModuleID' => '/**DynamicModuleID**/',
                'RelatedRecordID' => '/**RecordID**/'
            );
        }

        //check that fields which are part of conditions actually exist in the remote table
        if(isset($conditions) && count($conditions) > 0){
            foreach($conditions as $conditionField => $condition){
                if(!isset($subModule->ModuleFields[$conditionField])){
                    trigger_error("The exported global ViewGrid requires a field named $conditionField in the $subModuleID module. HINT: is this really a global module?", E_USER_ERROR);
                }
            }
        }
    } else {
        $module = GetModule($moduleID);
        $subModule = $module->SubModules[$subModuleID];
        if(empty($subModule)){
            die("$debug_prefix could not find a submodule that matches moduleID '$subModuleID'");
        }
        $localKey = $subModule->localKey;
        $conditions = null; //$subModule->conditions;
    }

    if(!is_object($subModule)){
        die("$debug_prefix Could not retrieve submodule '$subModuleID'");
    }

    //check for fields in the element: if there are none, we will import from the Exports section of the sub-module
    if((count($element->c) == 0) || 'yes' == strtolower($element->getAttr('import'))){

        $exports_element = $subModule->_map->selectFirstElement('Exports');
        if(empty($exports_element)){
            die("$debug_prefix Can't find an Exports section in the $subModuleID module.");
        }

        $grid_element = $exports_element->selectFirstElement('ViewGrid');
        if(empty($grid_element)){
            die("$debug_prefix Can't find a matching view grid in the $subModuleID module.");
        }

        //copy all the fields of the imported grid to the current element
        $element->c = array_merge($element->c, $grid_element->c);

        //copy attributes but allow existing attributes to override
        foreach($grid_element->attributes as $attrName => $attrValue){
            $gridAttrValue = $element->getAttr($attrName);
            if(empty($gridAttrValue)){
                $element->attributes[$attrName] = $attrValue;
            }
        }
    }

    $this->moduleID = $subModuleID;
    $this->phrase = $element->getAttr('phrase');

    if(strlen($element->getAttr('listExtended') > 0)){
        $this->listExtended = true;
    }
    if(!empty($conditions)){
        $this->conditions = $conditions;
    }
    $this->localKey = $localKey;

    if('yes' == strtolower($element->getAttr('isGuidance'))){
        $this->isGuidance = true;
        $this->formatOptions['suppressTitle'] = true;
        $this->formatOptions['suppressRecordIcons'] = true;
    }
    if('yes' == strtolower($element->getAttr('isInfo'))){
        $this->isInfo = true;
        $this->formatOptions['infoTitle'] = true;
        $this->formatOptions['suppressRecordIcons'] = true;
    }
    if('yes' == strtolower($element->getAttr('verticalDisplay'))){
        $this->isVertical = true;
        $this->formatOptions['suppressTitle'] = true;
    }
    if($this->listExtended){
        $this->formatOptions['suppressRecordIcons'] = true;
    }

    //append fields
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            $type = str_replace('Grid', '', $sub_element->type);
            switch($type){
            case 'OrderByField':
                //add invisible field if not in the selects already
                if(!isset($this->Fields[$sub_element->name])){
                    //make invisiblefield element
                    $invisibleField_element = new Element($sub_element->name, 'InvisibleField', array('name' => $sub_element->name));
                    $field_object = $invisibleField_element->createObjectWithRef($subModuleID, null, $this);
                    $this->AddField($field_object);
                    unset($field_object);
                }
                //add to $this->orderByFields
                $desc = false;
                if('desc' == strtolower($sub_element->getAttr('direction'))){
                    $desc = true;
                }
                $this->orderByFields[$sub_element->name] = $desc;
                break;
            case 'VerticalFormat':
                if(count($sub_element->c) > 0){
                    foreach($sub_element->c as $vformat_element){
                        $this->verticalFormats[$vformat_element->name] = $vformat_element->type;
                    }
                }
                break;
            case 'Conditions':
                if(count($sub_element->c) > 0){
                    foreach($sub_element->c as $condition_element){
                        if('Condition' == $condition_element->type){
                            $this->conditions[$condition_element->getAttr('field', true)] = $condition_element->getAttr('value', true);
                        }
                    }
                }
                break;
            case 'CustomProperty':
                $propertyName = $sub_element->name;
                $this->$propertyName = trim($sub_element->getContent(true));
                break;
            default:
                $sub_element->attributes['formName'] = $subModuleID;

                $field_object = $sub_element->createObjectWithRef($subModuleID, $type, $this);
                if(empty($field_object->phrase)){
                    $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;
                }
                $this->AddField($field_object);

                $align = $sub_element->getAttr('align');
                if(!empty($align)){
                    $this->fieldAlign[$field_object->name] = $align;
                }
                unset($field_object);
                break;
            }
        }
    }

    if(empty($this->listSQL)){
        $this->listSQL  = $subModule->generateListSQL($this);
    } else {
        CheckSQL($this->listSQL);
    }
    if(empty($this->countSQL)){
        $this->countSQL = $subModule->generateListCountSQL($this);
    } else {
        CheckSQL($this->countSQL);
    }
    $this->setUpFieldTypes($subModule->ModuleFields);

    $createFileName = $moduleID.'_'.$subModuleID.'ViewGrid.gen';
    $modelFileName = 'CustomModel.php';
    $createFilePath = GENERATED_PATH ."/$moduleID/$createFileName";
    $replaceValues = array('/**custom**/' => '$grid = unserialize(\''.escapeSerialize($this).'\');');
    SaveGeneratedFile($modelFileName, $createFileName, $replaceValues, $moduleID);
    debug_unindent();
}


function AddField($field)
{
    $this->_checkFieldClass($field);
    if(!$field->isEditable()){
        $this->Fields[$field->name] = $field;
        //print_r($field);
    } else {
        die("cannot add an editable field to a ViewGrid\n");
    }
}


function render($page, $qsArgs)
{
    require_once CLASSES_PATH . '/lists.php';
    global $dbh;
    global $User;
    global $theme_web;
    global $recordID;

    //print debug_r($qsArgs);

    //check whether user has permission at all
    if(! $User->PermissionToView($this->moduleID) ){
        $moduleInfo = GetModuleInfo($this->moduleID);
        $moduleName = $moduleInfo->getProperty('moduleName');

        if($this->formatOptions['suppressTitle']){
            $gridTitle = '';
        } else {
            $gridTitle = sprintf(
                GRID_TAB,
                gettext($this->phrase),
                '#',
                '#',
                '' //count
            );
        }

        //format grid table
        $content = sprintf(
            VIEWGRID_MAIN,
            $gridTitle,
            sprintf(gettext("You have no permissions to view records in the %s module."), $moduleName) . "<br />\n"
            );

        return $content;
    }

    if(isset($this->formatOptions['suppressPaging']) && $this->formatOptions['suppressPaging']){
        $perPage = -1;
    } else {
        if(!defined('IS_RPC') || !IS_RPC){
            $perPage = 10;
        } else {
            $perPage = intval($_GET['pp']);
        }
    }

    $listSQL = $this->prepareListSQL($recordID);
    $listData =& new ListData(
        $this->moduleID,
        $listSQL,
        $perPage,
        $this->fieldTypes,
        false,
        $this->prepareCountSQL($recordID),
        $this->fieldFormats
        );
    $nRows = $listData->getCount();


    if((!isset($this->formatOptions['suppressPaging']) || !$this->formatOptions['suppressTitle']) && (!defined('IS_RPC') || !IS_RPC)){
        if(empty($nRows)){
            $count = '';
        } else {
            $count = '('.$nRows.')';
        }
        if(!isset($this->formatOptions['infoTitle']) || !$this->formatOptions['infoTitle']){
            $gridTitle = sprintf(
                GRID_TAB,
                gettext($this->phrase),
                'list.php?mdl='.$this->moduleID,
                'frames_popup.php?dest=list&amp;mdl='.$this->moduleID,
                $count
                );
        } else {
            $gridTitle = sprintf(
                GRID_TAB_NOLINKS,
                gettext($this->phrase),
                $count
                );
        }
    } else {
        $gridTitle = '';
    }


    if($this->isVertical){
        $areaContent = '';
        $firstRecord = true;
        foreach($listData->getData() as $rowNum => $row){
            if($firstRecord){
                $firstRecord = false;
            } else {
                $areaContent .= '<hr class="vgrid_record_separator" />';
            }
            $rowContent = '<div class="vgrid_record">';
            foreach($this->Fields as $field){
                if($field->isVisible() && empty($field->parentName)){
                    if(array_key_exists($field->name, $this->verticalFormats)){
                        switch ($this->verticalFormats[$field->name]){
                        case 'LogoField':
                            $formatStr = '<div class="vgrid_logo">%s</div>';
                            break;
                        case 'TitleField':
                            $formatStr = '<h1 class="vgrid_title">%s</h1>';
                            break;
                        case 'FeatureField':
                            $formatStr = '<span class="vgrid_feature">%s</span><br />';
                            break;
                        default:
                            break;
                        }
                        $rowContent .= sprintf($formatStr, $field->simpleRender($row));


                    } else {
                        //normal fields
                        $formatStr = "<i>%s</i>: %s<br />";
                        $rowContent .= sprintf(
                            $formatStr,
                            ShortPhrase($field->phrase),
                            $row[$field->name]
                            );
                    }
                }
            }
            $rowContent .= '</div>';
            $areaContent .= $rowContent;

        }
        $content = sprintf('<div class="vgrid_main">%s</div>', $areaContent);
    } else {

        if(0 == $nRows){
            return sprintf(VIEWGRID_NONE, $gridTitle);
        }
        $headerPhrases = array();
        $linkFields = array();
        foreach($this->Fields as $fieldName => $field){
            if($field->isVisible() && empty($field->parentName)){
                $headerPhrases[$fieldName] = $field->gridHeaderPhrase();
                if(!empty($field->linkField)){
                    $linkFields[$fieldName] = $field->linkField;
                }
            }
        }

        $startRow = 0;
        if(defined('IS_RPC') && IS_RPC) {
            if(isset($qsArgs['sr'])){
                $startRow = intval($qsArgs['sr']);
            }
        }
        $renderer =& new ListRenderer(
            $this->moduleID,
            $listData,
            $headerPhrases,
            'frames_popup.php?dest=view&amp;',
            'rpc/gridList.php?grt=view&amp;smd='.$this->moduleID.'&amp;',
            $this->fieldAlign,
            'view',
            $linkFields,
            $this->formatOptions
        );
        $renderer->useBestPractices = false;
        $content = $renderer->render($startRow, $this->orderByFields);
//$content .= debug_r($this);
        if(!defined('IS_RPC') || !IS_RPC) {
            $content = '<div id="list_'.$this->moduleID.'">'.$content.'</div>';
            $content = sprintf(
                VIEWGRID_MAIN,
                $gridTitle,
                $content
            );
        }
    }
//print $content . "pre<br/>";
    return $content;
}


/**
 *  Renders the ViewGrid in text-only mode, suitable for text emails
 */
function renderText($recordID)
{
    require_once CLASSES_PATH . '/lists.php';
    global $dbh;
    global $User;

    if(! $User->PermissionToView($this->moduleID) ){
        return gettext($this->phrase) . ": ".gettext("no permission")."\n";
    }
    $content = gettext($this->phrase) . "\n";
    $headers = array();

    foreach($this->Fields as $fieldName => $field){
        if($field->isVisible() && empty($field->parentName)){
            $headers[] = $field->gridHeaderPhrase();
        }
    }

    $listSQL = $this->prepareListSQL($recordID);
    $listData =& new ListData(
        $this->moduleID,
        $listSQL,
        -1,
        $this->fieldTypes,
        false,
        $this->prepareCountSQL($recordID),
        $this->fieldFormats
        );
    $rows = $listData->getData();

    if(count($rows) > 0){
        foreach($rows as $rowNum => $row){

            foreach($this->Fields as $field){
                if($field->isVisible() && empty($field->parentName)){
                    $data[$rowNum][] = fldFormat($this->fieldTypes[$field->name], $row[$field->name]);
                }
            }
        }
        $textTable =& new TextTable($data, $headers);

        $content .= $textTable->render();

    } else {
        return gettext($this->phrase) . "\n".gettext("(no data)")."\n";
    }

    return $content;
}


/**
 *  Renders the ViewGrid in a format suitable for HTML emails
 */
function renderEmail($recordID)
{
    require_once CLASSES_PATH . '/lists.php';
// global $recordID;
    global $dbh;
    global $User;

    //check whether user has permission at all
    if(! $User->PermissionToView($this->moduleID) ){
        $moduleInfo = GetModuleInfo($this->moduleID);
        $moduleName = $moduleInfo->getProperty('moduleName');
        return gettext($this->phrase) . ": ".gettext("no permission")."<br />\n";
    }

    //headers without links
    $content = '';
    foreach($this->Fields as $FieldName => $Field){
        if($Field->isVisible() && empty($Field->parentName)){
            $content .= sprintf(
                GRID_HEADER_CELL_EMAIL,
                $Field->gridHeaderPhrase()
            );
        }
    }

    //format header row
    $content = sprintf(
        GRID_HEADER_ROW_EMAIL,
        $content
    );

    $listSQL = $this->prepareListSQL($recordID);
    $listData =& new ListData(
        $this->moduleID,
        $listSQL,
        -1,
        $this->fieldTypes,
        false,
        $this->prepareCountSQL($recordID),
        $this->fieldFormats
        );
    $rows = $listData->getData();

    if(count($rows) > 0){

        //CSS classes alternating row background colors
        $tdFormatting = array("aa_l", "aa_l2");

        //display rows
        foreach($rows as $rowNum => $row){
            $tdClass = $tdFormatting[$rowNum % 2];
            $rowContent = "";
            $fldpos = 0;

            foreach($this->Fields as $field){
                if($field->isVisible() && empty($field->parentName)){
                    $rowContent .= sprintf(
                        GRID_VIEW_CELL,
                        $field->gridAlign,
                        $tdClass,
                        $field->gridViewRender($row)
                    );
                }
                $fldpos++;
            }
            $content .= sprintf(VIEWGRID_ROW_EMAIL, $rowContent);

        }
        $content = sprintf(VIEWGRID_TABLE, $content);
    } else {
        $content = gettext("(No data)");
    }

    //format grid table
    $content = sprintf(
        VIEWGRID_MAIN_EMAIL,
        gettext($this->phrase),
        $content
        );

    return  $content;
}
} //end ViewGrid class



class EditGrid extends Grid
{
var $getFormSQL = ''; //SQL statement to retrieve the record values to be displayed in a form
var $getRowSQL = ''; //SQL statement to retrieve the grid columns of a just saved row
var $remoteFields; //a simple list of fields that are connected to a RemoteField
var $hasGridForm = false;
var $FormFields = array(); //Controls the style of the form.  If fields are present, the form is rendered vertically with the fields referenced here.  Otherwise, all fields will be used for the form, and rendered horizontally.  Only populated if the XML def has a GridForm tag.
var $selectedID;
var $encType = '';
var $IDTranslationSQL = '';  //for listExtended
var $listExtendedConditon = '';
var $PKField;
var $showGlobalSMRecords = false; //whether a global grid should show records of submodule records
var $orderByFields = array();
var $dataCollectionForm = false;
var $parentGetFields = array(); //fields in parent module to be queried when adding a new record. not possible in global modules.
var $parentSelectSQLOnNew;
var $getFormSQLOnNew; //initial values for form when no record was found: used in ire docs custom grid, could be used in extended grids
var $getRowSQLOnDelete; //used in ire docs custom grid, could be used in extended grids
var $allowAddRecord = true;

function &Factory($element, $moduleID)
{
    $debug_prefix = debug_indent("EditGrid::Factory:");

    $module = GetModule($moduleID); //local module
    $subModuleID = $element->getAttr('moduleID');

    //when building GlobalEditGrids, there's no SubModule 
    $isGlobalEditGrid = false;
    $hasNoParentRecordID = false;
    if(1 == $element->getAttr('isGlobalEditGrid')){
        $isGlobalEditGrid = true;
        $subModule = GetModule($subModuleID);

        if(1 == $element->getAttr('hasNoParentRecordID')){
            $hasNoParentRecordID = true;
            $localKey = '';
            $conditions = array(
                'RelatedModuleID' => '/**DynamicModuleID**/'
            );
        } else {
            $localKey = 'RelatedRecordID';
            $conditions = array(
                'RelatedModuleID' => '/**DynamicModuleID**/',
                'RelatedRecordID' => '/**RecordID**/'
            );
        }
        $subModule_parentKey = null;
    } else {
        $subModule = $module->SubModules[$subModuleID];
        if(empty($subModule)){
            die("$debug_prefix could not find a submodule that matches moduleID  '$subModuleID'");
        }
        $localKey = $subModule->localKey;
        $conditions = $subModule->conditions;
        $subModule_parentKey = $subModule->parentKey;
    }

    //check for fields in the element: if there are none, we will import from the Exports section of the sub-module
    if((count($element->c) == 0) || 'yes' == strtolower($element->getAttr('import'))){
        $exports_element = $subModule->_map->selectFirstElement('Exports');
        if(empty($exports_element)){
            die("$debug_prefix Can't find an Exports section in the $subModuleID module.");
        }

        $grid_element = $exports_element->selectFirstElement('EditGrid');
        if(empty($grid_element)){
            die("$debug_prefix Can't find a matching edit grid in the $subModuleID module.");
        }

        //copy all the fields of the imported grid to the current element
        $element->c = array_merge($element->c, $grid_element->c);

        //copy attributes but allow existing attributes to override
        foreach($grid_element->attributes as $attrName => $attrValue){
            $gridAttrValue = $element->getAttr($attrName);
            if(empty($gridAttrValue)){
                $element->attributes[$attrName] = $attrValue;
            }
        }
    }

    $grid =& new EditGrid(
        $subModuleID,
        $element->getAttr('phrase'),
        $localKey,
        $subModule_parentKey,
        $conditions,
        $element->getAttr('listExtended'),
        $element->getAttr('dataCollectionForm')
    );

    if('no' == strtolower($element->getAttr('allowAddRecord'))){
        $grid->allowAddRecord = false;
    }

    //append fields and GridForm
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('GridForm' == $sub_element->type){
                foreach($sub_element->c as $form_element){
                    $type = str_replace('Grid', '', $form_element->type);

                    switch($type){
                    case 'ParentField':
                        $grid->addParentGetField($form_element);
                        break;
                    default:
                        $form_element->attributes['formName'] = $subModuleID;

                        $field_object = $form_element->createObjectWithRef($subModuleID, $type, $grid);
                        $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;
                        $field_object->dataType = $subModule->ModuleFields[$field_object->name]->dataType;

                        $grid->AddFormField($field_object);
                        break;
                    }
                }
            } else {
                $type = str_replace('Grid', '', $sub_element->type);

                switch($type){
                case 'OrderByField':
                    //add invisible field if not in the selects already
                    if(!isset($grid->Fields[$sub_element->name])){
                        //make invisiblefield element
                        $invisibleField_element = new Element($sub_element->name, 'InvisibleField', array('name' => $sub_element->name));
                        $field_object = $invisibleField_element->createObjectWithRef($subModuleID, null, $grid);
                        $grid->AddField($field_object);
                        unset($field_object);
                    }
                    //add to $this->orderByFields
                    $desc = false;
                    if('desc' == strtolower($sub_element->getAttr('direction'))){
                        $desc = true;
                    }
                    $grid->orderByFields[$sub_element->name] = $desc;
                    break;
                case 'Conditions':
                    if(count($sub_element->c) > 0){
                        foreach($sub_element->c as $condition_element){
                            if('Condition' == $condition_element->type){
                                $grid->conditions[$condition_element->getAttr('field', true)] = $condition_element->getAttr('value', true);
                            }
                        }
                    }
                    break;
                case 'CustomProperty':
                    $propertyName = $sub_element->name;
                    $grid->$propertyName = trim($sub_element->getContent(true));
                    break;
                default:
                    $sub_element->attributes['formName'] = $subModuleID;

                    $field_object = $sub_element->createObjectWithRef($subModuleID, $type, $grid);
                    $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;
                    $field_object->dataType = $subModule->ModuleFields[$field_object->name]->dataType;
                    $grid->AddField($field_object);

                    $align = $sub_element->getAttr('align');
                    if(!empty($align)){
                        $grid->fieldAlign[$field_object->name] = $align;
                    }
                    unset($field_object);
                    break;
                }
            }
        }
    }

    if($isGlobalEditGrid){
        $grid->isGlobalEditGrid = true;
        $grid->hasNoParentRecordID = $hasNoParentRecordID;
    }

    //PHASE OUT
    $moduleInfo = GetModuleInfo($grid->moduleID);
    $grid->PKField = $moduleInfo->getPKField();

    //PHASE IN:
    $grid->PKFields = $subModule->PKFields;

    if($grid->listExtended){

        $extendingModule = GetModule($grid->moduleID);
        $grid->extendedModulePK = $extendingModule->extendsModuleKey;

        if(empty($grid->IDTranslationSQL)){
            $grid->IDTranslationSQL = "SELECT {$grid->PKField} AS Value FROM {$grid->moduleID} WHERE {$extendingModule->extendsModuleKey} = /*value*/";

            $conditions = array();
            $localKey = "`{$grid->moduleID}`.{$subModule->localKey}";
            $conditions[] = "{$localKey} = '/**RecordID**/'";

            foreach($subModule->conditions as $conditionField => $conditionValue){
                $conditions[] = "{$grid->moduleID}.$conditionField = '$conditionValue'\n";
            }
            $grid->IDTranslationSQL .= ' AND '.join("\n AND ", $conditions);
        } else {
            CheckSQL($grid->IDTranslationSQL);
        }
    }



    //sql stuff
    if(empty($grid->listSQL)){
        $grid->listSQL  = $subModule->generateListSQL($grid);
    } else {
        CheckSQL($grid->listSQL);
    }
    if(empty($grid->countSQL)){
        $grid->countSQL = $subModule->generateListCountSQL($grid);
    } else {
        CheckSQL($grid->countSQL);
    }
    $grid->setUpFieldTypes($subModule->ModuleFields);

    if(count($grid->parentGetFields) > 0 && empty($grid->parentSelectSQLOnNew)){
        $selectOnNew = array();
        $selectOnNew[$subModule_parentKey] = false;
        foreach($grid->parentGetFields as $parentFieldName => $onNew){
            if($onNew){
                $selectOnNew[$parentFieldName] = true;
            }
        }
        $parentSelect = MakeSelectStatement($selectOnNew, $moduleID);
        $parentSelectSQL = $parentSelect[0] . "\nWHERE {$parentSelect[1][$subModule_parentKey]} = '/**RecordID**/'";
        $grid->parentSelectSQLOnNew = $parentSelectSQL;
        trace($parentSelectSQL, 'parentSelectSQL');
    }

    //serializing a copy of the grid
    if($grid->hasGridForm){
        $fieldList = $grid->FormFields;
    } else {
        $fieldList = $grid->Fields;
    }

    if(empty($grid->getFormSQL)){
        print "$debug_prefix generating Grid-getFormSQL:\n";
        $grid->getFormSQL = $subModule->generateGetSQL($fieldList, null, true);
    } else {
        CheckSQL($grid->getFormSQL);
    }
    if(empty($grid->getRowSQL)){
        print "$debug_prefix generating Grid-getRowSQL:\n";
        $grid->getRowSQL = $subModule->generateGetSQL($grid->Fields, null, true);
    } else {
        CheckSQL($grid->getRowSQL);
    }

    if(count($grid->PKFields) == 2){
        if(empty($grid->getFormSQL)){
            $grid->getFormSQL .= "\nAND {$grid->moduleID}.{$grid->PKFields[0]} = '/**RecordID**/'";
        }
        if(empty($grid->getRowSQL)){
            $grid->getRowSQL .= "\nAND {$grid->moduleID}.{$grid->PKFields[0]} = '/**RecordID**/'";
        }
    }

    //RPC cached file section
    $createFileName = "{$moduleID}_{$grid->moduleID}EditGridRPC.gen";
    $modelFileName = 'EditGridRPCModel.php';


    $codeArray = array(
        '/**grid**/' => escapeSerialize($grid)
    );

    SaveGeneratedFile($modelFileName, $createFileName, $codeArray, $moduleID);

    debug_unindent();
    return $grid;
}


function EditGrid(
    $pModuleID,
    $pPhrase,
    $pLocalKey,
    $pParentKey,
    $conditions,
    $listExtended,
    $dataCollectionForm
    )
{
    $this->moduleID = $pModuleID;
    $this->phrase = $pPhrase;
    if(strlen($listExtended) > 0){
        $this->listExtended = true;
    }
    if(strlen($dataCollectionForm) > 0){
        if('yes' == strtolower($dataCollectionForm)){
            $this->dataCollectionForm = true;
        }
    }

    if(!empty($conditions)){
        $parentConditionFields = array();
        foreach($conditions as $conditionField => $conditionValue){
            //looks for [*fieldName*] references to parent record fields
            if(false !== strpos($conditionValue, '[*')){
                $pattern = '/\[\*(\w*)\*\]/';
                $matches = array();
                if(preg_match( $pattern, $conditionValue, $matches)){
                    $parentConditionFields[$conditionField] = $matches[1];
                }
            }
        }
        if(count($parentConditionFields) > 0){
            $this->needsParentFieldValues = true;
            $this->parentConditionFields = $this->makeParentFieldConditions($parentConditionFields);
        }


        $this->conditions = $conditions;
    }

    $this->localKey = $pLocalKey;
    $this->parentKey = $pParentKey;
}


/**
 *  converts conditions that include [*fieldName*] parent field references to a sub-query
 */
function makeParentFieldConditions($parentConditionFields)
{
    global $ModuleID;
    global $SQLBaseModuleID;
    $SQLBaseModuleID = $ModuleID;
    $parentModuleFields = GetModuleFields($ModuleID);
    $parentModuleInfo = GetModuleInfo($ModuleID);
    $parentRecordIDField = $parentModuleInfo->getPKField();

    $selects = array();
    $joins = array();

    $converted = array();
    foreach($parentConditionFields as $conditionField => $parentField){
        $parentModuleField = $parentModuleFields[$parentField];
        //$selects[] = $parentModuleField->makeSelectDef($ModuleID, false);
        //$joins = array_merge($joins, $parentModuleField->makeJoinDef($ModuleID));

        $selects[] = GetSelectDef($parentField);
        $joins = array_merge($joins, GetJoinDef($parentField));

        $joins = SortJoins($joins);
        $SQL = ' SELECT ';
        $SQL .= implode(', ', $selects);
        $SQL .= " FROM `$ModuleID` ";
        $SQL .= implode("\n   ", $joins);
        $SQL .= ' WHERE ';
        $SQL .= "`$ModuleID`.$parentRecordIDField = '/**RecordID**/'";
        $SQL .= ' ';
        $converted[$conditionField] = $SQL;
    }


    return $converted;
}


function isEditable()
{
    return true;
}


function AddFormField($field)
{
    $this->_checkFieldClass($field);
    $this->hasGridForm = true;
    $this->FormFields[$field->name] = $field;
}


function render($page, $qsArgs)
{
    require_once CLASSES_PATH . '/lists.php';
    global $dbh;
    global $User;
    global $theme_web;
    global $recordID;
    global $ModuleID;

    //check whether user has permission at all
    if(! $User->PermissionToView($this->moduleID) ){
        $moduleInfo = GetModuleInfo($this->moduleID);
        $moduleName = $moduleInfo->getProperty('moduleName');

        $gridTitle = sprintf(
            GRID_TAB,
            gettext($this->phrase),
            '#',
            '#',
            '' //count
            );

        //format grid table
        $content = sprintf(
            VIEWGRID_MAIN,
            $gridTitle,
            sprintf(gettext("You have no permissions to view or edit records in the %s module."), $moduleName) . "<br />\n"
            );
        return $content;
    }

    if(!defined('IS_RPC') || !IS_RPC) {
        $perPage = 10;
    } else {
        $perPage = intval($_GET['pp']);
    }

    $listSQL = $this->prepareListSQL($recordID);

    $listData =& new ListData(
        $this->moduleID,
        $listSQL,
        $perPage,
        $this->fieldTypes,
        false,
        $this->prepareCountSQL($recordID),
        $this->fieldFormats
        );
    $nRows = $listData->getCount();

    $headerPhrases = array();
    $linkFields = array();
    foreach($this->Fields as $fieldName => $field){
        if($field->isVisible() && empty($field->parentName)){
            $headerPhrases[$fieldName] = $field->gridHeaderPhrase();
            if(!empty($field->linkField)){
                $linkFields[$fieldName] = $field->linkField;
            }
        }
    }

    if(!defined('IS_RPC') || !IS_RPC) {
        $startRow = 0;
    } else {
        $startRow = intval($qsArgs['sr']);
    }

    if($this->listExtended){
        $gridType = 'edit_nfe';
    } else {
        if(!isset($this->allowAddRecord) || $this->allowAddRecord){
            $gridType = 'edit';
        } else {
            $gridType = 'edit_noadd';
        }
    }
    $renderer =& new ListRenderer(
        $this->moduleID,
        $listData,
        $headerPhrases,
        'frames_popup.php?dest=edit&amp;',
        'rpc/gridList.php?grt=edit&amp;smd='.$this->moduleID.'&amp;',
        $this->fieldAlign,
        $gridType,
        $linkFields
    );
    $renderer->useBestPractices = false;
    $content = $renderer->render($startRow, $this->orderByFields);

    $gridform_template = 
    '<div id="gridFormDiv" style="position:absolute;display:none; z-index:2;" class="l3"><table class="frm">%s</table></div>';

    $formDiv = sprintf($gridform_template, '<tr><td></td></tr>');
    //$content = sprintf(VIEWGRID_TABLE, $content);

    $gridTitle = sprintf(
        GRID_TAB,
        gettext($this->phrase),
        'list.php?mdl='.$this->moduleID,
        'frames_popup.php?dest=list&amp;mdl='.$this->moduleID,
        ''
    );

    //format grid table
    if(!defined('IS_RPC') || !IS_RPC) {
        $content = '<div id="list_'.$this->moduleID.'">'.$content.'</div>';
        $content = sprintf(
            VIEWGRID_MAIN,
            $gridTitle,
            $content
        );
    }

    $content .= $formDiv;
    $content .= "<script type=\"text/javascript\">\n";
    $content .= "\tvar recordID = '$recordID';\n";
    $content .= "\tvar moduleID = '$ModuleID';\n";
    $content .= "\tvar submoduleID = '{$this->moduleID}';\n";
    $content .= "\tvar useRowClassNum = 1;\n";
    $content .= "\tvar rowClasses = Array('l', 'l2');\n";
    $content .= "</script>\n";
    $content .= "<script type=\"text/javascript\" src=\"js/CBs.js\"></script>\n";
    $content .= "<script type=\"text/javascript\" src=\"3rdparty/filtery.js\"></script>\n";
    $content .= "<script type=\"text/javascript\" src=\"3rdparty/DataRequestor.js\"></script>\n";
    return  $content;
}



/**
 *  Renders the Grid Form as HTML to be sent via AJAX response
 */
function renderForm($rowID){
    global $dbh;
    global $User;
    global $phrases;
    global $recordID;

    $content = '';
    $datecontent = '';
    $precontent = "<script type=\"text/javascript\">\n<!--\n//[js:]\n";
    $precontent .= "    form_moduleID = \"{$this->moduleID}\";\n";
    $precontent .= "//[:js]\n-->\n</script>\n";

    //make sure we have correct rowID:
    if($this->listExtended){
        $extRowID = $rowID;
        $SQL = str_replace(array('/*value*/', '/**RecordID**/'), array($rowID, $recordID), $this->IDTranslationSQL);
trace($SQL, 'IDTranslationSQL');
        $rowID = $dbh->getOne($SQL);
        dbErrorCheck($rowID);
    }
    if(empty($rowID)){
        $rowID = 0;
    }

    //check permission
    if($User->PermissionToEdit($this->moduleID) > 0){ //should get ownerOrgID here
        $perm = 'permitted';

        if($this->listExtended && 0 == $rowID && !empty($this->getFormSQLOnNew)){
            if(!isset($extRowID)){
                $extRowID = 0;
            }
            $SQL = str_replace(array('/**ExtRowID**/', '/**RecordID**/'), array($extRowID, $recordID), $this->getFormSQLOnNew);
        } else {
            $SQL = str_replace(array('/**RowID**/', '/**RecordID**/'), array($rowID, $recordID), $this->getFormSQL);
        }
        $SQL = TranslateLocalDateSQLFormats($SQL);
        $values = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
        dbErrorCheck($values);

trace($SQL, 'getFormSQL');

        //get first (only) row from dataset
        if(count($values) > 0){
            $data = $values[0];
        } else {
            $data = array();

            if(!empty($this->parentSelectSQLOnNew)){
                $SQL = str_replace('/**RecordID**/', $recordID, $this->parentSelectSQLOnNew);
                $SQL = TranslateLocalDateSQLFormats($SQL);
                $parentValues = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
                dbErrorCheck($parentValues);
                $data = $parentValues[0];
            }
        }

        if($this->hasGridForm){
            $fieldList = $this->FormFields;
        } else {
            $fieldList = $this->Fields;
        }

        $consistencyMsg = renderConsistencyMessage($this->moduleID, $rowID);
        if(!empty($consistencyMsg)){
            $content = sprintf(FORM_CONSISTENCYROW_HTML, $consistencyMsg) . $content;
        }
        foreach($fieldList as $fName => $field){
            $content .= $field->render($data, $phrases);

            if('datefield' == strtolower(get_class($field))){
                $datecontent .= "Calendar.setup({\n";
                $datecontent .= "\tinputField : \"$fName\",\n";
                if('datetime' == $field->dataType){
                    $datecontent .= "\t" . $User->getCalFormat(true) ."\n";
                    $datecontent .= "\tshowsTime   : true,\n";
                } else {
                    $datecontent .= "\t" . $User->getCalFormat(false) ."\n";
                }
                $datecontent .= "\tbutton     : \"cal_$fName\"\n";
                $datecontent .= "});\n";
            }
        }
    }

    if($rowID > 0){
        $deleteButton = sprintf(FORM_BUTTON_HTML, 'Delete', gettext("Delete"), 'editGridDeleteRow(moduleID, submoduleID, editRowID)').' ';
    } else {
        $deleteButton = '';
    }

    //wrap into form & table
    $content .= sprintf(FORM_BUTTONROW_HTML,
        sprintf(FORM_BUTTON_HTML, 'Save', gettext("Save"), 'editGridSaveForm(moduleID, submoduleID, editRowID)').' '.
        $deleteButton.
        sprintf(FORM_BUTTON_HTML, 'Cancel', gettext("Cancel"), 'cancelEditRow(editRowID);')
    );
    $content = sprintf(EDITGRID_FORM, $this->moduleID, $content);
    if(!empty($datecontent)){
        $content .= "<script type=\"text/javascript\">\n<!--\n//[js:]\n";
        $content .= $datecontent . "\n";
        $content .= "//[:js]\n-->\n</script>\n";
    }

    $content = $precontent.$content;
    return $content;
}


function validateForm()
{
    if($this->hasGridForm){
        $fields =& $this->FormFields;
    } else {
        $fields =& $this->Fields;
    }
    $messages = '';
    foreach($fields as $fieldname => $field){
        $value = '';
        if(isset($_POST[$fieldname])){
            $value = $_POST[$fieldname];
        }
        $message = Validate($value, ShortPhrase($field->phrase), $field->validate, $field->dataType);
        $field->invalid = true;
        $messages .= $message;
    }
//START    
    if ( $messages == '' and 0 < strlen( $field->validate ) ){
        $_POST[$fieldname] = Normalize( $_POST[$fieldname], $field->validate );
    }
//END	
    return $messages;
}


/**
 * handles the form in AJAX-style (override for UploadGrid)
 */
function handleForm()
{ //EditGrid
    if(0 == count($_POST)){
        return false;
    }

    global $recordID; //parent record id

    $rowID = 0;
    if(isset($_GET['grw'])){
        $rowID = intval($_GET['grw']); //capture any selected row (record ID of grid's module)
    }

    global $dbh;
    global $User;

    $error = ''; //the rpc relies on capturing and returning errors in a JSON-encoded format.
    $isConsistent = true;

    $action = '';
    if(isset($_GET['action'])){
        $action = $_GET['action'];
    }
    switch($action){
    case 'save':
    case 'add':
        $action = 'save';
        break;
    case 'delete':
        $action = 'delete';
        break;
    default:
        if(defined('IS_RPC') && IS_RPC){
            trigger_error("Unknown action requested.", E_USER_WARNING);
            return array('error'=>gettext("Unknown action requested."));
        } else {
            //harmless reload
            return false;
        }
        break;
    }

    if($this->listExtended){
        //check for existing record that matches the posted ID (happens to be the Extended module's PK...)
        $extendedRowID = $rowID;
        $SQL = str_replace(array('/*value*/', '/**RecordID**/'), array($rowID, $recordID), $this->IDTranslationSQL);

        $rowID = $dbh->getOne($SQL);
        dbErrorCheck($rowID);
        //$origRowID = $rowID;
        trace("EditGrid translated ID from $extendedRowID to $rowID");
    }

    if(isset($this->needsParentFieldValues) && $this->needsParentFieldValues){
        foreach($this->parentConditionFields as $conditionField => $parentSQL){
            $parentSQL = str_replace('/**RecordID**/', $recordID, $parentSQL);
            $parentValue = $dbh->getOne($parentSQL);
            dbErrorCheck($parentValue);
            $this->conditions[$conditionField] = $parentValue;
        }
trace($parentSQL, "EditGrid parent SQL");
trace($this->conditions, "EditGrid populated conditions");
    }

    global $ModuleID;
    if(isset($this->conditions) && count($this->conditions) > 0){
        foreach($this->conditions as $conditionField => $conditionValue){
            if('/**RecordID**/' == $conditionValue){
                $this->conditions[$conditionField] = $recordID;
            } elseif('/**DynamicModuleID**/' == $conditionValue) {
                $this->conditions[$conditionField] = $ModuleID;
            }
        }
    }

    $dh = GetDataHandler($this->moduleID, false);
    if(isset($this->conditions) && count($this->conditions) > 0){
        foreach($this->conditions as $conditionField => $conditionValue){
            $dh->relatedRecordFieldValues[$conditionField] = $conditionValue;
        }
    }
    if(!empty($this->localKey)){
        $dh->relatedRecordFieldValues[$this->localKey] = $recordID;
    }
    if($this->listExtended){
        $dh->relatedRecordFieldValues[$this->extendedModulePK] = $extendedRowID;
    }

trace($dh->relatedRecordFieldValues, "EditGrid-dh relatedRecordFieldValues");

    if('save' == $action){

        $validateMsg = $this->validateForm();
        if(!empty($validateMsg)){
            $error .= gettext("The data was not saved, because:")."\n".$validateMsg;

            return array('rowID'=>$rowID, 'error'=>$error);
        }


        $resultID = $dh->saveRow($_POST, $rowID);

        if(false === $resultID){
            $error .= gettext("The record has not been saved, because:");
            foreach($dh->errmsg as $err => $id){
                $error .= "\n".$err;
            }
        }

        if($this->listExtended){
            if(empty($rowID)){
                $rowID = $resultID;
            } else {
                //ignores $resultID
            }
        } else {
            $rowID = $resultID;
        }

        $isConsistent = isConsistent($this->moduleID, $rowID);
        //but we might also have made the parent record consistent?? Would be nice to pass to parent page for update of parent consistency message.

        $displayRow = true;
    } elseif('delete' == $action){

        //use dataHandler delete function
        $success = $dh->deleteRow($rowID);
        if($success){
            //whatever
        }

        if($this->listExtended){
            $displayRow = true;
        } else {
            $displayRow = false;
        }

    }

    $allow_full_edit = true;
    if($displayRow){
        if('delete' == $action && !empty($this->getRowSQLOnDelete)){
            $SQL = str_replace(array('/**ExtRowID**/', '/**RowID**/', '/**RecordID**/'), array($extendedRowID, $rowID, $recordID), $this->getRowSQLOnDelete);
        } else {
            $SQL = str_replace(array('/**RowID**/', '/**RecordID**/'), array($rowID, $recordID), $this->getRowSQL);
        }
trace($SQL, "EditGrid row retrieval ");
        $SQL = TranslateLocalDateSQLFormats($SQL);
        $values = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
        if(!dbErrorCheck($values, false, false)){
            $error = gettext("Database error") . ':' . $SQL;
            return array('rowID'=>$rowID, 'error'=>$error);
        }

        //get first (only) row from dataset
        if(count($values) > 0){
            $data = $values[0];
        } else {
            $data = array();
        }

        foreach($this->Fields as $field){
            if($field->isVisible() && empty($field->parentName)){
                $cells[] = array(
                    'align' => 'left',
                    'className' => 'l',
                    'innerHTML' => $field->gridViewRender($data)
                );
            }
        }
        if($this->listExtended){
            $rowID = $extendedRowID; //from here on, $row ID is the id of the extended record, not the saved one
            $allow_full_edit = false;
        }
    }

    $updateParentConsistencyMsg = false; //determine this somehow... maybe check parent ccs before and after save?
    return array('rowID' => $rowID, 'cells' => $cells, 'error' => $error, 'fulledit' => $allow_full_edit, 'consistent' => $isConsistent, 'updccmsg' => $updateParentConsistencyMsg);
}


function prepRemoteFields()
{
    $debug_prefix = debug_indent("EditGrid-prepRemoteFields() {$this->moduleID}:");

//identify a list of remote fields
    if ($this->hasGridForm){
        $fieldList = &$this->FormFields;
    } else {
        $fieldList = &$this->Fields;
    }
//die('looking for RemoteFields');

    $moduleFields = GetModuleFields($this->moduleID); //renmoved &

    foreach($fieldList as $fieldName => $field){
        if($field->isEditable()){
            $moduleField = $moduleFields[$fieldName]; //removed &
            if(strtolower(get_class($moduleField)) == 'remotefield'){
                print "$debug_prefix Found Remote Field $fieldName\n\n";
                $this->remoteFields[$fieldName] = $moduleField;
            } else {
                //print "EditGrid->PrepRemoteFields: Field $fieldName is not a RemoteField\n\n";
            }
        }
    }
    debug_unindent();
}


function addParentGetField($element)
{
    $this->parentGetFields[$element->name] = ('yes' == strtolower($element->getAttr('getOnNew')));
}
} //end EditGrid class


class UploadGrid extends EditGrid
{
var $uploadFields = array();
var $encType = 'enctype="multipart/form-data"';


function Factory($element, $moduleID)
{
    $module = GetModule($moduleID);
    $subModuleID = $element->getAttr('moduleID');

    //when building GlobalEditGrids, there's no SubModule 
    if(1 == $element->getAttr('isGlobalEditGrid')){
        $subModule = GetModule($subModuleID);
        $localKey = 'RelatedRecordID';
        $conditions = array(
            'RelatedModuleID' => '/**DynamicModuleID**/',
            'RelatedRecordID' => '/**RecordID**/'
        );
        $localKey = '';
    } else {
        $subModule = $module->SubModules[$subModuleID];
        if(empty($subModule)){
            die("UploadGrid-Factory: could not find a submodule that matches moduleID  '$subModuleID'");
        }
        $localKey = $subModule->localKey;
        $conditions = null;
    }

    //check for fields in the element: if there are none, we will import from the Exports section of the sub-module
    if(count($element->c) == 0){
        $exports_element = $subModule->_map->selectFirstElement('Exports');
        if(empty($exports_element)){
            die("Can't find an Exports section in the $subModuleID module.");
        }

        $grid_element = $exports_element->selectFirstElement('UploadGrid');
        if(empty($grid_element)){
            die("Can't find a matching upload grid in the $subModuleID module.");
        }

        //copy all the fields of the imported grid to the current element
        $element->c = $grid_element->c;

        //copy attributes but allow existing attributes to override
        foreach($grid_element->attributes as $attrName => $attrValue){
            $gridAttrValue = $element->getAttr($attrName);
            if(empty($gridAttrValue)){
                $element->attributes[$attrName] = $attrValue;
            }
        }
    }

    $grid = new UploadGrid(
        $subModuleID,
        $element->getAttr('phrase'),
        $conditions,
        $localKey
    );

    //append fields and GridForm
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('GridForm' == $sub_element->type){
                foreach($sub_element->c as $form_element){
                    $type = str_replace('Grid', '', $form_element->type);

                    $field_object = $form_element->createObjectWithRef($subModuleID, $type, $grid);
                    $form_element->attributes['formName'] = $subModuleID;

                    $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;

                    $grid->AddFormField($field_object);
                }
            } else {
                $type = str_replace('Grid', '', $sub_element->type);

                $field_object = $sub_element->createObjectWithRef($subModuleID, $type, $grid);
                $sub_element->attributes['formName'] = $subModuleID;
                $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;

                $grid->AddField($field_object);
            }
        }
    }
    $moduleInfo = GetModuleInfo($grid->moduleID);
    $grid->PKField = $moduleInfo->getPKField();

    $grid->listSQL  = $subModule->generateListSQL($grid);

    return $grid;
}


function UploadGrid(
    $pModuleID,
    $pPhrase,
    $conditions,
    $pLocalKey
    )
{
    $this->moduleID = $pModuleID;
    $this->phrase = $pPhrase;
    if(!empty($conditions)){
        $this->conditions = $conditions;
    }
    $this->localKey = $pLocalKey;
}


function render($page, $qsArgs)
{
    global $recordID;
    global $dbh;
    global $User;
    global $data;

    //check whether user has permission at all
    if(! $User->PermissionToView($this->moduleID) ){
        $moduleInfo = GetModuleInfo($this->moduleID);
        $moduleName = $moduleInfo->getProperty('moduleName');

        $gridTitle = sprintf(
        GRID_TAB,
            gettext($this->phrase),
            '#',
            '#',
            '' //count
        );

        //format grid table
        $content = sprintf(
            VIEWGRID_MAIN,
            $gridTitle,
            sprintf(gettext("You have no permissions to view records in the %s module."), $moduleName) . "<br />\n"
            );
        return $content;
    }


    //check that the uploads folder is writeable
    global $messages;
    if(!is_writeable(UPLOAD_PATH)){
        $err_msg = gettext("The server's upload directory is not writeable. You will not be able to upload files.");
        $messages[] = array('e', $err_msg);
        trigger_error($err_msg, E_USER_WARNING);
    } else {
        if(!is_writeable(UPLOAD_PATH.'/'.$ModuleID)){
            $err_msg = gettext("The server's upload directory for this module is not writeable. You will not be able to upload files.");
            $messages[] = array('e', $err_msg);
            trigger_error($err_msg, E_USER_WARNING);
        }
    }

    //TODO: Don't display an Edit link for records where user has View but not Edit permissions


    //capture order by parameter
    $orderBy = $qsArgs['ob'.$this->number];
    $prevOrderBy = $qsArgs['pob'.$this->number];

    //making sure ob and pob fields exist in grid:
    if(!in_array($orderBy, array_keys($this->Fields))){
        $orderBy = '';
    }
    if(!in_array($prevOrderBy, array_keys($this->Fields))){
        $prevOrderBy = '';
    }

    //add grid ID to all links
    $qsArgs['gid'] = $this->number;

    unset($qsArgs['ob'.$this->number]);
    unset($qsArgs['pob'.$this->number]);
    $headerQS = MakeQS($qsArgs);

    //make form query string
    $formQS = MakeQS($qsArgs);

    $listSQL = $this->prepareListSQL();


    //setting up link for the next pob
    if(!empty($orderBy)){ 
        $prevOBString = '&amp;pob'.$this->number.'='.$orderBy;
    } else {
        $prevOBString = '';
    }

    //grid headers
    $content = GRID_HEADER_CELL_EMPTY;
    foreach($this->Fields as $FieldName => $Field)
    {
        //if('InvisibleField' != $Field->getType() && empty($Field->parentName)){
        if($Field->isVisible() && empty($Field->parentName)){
            if($prevOrderBy != $FieldName){
                $fPrevOBString = $prevOBString;
            } else {
                $fPrevOBString = '';
            }
            $content .= sprintf(
                GRID_HEADER_CELL,
                $page.'?'.$headerQS.'&amp;ob'.$this->number.'='.$FieldName.$fPrevOBString,
                $Field->gridHeaderPhrase()
            );
        }
    }

    //format header row
    $content = sprintf(
        GRID_HEADER_ROW,
        $content
    );

    //print nl2br($listSQL);
    //get data
    $r = $dbh->getAll($listSQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($r, true);

    //alternating row background colors
    $tdFormatting = array("l", "l2");

    //get selected row id
    $selRowID = $this->selectedID; //this allows Cancel to reset //$qsArgs['grw'];

    unset($qsArgs['grw']); //remove from QS
    $rowQS = MakeQS($qsArgs); //QS for row "Edit" links
//print debug_r($this->Fields);

    //display rows
    foreach($r as $rowNum => $row){

        $curRowID = reset($row); //assumes first column is row ID
        $tdClass = $tdFormatting[($rowNum) % 2];
        $rowContent = "";

        if ($selRowID != $curRowID){

            //add view cells
            foreach($this->Fields as $key => $field){
                //if('InvisibleField' != $field->getType() && empty($field->parentName)){
                if($field->isVisible() && empty($field->parentName)){
                    $rowContent .= sprintf(
                        GRID_VIEW_CELL,
                        $field->gridAlign, //'left',
                        'l2',
                        $field->gridViewRender($row)
                    );
                } else {
                    if($field->isEditable()){
                        $rowContent .= $field->gridViewRender($row);
                    }
                }
            }

            $content .= sprintf(
                UPLOADGRID_VIEWROW,
                $tdClass,
                $curRowID,
                //'edit.php?'.$formQS.'&grw='.$curRowID, //adds row ID
                $page.'?'.$rowQS.'&amp;grw='.$curRowID, //adds row ID
                gettext("Edit"),
                $rowContent
            );
        } else {
            if($this->hasGridForm){

                //display editable fields like a regular form (not a row)
                $numFields = count($this->Fields);
                //render each gridForm field
                foreach($this->FormFields as $formField){
                    if(empty($formField->parentName)){
                        $rowContent .= $formField->gridFormRender($row);

                        if ('datefield' == strtolower(get_class($formField))){
                            $dateFields[] = $formField->name;
                        }
                    }
                }
                //wrap into form
                $rowContent = sprintf(GRIDFORM_HTML, $numFields, $rowContent);

            } else {
                //add editable cells
                foreach($this->Fields as $key => $field){
                    //if('InvisibleField' != $field->getType() && empty($field->parentName)){
                    if($field->isVisible() && empty($field->parentName)){
                        $rowContent .= sprintf(
                            GRID_EDIT_CELL,
                            "left",
                            "l3",
                            $field->gridEditRender($row)
                        );
                        if ('datefield' == strtolower(get_class($field))){
                            $dateFields[] = $field->name;
                        }
                    } else {
                        //if it's still editable:
                        if($field->isEditable()){
                            $rowContent .= $field->gridEditRender($row);
                        }
                    }
                }
            }
            $content .= sprintf(
                EDITGRID_EDITROW,
                "l3",
                gettext("Save"),
                gettext("Delete"),
                gettext("Cancel"),
                $rowContent);
        }
    }

    //if inserting, add insert row
    if(!$this->listExtended && empty($selRowID)){
        $rowContent = "";
        global $data;

        //add insert cells
        if($this->hasGridForm){
            //display editable fields like a regular form (not a row)
            $numFields = count($this->Fields);
            //render each gridForm field
            foreach($this->FormFields as $formField){
                if(empty($formField->parentName)){
                    $rowContent .= $formField->gridFormRender($selRowID);

                    if ('datefield' == strtolower(get_class($formField))){
                        $dateFields[] = $formField->name;
                    }
                }
            }
            //wrap into form
            $rowContent = sprintf(GRIDFORM_HTML, $numFields, $rowContent);

        } else {
            foreach($this->Fields as $key => $field){
                //if('InvisibleField' != $field->getType()){
                if($field->isVisible() && empty($field->parentName)){
                    $rowContent .= sprintf(
                        GRID_EDIT_CELL,
                        "left",
                        "l3",
                        $field->gridEditRender($selRowID) //passing an empty variable in order to avoid warnings - omitting a parameter or passing an empty string causes warning
                    );
                    if ('datefield' == strtolower(get_class($field))){
                        $dateFields[] = $field->name;
                    }
                } else {
                    //if it's still editable:
                    if($field->isEditable()){
                        $rowContent .= $field->gridEditRender($selRowID);
                    }
                }
            }
        }
        $content .= sprintf(
            EDITGRID_INSERTROW,
            "l3",
            gettext("Add"),
            $rowContent);
    }

    //format grid table
    $content = sprintf(
        EDITGRID_MAIN,
        gettext($this->phrase),
        $page.'?'.$formQS,
        $this->moduleID,
        $this->encType,
        $this->number,
        $content);

    global $User;

    //generate content for each date field
    if (count($dateFields) > 0){
        $content .= "<script type=\"text/javascript\">\n";
        foreach($dateFields as $fieldName){
            $content .= "Calendar.setup({\n";
            $content .= "\tinputField : \"$fieldName\",\n";
            //$content .= "\tifFormat   : \\\"\".\$User->getDateFormatCal().\"\\\",\n";
            $content .= "\t" . $User->getCalFormat() ."\n";
            $content .= "\tbutton     : \"cal_$fieldName\"\n";
            $content .= "});\n";
        }
        $content .= "</script>\n";
    }

    //$content .= debug_r($listSQL);
    //$content .= debug_r($this->insertSQL);
    //$content .= debug_r($this->updateSQL);

    return  $content;
} //render

//UploadGrid
function handleForm(){
    global $recordID; 
    global $ModuleID; 
    global $messages;
    global $dbh;
    global $qsArgs;

    if (empty($_POST['cancel'])){

        //capture any selected row
        if (intval($_GET['grw']) > 0){
            $rowID = intval($_GET['grw']);
            $this->selectedID = $rowID;
        }

        if (intval($_POST['gridnum']) == $this->number){

            global $User;

            //check whether user has permission to edit at all
            if(! $User->PermissionToEdit($this->moduleID) ){
                $moduleInfo = GetModuleInfo($this->moduleID);
                $moduleName = $moduleInfo->getProperty('moduleName');
                trigger_error(sprintf(gettext("You have no permissions to edit records in the %s module."), $moduleName) . E_USER_ERROR);
            }

            //set up data handler
            $dh = GetDataHandler($this->moduleID);
            if(!empty($this->localKey)){
                $dh->relatedRecordFieldValues[$this->localKey] = $recordID;
            }
            if(count($this->conditions) > 0){
                foreach($this->conditions as $conditionField => $conditionValue){
                    if('/**RecordID**/' == $conditionValue){
                        $dh->relatedRecordFieldValues[$conditionField] = $recordID;
                    } elseif('/**DynamicModuleID**/' == $conditionValue) {
                        $dh->relatedRecordFieldValues[$conditionField] = $ModuleID;
                    } else {
                        $dh->relatedRecordFieldValues[$conditionField] = $conditionValue;
                    }
                }
            }

            //check whether to save or delete
            //add
            //save
            //delete
//trace($_POST, 'POST');
//trace($_GET, 'GET');

            if(isset($_POST['delete_x'])){
                $_POST['delete'] = 'delete';
            }
            if(isset($_POST['add_x'])){
                $_POST['add'] = 'add';
            }
            if(isset($_POST['save_x'])){
                $_POST['save'] = 'save';
            }

            if(!empty($_POST['delete'])){

                $result = $dh->deleteRow($rowID);
                $this->selectedID = 0;

            } elseif(!empty($_POST['add']) || !empty($_POST['save'])){

                $validateMsg = $this->validateForm();
                if(!empty($validateMsg)){
                    //use the EditScreen error
                    $validateMsg = gettext("The record has not been saved, because:")."\n".$validateMsg;
                    $validateMsg = nl2br($validateMsg);

                    //return error messages
                    global $messages;
                    $messages[] = array('e', $validateMsg);
                }
//print debug_r($_POST);
//die('test');


                switch($_FILES['FileName']['error']){
                case UPLOAD_ERR_OK:
                    if(is_uploaded_file($_FILES['FileName']['tmp_name'])){

//print "file upload went well<br>\n";

                        //adding the file name to the POST variable
                        $_POST['FileName'] = $_FILES['FileName']['name']; 

                        if(empty($_POST['Description'])){
                            $_POST['Description'] = $_FILES['FileName']['name'];
                        }

                        $dh->startTransaction(); //wraps into a higher level transaction; close after verifying upload was OK
                        $rowID = $dh->saveRow($_POST, $rowID);

                        //build the file name
                        $destination = UPLOAD_PATH . "/{$ModuleID}/att_{$ModuleID}_{$recordID}_{$rowID}.dat";

                        //create the folder if needed
                        if(!file_exists(dirname($destination))){
                            mkdir(dirname($destination));
                        }

                        if(move_uploaded_file($_FILES['FileName']['tmp_name'], $destination)){
                            $messages[] = array('m', gettext("The file was uploaded successfully."));

                            if(!empty($_FILES['FileName']['size'])){
                                $dh->saveRow(
                                    array(
                                        'AttachmentID' => $rowID,
                                        'FileSize' => $_FILES['FileName']['size']
                                    ), $rowID);
                            }
                            $dh->endTransaction(); //closes higher level transaction, data is committed
                            $this->selectedID = 0;
                        } else {
                            $messages[] = array('e', sprintf(gettext("There was a problem uploading the file %s."), $_FILES['FileName']['name']));

                            $dh->rollbackTransaction(); //cancels transaction
                            $this->selectedID = 0;
                        }

                    } else {
                        //print "what's the matter?<br>\n";
                        $messages[] = array('e', gettext("There was a problem uploading the file."));
                    }

                    break;
                case UPLOAD_ERR_INI_SIZE:
                    //print "file upload: file is larger than allowed for this server<br>\n";
                    $err_msg = gettext("The file is larger than allowed for this server.");
                    $messages[] = array('e', $err_msg);
                    trigger_error($err_msg, E_USER_WARNING);
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    //print "file upload: file is larger than allowed for this form<br>\n";
                    $err_msg = gettext("The file is larger than allowed.");
                    $messages[] = array('e', $err_msg);
                    trigger_error($err_msg, E_USER_WARNING);
                    break;
                case UPLOAD_ERR_PARTIAL:
                    //print "file upload: file was not completely uploaded<br>\n";
                    $err_msg = gettext("The file was not completely uploaded.");
                    $messages[] = array('e', $err_msg);
                    trigger_error($err_msg, E_USER_WARNING);
                    break;
                case UPLOAD_ERR_NO_FILE:
                    if (!empty($_POST['add'])){
                        //don't save record w/o file
                        $err_msg = gettext("No file was uploaded.");
                        $messages[] = array('e', $err_msg);
                        trigger_error($err_msg, E_USER_WARNING);
                        $this->selectedID = 0; //show the add form
                    } else {
                        //As long as $_POST does not contain FileName, the original file name won't be overwritten!!
                        $rowID = $dh->saveRow($_POST, $rowID);
                        $this->selectedID = 0;
                    }
                    break;
                default:
                    $err_msg = gettext("There was a problem uploading the file. Unknown problem.");
                    $messages[] = array('e', $err_msg);
                    trigger_error($err_msg, E_USER_ERROR);
                    $messages[] = array('e', $err_msg);
                }
            }
        }
    } else {
        $qsArgs['grw'] = 0;
        $this->selectedID = 0;
    }
}

} //end class UploadGrid





class SelectGrid extends EditGrid {
var $availableIDField;
var $availableNameField;

var $listAvailableSQL;
var $listConditions = array();
var $listSelectedSQL;
var $listExistingSelectedSQL;
var $insertSQL;
var $insertRemoteSQL;
var $removeSQL;
var $removeRemoteSQL;
var $restoreSQL;
var $restoreRemoteSQL;
var $getRemoteIDSQL;
var $getRemoteRowIDSQL;
var $logSQL;
var $logRemoteSQL;

var $useRemoteField = false;
var $listKeyType = '';

function &Factory($element, $moduleID)
{
    $grid = new SelectGrid($element, $moduleID);
    return $grid;
}

function SelectGrid (&$element, $moduleID)
{
    $module = GetModule($moduleID);
    $subModuleID = $element->getAttr('moduleID');
    $subModule = $module->SubModules[$subModuleID];

    //copies attributes from submoule's Exports section if grid is not defined locally
    if('SelectGrid' == $element->type && count($element->c) == 0){

        $exports_element = $subModule->_map->selectFirstElement('Exports');
        if(empty($exports_element)){
            die("Can't find an Exports section in the $subModuleID module.");
        }

        $grid_element = $exports_element->selectFirstElement('SelectGrid');
        //$grid_element = $exports_element->selectFirstElement($element->type);
        if(empty($grid_element)){
            die("Can't find a matching SelectGrid in the $subModuleID module.");
        }

        //copies all the fields of the imported grid to the current element
        $element->c = $grid_element->c;

        //copy attributes but allow existing attributes to override
        foreach($grid_element->attributes as $attrName => $attrValue){
            $gridAttrValue = $element->getAttr($attrName);
            if(empty($gridAttrValue)){
                $element->attributes[$attrName] = $attrValue;
            }
        }
    }

    $this->moduleID = $subModuleID;
    $this->phrase = $element->getAttr('phrase');
    $this->primaryListField = $element->getAttr('primaryListField');
    $this->localKey = $subModule->localKey;
    $this->conditions = $subModule->conditions;

    $listModuleField = GetModuleField($this->moduleID, $this->primaryListField);
    $this->listModuleID = $listModuleField->foreignTable;

    $this->availableIDField = $listModuleField->foreignKey;
    $this->availableNameField = $listModuleField->foreignField;

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('AvailbleListConditions' == $sub_element->type){
                foreach($sub_element->c as $condition_element){
                    $conditionObj = $condition_element->createObject($this->listModuleID);
                    $this->listConditions[$conditionObj->name] = $conditionObj;
                }
            }
        }
    }

    $conditionStrings = array();
    if(!empty($subModule->localKey)){
        if(!$this->listExtended){
            $localKey = $subModule->localKey;
            $this->conditions[$localKey] = "/*recordID*/";
        }
    }
    $this->init($this->conditions);

    //SQL for the "available items" list
    $listModuleFields = GetModuleFields($this->listModuleID);
    $listIDField = $listModuleFields[$this->availableIDField];
    $listNameField = $listModuleFields[$this->availableNameField];

    $arListOptions = array();
    if($element->getAttr('orgListOptions')){
        $arListOptions = OrgComboField::parseOrgListOptions($element->getAttr('orgListOptions'));
        trace($arListOptions, 'parsed list options');
    }

    $SQL = "SELECT ";
    $SQL .= GetQualifiedName($this->availableIDField, $this->listModuleID) . ' AS ID, ';
    $SQL .= GetQualifiedName($this->availableNameField, $this->listModuleID) . ' AS Name ';

    $SQL .= "FROM `{$this->listModuleID}` ";

    $listFroms = array();
    $listWheres = array();
    if(count($this->listConditions) > 0){
        foreach($this->listConditions as $listCondition){
            $exprArray = $listCondition->getExpression($this->listModuleID);
            $listFroms = array_merge($exprArray['joins'], $listFroms);
            $listWheres[] = $exprArray['expression'];
        }
    }

    $listFroms = array_merge($listFroms, GetJoinDef($this->availableIDField, $this->listModuleID));
    $listFroms = array_merge($listFroms, GetJoinDef($this->availableNameField, $this->listModuleID));

    foreach($listFroms as $alias => $def){
        $SQL .= "$def\n";
    }

    $SQL .= "WHERE\n";
    $SQL .= "{$this->listModuleID}._Deleted = 0";
    if(!empty($listModuleField->listCondition)){
        $SQL .= " AND {$this->listModuleID}.{$listModuleField->listCondition}\n";
    }

    if(count($listWheres > 0)){
        foreach($listWheres as $listWhere){
            $SQL .= " AND {$listWhere}\n";
        }
    }

    if(!empty($arListOptions) && in_array('orgListOption', $arListOptions) && !empty($arListOptions['where'])){
        $SQL .= ' AND '.$arListOptions['where']."\n";
    }

    $this->listAvailableSQL = $SQL;
}


function init($conditions) {
    /*
    SQL value placeholders:
    -------------------------
    *recordID*  - (parent) recordID
    *userID*    - PersonID of user who updates
    *value*     - ID of an "available" item
    *rowID*     - record id of record from $this->moduleID
    *deleted*   - 

    */

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;

    $ModuleFields = GetModuleFields($this->moduleID);
    $listModuleField = $ModuleFields[$this->primaryListField];
    $listKeyModuleField = $ModuleFields[$listModuleField->localKey];

    print "selectgrid key data type {$listKeyModuleField->dataType}\n";
    if('varchar(5)' == $listKeyModuleField->dataType){
        $this->listKeyType = 'modID';
    }

    $moduleInfo = GetModuleInfo($this->moduleID);
    $module_PK = $moduleInfo->getPKField();

    //if the field we're saving to is a RemoteField, then we need to save both a local record and a record in the remote module.
    if('remotefield' == strtolower(get_class($listKeyModuleField))){
        $this->useRemoteField = true;
        $remoteModuleInfo = GetModuleInfo($listKeyModuleField->remoteModuleID);
        $remoteModule_PK = $remoteModuleInfo->getPKField();

    }

    $aConditionStrings = array();
    $strInsertConditionFields = '';
    $strInsertConditionValues = '';
    $aInsertConditionFields = array();
    $aInsertConditionValues = array();

    if(count($conditions) > 0){
        foreach($conditions as $conditionField => $conditionValue){
            $aConditionStrings[] = "{$this->moduleID}.{$conditionField} = '$conditionValue'";

            //if(FALSE !== strpos($conditionField,$this->moduleID.'.')){
                $aInsertConditionFields[] = str_replace($this->moduleID.'.', '', $conditionField);
                $aInsertConditionValues[] = $conditionValue;
            //}
        }
        $strConditions =  join(' AND ', $aConditionStrings);

        $strInsertConditionFields = join(',', $aInsertConditionFields).',';
        $strInsertConditionValues = '\''.join('\',\'', $aInsertConditionValues).'\',';
    }

    //gets existing, non-deleted selected items
    $SQL = 'SELECT '; 
    $SQL .= GetQualifiedName($listKeyModuleField->name) . ' AS ID, ';
    $SQL .= GetQualifiedName($listModuleField->name) . ' AS Name ';

    $SQL .= " FROM {$this->moduleID} "; 

    $joins = GetJoinDef($listKeyModuleField->name);
    $joins = array_merge($joins, GetJoinDef($listModuleField->name));

    $joins = SortJoins($joins);

    if(count($joins) > 0){
        foreach($joins as $j){
            $SQL .= " $j\n";
        }
    }
    $SQL .= ' WHERE '; 
    if(!empty($strConditions)){
        //$SQL .= ' AND '.$strConditions; //join(' AND ', $aConditionStrings);
        $SQL .= ' '.$strConditions;
    }
    $this->listSelectedSQL = $SQL . " AND {$this->moduleID}._Deleted = 0 ORDER BY Name";


    //gets existing selected items, _Deleted as a column
    $this->listExistingSelectedSQL = str_replace(' FROM', ", {$this->moduleID}._Deleted FROM", $SQL);

    if(!$this->useRemoteField){
        $this->insertSQL = 
            "INSERT INTO {$this->moduleID} ({$listKeyModuleField->name}, $strInsertConditionFields _ModBy, _ModDate) VALUES ('/*value*/', $strInsertConditionValues /*userID*/, NOW());";

        //stmt to translate actual record id to row ids 
        $this->getRemoteIDSQL = "SELECT {$module_PK} AS RowID FROM {$listKeyModuleField->moduleID} WHERE {$listKeyModuleField->name} = '/*value*/'";
        if(!empty($strConditions)){
            $this->getRemoteIDSQL .= ' AND '.$strConditions;
        }

        $this->logSQL = 
            "INSERT INTO {$this->moduleID}_l ({$listKeyModuleField->name}, $strInsertConditionFields _ModBy, _ModDate, _Deleted) VALUES ('/*value*/', $strInsertConditionValues '/*userID*/', NOW(), /*deleted*/);";

    } else {
        $this->insertSQL = 
            "INSERT INTO {$this->moduleID} ($strInsertConditionFields _ModBy, _ModDate) VALUES ($strInsertConditionValues '/*userID*/', NOW());";

        $remoteDescriptorField = '';
        $remoteDescriptor = '';
        $remoteDescriptorCondition = '';            
        if(!empty($listKeyModuleField->remoteDescriptorField)){
            $remoteDescriptorField = $listKeyModuleField->remoteDescriptorField.',';
            $remoteDescriptor = $listKeyModuleField->remoteDescriptor.',';
            $remoteDescriptorCondition = " AND {$listKeyModuleField->remoteDescriptorField} = '{$listKeyModuleField->remoteDescriptor}'";
        }

        $this->insertRemoteSQL = 
            "INSERT INTO {$listKeyModuleField->remoteModuleID}
            ({$listKeyModuleField->remoteModuleIDField},
            {$listKeyModuleField->remoteRecordIDField},
            {$listKeyModuleField->remoteField},
            {$remoteDescriptorField}
            _ModBy, _ModDate) VALUES ('{$this->moduleID}',
            '/*recordID*/',
            '/*value*/',
            {$remoteDescriptor}
            /*userID*/, NOW()
            );";

        $this->removeRemoteSQL = "UPDATE {$listKeyModuleField->remoteModuleID} SET 
            _Deleted = 1,
            _ModBy = '/*userID*/',
            _ModDate = NOW()
        WHERE
            {$listKeyModuleField->remoteModuleIDField} = '{$this->moduleID}' AND
            {$listKeyModuleField->remoteRecordIDField} = '/*rowID*/' $remoteDescriptorCondition";

        //this is simply the reverse
        $this->restoreRemoteSQL = str_replace('_Deleted = 1', '_Deleted = 0', $this->removeRemoteSQL);

        //stmt to translate actual record (person) id to row ids 
        $this->getRemoteIDSQL = "SELECT {$listKeyModuleField->remoteRecordIDField} AS RowID FROM {$listKeyModuleField->remoteModuleID} WHERE {$listKeyModuleField->remoteField} = '/*value*/'
        AND {$listKeyModuleField->remoteModuleIDField} = '{$this->moduleID}'
        $remoteDescriptorCondition ORDER BY RowID DESC LIMIT 1";

        //stmt to tranlate local rowIDs to remote rowIDs
        $this->getRemoteRowIDSQL = "SELECT $remoteModule_PK AS RowID FROM
        {$listKeyModuleField->remoteModuleID} WHERE
        {$listKeyModuleField->remoteModuleIDField} = '{$this->moduleID}' AND
        {$listKeyModuleField->remoteRecordIDField} = '/*recordID*/' $remoteDescriptorCondition  ORDER BY RowID DESC LIMIT 1";

        //log SQL statements
        $this->logRemoteSQL = 
            "INSERT INTO {$listKeyModuleField->remoteModuleID}_l
            ($remoteModule_PK,
            {$listKeyModuleField->remoteModuleIDField},
            {$listKeyModuleField->remoteRecordIDField}, 
            {$listKeyModuleField->remoteField}, 
            {$remoteDescriptorField}
            _ModBy, _ModDate, _Deleted) VALUES (
            '/*rowID*/',
            '{$this->moduleID}',
            '/*recordID*/',
            '/*value*/',
            {$remoteDescriptor}
            '/*userID*/', NOW(), /*deleted*/
            );";

        //log statement
        $this->logSQL = 
            "INSERT INTO {$this->moduleID}_l ($module_PK, $strInsertConditionFields _ModBy, _ModDate, _Deleted) VALUES ('/*rowID*/',  $strInsertConditionValues '/*userID*/', NOW(), /*deleted*/);";

    }

    //Remove SQL
    $SQL = "UPDATE {$this->moduleID} SET 
            _Deleted = 1,
            _ModBy = '/*userID*/', 
            _ModDate = NOW()
        WHERE ";

    $SQL .= "{$module_PK} = '/*rowID*/'";
    if(!empty($strConditions)){
        $SQL .= ' AND '.$strConditions;
    }
    $this->removeSQL = $SQL;

    //Restore SQL: this is simply the reverse
    $this->restoreSQL = str_replace('_Deleted = 1', '_Deleted = 0', $this->removeSQL);

}

function render($page, $qsArgs){
    //add grid ID to all links
    $qsArgs['gid'] = $this->number;

    //make form query string
    $formQS = MakeQS($qsArgs);

    global $dbh;
    global $data;
    global $User;
    global $recordID;

    // create a new instance of JSON
    require_once(THIRD_PARTY_PATH . '/JSON.php');
    $json = new JSON();

    //use a generated List SQL statement...
    $SQL = $this->listAvailableSQL . ' ORDER BY Name';
    $SQL = TranslateLocalDateSQLFormats($SQL);

    $available = $dbh->getAssoc($SQL);
    dbErrorCheck($available);
    $js_available =  $json->encode($available);


    //get already selected
    $SQL = str_replace('/*recordID*/', $recordID, $this->listSelectedSQL);
    $SQL = TranslateLocalDateSQLFormats($SQL);
    $selected = $dbh->getAssoc($SQL);
    $js_selected =  $json->encode($selected);

    $phrase = $this->phrase;
    if(empty($phrase)){
        $phrase = gettext('Select');
    }

    $gridTitle = sprintf(
        GRID_TAB,
        gettext($this->phrase),
        'list.php?mdl='.$this->moduleID,
        'frames_popup.php?dest=list&amp;mdl='.$this->moduleID,
        ''
        );

    $content = "
    <script type=\"text/javascript\">
        var availableItems = $js_available;
        var selectedItems = $js_selected;
    </script>
    <script type=\"text/javascript\" src=\"js/selectGrid.js\"></script>";
    $content .= '<div id="sg" class="sz2tabs">';
    $content .= "$gridTitle
    <form name=\"searchForm\" method=\"post\" action=\"edit.php?$formQS\">
    <input type=\"hidden\" name=\"SaveIDs\" value=\"\"/>
    <input type=\"hidden\" name=\"gridnum\" value=\"{$this->number}\"/>
    <table class=\"frm\">
    ";

    $phrases = array('OrganizationID' => 'Organization', 'DepartmentID' => 'Department');

    $content .= sprintf(FORM_BUTTONROW_HTML,
        sprintf(
            FORM_BUTTON_HTML,
            'SelectAll_btn',
            gettext("Add All"),
            "selectAll()"
            ).' '.
        sprintf(
            FORM_BUTTON_HTML,
            'UnelectAll_btn',
            gettext("Remove All"),
            "unselectAll()"
            ).' &nbsp; '.
        sprintf(
            FORM_BUTTON_HTML,
            'Save_btn',
            gettext("Save"),
            "saveSelected()"
            )
        );
    $content .= '<tr><td valign="top">';

    $content .= 
    '<div class="sgList">
        <div class="sgTitle">'.gettext("Available").':</div>
        <div id="sgAvailable">
        <ul id="availableList">
        </ul>
        </div>
    </div>';
    $content .= '</td><td valign="top">';
    $content .=
    '<div class="sgList">
        <div class="sgTitle">'.gettext("Selected").':</div>
        <div id="sgSelected">
        <ul id="selectedList">
        </ul>
        </div>
    </div>';
    $content .= "</td></tr>";
    $content .= "</table>";
    $content .= "</form>";
    $content .= '</div>';
    //initially display "available" and "selected" list
    $content .= "<script type=\"text/javascript\">
        listAvailable(availableItems);
        initSelected(selectedItems);
    </script>";
    return $content;
}

//SelectGrid
function handleForm(){
    if(!empty($_POST)){

        global $recordID;
        global $dbh;
        global $User;

        //check whether user has permission to edit at all
        if(! $User->PermissionToEdit($this->moduleID) ){
            $moduleInfo = GetModuleInfo($this->moduleID);
            $moduleName = $moduleInfo->getProperty('moduleName');
            die(sprintf(gettext("You have no permissions to edit records in the %s module."), $moduleName) . "<br />\n");
        }

        $postedIDs = array();

        if(!empty($_POST['SaveIDs'])){
            //sanitize posted data:
            $uncleanDataArray = split(' ', trim($_POST['SaveIDs']));

            //different cleanup if we're dealing with ModuleIDs...
            if('modID' == $this->listKeyType){
                foreach($uncleanDataArray as $unclean){
                    $postedIDs[] = substr($unclean,0,5);
                }
            } else {
                foreach($uncleanDataArray as $unclean){
                    $postedIDs[] = intval($unclean);
                }
            }
        } else {
            $uncleanDataArray = array();
        }

        //start transaction
        $SQL = "BEGIN;";
        $r = $dbh->query($SQL);
        dbErrorCheck($r);

        //get info on selected items (including existing but _Deleted records)
        $SQL = str_replace('/*recordID*/', $recordID, $this->listExistingSelectedSQL);
        $existingDataSet = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);

        dbErrorCheck($existingDataSet);
        $existing = array();
        $active = array();
        $inactive = array();
        foreach($existingDataSet as $existingRow){
            if(0 != $existingRow['_Deleted']){
                $inactive[] = $existingRow['ID'];
            } else {
                $active[] = $existingRow['ID'];
            }
            $existing[] = $existingRow['ID'];
        }

        //insert any never before selected IDs
        if(count($postedIDs) > 0){
            foreach($postedIDs as $postedID){
                if(!in_array($postedID, $existing)){
                    //main record
                    $iSQL = str_replace(array('/*value*/','/*recordID*/', '/*rowID*/', '/*userID*/'), array($postedID, $recordID, $rowID, $User->PersonID), $this->insertSQL);
                    $r = $dbh->query($iSQL);
                    dbErrorCheck($r);

                    $SQL = "SELECT LAST_INSERT_ID();";
                    $subrecordID = $dbh->getOne($SQL);
                    dbErrorCheck($subrecordID);

                    if($this->useRemoteField) {
                        //remote record
                        $iSQL = str_replace(array('/*recordID*/', '/*value*/', '/*userID*/'), array($subrecordID, $postedID, $User->PersonID), $this->insertRemoteSQL); 
                        $r = $dbh->query($iSQL);
                        dbErrorCheck($r);

                        $SQL = "SELECT LAST_INSERT_ID();";
                        $remoteRecordID = $dbh->getOne($SQL);
                        dbErrorCheck($remoteRecordID);
                    }

                    //main log record
                    $lSQL = str_replace(array('/*rowID*/', '/*value*/', '/*recordID*/', '/*userID*/', '/*deleted*/'), array($subrecordID, $postedID, $postedID, $User->PersonID, '0'), $this->logSQL);
                    $r = $dbh->query($lSQL);
                    dbErrorCheck($r);

                    if($this->useRemoteField) {
                        //remote log record
                        $lSQL = str_replace(array('/*rowID*/', '/*recordID*/', '/*value*/', '/*userID*/', '/*deleted*/'), array($remoteRecordID, $subrecordID, $postedID, $User->PersonID, '0'), $this->logRemoteSQL);
                        $r = $dbh->query($lSQL);
                        dbErrorCheck($r);
                    }
                }
            }
        }

        //update existing but _Deleted IDs, reverting _Deleted to FALSE
        if(count($postedIDs) > 0){
            foreach($postedIDs as $postedID){

                if(in_array($postedID, $inactive)){
                    //get recordID from local table
                    $getRemoteIDSQL = str_replace(
                        array('/*value*/', '/*recordID*/'), 
                        array($postedID, $recordID), 
                        $this->getRemoteIDSQL
                    );
                    $rowID = $dbh->getOne($getRemoteIDSQL);
                    dbErrorCheck($rowID);

                    if($this->useRemoteField) {
                        //remote record
                        $rSQL = str_replace(array('/*rowID*/', '/*recordID*/', '/*userID*/'), array($rowID, $recordID, $User->PersonID), $this->restoreRemoteSQL);

                        $r = $dbh->query($rSQL);
                        dbErrorCheck($r);
                    }

                    //main record
                    $rSQL = str_replace(
                        array('/*rowID*/', '/*value*/', '/*recordID*/', '/*userID*/'), 
                        array($rowID, $recordID, $recordID, $User->PersonID), 
                        $this->restoreSQL
                    );

                    $r = $dbh->query($rSQL);
                    dbErrorCheck($r);

                    //main log record
                    $lSQL = str_replace(array('/*rowID*/', '/*value*/', '/*recordID*/', '/*userID*/', '/*deleted*/'), array($rowID, $postedID, $recordID, $User->PersonID, '0'), $this->logSQL);

                    $r = $dbh->query($lSQL);
                    dbErrorCheck($r);

                    if($this->useRemoteField) {
                        //get recordID of remote table (so we can log it)
                        $SQL = str_replace(array('/*recordID*/'), array($rowID), $this->getRemoteRowIDSQL);
                        $remoteRowID = $dbh->getOne($SQL);
                        dbErrorCheck($remoteRowID);

                        //remote log record
                        $lSQL = str_replace(array('/*rowID*/', '/*recordID*/', '/*value*/', '/*userID*/', '/*deleted*/'), array($remoteRowID, $rowID, $postedID, $User->PersonID, '0'), $this->logRemoteSQL);
                        $r = $dbh->query($lSQL);
                        dbErrorCheck($r);
                    }
                }
            }
        }

        //update removed IDs by setting _Deleted to TRUE
        if(count($active) > 0){
            foreach($active as $activeID){
                if(!in_array($activeID, $postedIDs)){
                    $getRemoteIDSQL = str_replace(array('/*value*/','/*recordID*/'), array($activeID, $recordID), $this->getRemoteIDSQL);
                    $rowID = $dbh->getOne($getRemoteIDSQL);
                    dbErrorCheck($rowID);

                    if($this->useRemoteField) {
                        //remote record
                        $rSQL = str_replace(array('/*rowID*/', '/*userID*/'), array($rowID, $User->PersonID), $this->removeRemoteSQL); 
                        $r = $dbh->query($rSQL);
                        dbErrorCheck($r);
                    }

                    //main record
                    $rSQL = str_replace(array('/*recordID*/', '/*rowID*/', '/*userID*/'), array($recordID, $rowID, $User->PersonID), $this->removeSQL); 
                    $r = $dbh->query($rSQL);
                    dbErrorCheck($r);

                    //main log record
                    $lSQL = str_replace(array('/*rowID*/', '/*value*/', '/*recordID*/', '/*userID*/', '/*deleted*/'), array($rowID, $activeID, $activeID, $User->PersonID, '1'), $this->logSQL);
                    $r = $dbh->query($lSQL);
                    dbErrorCheck($r);

                    if($this->useRemoteField) {
                        //get recordID of remote table (so we can log it)
                        $SQL = str_replace(array('/*recordID*/'), array($rowID), $this->getRemoteRowIDSQL);
                        $remoteRowID = $dbh->getOne($SQL);
                        dbErrorCheck($remoteRowID);

                        //remote log record
                        $lSQL = str_replace(array('/*rowID*/', '/*recordID*/', '/*value*/', '/*userID*/', '/*deleted*/'), array($remoteRowID, $rowID, $activeID, $User->PersonID, '1'), $this->logRemoteSQL);
                        $r = $dbh->query($lSQL);
                        dbErrorCheck($r);
                    }
                }
            }
        }

        $SQL = "COMMIT;";
        $r = $dbh->query($SQL);
        dbErrorCheck($r);
    }
}
} //end SelectGrid class

class CodeSelectGrid extends SelectGrid
{
}


class SearchSelectGrid extends SelectGrid
{
var $searchFields = array();
var $searchFieldPhrases = array();

function Factory($element, $moduleID)
{
    $debug_prefix = debug_indent("SearchSelectGrid::Factory():");

    $module = GetModule($moduleID);
    $subModuleID = $element->getAttr('moduleID');
    $subModule = $module->SubModules[$subModuleID];

    //check for fields in the element: if there are none, we will import from the Exports section of the sub-module
    if('SearchSelectGrid' == $element->type && count($element->c) == 0){
        $exports_element = $subModule->_map->selectFirstElement('Exports');
        if(empty($exports_element)){
            die("$debug_prefix Can't find an Exports section in the $subModuleID module.");
        }

        $grid_element = $exports_element->selectFirstElement('SearchSelectGrid');
        if(empty($grid_element)){
            die("$debug_prefix Can't find a matching check grid in the $subModuleID module.");
        }

        //copy all the fields of the imported grid to the current element
        $element->c = $grid_element->c;

        //copy attributes but allow existing attributes to override
        foreach($grid_element->attributes as $attrName => $attrValue){
            $gridAttrValue = $element->getAttr($attrName);
            if(empty($gridAttrValue)){
                $element->attributes[$attrName] = $attrValue;
            }
        } 
    }

    print "$debug_prefix $subModuleID\n";

    $grid = new SearchSelectGrid(
        $subModuleID,
        $element->getAttr('phrase'),
        $subModule->localKey,
        $subModule->conditions,
        $element->getAttr('primaryListField')
    );

    $listModuleField = GetModuleField($grid->moduleID, $grid->primaryListField);
    $grid->listModuleID = $listModuleField->foreignTable;

    $grid->availableIDField = $listModuleField->foreignKey;
    $grid->availableNameField = $listModuleField->foreignField;

    if($element->getAttr('orgListOptions')){
        $grid->_orgListOptions = OrgComboField::parseOrgListOptions($element->getAttr('orgListOptions'));
        trace($grid->_orgListOptions, 'parsed list options');
    }


    //append fields
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('SearchForm' == $sub_element->type){
                foreach($sub_element->c as $field_element){

                    $field_element->attributes['formName'] = 'searchForm';
                    trace($grid->listModuleID, "\$grid->listModuleID");
                    print "$debug_prefix SearchForm: adding field:\n";
                    indent_print_r($field_element);
                    //$field_object = $field_element->createObject($grid->listModuleID, $field_element->type);
                    $field_object = $field_element->createObjectWithRef($grid->listModuleID, $field_element->type, $grid);

                    $grid->AddSearchField($field_object);
                }
            } else {
                $type = str_replace('Grid', '', $sub_element->type);

                $field_object = $sub_element->createObjectWithRef($subModuleID, $type, $grid);
                $sub_element->attributes['formName'] = $subModuleID;

                $field_object->phrase = $subModule->ModuleFields[$field_object->name]->phrase;

                $grid->AddField($field_object);
            }
        }
    }

    //add RemoteFields
    $grid->prepRemoteFields();

    debug_unindent();
    return $grid;
}


function SearchSelectGrid (
    $pModuleID,
    $pPhrase, 
    $pLocalKey,
    $conditions,
    $primaryListField
    )
{
    $debug_prefix = debug_indent("SearchSelectGrid [constructor] {$pModuleID}:");

    print "$debug_prefix ($pModuleID - $primaryListField)...\n";
    $this->moduleID = $pModuleID;
    $this->phrase = $pPhrase;

    $this->primaryListField = $primaryListField;
    $listModuleField = GetModuleField($this->moduleID, $this->primaryListField);
    $this->listModuleID = $listModuleField->foreignTable;

    $this->availableIDField = $listModuleField->foreignKey;
    $this->availableNameField = $listModuleField->foreignField;
    $this->localKey = $pLocalKey;

    $subModule = GetModule($this->moduleID);
    $conditions = $subModule->conditions;
    $conditionStrings = array();

    if(!empty($subModule->localKey)){
        if(!$this->listExtended){
            $localKey = $subModule->localKey;
            $conditions[$localKey] = "/*recordID*/";
        }
    }
    foreach($conditions as $conditionField => $conditionValue){
        $conditionStrings[] = "{$this->moduleID}.$conditionField = '$conditionValue'";
    }

    $this->init($conditions);
    debug_unindent();
}



function AddSearchField($searchField){
    $this->_checkFieldClass($searchField);
    $searchMF = GetModuleField($this->listModuleID, $searchField->name);
    $this->searchFieldPhrases[$searchField->name] = $searchMF->phrase;
    $searchField->renderMode = 'search';
    $this->searchFields[$searchField->name] = $searchField;
}

function render($page, $qsArgs){

    //add grid ID to all links
    $qsArgs['gid'] = $this->number;

    //make form query string
    $formQS = MakeQS($qsArgs);

    global $dbh;
    global $data;
    global $User;
    global $recordID;

    include_once(CLASSES_PATH . '/search.class.php');

    $search = new Search(
        $this->listModuleID,
        array('ID' => $this->availableIDField, 'Name' => $this->availableNameField), // ID, Name
        $this->searchFields,
        $data //values from $data
        );
    $_SESSION['Search_ssg_'.$this->listModuleID] = $search;

    // create a new instance of JSON
    require_once(THIRD_PARTY_PATH. '/JSON.php');
    $json = new JSON();

    $search->prepareFroms($this->searchFields, $data);

    $SQL = $search->getListSQL('Name');
    if(!empty($this->_orgListOptions) && in_array('orgListOption', $this->_orgListOptions) && !empty($this->_orgListOptions['where'])){
        $SQL .= ' AND '.$this->_orgListOptions['where']."\n";
    }

    $available = $dbh->getAssoc($SQL);
    $js_available =  $json->encode($available);


    //get already selected
    $SQL = str_replace('/*recordID*/', $recordID, $this->listSelectedSQL);
    $SQL = TranslateLocalDateSQLFormats($SQL);
    $selected = $dbh->getAssoc($SQL);
    $js_selected =  $json->encode($selected);

    $content = '<div id="sg" class="sz2tabs">';

    $searchFieldNames = array_keys($this->searchFields);
    $jsSearchFieldNames = '"' . join('","', $searchFieldNames) . '"';

    $gridTitle = sprintf(
        GRID_TAB,
        gettext($this->phrase),
        'list.php?mdl='.$this->moduleID,
        'frames_popup.php?dest=list&amp;mdl='.$this->moduleID,
        ''
        );


    $content .= "
    <script type=\"text/javascript\">
        var searchFieldNames = Array($jsSearchFieldNames);
        var availableItems = $js_available;
        var selectedItems = $js_selected;
    </script>
    <script type=\"text/javascript\" src=\"js/CBs.js\"></script>
    <script type=\"text/javascript\" src=\"3rdparty/filtery.js\"></script>
    <script type=\"text/javascript\" src=\"3rdparty/DataRequestor.js\"></script>
    <script type=\"text/javascript\" src=\"js/selectGrid.js\"></script>

    $gridTitle
    <form name=\"searchForm\" method=\"post\" action=\"edit.php?$formQS\">
    <input type=\"hidden\" name=\"SaveIDs\" value=\"\"/>
    <input type=\"hidden\" name=\"gridnum\" value=\"{$this->number}\"/>
    <table class=\"frm\">
    ";


    //display search form
    foreach($this->searchFields as $searchField){
        $content .= $searchField->render($data, $this->searchFieldPhrases);
    }

    //how to pass values in form to fsgGetAvailable???
    //how to reference them at all???
    $content .= sprintf(FORM_BUTTONROW_HTML,
        sprintf(
            FORM_BUTTON_HTML,
            'Search_btn',
            gettext("Search"),
            "loadData('{$this->listModuleID}')"
            ).' '.
        sprintf(
            FORM_BUTTON_HTML,
            'SelectAll_btn',
            gettext("Add All"),
            "selectAll()"
            ).' '.
        sprintf(
            FORM_BUTTON_HTML,
            'UnelectAll_btn',
            gettext("Remove All"),
            "unselectAll()"
            ).' &nbsp; '.
        sprintf(
            FORM_BUTTON_HTML,
            'Save_btn',
            gettext("Save"),
            "saveSelected()"
            )
        );
    $content .= '<tr><td valign="top">';

    $content .=
    '<div class="sgList">
        <div class="sgTitle">Available:</div>
        <div id="sgAvailable">
        <ul id="availableList">

        </ul>
        </div>
    </div>';
    $content .= '</td><td valign="top">';
    $content .= '<div class="sgList">
        <div class="sgTitle">Selected:</div>
        <div id="sgSelected">
        <ul id="selectedList">

        </ul>
        </div>
    </div>';
    $content .= "</td></tr>";
    $content .= "</table>";
    $content .= "</form>";
    //initially display "available" list
    $content .= "<script type=\"text/javascript\">
        listAvailable(availableItems);
    </script>";
    //initially display "selected" list
    $content .= "<script type=\"text/javascript\">
        initSelected(selectedItems);
    </script>";

    return $content;
}
} //end SearchSelectGrid class

?>