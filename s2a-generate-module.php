<?php
/**
 * Generates a module from an XML spec file.
 *
 * This file creates all the generated files necessary for a module, and uses
 * the Module class to create or modify database tables that are needed.
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
 * @version        SVN: $Revision: 1643 $
 * @last-modified  SVN: $Date: 2009-05-22 07:30:08 +0200 (Pt, 22 maj 2009) $
 */


/**
 * Defines execution state as 'generating'.  Several classes and
 * functions behave differently because of this flag.
 */
define('EXEC_STATE', 4);

$script_location = realpath(dirname(__FILE__).'');

$config = array();
$config['project'] =
    array('short' => 'p',
        'min'   => 1,
        'max'   => 1,
        'desc'  => 'The s2a project name. Must be a folder under the s2a folder.',
        'default' => 'active_agenda'
    );
$config['module'] =
    array('short'   => 'm',
        'min'     => 1,
        'max'     => 1,
        'desc'    => 'The module ID of the module to generate (required).'
    );
$config['nav-phrases'] =
    array('short'   => 'np',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'Whether or not to export phrases for the navigation menu.',
        'default' => 'no'
    );
$config['export-masterdata'] =
    array('short'   => 'em',
        'min'     => 0,
        'max'     => 0,
        'default' => 'no',
        'desc'    => 'Exports master data to files.'
    );
$config['gen-info'] =
    array('short'   => 'gi',
        'min'     => 0,
        'max'     => 0,
        'default' => 'no',
        'desc'    => 'Return extra info on the generation job in the last line.'
    );
$config['central-addons'] =
    array('short'   => 'ca',
        'min'     => 0,
        'max'     => 0,
        'default' => 'use-file',
        'desc'    => 'Whether to re-use a temporary file (saved by s2a.php or previous generation jobs) for central add-on files. This saves some processing but won\'t pick up new central add-on files.'
    );
$config['no-merged-xml'] =
    array('short'   => 'nm',
        'min'     => 0,
        'max'     => 0,
        'default' => 'false',
        'desc'    => 'Whether to suppress writing the resulting XML of applied module add-ons to a file.'
    );
//START
$config['language'] =
    array('short'   => 'l',
        'min'     => 0,
        'max'     => 1,
        'default' => 'en_US',
        'desc'    => 'Whether to use localization and with what language'
    );
//END

//handles command-line options and general setup
include $script_location . '/lib/includes/cli-startup.php';

//getting the passed parameters
$ModuleID               = $args->getValue('module');
$SaveNavigationPhrases  = $args->getValue('nav-phrase');
$ExportMasterData       = $args->getValue('export-masterdata');
$GenInfo                = $args->getValue('gen-info');
$CentralAddOns          = $args->getValue('central-addons');
$WriteMergedXML = true;
$tmp                    = $args->getValue('no-merged-xml');
if($tmp){
    $WriteMergedXML = false;
}
//START
$Language = $args->getValue('language');
//END

global $ExportMasterData;
global $CentralAddOns;
global $WriteMergedXML;


if(empty($ModuleID)){
    echo Console_Getargs::getHelp($config)."\n";
    exit;
}

print "s2a-generate-module: project = $Project, module = $ModuleID\n";

require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class

//connect with superuser privileges - regular user has no permission to
//change table structure
global $dbh;
$dbh = DB::connect(GEN_DB_DSN);

dbErrorCheck($dbh);

//module builder class
include_once CLASSES_PATH . '/module.class.php';

global $moduleParseList;
unset($moduleParseList);
$moduleParseList = array();


