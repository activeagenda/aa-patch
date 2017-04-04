<?php
/**
 *  Module Class definition and related classes
 *
 *  This file contains the Module class and related classes used
 *  by the Module class.
 *
 *  PHP version 5
 *
 *
 *  LICENSE NOTE:
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
 * @version        SVN: $Revision: 1667 $
 * @last-modified  SVN: $Date: 2009-05-30 04:01:44 +0200 (So, 30 maj 2009) $
 * @package        gen_time
 */

/**
 *  This include contains the render-able component classes
 */
include_once CLASSES_PATH . '/grids.php'; //includes field classes

//this include contains the module field classes
include_once CLASSES_PATH . '/modulefields.php';

//this include contains utility functions
include_once INCLUDE_PATH . '/general_util.php';

//module map class
include_once CLASSES_PATH . '/module_map.class.php';


//debug support: change this to get more debug messages
define('DEBUG_LEVEL', 2); //0 = don't print nearly anything








/** Module class
 *
 * This class does the following:
 * - Parses an XML Module Definition file upon instantiation,
 *   including module definitions of related modules and submodules, 
 *   and stores this information in its structure.
 * - Provides methods for creating and updating database tables, building
 *   SQL statements, and generating PHP cached scripts.
 *
 * @package        gen_time
 */