function CreateModule($pModuleID, $language='en_US'){

	if ( empty( $language ) ){
		$language = 'en_US';
	}
    $start_time = getMicroTime();

    global $dbh;
    $mdb2 = GetMDB2();
    global $ModuleID;
    $ModuleID = $pModuleID;

    print "generate-module: starting to build module $ModuleID...\n";

    //global foreign modules array - metastructure info
    global $foreignModules;
    unset($foreignModules);
    $foreignModules = array();

    //create the Module class
    global $module;
    $module = new Module($ModuleID);

    global $WriteMergedXML;
    if( $WriteMergedXML ){
        if( defined('MERGED_XML_PATH') ){
            $mergedXMLPath = MERGED_XML_PATH;
        }else{
            $mergedXMLPath = XML_PATH . '/merged';
        }
        if( !file_exists($mergedXMLPath) ){
            if( !mkdir($mergedXMLPath, 0755) ){
                trigger_error("Could not create directory $mergedXMLPath.", E_USER_ERROR);
            }
        }
        if( $module->xmlMerged ){
            $merged_xml = $module->_map->getContent();
            $filename = $mergedXMLPath.'/'.$ModuleID.'ModuleDef_merged.xml';
            $res = file_put_contents($filename, $merged_xml);
            if( false === $res ){
                trigger_error("Could not write file $filename.", E_USER_ERROR);
            }
        }
    }

    global $ExportMasterData;

    //remove obsolete SQL Change file
    $sql_change_file = GEN_LOG_PATH . '/'.$ModuleID.'_dbChanges.sql';
    if(file_exists($sql_change_file)){
        unlink($sql_change_file);
    }

    //checks if the module table exists
    if ($module->checkTableExists($ModuleID)){
        print "generate-module: table exists: {$ModuleID}\n";
        $tableExists = true;
    } else {
        print "generate-module: table missing: {$ModuleID}\n";
        $tableExists = false;
    };

    //checks if the module log table exists
    if ($module->checkTableExists($ModuleID. "_l")){
        print "generate-module: table exists: {$ModuleID}_l\n";
        $logTableExists = true;
    } else {
        print "generate-module: table missing: {$ModuleID}_l\n";
        $logTableExists = false;
    };

    //how to pass $confirmed?

    $confirmed = false;
    $table_check = '';
    if( $tableExists ){ //table exists
		if( $module->isModuleView ){
			// do nothing
		}else{
			$table_check .= $module->checkTableStructure(false, $confirmed);
		}
    }else{ //table does not exist
        //create the table:
        $module->createTable(false);
    }

    if ($logTableExists) { //table exists
		if( $module->isModuleView ){
			// do nothing
		}else{
			$table_check .= $module->checkTableStructure(true, $confirmed);
		}
    } else { //table does not exist
        //create the audit trail table:
        $module->createTable(true);
    }


    //Check old module fields file, if it exists, and compare with newly defined module fields...
    $moduleFieldsFilePath = GENERATED_PATH.'/'.$ModuleID.'/'.$ModuleID.'_ModuleFields.gen';
    if(file_exists($moduleFieldsFilePath)){
        include $moduleFieldsFilePath; //returns $modulefields
        if(isset($modulefields) && !is_array($modulefields)){
            $modulefields = unserialize($modulefields);
        }
        $oldModulefields = $modulefields;

        //check for remote fields turned into table fields and v.v.
        foreach($module->ModuleFields as $moduleFieldName => $newModuleField){
            //find old field with same name
            if(isset($oldModulefields[$moduleFieldName])){
                $oldModuleField = $oldModulefields[$moduleFieldName];
                $newModuleFieldClass = strtolower(get_class($newModuleField));
                $oldModuleFieldClass = strtolower(get_class($oldModuleField));

                if($oldModuleFieldClass != $newModuleFieldClass){
                    trace("Changed type of field $moduleFieldName from $oldModuleFieldClass to $newModuleFieldClass");
                    switch($newModuleFieldClass){
                    case 'tablefield':
                        switch($oldModuleFieldClass){
                        case 'remotefield':
                            //check that table fields exist in the remote table
                            $mdb2->loadModule('Reverse', null, true);
                            $def = $mdb2->reverse->getTableFieldDefinition($oldModuleField->remoteModuleID, $oldModuleField->remoteModuleIDField);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                trigger_error("The field `{$oldModuleField->remoteModuleID}`.{$oldModuleField->remoteModuleIDField} does not exist. It is used by the remote field `$ModuleID`.$moduleFieldName.", E_USER_WARNING);
                                break; //leaves the case statement
                            }
                            $def = $mdb2->reverse->getTableFieldDefinition($oldModuleField->remoteModuleID, $oldModuleField->remoteRecordIDField);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                trigger_error("The field `{$oldModuleField->remoteModuleID}`.{$oldModuleField->remoteRecordIDField} does not exist. It is used by the remote field `$ModuleID`.$moduleFieldName.", E_USER_WARNING);
                                break;
                            }
                            $def = $mdb2->reverse->getTableFieldDefinition($oldModuleField->remoteModuleID, $oldModuleField->remoteField);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                trigger_error("The field `{$oldModuleField->remoteModuleID}`.{$oldModuleField->remoteField} does not exist. It is used by the remote field `$ModuleID`.$moduleFieldName.", E_USER_WARNING);
                                break;
                            }
                            if(!empty($oldModuleField->remoteDescriptorField)){
                                $def = $mdb2->reverse->getTableFieldDefinition($oldModuleField->remoteModuleID, $oldModuleField->remoteDescriptorField);
                                if(MDB2_ERROR_NOT_FOUND == $def->code){
                                    trigger_error("The field `{$oldModuleField->remoteModuleID}`.{$oldModuleField->remoteDescriptorField} does not exist. It is used by the remote field `$ModuleID`.$moduleFieldName.", E_USER_WARNING);
                                    break;
                                }
                            }

                            //check that the local table field exists
                            $def = $mdb2->reverse->getTableFieldDefinition($ModuleID, $moduleFieldName);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                trigger_error("WARNING: The field `$ModuleID`.$moduleFieldName does not exist in the table yet. It should have been created.", E_USER_WARNING);
                                break;
                            }

                            trace("Need to update records with old remote records for $moduleFieldName.");
                            $remoteModuleDefPath = GetXMLFilePath($oldModuleField->remoteModuleID.'ModuleDef.xml');
                            if(!file_exists($remoteModuleDefPath)){
                                trigger_error("The field `$ModuleID`.$moduleFieldName was changed from a RemoteField into a TableField. The data cannot be migrated because the remote module definition does not exist in the location $remoteModuleDefPath. If you do not care about this error, you can delete the generated file $moduleFieldsFilePath and regenerate $ModuleID.", E_USER_ERROR);
                                break;
                            }

                            trigger_error("INFO: The field `$ModuleID`.$moduleFieldName was changed from a RemoteField into a TableField. The data will be migrated.", E_USER_WARNING);

                            $conditions = array();
                            $selects = array();
                            $joins = array();
                            $wheres = array();

                            $remoteModulefields = GetModuleFields($oldModuleField->remoteModuleID);
                            $oldRemoteFields = array();

                            $oldRemoteFields[$oldModuleField->remoteModuleIDField] = $remoteModulefields[$oldModuleField->remoteModuleIDField];
                            $oldRemoteFields[$oldModuleField->remoteRecordIDField] = $remoteModulefields[$oldModuleField->remoteRecordIDField];
                            $oldRemoteFields[$oldModuleField->remoteField] = $remoteModulefields[$oldModuleField->remoteField];
                            $conditions[$oldModuleField->remoteModuleIDField] = $ModuleID;

                            if(!empty($oldModuleField->remoteDescriptorField)){
                                $oldRemoteFields[$oldModuleField->remoteDescriptorField] = $remoteModulefields[$oldModuleField->remoteDescriptorField];
                                $conditions[$oldModuleField->remoteDescriptorField] = $oldModuleField->remoteDescriptor;
                            }

                            $oldRemoteFields['_ModDate'] = $remoteModulefields['_ModDate'];
                            $oldRemoteFields['_ModBy'] = $remoteModulefields['_ModBy'];

                            //SQL to get old data from remote module
                            $SQL = "SELECT\n";
                            foreach($oldRemoteFields as $oldRemoteFieldsName => $oldRemoteField){
                                $select = $oldRemoteField->makeSelectDef($oldModuleField->remoteModuleID, false, false);
                                $selects[] = $select;
                                $joins = array_merge($joins, $oldRemoteField->makeJoinDef($oldModuleField->remoteModuleID));
                                if(isset($conditions[$oldRemoteFieldsName])){
                                    $wheres[$select] = $conditions[$oldRemoteFieldsName];
                                }
                            }
                            $joins = SortJoins($joins);

                            $SQL .= join(",\n   ", $selects);
                            $SQL .= "\nFROM `{$oldModuleField->remoteModuleID}`";
                            $SQL .= join("\n", $joins);

                            $whereSQL = "\nWHERE `{$oldModuleField->remoteModuleID}`._Deleted = 0";
                            foreach($wheres as $conditionSelect => $conditionValue){
                                $whereSQL .= "\n AND $conditionSelect = '$conditionValue'";
                            }
                            $SQL .= $whereSQL;
                            CheckSQL($SQL);

                            //SQL to mark old data as deleted.
                            $deleteSQL = "UPDATE `{$oldModuleField->remoteModuleID}`\n";
                            $deleteSQL .= join("\n", $joins);
                            $deleteSQL .= "SET _Deleted = 1, _ModBy = 0, _ModDate = NOW()\n";
                            $deleteSQL .= $whereSQL;
                            CheckSQL($deleteSQL);

                            trace('to do: wrap into transaction');
                            $updateSQL = "UPDATE `$ModuleID` INNER JOIN ($SQL) AS subq\n";
                            $updateSQL .= "ON (\n";
                            $updateSQL .= "`$ModuleID`.{$oldModuleField->localRecordIDField} = subq.{$oldModuleField->remoteRecordIDField}\n";
                            $updateSQL .= "\n";
                            $updateSQL .= ")\n";
                            $updateSQL .= "SET \n";
                            $updateSQL .= "`$ModuleID`.$moduleFieldName = subq.{$oldModuleField->remoteField}\n";
                            $updateSQL .= "WHERE `$ModuleID`._Deleted = 0";

                            trace($updateSQL, "update sql to migrate remote data locally");
                            $result = $mdb2->exec($updateSQL);
                            mdb2ErrorCheck($result);

                            trace('marking obsolete remote records as _Deleted');
                            $result = $mdb2->exec($deleteSQL);
                            mdb2ErrorCheck($result);
                            trace('to do: log the marking of obsolete remote records as _Deleted to log table!');

                            trace('to do: insert log records & end transaction');
                            break;
                        default:
                            //really need to do anything?
                            break;
                        }
                        break;
                    case 'remotefield':
                        switch($oldModuleFieldClass){
                        case 'tablefield':
                            //check that the old tablefield still exists in the table
                            $mdb2->loadModule('Reverse', null, true);
                            $def = $mdb2->reverse->getTableFieldDefinition($ModuleID, $moduleFieldName);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                $fieldExistsInTable = false;
                            } else {
                                mdb2ErrorCheck($def);
                                $fieldExistsInTable = true;
                            }

                            if($fieldExistsInTable){
                                trace("Need to insert remote records for $moduleFieldName.");
                                trigger_error("INFO: The field `$ModuleID`.$moduleFieldName was changed from a TableField into a RemoteField. The data will be migrated now before the table field is dropped.", E_USER_WARNING);
                            } else {
                                trigger_error("NOTICE: The field `$ModuleID`.$moduleFieldName was changed from a TableField into a RemoteField. The table field has been dropped (earlier), and we assume the data was migrated earlier.", E_USER_WARNING);
                                break; //leaves the case statement
                            }

                            //determine whether remote table needs to have fields added
                            $fixRemoteTable = false;
                            $def = $mdb2->reverse->getTableFieldDefinition($newModuleField->remoteModuleID, $newModuleField->remoteField);
                            if(MDB2_ERROR_NOT_FOUND == $def->code){
                                $fixRemoteTable = true;
                            } else {
                                $def = $mdb2->reverse->getTableFieldDefinition($newModuleField->remoteModuleID, $newModuleField->remoteRecordIDField);
                            }
                            if(!$fixRemoteTable && MDB2_ERROR_NOT_FOUND == $def->code){
                                $fixRemoteTable = true;
                            } else {
                                $def = $mdb2->reverse->getTableFieldDefinition($newModuleField->remoteModuleID, $newModuleField->remoteModuleIDField);
                            }
                            if(!$fixRemoteTable && MDB2_ERROR_NOT_FOUND == $def->code){
                                $fixRemoteTable = true;
                            } else {
                                if(!empty($newModuleField->remoteDescriptorField)){
                                    $def = $mdb2->reverse->getTableFieldDefinition($newModuleField->remoteModuleID, $newModuleField->remoteModuleIDField);
                                    if(MDB2_ERROR_NOT_FOUND == $def->code){
                                        $fixRemoteTable = true;
                                    }
                                }
                            }

                            if($fixRemoteTable){
                                $remoteModule = GetModule($newModuleField->remoteModuleID);
                                if($remoteModule->checkTableExists($newModuleField->remoteModuleID)){
                                    $remoteModule->checkTableStructure(false, false);
                                } else { //table does not exist
                                    //create the table:
                                    $remoteModule->createTable(false);
                                }
                                if($remoteModule->checkTableExists($newModuleField->remoteModuleID.'_l')){
                                    $remoteModule->checkTableStructure(true, false);
                                } else { //table does not exist
                                    //create the table:
                                    $remoteModule->createTable(true);
                                }
                            }

                            //create SELECT SQL to retrieve necessary data from local table
                            $joins = array();
                            $SQL = "SELECT\n";
                            $localRecordIDField = $oldModulefields[$newModuleField->localRecordIDField];
                            $SQL .= $localRecordIDField->makeSelectDef($ModuleID, false, false). " AS {$newModuleField->remoteRecordIDField},\n";
                            $joins = $localRecordIDField->makeJoinDef($ModuleID);

                            $localValueField = $oldModulefields[$moduleFieldName];
                            $SQL .= $localValueField->makeSelectDef($ModuleID, false, false). " AS {$newModuleField->remoteField}\n";
                            $joins = array_merge($joins, $localValueField->makeJoinDef($ModuleID));
                            $joins = SortJoins($joins);

                            $SQL .= "\nFROM `{$ModuleID}`";
                            $SQL .= join("\n", $joins);

                            $SQL .= "\nWHERE `{$ModuleID}`._Deleted = 0";

                            //get local data
                            $localData = $mdb2->queryAll($SQL);
                            mdb2ErrorCheck($localData);

                            trace($localData, 'localData with remote fieldnames');

                            //prepare related record values
                            $relatedRecordFieldValues = array(
                                $newModuleField->remoteModuleIDField => $ModuleID
                            );
                            if(!empty($newModuleField->remoteDescriptorField)){
                                $relatedRecordFieldValues[$newModuleField->remoteDescriptorField] = $newModuleField->remoteDescriptor;
                            }

                            //save with data handler
                            $dataHandler = GetDataHandler($newModuleField->remoteModuleID);
                            $dataHandler->startTransaction();

                            foreach($localData as $row){
                                $relatedRecordFieldValues[$newModuleField->remoteRecordIDField] = $row[$newModuleField->remoteRecordIDField];
                                $dataHandler->isPopulated = false; //forces re-populating the values in dataHandler
                                $dataHandler->PKFieldValues = array(); //ensures the remote record is looked up each time
                                $dataHandler->saveRowWithRelatedValues($row, $relatedRecordFieldValues);
                            }
                            $dataHandler->endTransaction();

                            break;
                        default:
                            //no need to do anything.
                            break;
                        }
                        break;
                    default:
                        trace("No preservation of data necessary for $moduleFieldName.");
                        break;
                    }
                }
            }
        }
    }


    if(false !== strpos($table_check, 'confirm')){
        die("CONFIRMATION REQUIRED: This update requires confirmation because table changes could possibly cause data loss.");
    }

    require_once CLASSES_PATH . '/moduleinfo.class.php';
    $moduleInfo = new ModuleInfo($ModuleID);
    $moduleInfo->makeGeneratedFile();


    //save table defs for table and log table as a generated file
    $newTableDef = $module->generateTableDef($createLogTable);
    SaveGeneratedFile(
        'CustomModel.php',
        $ModuleID.'_TableDef.gen',
        array('/**custom**/' => '$tableDef = unserialize(\''.escapeSerialize($newTableDef) .'\')'),
        $ModuleID
    );
    //trace($tableDef, 'generateTableDef');


    //make and save sql table create statements
    $CreateTableSQL = $module->generateCreateTableSQL('MySQL', false);
    $CreateLogTableSQL = $module->generateCreateTableSQL('MySQL', true);
    $SQL = $CreateTableSQL . "\n-- statement separator --\n". $CreateLogTableSQL;

    $CreateTableFile = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_CreateTables.sql";
    if($fp = fopen($CreateTableFile, 'w')) {
        $SQL = str_replace("\n", "\r\n", $SQL); //convert unix line-endings to windows line-endings
        if(fwrite($fp, $SQL)){
            //print no output about saving to log
        } else {
            die( "s2a: could not save to file $CreateTableFile. Please check file/folder permissions.\n" );
        }
        fclose($fp);
    } else {
        die( "s2a: could not open file $CreateTableFile. Please check file/folder permissions.\n" );
    }
	// make and save sql trigger create commands
	$CreateTriggerSQL = $module->generateCreateTriggerSQL();
	if( !empty( $CreateTriggerSQL ) ){
		$CreateTriggerSQL = str_replace("\n", "\r\n", $CreateTriggerSQL); //convert unix line-endings to windows line-endings
		$CreateTriggerFileList = S2A_FOLDER.'/install/triggers.sql';
		$CreateTriggerFile = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_CreateTrigger.sql";
		if($fp = fopen($CreateTriggerFile, 'w')) {        
			if(fwrite($fp, $CreateTriggerSQL)){
				//print no output about saving to log
			} else {
				die( "s2a: could not save to file $CreateTriggerFile. Please check file/folder permissions.\n" );
			}
			fclose($fp);
		} else {
			die( "s2a: could not open file $CreateTriggerFile. Please check file/folder permissions.\n" );
		}
		if($fp = fopen($CreateTriggerFileList, 'a')) {        
			fwrite($fp, '\. '.$CreateTriggerFile."\n");
			fclose($fp);
		} else {
			die( "s2a: could not open file $CreateTriggerFileList. Please check file/folder permissions.\n" );
		}
	}
    //important to do this also when it's empty (to reflect when ParentModuleID is removed)...
    SaveParentModuleID($ModuleID, $module->permissionParentModuleID);

    global $SaveNavigationPhrases;
    if($SaveNavigationPhrases){
        SaveNavigationPhrases();
    }

    MakeGlobalRollupDef();


    RebuildScreen('FieldDefCache');

    MakeDashBoardGrids();