class Module
{

//data-specific properties
var $ModuleID;                          //the Module ID as well as table name for the module
var $Name;                              //the Module's Name
var $SingularRecordName;                //phrase that describes one record
var $PluralRecordName;                  //phrase that describes multiple records
var $addNewName;                        //if present, overrides SingularRecordName for the "new" tab
var $OwnerField;                        //field name that specifies the owner organization of a record
var $recordDescriptionField;
var $recordLabelField;
var $isGlobal = false;
var $includeGlobalModules = true;
var $defaultMenuPath;
var $ModuleFields = array();            //contains descendants of ModuleField
var $SubModules = array();              //list of sub-modules
var $_screens = array();                 //list of screen objects (private)
var $viewScreen = null;
var $searchScreen = null;
var $listScreen = null;                 //reference to the list screen in the $_screens array

var $_listFields = array();              //fields for the list grid
var $permissionParentModuleID;
var $parentModuleID;
var $parentRecordIDField;

var $PKFields = array();                //primary key fields
var $Indexes = array();                 //indexes
var $uniquenessIndexes = array();
var $consistencyConditions = array();
var $AllowAddRecord = true;             //whether there should be a link to add a record in this module (otherwise, records can only be added via an EditGrid in a different module, or some other automatic way)
var $extendsModuleID = '';              //if set, indicates that this module should extend another
var $extendsModuleKey = '';
var $isExtendedByModuleID = '';
var $extendsModuleFilterField = '';
var $extendsModuleFilterValue = '';
var $updateImports = false;

var $_documentation = array();

//function-specific properties
var $_map;                      //module map
var $Parsed = false;            //is set to true once the XML file has been read
var $revisionInfo = array();    //array of revision properties
var $xmlMerged = false;
var $useBestPractices = false;
var $dataCollectionForm = false;
var $isMasterData = false;
var $isTypeModule = false;      //bad name for a useful feature: Whether the module is intended to provide lookup list items
var $isCacheModule = false;
var $isSystemModule = false;
var $isImplementationPlanningModule = false;


function Factory($element, $moduleID)
{
    return false;
}

//constructor
function Module($moduleID)
{
    $debug_prefix = debug_indent("Module constructor $moduleID:");
    print "$debug_prefix current class is ".get_class($this)."\n";

    global $moduleParseList;

    $this->ModuleID = $moduleID;

    $moduleDefFile = GetXMLFilePath($moduleID .'ModuleDef.xml');


    if(file_exists($moduleDefFile)){
        $moduleMap = new ModuleMap($moduleID);
        $this->_map = $moduleMap;

        if('yes' == strtolower($moduleMap->getAttr('isCacheModule'))){
            $this->isCacheModule = true;  //defaults to FALSE
        }

        //need PK info before loading add-ons
        $module_info_element = $this->_map->selectFirstElement('ModuleInfo', null, null);

        //primary key field(s)
        $pk_element = $module_info_element->selectFirstElement('PrimaryKey');
        if(!$this->isCacheModule && count($pk_element->c) > 1){
            trigger_error("Module $moduleID: Primary keys with multiple fields are strongly discouraged! See documentation at http://www.activeagenda.net/documentation", E_USER_WARNING);
        } elseif(count($pk_element->c) < 1){
            trigger_error("A Primary Key is required.", E_USER_ERROR);
        }
        foreach($pk_element->c as $pkfieldref_element){
            $this->PKFields[] = $pkfieldref_element->name;
        }

        $parent_element = $module_info_element->selectFirstElement('ParentInfo');
        if(!empty($parent_element)){
            $this->parentModuleID = $parent_element->getAttr('parentModuleID');
            $this->parentRecordIDField = $parent_element->getAttr('parentRecordIDField');
        }
        $this->loadXMLAddOns();
        if($this->xmlMerged){
            //re-getting module_info in case add-ons affected the ModuleInfo element
            $module_info_element = $this->_map->selectFirstElement('ModuleInfo', null, null);
        }

        //set the module name:
        $this->Name = $moduleMap->getAttr('name', true);//$module_attributes['name'];
        $this->SingularRecordName = $moduleMap->getAttr('singularRecordName', true);
        $this->PluralRecordName = $moduleMap->getAttr('pluralRecordName', true);
		//START
		$this->GlobalDiscussionAddress = $moduleMap->getAttr('forumID');
		//END
        $this->addNewName = $moduleMap->getAttr('addNewName');
        $this->permissionParentModuleID = $moduleMap->getAttr('parentModule');
        if('no' == strtolower($moduleMap->getAttr('allowAddRecord'))){
            $this->AllowAddRecord = false;  //defaults to TRUE
        }
        if('yes' == strtolower($moduleMap->getAttr('isGlobal'))){
            $this->isGlobal = true; //defaults to FALSE
        }
        if('no' == strtolower($moduleMap->getAttr('includeGlobalModules'))){
            $this->includeGlobalModules = false;  //defaults to TRUE
        }
        if('yes' == strtolower($moduleMap->getAttr('updateImports'))){
            $this->updateImports = true;  //defaults to FALSE
        }
        if('yes' == strtolower($moduleMap->getAttr('dataCollectionForm'))){
            $this->dataCollectionForm = true;  //defaults to FALSE
        }
        if('yes' == strtolower($moduleMap->getAttr('isMasterData'))){
            $this->isMasterData = true;  //defaults to FALSE
        }
        if('yes' == strtolower($moduleMap->getAttr('isTypeModule'))){
            $this->isTypeModule = true;  //defaults to FALSE
        }
        if('yes' == strtolower($moduleMap->getAttr('isImplementationPlanningModule'))){
            $this->isImplementationPlanningModule = true;  //defaults to FALSE
        }
        if('yes' == strtolower($moduleMap->getAttr('isSystemModule'))){
            $this->isSystemModule = true;  //defaults to FALSE
        }
		
		$this->isModuleView = strtolower( $moduleMap->getAttr('isModuleView') );
		if( !empty($this->isModuleView)  ){
            $this->isModuleView = true;  //defaults to FALSE
        }else{
			$this->isModuleView = false;
		}

        if($this->ModuleID != $moduleMap->getAttr('moduleID')){
            trigger_error("The moduleID attribute of the '{$this->ModuleID}' module is not consistent with the file name.", E_USER_WARNING);
        }
		
		$this->defaultMenuPath = $moduleMap->getAttr('defaultMenuPath');  
		
        //owner field
        $owner_field_element = $module_info_element->selectFirstElement('OwnerField');
        if(!empty($owner_field_element)){
            $this->OwnerField = $owner_field_element->name;
        }


        //record Description Field
        $rd_field_element = $module_info_element->selectFirstElement('RecordDescriptionField');
        if(!empty($rd_field_element)){
            $this->recordDescriptionField = $rd_field_element->name;
        }


        //record Label Field
        $rl_field_element = $module_info_element->selectFirstElement('RecordLabelField');
        if(!empty($rl_field_element)){
            $this->recordLabelField = $rl_field_element->name;
        }

        //extending functionality: 
        $ext_element = $module_info_element->selectFirstElement('ExtendModule');
        if($ext_element){ //is false if not successful
            $this->extendsModuleID = $ext_element->getAttr('moduleID', true);
            $this->extendsModuleKey = $ext_element->getAttr('localKey');
            $this->extendsModuleFilterField = $ext_element->getAttr('filterField');
            $this->extendsModuleFilterValue = $ext_element->getAttr('filterValue');
        }

        $revision_info_element = $this->_map->selectFirstElement('Revision', null, null);
        if(!empty($revision_info_element)){
            foreach($revision_info_element->attributes as $attribute_name => $attribute){
                $pattern = '/: (.+) \$/';
                $matches = array();
                preg_match ( $pattern, $attribute, $matches);
                $this->revisionInfo[$attribute_name] = $matches[1];
            }
        }


        global $foreignModules;
        $foreignModules[$moduleID] = &$this;

        //----------------------
        //set up module fields
        //----------------------
		
		
        $modulefields_element = $this->_map->selectFirstElement('ModuleFields', NULL, NULL);

        //make sure to add TableFields FIRST
//START
		$this->ModuleFields['_OwnedBy'] = MakeObject(
            $this->ModuleID,
            '_OwnedBy',
            'TableField',
            array(
                'name' => '_OwnedBy',
                'type' => 'int',
                'dbFlags' => 'unsigned not null default 0',
				'validate'=>'RequireSelection',
                'phrase' => 'Record Owner|ID of the record owner'
            )
        );
		$this->ModuleFields['_WorkGroupID'] = MakeObject(
            $this->ModuleID,
            '_WorkGroupID',
            'TableField',
            array(
                'name' => '_WorkGroupID',
                'type' => 'int',
                'dbFlags' => 'unsigned not null default 0',
                'phrase' => 'Workgroup ID|The ID of the workgroup owning the record'
            )
        );
// END
        foreach($modulefields_element->c as $modulefield_element){
            //we check against MySQL reserved words
            CheckReservedWords($modulefield_element->name);

            if('TableField' == $modulefield_element->type){
                $this->ModuleFields[$modulefield_element->name] = $modulefield_element->createObject($moduleID);
            }
        }
        //then the rest of them
        foreach($modulefields_element->c as $modulefield_element){
            switch($modulefield_element->type){
            case 'ForeignFields':
            case 'RemoteFields':
                $expected_content_type = substr($modulefield_element->type, 0, -1);

                $attrs = $modulefield_element->attributes;
                $cont = $modulefield_element->c;
                foreach($cont as $cont_ix => $grouped_modulefield_element){
                    if($expected_content_type != $grouped_modulefield_element->type){
                        die("Only {$expected_content_type}s are expected within a {$modulefield_element->type} section. Found a {$grouped_modulefield_element->type}\n");
                    }
                    $grouped_modulefield_element->attributes = array_merge(
                        $grouped_modulefield_element->attributes,
                        $attrs
                        );

                    $this->ModuleFields[$grouped_modulefield_element->name] = $grouped_modulefield_element->createObject($moduleID);
                }
                unset($cont);
                break;
            case 'TableField':
                break;
            default:
                $this->ModuleFields[$modulefield_element->name] = 'temp'; //this is a workaround to avoid a circular dependency while generating orgdp.
                $this->ModuleFields[$modulefield_element->name] = $modulefield_element->createObject($moduleID);
                break;
            }
        }

        //append audit trail fields to table def
        $this->ModuleFields['_ModDate'] = MakeObject(
            $this->ModuleID,
            '_ModDate',
            'TableField',
            array(
                'name' => '_ModDate',
                'type' => 'datetime',
                'dbFlags' => 'not null',
                'phrase' => 'Modified On'
            )
        );
        $this->ModuleFields['_ModBy'] = MakeObject(
            $this->ModuleID,
            '_ModBy',
            'TableField',
            array(
                'name' => '_ModBy',
                'type' => 'int',
                'dbFlags' => 'unsigned not null default 0',
                'phrase' => 'Modified By'
            )
        );
        $this->ModuleFields['_Deleted'] = MakeObject(
            $this->ModuleID,
            '_Deleted',
            'TableField',
            array(
                'name' => '_Deleted',
                'type' => 'bool',
                'dbFlags' => 'not null default 0',
                'phrase' => 'Deleted'
            )
        );

        $this->ModuleFields['_TransactionID'] = MakeObject(
            $this->ModuleID,
            '_TransactionID',
            'TableField',
            array(
                'name' => '_TransactionID',
                'type' => 'bigint',
                'dbFlags' => 'unsigned not null default 0',
                'phrase' => 'Transaction ID'
            )
        );

//        if('module' == strtolower(get_class($this))){
        //adding primary key constraint as an index
        $pkObj =& $pk_element->createObjectWithRef($this->ModuleID, 'PrimaryKey', $this);
        $this->Indexes[$pkObj->name] =& $pkObj;

        //indexes
        $index_elements = $module_info_element->selectElements('Index');
        if(count($index_elements) > 0){
            foreach($index_elements as $index_element){
                $indexObj =& $index_element->createObjectWithRef($this->ModuleID, null, $this);
                $this->Indexes[$indexObj->name] =& $indexObj;
                unset($indexObj);
            }
        }

        $uniqueness_elements = $module_info_element->selectElements('UniquenessIndex');
        if(count($uniqueness_elements) > 0){
            $this->uniquenessIndexes = $this->getUniquenessIndexes($uniqueness_elements);
        }

        if(!$this->isSystemModule){
            //make sure ppl module is included
            GetModule('ppl');
            $this->ModuleFields['_ModByName'] = MakeObject(
                $this->ModuleID,
                '_ModByName',
                'ForeignField',
                array(
                    'name' => '_ModByName',
                    'type' => 'text',
                    'localKey' => '_ModBy',
                    'foreignTable' => 'ppl',
                    'foreignKey' => 'PersonID',
                    'foreignField' => 'DisplayName',
                    'joinType' => 'left',
                    'phrase' => 'Modified By'
                )
            );
        }

        if($this->updateImports){
            $this->ModuleFields['_GlobalID'] = MakeObject(
                $this->ModuleID,
                '_GlobalID',
                'TableField',
                array(
                    'name' => '_GlobalID',
                    'type' => 'varchar(20)',
                    'dbFlags' => 'default null',
                    'phrase' => 'Global ID'
                )
            );
        }

        //record label field
        $record_label_element = $module_info_element->selectFirstElement('RecordLabelField');
        if(!empty($record_label_element)){
            $this->recordLabelField = $record_label_element->name;
        } else {
            //look for a RecordDescription field
            if(array_key_exists('RecordDescription', $this->ModuleFields)){
                $this->recordLabelField = 'RecordDescription';
            }
        }

        //$foreignModules[$moduleID] = $this;
        $this->Parsed = true;

        //do the following only if current class name is "Module"
        if('module' == strtolower(get_class($this))){
            $this->loadSubModules();

            //consistency conditions
            $cc_elements = $module_info_element->selectElements('ConsistencyCondition');
            if(count($cc_elements) > 0){
                foreach($cc_elements as $cc_element){
                    $this->consistencyConditions[] =& $cc_element->createObjectWithRef($this->ModuleID, null, $this);
                }
            }
        }
        $moduleParseList[$this->ModuleID] = 'parsed';

    } else {

        $moduleParseList[$this->ModuleID] = 'no file';

        trigger_error("$debug_prefix Could not find XML file '$moduleDefFile'.", E_USER_ERROR);
    }
    debug_unindent();
}


function loadXMLAddOns()
{
    $addOnFiles = array();

    //look for XML central add-ons first
    global $CentralAddOns; //this was passed as "-ca" on the command line to s2a-generate-module.php
    $central_addOns = GetCentralAddOns($this->ModuleID, $CentralAddOns);
    if(count($central_addOns) > 0){
        foreach($central_addOns as $addOnPath){
            $addOnFile = basename($addOnPath);
            $addOnFiles[$addOnFile] = $addOnPath;
        }
    }

    if(defined('ADDON_XML_PATH')){
        $addOnXMLPath = ADDON_XML_PATH;
    } else {
        $addOnXMLPath = XML_PATH . '/addons';
    }
    if(!file_exists($addOnXMLPath)){
        if(!mkdir($addOnXMLPath, 0755)){
            trigger_error("Could not create directory $addOnXMLPath.", E_USER_ERROR);
        }
    }
    $reg_addOns = glob($addOnXMLPath . "/*_{$this->ModuleID}_ModuleAddOn.xml");
    if(count($reg_addOns) > 0){
        foreach($reg_addOns as $addOnPath){
            $addOnFile = basename($addOnPath);
            $addOnFiles[$addOnFile] = $addOnPath;
        }
    }

    if(defined('CUSTOM_XML_PATH')){
        $custom_addOns = glob(CUSTOM_XML_PATH."/*_{$this->ModuleID}_ModuleAddOn.xml");
        if(count($custom_addOns) > 0){
            foreach($custom_addOns as $addOnPath){
                $addOnFile = basename($addOnPath);
                $addOnFiles[$addOnFile] = $addOnPath;
            }
        }
    }
    if(count($addOnFiles) > 0){
        foreach($addOnFiles as $addOnPath){
            $addOnMap = new XMLMap($addOnPath, 'ModuleAddOn');
            unset($addOnMap->attributes['applyToModules']);

            $this->replaceAttributeValues($addOnMap);

            //merge add-on data into $this->_map cleverly, somehow...
            $this->_mergeAddOn($addOnMap, $this->_map);
            unset($addOnMap);

            $truncated_path = str_replace(XML_PATH.'/', '', $addOnPath);
            $this->_map->c[] = new Element('AppliedAddOn', 'AppliedAddOn', array('ref' => $truncated_path));
        }
        $this->xmlMerged = true;
    }

    $this->cleanOutInsertionTargets($this->_map);
}


function _mergeAddOn(&$sourceElement, &$destElement)
{
    if(count($sourceElement->c) > 0){
        foreach($sourceElement->c as &$sourceChild){
            $foundMatch = false;

            //look for corresponding child in $destElement
            if(count($destElement->c) > 0){
                foreach($destElement->c as &$destChild){
                    switch($sourceChild->type){

                    //"section" elements
                    case 'ModuleFields':
                    case 'ModuleInfo':
                    case 'SubModules':
                    case 'Screens':
                    case 'RecordSummaryFields':
                    case 'Charts':
                    //various helper elements
                    case 'Self':
                    case 'Conditions':
                        $matchType = 'type';
                        break;

                    //all types of modulefields
                    case 'TableField':
                    case 'ForeignField':
                    case 'RemoteField':
                    case 'CodeField':
                    case 'CalculatedField':
                    case 'CombinedField':
                      case 'CombinedFieldRef':
                      //some way to handle CombinedField character content
                    case 'SummaryField':
                    //moduleInfo elements (most make no sense to override)
                    case 'Index':
                    //all screen types
                    case 'ListScreen':
                    case 'SearchScreen':
                    case 'ViewScreen':
                      case 'ViewScreenSection':
                    case 'EditScreen':
                      case 'RecordSummaryFieldsRef':
                    case 'RecordReportScreen':
                    case 'ListReportScreen':
                    //all screen fields
                    case 'ListField':
                    case 'OrderByField':
                    case 'EditField':
                    case 'CodeComboField':
                      case 'UpdateFieldRef':
                    case 'ComboField':
                    case 'PersonComboField':
                    case 'CheckBoxField':
                    case 'MemoField':
                    case 'OrgComboField':
                    case 'ViewField':
                    case 'InvisibleField':
                    //all charts
                    case 'PieChart':
                    case 'ParetoChart':
                        $matchType = 'name';
                        break;

                    case 'SubModule':
                    //all grids
                    case 'ViewGrid':
                    case 'EditGrid':
                        $matchType = 'attr';
                        $match = 'moduleID';
                        break;

                    case 'SubModuleCondition':
                    case 'Condition':
                        $matchType = 'attr';
                        $match = 'field';
                        break;

                    default:
                        $matchType = false;
                        break;
                    }

                    //different tests depending on matchType
                    switch($matchType){
                    case 'attr':
                        if(isset($destChild->attributes[$match]) && $sourceChild->attributes[$match] == $destChild->attributes[$match]){
                            $foundMatch = true;
                        }
                        break;
                    case 'name':
                        if($sourceChild->name == $destChild->name){
                            $foundMatch = true;
                        }
                        break;
                    case 'type':
                        if($sourceChild->type == $destChild->type){
                            $foundMatch = true;
                        }
                        break;
                    default:
                        break;
                    }
                    if($foundMatch){
                        $this->_mergeAddOn($sourceChild, $destChild);
                        break; //leave dest loop
                    }
                }
                unset($destChild);
            }
            if(!$foundMatch){
                //look for InsertionTarget, if not found, add at the end
                $insertIX = 0;
                foreach($destElement->c as $childIX => $child){
                    if('InsertionTarget' == $child->type){ //here we may add a check for add-on-specific InsertionTargets if needed
                        $insertIX = $childIX-1;
                        break;
                    }
                }
                if(0  == $insertIX){
                    $destElement->c[] =& $sourceChild;
                } else {
                    array_splice($destElement->c, $insertIX, 0, array($sourceChild));
                }
            }
        }
        unset($sourceChild);
    }
}


function replaceAttributeValues(&$element)
{
    if(count($element->attributes) > 0){
        foreach($element->attributes as $attName => $attValue){
            $attValue = $this->_addOnReplaceAttribute($attValue);
            $element->attributes[$attName] = $attValue;
        }
    }
    if(count($element->c) > 0){
        foreach($element->c as $ix => &$child_element){
            $this->replaceAttributeValues($child_element);//need to copy back
        }
    }
}


/**
 *  Replaces specific strings with host module properties (useful for central module add-ons)
 */
function _addOnReplaceAttribute($value)
{
    switch($value){
    case '**host_moduleID**':
        return $this->ModuleID;
        break;
    case '**host_recordIDField**':
        return end($this->PKFields);
        break;
    case '**host_parent_moduleID**':
        if(empty($this->parentModuleID)){
            trigger_error("A Central Module Add-on uses the replacement string '**host_parent_moduleID**', but the required parentModuleID was not found in the host module '{$this->ModuleID}'.", E_USER_ERROR);
        }
        return $this->parentModuleID;
        break;
    case '**host_parent_recordIDField**':
        if(empty($this->parentRecordIDField)){
            trigger_error("A Central Module Add-on uses the replacement string '**host_parent_recordIDField**', but the required parentRecordIDField was not found in the host module '{$this->ModuleID}'.", E_USER_ERROR);
        }
        return $this->parentRecordIDField;
        break;

    default:
        return $value;
    }
}


function cleanOutInsertionTargets(&$element)
{
    if(is_a($element, 'Element')){
        $insertionTargetIxs = array();
        if(count($element->c) > 0){
            foreach($element->c as $ix => &$child){
                if(is_a($child, 'Element')){
                    if('InsertionTarget' == $child->type){
                        unset($child);
                        unset($element->c[$ix]);
                    } else {
                        $this->cleanOutInsertionTargets($child);
                    }
                 }
            }
        }
    }
}


function getUniquenessIndexes($uniqueness_elements)
{
    $uniquenessIndexes = array();

//go through the XML looking for UniquenessIndex elements
    foreach($uniqueness_elements as $uniqueness_element){
        //...check whether fields are remotefields or tablefields
        $fields = array();
        foreach($uniqueness_element->c as $fieldref_element){
            $mf = $this->ModuleFields[$fieldref_element->name];
            switch(strtolower(get_class($mf))){
            case 'tablefield':
                $fields[$fieldref_element->name] = array('type' => 'tablefield', 's' => $mf->getQualifiedName($this->ModuleID));
                break;
            case 'remotefield':
                $fields[$fieldref_element->name] = array('type' => 'remotefield', 's' => $mf->getQualifiedName($this->ModuleID), 'j' => $mf->makeJoinDef($this->ModuleID));
                break;
            default:
                trigger_error("The field {$fieldref_element->name} referenced in UniquenessIndex {$uniqueness_element->name} is a ".get_class($mf)." must be a TableField or a RemoteField.",E_USER_ERROR);
                break;
            }
        }
        $index_element = new Element($uniqueness_element->name, 'Index', array('name'=>$uniqueness_element->name));
        foreach($fields as $fieldName => $info){
            if('tablefield' == $info['type']){
                $index_element->c[] = new Element($fieldName, 'FieldRef', array('name'=>$fieldName));
            }
        }
        if(count($index_element->c) > 0){
            $indexObj =& $index_element->createObjectWithRef($this->ModuleID, null, $this);
            $this->Indexes[$indexObj->name] =& $indexObj;
            unset($index_element);
            unset($indexObj);
        }
        $uniquenessIndexes[$uniqueness_element->name] = $fields;
    }
    return $uniquenessIndexes;
}


/**
 * returns TRUE if the module uses an auto_increment field
 */
function usesAutoIncrement()
{
    $found = false;
    foreach($this->PKFields as $PKField){
        $moduleField = $this->ModuleFields[$PKField];
        if(false !== strpos($moduleField->dbFlags, "auto_increment")){
            $found = true;
        }
    }
    return $found;
}


/**
 *  Returns the specified submodule without loading all submodules
 */
function &getSubModule($moduleID)
{
    if( isset($this->SubModules[$moduleID]) ){
        return $this->SubModules[$moduleID];
    } else {
        $submodule_elements = $this->_map->selectChildrenOfFirst('SubModules', null, null);
        if(count($submodule_elements) > 0){
            foreach($submodule_elements as $submodule_element){
                if($submodule_element->getAttr('moduleID', true) == $moduleID){
                    $submodule =& $submodule_element->createObject($this->ModuleID);
                    break;
                }
            }
        }
        if(empty($submodule)){
            return false;
        } else {
            return $submodule;
        }
    }
}



/**
 *  Returns the all the submodules, and loads them if not already loaded
 */
function &getSubModules()
{
    if(count($this->SubModules) == 0){
        $this->loadSubModules();
    }
    return $this->SubModules;
}



/**
 *  Creates and loads all the submodules of the module
 */
function loadSubModules()
{
    $debug_prefix = debug_indent("Module->loadSubModules() {$this->ModuleID}:");
    $submodule_elements = $this->_map->selectChildrenOfFirst('SubModules', null, null, true, true);
    if(count($submodule_elements) > 0){
        foreach($submodule_elements as $submodule_element){
            $submodule = $submodule_element->createObject($this->ModuleID);
            $subModuleID = $submodule_element->getAttr('moduleID', true);
            $this->SubModules[$subModuleID] = $submodule;
            print "$debug_prefix Submodule $subModuleID parsed.\n";
            unset($submodule);
        }

        //special for best practices: add the IsBestPractice SummaryField
        if(isset($this->SubModules['bpc'])){
            $this->useBestPractices = true;
            $recordIDField = end($this->PKFields);

            $field_object = MakeObject(
                $this->ModuleID,
                'IsBestPractice',
                'SummaryField',
                array(
                    'name' => 'IsBestPractice',
                    'type' => 'tinyint',
                    'summaryFunction' => 'count',
                    'summaryField' => 'BestPracticeID',
                    'summaryKey' => 'RelatedRecordID',
                    'summaryModuleID' => 'bpc',
                    'localKey' => $recordIDField,
                    'phrase' => 'Is Best Practice|Whether the associated record is a best practice'
                )
            );
//print "best practice auto field";
//print_r($field_object);
//die();
            $this->ModuleFields['IsBestPractice'] = $field_object;
        }

        //copies submodule conditions to summary fields
        foreach($this->ModuleFields as $fieldName => $field){
            if('summaryfield' == strtolower(get_class($field))){
                if(!$field->isGlobal){

                    if(isset($this->SubModules[$field->summaryModuleID])){
                        $subModule =& $this->SubModules[$field->summaryModuleID];
                        if(count($subModule->conditions) > 0){
                            //$field->conditions = $subModule->conditions;
                            $field->conditions = array_merge($subModule->conditions, (array)$field->conditions);
                            $this->ModuleFields[$fieldName] = $field;
                        }
                        unset($subModule);
                    } else {
                        trigger_error("The summaryfield '{$field->name}' requires a '{$field->summaryModuleID}' submodule.", E_USER_ERROR);
                    }
                }
            }
        }
    }
    debug_unindent();
}



/**
 *  Returns the screens of a module (and creates them if they're not yet created)
 */
function &getScreens()
{
    if(count($this->_screens) > 0){
        return $this->_screens;
    } else {
        $debug_prefix = debug_indent("Module-getScreens() {$this->ModuleID}:");
        $screens_element = $this->_map->selectFirstElement('Screens', NULL, NULL);

        if(!empty($screens_element) && count($screens_element->c) > 0){
            foreach($screens_element->c as $screen_element){
                $screen = $screen_element->createObject($this->ModuleID);
                $this->_screens[$screen_element->name] =& $screen;

                switch(strtolower(get_class($screen))){
                case 'viewscreen':
                    $this->viewScreen =& $screen;
                    break;
                case 'searchscreen':
                    $this->searchScreen =& $screen;
                    break;
                case 'listscreen':
                    $this->listScreen =& $screen;
                    break;
                default:
                    //do nothing
                }
                unset($screen);
            }
        }

        debug_unindent();
        return $this->_screens;
    }
//print_r($this->_screens);
}



/**
 *  Returns the a screen by name
 */
function &getScreen($name)
{
    print "getScreen: $name\n";
    if(isset($this->_screens[$name]) && count($this->_screens) > 0){
        return $this->_screens[$name];
    } else {
        if($screens = $this->getScreens()){
            return $screens[$name];
        } else {
            $dummy = null;
            return $dummy;
        }
    }
}



function getScreenOfType($type)
{
    $screens = $this->getScreens();

    if(count($this->_screens) > 0){
        foreach($this->_screens as $screen_name => $screen_object){
            if(strtolower(get_class($screen_object)) == $type){
                return $screen_object;
                break;
            }
        }
        print_r(array_keys($this->_screens));
        die("could not find a screen of type $type");
    } else {
        return null;
    }
}



function &getListFields()
{
    if(!empty($this->listScreen)){
        return $this->listScreen->Fields;
    } else {
        $listScreen =& $this->getScreen('List');
        return $listScreen->Fields;
    }
}


/**
 *  Returns if chart section is defined
 */
function areChartsDefined()
{
	$documentation_element = $this->_map->selectFirstElement('Charts', NULL, NULL);
	if(!empty($documentation_element) && count($documentation_element->c) > 0){
		return true;
	} else {
		return false;
	}    
}

/**
 *  Returns the documentation of the module as an array (creates it if required)
 */
function getDocumentation()
{	
    if(count($this->_documentation) > 0){
        return $this->_documentation;
    } else {
        $debug_prefix = debug_indent("Module-getDocumentation() {$this->ModuleID}:");

        $this->_documentation = array(
            'Introduction' => ''            
        );

        $documentation_element = $this->_map->selectFirstElement('Documentation', NULL, NULL);
        if(!empty($documentation_element) && count($documentation_element->c) > 0){
            foreach($documentation_element->c as $docsection_element){
                $title = $docsection_element->getAttr('title');
                print "$debug_prefix generating section: $title\n";

                $content = '';

                //loop through contents
                foreach($docsection_element->c as $contentItem){
                    switch(strtolower(get_class($contentItem))){
                    case 'element':
                        $content .= $contentItem->getContent();
                        break;
                    case 'characterdata':
                        $content .= $contentItem->content;
                        break;
                    default:
                        die("$debug_prefix unknown content type in documentation section");
                    }
                }

                $this->_documentation[$docsection_element->getAttr('sectionID')] = array($docsection_element->getAttr('title'), $content);
            }
        }
        debug_unindent();
        return $this->_documentation;
    }
}



/**
 *  Returns a list of moduleIDs that belong to remote fields of the module
 *
 *  (Note slight misnomer: returns module IDs, not module objects)
 */
function getRemoteModules()
{
    $remoteModules = array();
    foreach($this->ModuleFields as $mfName => $mf){
        if('remotefield' == strtolower(get_class($mf))){
            $remoteModules[$mfName] = $mf->remoteModuleID;
        }
    }
    return $remoteModules;
}




//**************************************//
//        SQL-related functions         //
//**************************************//

/**
 *  Saves suggested but potentially destructive SQL changes in a text file 
 *
 *  Rather than directly applying DROP COLUMN and data type changes directly,
 *  this function simply logs those changes to the dbChanges.gen file.
 */
function appendTableChangeFile($tableName, $alterations, $alteration_descriptions)
{

    static $overwrite = true;
    if($overwrite){
        $writemode = 'w';
        $overwrite = false;
        $content = "<?php \$alterations_moduleID = '{$this->ModuleID}';\n";
        $content .= "\$alterations = array();\n";
        $content .= "\$alteration_descriptions = array();?>\n";
    } else {
        $writemode = 'a';
        $content = "\n";
    }

    $outFile = GEN_LOG_PATH . '/'.$this->ModuleID.'_dbChanges.gen';

    $content .= "<?php \$alterations['$tableName'] = unserialize('".escapeSerialize($alterations)."');\n";
    $content .= "\$alteration_descriptions['$tableName'] = unserialize('".escapeSerialize($alteration_descriptions)."');?>\n";

    $fh = fopen($outFile, $writemode);
    fwrite($fh, $content);
    fclose($fh);

    return true;
}


/**
 *  Returns whether the database contains the module table already
 */
function checkTableExists($tableName)
{
    $mdb2 =& GetMDB2();
    $SQL = "SELECT count(*) FROM `$tableName` WHERE 1 = 0";
    $res = $mdb2->queryOne($SQL);
    $err = mdb2ErrorCheck($res, false, false, -18);
    switch($err['code']){
    case 0:
        return true;
        break;
    case -18:
        return false;
        break;
    default:
        mdb2ErrorCheck($res);
        die("Error when checking table $tableName.\n");
    }
}



/**
 *  see whether existing table needs to be updated
 */
function checkTableStructure($logTable = false, $confirmed = false)
{
    if( 'module' != strtolower(get_class($this)) ){
        return '';
    }
    $debug_prefix = debug_indent("Module-checkTableStructure($logTable) {$this->ModuleID}:");

    if( $logTable ){
        $tableName = $this->ModuleID . '_l';
    } else {
        $tableName = $this->ModuleID;
    }

    //check that module table fields are in the existing table
    $mdb2defs = array();
    if($logTable){
        $mdb2defs['_RecordID'] = array(
               'type' => 'integer',
               'length' => 4,
               'autoincrement' => 1,
               'notnull' => 1,
               'unsigned' => 1
           );
    }
    foreach($this->ModuleFields as $FieldName => $ModuleField){
        if (!is_object($ModuleField)){
            print $debug_prefix .' '. get_class($this) .' '. $this->ModuleID . "\n";
            print ("$debug_prefix ModuleField $FieldName is empty. where did it come from??\n");
        } else {
            if (strtolower(get_class($ModuleField)) == 'tablefield'){
                $mdb2def = $ModuleField->getMDB2Def();
                if($logTable){
                    unset($mdb2def['autoincrement']);
                }
                $mdb2defs[$FieldName] = $mdb2def;
            }
        }
    }

    //trace($mdb2defs, "{$this->ModuleID} $logTable XML mdb2defs");

    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $mdb2->loadModule('Reverse', null, true);

    $table_info = $mdb2->reverse->tableInfo($tableName, NULL);
    mdb2ErrorCheck($table_info);
    //trace($table_info, "{$this->ModuleID} $logTable tableInfo");

    $drop_fields = array();
    $update_fields = array();
    $field_diffs = array();
    $uses_auto_increment = false;
    $need_confirmation = false;
    foreach($table_info as $field_info){
        $diffs = array();
        if(!isset($mdb2defs[$field_info['name']])){
            $drop_fields[$field_info['name']] = array();
            $field_diffs[$field_info['name']] = array("Dropping field $tableName.{$field_info['name']}.");
            $need_confirmation = true;
        } else {

            //check whether the field needs to be updated
            $update = false;
            $def = $mdb2defs[$field_info['name']];

            //data type
            if($def['type'] != $field_info['mdb2type']){
                if($def['type'] != 'boolean'){
                    $update = true;
                    $need_confirmation = true;

                    $diffs[] = "Data type change from {$field_info['mdb2type']} (native type {$field_info['nativetype']}) to {$def['type']}.";
                } else {
                    //MDB2 seems to not detect booleans but returns them as integer with length 1
                    if('integer' != $field_info['mdb2type'] || 1 != $field_info['length']){
                        $update = true;
                        $need_confirmation = true;
                        $diffs[] = "Data type change from {$field_info['mdb2type']} (native type {$field_info['nativetype']}) to {$def['type']}.";
                    }
                }
            }

            if($def['length'] != $field_info['length']){
                $update = true;
                if($def['length'] < $field_info['length']){
                    $need_confirmation = true;
                    $diffs[] = "Reducing field length from {$field_info['length']} to {$def['length']}.";
                } else {
                    $diffs[] = "Increasing field length from {$field_info['length']} to {$def['length']}.";
                }
            }

            if($def['autoincrement'] != $field_info['autoincrement']){
                $update = true;
                $need_confirmation = true;
                if($def['autoincrement']){
                    $diffs[] = "Adding 'auto increment'.";
                } else {
                    $diffs[] = "Removing 'auto increment'.";
                }
            }

            if($def['notnull'] != $field_info['notnull']){
                $update = true;
                if($def['notnull']){
                    $diffs[] = "Adding 'not null'.";
                    $need_confirmation = true;
                    if(!$logTable && !isset($def['default']) && !isset($def['autoincrement'])){
                        trigger_error("$tableName.{$field_info['name']}: Adding a not null requirement requires a default value.", E_USER_ERROR);
                    }
                } else {
                    $diffs[] = "Removing 'not null'.";
                }
            }

            if($def['default'] != $field_info['default']){
                $diffs[] = "Changing default from {$field_info['default']} to {$def['default']}.";
                $update = true;
            }

            if($def['unsigned'] != $field_info['unsigned']){
                $update = true;
                if($def['unsigned']){
                    $diffs[] = "Adding 'unsigned'.";
                    $need_confirmation = true;
                } else {
                    $diffs[] = "Removing 'unsigned'.";
                }
            }

            if($update){
                $update_fields[$field_info['name']] = array('length' => $def['length'], 'definition' => $def);
                $field_diffs[$field_info['name']] = $diffs;
            }
        }
    }
    $add_fields = array();
    foreach($mdb2defs as $def_fieldname => $def){
        if(isset($def['definition']['autoincrement']) && $def['definition']['autoincrement']){
            $uses_auto_increment = true;
        }

        $exists = false;
        foreach($table_info as $field_info){
            if($field_info['name'] == $def_fieldname){
                $exists = true;
            }
        }
        if(!$exists){
            $add_fields[$def_fieldname] = $def;
        }
    }

    if($need_confirmation && !$confirmed){
        $alterations = array('add' => $add_fields);
        $alterations_to_confirm = array('remove' => $drop_fields, 'change' => $update_fields);
    } else {
        $alterations = array('add' => $add_fields, 'remove' => $drop_fields, 'change' => $update_fields);
        $alterations_to_confirm = array();
    }

    if(!empty($alterations['add']) || !empty($alterations['remove']) || !empty($alterations['change'])){
        //do the table update here!!
        $result = $mdb2->manager->alterTable($tableName, $alterations, false);

        $errorCodes = mdb2ErrorCheck($result, false, true);
        if(0 != $errorCodes['code']){
            if(1068 == $errorCodes['native_code']){
                //drop primary key, then re-do the alterations
                print "Dropping existing primary key...\n";
                Index::drop($tableName, 'PRIMARY', true);

                $result = $mdb2->manager->alterTable($tableName, $alterations, false);
            }
            mdb2ErrorCheck($result);
        }
    }

    if(!$logTable){
        $index_actions = array();
        foreach($this->Indexes as $index_name => $indexObj){
            if('PRIMARY' == $index_name && $uses_auto_increment){
                continue; //skip primary key
            } else {
                $index_actions[$index_name] = $indexObj->verify();
            }
        }

        $table_indexes = Index::getTableIndexList($this->ModuleID);
        foreach($table_indexes as $table_index_name => $unique){
            if(!isset($this->Indexes[$table_index_name]) && 'PRIMARY' != $table_index_name){ //PRIMARY is not in $this->Indexes, therefore don't drop it here
                $index_actions[$table_index_name] = 'drop';
            }
        }
//        trace($index_actions, '$index_actions');
//        trace($table_indexes, '$table_indexes');

        foreach($index_actions as $index_name => $index_action){
            switch($index_action){
            case 'ok':
                break;
            case 'drop':
                Index::drop($this->ModuleID, $index_name, $table_indexes[$index_name]);
                break;
            case 'add':
                $this->Indexes[$index_name]->add();
                break;
            case 'update':
                $this->Indexes[$index_name]->update();
                break;
            default:
                trigger_error("Unknown Index action requested.", E_USER_ERROR);
                break;
            }
        }
    }

    if(count($alterations_to_confirm) > 0){
        //create a file with changes to be examined
        $this->appendTableChangeFile($tableName, $alterations_to_confirm, $field_diffs);

        debug_unindent();
        return 'confirm';
    }

    debug_unindent();
    return '';
}



/**
 *  Creates a table.
 */
function createTable($createLogTable = false)
{
    $tableName = $this->ModuleID;
    if($createLogTable){
        $tableName .= '_l';
    }

    $tableDef = $this->generateTableDef($createLogTable);

    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $result = $mdb2->manager->createTable($tableName, $tableDef['fields'], $tableDef['table_options']);
    mdb2ErrorCheck($result);

    if(!$createLogTable){
        //check for any automatically created PK constraints... (MDB2 does this for mysql auto_increment columns)
        $auto_indexes = $mdb2->manager->listTableConstraints($tableName);
        mdb2ErrorCheck($auto_indexes);
        $has_pk = false;
        foreach($auto_indexes as $index_name){
            if('PRIMARY' == $index_name){
                $has_pk = true;
            }
        }

        if(0 < count($tableDef['constraints'])){
            foreach($tableDef['constraints'] as $name => $def){
                if(!($has_pk && 'PRIMARY' == $name)){
                    $result = $mdb2->manager->createConstraint($tableName, $name, $def);
                    mdb2ErrorCheck($result);
                }
            }
        }

        if(0 < count($tableDef['indexes'])){
            foreach($tableDef['indexes'] as $name => $def){
                $result = $mdb2->manager->createIndex($tableName, $name, $def);
                mdb2ErrorCheck($result);
            }
        }
    }
    trace("Successfully created $tableName table\n");

    if ($this->checkTableExists($tableName)){
        return true;
    } else {
        return false;
    }
}



/**
 *  Returns an array of a complete MDB2 table definition
 */
function generateTableDef($createLogTable = false)
{
    $fieldDefs = array();
    $table_comment = $this->Name;
    if($createLogTable){
        $table_comment .= ' (log table)';

        //length should be at least as long as regular table's PK
        $fieldDefs['_RecordID'] = array(
               'type' => 'integer',
               'length' => 4,
               'autoincrement' => 1,
               'notnull' => 1,
               'unsigned' => 1
           );
    }

    foreach($this->ModuleFields as $fieldName => $field){
        if('tablefield' == strtolower(get_class($field))){
            $def = $field->getMDB2Def();
            if($createLogTable && isset($def['autoincrement'])){
                unset($def['autoincrement']);
            }
            $fieldDefs[$fieldName] = $def;
        }
    }

    $indexDefs = array();
    $constraintDefs = array();
    foreach($this->Indexes as $indexName => $index){
        if($index->unique){
            $constraintDefs[$indexName] = $index->getMDB2Def();
        } else {
            $indexDefs[$indexName] = $index->getMDB2Def();
        }
    }

    $tableOptions = array();
    if(defined('DB_TYPE')){
        switch(DB_TYPE){
        case 'MySQL':
            $tableOptions = array(
                'comment' => $table_comment,
                /* 'charset' => 'utf8',
                'collate' => 'utf8_unicode_ci', */
                'type'    => 'innodb'
            );
            break;
        default:
            break;
        }
    }

    return array('fields' => $fieldDefs, 'indexes' => $indexDefs, 'constraints' => $constraintDefs, 'table_options' => $tableOptions);
}

// trimmed only to MySQL syntax
function generateCreateTriggerSQL()
{
	$updateSQL = '';
	$passValueSQL = '';
	foreach($this->ModuleFields as $vName => $value){
        if(!is_object($value)){
            print_r($this->ModuleFields);
            die("m. _generateCreateTrigger: Field $vName is not a valid ModuleField.");
        } else {
            if (strtolower(get_class($value)) == 'tablefield'){					
                if ( !empty( $value->deleteKeys ) ){
					foreach( $value->deleteKeys as $whereCondition ){
						list( $whereModuleID, $whereColumn ) = preg_split( '/\./', $whereCondition);
						$updateSQL .= "\t\tUPDATE `".$whereModuleID."` SET ".$whereModuleID."._Deleted = 1 WHERE ".$whereCondition." = NEW.".$vName.";\n";						
					}
                }
				if ( !empty( $value->deleteOnIdGlobals ) ){
					foreach( $value->deleteOnIdGlobals as $whereModuleID ){
						$updateSQL .= "\t\tUPDATE `".$whereModuleID."` SET ".$whereModuleID."._Deleted = 1 WHERE ".$whereModuleID.".RelatedModuleID = '".$this->ModuleID."' AND ".$whereModuleID.".RelatedRecordID = NEW.".$vName.";\n";						
					}
                }
				 if ( !empty( $value->passValueOn ) ){
					$passValueSQL .=  "\tIF NEW.".$vName." <> OLD.".$vName." THEN\n";
					foreach( $value->passValueOn as $whereCondition ){
						//xt._TaskStatusID/_ProjectID=xt._ProjectID
						list( $whereModuleIDColumn, $whereKeysCondition ) = preg_split( '/\//', $whereCondition);
						list( $whereModuleID, $whereColumn ) = preg_split( '/\./', $whereModuleIDColumn);						
						$passValueSQL .= "\t\tUPDATE `".$whereModuleID."` SET ".$whereModuleIDColumn." = NEW.".$vName." WHERE  NEW.".$whereKeysCondition.";\n";						
						
					}
					$passValueSQL .= "\tEND IF;\n";
                }
            }
        }
    }
	// No trigger condition found so return nothing	
	if ( empty( $updateSQL ) AND empty( $passValueSQL ) ){ 
		return ''; 
	};	
	// Creating SQL
	$SQL  .= "DROP TRIGGER IF EXISTS ".$this->ModuleID."_afupd ;\n"; 
	$SQL .= 'delimiter //'."\n";
	$SQL .= 'CREATE TRIGGER '.$this->ModuleID.'_afupd AFTER UPDATE ON `'.$this->ModuleID.'` FOR EACH ROW'."\n";
	$SQL .= 'BEGIN'."\n";
	if ( !empty( $updateSQL ) ){
		$SQL .= "\tIF NEW._Deleted = 1 AND OLD._Deleted = 0 THEN\n";
//		$SQL .= "\t\tUPDATE cti SET cti._Deleted = 1 WHERE cti.StateID = StateID;\n";
		$SQL .= $updateSQL;
		$SQL .= "\tEND IF;\n";
	}
	if ( !empty( $passValueSQL ) ){
		$SQL .= $passValueSQL;
	}
	$SQL .= 'END;//'."\n";
	$SQL .= "delimiter ;\n\n";
	
	return $SQL;
}

/**
 *  generate the create table statement(s)
 *
 *  (to be retired)
 */
function generateCreateTableSQL($targetDB = 'MySQL', $makeLogTable = true)
{
    if (!$makeLogTable){
        //create the main table statement
        $SQL = $this->_generateBasicCreateTable(
            $targetDB,
            $this->ModuleID,
            $this->ModuleFields,
            $this->PKFields,
            $this->Indexes
        );

    } else { //make changes from basic structure for audit trail table
        //table name for the log table is Module ID + "_l"
        $logTableName = $this->ModuleID . '_l';

        //copy module table fields to a local array
        $logTableFields = $this->ModuleFields;

        //copy primary key fields to a local array
        $logPKfields = $this->PKFields;

        //copy indexes to a local array
        $logIndexes = $this->Indexes;

        //remove 'auto_increment' from any primary key field
        foreach($logPKfields as $value){
            $logTableFields[$value]->dbFlags =
                str_replace('auto_increment', '',
                    $logTableFields[$value]->dbFlags
                );
        }

        //prepend a special recordID to the beginning of the array
        $logPKfield = MakeObject(
            $this->ModuleID,
            '_RecordID',
            'TableField',
            array(
                'name' => '_RecordID',
                'type' => 'int',
                'dbFlags' => 'unsigned not null auto_increment',
                'phrase' => 'LogID'
            )
        );

        array_unshift($logTableFields, $logPKfield);

        //also prepend it to the primary key list
        array_unshift($logPKfields, '_RecordID');

        //build the CREATE TABLE statement
        $SQL = $this->_generateBasicCreateTable(
            $targetDB,
            $logTableName,
            $logTableFields,
            $logPKfields,
            $logIndexes
        );

        //dispose of the log table array
        unset($logTableFields);
    }
    return $SQL;
}



/**
 *  "private" function to generate a CREATE TABLE statement
 */
function _generateBasicCreateTable($targetDB, $tableName, &$tableFields, $PKFields, $Indexes)
{
    $dbFormat = new DBFormat($targetDB); //object that contains DB-specific translations
    //print_r ($dbFormat);

    $IndexNames = array();

    //fix up index names (need to be unique in at least MS SQL)
    //not so good coding practice for performance but items are few in these arrays
    foreach($Indexes as $key => $value){
        $IndexNames["{$tableName}_{$key}"] = $value;
    }

    //start building the statement
    $SQL = "CREATE TABLE `$tableName` (\n";
    //  print($SQL);
    //  print_r($tableFields);
    //add the fields
    foreach($tableFields as $vName => $value){
        if(!is_object($value)){
            print_r($tableFields);
            die("m. _generateBasicCreateTable: Field $vName is not a valid ModuleField.");
        } else {
            if (strtolower(get_class($value)) == 'tablefield'){
                if(!isset($dbFormat->dataTypes[$value->dataType])){
                    trigger_error("Data type {$value->dataType} not supported.", E_USER_ERROR);
                }
                $SQL .= "   {$value->name} {$dbFormat->dataTypes[$value->dataType]}";
                if (!empty($value->dbFlags)){
                    $flag = $value->dbFlags;
                    foreach($dbFormat->flags as $k=>$v){
                        $flag = str_replace($k, $v, $flag);
                    }
                    $SQL .= " $flag";
                }
                $SQL .= ",\n";

            }
        }

    }

    //add primary key definiton
    $SQL .= "   {$dbFormat->PKDeclaration}(\n      ";
    $SQL .= implode(",\n      ",  $PKFields);
    $SQL .= "\n   )";

    //add MySQL indexes:
    //within the CREATE TABLE statement
    if ($targetDB == 'MySQL'){

/*
        if (count($IndexNames) > 0){
            foreach($IndexNames as $key => $value){
                $SQL .= ",\n   INDEX $key (\n      ";
                $SQL .= implode(",\n      ",  $value);
                $SQL .= "\n   )";
            }
        }
*/
    }

    //close the statement
    if ($targetDB == 'MySQL'){
        $SQL .= "\n) TYPE=InnoDB;\n"; //using transacion-capable tables
    } else {
        $SQL .= "\n);\n";
    }

    //add MS SQL Server indexes:
    //with separate CREATE INDEX statements
    if ($targetDB == 'MSSQL'){
        if (count($IndexNames) > 0){
            foreach($IndexNames as $key => $value){
                $SQL .= "CREATE INDEX $key ON `$tableName` (\n   ";
                $SQL .= implode(",\n   ",  $value);
                $SQL .= "\n);\n";
            }
        }
    }

    unset($dbFormat);

    return $SQL;
}



/**
 *  Returns the SQL filter for permissions
 */
function getOwnerFieldFilter()
{
    if(empty($this->OwnerField)){
        return '';
    }
    $ownerMF = $this->ModuleFields[$this->OwnerField];

    if(empty($ownerMF)){
        print "ModuleField names: ({$this->ModuleID})\n";
        print_r(array_keys($this->ModuleFields));
        trigger_error("Module->getOwnerFieldFilter found an empty/invalid Owner Field named {$this->OwnerField}.", E_USER_ERROR);
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->ModuleID;
    $ownerFieldFilter = $ownerMF->getQualifiedName($this->ModuleID) . ' IN (%s)';
    return $ownerFieldFilter;
}





/**
 *  Used by generateListCountSQL and generateListSQL
 */
function _prepareConditions($conditions, $pExtendingModuleID = null)
{
    $debug_prefix = debug_indent("Module-_prepareConditions() {$this->ModuleID}:");
    print "$debug_prefix\n";
    $parentRecordConditions = array();
    $whereConditions = array();
    $protectJoinAliases = array();
    $SQL = '';

    $use_parent = true;
    if(empty($parentModuleID)){
        if(empty($this->parentModuleID)){
            $use_parent = false;
        } else {
            $parentModuleID = $this->parentModuleID;
        }
    }

    if($use_parent){
        $parentModule =& GetModule($parentModuleID);
        $parentPK = end($parentModule->PKFields);
    }

    if(! empty($pExtendingModuleID )){
        print "extending module conditions: $pExtendingModuleID, {$this->ModuleID}\n";
        $extendingModule = GetModule($pExtendingModuleID);
        if(!empty( $extendingModule->extendsModuleFilterField )){
            $conditions[$extendingModule->extendsModuleFilterField] = $extendingModule->extendsModuleFilterValue;
            print "added extended condition:\n";
            print_r($conditions);
        }
    }

    if(count($conditions) > 0){
        foreach($conditions as $conditionField => $conditionValue){
            print "$debug_prefix Condition $conditionField => $conditionValue\n";

            $conditionModuleField = $this->ModuleFields[$conditionField];
            if(empty($conditionModuleField)){
                die("field {$this->ModuleID}.$conditionModuleField is empty\n");
            }
            $qualConditionField = $conditionModuleField->getQualifiedName($this->ModuleID);
            $conditionJoins = $conditionModuleField->makeJoinDef($this->ModuleID);
            if(count($conditionJoins) > 0){
                foreach(array_keys($conditionJoins) as $conditionJoinAlias){
                    $protectJoinAliases[] = $conditionJoinAlias;
                }
            }

            if($use_parent){
                if(preg_match('/\[\*([\w]+)\*\]/', $conditionValue, $match[1])){
                    if($match[1][1] == $parentPK){
                        $whereConditions[$qualConditionField] = '/**RecordID**/';
                    } else {
                        print "SM found match $match[1][0]\n";
                        $parentRecordConditions[$qualConditionField] = $match[1][1];
                    }
                } else {
                    $whereConditions[$qualConditionField] = $conditionValue;
                }
            } else {
                $whereConditions[$qualConditionField] = $conditionValue;
            }
        }
    }

    if($use_parent){
        $needParentSubselect = false;
        if(count($parentRecordConditions) > 0){
            foreach($parentRecordConditions as $localConditionField => $parentConditionField){
                $parentModuleField = $parentModule->ModuleFields[$parentConditionField];
                if(empty($parentModuleField)){
                    die("field {$this->parentModuleID}.$parentConditionField is empty\n");
                }
                if(strtolower(get_class($parentModuleField)) != 'tablefield'){
                    $needParentSubselect = true;
                }
            }
            if($needParentSubselect){
                $parentJoins = array();
                $parentSelects = array();
                foreach($parentRecordConditions as $localConditionField => $parentConditionField){
                    $parentModuleField = $parentModule->ModuleFields[$parentConditionField];
                    $parentSelects[] = $parentModuleField->makeSelectDef('iparent');
                    $parentJoins = array_merge($parentJoins, $parentModuleField->makeJoinDef('iparent'));
                }

                $subSQL = 'SELECT ';
                $subSQL .= implode(",\n", $parentSelects);
                $subSQL .= "\nFROM `{$this->parentModuleID}` AS `iparent`\n";
                $parentJoins = SortJoins($parentJoins);
                $subSQL .= implode("\n   ", $parentJoins);
                $subSQL .= "\nWHERE `iparent`.$parentPK = '/**RecordID**/' \n";

                $SQL = "\n INNER JOIN ($subSQL) AS `parent` ON (\n";
                $parentConditionStrings = array();
                foreach($parentRecordConditions as $localConditionField => $parentConditionField){
                    $parentConditionStrings[] = "`parent`.$parentConditionField = $localConditionField";
                }
                $SQL .= implode("\n AND ", $parentConditionStrings);
                $SQL .= ")\n";
            } else {
                $SQL = "\n INNER JOIN `{$this->parentModuleID}` AS `parent` ON (\n";
                $parentConditionStrings = array();
                foreach($parentRecordConditions as $localConditionField => $parentConditionField){
                    $parentModuleField = $parentModule->ModuleFields[$parentConditionField];
                    $qualParentConditionField = $parentModuleField->getQualifiedName('parent');
                    $parentConditionStrings[] = "$localConditionField = $qualParentConditionField";;
                }
                $SQL .= implode("\n AND ", $parentConditionStrings);
                $SQL .= ")\n";

                $whereConditions["`parent`.$parentPK"] = '/**RecordID**/';
            }

        }
    }

    debug_unindent();
    return array(
        'parentJoinSQL' => $SQL,
        'whereConditions' => $whereConditions,
        'protectJoinAliases' => $protectJoinAliases
    );
}


/**
 *  generates the Count SQL statement
 */
function generateListCountSQL(&$Grid, $pExtendingModuleID = null)
{
    $debug_prefix = debug_indent("Module-generateListCountSQL() {$this->ModuleID}:");
    $extended = false;

    $Fields =& $Grid->Fields;
    if(! empty($pExtendingModuleID )){
        $extended = true;
    }

    if(!empty($this->extendsModuleID)){
        if($Grid->listExtended && $this->ModuleID == $Grid->moduleID){

            print "$debug_prefix extending {$this->extendsModuleID} with {$this->ModuleID} ({$Grid->moduleID})\n";
            $extendedModule = GetExtendedModule($this->extendsModuleID, $this->ModuleID);

            debug_unindent();
            //call same function for the extended module
            return $extendedModule->generateListCountSQL($Grid, $this->ModuleID);
        }
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->ModuleID;

    //array of fields in the SELECT statement (just to be able to use 'implode')
    $Joins = array();

    //ensuring that OwnerField is included
    $ownerAliases = array();
    if(!empty($this->OwnerField)){
        if(! array_key_exists($this->OwnerField, $Fields)){
            $ownerMF = $this->ModuleFields[$this->OwnerField];
            $Joins = array_merge($Joins, GetJoinDef($ownerMF->name));
            $ownerAliases = array_keys($Joins);
        }
    }

    $conditions = array();
    if(isset($Grid->conditions)){
        $conditions = $Grid->conditions;
    }
    if(isset($this->conditions)){
        $conditions = array_merge((array)$this->conditions, (array)$conditions);
    }

    if(!empty($this->localKey)){
        if(!empty($this->parentKey)){
            $conditions[$this->localKey] = '[*'.$this->parentKey.'*]';
        } else {
            die("Found non-empty submodule local key but no corresponding parent key.");
        }
    }

    if(count($conditions) > 0){
        foreach($conditions as $conditionField => $conditionValue){
            if(!array_key_exists($conditionField, $Fields)){
                $conditionMF = $this->ModuleFields[$conditionField];
                if('tablefield' != strtolower(get_class($conditionMF))){
                    //$Joins = array_merge($Joins, $conditionMF->makeJoinDef($this->ModuleID));
                    $Joins = array_merge($Joins, GetJoinDef($conditionMF->name));
                }
            }
        }
    }

    $SQL = "SELECT \n   ";
    foreach($Fields as $fName => $field){

        print "$debug_prefix field: $fName\n";

        //look up corresponding ModuleField based on list field name
        $mf = $this->ModuleFields[$fName];
        if(!is_object($mf)){
            die("$debug_prefix Field $fName is not a valid ModuleField.");
        }
        //$Joins = array_merge($Joins, $mf->makeJoinDef($this->ModuleID));
        $Joins = array_merge($Joins, GetJoinDef($mf->name));

        print "$debug_prefix Adding JoinDef of ".get_class($mf)." {$field->name}\n";
    }
    $SQL .= " count(*) ";
    $SQL .= "\nFROM `{$this->ModuleID}`\n   ";

    $Joins = SortJoins($Joins);

    $prepared_conditions = $this->_prepareConditions($conditions, $pExtendingModuleID);
    $parentJoinSQL = $prepared_conditions['parentJoinSQL'];
    $whereConditions = $prepared_conditions['whereConditions'];
    $protectJoinAliases = $prepared_conditions['protectJoinAliases'];
    $protectJoinAliases = array_merge($protectJoinAliases, $ownerAliases);

    foreach($Joins as $key => $join){
        if(!in_array($key, $protectJoinAliases)){
            $joinStr = substr($join, 0, 20);
            if(false !== strpos($joinStr, 'OUTER JOIN')){
                print "$debug_prefix unsetting uneeded join '$key'\n";
                unset($Joins[$key]); //a COUNT(*) statement isn't usually affected by outer joins
            }
        }
    }

    $SQL .= implode("\n   ", $Joins);

    if(!empty($parentJoinSQL)){
        $SQL .= $parentJoinSQL;
    }

    $SQL .= "\nWHERE {$this->ModuleID}._Deleted = 0\n   ";
    foreach($whereConditions as $whereCondition => $whereConditionValue){
        if(false === strpos($whereConditionValue, '|')){
            $SQL .= "AND $whereCondition = '$whereConditionValue'\n";
        } else {
            $whereConditionValues = explode('|', $whereConditionValue);
            $whereConditionString = join('\',\'', $whereConditionValues);
            $SQL .= "AND $whereCondition IN ('$whereConditionString')\n";
        }
    }

    //close the statement
    $SQL .= ""; //NO ENDING NEWLINE - that will cause error messages...

    print "$debug_prefix list count SQL:\n";
    print $SQL."\n";

    //verify that the SQL statement will execute without error
    CheckSQL($SQL);

    debug_unindent();
    return $SQL;
}


function generateListSQL(&$Grid, $pExtendingModuleID = null)
{
    $debug_prefix = debug_indent("Module-generateListSQL() {$this->ModuleID}:");
    print "$debug_prefix pExtendingModuleID = '$pExtendingModuleID'\n";

    $extended = false;
    $listModuleID = $this->ModuleID;

    if( !empty($pExtendingModuleID ) ){
        $extended = true;
    }

    switch( strtolower(get_class($Grid)) ){
    case 'uploadgrid':
        if( $Grid->hasGridForm ){
            //make sure the form fields are used over view list fields (duplicate field names in the latter array overwrites fields from the former)
            $Fields = array_merge($Grid->Fields, $Grid->FormFields);

        }else{
            $Fields = $Grid->Fields;
        }
        break;
    case 'selectgrid':
    case 'searchselectgrid':
    case 'codeselectgrid':
        return '';
        break;
    case 'permissiongrid';
        return $Grid->listSQL;
        break;
    default:
        $Fields = $Grid->Fields;
    }

    print "$debug_prefix grid Fields\n";
    indent_print_r(array_keys($Fields));

    if( !empty($this->extendsModuleID) ){
        if( $Grid->listExtended && $this->ModuleID == $Grid->moduleID ){

            print "$debug_prefix extending the module {$this->extendsModuleID} with {$this->ModuleID} ({$Grid->moduleID})\n";
            $extendedModule = GetExtendedModule($this->extendsModuleID, $this->ModuleID);
            print "extended module is a ".get_class($extendedModule)."\n";
            //call same function for the extended module
            debug_unindent();
            return $extendedModule->generateListSQL($Grid, $this->ModuleID);

        }
    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $listModuleID;

    print "$debug_prefix listModuleID = $listModuleID\n";

    //array of fields in the SELECT statement
    $SelectFieldNames = array();

    //ensure that OwnerField is included
    if( !empty($this->OwnerField) ){
        if( ! array_key_exists($this->OwnerField, $Fields) ){
            $SelectFieldNames[$this->OwnerField] = true;
        }
    }

    //ensure that local key is in the conditions
    $conditions = array();
    if( isset($Grid->conditions) ){
        $conditions = $Grid->conditions;
    }
    if( isset($this->conditions) ){
        $conditions = array_merge((array)$this->conditions, (array)$conditions);
    }

    if( !empty($this->localKey) ){
        if( !empty($this->parentKey) ){
            $conditions[$this->localKey] = '[*'.$this->parentKey.'*]';
        }else{
            die("Found non-empty submodule local key but no corresponding parent key.");
        }
    }

    //ensures that local condition field is in the SELECT fields
    if( count($conditions) > 0 ){
        foreach( $conditions as $conditionField => $conditionValue ){
            if( !array_key_exists($conditionField, $Fields) ){
                $conditionMF = $this->ModuleFields[$conditionField];
                if( 'tablefield' != strtolower(get_class($conditionMF)) ){
                    if( !array_key_exists($conditionField, $Fields) ){
                        $SelectFieldNames[$conditionField] = false;
                    }
                }
            }
        }
    }
    foreach( $Fields as $fieldName => $field ){

        //combo fields etc have several ModuleFields
        $SelectFieldNames = array_merge( $SelectFieldNames, $field->getSelectFields() );

        if( !empty($field->linkField) ){
            $SelectFieldNames[$field->linkField] = true;
        }
        if( !empty($field->formatField) ){
            $SelectFieldNames[$field->formatField] = true;
        }

    }

    //make sure module rowID is the first column
    $rowIDFieldName = end($this->PKFields); //if there are more than one PK field, use the LAST
    if( reset(array_keys($SelectFieldNames)) != $rowIDFieldName ){
        unset($SelectFieldNames[$rowIDFieldName]);
        $SelectFieldNames = array_merge( array($rowIDFieldName=>true),$SelectFieldNames );
    }

    $Joins = array();
    foreach( $SelectFieldNames as $SelectFieldName => $makeSelect ){

        //look up corresponding ModuleField based on list field name
        $mf = $this->ModuleFields[$SelectFieldName];
        if( !is_object($mf) ){
            indent_print_r( $this->ModuleFields, true, 'module fields' );
            print "$debug_prefix SelectFieldNames:\n";
            indent_print_r($SelectFieldNames);
            die("$debug_prefix Field $SelectFieldName is not a valid ModuleField.");
        }

        //delegates the creation of select and from clauses to the module fields
        if( $makeSelect ){
            //$SelectFields[] = $mf->makeSelectDef($listModuleID, true);
            $SelectFields[] = GetSelectDef($mf->name);
        }
        //$Joins = array_merge($Joins, $mf->makeJoinDef($listModuleID));
        $Joins = array_merge($Joins, GetJoinDef($mf->name));

    }

    $prepared_conditions = $this->_prepareConditions($conditions, $pExtendingModuleID);
    $parentJoinSQL = $prepared_conditions['parentJoinSQL'];
    $whereConditions = $prepared_conditions['whereConditions'];

    $SQL = "SELECT \n   ";
    $SQL .= implode(",\n", $SelectFields);
    $SQL .= "\nFROM `{$this->ModuleID}`\n";

    //ensure that joins are sorted well
    $Joins = SortJoins($Joins);

    //adds joins
    $SQL .= implode("\n   ", $Joins);

    if( !empty($parentJoinSQL) ){
        $SQL .= $parentJoinSQL;
    }
    $SQL .= "\nWHERE {$listModuleID}._Deleted = 0\n";

    foreach( $whereConditions as $whereCondition => $whereConditionValue ){
        if( false === strpos($whereConditionValue, '|') ){
            $SQL .= "AND $whereCondition = '$whereConditionValue'\n";
        }else{
            $whereConditionValues = explode('|', $whereConditionValue);
            $whereConditionString = join('\',\'', $whereConditionValues);
            $SQL .= "AND $whereCondition IN ('$whereConditionString')\n";
        }
    }

    //verifies that the SQL statement will execute without error
    CheckSQL($SQL);

    $SQL .= "\n";

    print "$debug_prefix listSQL:\n";
    indent_print_r($SQL . "\n");

    debug_unindent();
    return $SQL;
}


    //generates the SQL statement used for the list in the CheckGrids and CodeCheckGrids
    function generateCheckListSQL($Grid)
    {
        //(for grids, this function is called on the associated SubModule)

        print "\n";
        print "primaryListField: {$Grid->primaryListField}\n";
        
        $primaryListField = $Grid->Fields[$Grid->primaryListField];
        //print_r($primaryListField);
        
        $t_ModuleFields = GetModuleFields($Grid->moduleID);
        $values_PKField = reset($t_ModuleFields);
        
        global $SQLBaseModuleID;
        $SQLBaseModuleID = $Grid->moduleID;
        
        $listAlias = GetTableAlias($t_ModuleFields[$Grid->primaryListField], $Grid->moduleID);
        print "listAlias: $listAlias\n";
        
        $list_ModuleField = $t_ModuleFields[$Grid->primaryListField];
        $list_PKField = $list_ModuleField->foreignKey;
        print "list_PKField: $list_PKField\n";
        //die();
        
        $Fields = $Grid->Fields;

        

        //array of fields in the SELECT statement (just to be able to use 'implode')
        $SelectFields = array();
        $ValueConditions = array();

        $ValueConditions[] = $Grid->moduleID . '._Deleted = 0';

        $SQL = "SELECT \n   ";
        //add PK of list module, to be used as insert keys
        $SelectFields[] = "$listAlias.$list_PKField AS RowID";

        foreach($Fields as $value){
            if('Checked' != $value->name){ //allow special field Checked to pass
                //combo grid fields etc have several ModuleFields
                $SelectFieldNames = $value->getSelectFields();
    
                if(!empty($value->linkField)){
                    $SelectFieldNames[] = $value->linkField;
                }
                
                foreach($SelectFieldNames as $SelectFieldName){
                    //look up corresponding ModuleField based on list field name
                    $mf = $this->ModuleFields[$SelectFieldName];
                    if(!is_object($mf)){
                        print_r($this->ModuleFields);
                        die("m. generateCheckListSQL: Field $SelectFieldName is not a valid ModuleField.");
                    }
                    
                    //delegate the creation of select and from clauses to the module fields
                    //$SelectFields[] = $mf->makeSelectDef($this->ModuleID);
                    $SelectFields[] = GetSelectDef($mf->name);
                    //$Joins = array_merge($Joins, $mf->makeJoinDef($listModuleID));
                    $Joins = array_merge($Joins, GetJoinDef($mf->name));
                    
                } //foreach
                
                $mf = $this->ModuleFields[$value->name];
                print "m. Adding ".get_class($mf)." {$value->name}\n";
            
            } else {
                $SelectFields[] = "IF({$Grid->moduleID}.{$values_PKField->name} IS NOT NULL, $listAlias.$list_PKField, NULL) AS Checked";
            }
        }

        $SQL .= implode(",\n   ", $SelectFields);

        
            
        $listJoin = $Joins[$listAlias];
        $copy_start = strpos($listJoin, ' JOIN ') + 6;
        $copy_end = strpos($listJoin, 'ON ');
        $copy_length = $copy_end - $copy_start-1;
        $listTableAndAlias = trim(substr($listJoin, $copy_start, $copy_length));
        print "copy_start: $copy_start\n";
        print "copy_end: $copy_end\n";
        print "copy_length: $copy_length\n";
        print "listTableAndAlias: $listTableAndAlias\n";
        
        //swap list table and alias with module ID 
        $listJoin = str_replace($listTableAndAlias, $this->ModuleID.' ', $listJoin);
        //append value conditions
        if(count($ValueConditions) > 0){
            $listJoin = str_replace(')', ' AND '.join(' AND ', $ValueConditions).' /*ValueConditions*/)', $listJoin);
        } else {
            $listJoin = str_replace(')', ' /*ValueConditions*/)', $listJoin);
        }
        $Joins[$listAlias] = $listJoin;
        
        //print_r($Joins);
        //die();

        //$SQL .= "\nFROM {$this->ModuleID}\n   ";
        $SQL .= "\nFROM $listTableAndAlias\n  ";
        
        $Joins = SortJoins($Joins);
        
        //add joins here
        $SQL .= implode("\n   ", $Joins);


        $SQL .= "\nWHERE {$listAlias}._Deleted = 0\n   ";
        if(!empty($list_ModuleField->listCondition)){
            $SQL .= " AND {$listAlias}.{$list_ModuleField->listCondition}\n";
        }
        
        //close the statement
        $SQL .= ""; //NO ENDING NEWLINE - that will cause error messages...



        print $SQL."\n";
        
        //verify that the SQL statement will execute without error
        CheckSQL($SQL);     


//print "checkListSQL:\n";
//print $SQL . "\n";
        //die();
        return $SQL;
    }

//generates the SQL statement used to get a record
function generateGetSQL($ScreenName, $section = null, $isGrid = false)
{
    $sectionModuleID = '';
    if(!empty($section)){
        $sectionModuleID = $section->moduleID;
    }
    $debug_prefix = debug_indent("Module-generateGetSQL() {$this->ModuleID} $ScreenName {$sectionModuleID}:");

    $fields = array();
    $isScreen = false;

    if(empty($section)){
        if($ScreenName == 'ListFields'){ //yes, we use ListFields to make Labels
            print "$debug_prefix getting ListFields\n";
            $fields = $this->getListFields();
        } else {

            if(is_array($ScreenName)){
                $fields = $ScreenName;
            } else {

                if($ScreenName == 'View'){
                    print "$debug_prefix getting View Screen Fields\n";
                    $Screen =& $this->viewScreen;
                } else {
                    print "$debug_prefix getting screen fields\n";
                    $Screen = $this->getScreen($ScreenName); //&
                }
                $fields = $Screen->Fields;
                $isScreen = true;
            }
        }
    } else {

        //$Screen = $section;
        print "$debug_prefix getting View Screen Section Fields\n";
        $fields = $section->Fields;

    }

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->ModuleID;

    //handle sub-fields - we "flatten" the subfield hierarchy
    $allFields = array();
    foreach($fields as $sfName => $sf){
        $allFields = array_merge($allFields, $sf->getRecursiveFields());
    }

    $selectFieldNames = array();
    foreach($allFields as $fieldName => $field){
        $selectFieldNames = array_merge($selectFieldNames, $field->getSelectFields());
    }
    if($isScreen){
        if(!empty($this->recordLabelField)){
            if(!array_key_exists($this->recordLabelField, $selectFieldNames)){
                $selectFieldNames[$this->recordLabelField] = true;
            }
        }
    }
    if(!empty($this->OwnerField)){
        if(!array_key_exists($this->OwnerField, $selectFieldNames)){
            $selectFieldNames[$this->OwnerField] = true;
        }
    }

    //ensure that local key is in the conditions
    $conditions = array();
    if(isset($this->conditions)){
        $conditions = $this->conditions;
    }
    if(!empty($this->localKey)){
        if(!empty($this->parentKey)){
            $conditions[$this->localKey] = '[*'.$this->parentKey.'*]';
        } else {
            die("Found non-empty submodule local key but no corresponding parent key.");
        }
    }

    //ensures that local condition field is in the SELECT fields
    if(count($conditions) > 0){
        foreach($conditions as $conditionField => $conditionValue){
            if(!array_key_exists($conditionField, $fields)){
                $conditionMF = $this->ModuleFields[$conditionField];
                if('tablefield' != strtolower(get_class($conditionMF))){
                    if(!array_key_exists($conditionField, $fields)){
                        $selectFieldNames[$conditionField] = false;
                    }
                }
            }
        }
    }


    //array of fields in the SELECT statement (just to be able to use 'implode')
    $SelectDefs = array();
    $Joins=array();

    $SQL = "SELECT \n   ";
    foreach($selectFieldNames as $fieldName => $displayed){

        //look up corresponding ModuleField based on screen field name
        $mf = $this->ModuleFields[$fieldName];

        print "$debug_prefix mfname: " . $mf->name . "\n";
        if(empty($mf)){

            indent_print_r($fields, true, 'fields');
            indent_print_r($selectFieldNames, true, 'selectFieldNames');
            die("$debug_prefix The screen {$Screenname} contains a field {$fieldName} that is not in the ModuleFields of the {$this->ModuleID} module.\n");
        } else {
            if($displayed){
                $SelectDefs[] = GetSelectDef($mf->name);
            }
            $Joins = array_merge($Joins, GetJoinDef($mf->name));
        }
    }

    $SQL .= implode(",\n   ", $SelectDefs);
    $SQL .= "\nFROM `{$this->ModuleID}`\n   ";

    $Joins = SortJoins($Joins);


    //special for GET: record ID condition
    $pkField = end($this->PKFields);
    if($isGrid){
        $conditions[$pkField] = '/**RowID**/';
    } else {
        $conditions[$pkField] = '/**RecordID**/';
    }

    $prepared_conditions = $this->_prepareConditions($conditions);
    $parentJoinSQL = $prepared_conditions['parentJoinSQL'];
    $whereConditions = $prepared_conditions['whereConditions'];

    $SQL .= implode("\n   ", $Joins);

    if(!empty($parentJoinSQL)){
        $SQL .= $parentJoinSQL;
    }
    
    $SQL .= "\nWHERE {$this->ModuleID}._Deleted = 0\n   ";
    foreach($whereConditions as $whereCondition => $whereConditionValue){
        if(false === strpos($whereConditionValue, '|')){
            $SQL .= "AND $whereCondition = '$whereConditionValue'\n";
        } else {
            $whereConditionValues = explode('|', $whereConditionValue);
            $whereConditionString = join('\',\'', $whereConditionValues);
            $SQL .= "AND $whereCondition IN ('$whereConditionString')\n";
        }
    }
    print "$debug_prefix $SQL\n";

    //verify that the SQL statement will execute without error
    CheckSQL(str_replace(array('/**RecordID**/', '/**RowID**/'), array('1', '1'), $SQL));

    debug_unindent();
    return $SQL;
}

    //helper function to determine whether to make SQL statements to insert or update fields
    //i.e. if an EditScreen only has View fields (and one or more EditGrids),
    //don't bother adding SQL statements that will never be used
    function CheckForEditableFields($ScreenName){
        $Screen = &$this->getScreen($ScreenName);
        //$Screen = &$this->Screens[$ScreenName];
        $nEditableFields = 0;
        foreach($Screen->Fields as $sf){
            if ($sf->isEditable()){
                $nEditableFields += 1;
            }
        }
        if ($nEditableFields > 0){
            return true;
        } else {
            return false;
        }
    }



    /**
     * generates the SQL statement used to insert a record
     *
     * expects an EditScreen or EditGrid object
     */
    function generateInsertSQL(&$Screen, $logTable, $format = 'var')
    {
        //some sanity checking for the format parameter
        switch($format){
        case 'var':
        case 'replace':
            break;
        default:
            die("ERROR: Parameter \$format in function generateInsertSQL expects either 'var' or 'replace'.\n");
        }

        //array of fields in the SELECT statement (just to be able to use 'implode')
        $InsertFields = array();

        if ($logTable){
            $tableName = $this->ModuleID."_l";

            $PKFieldName = end($this->PKFields);
            if('var' == $format){
                $InsertFields[$PKFieldName] = "\"'\".\$recordID.\"'\"";
            } else {
                $InsertFields[$PKFieldName] = '/**RecordID**/';
            }

        } else {
            $tableName = $this->ModuleID;
            //inserts should include the PK field if it's not auto_increment
            foreach($this->PKFields as $PKFieldName){
                if(false === strpos($this->ModuleFields[$PKFieldName]->dbFlags, 'auto_increment')){
                    if('var' == $format){
                        $InsertFields[$PKFieldName] = "\"'\".\$recordID.\"'\"";
                    } else {
                        $InsertFields[$PKFieldName] = '/**RecordID**/';
                    }
                }
            }
        }

        
        if (in_array(strtolower(get_class($Screen)), array('editgrid', 'uploadgrid'))){
            //add grid specific fields 
            if(!empty($Screen->localKey)){
                $InsertFields[$Screen->localKey] = '/**PR-ID**/';
            }
            
            if($Screen->listExtended){
                $extendingModule = GetModule($Screen->moduleID);
                $InsertFields[$extendingModule->extendsModuleKey] = '/**extendID**/';
            }

            if(count($Screen->conditions) > 0){
                $conditions = $Screen->conditions;
            } else {
                $conditions = $this->conditions;
            }

            if(count($conditions) > 0){
                foreach($conditions as $condField => $condValue){
                    if(!in_array($condField, $InsertFields)){
                        $InsertFields[$condField] = "'{$condValue}'";
                    }
                }
            }
            
            
            //pick the right set of editable fields
            if($Screen->hasGridForm){
                $t_fields = $Screen->FormFields;
            } else {
                $t_fields = $Screen->Fields;
            }
        } else {
            if(is_array($Screen)){
                $t_fields = $Screen;
            } else {
                $t_fields = $Screen->Fields;
            }
        }
//print_r($t_fields);
        //handle sub-fields - we "flatten" the subfield hierarchy
        $saveFields = array();
        foreach($t_fields as $sfName => $sf){
            //print "screen field name $sfName\n";
            $saveFields = array_merge($saveFields, $sf->getRecursiveFields());
        }

        foreach($saveFields as $sfName => $sf){

            //only insert editable fields (no ViewFields etc)
            if ($sf->isEditable()){

                //look up corresponding ModuleField based on screen field name
                $mf = $this->ModuleFields[$sfName];

                switch (strtolower(get_class($mf))){
                case 'tablefield':
                    $insert = false;
                    //exclude any auto_increment field
                    if ($logTable){
                        if ("_RecordID" != $sfName){
                            $insert = true;
                        }
                    } else {
                        if (false === strpos($mf->dbFlags, "auto_increment")){
                            $insert = true;
                        }
                    }
                    if ($insert){
                        if('var' == $format){
                            $InsertFields[$sfName] = "dbQuote(\$data['{$sfName}'], '{$mf->dataType}')";
                            
                        } elseif('replace' == $format){
                            //using replace-style formatting, the dbQuote,
                            //ChkFormat and and DateToISO must be called
                            //outside this function.
                            $InsertFields[$sfName] = "'[*$sfName*]'"; //this is a tag to be replaced before the prepared SQL is executed
                        }
                    }
                    break;
                case 'remotefield';
                    //this should save to the remote table - must generate separate SQL statement
                    //(ignore RemoteFields here)

                    break;
                case 'foreignfield':
                case 'codefield':
                case 'dynamicforeignfield':
                case 'calculatedfield':
                    //die("Cannot use a foreign field in INSERT statement ($sfName)\n");
                    print "AA warning: (Screen {$Screen->name}, Field $sfName, Table $tableName)\n";
                    print "         Non-saving field type used in form - INSERT statement will not save data  for this field. \n";
                    print "         This may be OK if this field is used for combo box filtering only.\n";
                    break;

                default:
                    print "m. field type for INSERT statement: '".get_class($mf)."'\n";
                    die("m. Unknown field type in INSERT statement (Screen {$Screen->name}, Field $sfName, Table $tableName)\n");
                    break;
                }
            }
        }

        //add log data:
        if('var' == $format){
            $InsertFields['_ModDate'] = '"NOW()"';
            $InsertFields['_ModBy'] = '$User->PersonID';
        } elseif('replace' == $format){
            $InsertFields['_ModDate'] = 'NOW()';
            $InsertFields['_ModBy'] = '[**UserID**]';
        }
//print "generateInsertSQL {$this->ModuleID} InsertFields: \n";
//print_r($InsertFields);

        if (count($InsertFields) > 0){
            //format SQL Statement
            if('var' == $format){
                $SQL = "INSERT INTO `$tableName` (\n   ";
                $SQL .= implode(",\n   ", array_keys($InsertFields));
                $SQL .= "\n) VALUES (\n   \".";
                $SQL .= implode(".\",\n   \".", $InsertFields);
                $SQL .= ".\")"; //NO ENDING NEWLINE - that will cause error messages...
            } elseif('replace' == $format){
                $SQL = "INSERT INTO `$tableName` (\n   ";
                $SQL .= implode(",\n   ", array_keys($InsertFields));
                $SQL .= "\n) VALUES (\n   ";
                $SQL .= implode(",\n   ", $InsertFields);
                $SQL .= ")"; //NO ENDING NEWLINE - that will cause error messages...
            }
        } else {
            $SQL = "";
        }
        return $SQL;
    }


    //generates the SQL statement used to update a record
    function generateUpdateSQL(&$Screen, $format = 'var')
    //now expects an EditScreen or EditGrid object
    //function generateUpdateSQL($ScreenName)
    {
        global $SQLBaseModuleID;
        $SQLBaseModuleID = $this->ModuleID;

        //some sanity checking for the format parameter
        if ($format != 'var' && $format != 'replace'){
            if (empty($format)) {
                $format = 'var';
            } else {
                die("ERROR: Parameter \$format in function generateUpdateSQL expects either 'var' or 'replace'.\n");
            }
        }

        //array of fields in the SQL statement (just to be able to use 'implode')
        $UpdateFields = array();

        $tableName = $this->ModuleID;
        
        if (in_array(strtolower(get_class($Screen)), array('editgrid', 'uploadgrid'))){
            //pick the right set of editable fields
            if($Screen->hasGridForm){
                $t_fields = $Screen->FormFields;
            } else {
                $t_fields = $Screen->Fields;
            }
        } else {
            if(is_array($Screen)){
                $t_fields = $Screen;
            } else {
                $t_fields = $Screen->Fields;
            }
        }
        
        //handle sub-fields - we "flatten" the subfield hierarchy
        $saveFields = array();
        foreach($t_fields as $sfName => $sf){
            $saveFields = array_merge($saveFields, $sf->getRecursiveFields());
        }

        foreach($saveFields as $sfName => $sf){
//      foreach($Screen->Fields as $sfName => $sf){

            //only insert editable screen fields (no ViewFields etc)
            if ($sf->isEditable()){
                //look up corresponding ModuleField based on screen field name
                $mf = $this->ModuleFields[$sfName];

                switch (strtolower(get_class($mf))){
                case 'tablefield':
                    $insert = false;
                    //exclude any auto_increment field
                    if (false === strpos($mf->dbFlags, "auto_increment")){
                        $insert = true;
                    }
                    if ($insert){
                        if('var' == $format){

                            //add the field/value pair
                            $UpdateFields[$sfName] = "dbQuote(\$data['{$sfName}'], '{$mf->dataType}')";
                            /*
                            if ("date" == $mf->dataType){
                                $UpdateFields[$sfName] = "DateToISO(\$data['".$sfName."'])";
                            } elseif ("bool" == $mf->dataType) {
                                $UpdateFields[$sfName] = "ChkFormat(\$data['".$sfName."'])";
                            } else {
                                $UpdateFields[$sfName] = "dbQuote(\$data['".$sfName."'])";
                            }*/
                        } elseif('replace' == $format){
                            //using replace-style formatting, the dbQuote,
                            //ChkFormat and and DateToISO must be called
                            //outside this function.
                            $UpdateFields[$sfName] = "'[*$sfName*]'"; //this is a tag to be replaced before the prepared SQL is executed
                        }
                    }
                    break;
                case 'remotefield';
                    //this should save to the remote table - must generate separate SQL statement
                    //(ignore RemoteFields here)
                    print "Note: (Screen {$Screen->name}, Field $sfName, Table $tableName)\n";
                    print "         RemoteField used. Will need a separate SQL statement to save data for this field. \n";
                    break;
                case 'foreignfield':
                case 'codefield':
                case 'dynamicforeignfield':
                case 'calculatedfield':
                    //die("Cannot use a foreign field in INSERT statement ($sfName)\n");
                    print "AA warning: (Screen {$Screen->name}, Field $sfName, Table $tableName)\n";
                    print "         Non-saving field type used in form - UPDATE statement will not save data for this field. \n";
                    print "         This may be OK if this field is used for combo box filtering only.\n";
                    break;

                default:
                    die("Unknown field type in UPDATE statement (Screen {$Screen->name}, Field $sfName, Table $tableName)\n");
                    break;
                }
            }
        }

        //add log data:
        if('var' == $format){
            $UpdateFields["_ModDate"] = "\"NOW()\"";
            $UpdateFields["_ModBy"] = "\$User->PersonID";
        } elseif('replace' == $format){
            $UpdateFields["_ModDate"] = "NOW()";
            $UpdateFields["_ModBy"] = "[**UserID**]";
        }


        //check for one or more PK's
        if (count($this->PKFields) > 1){

            if('var' == $format){
                //note: I'm not sure if this is useful - will
                //probably redo this case

                //if more than one PK field, append # to $recordID
                foreach($this->PKFields as $key=>$PKField){
                    $Conditions[] = $PKField."=\$recordID$key";
                }
            } else {
                //note: it is assumed that the first PK field
                //is the same as the parent row ID
                //and the second is the current module rowID
                $Conditions[] = $this->PKFields[0]."=/**PR-ID**/";
                $Conditions[] = $this->PKFields[1]."=/**RecordID**/";
            }
        } else {
            if('var' == $format){
                $Conditions[] = $this->PKFields[0]."='\$recordID'";
            } else {
                $Conditions[] = $this->PKFields[0]."='/**RecordID**/'";
            }
        }
        if(isset($this->conditions) && count($this->conditions) > 0){
            foreach($this->conditions as $condField => $condValue){
                $Conditions[] = "`{$this->ModuleID}`.$condField = '{$condValue}'";
            }
        }


        if (count($UpdateFields) > 0){
            $FormatFields = array();

            //format SQL Statement
            $SQL = "UPDATE `$tableName`\nSET\n   ";
            if('var' == $format){
                foreach($UpdateFields as $field => $value){
                    $FormatFields[] = "$field = \".$value.\"";
                }
            } else {
                foreach($UpdateFields as $field => $value){
                    $FormatFields[] = "$field = $value";
                }

            }
            $SQL .= implode(",\n    ", $FormatFields);

            $SQL .= "\nWHERE\n   ";

            //add row identifying conditions
            $SQL .= implode("\n   AND ", $Conditions);

            $SQL .= ""; //NO ENDING NEWLINE - that will cause error messages...
        } else {
            $SQL = "";
        }
        return $SQL;
    }

    //generates the SQL statement used to delete a record
    function generateDeleteSQL(&$Screen, $format = 'var')
    {
        global $SQLBaseModuleID;
        $SQLBaseModuleID = $this->ModuleID;
    
        //some sanity checking for the format parameter
        if ($format != 'var' && $format != 'replace'){
            if (empty($format)) {
                $format = 'var';
            } else {
                die("ERROR: Parameter \$format in function generateDeleteSQL expects either 'var' or 'replace'.\n");
            }
        }

        //check for one or more PK's
        if (count($this->PKFields) > 1){
            if('var' == $format){
                //note: I'm not sure if this is useful - will
                //probably redo this case

                //if more than one PK field, append # to $recordID
                foreach($this->PKFields as $key=>$PKField){
                    $Conditions[] = $PKField."=\$recordID$key";
                }
            } else {
                //note: it is assumed that the first PK field
                //is the same as the parent row ID
                //and the second is the current module rowID
                $Conditions[] = $this->PKFields[0]."=/**PR-ID**/";
                $Conditions[] = $this->PKFields[1]."=/**RecordID**/";
            }
        } else {
            if('var' == $format){
                $Conditions[] = $this->PKFields[0]."=\$recordID";
            } else {
                $Conditions[] = $this->PKFields[0]."=/**RecordID**/";
            }
        }

        $tableName = $this->ModuleID;

        //format SQL Statement (we use Update because we simply set _Deleted to true)
        $SQL = "UPDATE `$tableName`\n   SET\n      ";
        $SQL .= "_Deleted = 1,\n";
        if('var' == $format){
            $SQL .= "_ModBy = \".\$User->PersonID.\",\n";
            $SQL .= "_ModDate = NOW()\n";
        } else {
            $SQL .= "_ModBy = [**UserID**],\n";
            $SQL .= "_ModDate = NOW()\n";
        }

        $SQL .= "\nWHERE\n   ";

        //add row identifying conditions
        $SQL .= implode("\n   AND ", $Conditions);

        $SQL .= ""; //NO ENDING NEWLINE - that will cause error messages...

        return $SQL;
    }

    
//generates the SQL statement used to delete a record
    function generateDeleteLogSQL(&$Screen, $format = 'var')
    {

        $tableName = $this->ModuleID."_l";

        //look up the regular auto_increment field and include it
        //$FirstField = &reset($this->ModuleFields);
        $FirstField = reset($this->ModuleFields);

        $InsertFields[] = '_Deleted';
        $InsertValues[] = '"1"';
        
        $InsertFields[] = $FirstField->name;
        if('var' == $format){
            $InsertValues[] = "\$recordID";
        } else {
            $InsertValues[] = "/**RecordID**/";
        }
    
        
        //add log data:
        $InsertFields[] = "_ModDate";

        $InsertFields[] = "_ModBy";
        if('var' == $format){
            $InsertValues[] = "\"NOW()\"";
            $InsertValues[] = "\$User->PersonID";
        } elseif('replace' == $format){
            $InsertValues[] = "NOW()";
            $InsertValues[] = "[**UserID**]";
        }


        if (count($InsertFields) > 0){
            //format SQL Statement
            if('var' == $format){
                $SQL = "INSERT INTO `$tableName` (\n   ";
                $SQL .= implode(",\n   ", $InsertFields);
                $SQL .= "\n) VALUES (\n   \".";
                $SQL .= implode(".\",\n   \".", $InsertValues);
                $SQL .= ".\")"; //NO ENDING NEWLINE - that will cause error messages...
            } elseif('replace' == $format){
                $SQL = "INSERT INTO $tableName (\n   ";
                $SQL .= implode(",\n   ", $InsertFields);
                $SQL .= "\n) VALUES (\n   ";
                $SQL .= implode(",\n   ", $InsertValues);
                $SQL .= ")"; //NO ENDING NEWLINE - that will cause error messages...
            }
        } else {
            $SQL = "";
        }
        
        return $SQL;
    }


    //**************************************//
    //       create screen functions        //
    //**************************************//
    function generateTabs($pScreenName, $pRestriction = ''){
        if(0 == count($this->getScreens())){
            return '';
        }

        $content = '';
        $tabs = array();
        $Screens =& $this->getScreens();

        if ("List" == $pScreenName){
            if(empty($this->addNewName)){
                $addNewName = $this->SingularRecordName;
            } else {
                $addNewName = $this->addNewName;
            }
			$content = '';
            if($this->AllowAddRecord){
                if('view' == $pRestriction){
                    //$content  = "\$tabs['New'] = array(\"\", gettext(\"No Add New|You cannot add a new {$addNewName} because you don't have permission\"), 'disabled');";
                } else {
                    //get first edit screen and insert a tab link to it as "new"
                    foreach ($Screens as $Screen){
                        if ('editscreen' == strtolower(get_class($Screen))){
							$content  = "\$tabs['New'] = array(\"edit.php?mdl={$this->ModuleID}&amp;scr={$Screen->name}\", gettext(\"Add New|Add a new \").gettext(\"{$addNewName}\"));";	
                            break; //exits loop
                        }
                    }
                }
            } else {
               // $content  = "\$tabs['New'] = array(\"\", gettext(\"No Add New|To add a new {$addNewName} you must go to a parent module\"), 'disabled');";
            }
                        

            //get search screen and insert a tab link to it
            if(count($Screens)){
                foreach ($Screens as $Screen){
                    if ('searchscreen' == strtolower(get_class($Screen))){
                        $content  .= "\$tabs['Search'] = array(\"search.php?mdl={$this->ModuleID}\", gettext(\"Search|Search in \").gettext(\"{$this->PluralRecordName}\"));";
                        //$content  .= "\$tabs['Reports'] = array(\"reports.php?mdl={$this->ModuleID}\", gettext(\"Reports|Reports for \").gettext(\"{$this->PluralRecordName}\"));";
						if( true == $this->areChartsDefined() ){
							$content  .= "\$tabs['Charts'] = array(\"charts.php?mdl={$this->ModuleID}\", gettext(\"Charts|Charts for \").gettext(\"{$this->PluralRecordName}\"));";
						}
                    } 
                }
            }
            if($this->dataCollectionForm){
                $content  .= "\$tabs['DataCollection'] = array(\"dataCollectionForm.php?mdl={$this->ModuleID}\", gettext(\"Blank Form|Blank form for \").gettext(\"{$this->PluralRecordName}\"), 'download');";
            }
        } elseif ("ListCtxTabs" == $pScreenName){
		
//$tabs['List'] = Array("list.php?$qs", gettext("List|View the list of /**plural_record_name**/"));
			$content  = "\$tabs['List'] = array(\"list.php?\$qs\", gettext(\"List|View the list of \").gettext(\"{$this->PluralRecordName}\"));";
			if(empty($this->addNewName)){
                $addNewName = $this->SingularRecordName;
            } else {
                $addNewName = $this->addNewName;
            }

            if($this->AllowAddRecord){
                if('view' == $pRestriction){
                   // $content  .= "\$tabs['New'] = array(\"\", gettext(\"No Add New|You cannot add a new {$addNewName} because you don't have permission\"), 'disabled');";
                } else {
                    //get first edit screen and insert a tab link to it as "new"
                    foreach ($Screens as $Screen){
                        if ('editscreen' == strtolower(get_class($Screen))){
							$content  .= "\$tabs['New'] = array(\"edit.php?mdl={$this->ModuleID}&amp;scr={$Screen->name}\", gettext(\"Add New|Add a new \").gettext(\"{$addNewName}\"));";	

                            break; //exits loop
                        }
                    }
                }
            } else {
                //$content  = "\$tabs['New'] = array(\"\", gettext(\"No Add New|To add a new {$addNewName} you must go to a parent module\"), 'disabled');";
            }
            

            //get search screen and insert a tab link to it
            if(count($Screens)){
                foreach ($Screens as $Screen){
                    if ('searchscreen' == strtolower(get_class($Screen))){
                        $content  .= "\$tabs['Search'] = array(\"search.php?mdl={$this->ModuleID}\", gettext(\"Search|Search in \").gettext(\"{$this->PluralRecordName}\"));";
						//$content  .= "\$tabs['Reports'] = array(\"reports.php?mdl={$this->ModuleID}\", gettext(\"Reports|Reports for \").gettext(\"{$this->PluralRecordName}\"));";
						if( true == $this->areChartsDefined() ){
							$content  .= "\$tabs['Charts'] = array(\"charts.php?mdl={$this->ModuleID}\", gettext(\"Charts|Charts for \").gettext(\"{$this->PluralRecordName}\"));";
						}
					} 
                }
            }
            if($this->dataCollectionForm){
                $content  .= "\$tabs['DataCollection'] = array(\"dataCollectionForm.php?mdl={$this->ModuleID}\", gettext(\"Blank Form|Blank form for \").gettext(\"{$this->PluralRecordName}\"), 'download');";
            }
		}elseif( "EditScreenPermissions" == $pScreenName ){
			$content = '';
			foreach ($Screens as $Screen){			
				if( 'editscreen' == strtolower(get_class($Screen)) AND isset($Screen->EditPermission) ){
					$content  .= "\$EditScrPermission['{$Screen->name}'] = '{$Screen->EditPermission}';\n";	
				}
            }
			if( $content != '' ){
				foreach ($Screens as $Screen){			
					if( 'editscreen' == strtolower(get_class($Screen)) AND !isset($Screen->EditPermission) ){
						$content  .= "\$EditScrPermission['{$Screen->name}'] = '{$this->ModuleID}';\n";	
					}
            }
			}
		
		}elseif( "ListRecordMenu" == $pScreenName ){ 
		        
			$recordMenuCounter = 0;
				
			foreach ($Screens as $Screen){
                $linkTo = '';
                $tab = '';
				$recordMenu = '';
				$recordMenuEntries = '';
				
                switch( strtolower(get_class($Screen)) ){
                case "viewscreen":
                    $handler = "view.php";                   
                    $phrase = "View|View summary information about a record of type \").gettext(\"". $this->SingularRecordName;
                    break;
                case "editscreen":
                    if(!empty($Screen->linkToModuleID)){
                        $linkTo = $Screen->linkToModuleID;
                    }
                    $handler = "edit.php";

                    if (empty($Screen->phrase)){
                        $phrase = $Screen->name;
                    } else {
                        $phrase = $Screen->phrase;
                    }
                    break;
                case "searchscreen":
                    $handler = "search.php";
                    $phrase = "Search|Search in \").' '.gettext(\"". $this->PluralRecordName;
                    break;
                case "listscreen":
                    $handler = "list.php";
                    $phrase = "List|View the list of \").' '.gettext(\"". $this->PluralRecordName;
                    break;
                case "recordreportscreen":
                    $handler = "reports.php";
                    $phrase = $Screen->phrase;
                    break;
                case "listreportscreen":
                    $handler = "reports.php";
                    $phrase = $Screen->phrase;
                    break;
                case "anoneditscreen":
                    continue;
                    break;
                default:
                    print_r($Screens);
                    die("unknown screen type: '".get_class($Screen)."'\n");
                }

				$recordMenu = '$recordMenuEntries['.$Screen->name.']='."'{ text: \"'.strip_tags( ShortPhrase( gettext(\"{$phrase}\") ) ).'\" }';\n";

                $tabConditionModuleID = '';
                if(!empty($Screen->tabConditionModuleID)){
                    $tabConditionModuleID = ", '{$Screen->tabConditionModuleID}'";
                }

				if( ( "view" == $pRestriction && "viewscreen" == strtolower(get_class($Screen)) ) 
				 || ("view" != $pRestriction) ){
					if($linkTo == ''){						
						$recordMenuURL = '$recordMenuURL['.$Screen->name.']= '."\"$handler?scr={$Screen->name}&mdl={$this->ModuleID}\";\n"; 
					} else {
						$recordMenuURL = '$recordMenuURL['.$Screen->name.']= '."\"$handler?scr={$Screen->name}&mdl=$linkTo\";\n"; 
					}
				}
				
							
                if( in_array(strtolower(get_class($Screen)), array('viewscreen', 'editscreen', 'recordreportscreen')) ){                    
					$recordMenuList .= $recordMenu;
					$recordMenuURLList .= $recordMenuURL;					
                }
            }			
			
			$content = $recordMenuList.$recordMenuURLList;
	
		}else {
            print "m. GenerateTabs: current screen $pScreenName\n";

            $currentScreen = $this->getScreen($pScreenName);

            foreach ($Screens as $Screen){
                $linkTo = '';
                $tab = '';
                switch(strtolower(get_class($Screen))){
                case "viewscreen":
                    $handler = "view.php";
                   /*  if(in_array($this->SingularRecordName[0], array('a','e','i','o','h','y','A','E','I','O','H','Y'))){
                        $a = 'an';
                    } else {
                        $a = 'a';
                    } */
                    $phrase = "View|View summary information about a record of type \").gettext(\"". $this->SingularRecordName;
                    break;
                case "editscreen":
                    if(!empty($Screen->linkToModuleID)){
                        $linkTo = $Screen->linkToModuleID;
                    }
                    $handler = "edit.php";

                    if (empty($Screen->phrase)){
                        $phrase = $Screen->name;
                    } else {
                        $phrase = $Screen->phrase;
                    }
                    break;
                case "searchscreen":
                    $handler = "search.php";
                    $phrase = "Search|Search in \").' '.gettext(\"". $this->PluralRecordName;
                    break;
                case "listscreen":
                    $handler = "list.php";
                    $phrase = "List|View the list of \").' '.gettext(\"". $this->PluralRecordName;
                    break;
                case "recordreportscreen":
                    $handler = "reports.php";
                    $phrase = $Screen->phrase;
                    break;
                case "listreportscreen":
                    $handler = "reports.php";
                    $phrase = $Screen->phrase;
                    break;
                case "anoneditscreen":
                    continue;
                    break;
                default:
                    print_r($Screens);
                    die("unknown screen type: '".get_class($Screen)."'\n");
                }

                $tabConditionModuleID = '';
                if(!empty($Screen->tabConditionModuleID)){
                    $tabConditionModuleID = ", '{$Screen->tabConditionModuleID}'";
                }

                if ($pScreenName != $Screen->name){
                    if ( ( "view" == $pRestriction 
					 && ( "viewscreen" == strtolower( get_class($Screen) )  
					 || "recordreportscreen" == strtolower( get_class($Screen) ) ) )  
					 || ("view" != $pRestriction) ){

                        //insert link
                        if($linkTo == ''){
                            $tab = "      \$tabs['{$Screen->name}'] = array( \"$handler?scr={$Screen->name}&amp;\$tabsQS\", gettext(\"{$phrase}\") $tabConditionModuleID);\n";
                        } else {
                            $tab = "      \$tabs['{$Screen->name}'] = array( \"$handler?mdl=$linkTo&amp;rid=\$recordID\", gettext(\"{$phrase}\") $tabConditionModuleID);\n";
                        }
                    }
                } else {
                    //Current screen: insert name only
                    $tab = "      \$tabs['{$Screen->name}'] = array( \"\", gettext(\"{$phrase}\") $tabConditionModuleID);\n";
                }

                if(in_array(strtolower(get_class($Screen)), array('viewscreen', 'editscreen', 'recordreportscreen'))){
                    $content .= $tab;
                }
            }
        }

        return $content;
    }


function getDataCollectionForms()
{
    //get all edit screens
    $dataForms = array();
    foreach($this->getScreens() as $screenName => $screen){
        if('editscreen' == strtolower(get_class($screen))){
            //remove unnecessary properties?

            //filter out screens without editable fields (we might support screens with grids later)
            $fields = $screen->Fields;
            $dataFields = array();

            foreach($fields as $fieldName => $field){
                //determine which screen fields to show
                if($field->isEditable()){
                    $moduleField = $this->ModuleFields[$fieldName];
                    switch(strtolower(get_class($moduleField))){
                    case 'tablefield':
                    case 'remotefield':

                        break;
                    default:
                        //non-saving field
                        $field->nonSaving = true;
                        break;
                    }
                    $field->phrase = $moduleField->phrase;
                    $dataFields[$fieldName] = $field;
                }
				if(count($field->Fields) > 0){
                    foreach($field->Fields as $subFieldName => $subField){

                        if($subField->isEditable()){
                            $moduleField = $this->ModuleFields[$subFieldName];
                            switch(strtolower(get_class($moduleField))){
                            case 'tablefield':
                            case 'remotefield':

                                break;
                            default:
                                //non-saving field
                                $subField->nonSaving = true;
                                break;
                            }
                            $subField->phrase = $moduleField->phrase;
                            $dataFields[$subFieldName] = $subField;
                        }
                    }
                }
            }
            if(count($dataFields) > 0){
                $dataForms[$screenName]['phrase'] = $screen->phrase;
                $dataForms[$screenName]['fields'] = $dataFields;
            }
            if(count($screen->Grids) > 0){
                $grids = $screen->Grids;
                foreach($grids as $gridName => $grid){
                    if('editgrid' == strtolower(get_class($grid)) && $grid->dataCollectionForm){
                        $subModule =& $this->SubModules[$grid->moduleID];
                        $gridFields = array();

                        foreach($grid->FormFields as $fieldName => $field){
                            //determine which screen fields to show
                            if($field->isEditable()){
                                $moduleField = $subModule->ModuleFields[$fieldName];
                                switch(strtolower(get_class($moduleField))){
                                case 'tablefield':
                                case 'remotefield':

                                    break;
                                default:
                                    //non-saving field
                                    $field->nonSaving = true;
                                    break;
                                }
                                $field->phrase = $moduleField->phrase;
                                $gridFields[$fieldName] = $field;
                            }
                        }

                        $dataForms[$screenName]['moduleName'] = $subModule->Name;
                        $dataForms[$screenName]['phrase'] = $screen->phrase;
                        $dataForms[$screenName]['sub'][$grid->moduleID] = array(
                            $grid->phrase,
                            $gridFields
                            );

                    }
                }
            }
        }
    }
    return $dataForms;
} //end getDataCollectionForms()


    function generateExport(){
        require_once CLASSES_PATH . '/data_handler.class.php';
        require_once CLASSES_PATH . '/report.class.php';
        require_once CLASSES_PATH . '/module_map.class.php';
        $exportFields = array();

        foreach($this->ModuleFields as $fieldName => $moduleField){
            switch(strtolower(get_class($moduleField))){
            case 'tablefield':
            case 'remotefield':
                $exportFields[$fieldName] = $fieldName;
                break;
            default:
                break;
            }
        }

        unset($exportFields['_ModDate']);
        unset($exportFields['_ModBy']);
        unset($exportFields['_Deleted']);
        unset($exportFields['_TransactionID']);

        $subElements = array();
        foreach($exportFields as $exportField){
            $subElements[] =  new Element($exportField, 'ReportField', array('name' => $exportField));
        }

        if(count($this->SubModules) > 0){
            foreach($this->SubModules as $subModuleID => $subModule){
                if('submodule' == strtolower(get_class($subModule))){
                    if(!empty($subModule->parentKey)){
                        //creates submodule report element
                        $submodule_element = $subModule->makeExportSubReportElement();
                        if(!$submodule_element){
                            print "Skipped XML export for submodule $subModuleID\n";
                        } else {
                            $subElements[] = $submodule_element;
                        }
                    }
                }
            }
        }

        $report_element = new Element(
            'XmlExport',
            'Report',
            array(
                'moduleID' => $this->ModuleID,
                'title' => 'XML Export'
            ),
            $subElements);

        $report = $report_element->createObject($this->ModuleID);

        return array('/**exportReport**/' => escapeSerialize($report));
    }


function getNextScreen($pScreenName)
{
    //look up the current screen
    $ScreenNames = array_keys($this->getScreens());
    $current_key = array_search($pScreenName, $ScreenNames);
    if(false !== $current_key){
        $next_key = $current_key + 1;
        if(isset($ScreenNames[$next_key])){
            return $ScreenNames[$next_key];
        }
    }
    return '';
}


function getFirstEditScreen()
{
    $FirstEditScreenName = '';

    $screens_element = $this->_map->selectFirstElement('Screens', NULL, NULL);
    if(!empty($screens_element) && count($screens_element->c) > 0){
        foreach($screens_element->c as $screen_element){
            if('EditScreen' == $screen_element->type){
                $FirstEditScreenName = $screen_element->name;
                break;
            }
        }
    }
    return $FirstEditScreenName;
}




function _generateSerializedFieldPhrases($fields)
{
    $phrases = array();
    $content = "array(\n";
    foreach($fields as $FieldName=>$Field){

        $t_mf = $this->ModuleFields[$FieldName];

        if (!empty($t_mf->phrase)){
            $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
        } else {
            $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
        }
    }
    $content .= join(",\n", $phrases);
    $content .= "\n   );\n";
    return $content;
}


function generateViewScreenSections()
{
    trace( "m.generateViewScreenSections: begin\n");

    $screen =& $this->viewScreen;
    if(empty($screen)){
        return null;
    }

    $sections = array();
    $sections[0]['phrase'] = $this->SingularRecordName;

    if(count($screen->Fields) > 0){
        $serPhrases = "\$phrases[0] = ";
        $serPhrases .= $this->_generateSerializedFieldPhrases($screen->Fields);
        $sections[0]['sql'] = $this->generateGetSQL('View');
        $sections[0]['fields'] = $screen->Fields;
    }
    if(count($screen->Grids) > 0){
        foreach($screen->Grids as $gridID => $grid){
            $sections[0]['grids'][$gridID] = $grid;
        }
    }
    $sectionID = 0;
    if(isset($screen->sections) && count($screen->sections) > 0){
        foreach($screen->sections as $section){
            $sectionID++;
            if(!empty($section->phrase)){
                $sections[$sectionID]['phrase'] = $section->phrase;
            }
            if(count($section->Fields) > 0){
                $serPhrases .= "\$phrases[$sectionID] = ";
                $serPhrases .= $this->_generateSerializedFieldPhrases($section->Fields);
                $sections[$sectionID]['sql'] = $this->generateGetSQL('View', $section);
                $sections[$sectionID]['fields'] = $section->Fields;
            }
            if(count($section->Grids) > 0){
                foreach($section->Grids as $gridID => $grid){
                    $subModule = GetModule($grid->moduleID);
                    //$grid->listSQL = $subModule->generateListSQL($grid); //this is done by the grid constructor
                    $sections[$sectionID]['grids'][$gridID] = $grid;
                }
            }
        }
    }

    $output['/**PHRASES**/'] = $serPhrases;
    $output['/**SECTIONS**/'] = "\$sections = unserialize('" . escapeSerialize($sections) . "');";
    return $output;
}

function BuildViewScreen()
{
    trace( "m.BuildViewScreen: begin ({$this->ModuleID})");

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->ModuleID;

    $output['/**module_name**/'] = $this->Name;
    $output['/**singular_record_name**/'] = $this->SingularRecordName;
    $output['/**plural_record_name**/'] = $this->PluralRecordName;

    $Screen =& $this->viewScreen;

    if($this->useBestPractices){
        $output['/**useBestPractices**/'] = '$useBestPractices = true;';

        $field_object = MakeObject(
            $this->ModuleID,
            'IsBestPractice',
            'InvisibleField',
            array(
                'name' => 'IsBestPractice'
            )
        );
        $Screen->Fields['IsBestPractice'] = $field_object;
    }

    if(!isset($Screen->Fields['_ModDate'])){
        $field_object = MakeObject(
            $this->ModuleID,
            '_ModDate',
            'InvisibleField',
            array(
                'name' => '_ModDate'
            )
        );
        $Screen->Fields['_ModDate'] = $field_object;
    }

    //fields
    trace( "Generating fields:");
    $content = "\$fields = unserialize('";
    $content .=
        str_replace("'", "\\'",
            str_replace("\\", "\\\\",
                serialize($Screen->Fields)
            )
        );
    $content .= "');\n";
    $output['/**fields**/'] = $content;

    $output['/**screen_phrase**/'] = $Screen->phrase;

    if(!$this->includeGlobalModules){
        $output['/**disbleGlobalModules**/'] = '$disableGlobalModules = true;';
    }

    //phrases array
    trace("Building phrases:");
    $phrases = array();
    $content = "\$phrases = array(\n";
    foreach($Screen->Fields as $FieldName=>$Field){

        $t_mf = $this->ModuleFields[$FieldName];
        if (!empty($t_mf->phrase)){
            $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
        } else {
            $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
        }
    }
    $content .= join(",\n", $phrases);
    $content .= "\n   );\n";

    $output['/**phrases**/'] = $content;

    //ownerField filter
    trace("m. getting owner field");
    if($this->OwnerField) {
        $content = $this->OwnerField;
    } else {
        $content = '';
    }
    $output['/**ownerField**/'] = "\$ownerField = '". $content . "';\n";

    trace("m. generating get statement");
    $output['/**SQL|GET**/'] = $this->generateGetSQL('View');

    $output['/**tabs|EDIT**/'] = $this->generateTabs('View');
    $output['/**tabs|VIEW**/'] = $this->generateTabs('View', "view");

    $output['/**nextScreen**/'] = $this->getNextScreen('View');

    if(empty($this->recordLabelField)){
        $output['/**RecordLabelField**/'] = "\$recordLabelField = 'Record ' . \$recordID;";
    } else {
        $output['/**RecordLabelField**/'] = "\$recordLabelField = \$data['{$this->recordLabelField}'];";
    }

    //grids
    $i = 1;
    $grids = "";
    foreach($Screen->Grids as $Grid){
        if (is_object($Grid)){
            $t_subModule =& $this->SubModules[$Grid->moduleID];

            $Grid->number = $i;

            $grids .= "   \$Grid$i = unserialize('";
            $grids .=
                str_replace("'", "\\'",
                    str_replace("\\", "\\\\",
                        serialize($Grid)
                    )
                );
            $grids .= "');\n";
            $grids .= "   \$content .= \$Grid{$i}->render('view.php', \$qsArgs);\n";

            unset($t_subModule);
        }
        $i++;
    }

    $output['/**VIEWGRIDS**/'] = $grids;

    if(isset($Screen->sections) && count($Screen->sections) > 0){
        //for each ViewScreenSection, display it...
        //$content = "include(INCLUDE_PATH.'/viewscreensection.php');\n";
        $content = ''; //line above has been transferred to view.php - needed for global grids...
        foreach($Screen->sections as $section){
            $content .= $this->BuildViewScreenSection($section);
        }
        $output['/**VIEWSCREENSECTIONS**/'] = $content;
    }
    //see if there are any CustomCode elements
    if(count($Screen->customCodes) > 0){
        foreach($Screen->customCodes as $location => $customCode){
            $output[$location] = $customCode->getContent();
        }
    }

    trace( "m.BuildViewScreen: end ({$this->ModuleID})");
    return $output;
}


function BuildViewScreenSection($section){

    if(!empty($section->phrase)){
	#<a href="#top" title="'.gettext("go top").'"><img src="'.$theme_web.'/img/page_top.png" alt="'.gettext("go top").'"/></a>&nbsp;&nbsp;
        $content = "\$content .= \$siteNavigationSnip.'<h1 id=\"'.\"$section->name\".'\"><span class=\"h1phr\">'. gettext(\"{$section->phrase}\") .'</span></h1>';\n";
    }else{
		$content = "\$content .= '<h1 class=\"h1nphr\" id=\"'.\"$section->name\".'\"></h1>';\n";
	}

    if(count($section->Fields) > 0){
        $content .= "\$fields = unserialize('";
        $content .=
            str_replace("'", "\\'",
                str_replace("\\", "\\\\",
                    serialize($section->Fields)
                )
            );
        $content .= "');\n";

        $content .= "\$phrases = array(\n";
        $phrases = array();
        foreach($section->Fields as $FieldName=>$Field){
            $t_mf = $this->ModuleFields[$FieldName];
            if (!empty($t_mf->phrase)){
                $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
            } else {
                $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
            }
        }
        $content .= join(",\n", $phrases);
        $content .= "\n   );\n";

        //get data
        $SQL = $this->generateGetSQL('View', $section);
        $content .= "\$SQL = \"$SQL\";\n";
    } else {
        $content .= "\$fields = '';\n";
        $content .= "\$phrases = array();\n";
        $content .= "\$SQL = '';\n";
    }

    $i = 1;
    $grids = "   \$grids = array();\n";

    foreach($section->Grids as $Grid){
        if (is_object($Grid)){

            if(!in_array($Grid->moduleID, array_keys($this->SubModules))){
                die("Grid {$Grid->moduleID} has no corresponding SubModule.");
            }
            $t_subModule = $this->SubModules[$Grid->moduleID];

            $Grid->number = $i;

            $grids .= "   \$grids[$i] = unserialize('";
            $grids .=
                str_replace("'", "\\'",
                    str_replace("\\", "\\\\",
                        serialize($Grid)
                    )
                );
            $grids .= "');\n";

            unset($t_subModule);
        }
        $i++;
    }
    $content .= $grids;
    $content .= "\$content .= renderViewScreenSection(\$fields, \$phrases, \$SQL, \$grids);\n";
    return $content;

}


function BuildEditScreen($ScreenName){

        trace( "m.BuildEditScreen: begin ({$this->ModuleID}:{$ScreenName})");

        global $SQLBaseModuleID;
        $SQLBaseModuleID = $this->ModuleID;

        $output['/**module_name**/'] = $this->Name;
        $output['/**singular_record_name**/'] = $this->SingularRecordName;
        $output['/**plural_record_name**/'] = $this->PluralRecordName;

       		
        //fields
        trace( "Generating fields:\n");
        $Screen = $this->getScreen($ScreenName);

			
		if( $Screen->name == 'Form' AND isset( $Screen->onNewGoEditScreen ) ){
			$output['/**go_EditScreen**/'] = '$goEditScreen = "'.$Screen->onNewGoEditScreen.'";';
		}else{
			$output['/**go_EditScreen**/'] = '$goEditScreen = null;';
		}
		
		if( isset( $Screen->onOkGoListScreen ) ){
			$output['/**go_ListScreen**/'] = '$goListScreen = true;';
		}else{
			$output['/**go_ListScreen**/'] = '$goListScreen = false;';
		}
		
		if( isset( $Screen->onOkGoViewScreen ) ){
			$output['/**go_ViewScreen**/'] = '$goViewScreen = true;';
		}else{
			$output['/**go_ViewScreen**/'] = '$goViewScreen = false;';
		}
		
		if( isset( $Screen->onOkGoEditScreen ) ){
			$output['/**go_EditScreen1**/'] = '$goEditScreen1 = "'.$Screen->onOkGoEditScreen.'";';
		}else{
			$output['/**go_EditScreen1**/'] = '$goEditScreen1 = null;';
		}

        $content = "\$fields = unserialize('";
        $content .=
            str_replace("'", "\\'",
                str_replace("\\", "\\\\",
                    serialize($Screen->Fields)
                )
            );
        $content .= "');\n";
        $output['/**fields**/'] = $content;

        $skipSaveFields = array();
        foreach($Screen->Fields as $screenFieldName => $screenField){
            if(!$screenField->isEditable()){
                $skipSaveFields[] = $screenFieldName;
            }
        }
        $output['/**skipSaveFields**/'] = '$skipSaveFields = unserialize(\''.escapeSerialize($skipSaveFields) .'\');'."\n";

        $output['/**screen_phrase**/'] = $Screen->phrase;

        if(!$this->includeGlobalModules){
            $output['/**disbleGlobalModules**/'] = '$disableGlobalModules = true;';
        }

        $output['/**PKField**/'] = "\$PKField = '".end($this->PKFields)."';\n";

        //list of date fields (needed to insert calendar code)
        $dateFields = array();

        $hasEditableFields = 'false';

        //build list of DateFields
        foreach($Screen->Fields as $fieldKey => $field){
            print "Module->BuildEditScreen: screen field $fieldKey\n";

            if ("datefield" == strtolower(get_class($field))){
                $dateFields[] = $field->name;
            }
            if($field->isEditable()){
                $hasEditableFields = 'true';
            }
        }
        $output['/**hasEditableFields**/'] = "\$hasEditableFields = {$hasEditableFields};\n";

        //generate content for each date field
        if( count($dateFields) > 0 ){
            $content = "\$content .= \"\n<script type=\\\"text/javascript\\\">\n";
            foreach($dateFields as $fieldName){
                $content .= "Calendar.setup({\n";
                $content .= "   inputField : \\\"$fieldName\\\",\n";
                if('datetime' == $this->ModuleFields[$fieldName]->getDataType()){
                    $content .= "\".\$User->getCalFormat(true).\"\n";
                    $content .= "   showsTime   : true,\n";
                } else {
                    $content .= "\".\$User->getCalFormat(false).\"\n";
                }
                $content .= "   onUpdate    : indicateUnsavedDateChanges,\n";
                $content .= "   button      : \\\"cal_$fieldName\\\"\n";
                $content .= "});\n";
            }
            $content .= "</script>\\n\";";

            $output['/**dateFields**/'] = $content;
        }

        //list of remote fields:
        $remoteFields = array();

        //build list of RemoteFields
        //only add RemoteFields that are on THIS SCREEN
        foreach( $Screen->Fields as $FieldName=>$Field ){

            if(!in_array($FieldName, array_keys($this->ModuleFields))){
                //print_r($this->ModuleFields);
                print_r($Screen->Fields);
                die("Field {$FieldName} in edit screen {$ScreenName} has no corresponding ModuleField");
            }

            $t_mf = $this->ModuleFields[$FieldName];
            //print_r ($t_mf);
            if ('remotefield' == strtolower(get_class($t_mf))){
                $remoteFields[$FieldName] = $t_mf;
            } 
        }

        //generate content for each RemoteField
        if (count($remoteFields) > 0){

            $content = "\$remoteFields = unserialize('";
            $content .=
                str_replace("'", "\\'",
                    str_replace("\\", "\\\\",
                        serialize($remoteFields)
                    )
                );
            $content .= "');\n";

            $output['/**REMOTEFIELDS_ARRAY**/'] = $content;
        } else {
            $output['/**REMOTEFIELDS_BEGIN**/'] = '/**-remove_begin-**/'; //"\nif(0){\n   //this section is commented out because\n   //there are no remote fields\n";
            $output['/**REMOTEFIELDS_END**/'] = '/**-remove_end-**/'; //"}\n";
        }


        //see if there are any CustomCode elements
        if(count($Screen->customCodes) > 0){
            foreach($Screen->customCodes as $location => $customCode){
                $output[$location] = $customCode->getContent();
                indent_print_r($customCode, $location);
            }
        }

        //THIS SHOULD BE REMOVED WHEN XML CHANGES ARE DONE
        //checks for resource grids - and hides them
        foreach($Screen->Grids as $id => $Grid){
            if('res' == $Grid->moduleID){
                unset($Screen->Grids[$id]);
            }
        }

        //define grids
        $i = 1;
        $grids = "   \$grids = array();\n";
        foreach($Screen->Grids as $Grid){
            if (is_object($Grid)) {
                $t_subModule =& $this->SubModules[$Grid->moduleID];
                if(!isset($Grid->isGuidance) || !$Grid->isGuidance){
                    $Grid->number = $i;
                    $grids .= "   \$grids[$i] = unserialize('";
                    $grids .=
                        str_replace("'", "\\'",
                            str_replace("\\", "\\\\",
                                serialize($Grid)
                            )
                        );
                    $grids .= "');\n";
                } else {
                    $output['/**guidanceGrid**/'] = '$guidanceGrid = unserialize(\''. escapeSerialize($Grid) . "');\n";
                }

                unset($t_subModule);
            }
            $i++;
        }
        $output['/**GRIDS|DEFINE**/'] = $grids;


        //saving posted grid forms
        $i = 1;
        $grids = "";
        foreach($Screen->Grids as $Grid){
            if (is_object($Grid) && 'viewgrid' != strtolower(get_class($Grid))){

                $grids .= "   \$grids[{$i}]->handleForm();\n";
            }
            $i++;
        }

        $output['/**GRIDS|SAVE**/'] = $grids;


        //display grids
        $i = 1;
        $grids = "";
        if(count($Screen->Grids > 0)){
            $grids .= "foreach(\$grids as \$gridID => \$grid){\n";
            $grids .= "   \$content .= \$grid->render('edit.php', \$qsArgs);\n";
            $grids .= "}\n";
        }

        $output['/**GRIDS|DISPLAY**/'] = $grids;


        //phrases array
        trace( "Building phrases");
        $phrases = array();
        $content = "\$phrases = array(\n";
        foreach($Screen->Fields as $FieldName=>$Field){
            switch(strtolower(get_class($Field))){
                case 'combofield':
                case 'personcombofield':
                case 'orgcombofield':
                case 'codecombofield':
                case 'radiofield':
                case 'coderadiofield':
                    //get the list source field instead of the ID field
                    if(!empty($Field->listField)){
                        $listSourceFieldName = $Field->listField;
                    } else {
                        $listSourceFieldName = substr($FieldName, 0, -2);
                    }
                    $t_mf = $this->ModuleFields[$listSourceFieldName];
                    break;
                default:
                    $t_mf = $this->ModuleFields[$FieldName];
            }

            if (!empty($t_mf->phrase)){
			//print "   FIELD {$t_mf->name} is missing a phrase!!!\n\n";
                $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
            } else {
                $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
            }
			if( !empty($Field->Fields) ){	
				foreach( $Field->Fields as $Subfield ){
					$FieldName = $Subfield->name;					
					switch(strtolower(get_class($Subfield))){
						case 'combofield':
						case 'personcombofield':
						case 'orgcombofield':
						case 'codecombofield':
						case 'radiofield':
						case 'coderadiofield':
							//get the list source field instead of the ID field
							if(!empty($Subfield->listField)){
								$listSourceFieldName = $Subfield->listField;
							} else {
								$listSourceFieldName = substr($FieldName, 0, -2);
							}
							$t_mf = $this->ModuleFields[$listSourceFieldName];
							break;
						default:
							$t_mf = $this->ModuleFields[$FieldName];
					}

					if (!empty($t_mf->phrase)){
					//print "   FIELD {$t_mf->name} is missing a phrase!!!\n\n";
						$phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
					} else {
						$phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
					}
				}
			}
			
        }

        $content .= join(",\n", $phrases);
        $content .= "\n   );\n";
        $output['/**phrases**/'] = $content;


        //ownerField
        trace("m. getting owner field");
        if($this->OwnerField) {
            $content = $this->OwnerField;
        } else {
            $content = '';
        }
        $output['/**ownerField**/'] = "\$ownerField = '". $content . "';\n";

        //validation
		$output['/**DELETE_BY_GET**/'] = 'false';
		$output['/**VALIDATE_OWNEDBY**/'] = '';
        $content = '';
        foreach($Screen->Fields as $field){
			//if($field->isEditable() && isset($this->ModuleFields[$field->name]->validate)){
            if( $field->isEditable() ){
                $vString = $this->ModuleFields[$field->name]->validate;
                $vType = $this->ModuleFields[$field->name]->getDataType();
                //if (0 < strlen($vString)){
                $validationFormula = "\$vMsg = Validate( \$data, \$data['{$field->name}'], ShortPhrase(\$phrases['{$field->name}']), '{$vString}', '$vType');
            if(\$vMsg != ''){
                \$vMsgs .= \$vMsg;
                \$fields['{$field->name}']->invalid = TRUE;
            }\n";
                //}
				$content .= $validationFormula;
				if( $field->name == '_OwnedBy' ){
					$output['/**VALIDATE_OWNEDBY**/'] = $validationFormula;
					$output['/**DELETE_BY_GET**/'] = '$deleteByGET';
				}
			}
			
			if( !empty($field->Fields) ){	
				foreach( $field->Fields as $subfield ){
					if( $subfield->isEditable() ){
						$vString = $this->ModuleFields[$subfield->name]->validate;
						$vType = $this->ModuleFields[$subfield->name]->getDataType();
						//if (0 < strlen($vString)){
						$validationFormula = "\$vMsg = Validate( \$data, \$data['{$subfield->name}'], ShortPhrase(\$phrases['{$subfield->name}']), '{$vString}', '$vType');
					if(\$vMsg != ''){
						\$vMsgs .= \$vMsg;
						\$fields['{$subfield->name}']->invalid = TRUE;
					}\n";
						//}
						$content .= $validationFormula;
						if( $subfield->name == '_OwnedBy' ){
							$output['/**VALIDATE_OWNEDBY**/'] = $validationFormula;
							$output['/**DELETE_BY_GET**/'] = '$deleteByGET';
						}
					}
				}
			}
        }		
        $output['/**VALIDATE_FORM**/'] = $content;
//START 
		$normalize_functions = '';
		foreach($Screen->Fields as $field){
            if( $field->isEditable() ){
    		$vString = $this->ModuleFields[$field->name]->validate;
				if ( 0 < strlen( $vString ) ){
					$normalize_functions .= 
				"\n\tif( isset( \$_POST['{$field->name}'] ) ){\n\t\t\$_POST['{$field->name}'] = Normalize( \$_POST['{$field->name}'], '{$vString}' );\n\t\t}\n";
				}            
			}
			if( !empty($field->Fields) ){	
				foreach( $field->Fields as $subfield ){
					 if( $subfield->isEditable() ){
						$vString = $this->ModuleFields[$subfield->name]->validate;
						if ( 0 < strlen( $vString ) ){
							$normalize_functions .= 
						"\n\tif( isset( \$_POST['{$subfield->name}'] ) ){\n\t\t\$_POST['{$subfield->name}'] = Normalize( \$_POST['{$subfield->name}'], '{$vString}' );\n\t\t}\n";
						}            
					}
				}
			}
        }
        
		$output['/**CUSTOM_CODE|normalize**/']= $normalize_functions;
//END

        //SQL statements:
        $output['/**SQL|GET**/'] = $this->generateGetSQL($ScreenName);

        $needsReGet = false;
        foreach($Screen->Fields as $field){
            if($field->needsReGet()){
                $needsReGet = true;
            }
        }
        if(!$needsReGet){
            $output['/**RE-GET_BEGIN**/'] = '/**-remove_begin-**/'; //"\nif(0){\n";
            $output['/**RE-GET_END**/'] = '/**-remove_end-**/'; //"\n}\n";
        }

        if ($this->CheckForEditableFields($ScreenName)){

            //inserts
            $output['**SQL|INSERT**'] = $this->generateInsertSQL($Screen, false, 'var');

            //updates
            $output['**SQL|UPDATE**'] = $this->generateUpdateSQL($Screen, 'var');

            //deletes
            $output['**SQL|DELETE**'] = $this->generateDeleteSQL($Screen, 'var');
            $output['**SQL|INSERT_LOG_DELETE**'] = $this->generateDeleteLogSQL($Screen, 'var');

            $output['**SQL|INSERT_LOG**'] = $this->generateInsertSQL($Screen, true, 'var');

        } else {
            $output['/**DB_SAVE_BEGIN**/'] = '/**-remove_begin-**/'; //"\nif(0){\n//the following has been commented out\n//because this screen has no editable fields";
            $output['/**DB_SAVE_END**/'] = '/**-remove_end-**/'; //"}";
        }

        $output['/**tabs|EDIT**/'] = $this->generateTabs($ScreenName);
        if(empty($this->addNewName)){
            $addNewName = $this->SingularRecordName;
        } else {
            $addNewName = $this->addNewName;
        }
        $output['/**tabs|ADD**/'] = "      \$tabs['{$ScreenName}'] = array( \"\", gettext(\"New record\") );\n";

        //handle sub-fields - we "flatten" the subfield hierarchy
        $selectFields = array();
        foreach($Screen->Fields as $sfName => $sf){
            $selectFields = array_merge($selectFields, $sf->getRecursiveFields());
        }

        $output['/**data**/'] = "'".implode("' => '',\n         '", array_keys($selectFields))."' => ''";

        $output['/**nextScreen**/'] = $this->getNextScreen($ScreenName);

        //delete button goes on the first edit screen
        if ( $this->getFirstEditScreen() == $ScreenName AND $Screen->allowDelete ){
            $output['/**deletelink**/'] = "view.php?\$tabsQS&delete=1";
            $output['/**is_main_form**/'] = '\'is_main_form\'       => true,';
        }else{
            $output['/**deletelink**/'] = '';
        }
		

        if(empty($this->recordLabelField)){
            $output['/**RecordLabelField**/'] = "\$recordLabelField = 'Record ' . \$recordID;";
        } else {
            $output['/**RecordLabelField**/'] =
    "   \$recordLabelField = '';
    if(isset(\$data['{$this->recordLabelField}'])) {
    \$recordLabelField = \$data['{$this->recordLabelField}'];
    }";
        }

        trace( "m.BuildEditScreen: end ({$this->ModuleID}:{$ScreenName})");
        return $output;
    } //BuildEditScreen


/**
 *  Method for building the generated file for the AnonEditScreen
 */
function BuildAnonEditScreen($ScreenName){

    trace( "m.BuildAnonEditScreen: begin ({$this->ModuleID}:{$ScreenName})");

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->ModuleID;

    $output['/**module_name**/'] = $this->Name;
    $output['/**singular_record_name**/'] = $this->SingularRecordName;
    $output['/**plural_record_name**/'] = $this->PluralRecordName;

    //fields
    trace( "Generating fields:\n");
    $Screen = $this->getScreen($ScreenName);
//print_r($Screen);
    $content = "\$fields = unserialize('";
    $content .= escapeSerialize($Screen->Fields);
    $content .= "');\n";
    $output['/**fields**/'] = $content;

    $skipSaveFields = array();
    foreach($Screen->Fields as $screenFieldName => $screenField){
        if(!$screenField->isEditable()){
            $skipSaveFields[] = $screenFieldName;
        }
    }
    $output['/**skipSaveFields**/'] = '$skipSaveFields = unserialize(\''.escapeSerialize($skipSaveFields) .'\');'."\n";
    $output['/**screen_phrase**/'] = $Screen->phrase;
    $output['/**PKField**/'] = "\$PKField = '".end($this->PKFields)."';\n";

    //list of date fields (needed to insert calendar code)
    $dateFields = array();

    $hasEditableFields = 'false';

    //build list of DateFields
    foreach($Screen->Fields as $fieldKey => $field){
        print "Module->BuildAnonEditScreen: screen field $fieldKey\n";

        if ("datefield" == strtolower(get_class($field))){
            $dateFields[] = $field->name;
        }
        if($field->isEditable()){
            $hasEditableFields = 'true';
        }
    }
    $output['/**hasEditableFields**/'] = "\$hasEditableFields = {$hasEditableFields};\n";

    //generate content for each date field
    if (count($dateFields) > 0){
        $content = "\$content .= \"\n<script type=\\\"text/javascript\\\">\n";
        foreach($dateFields as $fieldName){
            $content .= "Calendar.setup({\n";
            $content .= "   inputField : \\\"$fieldName\\\",\n";
            if('datetime' == $this->ModuleFields[$fieldName]->getDataType()){
                $content .= "\".\$User->getCalFormat(true).\"\n";
                $content .= "   showsTime   : true,\n";
            } else {
                $content .= "\".\$User->getCalFormat(false).\"\n";
            }
            $content .= "   onUpdate    : indicateUnsavedDateChanges,\n";
            $content .= "   button      : \\\"cal_$fieldName\\\"\n";
            $content .= "});\n";
        }
        $content .= "</script>\\n\";";

        $output['/**dateFields**/'] = $content;
    }

    //list of remote fields:
    $remoteFields = array();

    //build list of RemoteFields
    //only add RemoteFields that are on THIS SCREEN
    foreach($Screen->Fields as $FieldName=>$Field){

        if(!in_array($FieldName, array_keys($this->ModuleFields))){
            print_r($Screen->Fields);
            die("Field {$FieldName} in edit screen {$ScreenName} has no corresponding ModuleField");
        }

        $t_mf = $this->ModuleFields[$FieldName];
        if ('remotefield' == strtolower(get_class($t_mf))){
            $remoteFields[$FieldName] = $t_mf;
        } 
    }

    //generate content for each RemoteField
    if (count($remoteFields) > 0){

        $content = "\$remoteFields = unserialize('";
        $content .=
            str_replace("'", "\\'",
                str_replace("\\", "\\\\",
                    serialize($remoteFields)
                )
            );
        $content .= "');\n";

        $output['/**REMOTEFIELDS_ARRAY**/'] = $content;
    } else {
        $output['/**REMOTEFIELDS_BEGIN**/'] = '/**-remove_begin-**/';
        $output['/**REMOTEFIELDS_END**/'] = '/**-remove_end-**/';
    }


    //see if there are any CustomCode elements
    if(count($Screen->customCodes) > 0){
        foreach($Screen->customCodes as $location => $customCode){
            $output[$location] = $customCode->getContent();
            indent_print_r($customCode, $location);
        }
    }


    //phrases array
    trace( "Building phrases");
    $phrases = array();
    $content = "\$phrases = array(\n";
    foreach($Screen->Fields as $FieldName=>$Field){
        switch(strtolower(get_class($Field))){
            case 'combofield':
            case 'personcombofield':
            case 'orgcombofield':
            case 'codecombofield':
            case 'radiofield':
            case 'coderadiofield':
                //get the list source field instead of the ID field
                if(!empty($Field->listField)){
                    $listSourceFieldName = $Field->listField;
                } else {
                    $listSourceFieldName = substr($FieldName, 0, -2);
                }
                $t_mf = $this->ModuleFields[$listSourceFieldName];
                break;
            default:
                $t_mf = $this->ModuleFields[$FieldName];
        }

        if (!empty($t_mf->phrase)){
            $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
        } else {
            $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
        }
    }

    $content .= join(",\n", $phrases);
    $content .= "\n   );\n";
    $output['/**phrases**/'] = $content;


    //ownerField
    trace("m. getting owner field");
    if($this->OwnerField) {
        $content = $this->OwnerField;
    } else {
        $content = '';
    }
    $output['/**ownerField**/'] = "\$ownerField = '". $content . "';\n";

    //validation
    $content = '';
    foreach($Screen->Fields as $field){
        if($field->isEditable() && isset($this->ModuleFields[$field->name]->validate)){
            $vString = $this->ModuleFields[$field->name]->validate;
            $vType = $this->ModuleFields[$field->name]->getDataType();
            if (0 < strlen($vString)){
                $content .= "\$vMsg = Validate(\$data['{$field->name}'], ShortPhrase(\$phrases['{$field->name}']), '{$vString}', '$vType');
        if(\$vMsg != ''){
            \$vMsgs .= \$vMsg;
            \$fields['{$field->name}']->invalid = TRUE;
        }\n";
            }
        }
    }
    $output['/**VALIDATE_FORM**/'] = $content;


    //SQL statements:
    $output['/**SQL|GET**/'] = $this->generateGetSQL($ScreenName);

    $needsReGet = false;
    foreach($Screen->Fields as $field){
        if($field->needsReGet()){
            $needsReGet = true;
        }
    }
    if(!$needsReGet){
        $output['/**RE-GET_BEGIN**/'] = '/**-remove_begin-**/';
        $output['/**RE-GET_END**/'] = '/**-remove_end-**/';
    }

    if ($this->CheckForEditableFields($ScreenName)){
        //saving and deleting is handled by the DataHandler
    } else {
        $output['/**DB_SAVE_BEGIN**/'] = '/**-remove_begin-**/';
        $output['/**DB_SAVE_END**/'] = '/**-remove_end-**/';
    }

    //handle sub-fields - we "flatten" the subfield hierarchy
    $selectFields = array();
    foreach($Screen->Fields as $sfName => $sf){
        $selectFields = array_merge($selectFields, $sf->getRecursiveFields());
    }

    $output['/**data**/'] = "'".implode("' => '',\n         '", array_keys($selectFields))."' => ''";

    //nextScreen will only be useful when anonymous entry supports multiple screens, but we can keep this for now. MJT 2008-11-21
    $output['/**nextScreen**/'] = $this->getNextScreen($ScreenName);

    trace( "m.BuildAnonEditScreen: end ({$this->ModuleID}:{$ScreenName})");
    return $output;
} //BuildAnonEditScreen


function BuildSearchScreen($ScreenName)
{
    trace( "m.BuildSearchScreen: begin ({$this->ModuleID}:{$ScreenName})");

    $output['/**module_name**/'] = $this->Name;
    $output['/**singular_record_name**/'] = $this->SingularRecordName;
    $output['/**plural_record_name**/'] = $this->PluralRecordName;

    //fields
    trace( "Generating screen fields");
    $Screen = $this->getScreen($ScreenName);
    $content = "\$fields = unserialize('";
    $content .=
        str_replace("'", "\\'",
            str_replace("\\", "\\\\",
                serialize($Screen->Fields)
            )
        );
    $content .= "');\n";
    $output['/**fields**/'] = $content;

    //module fields
    trace( "Generating module fields:");

    $moduleFields = array();
    foreach ($this->ModuleFields as $fieldname => $moduleField){
        switch(strtolower(get_class($moduleField))){
            case 'tablefield':
                break;
            case 'foreignfield':
            case 'codefield':
                $moduleField->foreignTableAlias = GetTableAlias($moduleField, $this->ModuleID);
                break;
            case 'remotefield':
                $moduleField->remoteTableAlias = GetTableAlias($moduleField, $this->ModuleID);
                //die($moduleField->remoteTableAlias);
                break;
            case 'dynamicforeignfield':
            case 'combinedfield':
            case 'calculatedfield':
            case 'summaryfield':
            case 'recordmetafield':
            case 'rangefield':
            case 'linkfield':
            case 'staticfield':
                break;
            default:
                print_r($moduleField);
                die('m. BuildSearchScreen: unknown modulefield type?');
        }

        //reduce the size by removing the phrase
        $php_version = floor(phpversion());
        if($php_version < 5){
            $copyField = $moduleField;
        } else {
            $copyField = clone( $moduleField );
        }
        unset($copyField->phrase);
        $moduleFields[$fieldname] = $copyField;
    }


    $content = "\$moduleFields = unserialize('";
    $content .=
        str_replace("'", "\\'",
            str_replace("\\", "\\\\",
                serialize($moduleFields)
            )
        );
    $content .= "');\n";
    $output['/**module_fields**/'] = $content;



    //list screen fields
    trace( "Generating list fields");
    $content = "\$listFields = unserialize('";
    $content .=
        str_replace("'", "\\'",
            str_replace("\\", "\\\\",
                serialize(array_keys($this->getListFields()))
            )
        );
    $content .= "');\n";
    $output['/**list_fields**/'] = $content;



    //loop through list fields to find some with link fields
    $linkFields = array();
    foreach ($this->getListFields() as $listField){
        if(!empty($listField->linkField)){
            $linkFields[] = "'{$listField->name}' => '{$listField->linkField}'";
        }
    }

    $content = "\$linkFields = array(\n";
    if(count($linkFields) > 0){
        $content .= join(",\n", $linkFields);
    } else {
        print "no link fields\n";
    }
    $content .= "\n      );\n";
    $output['/**linkFields**/'] = $content;



    //list of date fields (needed to insert calendar code)
    $dateFields = array();
    foreach($Screen->Fields as $field){

        if ('datefield' == strtolower(get_class($field))){
            $dateFields[] = $field->name;
        }
    }

    if (count($dateFields) > 0){
        $content = "\$content .= \"\n<script type=\\\"text/javascript\\\">\n";
        foreach($dateFields as $fieldName){
            $content .= "Calendar.setup({\n";
            $content .= "   inputField : \\\"{$fieldName}_f\\\",\n";
            //$content .= "   ifFormat   : \\\"\".\$User->getDateFormatCal().\"\\\",\n";
            $content .= "\".\$User->getCalFormat().\"\n";
            $content .= "   button     : \\\"cal_{$fieldName}_f\\\"\n";
            $content .= "});\n";

            $content .= "Calendar.setup({\n";
            $content .= "   inputField : \\\"{$fieldName}_t\\\",\n";
            //$content .= "   ifFormat   : \\\"\".\$User->getDateFormatCal().\"\\\",\n";
            $content .= "\".\$User->getCalFormat().\"\n";
            $content .= "   button     : \\\"cal_{$fieldName}_t\\\"\n";
            $content .= "});\n";

        }
        $content .= "</script>\\n\";";

        $output['/**dateFields**/'] = $content;
    }



    //phrases array
    trace( "Building phrases");

    $content = "\$phrases = array(\n";
    foreach($Screen->Fields as $FieldName=>$Field){
       
            switch(strtolower(get_class($Field))){
                case 'combofield':
                case 'personcombofield':
                case 'orgcombofield':
                case 'codecombofield':
                case 'radiofield':
                case 'coderadiofield':
                    //get the list source field instead of the ID field
                    if(!empty($Field->listField)){
                        $listSourceFieldName = $Field->listField;
                    } else {
                        $listSourceFieldName = substr($FieldName, 0, -2);
                    }
                    $t_mf = $this->ModuleFields[$listSourceFieldName];
                    break;

                default:
                    $t_mf = $this->ModuleFields[$FieldName];
            }

            if (!empty($t_mf->phrase)){
                $phrases[] = "   '$FieldName' => gettext(\"{$t_mf->phrase}\")";
            } else {
                $phrases[] = "   '$FieldName' => gettext(\"$FieldName\")";
            }        
    }

    $content .= join(",\n", $phrases);
    $content .= "\n   );\n";
    $output['/**phrases**/'] = $content;

    $output['/**screen_phrase**/'] = ShortPhrase($Screen->phrase);

    trace( "m.BuildSearchScreen: end ({$this->ModuleID}:{$ScreenName})");
    return $output;
} //BuildSearchScreen

} //end class Module



/**
 * Represents a one to many relationship to the module being generated.
 *
 * @package        gen_time
 */
class SubModule extends Module
{
var $parentModuleID;
var $parentKey; //key field of parent module
var $localKey;  //local key field
var $conditions = array();

function &Factory($element, $moduleID)
{
    $subModule = new SubModule(
        $element->getAttr('moduleID', true),
        $moduleID,
        $element->getAttr('parentKey'),
        $element->getAttr('localKey')
    );

    //look for conditions
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('SubModuleCondition' == $sub_element->type){
                $subModule->AddCondition(
                    $sub_element->getAttr('field', true),
                    $sub_element->getAttr('value')
                );
            }
        }
    }

    //maintains some metadata in the `mod` table:

    //determines whether this is a Central Sub-Module:
    $isCentralSub = 0;
    $CentralSubSnip = '';
    if(!empty($subModule->parentKey)){
        foreach($subModule->conditions as $fieldName => $fieldValue){
            if($fieldValue == $moduleID){
                $isCentralSub = 1;
                $CentralSubSnip = ', Association = 1';
            }
        }
    }

    $mdb2 =& GetMDB2();
    $SQL = "UPDATE `mod` SET SubModule = 1 {$CentralSubSnip}, _ModDate = NOW() WHERE ModuleID = '{$subModule->ModuleID}';";
    $result = $mdb2->query($SQL);
    $errcodes = mdb2ErrorCheck($result, false, false, MDB2_ERROR_NOSUCHTABLE);
    switch($errcodes['code']){
    case 0:
        break;
    case MDB2_ERROR_NOSUCHTABLE:
        trigger_error("Could not find table `mod`. Be sure to generate the 'mod' module.", E_USER_WARNING);
        break;
    default:
        mdb2ErrorCheck($result); //handles unknown errors
        break;
    }

    return $subModule;
}

function SubModule (
    $moduleID,
    $parentModuleID,
    $parentKey,
    $localKey
    )
{
    global $foreignModules;
    $foreignModules[$moduleID] = $this; //&$this;
    $this->ModuleID = $moduleID;
    $this->parentModuleID = $parentModuleID;
    $this->parentKey = $parentKey;
    $this->localKey = $localKey;

    parent::Module($moduleID);

    //converting legacy format to new
    if(!empty($conditionField)){
        $this->AddCondition($conditionField, $conditionValue);
    }
    if(!empty($condition)){
        $t_conditions = preg_split('/\s+AND\s+/', $condition);
        foreach($t_conditions as $t_condition){
            $t_parts = explode('=', $t_condition);
            $t_fieldName = trim($t_parts[0]);
            $t_pos = strpos($t_fieldName, '.');
            if(FALSE !== $t_pos){
                $t_fieldName = substr($t_fieldName, $t_pos+1);
            }
            $this->AddCondition($t_fieldName, trim($t_parts[1]));
        }
    }
    if(empty($this->parentKey) && !empty($join)){
        $t_parts = array();
        $t_parts = explode('=', $join);
        foreach($t_parts as $t_part){
            print "t_part: $t_part\n";
            $t_subparts = explode(".", $t_part);
            print_r($t_subparts);
            switch(trim($t_subparts[0])){
            case $parentModuleID:
                $this->parentKey = trim($t_subparts[1]);
                break;
            case $moduleID:
                $this->localKey = trim($t_subparts[1]);
                break;
            default:
                print "t_subpart: {$t_subparts[0]}\n";
                die("unknown syntax in $join\n");
            }
        }
    }

    trace( "SubModule constructor: $moduleID (end)");
}


function AddCondition($fieldName, $fieldValue)
{
    $this->conditions[$fieldName] = $fieldValue;
}




function makeExportSubReportElement()
{
    $exportFields = array();
    $tableFields = array();
    foreach($this->ModuleFields as $fieldName => $moduleField){
        switch(strtolower(get_class($moduleField))){
        case 'tablefield':
            $tableFields[$fieldName] = $fieldName;
            //no break!
        case 'remotefield':
            $exportFields[$fieldName] = $fieldName;
            break;
        default:
            break;
        }
    }

    unset($exportFields['_ModDate']);
    unset($exportFields['_ModBy']);
    unset($exportFields['_Deleted']);
    unset($exportFields['_TransactionID']);

    $subElements = array();
    if(count($this->conditions) > 0){
        foreach($this->conditions as $conditionField => $conditionValue){
            if(!array_key_exists($conditionField, $tableFields)){
                return false; //bailing out
            }
            $subElements[] =  new Element($conditionField, 'SubReportCondition', array('field' => $conditionField, 'value' => $conditionValue));
        }
    }

    foreach($exportFields as $exportField){
        $subElements[] =  new Element($exportField, 'ReportField', array('name' => $exportField));
    }


                            //$name, $type, $attributes
    $element = new Element(
        'Sub_'.$this->ModuleID,
        'SubReport',
        array(
            'moduleID' => $this->ModuleID,
            'title' => 'Sub_'.$this->ModuleID
        ),
        $subElements
        );

    return $element;
}

} //end SubModule class



require_once CLASSES_PATH . '/data_view.class.php';

//SubDataView class
class SubDataView {
    var $dataViewID;
    var $parentModuleID;
    var $parentKey; //key field of parent module
    var $localKey;  //local key field
    var $conditions = array();
    var $_dataView;
    var $ModuleFields; //slightly faked. This is a "shim" to make the ViewField class work with the DataView

function &Factory($element, $moduleID)
{
    $view = new SubDataView($element, $moduleID);
    return $view;
}

function SubDataView($element, $moduleID)
{

    $this->dataViewID = $element->getAttr('dataViewID');
    $this->parentModuleID = $moduleID;
    $this->parentKey = $element->getAttr('parentKey');
    $this->localKey = $element->getAttr('localKey');

    $fileName = GetXMLFilePath( $this->dataViewID .'_DataView.xml' );
    if( file_exists($fileName) ){
        include_once CLASSES_PATH . '/data_view_map.class.php';
        include_once CLASSES_PATH . '/data_view.class.php';

        $dataViewMap = new DataViewMap($fileName);
        $dataView = $dataViewMap->generateDataView();
    } else {
        trigger_error("Could not find file $fileName.", E_USER_ERROR);
    }

    $this->_dataView =& $dataView;

    //look for conditions
    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('SubDataViewCondition' == $sub_element->type){
                $this->AddCondition(
                    $sub_element->getAttr('field', true),
                    $sub_element->getAttr('value', true)
                );
            }
        }
    }