makeDotGraph();

    if('pple' == $ModuleID){
        custom_ppl_GenerateSQL();
    }
    if('usr' == $ModuleID){
        custom_usr_GenerateMyScreens();
    }
    if('mtgma' == $ModuleID){
        custom_mtgma_GenerateSQL();
    }

    if($module->dataCollectionForm){
        RebuildScreen('DataCollectionForm');
    }

    //build the List Screen:
    if(count($module->getListFields()) > 0){
        RebuildScreen('List');
    }


    RebuildScreen('DataHandler');

    //serialized grids and fields for view screen and notification output...
    RebuildScreen('ViewScreenSer');

    RebuildScreen('Export');

    $screens = $module->getScreens();

    //generate report definitions
    $reportCounts = GenerateReports();
    asort($reportCounts);

    //check for list and record level reports in returned $reportCounts variable
    //any that > 0, insert a report screen appropriate to the level.
    foreach($reportCounts as $level => $count){
        if($count > 0){
            $screenName = $level.'Reports';
            switch($level){
            case 'List':
                $screenPhrase = "Reports|View available reports based on active search criteria";
                break;
            case 'Record':
                $screenPhrase = "Reports|View available reports for this record";
                break;
            default:
                break;
            }

            $reportScreen =& MakeObject(
                $ModuleID,
                $screenName,
                $level.'ReportScreen',
                $attributes = array(
                    'name' => $screenName,
                    'phrase' => $screenPhrase
                )
            );
            //pretty ugly way of tacking these objects on here
            $module->_screens[$screenName] =& $reportScreen;
        }
    }

    if(count($screens) > 0){

        //rebuild other screens
        foreach($screens as $ScreenName => $Screen){
            if(!empty($Screen->linkToModuleID)){
                print "generate-module: {$Screen->name} is a link only.\n";
            } else {
                RebuildScreen($ScreenName);
            }
        }

        //rebuild any other screens: Stats, Delete confirmation
    }

    RebuildScreen('ScreenList');
    RebuildScreen('Tabs');
	RebuildScreen('ListCtxTabs');	
	RebuildScreen('EditScreenPermissions');
    //cache the search fields
    RebuildScreen('SearchFields');

    //cache the Module Fields
    RebuildScreen('ModuleFields');

    //make a generated OwnerField SQL statement
    RebuildScreen('OwnerFieldSQL');

    //cache the loss cost calculation, if needed
    RebuildScreen('CostSeveritySQL');

    //cache info needed by the Audit screen
    RebuildScreen('Audit');

    //cache consistency condition definitions
    RebuildScreen('Consistency');

    //cache info to be used on global screens
    if(count($module->getListFields()) > 0){
        RebuildScreen('LabelSection');
        RebuildScreen('ListFields');
    }

    if($module->isGlobal){
        RebuildScreen('GlobalViewGrid');
        RebuildScreen('GlobalEditGrid');
    }
    RebuildScreen('ScreenInfo');
    RebuildScreen('RDCUpdate');

    //check whether this module is a guidance type module:
    $remoteModules = $module->getRemoteModules();
    $isGuidanceTypeModule = in_array('guit', $remoteModules);
//START
    putenv('LANGUAGE='.$language.'.UTF-8');
    setlocale(LC_ALL, $language.'.UTF-8');
	setlocale(LC_NUMERIC, 'en_US.UTF-8');
    bindtextdomain('active_agenda', LOCALE_PATH);
    textdomain('active_agenda');

    $modInfo = array(
        'Name'                   => gettext($module->Name),
        'AddNew'                 => intval(!empty($module->addNewName)),
        'AllowAdd'               => intval($module->AllowAddRecord),
        'DataForm'               => intval($module->dataCollectionForm),
        'ExtendsModule'          => intval(!empty($module->extendsModuleID)),
        'Global'                 => intval($module->isGlobal),
        'HasGlobalModules'       => intval($module->includeGlobalModules),
        'ImpPlan'                => intval($module->isImplementationPlanningModule),
        'LastParsed'             => 'ISO'.date('Y-m-d H:i:s'),
        'MasterData'             => intval($module->isMasterData),
        'ModuleListType'         => intval($module->isTypeModule),
        'OwnerField'             => $module->OwnerField,
        'ParentModuleID'         => $module->permissionParentModuleID.'_',
        'RecordDescriptionField' => $module->recordDescriptionField,
        'RecordLabelField'       => $module->recordLabelField,
        'RevisionAuthor'         => $module->revisionInfo['author'],
        'RevisionNumber'         => $module->revisionInfo['id'],
        'RevisionDate'           => $module->revisionInfo['date'],
        'GuidanceType'           => $isGuidanceTypeModule,
		'GlobalDiscussionAddress'           => $module->GlobalDiscussionAddress,
		'DefaultMenuPath'		=> $module->defaultMenuPath
    );
//END
    $dataHandler =& GetDataHandler('mod');
    $dataHandler->saveRowWithRelatedValues($modInfo, array('ModuleID' => $ModuleID));

    //check module status
    $SQL = "SELECT cod.Value FROM `mod` INNER JOIN cod ON (mod.ModuleStatusID = cod.CodeID AND cod.CodeTypeID = 272) WHERE mod.ModuleID = '{$ModuleID}'";
    $modStatusVal = $mdb2->queryOne($SQL);
    mdb2ErrorCheck($modStatusVal);
    print $SQL . "\n";
    print ("modStatusVal = $modStatusVal");

    //set module status to "tables and files", if module isn't 
    if(intval($modStatusVal) < 3){
        $SQL = "UPDATE `mod` SET ModuleStatusID = 3 WHERE ModuleID = '$ModuleID';";
        $result = $mdb2->exec($SQL);
        mdb2ErrorCheck($result);
        trace( "Successfully updated mod table with ModuleStatusID.");
    }

    //make sure SubModule tables exist
    foreach($module->SubModules as $SubModuleID => $SubModule){
        if(count($SubModule->ModuleFields) > 0){
            if ($SubModule->checkTableExists($SubModuleID)){
                print "generate-module: table exists: {$SubModuleID}\n";
                $tableExists = true;
            } else {
                print "generate-module: table missing: {$SubModuleID}\n";
                $tableExists = false;
            };

            //check if the module log table exists
            if ($SubModule->checkTableExists($SubModuleID. "_l")){
                print "generate-module: table exists: {$SubModuleID}_l\n";
                $logTableExists = true;
            } else {
                print "generate-module: table missing: {$SubModuleID}_l\n";
                $logTableExists = false;
            };

            if ($tableExists) { //table exists
                $SubModule->checkTableStructure(false);
            } else { //table does not exist
                //create the table:
                $SubModule->createTable($SubModuleID, false);
            }

            if ($logTableExists) { //table exists
                $SubModule->checkTableStructure(true);
            } else { //table does not exist
                //create the audit trail table:
                $SubModule->createTable($SubModuleID . "_l", true);
            }
        }
    }

    //generate chart definitions
    GenerateCharts();

    //moved down
    RebuildScreen('TableAliases');

    //not necessary except for data that was entered before SMC triggers
    UpdateSMCTriggers();

    //document what code types are used by this module
    $codeTypesUsed = array();
    foreach($module->ModuleFields as $mf){
        if('codefield' == strtolower(get_class($mf))){
            $codeTypesUsed[$mf->codeTypeID] = $mf->codeTypeID;
        }
    }

    if(count($codeTypesUsed)){
        //print "code types used";
        //print_r($codeTypesUsed);

        $inserts = array();

        $SQL = "DELETE FROM codtd WHERE DependencyID = '$ModuleID'";
        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);

        $SQL = "INSERT INTO codtd (CodeTypeID, DependencyID, _ModDate, _ModBy, _Deleted) VALUES ";
        foreach($codeTypesUsed AS $codeTypeID){
            $inserts[] = "($codeTypeID, '$ModuleID', NOW(), 0, 0)";
        }
        $SQL .= join(',', $inserts);

        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);
    }

    //print "ModuleFields:\n";
    //print_r($module->ModuleFields);

    //print "Primary Key Fields:\n";
    //($module->PKFields);

    global $foreignModules;
    $foreignModuleIDs = array_keys($foreignModules);

    print "generate-module: foreignModules:\n";
    //print_r($foreignModuleIDs);

    //  print "form screen:\n";
    //  print_r($module->Screens['View']);

    //first remove all existing records
    print "generate-module: removing all dependencies with $ModuleID from modd table\n";
    $SQL = "DELETE FROM modd WHERE DependencyID = '$ModuleID';\n";
    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r);

    print "generate-module: Foreign Modules:\n";
    foreach($foreignModuleIDs as $k => $key){

        //check if this foreignModule is listed in the modd table
        if(false === strpos($key, '_') && $ModuleID != $key){
            print "generate-module: inserting $key foreignModule dependency\n";
            $SQL = "INSERT INTO modd (ModuleID, DependencyID, ForeignDependency, _ModDate, _ModBy, _Deleted) 
            VALUES ('{$key}', '$ModuleID', 1, NOW(), 0, 0)";

            $r = $mdb2->exec($SQL);
            mdb2ErrorCheck($r);
        } else {
            //handle DataViews here if needed
        }
    }

    print "generate-module: getting listed foreignModules from modd table (again)\n";
    $SQL = "SELECT ModuleID FROM modd WHERE DependencyID = '$ModuleID';\n";
    //get data
    $deps = $mdb2->queryCol($SQL);
    mdb2ErrorCheck($deps);

    $fTables = array_unique($module->getRemoteModules());
    print_r($fTables);

    print "generate-module: Remote Modules:\n";
    foreach($fTables as $k => $key){

        //check if this foreignModule is listed in the modd table
        if(!in_array($key, $deps)){
            print "generate-module: inserting $key Remote Module dependency\n";
            $SQL = "INSERT INTO modd (ModuleID, DependencyID, RemoteDependency, _ModDate, _ModBy, _Deleted)
            VALUES ('{$key}', '$ModuleID', 1, NOW(), 0, 0)";

        } else {
            print "generate-module: we update $key Remote Module dependency\n";
            $SQL = "UPDATE modd SET 
                RemoteDependency = 1, 
                _ModDate = NOW()
            WHERE ModuleID = '{$key}' AND DependencyID = '$ModuleID';";

        }

        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);

        //also update the remote module entry itself
        $SQL = "UPDATE `mod` SET Remote = 1, _ModDate = NOW() WHERE ModuleID = '$key';";
        $result = $mdb2->exec($SQL);
        mdb2ErrorCheck($result);
    }

    print "generate-module: getting listed dependencies from modd table (again)\n";
    $SQL = "SELECT ModuleID FROM modd WHERE DependencyID = '$ModuleID';\n";
    //get data
    $deps = $mdb2->queryCol($SQL);
    mdb2ErrorCheck($deps);

    foreach($module->SubModules as $key => $SubModule){
        //check if this foreignModule is listed in the modd table
        if(!in_array($key, $deps)){
            if(false === strpos($key, '_')){
                print "generate-module: inserting $key subModule dependency\n";
                $SQL = "INSERT INTO modd (ModuleID, DependencyID, SubModDependency, _ModDate, _ModBy, _Deleted)
                VALUES ('{$key}', '$ModuleID', 1, NOW(), 0, 0)";
            }
        } else {
            print "generate-module: we update $key subModule dependency\n";
            $SQL = "UPDATE modd SET 
                SubModDependency = 1, 
                _ModDate = NOW()
            WHERE ModuleID = '{$key}' AND DependencyID = '$ModuleID';";

        }
        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);
    }

    MakeRDCTriggers();

    //print "documentation:\n";
    //print_r($module->Documentation);
    $sortOrder = 10;

    //get support document ID
    $SQL = "SELECT SupportDocumentID FROM spt WHERE ModuleID = '{$module->ModuleID}' ORDER BY SupportDocumentID LIMIT 1;";

    $recordID = $mdb2->queryOne($SQL);
    mdb2ErrorCheck($recordID);

    if(empty($recordID)){
        $SQL = "INSERT INTO spt (ModuleID, _ModDate, _ModBy, _Deleted) VALUES ('{$module->ModuleID}', NOW(), 0, 0)";
        $r = $mdb2->exec($SQL);
        mdb2ErrorCheck($r);

        $SQL = "SELECT LAST_INSERT_ID();";
        $recordID = $mdb2->queryOne($SQL);
        mdb2ErrorCheck($recordID);
    }

    //handle support documentation
    $documentationSections = $module->getDocumentation();
    foreach($documentationSections as $key => $docArray){
        //if(!empty($docArray[1])){
        if(is_array($docArray)){
            $title = $dbh->quote($docArray[0]);
            $text = $dbh->quote(trim($docArray[1]));

            //check if the section exists
            $SQL = "SELECT SupportDocumentSectionID, Protected FROM spts WHERE
SupportDocumentID = $recordID AND SectionID = '$key' ORDER BY
SupportDocumentSectionID LIMIT 1;";

            $r = $mdb2->queryRow($SQL);
            mdb2ErrorCheck($r);
//print "Existing section:\n";
//print_r($r);

            if(!empty($r['SupportDocumentSectionID'])){
//print "Section $title exists\n";
                if(in_array(intval($r['Protected']), array(-1, 0) )){
//print "Section $title is not protected\n";
                    $SQL = "UPDATE spts SET Title = {$title}, SectionText =
{$text}, SortOrder = $sortOrder, SectionID = '$key', _Deleted = 0 WHERE
SupportDocumentSectionID = {$r['SupportDocumentSectionID']}";
                    $r = $mdb2->exec($SQL);
                    mdb2ErrorCheck($r);
                }
            } else {
//print "Section $title does not exist\n";

                //insert if it dowsn't exist
                $SQL = "INSERT INTO spts (SupportDocumentID, Title, SectionText,
SortOrder, SectionID, _ModDate, _ModBy, _Deleted) VALUES ($recordID, {$title}, {$text}, $sortOrder, '$key', NOW(), 0, 0)";
                $r = $mdb2->exec($SQL);
                mdb2ErrorCheck($r);
            }
        }
        $sortOrder = $sortOrder + 10;
    }


    $end_time = getMicroTime();

    $duration = round($end_time - $start_time, 2);
    $SQL = "INSERT INTO modgt (ModuleID, Duration, _ModDate, _ModBy, _Deleted) VALUES ('$pModuleID', '$duration', NOW(), 0, 0);";
    $r = $mdb2->exec($SQL);
    mdb2ErrorCheck($r, false);

    global $GenInfo;
    if($GenInfo){
        $gen_info_msg = 'mem '. round(memory_get_usage() / 1024 / 1024, 2);
        $gen_info_msg .= ' MB';

        $gen_info_msg = " ($gen_info_msg)";
    }
    //ob_clean();
    print "generate-module: Created module $pModuleID in $duration s.$gen_info_msg\n";

} //end function CreateModule