    $this->ModuleFields = $dataView->getModuleFields();

    global $foreignModules;
    $foreignModules[$this->dataViewID] =& $this;
}

function AddCondition($fieldName, $fieldValue)
{
    $this->conditions[$fieldName] = $fieldValue;
}

function generateListSQL(&$grid){
    return $this->_dataView->generateListSQL($grid);
}

function generateListCountSQL(&$grid){
    return $this->_dataView->generateListCountSQL($grid);
}

function checkTableExists($tableName)
{
    return true; //fake the table
}

function checkTableStructure($logTable = false)
{
    return null; //fake the check
}

} //end class SubDataView












//ForeignModule class
class ForeignModule extends Module
{

function Factory($element, $moduleID)
{
    $foreignModule = new ForeignModule(
        $element->getAttr('moduleID')
    );
}

function ForeignModule($moduleID)
{

    global $foreignModules;
    $foreignModules[$moduleID] = &$this;

    parent::Module($moduleID);
}
} //end ForeignModule class







//Screen classes:
class Screen
{ //abstract

var $moduleID;
var $name;
var $phrase;
var $allowEdit;
var $customCodes = array();
var $Fields = array();
var $templateFieldName;
var $genFileName;
var $tabConditionModuleID = '';

function Factory($element, $moduleID)
{
    return false; //override this method
}

function DocFactory($element, $moduleID)
{
    return new ScreenDoc($element, $moduleID);
}

function addField(&$field)
{
    $this->Fields[$field->name] = &$field;
}

function addCustomCode($customCode)
{
    $this->customCodes[$customCode->getTag()] = $customCode;
}

function build()
{
    return false; //override this method
}

/**
 *  returns true for screens that handle one record at the time
 */
function isRecordScreen()
{
    return true;
}
} //end class Screen