function MakeRDCTriggers(){
    global $module;
    $rdFieldName = $module->recordDescriptionField;
    $debug_prefix = debug_indent("generate-module-MakeRDCTriggers() $rdFieldName:");

    if(!empty($rdFieldName)){
        if(array_key_exists($rdFieldName, $module->ModuleFields)){

            $rdField = $module->ModuleFields[$rdFieldName];

            //$deps = $rdField->getFieldDependencies('');
            $deps = $rdField->getDependentFields();
            indent_print_r($deps);

            if(count($deps) > 0){
                //foreach dependent field (ignore local tablefields), call $moduleField->makeRDCTrigger()
                foreach($deps as $dep){
                    if($dep['moduleID'] == $module->ModuleID){
                        $moduleField = $module->ModuleFields[$dep['name']];
                    } else {
                        $moduleField = GetModuleField($dep['moduleID'], $dep['name']);
                    }
                   // $moduleField->makeRDCTrigger($module->ModuleID, $rdField->makeRDCCallerDef());
                     $moduleField->makeRDCTrigger($module->ModuleID);
                }
            } else {
                print "$debug_prefix Warning: no dependent fields for RecordDescription\n";
            }

        } else {
            die("$debug_prefix no RecordDescription field called $rdFieldName");
        }
    }
    debug_unindent();
}





//rationalizing the screen rebuilding business into a function:
function RebuildScreen($ScreenName){

    print "\n\n\n";
    print ">>> ################################################################### >>>\n";
    print "   generate-module->RebuildScreen: Rebuilding the $ScreenName screen...\n";
    print ">>> ||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||| >>>\n";
    print "\n\n";

    global $module;
    global $ModuleID;
    global $parsingModuleID;
    $parsingModuleID = $ModuleID;

    $cancel = false;

    //set up some parameters depending on $ScreenName
    switch($ScreenName){
    case 'Audit':
        $modelFileName = "AuditModel.php";
        $pkField = $module->PKFields[0];
        $codeArray = Array('/**recordIDField**/' => $pkField);
        $createFileName = "{$ModuleID}_Audit.gen";
        break;
    case 'Consistency':
        if(count($module->consistencyConditions) == 0){
            $cancel = true;
            break;
        }

        $modelFileName = 'CustomModel.php';
        $consistencies = array();
        $targetSQLs = array();
        $joins = array();
        $condition_phrase_strings = array();
        foreach($module->consistencyConditions as $ccix => $consistencyCondition){
            $consistencies[$ccix] = $consistencyCondition->makeTriggerSQL($ccix);
            $targetSQLs['Condition_'.$ccix] = $consistencyCondition->makeTargetSQL($ccix);
            $condition_phrase_strings[] = $consistencyCondition->makePhraseString($ccix);
        }

        //creates the trigger SQL
        $selects = array();
        $triggerSQL = "SELECT\n";
        foreach($consistencies as $ccix => $consistency){
            $selects[] = $consistency['triggerExpr'];
            $joins = array_merge($joins, $consistency['joins']);
        }
        $triggerSQL .= join(",\n", $selects);
        $triggerSQL .= "\nFROM `{$ModuleID}`\n";
        $joins = SortJoins($joins);
        if(count($joins) > 0){
            $triggerSQL .= join("\n", $joins);
        }
        trace($triggerSQL, "ConsistencySQL");
        $consistencySQLs['triggerSQL'] = $triggerSQL;
        $consistencySQLs['targets'] = $targetSQLs;

        $phrases_string = "\$phrases = array(\n".join(",\n", $condition_phrase_strings).");\n\n";

        $codeArray['/**custom**/'] = $phrases_string.'$consistencySQLs = unserialize(\''.escapeSerialize($consistencySQLs) .'\')';
        $createFileName = $ModuleID.'_Consistency.gen';
        break;
    case 'CostSeveritySQL':
        if(in_array('rskxa', $module->getRemoteModules())){
            if(isset($module->ModuleFields['CostSeverityValue'])){
                $csvField = $module->ModuleFields['CostSeverityValue'];
                if(isset($module->ModuleFields['TotalCost'])){
                    $tcField = $module->ModuleFields['TotalCost'];
                } else {
                    trigger_error("The module '$ModuleID' must have a field named TotalCost.", E_USER_WARNING);
                }

                global $SQLBaseModuleID;
                $SQLBaseModuleID = $ModuleID;
                $selectDefs = array();
                $selectDefs[] = $csvField->makeSelectDef($ModuleID);
                $joinDefs = $csvField->makeJoinDef($ModuleID);
                if(isset($tcField)){
                    $selectDefs[] = $tcField->makeSelectDef($ModuleID);
                    $joinDefs = array_merge($joinDefs, $csvField->makeJoinDef($ModuleID));
                } else {
                    $selectDefs[] = '0 AS TotalCost';
                }

                $joinDefs = SortJoins($joinDefs);
                $PKField = end($module->PKFields);

                $SQL = "SELECT ";
                $SQL .= join(", ", $selectDefs);
                $SQL .= " FROM `$ModuleID`\n";
                $SQL .= join("\n", $joinDefs);
                $SQL .= "WHERE `$ModuleID`.$PKField = '/**RecordID**/'";
                print $SQL;

            } else {
                //make a dummy trigger
                $SQL = 'SELECT 5';

                trigger_error("The module $ModuleID uses a RemoteField to rskxa, and should have a field named CostSeverityValue.", E_USER_WARNING);
            }

            $modelFileName = 'CustomModel.php';
            $createFileName = $ModuleID.'_CostSeveritySQL.gen';

            $codeArray['/**custom**/'] = '$csSQL = \''.addslashes($SQL).'\'';
        } else {
            return true;
        }
        break;
    case 'DataCollectionForm':
        $dataForms = $module->getDataCollectionForms();
        $modelFileName = 'CustomModel.php';
        $createFileName = $ModuleID.'_DataCollection.gen';
        $codeArray['/**custom**/'] = '$dataCollection = unserialize(\''.escapeSerialize($dataForms) .'\')';
        break;
    case 'DataHandler':
        $modelFileName = 'CustomModel.php';
        $createFileName = $ModuleID.'_DataHandler.gen';
        require_once CLASSES_PATH . '/data_handler.class.php';
        $dataHandler = new DataHandler($ModuleID);
        $codeArray['/**custom**/'] = '$dataHandler = unserialize(\''.escapeSerialize($dataHandler) .'\')';
        break;
    case 'Export':
        $modelFileName = 'ExportModel.php';
        $codeArray = $module->generateExport();
        $createFileName = "{$ModuleID}_Export.gen";

        break;
    case 'FieldDefCache':

        $modelFileName = 'CustomModel.php';
        $createFileName = $ModuleID.'_FieldDefCache.gen';

        global $SQLBaseModuleID;
        $SQLBaseModuleID = $ModuleID;

        $mFieldDefs = array();

        foreach($module->ModuleFields as $modulefield_name => $modulefield){
            $field_def = array(
                $modulefield->getQualifiedName($ModuleID),
                $modulefield->makeSelectDef($ModuleID, true),
                $modulefield->makeJoinDef($ModuleID)
            );
            $mFieldDefs[$modulefield_name] = $field_def;
        }

        $codeArray['/**custom**/'] = '$gFieldDefs[\''.$ModuleID.'\'] = unserialize(\''.escapeSerialize($mFieldDefs) .'\')';

        break;
    case 'GlobalEditGrid':
        $moduleMap = $module->_map;

        $export_matches = $moduleMap->selectElements('Exports');

        if(!empty($export_matches[0])){
            $matches = $export_matches[0]->selectElements('EditGrid');

            //if(count($matches[0]) == 0){
            if(empty($matches[0])){
                $matches = $export_matches[0]->selectElements('UploadGrid');
            }

            if(!empty($matches)){
                //build and serialize the EditGrid
                $editGrid_element = $matches[0];
                $editGrid_element->attributes['isGlobalEditGrid'] = 1; //add a special attribute
                switch($module->ModuleID){
                case 'modnr':
                case 'moddr':
                    //grids that apply to module but not a record in the module
                    $editGrid_element->attributes['hasNoParentRecordID'] = 1; //add another special attribute
                    break;
                default:
                    break;
                }

                $editGrid = &$editGrid_element->createObject($ModuleID);
                $editGrid->number = 1;
                $editGrid->listSQL = $module->generateListSQL($editGrid);
                $editGrid->insertSQL = $module->generateInsertSQL($editGrid, false, 'replace');
                $editGrid->updateSQL = $module->generateUpdateSQL($editGrid, 'replace');
                $editGrid->deleteSQL = $module->generateDeleteSQL($editGrid, 'replace');
                $editGrid->logSQL = $module->generateInsertSQL($editGrid, true, 'replace');
                $editGrid->prepRemoteFields();

                $codeArray = array(
                    '/**EditGrid**/' => escapeSerialize($editGrid),
                    '/**plural_record_name**/' => $module->PluralRecordName
                    );
                $modelFileName = "GlobalEditGridModel.php";
                $createFileName = "{$ModuleID}_GlobalEditGrid.gen";
            } else {
                $cancel = true;
            }
        }
        break;
    case 'GlobalViewGrid':
        $moduleMap = $module->_map;
        $export_matches = $moduleMap->selectElements('Exports');

        if(!empty($export_matches[0])){
            $matches = $export_matches[0]->selectElements('ViewGrid');

            if( !empty($matches) ){
                //build and serialize the grid
                $grid_element = $matches[0];
                $grid_element->attributes['isGlobalGrid'] = 1; //add a special attribute
                switch($module->ModuleID){
                case 'modnr':
                case 'moddr':
                    //grids that apply to module but not a record in the module
                    $grid_element->attributes['hasNoParentRecordID'] = 1; //add another special attribute
                    break;
                case 'res':
                    $grid_element->attributes['isGlobalGridWithConditions'] = 1; //add another special attribute
                    break;
                default:
                    break;
                }
                $grid = &$grid_element->createObject($ModuleID);
                $grid->number = 1;
                $grid->listSQL = $module->generateListSQL($grid);

                $codeArray = array(
                    '/**Grid**/' => escapeSerialize($grid)
                    );

                $modelFileName = "GlobalViewGridModel.php";
                $createFileName = "{$ModuleID}_GlobalViewGrid.gen";
            }
        }
        break;
    case 'LabelSection':
        $modelFileName = "LabelModel.php";

        $phrases = array();
        $moduleFields = $module->ModuleFields;
        $labelFields = array();

        $summaryfields_element = $module->_map->selectFirstElement('RecordSummaryFields');
        $lf = array();
        if(!empty($summaryfields_element) && count($summaryfields_element->c) > 0){
            foreach($summaryfields_element->c as $summaryfield_element){
                $lf[$summaryfield_element->name] = $summaryfield_element->createObject($ModuleID);
            }
        } else {
            $lf = $module->getListFields();
        }

        foreach($lf as $name => $field){
            $moduleField = $module->ModuleFields[$fieldname];
            $phrases[$name] = $moduleField->phrase;
            if('listfield' == strtolower(get_class($field))){
                $viewField = MakeObject($ModuleID, $name, 'ViewField', array());
                if(!empty($field->linkField)){
                    $viewField->linkField = $field->linkField;
                }
                $labelFields[$name] = $viewField;
            } else {
                $labelFields[$name] = $field;
            }
        }

        if(!empty($module->recordLabelField)){
            if(!isset($labelFields[$module->recordLabelField])){
                $labelFields[$module->recordLabelField] = MakeObject($ModuleID, $module->recordLabelField, 'InvisibleField');
            }
        }

        $labelSQL = $module->generateGetSQL($labelFields);

        //generate $fields, $phrases, screenTitle, $labelSQL
        $codeArray = array(
            '/**LabelFields**/' => escapeSerialize($labelFields),
            '/**LabelPhrases**/' => escapeSerialize($phrases),
            '/**singular_record_name**/' => $module->SingularRecordName,
            '/**GetSQL**/' => $labelSQL,
            '/**recordLabelField**/' => $module->recordLabelField
        );
        $createFileName = "{$ModuleID}_LabelSection.gen";
        break;
    case 'ListFields';
        $modelFileName = "ListFieldsModel.php";
        $listFields = $module->getListFields();
        $linkFields = array();
        $fieldHeaders = array();
        $fieldTypes = array();
        $fieldAlign = array();

        $onFirstField = true;
        foreach($listFields as $fieldname => $field){
            $moduleField = $module->ModuleFields[$fieldname];
            $fieldTypes[$fieldname] = $moduleField->getDataType();
            if(empty($field->phrase)){
                $fieldHeaders[$fieldname] = $moduleField->phrase;
            } else {
                $fieldHeaders[$fieldname] = $field->phrase;
            }
            if(!empty($field->linkField)){
                $linkFields[$fieldname] = $field->linkField;
            }
            if($field->isVisible()){
                if(empty($field->listColAlign)){
                    switch($fieldTypes[$fieldname]){
                    case 'int':
                    case 'tinyint':
                    case 'float':
                    case 'money':
                        $fieldAlign[$fieldname] = 'right';
                        break;
                    case 'date':
                    case 'time':
                    case 'datetime':
                    case 'bool';
                        $fieldAlign[$fieldname] = 'center';
                        break;
                    default:
                        $fieldAlign[$fieldname] = 'left';
                        break;
                    }
                } else {
                    $fieldAlign[$fieldname] = $field->listColAlign;
                }
            } else {
                 $fieldAlign[$fieldname] = 'hide';
            }
            if($onFirstField){
                $onFirstField = false;
                $fieldAlign[$fieldname] = 'center';
            }
        }

        $ar_FieldHeaders = array();
        foreach($fieldHeaders as $fieldName => $fieldHeader){
            $ar_FieldHeaders[] = "'$fieldName' => gettext(\"$fieldHeader\")";
        }
        $str_FieldHeaders = "array(\n        ";
        $str_FieldHeaders .= join(",\n        ", $ar_FieldHeaders);
        $str_FieldHeaders .= "\n    );\n";

        $codeArray = array(
            '/**FieldHeaders**/' => $str_FieldHeaders,
            '/**FieldTypes**/'      => escapeSerialize($fieldTypes),
            '/**ListFields**/'      => escapeSerialize(array_keys($listFields)),
            '/**LinkFields**/'      => escapeSerialize($linkFields),
            '/**FieldAlignments**/' => escapeSerialize($fieldAlign)
        );
        $createFileName = "{$ModuleID}_ListFields.gen";
        break;
    case 'ModuleFields':
        $modelFileName = "ModuleFieldsModel.php";
        $t_mfs = array();

        global $SQLBaseModuleID;
        $SQLBaseModuleID = $ModuleID;

        foreach($module->ModuleFields as $t_mf){
            switch(strtolower(get_class($t_mf))){
                case 'tablefield':
                    break;
                case 'foreignfield':
                case 'codefield':
                    $t_mf->foreignTableAlias = GetTableAlias($t_mf);
                    print "RebuildScreen:ModuleFields... foreignTableAlias {$t_mf->foreignTableAlias}.{$t_mf->name}\n";
                    break;
                case 'remotefield':
                    $t_mf->remoteTableAlias = GetTableAlias($t_mf);
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
                    print_r($t_mf);
                    die('createModule: unknown module field type');
            }
            $t_mfs[$t_mf->name] = $t_mf;
        }

        $ser_mfs = "";
        $ser_mfs .= str_replace("'", "\\'",
            str_replace("\\", "\\\\",
                serialize($t_mfs)
            )
        );
        $ser_mfs .= "";
        $codeArray = Array('/**ModuleFields**/' => $ser_mfs);
        $createFileName = "{$ModuleID}_ModuleFields.gen";
        break;
    case 'OwnerFieldSQL':
        $ownerFieldName = $module->OwnerField;
        $pkFieldName = end($module->PKFields);

        if(empty($ownerFieldName)){
            $cancel = true;
        } else {
            $selectArr = MakeSelectStatement(array($ownerFieldName => true), $ModuleID);

            $ownerFieldSQL = $selectArr[0];
            $ownerFieldSQL .= "\nWHERE `$ModuleID`.$pkFieldName = '/**RecordID**/'";

            $modelFileName = 'CustomModel.php';
            $createFileName = $ModuleID.'_OwnerFieldSQL.gen';
            $codeArray['/**custom**/'] = '$ownerFieldSQL = unserialize(\''.escapeSerialize($ownerFieldSQL) .'\')';
        }
        break;
    case 'RDCUpdate':

        $pkFieldName = end($module->PKFields);
        $rdFieldName = $module->recordDescriptionField;
        $ooFieldName = $module->OwnerField;
        $mfs = GetModuleFields($ModuleID);
        $joinDefs = array();
        $ooInsertFieldSQL = '';
        $ooInsertValueSQL = '';
        $ooUpdateSQL = '';

        global $SQLBaseModuleID;
        $SQLBaseModuleID = $ModuleID;  //needed by [ModuleField]->makeJoinDef()

        if(empty($rdFieldName)){
            $rdFieldName = 'RecordDescription';
            if(!array_key_exists('RecordDescription', $mfs)){
                print "RDCUpdate: No RecordDescription field";
                return true;
            }
        } else {
            if(!array_key_exists($rdFieldName, $mfs)){
                die( "RDCUpdate: RecordDescription field '$rdField' does not exist in ModuleFields." );
                return false;
            }
        }

        if(!empty($ooFieldName)){
            if(!array_key_exists($ooFieldName, $mfs)){
                die( "RDCUpdate: Owner Organization field '$ooFieldName' does not exist in ModuleFields." );
                return false;
            }
            $ooField = $mfs[$ooFieldName];
            $ooFieldSelect = $ooField->makeSelectDef($ModuleID, false);
            $joinDefs = array_merge($joinDefs, $ooField->makeJoinDef($ModuleID));

            $ooInsertFieldSQL = ", OrganizationID";
            $ooInsertValueSQL = ",\n$ooFieldSelect";
            $ooUpdateSQL = ",\nrdc.OrganizationID = $ooFieldSelect";
        }

        $rdField = $mfs[$rdFieldName];

        print "$ModuleID RDField:\n";
        print_r($rdField);

        ob_start(); //suppress the printout from these
        $select_sql = $rdField->makeSelectDef($ModuleID, false);
        $joinDefs = array_merge($joinDefs, $rdField->makeJoinDef($ModuleID));
        $joinDefs = SortJoins($joinDefs);
        ob_end_clean();

        print "RDCUpdate: created the following SELECT definition:\n";
        print "   $select_sql\n";

        print "RDCUpdate: created the following join definition:\n";
        print_r ($joinDefs);

        $RDCinsertSQL = "INSERT IGNORE INTO `rdc` (ModuleID, RecordID, Value$ooInsertFieldSQL, _ModDate, _ModBy, _Deleted, OwnedBy, WorkGroupID)\n";
        $RDCinsertSQL .= "SELECT \n";
        $RDCinsertSQL .= "   '$ModuleID' AS ModuleID,\n";
        $RDCinsertSQL .= "   `$ModuleID`.{$pkFieldName} AS RecordID,\n";
        $RDCinsertSQL .= "   $select_sql AS Value$ooInsertValueSQL,\n";
        $RDCinsertSQL .= "    NOW() AS _ModDate,\n";
        $RDCinsertSQL .= "    0 AS _ModBy,\n";
        $RDCinsertSQL .= "    0 AS _Deletetd,\n";
		$RDCinsertSQL .= "   `$ModuleID`._OwnedBy AS OwnedBy,\n";
		$RDCinsertSQL .= "   `$ModuleID`._WorkGroupID AS WorkGroupID\n";
        $RDCinsertSQL .= "FROM \n   `$ModuleID`\n";
        $RDCinsertSQL .= "LEFT OUTER JOIN `rdc` ON (`$ModuleID`.{$pkFieldName} = `rdc`.RecordID AND `rdc`.ModuleID = '$ModuleID')\n";
        foreach($joinDefs as $joinDef){
            $RDCinsertSQL .= "   $joinDef\n";
        }

        $RDCinsertSQL .= "WHERE `$ModuleID`.{$pkFieldName} IN ([*insertIDs*])";

        print "RDCUpdate: created the following INSERT SQL statement:\n";
        print "$RDCinsertSQL\n";

        $RDCupdateSQL = "UPDATE `rdc`\n";
        $RDCupdateSQL .= "INNER JOIN `$ModuleID` ON (`rdc`.RecordID = `$ModuleID`.{$pkFieldName} AND `rdc`.ModuleID = '$ModuleID')\n";
        foreach($joinDefs as $joinDef){
            $RDCupdateSQL .= "   $joinDef\n";
        }
        $RDCupdateSQL .= "SET\n";
        $RDCupdateSQL .= "   `rdc`.Value = {$select_sql}{$ooUpdateSQL},\n";
        $RDCupdateSQL .= "   `rdc`._ModDate = NOW(),\n";
		$RDCupdateSQL .= "   `rdc`.OwnedBy = `$ModuleID`._OwnedBy,\n";
		$RDCupdateSQL .= "   `rdc`.WorkGroupID = `$ModuleID`._WorkGroupID\n";
        $RDCupdateSQL .= "WHERE\n";
        $RDCupdateSQL .= "   `rdc`.ModuleID = '$ModuleID'\n";

        $codeArray = array(
            '/**RDCinsert**/' => escapeSerialize($RDCinsertSQL),
            '/**RDCupdate**/' => escapeSerialize($RDCupdateSQL)
        );
        $modelFileName = "RDCUpdateModel.php";
        $createFileName = "{$ModuleID}_RDCUpdate.gen";
        break;
    case 'ScreenInfo':  //used by the Documentation viewer
        $content = '';

        print "ScreenInfo: $ModuleID\n";

        $moduleMap = $module->_map;

        //look for the Screens section
        $screens_element = $moduleMap->selectFirstElement('Screens');
        $n_modulefields = $moduleMap->selectChildrenOfFirst('ModuleFields');

        //make an array of modulefield elements by name rather than numerically indexed
        $modulefields = array();
        foreach($n_modulefields as $modulefield_element){
            $modulefields[$modulefield_element->name] = $modulefield_element;
        }

        if(!empty($screens_element)){
            include_once CLASSES_PATH . '/metadoc.class.php';
            $screen_elements = $screens_element->selectElements('EditScreen');
            foreach($screen_elements as $screen_element){
                $screen_doc = $screen_element->createDoc($ModuleID);
                //print_r($screen_doc);
                $content .=  $screen_doc->getContent();
            }
        }

        $codeArray = array(
            '/**editScreens**/' => escapeSerialize($content)
        );

        $modelFileName = "ScreenInfoModel.php";
        $createFileName = "{$ModuleID}_ScreenInfo.gen";
        break;
    case 'ScreenList':
        $screenList = array();
        $screens = $module->getScreens();
        if (count($screens) > 0){
            //detect screens, save a list of them and their types
            foreach($screens as $screenName => $screen){
                if(!empty($Screen->linkToModuleID)){
                    print "generate-module: {$Screen->name} is a link only.\n";
                } else {
                    $screenList[$screenName] = strtolower(get_class($screen));
                }
            }
        }
        $modelFileName = 'CustomModel.php';
        $createFileName = $ModuleID.'_ScreenList.gen';
        $codeArray['/**custom**/'] = '$screenList = unserialize(\''.escapeSerialize($screenList) .'\')';
        break;
    case 'SearchFields':  //to be used by the Dashboard item setup
        $moduleMap = $module->_map;
        $screens_element = $moduleMap->selectFirstElement('Screens');

        if(empty($screens_element)){
            $cancel = true;
            break;
        }
        $screen_element = $screens_element->selectFirstElement('SearchScreen');
        if(empty($screen_element)){
            $cancel = true;
        } else {
            $searchFields = array();

            //create all search fields
            if(count($screen_element->c) == 0){
                $cancel = true;
            } else {
                foreach($screen_element->c as $field_element){
                    $field_name = $field_element->name;
                    $searchFields[$field_name] = $field_element->createObject($ModuleID);
                }
                $codeArray = array(
                    '/**searchFields**/' => escapeSerialize($searchFields)
                );
                $modelFileName = "SearchFieldsModel.php";
                $createFileName = "{$ModuleID}_SearchFields.gen";
            }
        }
        break;
    case 'TableAliases':
        global $gTableAliasCached;
        global $gTableAliasParents;
        $modelFileName = "TableAliasesModel.php";
        $codeArray = Array(
            '/**TableAliases**/' => escapeSerialize($gTableAliasCached[$ModuleID]),
            '/**TableAliasParents**/' => escapeSerialize($gTableAliasParents[$ModuleID])
        );
        $createFileName = "{$ModuleID}_TableAliases.gen";
        break;
    case 'Tabs':
        $modelFileName = "TabsModel.php";
        $codeArray = array(
            '/**Tabs**/' => $module->generateTabs('')
        );
        $createFileName = "{$ModuleID}_Tabs.gen";
        break;
	 case 'ListCtxTabs':
        $modelFileName = "TabsModel.php";
        $codeArray = array(
            '/**Tabs**/' => $module->generateTabs('ListCtxTabs')
        );
        $createFileName = "{$ModuleID}_ListCtxTabs.gen";
        break;
	 case 'EditScreenPermissions':
        $modelFileName = "TabsModel.php";
        $codeArray = array(
            '/**Tabs**/' => $module->generateTabs('EditScreenPermissions')
        );
        $createFileName = "{$ModuleID}_EditScreenPermissions.gen";
        break;
    case 'ViewScreenSer':
        $modelFileName = "ViewSerModel.php";
        $codeArray = $module->generateViewScreenSections();
        if(empty($codeArray)){
            $codeArray = array();
        }
        $createFileName = "{$ModuleID}_ViewSer.gen";
        break;
    default:
        print "generate-module: Building Screen $ScreenName\n";
        $Screen = $module->getScreen($ScreenName); //rm &
        $makeCacheFile = true;
        if (!empty($Screen)){
            if($codeArray = $Screen->build()){
                $modelFileName = $Screen->templateFieldName;
                $createFileName = $Screen->genFileName;
            } else {
                return true;
            }
        }
    }

    if(!$cancel){
        SaveGeneratedFile($modelFileName, $createFileName, $codeArray, $ModuleID);
    }

    print "\n";
    print "<<< ||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||| <<<\n";
    print "   generate-module->RebuildScreen: Finished the $ScreenName screen...\n";
    print "<<< ################################################################### <<<\n";
    print "\n";
}