class ViewScreen extends Screen
{

var $Grids = array();


function &Factory(&$element, $moduleID)
{
    $screen = new ViewScreen($element, $moduleID);
    return $screen;
}


function ViewScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;
    $this->templateFieldName = "ViewModel.php";
    $this->genFileName = "{$moduleID}_View.gen";

    foreach($element->c as $field_element){

        if('Field' == substr($field_element->type, -5)){
            $this->addField($field_element->createObject($moduleID));
        } elseif('Grid' == substr($field_element->type, -4)){
            $this->addGrid($field_element->createObject($moduleID));
        } elseif('ViewScreenSection' == $field_element->type){
            $this->sections[$field_element->name] = $field_element->createObject($moduleID);
        } elseif('CustomCode' == $field_element->type){
            $this->addCustomCode($field_element->createObject($moduleID));
        } else {
            die("ViewScreen {$this->name}: element type {$field_element->type} not handled");
        }
    }
}


function addField(&$field)
{
    if ($field->isEditable()){
        print "ERROR: can't add a ";
        print get_class($field);
        print " field to a View screen.\n";
    } else {
        $this->Fields[$field->name] = &$field;
    }
}


function addGrid(&$grid)
{
    if ($grid->isEditable()){
        print "ERROR: can't add a ";
        print get_class($grid);
        print " grid to a View screen.\n";
    } else {
        $gridID = strtolower(get_class($grid)) . '_' . $grid->moduleID;
        $this->Grids[$gridID] = &$grid;
    }
}


function build()
{
    $module =& GetModule($this->moduleID);
    return $module->buildViewScreen();
}
} //end class ViewScreen








class EditScreen extends Screen
{

var $Grids = array();
var $linkToModuleID;
var $sections = array(); //ViewScreenSections


function &Factory($element, $moduleID)
{
    $screen = new EditScreen($element, $moduleID);
    return $screen;
}


function EditScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = true;
    $this->moduleID = $moduleID;
    $this->linkToModuleID = $element->getAttr('linkToModuleID');
    $this->tabConditionModuleID = $element->getAttr('tabConditionModuleID');
	$this->EditPermission = $element->getAttr('EditPermission');	
	( strtolower( $element->getAttr('allowDelete') ) == 'no' ? $this->allowDelete = false : $this->allowDelete = true );
	
	if( $this->name == 'Form' ){		
		$this->onNewGoEditScreen = $element->getAttr('onNewGoEditScreen');
	}	
	$this->onOkGoListScreen = $element->getAttr('onOkGoListScreen');
	$this->onOkGoViewScreen = $element->getAttr('onOkGoViewScreen');
	$this->onOkGoEditScreen = $element->getAttr('onOkGoEditScreen');
	
	if( $this->name == 'Form' AND strtolower($element->getAttr('cloneAsNew')) == 'no' ){		
		$this->noCloneAsNew = true;		
	}
	
	
    $this->templateFieldName = "EditModel.php";
    $this->genFileName = "{$moduleID}_Edit{$this->name}.gen";