function generateCharts(){
    global $module;
    global $dbh;
    $modelFileName = "ChartModel.php";
    $chart_elements = $module->_map->selectChildrenOfFirst('Charts');

    $SQL = "DELETE FROM modch WHERE ModuleID = '{$module->ModuleID}'";
    $r = $dbh->query($SQL);
    dbErrorCheck($r);

    if(count($chart_elements)>0){
        require_once CLASSES_PATH . '/chart.class.php';
        $added_charts = array();
        foreach($chart_elements as $chart_element){
            if(in_array($chart_element->name, $added_charts)){
                die("Module {$module->ModuleID} cannot have more than one chart named {$chart_element->name}.");
            }

            $chart_obj = $chart_element->createObject($module->ModuleID);

            //serialize each chart to a separate file, by moduleID and name
            $createFileName = "{$module->ModuleID}_{$chart_element->name}_Chart.gen";

            SaveGeneratedFile($modelFileName, $createFileName, array('/**Chart**/' => escapeSerialize($chart_obj)), $module->ModuleID);

            //save to database, modch table
            $SQL = "INSERT INTO modch (ModuleID, Name, Title, Type, _ModDate, _ModBy, _Deleted) VALUES ('{$module->ModuleID}', '{$chart_element->name}', '{$chart_element->attributes['title']}', '".$chart_obj->getDisplayType()."', NOW(), 0, 0)";

            $r = $dbh->query($SQL);
            dbErrorCheck($r);

            unset($chart_obj);
            $added_charts[] = $chart_element->name;
        }
    }
}


function GenerateReports(){
    $debug_prefix = debug_indent("GenerateReports(): ");
    global $module;
    global $dbh;

    $reportCounts = array('List' => 0, 'Record'=>0);

    $SQL = "DELETE FROM modrp WHERE ModuleID = '{$module->ModuleID}'";
    $r = $dbh->query($SQL);
    dbErrorCheck($r);

    //looks for report data structure defs in default folder first
    $indexed_reportDefPaths = array();
    $reportDefPaths = glob(XML_PATH ."/{$module->ModuleID}_ReportDef_*.xml");
    if(count($reportDefPaths) > 0){
        foreach($reportDefPaths as $reportDefPath){
            $indexed_reportDefPaths[basename($reportDefPath)] = $reportDefPath;
        }
    }

    //then overrides with custom reports by the same file name
    if(defined('CUSTOM_XML_PATH')){
        $reportDefPaths = glob(CUSTOM_XML_PATH ."/{$module->ModuleID}_ReportDef_*.xml");
        if(count($reportDefPaths) > 0){
            foreach($reportDefPaths as $reportDefPath){
                $indexed_reportDefPaths[basename($reportDefPath)] = $reportDefPath;
            }
        }
    }

    if(count($indexed_reportDefPaths) > 0){

        include_once CLASSES_PATH . '/report_map.class.php';
        include_once CLASSES_PATH . '/report.class.php';

        foreach($indexed_reportDefPaths as $reportDefFile){
            //  map the file
            $reportMap = new ReportMap($reportDefFile);

            //create one or more ReportDef objects of the file
            $reports = $reportMap->generateReports();

            foreach($reports as $report){
                //  serialize the ReportDef object to a generated file
                SaveGeneratedFile('CustomModel.php', "{$module->ModuleID}_Report_{$report->name}.gen", array('/**custom**/' => '$report = unserialize(\''.escapeSerialize($report).'\')'), $module->ModuleID);

                foreach($report->reportLocations as $level => $group){
                    //save a reference to the database 
                    $SQL = "INSERT INTO modrp (ModuleID, Name, Title, LocationLevel, LocationGroup, Format, _ModDate, _ModBy, _Deleted) VALUES ('{$module->ModuleID}', '{$report->name}', '{$report->title}', '$level', '$group', '{$report->displayFormat}', NOW(), 0, 0)";

                    $r = $dbh->query($SQL);
                    dbErrorCheck($r);

                    $reportCounts[$level]++;
                }
            }
        }
    } else {
        print "$debug_prefix No reports to be generated.";
    }

    //insert worksheet report links for Type modules
    if($module->isTypeModule){
        $SQL = "INSERT INTO modrp (ModuleID, Name, Title, LocationLevel, LocationGroup, Format, _ModDate, _ModBy, _Deleted) VALUES ('{$module->ModuleID}', 'typeWorksheet', 'Empty Worksheet', 'List', 'Main', 'listWorksheet', NOW(), 0, 0)";

        $r = $dbh->query($SQL);
        dbErrorCheck($r);

        $reportCounts['List']++;
    }
    debug_unindent();

    return $reportCounts;
}