    foreach($element->c as $field_element){
        if('GridField' == substr($field_element->type, -9)){
            die("Screen {$this->name} has a GridField {$field_element->name} which is not part of a grid.");
        }
        if('Field' == substr($field_element->type, -5)){
            $this->addField($field_element->createObject($moduleID));
        } elseif('Grid' == substr($field_element->type, -4)){
            $this->addGrid($field_element->createObject($moduleID));
        } elseif('CustomCode' == $field_element->type){
            $this->addCustomCode($field_element->createObject($moduleID));
        } elseif('RecordSummaryFieldsRef' == $field_element->type){
            print "RecordSummaryFieldsRef in $moduleID {$element->name}\n";

            //look up SummaryFields section
            $module = GetModule($moduleID);

            $summaryfields_element = $module->_map->selectFirstElement('RecordSummaryFields');
            if(!empty($summaryfields_element) && count($summaryfields_element->c) > 0){
                foreach($summaryfields_element->c as $summaryfield_element){
                    $this->addField($summaryfield_element->createObject($moduleID));
                }
            }
        } else {
            die("EditScreen {$this->name}: element type {$field_element->type} not handled");
        }
    }
}


function addGrid(&$grid)
{
    //$this->Grids[$grid->moduleID] = $grid;
    $gridID = strtolower(get_class($grid)) . '_' . $grid->moduleID;
    $this->Grids[$gridID] = &$grid;
}


function build()
{
    $module =& GetModule($this->moduleID);
    return $module->buildEditScreen($this->name);
}

} //end class EditScreen


//fake wrapper class to EditScreen
class EditScreenLink extends EditScreen
{

function &Factory(&$element, $moduleID)
{
    $screen = EditScreen::Factory($element, $moduleID);
    return $screen;
}
}


/**
 * Class for anonymous data entry forms
 *
 * Provides a new class for anonymous data entry forms to allow specific custom behavior of those forms.
 * added by ORQA - mdo 11/5/08
 */
class AnonEditScreen extends Screen
{

var $Grids = array();
var $linkToModuleID;
var $sections = array(); //ViewScreenSections


function &Factory($element, $moduleID)
{
    $screen = new AnonEditScreen($element, $moduleID);
    return $screen;
}


function AnonEditScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = true;
    $this->moduleID = $moduleID;
    $this->linkToModuleID = $element->getAttr('linkToModuleID');
    $this->tabConditionModuleID = $element->getAttr('tabConditionModuleID');

    $this->templateFieldName = "AnonEditModel.php";
    $this->genFileName = "{$moduleID}_AnonEdit{$this->name}.gen";

    foreach($element->c as $field_element){
        if('GridField' == substr($field_element->type, -9)){
            die("Screen {$this->name} has a GridField {$field_element->name} which is not part of a grid.");
        }
        if('Field' == substr($field_element->type, -5)){
            $this->addField($field_element->createObject($moduleID));
        } elseif('Grid' == substr($field_element->type, -4)){
            $this->addGrid($field_element->createObject($moduleID));
        } elseif('CustomCode' == $field_element->type){
            $this->addCustomCode($field_element->createObject($moduleID));
        } elseif('RecordSummaryFieldsRef' == $field_element->type){
            print "RecordSummaryFieldsRef in $moduleID {$element->name}\n";

            //look up SummaryFields section
            $module = GetModule($moduleID);

            $summaryfields_element = $module->_map->selectFirstElement('RecordSummaryFields');
            if(!empty($summaryfields_element) && count($summaryfields_element->c) > 0){
                foreach($summaryfields_element->c as $summaryfield_element){
                    $this->addField($summaryfield_element->createObject($moduleID));
                }
            }
        } else {
            die("AnonEditScreen {$this->name}: element type {$field_element->type} not handled");
        }
    }
}


function build()
{
    $module =& GetModule($this->moduleID);
    return $module->BuildAnonEditScreen($this->name);
}

} //end class AnonEditScreen



class SearchScreen extends Screen {
function &Factory($element, $moduleID){
    $screen = new SearchScreen($element, $moduleID);
    return $screen;
}

function SearchScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;

    $this->templateFieldName = "SearchModel.php";
    $this->genFileName = "{$moduleID}_Search.gen";

    foreach($element->c as $field_element){
        if('GridField' == substr($field_element->type, -9)){
            die("Screen {$this->name} has a GridField {$field_element->name} which is not part of a grid.");
        }
        if('Field' == substr($field_element->type, -5)){
			$newField = &$field_element->createObject($moduleID);
			if( property_exists( $newField, 'validate') ){
				$newField->validate = '';
			}
            $this->addField( $newField );
        } else {
            die("The search screen '{$this->name}' does not support {$field_element->type}");
        }
    }

}

function build()
{
    $module =& GetModule($this->moduleID);
    return $module->buildSearchScreen($this->name);
}

function isRecordScreen()
{
    return false;
}
} //end class SearchScreen


class ListScreen extends Screen {
var $orderByFields = array();

function &Factory($element, $moduleID){
    $obj = new ListScreen($element, $moduleID);
    return $obj;
}


function ListScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    if( empty($this->name) ){
        $this->name = 'List';
    }
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;
	( strtolower( $element->getAttr('allowDelete') ) == 'no' ? $this->allowDelete = false : $this->allowDelete = true );

    $this->templateFieldName = "ListModel.php";
    $this->genFileName = "{$this->moduleID}_List.gen";



    foreach($element->c as $field_element){
        if('GridField' == substr($field_element->type, -9)){
            die("Screen {$this->name} has a GridField {$field_element->name} which is not part of a grid.");
        }
        if('OrderByField' == $field_element->type){
            //add invisible field if not in the selects already
            if(!isset($this->Fields[$field_element->name])){
                //make invisiblefield element
                /*$invisibleField_element = new Element($field_element->name, 'InvisibleField', array('name' => $field_element->name));
                $field_object = $invisibleField_element->createObject($this->moduleID);
                */
                $field_object = MakeObject($this->moduleID, $field_element->name, 'InvisibleField', array('name' => $field_element->name));
                $this->AddField($field_object);
                //unset($invisibleField_element);
                unset($field_object);
            }

            //add to $this->orderByFields
           //$this->orderByFields[$field_element->name] = $field_element->getAttr('direction');
			if( $field_element->getAttr('direction') == 'desc' ){
				$this->orderByFields[$field_element->name] = true;			
			}else{
				$this->orderByFields[$field_element->name] = false;
			}

        } elseif('Field' == substr($field_element->type, -5)){
            $this->addField($field_element->createObject($moduleID));
        } elseif('CustomCode' == $field_element->type) {
            $customCode = $field_element->createObject($moduleID);
            $this->addCustomCode($customCode);
        } else {
            die("The search screen '{$this->name}' does not support {$field_element->type}");
        }
    }
    
    $module =& GetModule($moduleID);
    if($module->useBestPractices){
print "found best practices\n";
        $field_object = MakeObject(
            $this->moduleID,
            'IsBestPractice',
            'ListField', //InvisibleField
            array(
                'name' => 'IsBestPractice'
            )
        );
        $this->AddField($field_object);
    } else {
print "no best practices submodule\n";
    }

}


function build()
{
    $debug_prefix = debug_indent("ListScreen->build() {$this->moduleID}:");
    echo "$debug_prefix (begin)\n";

    $module =& GetModule($this->moduleID);

    /*module properties*/
    $output['/**module_name**/'] = $module->Name;
    $output['/**singular_record_name**/'] = $module->SingularRecordName;
    $output['/**plural_record_name**/'] = $module->PluralRecordName;

    $headers = array();
    $linkFields = array();

    /*list column headers and link fields*/
    foreach ($this->Fields as $listField){
        if(!empty($listField->phrase)){
            $headers[] = "   '{$listField->name}' => gettext(\"{$listField->phrase}\")";
        } else {
            $headers[] = "   '{$listField->name}' => ''";
        }
        if(!empty($listField->linkField)){
            $linkFields[] = "   '{$listField->name}' => \"{$listField->linkField}\"";
        }
    }

    $content = "\$headers = array(\n";
    $content .= join(",\n", $headers);
    $content .= "\n   );\n";
    $output['/**headers**/'] = $content;

    $content = "\$linkFields = array(\n";
    $content .= join(",\n", $linkFields);
    $content .= "\n   );\n";
    $output['/**linkFields**/'] = $content;

    if(true === $module->useBestPractices){
        $content = "\$useBestPractices = true;\n";
        $output['/**useBestPractices**/'] = $content;
    }


    //list screen fields
    $content = "\$listFields = unserialize('";
    $content .= escapeSerialize(array_keys($this->Fields));
    $content .= "');\n";
    $output['/**list_fields**/'] = $content;

    $pkField = end($module->PKFields);

    //field alignment
    $content = "\$fieldAlign = array(\n";
    $fieldFormats = array();
    foreach ($this->Fields as $listField){
        if($listField->isVisible()){
            $dType = $module->ModuleFields[$listField->name]->getDataType();
            $fieldTypes[] = "'{$listField->name}' => '$dType'";

            $displayFormat = $module->ModuleFields[$listField->name]->displayFormat;
            if(!empty($displayFormat)){
                $fieldFormats[] = "'{$listField->name}' => '$displayFormat'";
            }

            if($pkField == $listField->name){
                $fieldAlign[$listField->name] = "'{$listField->name}' => 'center'";
            } else {
                if(empty($listField->listColAlign)){
                    switch ($dType){
                        case 'int':
                        case 'tinyint':
                        case 'float':
                        case 'money':
                        case 'decimal(2)':
                        case 'longdecimal(6)':
                            $fieldAlign[$listField->name] = "'{$listField->name}' => 'right'";
                            break;
                        case 'date':
                        case 'time':
                        case 'datetime':
                        case 'bool';
                            $fieldAlign[$listField->name] = "'{$listField->name}' =>  'center'";
                            break;
                        default:
                            $fieldAlign[$listField->name] = "'{$listField->name}' =>  'left'";
                            break;
                    }
        
                } else {
                    $fieldAlign[] = "'{$listField->name}' => '{$listField->listColAlign}'";
                }
            }
        } else {
            $fieldAlign[] = "'{$listField->name}' => 'hide'"; //checked by ListRenderer
        }
    }
    //change ID column (always first) to align center
    //$fieldAlign[0] = "   'center'";
    /*$content .= join(",\n", $fieldAlign);
    $content .= "\n   );\n";*/
    $content = "\$fieldAlign = array(\n";
    $content .= join(",\n", $fieldAlign);
    $content .= "\n);\n";
    $output['/**fieldAlign**/'] = $content;
    
    $content = "\$fieldTypes = array(\n";
    $content .= join(",\n", $fieldTypes);
    $content .= "\n);\n";
    $output['/**fieldTypes**/'] = $content;

    $content = "\$fieldFormats = array(\n";
    $content .= join(",\n", $fieldFormats);
    $content .= "\n);\n";
    $output['/**fieldFormats**/'] = $content;

    //set the default ORDER BY columns
    if(count($this->orderByFields) > 0){
        $orderBys = $this->orderByFields;
    } else {
        $orderByField = reset($this->Fields);
        $orderBys = array($orderByField->name => false);
    }

    $output['/**defaultOrderBys**/'] = '$defaultOrderBys = unserialize(\''.escapeSerialize($orderBys).'\');';

    $output['/**tabs|EDIT**/'] = $module->generateTabs('List');
    $output['/**tabs|VIEW**/'] = $module->generateTabs('List', "view");
	$output['/**tabs|RECORDMENU**/'] = $module->generateTabs('ListRecordMenu');

	( $this->allowDelete ? $output['/**allowDelete**/'] = ' $allowEdit ' : $output['/**allowDelete**/'] = ' false ' );
	
    //custom code
    if(count($this->customCodes) > 0){
        foreach($this->customCodes as $location => $codeObject){
            $output[$location] = $codeObject->getContent();
        }
    }

    echo "$debug_prefix (end)\n";
    debug_unindent();
    return $output;
}

function isRecordScreen()
{
    return false;
}
} //end class ListScreen




class RecordReportScreen extends Screen
{

function &Factory($element, $moduleID){
    $screen = new RecordReportScreen($element, $moduleID);
    return $screen;
}

function RecordReportScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;
}
}


class ListReportScreen extends Screen
{

function &Factory($element, $moduleID){
    $screen = new ListReportScreen($element, $moduleID);
    return $screen;
}

function ListReportScreen(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;
}

function isRecordScreen()
{
    return false;
}
}


class ViewScreenSection {
var $Fields = array();
var $Grids = array();
var $allowEdit;
var $moduleID;


function &Factory($element, $moduleID){
    $section = new ViewScreenSection($element, $moduleID);
    return $section;
}


function ViewScreenSection(&$element, $moduleID)
{
    $this->name = $element->getAttr('name');
    $this->phrase = $element->getAttr('phrase');
    $this->allowEdit = false;
    $this->moduleID = $moduleID;

    foreach($element->c as $field_element){
        if('Field' == substr($field_element->type, -5)){
            $this->addField($field_element->createObject($moduleID));
        } elseif('Grid' == substr($field_element->type, -4)){
            $this->addGrid($field_element->createObject($moduleID));
        } else {
            die("ViewScreenSection ($this->name): element type {$field_element->type} not handled");
        }
    }
}

    function addField(&$field){
        if ($field->isEditable()){
            print "ERROR: can't add a ";
            print get_class($field);
            print " field to a View screen section.\n";
        } else {
            $this->Fields[$field->name] = &$field;
        }
    }

    function addGrid(&$grid){
        if ($grid->isEditable()){
            print "ERROR: can't add a ";
            print get_class($grid);
            print " grid to a View screen section.\n";
        } else {
            //$this->Grids[$grid->moduleID] = $grid;
            $gridID = strtolower(get_class($grid)) . '_' . $grid->moduleID;
            $this->Grids[$gridID] = &$grid;
        }
    }
}





class ListField
{
var $name;
var $phrase;
var $linkField; //name of module field that holds a URL
var $listColAlign;


function &Factory($element, $moduleID)
{
    return  new ListField ($element, $moduleID);
}


function ListField(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase');
    if(empty($this->phrase)){
        if('IsBestPractice' != $this->name){
            $moduleField = GetModuleField($moduleID, $this->name);
            $this->phrase = $moduleField->phrase;
        }
    }
    $this->linkField = $element->getAttr('link');
    $this->listColAlign = $element->getAttr('align');
}


//returns an array of names of subfields (recursively), including this field
function getRecursiveFields()
{
    $subFields = array();
    $subFields[$this->name] = $this;
    return $subFields;
}


function isEditable()
{
    return false;
}

function isVisible()
{
    return true;
}
} //end class ListField




class CustomCode
{
var $location;
var $tagPrefix;
var $content;


function Factory($element, $moduleID)
{
    return new CustomCode (
        $element->getAttr('location'),
        $element->getAttr('tagPrefix'),
        $element->c
    );
}


function DocFactory($element, $moduleID)
{
    return new CustomCodeDoc($element, $moduleID);
}


function CustomCode($pLocation, $pTagPrefix, $contentLines)
{
    $this->location = $pLocation;
    if(empty($pTagPrefix)){
        $this->tagPrefix = 'CUSTOM_CODE|';
    }
    if('none' == $pTagPrefix){
        $this->tagPrefix = '';
    }
    $this->content = '';
    foreach($contentLines as $contentLine){
        $this->content .= trim($contentLine->content)."\n";
    }
}


function getTag()
{
    return '/**'.$this->tagPrefix . $this->location.'**/';
}


function getContent()
{
    return $this->content;
}
}


//this class defines the database-specific types and styles of each RDBMS
//supports MySQL and MS SQL Server ('MSSQL')
//NOTE 2008-09-19: This could be replaced by MDB2 functionality.
class DBFormat {
    var $dataTypes = array();   //list of translated data type names
    var $PKDeclaration;         //string for the Primary Key drclaration
    var $flags = array();       //list of translated column options

    function DBFormat($targetDB){
        //translates to
        switch ($targetDB){
        case 'MSSQL':
            $this->dataTypes = array(
                'bool' => 'bit',
                'tinyint' => 'int',
                'int' => 'int',
                'varchar(5)' => 'nvarchar(5)',
                'varchar(10)' => 'nvarchar(10)',
                'varchar(15)' => 'nvarchar(15)',
                'varchar(20)' => 'nvarchar(20)',
                'varchar(25)' => 'nvarchar(25)',
                'varchar(30)' => 'nvarchar(30)',
                'varchar(50)' => 'nvarchar(50)',
                'varchar(75)' => 'nvarchar(75)',
                'varchar(128)' => 'nvarchar(128)',
                'varchar(255)' => 'nvarchar(255)',
                'date' => 'shortdatetime',
                'time' => 'shortdatetime',
                'datetime' => 'datetime',
                'text' => 'ntext',
                'money' => 'money',
                'decimal(2)' => 'decimal(12,2)',
                'longdecimal(6)' => 'decimal(24,6)',
                'float' => 'float'
            );
            $this->flags = array(
                'not null' => 'NOT NULL',
                'unsigned' => '', //nothing corresponding
                'auto_increment' => 'IDENTITY (1,1)'
            );
            $this->PKDeclaration = "CONSTRAINT PK_$tableName PRIMARY KEY";
            //$ixStyle = "separate";
            break;
        default:    //such as MySQL
            $this->dataTypes = array(
                'bool' => 'bool',
                'int' => 'int',
                'tinyint' => 'tinyint',
                'bigint' => 'bigint',
                'varchar(5)' => 'varchar(5)',
                'varchar(10)' => 'varchar(10)',
                'varchar(15)' => 'varchar(15)',
                'varchar(20)' => 'varchar(20)',
                'varchar(25)' => 'varchar(25)',
                'varchar(30)' => 'varchar(30)',
                'varchar(50)' => 'varchar(50)',
                'varchar(75)' => 'varchar(75)',
                'varchar(128)' => 'varchar(128)',
                'varchar(255)' => 'varchar(255)',
                'date' => 'date',
                'time' => 'time',
                'datetime' => 'datetime',
                'text' => 'text',
                'money' => 'decimal(12,4)',
                'decimal(2)' => 'decimal(12,2)',
                'longdecimal(6)' => 'decimal(24,6)',
                'float' => 'float'
            );
            $this->flags = array(
                'not null' => 'not null',
                'unsigned' => 'unsigned',
                'auto_increment' => 'auto_increment'
            );
            $this->PKDeclaration = "PRIMARY KEY";
            break;
        }
    }
}


//a dbquote function for preg_replace_callback
function aQuote($matches){
//  print "matches:\n";
//  print_r($matches);
//  print "\n";

    $matches[1] = str_replace('**' , '', $matches[1]);
    list($type, $val) = preg_split('/:/', $matches[1]);

    switch($type){
    case 'localVar':
        return "\". \${$val} .\"";
        break;
    case 'fieldVal':
        return "\". dbQuote(\$data['{$val}']) .\"";
        break;
    case 'fieldChkVal':
        return "\". dbQuote(\$data['{$val}'], 'bool') .\"";
        break;
    case 'fieldDateVal':
        return "\". dbQuote(\$data['{$val}'], 'date') .\"";
        break;
    case 'fieldDateTimeVal':
        return "\". dbQuote(\$data['{$val}'], 'datetime') .\"";
        break;
    case 'fieldIntVal':
        return "\". dbQuote(\$data['{$val}'], 'int') .\"";
        break;
    case 'fieldMoneyVal':
        return "\". dbQuote(\$data['{$val}'], 'money') .\"";
        break;
    case 'UserID':
        return "\".\$User->PersonID.\"";
        break;
    case 'PR-ID':
        return "\".\$recordID.\"";
        break;
    case 'RecordID':
        return "\".\$recordID.\"";
        break;
    default:
        return $val;
    }

    //return "replaced";
}

//"extends" a module with reversed ForeignFields from an "extending" (dependent) module
function GetExtendedModule($ModuleID, $extendingModuleID)
{
    $debug_prefix = debug_indent("GetExtendedModule() $ModuleID, $extendingModuleID:");

    global $foreignModules;

    //parpe, surq
    $extendedModule = GetModule($ModuleID);
    if($extendedModule->isExtendedByModuleID == $extendingModuleID){
        return $extendedModule;
    }

    //parse, surr
    $extendingModule = GetModule($extendingModuleID);
    $extendedModule->parentModuleID = $extendingModule->parentModuleID;

    $moduleInfo = GetModuleInfo($ModuleID);
    $extendedPK = $moduleInfo->getPKField();
    $extModuleInfo = GetModuleInfo($extendingModuleID);
    $extendingPK = $extModuleInfo->getPKField();

    $extendedMFNames = array_keys($extendedModule->ModuleFields);
    $new_elements = array();
    $extendedModuleMap = &$extendedModule->_map;
    foreach($extendedModuleMap->c as $map_element){
        if('ModuleFields' == $map_element->name){
            $extendedModuleFields_element = &$map_element;
            break;
        }
    }

    foreach($extendingModule->ModuleFields as $name => $dummy){
        if(!in_array($name, $extendedMFNames)){
            print "$debug_prefix   adding field $name to $ModuleID\n";
            $extendingMF = $extendingModule->ModuleFields[$name];

            //translate the extending modulefield into a valid foreign field in the extended module
            switch(strtolower(get_class($extendingMF))){
            case 'tablefield':
                $newFieldType = 'ForeignField';
                $listCondition = '';
                if($extendingModule->parentKey){
                    //$listCondition = $extendingModule->localKey." = '[*{$extendingModule->parentKey}*]'";
                    $listCondition = $extendingModule->localKey." = '/**RecordID**/'";
                }

                $attributes = array(
                    'name' => $name,
                    'type' => $extendingMF->dataType,
                    'localTable' => $ModuleID,
                    'key' => $extendedPK,
                    'foreignTable' => $extendingModuleID,
                    'foreignField' => $name,
                    'foreignKey' => $extendingModule->extendsModuleKey, //was $extendingPK (wrong)
                    'joinType' => 'left',
                    'phrase' => $extendingMF->phrase,
                    'defaultValue' => $extendingMF->defaultValue,
                    'listCondition' => $listCondition
                );

                break;
            case 'codefield':
            case 'foreignfield':
                $newFieldType = 'ForeignField';
                $attributes = array(
                    'name' => $name,
                    'type' => $extendingMF->dataType,
                    'localTable' => $ModuleID,
                    'key' => $extendingMF->localKey,
                    'foreignTable' => $extendingMF->foreignTable,
                    'foreignField' => $extendingMF->foreignField,
                    'foreignKey' => $extendingMF->foreignKey,
                    'joinType' => 'left',
                    'phrase' => $extendingMF->phrase,
                    'defaultValue' => $extendingMF->defaultValue
                );
                break;
            case 'remotefield':
                $newFieldType = 'RemoteField';
                $attributes = array(
                    'name' => $name,
                    'type' => $extendingMF->dataType,
                    'localTable' => $extendingMF->moduleID,
                    'remoteModuleID' => $extendingMF->remoteModuleID,
                    'remoteModuleIDField' => $extendingMF->remoteModuleIDField,
                    'remoteRecordIDField' => $extendingMF->remoteRecordIDField,
                    'remoteField' => $extendingMF->remoteField,
                    'remoteDescriptorField' => $extendingMF->remoteDescriptorField,
                    'remoteDescriptor' => $extendingMF->remoteDescriptor,
                    'phrase' => $extendingMF->phrase,
                    'defaultValue' => $extendingMF->defaultValue,
                    'conditionModuleID' => $extendingModuleID //parse
                );
                break;
            default:
                //die('class '.get_class($extendingMF).' not handled in function GetExtendedModule');
                print "$debug_prefix class ".get_class($extendingMF)." not handled in function GetExtendedModule\n";
                break 2; //
            }
            $new_element = new Element($name, $newFieldType, $attributes);
            $extendedModuleFields_element->c[] = $new_element;

            //$newModuleField = $new_element->createObject($extendingModuleID);
            $newModuleField = $new_element->createObject($ModuleID); //this parses

            indent_print_r($newModuleField, 'new modulefield');

            $extendedModule->ModuleFields[$name] = $newModuleField;

        }
    }

    $extendedModule->isExtendedByModuleID = $extendingModuleID;
    $foreignModules[$ModuleID] = $extendedModule;

    debug_unindent();
    return $extendedModule;
}




/**
 *  Helper class to track table indexes
 */