/**
 *  Creates a generated file for each of the module's submodules
 */
function GenerateSubModuleInfo()
{
    global $module;
    global $ModuleID;
    if(count($module->SubModules) > 0){
        foreach($module->SubModules as $subModuleID => $subModule){

            $props = array();
            $props['parentKey'] = $subModule->parentKey;
            $props['localKey'] = $subModule->localKey;
            $props['conditions'] = $subModule->conditions;

            $codeArray['/**custom**/'] = '$sub_props = unserialize(\''.escapeSerialize($props) .'\')';

            $modelFileName = 'CustomModel.php';
            $createFileName = $ModuleID.'_'.$subModuleID.'_SubModuleInfo.gen';
            SaveGeneratedFile($modelFileName, $createFileName, $codeArray, $ModuleID);
        }
    }
}






class SubModuleMap extends ModuleMap
{
var $subRels = array();

function makeGlobalRollupDef($callerModuleID, $callerPK, $callerDefs)
{
    print "SubModuleMap->makeGlobalRollupDef(): {$this->moduleID}\n";

    if(count($callerDefs) > 0){

        //generates trigger SQL for this submodule
        $SQL = "SELECT 
            '$callerModuleID' as ModuleID, 
            `$callerModuleID`.$callerPK AS RecordID,
            '/*SubModuleID*/' AS SubModuleID,
            '/*SubRecordID*/' AS SubRecordID
        FROM `$callerModuleID`\n";
        foreach($callerDefs as $cdModuleID => $callerDef){
            if(isset($callerDef['remoteJoins'])){

                //do the following INNER JOIN also, but slightly differently...
                $SQL .= "INNER JOIN `{$callerDef['RemoteModID']}` AS `{$callerDef['RemoteModAlias']}`ON ({$callerDef['join']} ";
                if(isset($callerDef['conditions']) && count($callerDef['conditions']) > 0){
                    foreach($callerDef['conditions'] as $condition){
                        //print_r($condition);
                        $SQL .= " AND `$cdModuleID`.{$condition['field']} = '{$condition['value']}' ";
                    }
                }
                $SQL .= ")\n";

                //change the remotefield too
                $SQL .= join($callerDef['remoteJoins']);
                $SQL .= "\n";

            } else {
                $SQL .= "INNER JOIN `$cdModuleID` ON ({$callerDef['join']} ";
                if(isset($callerDef['conditions']) && count($callerDef['conditions']) > 0){
                    foreach($callerDef['conditions'] as $condition){
                        $SQL .= " AND `$cdModuleID`.{$condition['field']} = '{$condition['value']}' ";
                    }
                }
                $SQL .= ")\n";
            }
        }

        $cdModuleInfo = GetModuleInfo($cdModuleID);
        $cdModulePK = $cdModuleInfo->getPKField();
        $SQL .= " WHERE `{$this->moduleID}`.$cdModulePK = '/*SubRecordID*/'";


        CheckSQL($SQL);
        print "Submodule trigger SQL $cdModuleID: $SQL\n";

        //saves trigger to $cdModuleID
        $modelFileName = "SMCTriggersModel.php";
        $createFileName = "{$this->moduleID}_SMCTriggers.gen";
        $createFilePath = GENERATED_PATH. "/{$this->moduleID}/$createFileName";
        if(file_exists($createFilePath)){
            include $createFilePath; //sets $SMCtriggers
        } else {
            $SMCtriggers = array();
        }
        $SMCtriggers[$callerModuleID] = $SQL;

        $codeArray = array('/**SMCtriggers**/' => escapeSerialize($SMCtriggers));
print "Creating trigger for {$this->moduleID}:\n";

        //file creation code...
        SaveGeneratedFile($modelFileName, $createFileName, $codeArray, $this->moduleID);
    }

    $rels = array(); //rels used for stats

    $subs = $this->selectChildrenOfFirst('SubModules', NULL, NULL);

    if(count($subs) > 0){

        $rels['_totSubs'] = count($subs);

        foreach($subs as $sub_elem){
            $subRel = array();
            if('SubModule' == $sub_elem->type){
                $subModuleID = $sub_elem->attributes['moduleID'];

                $subMap = new SubModuleMap($subModuleID);
                $subMap->parentModuleID = $this->moduleID;

                //propagates attributes from the submodule element to the submodule map
                foreach($sub_elem->attributes as  $attrKey => $attrVal){
                    $subMap->attributes[$attrKey] = $attrVal;
                }

                if(!empty($sub_elem->attributes['parentKey'])) {
                    $def = array();
                    $lkIsRemote = false;

                    $tmp_lkModuleField = GetModuleField($subModuleID, $sub_elem->attributes['localKey']);
                    $php_version = floor(phpversion());
                    if($php_version < 5){
                        $lkModuleField = $tmp_lkModuleField;
                    } else {
                        $lkModuleField = clone( $tmp_lkModuleField );
                    }
                    switch(strtolower(get_class($lkModuleField))){
                    case 'tablefield':
                        //nothing to do
                        break;
                    case 'remotefield':
                        $lkIsRemote = true;
                        global $SQLBaseModuleID;
                        $SQLBaseModuleID = $subModuleID;
                        $lkModuleField->reversed = true;
                        $remoteJoins = $lkModuleField->makeJoinDef($subModuleID);

                        $def['remotejoins'] = $remoteJoins;

                        break;
                    default:
print "Submodule trigger local key field {$lkModuleField->name} is a ". get_class($lkModuleField). "\n";
                        unset($lkModuleField);
                        print "Skipping submodule trigger for `$subModuleID`.{$sub_elem->attributes['localKey']}: not a table field";
                        break 2;
                    }

                    $parentKeyModuleField = GetModuleField($this->moduleID, $sub_elem->attributes['parentKey']);
                    if('tablefield' != strtolower(get_class($parentKeyModuleField))){
                        unset($parentKeyModuleField);
                        print "Skipping submodule trigger for {$this->moduleID}.{$sub_elem->attributes['parentKey']}: not a table field";
                        break;
                    }

                    if($lkIsRemote){
                        $def['remoteJoins'] = $remoteJoins;
                        $lkQualified = $lkModuleField->makeSelectDef($subModuleID, false);
                        $def['RemoteModID'] = $lkModuleField->remoteModuleID;
                        $def['RemoteModAlias'] = GetTableAlias($lkModuleField, $subModuleID);
                        $def['join'] = "$lkQualified = `{$this->moduleID}`.{$sub_elem->attributes['parentKey']}";
                    } else {
                        $def['join'] = "`$subModuleID`.{$sub_elem->attributes['localKey']} = `{$this->moduleID}`.{$sub_elem->attributes['parentKey']}";
                    }

                    if(count($sub_elem->c)> 0){
                        foreach($sub_elem->c as $cond_elem){
                            if($cond_elem->type == 'SubModuleCondition') {
                                $def['conditions'][] = $cond_elem->attributes;
                            }
                        }
                    }
                    $defs = $callerDefs;
                    $defs[$subModuleID] = $def;

                    $subRel = $subMap->makeGlobalRollupDef($callerModuleID, $callerPK, $defs);
                }
            }

            //some stats
            if(isset($subRel[$subModuleID]['_totSubs'])){
                $rels['_totSubs'] = $rels['_totSubs'] + $subRel[$subModuleID]['_totSubs'];
            }

            $rels = array_merge((array)$rels, (array)$subRel);
        }
    }
    return array($this->moduleID => $rels);
}
} //class SubModuleMap

function MakeGlobalRollupDef(){
    global $ModuleID;
    //using SubModuleMap to recurse through submodule definitions
    $smm = new SubModuleMap($ModuleID);

    global $module; 
    $PKField = end($module->PKFields);
    $rels = $smm->makeGlobalRollupDef($ModuleID, $PKField, array());
}

function UpdateSMCTriggers(){

return true;

//skipped

    global $ModuleID;
    global $module;
    global $dbh;
    $PKField = end($module->PKFields);
    
    //get triggers for module
    $TriggerFileName = GENERATED_PATH. "/{$ModuleID}/{$ModuleID}_SMCTriggers.gen";
    $insertSQL = "INSERT INTO `smc` (ModuleID, RecordID, SubModuleID, SubRecordID)\n";
            
    if(file_exists($TriggerFileName)){
        include $TriggerFileName;
        
        foreach($SMCtriggers as $triggerModuleID => $triggerSQL){
            $triggerSQL .= " LEFT OUTER JOIN smc ON 
                `$ModuleID`.$PKField = smc.SubRecordID
                AND smc.ModuleID = '$triggerModuleID'
                AND smc.SubModuleID = '$ModuleID' ";
            $triggerSQL = str_replace(array('/*SubModuleID*/', '\'/*SubRecordID*/\''), array($ModuleID, "`$ModuleID`.$PKField"), $triggerSQL);
            
           // $triggerSQL .= " AND {$ModuleID}.$PKFieldName = '$recordID'";
            
           // print debug_r($triggerSQL, "SMCTriggerSQL for $triggerModuleID");
           
            
            
            $SQL = $insertSQL . $triggerSQL;
            $SQL .= "\nWHERE smc.ModuleID IS NULL\n";
            
            print "\ntriggerSQL for $triggerModuleID\n";
            print_r($SQL);
            
            $r = $dbh->query($SQL);
            dbErrorCheck($r);
        }
    }
}

function MakeDashBoardGrids(){
    global $ModuleID;

    switch($ModuleID){
    case 'act':
    case 'acc':
    case 'usrds':

        global $module;
        global $dbh;

        include_once INCLUDE_PATH . '/dashboardGrids.php'; //class definitions

        switch($ModuleID){
        case 'act':
            $dashgrid = new ActionsDashboardGrid();
            break;
        case 'acc':
            $dashgrid = new AccountabilityDashboardGrid();
            break;
        case 'usrds':
            $dashgrid = new ShortcutDashboardGrid();
            break;
        default:
            die("DashboardGrid for module '$ModuleID' aren't implemented.");
            break;
        }
        $replaceValues = array('/**dashgrid**/' => escapeSerialize($dashgrid));

        SaveGeneratedFile('DashboardGridModel.php', '/dsb_'.$ModuleID.'DashboardGrid.gen', $replaceValues, 'dsb');
        break;
    default:
        break;
    }
}


/**
 *  A custom function for pple only: it needs SQL statements from ppl
 */