class Index
{
var $name;
var $unique = false;
var $primary = false;
var $fields = array();
var $module; //reference to parent object

function &Factory(&$element, $moduleID, &$callerRef)
{
    if(empty($callerRef)){
        trigger_error("The Index class requires a reference to the calling module object.", E_USER_ERROR);
    }

    if(!is_a($callerRef, 'Module')){
        trigger_error("The Index class can only be used by a module object.", E_USER_ERROR);
    }
    return new $element->type($element, $moduleID, $callerRef);
}


/**
 *  PHP 4 constructor
 */
function Index(&$element, $moduleID, &$callerRef)
{
    $this->__construct($element, $moduleID, $callerRef);
}


function __construct(&$element, $moduleID, &$callerRef)
{
    $this->module =& $callerRef;

    $fieldref_elements = $element->selectElements('FieldRef');
    if(count($fieldref_elements) == 0){
        trigger_error(get_class($this) .": {$this->name} requires at least one FieldRef element.", E_USER_ERROR);
    }

    foreach($fieldref_elements as $fieldref_element){

        //check that the field exists in the module
        if(isset($this->module->ModuleFields[$fieldref_element->name])){
            $moduleField = $this->module->ModuleFields[$fieldref_element->name];
            if('tablefield' == strtolower(get_class($moduleField))){

                $options = array();
                $length = $fieldref_element->getAttr('length');
                if(!empty($length)){
                    $options['length'] = $length;
                } else {
//check for clob/text data type, add a default key length of ...100?
                    if('text' == $moduleField->dataType){
                        $options['length'] = 75;
                    }
                }

                //add it to the fields list
                $this->fields[$fieldref_element->name] = $options;

            } else {
                trigger_error(get_class($this) .": FieldRef {$fieldref_element->name} does not match a TableField element.", E_USER_ERROR);
            }

        } else {
            trigger_error(get_class($this) .": FieldRef {$fieldref_element->name} does not match a module field.", E_USER_ERROR);
        }
    }

    if('primarykey' == strtolower(get_class($this))){
        $this->name = 'PRIMARY';
        $this->unique = true;
    } else {
        //index name is prepended with module ID in the DB
        $name = $element->getAttr('name');
        if(empty($name)){
            $name = join('_', array_keys($this->fields));
        }
        $this->name = $moduleID .'_'. $name;
        $this->unique = 'yes' == strtolower($element->getAttr('unique'));
    }
} //end Index::__constructor


/**
 *  Static function to return a list of indexes, along with unique/non-uniueness
 */
function &getTableIndexList($moduleID)
{
    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $mdb2->loadModule('Reverse', null, true);

    //get both table constraints and indexes
    static $indexes = array();

    if(!isset($indexes[$moduleID])){
        //unique indexes
        $temp_raw_indexes = $mdb2->manager->listTableConstraints($moduleID);
        mdb2ErrorCheck($temp_raw_indexes);
        $temp_indexes = array();
        foreach($temp_raw_indexes as $index_name){
            //if('PRIMARY' != $index_name){
                $temp_indexes[$index_name] = true;
            //}
        }

        //non-unique indexes
        $temp_raw_indexes = $mdb2->manager->listTableIndexes($moduleID);
        mdb2ErrorCheck($temp_raw_indexes);
        foreach($temp_raw_indexes as $index_name){
            $temp_indexes[$index_name] = false;
        }

        $indexes[$moduleID] = $temp_indexes;
    }

    return $indexes[$moduleID];
}


/**
 *  Check whether the index exists in the database table
 *
 *  Returns "add", "update", or "ok"
 */
function verify()
{
    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $mdb2->loadModule('Reverse', null, true);

    //$indexes is an array where the key is the index name, the value is true if the index is unique
    $indexes = Index::getTableIndexList($this->module->ModuleID);
    if(isset($indexes[$this->name])){

        if($this->unique != $indexes[$this->name]){
            return 'update';
        }

        //verify index fields
        if($this->unique){
            $db_info = $mdb2->reverse->getTableConstraintDefinition($this->module->ModuleID, $this->name);
        } else {
            $db_info = $mdb2->reverse->getTableIndexDefinition($this->module->ModuleID, $this->name);
        }
        mdb2ErrorCheck($db_info);

        $match = true;
        reset($this->fields);
        foreach($db_info['fields'] as $db_fieldname => $db_field_props){
            $def_fieldname = key($this->fields);
            if($db_fieldname != $def_fieldname){
                $match = false;
            }
            next($this->fields);
        }

        if($match){
            return 'ok';
        } else {
            return 'update';
        }

    } else {
        return 'add';
    }

}


/**
 *  Static function to drop an index
 */
function drop($moduleID, $index_name, $unique)
{
    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    if($unique){
        $result = $mdb2->manager->dropConstraint($moduleID, $index_name);
        trace("dropping constraint $index_name...");
    } else {
        $result = $mdb2->manager->dropIndex($moduleID, $index_name);
        trace("dropping index $index_name...");
    }
    mdb2ErrorCheck($result, true, true);
    trace('successful');
    return $result;
}


function add()
{
    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $mdb2_def = $this->getMDB2Def();

    if($this->unique){
        trace("adding constraint {$this->name}...");
        $result = $mdb2->manager->createConstraint($this->module->ModuleID, $this->name, $mdb2_def);
    } else {
        trace("adding index {$this->name}...");
        $result = $mdb2->manager->createIndex($this->module->ModuleID, $this->name, $mdb2_def);
    }
    mdb2ErrorCheck($result, true, true);
    trace('successful');
    return $result;
}


function update()
{
    trace("updating index/constraint {$this->name}...");

    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Manager');
    $mdb2_def = $this->getMDB2Def();

    if($this->unique){
        trace("   dropping constraint {$this->name}...");
        $result = $mdb2->manager->dropConstraint($this->module->ModuleID, $this->name);
        mdb2ErrorCheck($result, true, true);

        trace("   adding constraint {$this->name}...");
        $result = $mdb2->manager->createConstraint($this->module->ModuleID, $this->name, $mdb2_def);
    } else {
        trace("   dropping index {$this->name}...");
        $result = $mdb2->manager->dropIndex($this->module->ModuleID, $this->name);
        mdb2ErrorCheck($result, true, true);

        trace("   adding index {$this->name}...");
        $result = $mdb2->manager->createIndex($this->module->ModuleID, $this->name, $mdb2_def);
    }

    mdb2ErrorCheck($result, true, true);
    trace('successful');
    return $result;
}


function getMDB2Def()
{
    $def = array(
        'primary' => $this->primary,
        'unique' => $this->unique,
        'fields' => array()
    );

    foreach($this->fields as $field_name => $options){
        $def['fields'][$field_name] = $options;
    }

    return $def;
}
} //end class Index




class PrimaryKey extends Index
{
var $primary = true;
var $unique = true;


/**
 *  PHP 4 constructor
 */
function PrimaryKey(&$element, $moduleID, &$callerRef)
{
    $this->__construct($element, $moduleID, $callerRef);
}
} //end class PrimaryKey



/**
 * Prepares the SQL stuff for checking consistency conditions.
 *
 *
 */
class ConsistencyCondition
{
var $triggers = array();
var $targets = array();
var $enforce = true;

function Factory(&$element, $moduleID, &$moduleRef)
{
    $obj = new ConsistencyCondition($element, $moduleID, $moduleRef);
    return $obj;
}


//PHP 4 constructor
function ConsistencyCondition(&$element, $moduleID, &$moduleRef)
{
    $this->__construct($element, $moduleID, $moduleRef);
}


function __construct(&$element, $moduleID, &$moduleRef)
{
    $str_enforce = $element->getAttr('enforce');
    if(false !== strpos(strtolower($str_enforce), 'no')){
        $this->enforce = false;
    }

    $trigger_elements = $element->selectElements('ConditionTrigger');
    if(count($trigger_elements) < 1){
        trigger_error("A ConsistencyCondition must have at least one ConditionTrigger element.", E_USER_ERROR);
    }
    foreach($trigger_elements as $trigger_element){
        $trigger_field_elements = $trigger_element->selectElements('TriggerField');
        if(count($trigger_field_elements) < 1){
            trigger_error("A ConditionTrigger must have at least one TriggerField element.", E_USER_ERROR);
        }
        $trigger_fields = array();
        foreach($trigger_field_elements as $trigger_field_element){
            $tfObj =& $trigger_field_element->createObject($moduleID);
            $trigger_fields[$tfObj->name] =& $tfObj;
            unset($tfObj);
        }
        $this->triggers[] = $trigger_fields;
    }
trace($this->triggers, '$this->triggers');

    $target_elements = $element->selectElements('SubModuleTarget');
    foreach($target_elements as $target_element){
        $subModuleID = $target_element->getAttr('moduleID', true);
        if(!isset($moduleRef->SubModules[$subModuleID])){
            trigger_error("A SubModuleTarget must refer to a valid SubModule. '$subModuleID' is not a submodule of the '$moduleID' module.", E_USER_ERROR);
        }
    }

    $target_elements = array_merge($target_elements, $element->selectElements('LocalFieldTarget'));
    if(count($target_elements) < 1){
        trigger_error("A ConsistencyCondition must have at least one target element. Either a SubModuleTarget, or a LocalFieldTarget, or both.", E_USER_ERROR);
    }
    foreach($target_elements as $target_element){
            $tgObj =& $target_element->createObject($moduleID);
            $this->targets[] =& $tgObj;
            unset($tgObj);
    }
    //trace($this, 'ConsistencyCondition');
}


function makeTriggerSQL($condition_ix)
{
    //create a SQL CASE snippet, plus pass joins
    $triggerSQLs = array();
    $joins = array();
    foreach($this->triggers as $trg_ix => $triggerFields){
        $defSnips = array();
        foreach($triggerFields as $triggerField){
            $def = $triggerField->getDef();
            $defSnips[] = $def['snippet'];
            $joins = array_merge($joins, $def['joins']);
        }
        $SQL = "CASE WHEN ";
        $SQL .= join(' AND ', $defSnips);
        //$SQL .= " THEN 1 ELSE NULL END";
        $SQL .= " THEN 'C{$condition_ix}Tr{$trg_ix}' ELSE NULL END";
        $triggerSQLs[] = $SQL;
    }

    if(count($this->triggers) > 1){
        //$SQL = "COALESCE(".join(',', $triggerSQLs).")";
        $SQL = "CONCAT_WS(',', ".join(',', $triggerSQLs).")";
    }
    $SQL .= " AS Condition_$condition_ix";
    return array('triggerExpr' => $SQL, 'joins' => $joins);
}


function makeTargetSQL($condition_ix)
{
    //create a SQL CASE snippet, plus pass joins
    $targetSQLs = array();
    $joins = array();
trace($this->targets, 'makeTargetSQL targets');
    foreach($this->targets as $tgt_ix => $target){
        $defSnips = array();
        $def = $target->getDef();
        $defSnips[] = $def['snippet'];
        if(isset($def['joins'])){
            $joins = array_merge($joins, $def['joins']);
        }

        $SQL = "CASE WHEN ";
        $SQL .= join(' AND ', $defSnips);
        $SQL .= " THEN 1 ELSE NULL END";
        //$SQL .= " AS Target_$tgt_ix";
        $SQL .= " AS C{$condition_ix}Ta{$tgt_ix}";
        $targetSQLs[$tgt_ix] = $SQL;
    }

    return array('selects' => $targetSQLs, 'joins' => $joins);
}


function makePhraseString($index)
{
    $triggerString = '';
    $triggerStrings = array();
    foreach($this->triggers as $trg_ix => $triggerFields){
        $triggerFieldStrings = array();
        foreach($triggerFields as $triggerField){
            $triggerFieldStrings[] = "'{$triggerField->name}' => gettext(\"{$triggerField->phrase}\")" ;
        }
        //$triggerStrings[$trg_ix] = "'$trg_ix' => array(\n" . join(",\n", $triggerFieldStrings) . "\n)";
        $triggerStrings[$trg_ix] = "'C{$index}Tr{$trg_ix}' => array(\n" . join(",\n", $triggerFieldStrings) . "\n)";
    }
    trace($triggerStrings, 'triggerStrings');
    $triggerString = join(",\n", $triggerStrings);

    $targetString = '';
    $targetStrings = array();
    foreach($this->targets as $tgt_ix => $target){
        //$targetStrings[] = "'Target_$tgt_ix' => gettext(\"{$target->phrase}\")";
        $targetStrings[] = "'C{$index}Ta{$tgt_ix}' => gettext(\"{$target->phrase}\")";
    }
    $targetString = join(",\n", $targetStrings);

    //return "'Condition_$index' => array(\n'phrase' => gettext(\"{$this->phrase}\"),\n'triggers' => array($triggerString),\n'targets' => array($targetString))";
    return "$triggerString,\n$targetString";
}
} //end class ConsistencyCondition




class TriggerField
{
var $name;
var $mode;
var $values = array();
var $phrase;

function Factory(&$element, $moduleID)
{
    $obj = new TriggerField($element, $moduleID);
    return $obj;
}


//PHP 4 constructor
function TriggerField(&$element, $moduleID)
{
    $this->__construct($element, $moduleID);
}


function __construct(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->moduleID = $moduleID;
    $this->phrase = $element->getAttr('phrase', true);

    $this->mode = $element->getAttr('mode');
    if(empty($this->mode)){
        $this->mode = 'equals';
    }

    $require_values = true;

    switch($this->mode){
    case 'greater-than':
    case 'in':
    case 'less-than':
    case 'one-of': //synonymous to 'in'
    case 'equals':
    case 'not':
        break;
    case 'non-empty':
    case 'non-zero':
        $require_values = false;
        break;
    default:
        trigger_error("Mode '{$this->mode}' not supported in TriggerField {$this->name}.", E_USER_ERROR);
    }

    if($require_values){
        if(count($element->c) < 1){
            trigger_error("TriggerField {$this->name} requires one or more value elements.", E_USER_ERROR);
        }
        foreach($element->c as $value_element){
            switch($value_element->type){
            case 'StaticValue':
                $this->values[] = $value_element->getAttr('value', true);
                break;
            case 'FieldValue':
                $fieldValue = array();
                $fieldValue['type'] = 'fieldValue';
                $fieldValue['value'] = $value_element->getAttr('value', true);
                $this->values[] = $fieldValue;
                //trigger_error("TriggerField does not YET support '{$value_element->type}' as a value.", E_USER_ERROR);
                break;
            default:
                trigger_error("TriggerField does not support '{$value_element->type}' as a value.", E_USER_ERROR);
            }
        }
    }

}


function getDef()
{
    $snippet = '';
    $selectDef = GetQualifiedName($this->name, $this->moduleID);
    $joins = array();
    $joins = array_merge($joins, GetJoinDef($this->name, $this->moduleID));

    //preps $valueExpr for the next 'case' statement in the cases specified
    switch($this->mode){
    case 'greater-than':
    case 'less-than':
    case 'equals':
    case 'not':
        $value = reset($this->values); //this needs to also handle field values, by getting the select def and join def of the referenced field
        if(is_array($value)){
            if($value['type'] == 'fieldValue'){
                $valueExpr = GetQualifiedName($value['value'], $this->moduleID);
                $joins = array_merge($joins, GetJoinDef($value['value'], $this->moduleID));
            }
        } else {
            $valueExpr = dbQuote($value);
        }
    default:
        break;
    }


    switch($this->mode){
    case 'non-empty':
        $snippet = "!ISNULL({$selectDef})";
        break;
    case 'non-zero':
        $snippet = "$selectDef != 0";
        break;
    case 'not':
        $snippet = "$selectDef != $valueExpr";
        break;
    case 'equals':
        $snippet = "$selectDef = $valueExpr";
        break;
    case 'greater-than':
        $snippet = "$selectDef > $valueExpr";
        break;
    case 'less-than':
        $snippet = "$selectDef < $valueExpr";
        break;
    case 'in':
    case 'one-of': //synonymous to 'in'
        $valueExprs = array();
        foreach($this->values as $value){
            if(is_array($value)){
                if($value['type'] == 'fieldValue'){
                    $valueExpr = GetQualifiedName($value['value'], $this->moduleID);
                    $joins = array_merge($joins, GetJoinDef($value['value'], $this->moduleID));
                }
            } else {
                $valueExpr = dbQuote($value);
            }
            $valueExprs[] = $valueExpr;
        }
        $snippet = $selectDef . ' IN ('.join(',', $valueExprs).')';
        break;
    default:
        trigger_error("Mode '{$this->mode}' not supported in TriggerField {$this->name}.", E_USER_ERROR);
    }
    return array('snippet' => $snippet, 'joins' => $joins);
}
} //end class TriggerField


class SubModuleTarget
{
var $moduleID;
var $parentModuleID;
var $satisfactions = array();
var $phrase;

function Factory(&$element, $moduleID)
{
    $obj = new SubModuleTarget($element, $moduleID);
    return $obj;
}


//PHP 4 constructor
function SubModuleTarget(&$element, $moduleID)
{
    $this->__construct($element, $moduleID);
}


function __construct(&$element, $moduleID)
{
    $this->moduleID = $element->getAttr('moduleID', true);
    $this->parentModuleID = $moduleID;
    $this->phrase = $element->getAttr('phrase', true);

    $satisfaction_elements = $element->selectElements('Satisfaction');
    if(count($satisfaction_elements) < 1){
        trigger_error("A SubModuleTarget must have at least one Satisfaction element.", E_USER_ERROR);
    }
    foreach($satisfaction_elements as $satisfaction_element){
        $satisfaction_props = array();
        $type = $satisfaction_element->getAttr('type', true);
        switch($type){
        case 'matching-rows':
            break;
        default:
            trigger_error("A Satisfaction element of a SubModuleTarget does not support the type '$type'.", E_USER_ERROR);
        }
        $satisfaction_props['type'] = $type;

        $mode = $satisfaction_element->getAttr('mode', true);
        switch($mode){
        case 'min':
        case 'max':
            break;
        default:
            trigger_error("A Satisfaction element of a SubModuleTarget does not support the mode '$mode'.", E_USER_ERROR);
        }
        $satisfaction_props['mode'] = $mode;

        $value = $satisfaction_element->getAttr('value', true);
        //simple validation; expand when required
        if(!is_numeric($value) && !is_integer(intval($value))) {
            trigger_error("A Satisfaction element of a SubModuleTarget must have an integer value (found '$value').", E_USER_ERROR);
        }
        $satisfaction_props['value'] = $value;
        $this->satisfactions[] = $satisfaction_props;
    }
}


function getDef()
{
    //need submodule properties here in order to determine parent join
    $subModule = GetModule($this->moduleID);
    $subModuleAlias = $this->moduleID . '_sub';

    trace($subModule->conditions, "Submodule Conditions for {$this->moduleID}");
    trace("parentKey {$subModule->parentKey}");
    trace("localKey  {$subModule->localKey}");


    $parentSelects = array();
    $parentJoins = array();
    $subSelects = array();
    $subJoins = array();

    if(!empty($subModule->localKey)){
//        $localModuleField = $subModule->ModuleFields[$subModule->localKey];
//        $parentModuleField = GetModuleField($this->parentModuleID, $subModule->parentKey);

        $parentSelects[$subModule->localKey] = GetQualifiedName($subModule->parentKey, $this->parentModuleID); //indexing $parentSelects by sub field, intentionally
        $parentJoins = array_merge($parentJoins, GetJoinDef($subModule->parentKey, $this->parentModuleID));

        $subSelects[$subModule->localKey] = GetQualifiedName($subModule->localKey, $this->moduleID);
        $subJoins = array_merge($subJoins, GetJoinDef($subModule->localKey, $this->moduleID));
    }

    //loop through submodule conditions and add to the above.
    foreach($subModule->conditions as $conditionName => $conditionValue){
        //handle all the conditions in a clever way just like SummaryField::makeJoinDef()
    }
    //might need to set $SQLBaseModuleID?
    $subJoins = SortJoins($subJoins);

trace($this->satisfactions, "SubModuleTarget satisfactions");

    //$snippet = '0 < ( SELECT COUNT(*) FROM `'.$this->moduleID.'` WHERE _Deleted = 0 )';
    $subModuleJoin = "LEFT OUTER JOIN ( SELECT\n";
    $satisfactionSelects = array();
    foreach($this->satisfactions as $satisfaction){
        //more than one 'type' in the same target is probably not a good idea.
        switch($satisfaction['type']){
        case 'matching-rows':
            $subModuleJoin .= "COUNT(*) AS matchingRows";
            switch($satisfaction['mode']){
            case 'min':
                $satisfactionSelects[] = $satisfaction['value'] . " <= `$subModuleAlias`.matchingRows";
                break;
            case 'max':
                $satisfactionSelects[] = $satisfaction['value'] . " >= `$subModuleAlias`.matchingRows";
                break;
            default:
                break;
            }
            break;
        default:
            break;
        }
    }
    $select = join(' AND ', $satisfactionSelects);

    $subModuleJoinDefs = array();
    $groupBys = array();
    foreach($subSelects as $subFieldName => $subSelect){
        $subModuleJoin .= ",\n$subSelect AS $subFieldName";
        $subModuleJoinDefs[] = "{$parentSelects[$subFieldName]} = `{$subModuleAlias}`.$subFieldName";
        $groupBys[] = $subSelect;
    }
    $subModuleJoin .= "\nFROM `{$this->moduleID}`";
    foreach($subJoins as $subJoin){
        $subModuleJoin .= "\n$subJoin";
    }

    $subModuleJoin .= "\nWHERE _Deleted = 0";
    $subModuleJoin .= "\nGROUP BY ".join(', ', $groupBys);
    $subModuleJoin .= ") AS `$subModuleAlias` ON (\n";
    $subModuleJoin .= join("\n   ", $subModuleJoinDefs);
    $subModuleJoin .= "\n) ";

    global $gTableAliasParents;
    global $SQLBaseModuleID;
    $gTableAliasParents[$SQLBaseModuleID][$subModuleAlias] = $this->parentModuleID;

    $joins = array(
        $subModuleAlias => $subModuleJoin
    );
    $joins = array_merge($parentJoins, $joins);
    $joins = SortJoins($joins);

trace($joins, "SubModuleTarget joins");

    return array('snippet' => $select, 'joins' => $joins);
}
} //end class SubModuleTarget


class LocalFieldTarget
{
var $name;
var $satisfactions = array();
var $phrase;

function Factory(&$element, $moduleID)
{
    $obj = new LocalFieldTarget($element, $moduleID);
    return $obj;
}


//PHP 4 constructor
function LocalFieldTarget(&$element, $moduleID)
{
    $this->__construct($element, $moduleID);
}


function __construct(&$element, $moduleID)
{
    $this->name = $element->getAttr('name', true);
    $this->phrase = $element->getAttr('phrase', true);

    $satisfaction_elements = $element->selectElements('Satisfaction');
    if(count($satisfaction_elements) < 1){
        trigger_error("A LocalFieldTarget must have at least one Satisfaction element.", E_USER_ERROR);
    }
    foreach($satisfaction_elements as $satisfaction_element){
        $mode = $satisfaction_element->getAttr('mode');
        if(empty($mode)){ //workaround for previous format: 'type' is now 'mode'
            $mode = $satisfaction_element->getAttr('type');
            if(empty($mode)){
                trigger_error("The Satisfaction of LocalFieldTarget {$this->name} must have a 'mode' attribute.", E_USER_ERROR);
            } else {
                trigger_error("The 'type' attribute of a Satisfaction of LocalFieldTarget {$this->name} should be renamed to 'mode'.", E_USER_WARNING);
            }
        }
        $satisfaction_props = array();

        $require_values = true;
        switch($mode){
        case 'greater-than':
        case 'in':
        case 'less-than':
        case 'one-of': //synonymous to 'in'
        case 'equals':
        case 'not':
            break;
        case 'non-empty':
        case 'non-zero':
            $require_values = false;
            break;
        default:
            trigger_error("Mode '{$mode}' not supported in Satisfaction of LocalField {$this->name}.", E_USER_ERROR);
        }

        if($require_values){
            if(count($satisfaction_element->c) < 1){
                trigger_error("Satisfaction of LocalField {$this->name} requires one or more value elements.", E_USER_ERROR);
            }
            $satisfaction_values = array();
            foreach($satisfaction_element->c as $value_element){
                switch($value_element->type){
                case 'StaticValue':
                    $satisfaction_props['values'][] = $value_element->getAttr('value', true);
                    break;
                case 'FieldValue':
                    $fieldValue = array();
                    $fieldValue['type'] = 'fieldValue';
                    $fieldValue['value'] = $value_element->getAttr('value', true);
                    $satisfaction_props['values'][] = $fieldValue;
                    break;
                default:
                    trigger_error("Satisfaction of LocalField does not support '{$value_element->type}' as a value.", E_USER_ERROR);
                }
            }
        }

        $satisfaction_props['mode'] = $mode;
        $this->satisfactions[] = $satisfaction_props;
    }
}


function getDef()
{
    $snippet = '';
    $selectDef = GetQualifiedName($this->name, $this->moduleID);
    $joins = array();
    $joins = array_merge($joins, GetJoinDef($this->name, $this->moduleID));

    foreach($this->satisfactions as $s_ix => $satisfaction){
        //preps $valueExpr for the next 'case' statement in the cases specified
        switch($satisfaction['mode']){
        case 'greater-than':
        case 'less-than':
        case 'equals':
        case 'not':
            $value = reset($satisfaction['values']);
            if(is_array($value)){
                if($value['type'] == 'fieldValue'){
                    $valueExpr = GetQualifiedName($value['value'], $this->moduleID);
                    $joins = array_merge($joins, GetJoinDef($value['value'], $this->moduleID));
                }
            } else {
                $valueExpr = dbQuote($value);
            }
            break;
        default:
            break;
        }

        switch($satisfaction['mode']){
        case 'non-empty':
            $snippet = "!ISNULL({$selectDef})";
            break;
        case 'non-zero':
            $snippet = "{$selectDef} != 0";
            break;
        case 'not':
            $snippet = "$selectDef != $valueExpr";
            break;
        case 'equals':
            $snippet = "$selectDef = $valueExpr";
            break;
        case 'greater-than':
            $snippet = "$selectDef > $valueExpr";
            break;
        case 'less-than':
            $snippet = "$selectDef < $valueExpr";
            break;
        case 'in':
        case 'one-of': //synonymous to 'in'
            $valueExprs = array();
            foreach($satisfaction['values'] as $value){
                if(is_array($value)){
                    if($value['type'] == 'fieldValue'){
                        $valueExpr = GetQualifiedName($value['value'], $this->moduleID);
                        $joins = array_merge($joins, GetJoinDef($value['value'], $this->moduleID));
                    }
                } else {
                    $valueExpr = dbQuote($value);
                }
                $valueExprs[] = $valueExpr;
            }
            $snippet = $selectDef . ' IN ('.join(',', $valueExprs).')';
            break;
        default:
            trigger_error("Mode '{$satisfaction['mode']}' not supported in Satisfaction of LocalFieldTarget {$this->name}.", E_USER_ERROR);
        }
    }

    return array('snippet' => $snippet, 'joins' => $joins);

}
} //end class LocalFieldTarget
?>