function custom_ppl_GenerateSQL(){
    $debug_prefix = debug_indent("custom_ppl_GenerateSQL()");

    global $module; //pple module
    $pplModule = GetModule('ppl');
    $ppleScreens =& $module->getScreens();
    $ppleModuleFields =& GetModuleFields('pple');
    $pplSQLs = array();

    foreach($ppleScreens as $screenName => $screen){
        if($screen->isRecordScreen()){
            $screenFields = array();
            $selectFields = array();
            $editableFields = array();

            foreach($screen->Fields as $fieldName => $field){
                $screenFields = array_merge($screenFields, $field->getRecursiveFields());
            }
            if(isset($screen->sections) && count($screen->sections) > 0){
                foreach($screen->sections as $section){
                    foreach($section->Fields as $fieldName => $field){
                        $screenFields = array_merge($screenFields, $field->getRecursiveFields());
                    }
                }
            }
            foreach($screenFields as $fieldName => $field){
                if(strtolower(get_class($ppleModuleFields[$fieldName])) == 'foreignfield'){
                    if('ppl' == $ppleModuleFields[$fieldName]->foreignTable){
                        if('PersonID' == $ppleModuleFields[$fieldName]->localKey){
                            $selectFields[$ppleModuleFields[$fieldName]->foreignField] = $screenFields[$fieldName];
                            echo "theField\n";
                            print_r($field);
                            if($field->isEditable()){
                                $editableFields[$ppleModuleFields[$fieldName]->foreignField] = $screenFields[$fieldName];
                            }
                        }
                    }
                }
            }

            switch(strtolower(get_class($screen))){
            case 'viewscreen':
                $pplSQLs['View']['get'] = $pplModule->generateGetSQL($selectFields);
                break;
            case 'editscreen':
                if(count($selectFields) > 0){
                    $pplSQLs[$screenName]['get'] = $pplModule->generateGetSQL($selectFields);
                    //if($module->CheckForEditableFields($screenName)){
                    if(count($editableFields) > 0){
                        $remotefields = array();
                        foreach(array_keys($selectFields) as $fieldname){
                            $field = $ppleModuleFields[$fieldname];
                            if('remotefield' == strtolower(get_class($field))){
                                $remotefields[$fieldname] = $field;
                                unset($editableFields[$fieldname]);
                            }
                        }
                        if(count($remotefields) > 0){
                            $pplSQLs[$screenName]['remotefields'] = $remotefields;
                        }

                        if(count($editableFields) > 0){
                            $pplSQLs[$screenName]['update'] = $pplModule->generateUpdateSQL($editableFields, 'replace');
                            $pplSQLs[$screenName]['log'] = $pplModule->generateInsertSQL($editableFields, true, 'replace');
                        }

                    }
                }
                break;
            default:
                break;
            }
        }
    }

    //indent_print_r($pplSQLs);
    //die('just testing');

    $modelFileName = 'CustomModel.php';
    $createFileName = 'pple_CustomSQLs.gen';
    $codeArray['/**custom**/'] = '$custom_pplSQLs = unserialize(\''.escapeserialize($pplSQLs).'\')';

    SaveGeneratedFile($modelFileName, $createFileName, $codeArray, 'pple');

    debug_unindent();
}



/**
 *  A custom function for mtgma: generating SQL statement needed by mtg
 */
function custom_mtgma_GenerateSQL()
{
    global $module; //mtgma module

    //fields that are required for SELECT stmt
    $fieldnames = array(
        'MeetingTitle'             => 'MasterMeetingTitle',
        'Agenda'                   => 'MasterAgenda',
        'PersonAccountable'        => 'AssignedPersonAccountable',
        'AssignmentDate'           => 'AssignmentDate',
        'AssignmentDetails'        => 'AssignmentDetails',
        'MasterLeaderObservations' => 'MasterLeaderObservations'
    );

    $selects = array();
    $joins = array();

    foreach($fieldnames as $mtgma_fieldname => $mtg_fieldname){
        $mf = $module->ModuleFields[$mtgma_fieldname];
        $selects[] = $mf->makeSelectDef('mtgma', false) . ' AS ' .$mtg_fieldname;
        $joins = array_merge($joins, $mf->makeJoinDef('mtgma'));
    }

    $SQL = 'SELECT '.join(',', $selects);
    $SQL .= " FROM mtgma \n";
    $SQL .= join("\n", $joins);
    $SQL .= "\nWHERE mtgma.MasterAssignID = [*MasterAssignID*]";

    $modelFileName = 'CustomModel.php';
    $createFileName = 'mtg_CustomSQL.gen';
    $codeArray['/**custom**/'] = '$custom_mtgSQL = unserialize(\''.escapeserialize($SQL).'\')';

    SaveGeneratedFile($modelFileName, $createFileName, $codeArray, 'mtg');
}


/**
 *  A custom function for usr only: It generates files needed by the user preference and user password change screens
 */
function custom_usr_GenerateMyScreens()
{
    $debug_prefix = debug_indent("custom_usr_GenerateMyScreens()");
    print "$debug_prefix start\n";
    global $module; //usr module
    global $ModuleID;

    $field_elements = array(
        0 => new Element(
            'Password',
            'PasswordField',
            array(
                'name' => 'Password',
                'size' => '15',
                'maxLength' => '25',
                'confirm' => 'yes'
            )
        )
    );

    //generate fields here
    $fields = array();
    foreach($field_elements as $field_element){
        $field_object = $field_element->createObject($ModuleID, $field_element->type);
        if(empty($sub_element->attributes['phrase'])){
            $field_object->phrase = $module->ModuleFields[$field_object->name]->phrase;
        }
        $fields[$field_object->name] = $field_object;
    }

    $code = '$fields = unserialize(\''.escapeserialize($fields).'\')';

    $codeArray['/**custom**/'] = $code;
    $modelFileName = 'CustomModel.php';
    $createFileName = 'usr_ChangePassword.gen';
    SaveGeneratedFile($modelFileName, $createFileName, $codeArray, 'usr');


    //generate fields here
    $fields = array();
    foreach($field_elements as $field_element){
        $field_object = $field_element->createObject($ModuleID, $field_element->type);
        if(empty($sub_element->attributes['phrase'])){
            $field_object->phrase = $module->ModuleFields[$field_object->name]->phrase;
        }
        $fields[$field_object->name] = $field_object;
    }

    $get_sql = $module->generateGetSQL($fields);

    print $get_sql;

    $code = '$fields = unserialize(\''.escapeserialize($fields).'\')'.";\n";
    $code .= '$get_sql = \''.addslashes($get_sql).'\'';

    $codeArray['/**custom**/'] = $code;
    $modelFileName = 'CustomModel.php';
    $createFileName = 'usr_ChangePreferences.gen';
    SaveGeneratedFile($modelFileName, $createFileName, $codeArray, 'usr');

    debug_unindent();
}



function SaveParentModuleID($moduleID, $parentModuleID)
{
    $createFileName = 'ParentModuleIDs.gen';
    $permissionModuleIDs = array();
    $needSave = false;

    if(file_exists(GENERATED_PATH . "/{$createFileName}")){
        include(GENERATED_PATH . "/{$createFileName}");
    }

    if(empty($parentModuleID) && isset($permissionModuleIDs[$moduleID])){
        unset($permissionModuleIDs[$moduleID]);
        $needSave = true;
    } elseif(!empty($parentModuleID)){
        $permissionModuleIDs[$moduleID] = $parentModuleID;
        $needSave = true;
    }

    if($needSave){
        $modelFileName = 'CustomModel.php';
        //$codeArray['/**custom**/'] = '$permissionModuleIDs = unserialize(\''.escapeSerialize($permissionModuleIDs).'\')';
        $codeArray['/**custom**/'] = var_export($permissionModuleIDs, true);

        SaveGeneratedFile($modelFileName, $createFileName, $codeArray);
    }
}


/**
 *  Save a file with gettext calls for the navigation menu
 *
 *  This is pretty much a dummy file; it's needed only so that
 *  the xgettext command will find the phrases.
 */
function SaveNavigationPhrases()
{
    require_once CLASSES_PATH . '/navigator.class.php';
    $nav = new Navigator(0);
    $phrases = $nav->getPhrases();
    $gettext_phrases = array();
    foreach($phrases as $phrase){
        if(!empty($phrase)){
            $gettext_phrases[] = 'gettext("'.addslashes($phrase).'")';
        }
    }
    $content = 'array('."\n";
    $content .= join(",\n     ", $gettext_phrases);
    $content .= "\n".')';
    $modelFileName = 'CustomModel.php';
    $codeArray['/**custom**/'] = '$phrases = ' . $content;
    $createFileName = 'NavigationPhrases.gen';
    SaveGeneratedFile($modelFileName, $createFileName, $codeArray);

    //die('test');
}



function makeDotGraph()
{
    global $module;
    global $ModuleID;
    $pk_field = end($module->PKFields);
    $subModules = array();
    $subModuleIDs = array_keys($module->SubModules);
    $submodule_edges = '';

    $graphModuleLabelFile = 'graphModuleLabels.gen';
    $graphModuleLabelPath = GENERATED_PATH . '/' .$graphModuleLabelFile;
    if(file_exists($graphModuleLabelPath)){
        include($graphModuleLabelPath);
    } else {
        $graphModuleLabels = array();
    }
    $graphModuleLabels[$ModuleID] = "    $ModuleID [label=\"{$module->Name}\\n$ModuleID\"];";

    $graphModules = array($ModuleID => array('parent'));

    if(count($module->SubModules) > 0){
        foreach($module->SubModules as $subModuleID => $subModule){
            $conditionColor = '';

            //skip submodules that are only for view:
            if($subModule->parentKey == $pk_field){
                $graphModuleLabels[$subModuleID] = "    $ModuleID [label=\"{$subModule->Name}\\n$subModuleID\"];";

                if(count($subModule->conditions)){
                    $conditionColor = ' [color=blue]';
                    $graphModules[$subModuleID][] = 'central sub';
                } else {
                    $graphModules[$subModuleID][] = 'sub';
                }

                $subModuleKey = $subModule->ModuleFields[$subModule->localKey];
                if('tablefield' === strtolower(get_class($subModuleKey))){
                    $submodule_edges .=  "    {$ModuleID} -> $subModuleID $conditionColor;\n";
                } else {
                    print "view-only submodule\n";
                }
            } else {
                $graphModules[$subModuleID][] = 'view sub';
            }
        }
    }

    trace($graphModules, '$graphModules');

    SaveGeneratedFile('CustomModel.php', $graphModuleLabelFile, array('/**custom**/' => '$graphModuleLabels = unserialize(\''.escapeSerialize($graphModuleLabels).'\')'));

    SaveGeneratedFile('CustomModel.php', $ModuleID.'_SubModules.gen', array('/**custom**/' => '$graphModules = unserialize(\''.escapeSerialize($graphModules).'\')'), $ModuleID);

    print "makeDotGraph fragment\n";
    print "$submodule_edges\n";

    if(!empty($submodule_edges)){
        $smDotFile = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_submodules.dot";
        if($fp = fopen($smDotFile, 'w')) {
            if(fwrite($fp, $submodule_edges)){

            } else {
                die( "s2a: could not save to file $smDotFile. Please check file/folder permissions.\n" );
            }
            fclose($fp);
        } else {
            die( "s2a: could not open file $smDotFile. Please check file/folder permissions.\n" );
        }
    }
    return $submodule_edges;
}



//"MAIN"

//Create one module
CreateModule($ModuleID, $Language);

?>
