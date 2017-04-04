<?php
/**
 * Generally available functions to be used at run-time as well as generating time.
 *
 * This file contains functions that are used by any other file in the
 * Active Agenda/s2a project. It is included by most if not all running files.
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
 * @version        SVN: $Revision: 1686 $
 * @last-modified  SVN: $Date: 2009-07-06 21:44:30 +0200 (Pn, 06 lip 2009) $
 */



/**
 *  Returns the portion of a phrase string before the "pipe" caracter, or the entire string if none.
 */
function ShortPhrase($phrase)
{
    $pos = strpos($phrase, '|');
    if ($pos > 0){
        $phrase = substr($phrase, 0, $pos);
    }

    return $phrase;
}


/**
 *  Returns the portion of a phrase string after the "pipe" caracter, or the entire string if none.
 */
function LongPhrase($phrase)
{
    $pos = strpos($phrase, '|');
    if ($pos > 0){
        $phrase = substr($phrase, $pos+1);
    }

    return $phrase;
}


function GetDefaultDateFormat($key)
{
    static $formats = null;

    if( empty($formats) ){
        //default settings (ISO)
        $formats = array(
            'date'       => 'YYYY-MM-DD',
            'dateDB'     => '%Y-%m-%d',
            'dateTime'   => 'YYYY-MM-DD HH:MM',
            'dateTimeDB' => '%Y-%m-%d %H:%i',
            'datePHP'    => '%Y-%m-%d',
            'dateTimePHP'=> '%Y-%m-%d %H:%M',
            'dateCal'    => '%Y-%m-%d',
            'dateTimeCal'=> '%Y-%m-%d %H:%M',
            'timeFormat' => '24',
            'timePHP'    => '%H:%M',
            'timeDB'     => '%H:%i',
            'mondayFirst'=> true,
            'weekNumbers'=> true
        );

        $localSettingsFilePath = LOCALE_PATH .'/'. DEFAULT_LOCALE .'/settings.php';
        if( file_exists($localSettingsFilePath) ){
            include $localSettingsFilePath; //returns $dateFormats

            //copies local settings while ensuring that any missing entries have a default value
            foreach( $formats as $formatName => $formatValue ){
                if( isset($dateFormats[$formatName]) ){
                    $formats[$formatName] = $dateFormats[$formatName];
                }
            }
            if( !isset($formats['timePHP']) && '12' == $formats['timeFormat'] ){
                $formats['timePHP'] = '%I:%M %p';
            }
        }
    }
    if( !isset($formats[$key]) ){
        trigger_error("Unknown date format key '$key'", E_USER_ERROR);
    }
    return $formats[$key];
}


/**
 *  Returns an array of modulefields of the specified module.
 */
function &GetModuleFields($pModuleID)
{
    if(defined('EXEC_STATE') && EXEC_STATE == 4){
        $debug_prefix = "GetModuleFields() {$pModuleID}";

        $t_module =& GetModule($pModuleID);
        if(is_object($t_module)){

            if(empty($t_module->ModuleFields)){
                trace($t_module);
                trigger_error("$debug_prefix modulefields are empty", E_USER_ERROR);
            }
            return $t_module->ModuleFields;

        } else {
            trace("$debug_prefix could not find $pModuleID");
            return false;
        }

    } else {

        $genFileName = GENERATED_PATH . "/{$pModuleID}/{$pModuleID}_ModuleFields.gen";

        if(file_exists($genFileName)){
            //imports $modulefields
            include($genFileName);

            $modulefields = unserialize($modulefields);

            if(!empty($modulefields)){
                return $modulefields;
            } else {
                trigger_error("GetModuleFields() {$pModuleID}: moduleFields are empty", E_USER_ERROR);
            }
        } else {
            trigger_error("GetModuleFields() {$pModuleID}: error loading file $genFileName", E_USER_ERROR);
        }

        return false;
    }
}


/**
 *  Returns a modulefield object
 */
function GetModuleField($pModuleID, $fieldname)
{
    $moduleFields = GetModuleFields($pModuleID);
    if(!isset($moduleFields[$fieldname])){
        trigger_error("GetModuleField: cannot find ModuleField '$pModuleID'.'$fieldname'", E_USER_ERROR);
    }
    return $moduleFields[$fieldname];
}


/**
 *  Returns a ModuleInfo object
 */
function &GetModuleInfo($moduleID)
{
    if(empty($moduleID)){
        trigger_error("moduleID cannot be empty", E_USER_ERROR);
    }
    require_once CLASSES_PATH . '/moduleinfo.class.php';
    static $moduleInfos = array();

    if(empty($moduleInfos[$moduleID])){
        $moduleInfo = new ModuleInfo($moduleID);
        $moduleInfos[$moduleID] =& $moduleInfo;
        return $moduleInfo;
    } else {
        return $moduleInfos[$moduleID];
    }
}


/**
 *  Returns a DataHandler object
 */
function &GetDataHandler($moduleID, $isImport = false, $useRemoteIDCheck = false)
{
    require_once CLASSES_PATH . '/data_handler.class.php';
    $includeFileName = GENERATED_PATH . "/{$moduleID}/{$moduleID}_DataHandler.gen";

    if(file_exists($includeFileName)){
        include($includeFileName); //supplies $dataHandler
    } else {
        trigger_error(sprintf(gettext("Cannot find the file %s."), $includeFileName), E_USER_ERROR);
    }

    if('datahandler' != strtolower(get_class($dataHandler))){
        trigger_error(sprintf(gettext("Could not get a valid DataHandler object for the %s module."), $moduleID), E_USER_ERROR);
    }

    $dataHandler->useRemoteIDCheck = $useRemoteIDCheck;
//trace($dataHandler, 'returned data handler');
    return $dataHandler;
}


/**
 *  Returns the proper table alias for a ModuleField, given the $SQLBaseModuleID
 */
function GetTableAlias(&$pModuleField, $parentAlias = null)
{
    $debug_prefix = "GetTableAlias() {$pModuleField->moduleID}.{$pModuleField->name}:";
    trace("$debug_prefix parentAlias = $parentAlias");

    global $SQLBaseModuleID;
    trace("$debug_prefix SQLBaseModuleID = $SQLBaseModuleID");

    switch(strtolower(get_class($pModuleField))){
    case 'calculatedfield':
        return "{$parentAlias}_cf";
        break;
    case 'recordmetafield':
    case 'rangefield':
    case 'linkfield':
    case 'tablefield':
        return $parentAlias;
        break;
    default:
        $arrayKey = $pModuleField->getTableAliasKey($parentAlias);
        $foreignModuleID = $pModuleField->getForeignModuleID();
    }

    global $gTableAliasCached;
    if(!defined('EXEC_STATE') || EXEC_STATE != 4){
        $tableAliases = array();
        include_once(GENERATED_PATH . "/{$SQLBaseModuleID}/{$SQLBaseModuleID}_TableAliases.gen");
        if(count($tableAliases) > 0){ //detects whether the include happened (note "include_once" anpve)
            $gTableAliasCached[$SQLBaseModuleID] = $tableAliases;
        }
    }

    global $gForeignModules;

    /*$condition = trim($condition); //makes sure it doens't create different matches b/c of whitespace differences*/

    if(!empty($parentAlias)){

        //sanity check: makes sure $localModuleAlias.$localKey isn't actually the same field as $foreignModuleID.$foreignKey
        if(preg_replace('/[\d]*/', '', $parentAlias) == $foreignModuleID){
            $localModuleAlias = $SQLBaseModuleID; //$localModuleID;
        } else {
            $localModuleAlias = $parentAlias;
        }
    } else {
        $localModuleAlias = $SQLBaseModuleID; //$localModuleID;
    }

    if(empty($gTableAliasCached[$SQLBaseModuleID][$arrayKey])){
        if(empty($gForeignModules[$SQLBaseModuleID][$foreignModuleID])){
            $gForeignModules[$SQLBaseModuleID][$foreignModuleID] = 1;
        } else {
            $gForeignModules[$SQLBaseModuleID][$foreignModuleID] += 1;
        }

        $alias = $foreignModuleID . $gForeignModules[$SQLBaseModuleID][$foreignModuleID];
        $gTableAliasCached[$SQLBaseModuleID][$arrayKey] = $alias;
        $inserting = true;
    } else {
        $alias = $gTableAliasCached[$SQLBaseModuleID][$arrayKey];
        $inserting = false;
    }

    trace("$debug_prefix field type " .get_class($pModuleField));
    trace("$debug_prefix parent alias $parentAlias");
    if($inserting){
        trace("$debug_prefix Adding an alias: $alias");
        trace($gTableAliasCached[$SQLBaseModuleID], "$debug_prefix gTableAliasCached for $SQLBaseModuleID");
        trace($gForeignModules[$SQLBaseModuleID], "$debug_prefix gForeignModules for $SQLBaseModuleID");
    }

    trace("$debug_prefix alias = $alias");
    return $alias;
}


/**
 *  Returns a modulfield's qualified name from a cached definition
 */
function GetQualifiedName($fieldName, $moduleID = null)
{
    $def = _getCachedFieldDef($fieldName, $moduleID);
    return $def[0];
}


/**
 *  Returns a Select Def
 *
 *  A Select Def is an expression of a modulefield for the SELECT part of a SQL
 *  query.  Looks for a cached def, otherwise generates the def from the module
 *  field.
 */
function GetSelectDef($fieldName, $moduleID = null)
{
    $def = _getCachedFieldDef($fieldName, $moduleID);
    return $def[1];
}


/**
 *  Returns a Join Def
 *
 *  A Join Def is an expression of a modulefield for the FROM part of a SQL
 *  query (defines the joins needed to get the information from the field).
 *  Looks for a cached def, otherwise generates the def from the module
 *  field.
 */
function GetJoinDef($fieldName, $moduleID = null)
{
    $def = _getCachedFieldDef($fieldName, $moduleID);
    return $def[2];
}


/**
 *  Private function for use by GetSelectDef() and GetJoinDef()
 */
function _getCachedFieldDef($fieldName, $moduleID = null)
{
    static $gFieldDefs = array();
    if(empty($moduleID)){
        global $SQLBaseModuleID;
        $moduleID = $SQLBaseModuleID;
    }
    if(empty($moduleID)){
        global $ModuleID;
        $moduleID = $ModuleID;
    }

    if(!defined('EXEC_STATE') || EXEC_STATE != 4){
        if(empty($gFieldDefs[$moduleID])){
            //look up file
            $genfile_name = GENERATED_PATH . "/{$moduleID}/{$moduleID}_FieldDefCache.gen";
            if(file_exists($genfile_name)){
                include_once($genfile_name);
            }
        }
    }

    if(empty($gFieldDefs[$moduleID][$fieldName])){
        $modulefield = GetModuleField($moduleID, $fieldName);
        $field_def = array(
            $modulefield->getQualifiedName($moduleID),
            $modulefield->makeSelectDef($moduleID),
            $modulefield->makeJoinDef($moduleID)
        );
        $gFieldDefs[$moduleID][$fieldName] = $field_def;
    }

    return $gFieldDefs[$moduleID][$fieldName];
}


/**
 * Makes custom SELECT statements.
 *
 * Returns an array where the first element is the SELECT...FROM statement
 * and the second element is an array of the qualified names which can are
 * valid to be used as part of a WHERE or ORDER BY clause.
 */
function MakeSelectStatement($fieldNames, $moduleID)
{
    $selects = array();
    $joins = array();
    $qualified = array();
    foreach($fieldNames as $fieldName => $display){
        if($display){
            $selects[$fieldName] = GetSelectDef($fieldName, $moduleID);
        }
        $qualified[$fieldName] = GetQualifiedName($fieldName, $moduleID);
        $joins = array_merge($joins, GetJoinDef($fieldName, $moduleID));
    }
    $joins = SortJoins($joins);

    $SQL = "SELECT \n";
    $SQL .= join(",\n", $selects) . "\n";
    $SQL .= "FROM `$moduleID`\n";
    $SQL .= join("\n", $joins)."\n";

    trace($SQL, 'MakeSelectStatement');

    CheckSQL($SQL);
    return array($SQL, $qualified);
}


/**
 *  returns a new search object with no conditions
 */
function &GetNewSearch($moduleID)
{
    $listFieldsFileName = GENERATED_PATH . "/{$moduleID}/{$moduleID}_ListFields.gen";

    if (!file_exists($listFieldsFileName)){
        trigger_error("Could not find file '$listFieldsFileName'.", E_USER_ERROR);
    }

    //the included file sets $listFields variable
    include $listFieldsFileName;

    $ModuleInfo = GetModuleInfo($moduleID);
    $listPK = $ModuleInfo->getPKField();
    $search = new Search(
        $moduleID,
        $listFields
    );

    return $search;
}


/**
 *  Handy "object factory" function.
 */
function &MakeObject($moduleID, $name, $type, $attributes = array())
{
    require_once(CLASSES_PATH.'/module_map.class.php');
    $attributes['name'] = $name;
    $element = new Element($name, $type, $attributes);
    $returnObj = $element->createObject($moduleID);
    return $returnObj;
}


/**
 *  Checks a PEAR DB query result for error messages
 */
function dbErrorCheck($dbResult, $die = true, $print = false)
{
    global $dbh;

    if (DB::isError($dbResult)) {
        if($dbResult !== $dbh){
            $dbh->query("ROLLBACK");
        }

        $reg = '/\[nativecode=(.*)\]/';
        $matches = array();
        preg_match($reg, $dbResult->userinfo, $matches);
        $server_code = $matches[1];

        $message = $dbResult->getMessage();
        $message .= "\n".gettext("Database error")." ".$server_code;
        $message .= $dbResult->userinfo;

        if($print){
            print debug_r($message). "<br />\n";
        }

        if( $die || (defined('IS_RPC') && IS_RPC) ){
            trigger_error($message, E_USER_ERROR);
            die();
        }else{
            trigger_error($message, E_USER_WARNING);
        }

        return false;
    }
    return true;
}


/**
 *  Returns a valid MDB2 handle
 *
 *  This avoids repeating the use of the global $dbh object
 */
function &GetMDB2()
{
    require_once 'MDB2.php';

    if(isset($GLOBALS['MDB2_instance'])){
        return $GLOBALS['MDB2_instance'];
    }

    if(defined('EXEC_STATE') && (4 == EXEC_STATE || 2 == EXEC_STATE)){
        if(!defined('GEN_DB_DSN')){
            trigger_error('An expected configuration setting was not found: GEN_DB_DSN', E_USER_ERROR);
        }
        $dsn = GEN_DB_DSN;
    } else {
        if(!defined('DB_DSN')){
            trigger_error('An expected configuration setting was not found: DB_DSN', E_USER_ERROR);
        }
        $dsn = DB_DSN;
    }

    //trace('creating a new mdb2');
    $mdb2 =& MDB2::connect($dsn,
        array(
            'portability' => MDB2_PORTABILITY_NONE,
            'quote_identifier' => true
        )
    );
    if (PEAR::isError($mdb2)) {
        trigger_error($mdb2->getMessage(), E_USER_ERROR);
    }
    $mdb2->setOption('idxname_format', '%s'); //avoids appending '_idx' to the index names
    $mdb2->setFetchMode(MDB2_FETCHMODE_ASSOC);

    $GLOBALS['MDB2_instance'] =& $mdb2;
    return $mdb2;
}


/**
 *  Checks a PEAR MDB2 result for error messages
 *
 *  Parameters:
 *   $die       - ends processing at this error
 *   $print     - prints the message
 *   $omit_code - an error code which you want to ignore or handle elsewhere
 *
 *  Returns: the error code contained in the error object
 */
function mdb2ErrorCheck(&$dbResult, $die = true, $print = false, $omit_codes = array())
{
    $code = 0;
    $native_code = 0;
    $message = '';

    if (MDB2::isError($dbResult)) {
        $code = $dbResult->code;
        if(!is_array($omit_codes)){
            $omit_codes = array($omit_codes);
        }
        if(count($omit_codes) == 0 || !in_array($code, $omit_codes)){
            $message = $dbResult->userinfo;
			
            $native_code_pattern = '/\[Native code:(.*)\]/';
            if(preg_match($native_code_pattern, $dbResult->userinfo, $matches)){
                $native_code = trim($matches[1]);
                if(0 == $native_code){ 
					//This can happen when "commit/release savepoint cannot be done changes are auto committed". Best to just ignore after raising a notice
                    trigger_error($message, E_USER_NOTICE);
                    return array('code' => 0, 'native_code' => 0);
                }
            }
            /* this may be a good idea but not tested yet
            $native_msg_pattern = '/\[Native message:(.*)\]/';
            if(preg_match($native_msg_pattern, $dbResult->userinfo, $matches)){
                $message .= '|'. trim($matches[1]);

            }*/
			$aa_error = false;
			if( preg_match( '/\[Native\s+message:\s+Unknown\s+column\s+\'error_(3\d\d\d)\'\s+in\s+\'field\s+list\'\]/', 
				$message, $aa_code ) ){	
				
				switch( $aa_code[1] ){
				case '3001':
					$MDB2Error['code'] = $aa_code[1];
					$MDB2Error['message'] = gettext("you have no delete permissions for this record!");
					$aa_error = true;
					break;
				case '3002':
					$MDB2Error['code'] = $aa_code[1];
					$MDB2Error['message']  = gettext("you have no permissions to edit this record!");
					$aa_error = true;
					break;
				case '3003':
					$MDB2Error['code'] = $aa_code[1];
					$MDB2Error['message']  = gettext("you have no permissions to make this action for this case!");
					$aa_error = true;					
					break;
				case '3004':
					$MDB2Error['code'] = $aa_code[1];
					$MDB2Error['message']  = gettext("you have no permissions to make decisions for this case!");
					$aa_error = true;					
					break;
				case '3005':
					$MDB2Error['code'] = $aa_code[1];
					$MDB2Error['message']  = gettext("you have no permissions to close this case!");
					$aa_error = true;					
					break;
				case '3006':
					$MDB2Error['code'] = $aa_code[1]; 
					$MDB2Error['message']  = gettext("you have no delete permissions for this case!");
					$aa_error = true;					
					break;
				case '3007':
					$MDB2Error['code'] = $aa_code[1]; 
					$MDB2Error['message']  = gettext("you have no permissions to edit this case!");
					$aa_error = true;					
					break;
				default:				
					$MDB2Error['code'] = $aa_code[1]; 
					$MDB2Error['message']  = gettext( "Error ".$aa_code[1] );
					$aa_error = true;
				}				
			}
			
            if($print){
                if(defined('debug_r')){
                    print debug_r($message). "<br />\n";
                } else {
                    trace($message);
                }
            }

            $mdb2 =& GetMDB2();
            if( $mdb2->inTransaction() ){
                trace('rolling back transaction');
                $rollbackResult = $mdb2->rollback();
            }
			if( $aa_error ){				
				if( isset($rollbackResult) ){
					mdb2ErrorCheck($rollbackResult, false, $print);
				}
				return array('code' => $MDB2Error['code'] , 'native_code' => $MDB2Error['message'] );
			}
			if( $die || (defined('IS_RPC') && IS_RPC) ){
				trigger_error($message, E_USER_ERROR);
				if(isset($rollbackResult)){
					mdb2ErrorCheck($rollbackResult);
				}
				die();
			} else {
				trigger_error($message, E_USER_WARNING);
				if(isset($rollbackResult)){
					mdb2ErrorCheck($rollbackResult, false, $print);
				}
			}
			
        }
    }
    return array('code' => $code, 'native_code' => $native_code);
}


/**
 *  Checks that a SQL statement is (syntactically) valid
 */
function CheckSQL($SQL, $die = true)
{
    global $dbh;
    global $SQLBaseModuleID;

    $debug_prefix = debug_indent("CheckSQL() $SQLBaseModuleID:");

    //start transaction
    $result = $dbh->query('BEGIN');
    dbErrorCheck($result);

    //execute statement
    $result = $dbh->query($SQL);
    if (DB::isError($result)) {
        $dbh->query("ROLLBACK");

        $message = "$debug_prefix SQL error: \n";
        $message .= $result->getMessage() . "\n";
        $message .= $SQL . "\n";
        $message .= $result->getMessage();

        $reg = '/\[nativecode=(.*)\]/';
        $matches = array();
        preg_match($reg, $result->userinfo, $matches);

        $server_code = $matches[1];
        $message .= "\nMySQL error ".$server_code;

        if(defined('EXEC_STATE') && EXEC_STATE == 4){
            die($message);
        }

        if($die){
            trigger_error($message, E_USER_ERROR);
            die();
        } else {
            trigger_error($message, E_USER_WARNING);

            debug_unindent();
            return false;
        }
    }

    //roll back transaction
    $result = $dbh->query('ROLLBACK');

    debug_unindent();
    return true;
}


/**
 * pretty-indents debug messages from function calls: to be called at top of function declaration
 */
function debug_indent($prefix)
{
    global $debug_indent_level;
    global $debug_array;
    if(empty($debug_indent_level)){
        $debug_indent_level = 1;
        $debug_array = array($prefix);
    } else {
        $debug_indent_level++;
        $debug_array[] = $prefix;
    }
    if(defined('DEBUG_STYLE_PRINT_LEVEL') && DEBUG_STYLE_PRINT_LEVEL){
        print "d+ $debug_indent_level\n";
    }
    if(defined('DEBUG_STYLE_BACKTRACE') && DEBUG_STYLE_BACKTRACE){
        print join(' | ', $debug_array);
        print " (begin)\n";
    }
    if(defined('DEBUG_STYLE_INDENT') && DEBUG_STYLE_INDENT){
        return str_pad('', $debug_indent_level*3, ' ', STR_PAD_LEFT) . $prefix;
    } else {
        return $prefix;
    }
}


/**
 * companion to debug_indent: to be called immediately before any return statement
 */
function debug_unindent()
{
    global $debug_indent_level;
    global $debug_array;

    if(defined('DEBUG_STYLE_PRINT_LEVEL') && DEBUG_STYLE_PRINT_LEVEL){
        print "d- $debug_indent_level\n";
    }
    if(defined('DEBUG_STYLE_BACKTRACE') && DEBUG_STYLE_BACKTRACE){
        print join(' | ', $debug_array);
        print " (end)\n";
    }
    array_pop($debug_array);
    $debug_indent_level--;
    if(0 > $debug_indent_level){
        trigger_error("Debug indent level is zero: check code for mismatch between debug_indent() and debug_unindent()", E_USER_WARNING);
    }
}


/**
 * prints the output of print_r(), indented
 */
function indent_print_r($object, $print = true, $title = null)
{
    global $debug_indent_level;
    if(defined('DEBUG_STYLE_INDENT') && DEBUG_STYLE_INDENT){
        $indents = $debug_indent_level + 1;
    } else {
        $indents = 1;
    }
    $pad = str_pad('', $indents*3, ' ', STR_PAD_LEFT);

    ob_start();
        if(!empty($title)){
            print $title.":\n";
        }
        print_r($object);
        $lines = explode("\n", ob_get_contents());
    ob_end_clean();

    $content = '';
    foreach($lines as $line){
        $content .= $pad . $line . "\n";
    }

    if($print){
        print $content;
    }
    return $content;
}


/**
 * orders join definitions so that dependents are always after parents
 */
function SortJoins($joins)
{

    global $SQLBaseModuleID;
    if(empty($SQLBaseModuleID)){
        global $ModuleID;
        $SQLBaseModuleID = $ModuleID;
    }

    global $gTableAliasParents;
    if(!defined('EXEC_STATE') || EXEC_STATE != 4){
        $tableAliasParents = array();
        include(GENERATED_PATH . "/{$SQLBaseModuleID}/{$SQLBaseModuleID}_TableAliases.gen");

        $gTableAliasParents[$SQLBaseModuleID] = $tableAliasParents;
    }

    $debug_prefix = debug_indent("SortJoins():");

    if(count($joins) == 0){
        debug_unindent();
        return array();
    }

    $dependences = array();
    foreach($joins as $dependent => $join){
        if(!empty($join)){

            if(!empty($gTableAliasParents[$SQLBaseModuleID][$dependent])){
                $parent = $gTableAliasParents[$SQLBaseModuleID][$dependent];
                $dependences[$parent][] = $dependent;
            } else {
                if($SQLBaseModuleID == $dependent){
                    //not a problem
                } else {
                    trigger_error( "there should be a cached parent alias for base='$SQLBaseModuleID' dep='$dependent'\n", E_USER_WARNING);
                }
            }
        }

    }

    $newJoins = array();
    $ordered = array();
    $flat_dependences = array();
    $parents = array();

    foreach($dependences as $parent => $items){
        $parents[] = $parent;
        foreach($items as $item){
            $flat_dependences[$item] = $parent;
        }
    }

    $root = $SQLBaseModuleID;

    //panic if there's no root
    if('' === $root){
        //die("$debug_prefix Can't find a root table in joins.");
        trigger_error("Can't find a root table in joins.", E_USER_ERROR);
    }

    //start by inserting root:
    $ordered[] = $root;
    $subs = array();
    $running = true;
    $current_parent = $root;
    $current_children = array();
    $bail_counter = 50;
    while($running){
        foreach($flat_dependences as $item => $parent){
            if($parent == $current_parent){
                $ordered[] = $item;
                unset($flat_dependences[$item]);
                $current_children[] = $item;
            }
        }

        //pick a new parent
        if(! ($current_parent = array_shift($current_children))){
            $running = false;

            //inset any remaining joins
            foreach($flat_dependences as $item => $parent){
                $ordered[] = $item;
            }
            break;
        }
        if(count($flat_dependences) == 0){
            $running = false;
        } 
        $bail_counter--;
        if($bail_counter < 1){
            $running = false;
        }
    }

    foreach($ordered as $item){
        if(isset($joins[$item])){
            $newJoins[$item] = $joins[$item]; //append the joins in the new order
        }
    }

    debug_unindent();
    return $newJoins;
}


/**
 * returns a timestamp
 */
function getMicroTime()
{
    list($usec, $sec) = explode(' ', microtime());
    return (float)$sec + (float)$usec;
}


/**
 * sets a time stamp in the global $timestamps array in Miliseconds
 */
function setTimeStamp($name)
{
	if( !SCRIPT_PROFILING ){ return; };
    global $timestamps;
    $timestamps[$name] = 1000*getMicroTime();
}


/**
 * returns a performance report
 */
function getDuration()
{
	if( !SCRIPT_PROFILING ){ return ''; };
    global $timestamps;
    $end_time = 1000*getMicroTime();
    $first_timestamp = reset($timestamps);
    $prev_timestamp = $first_timestamp;
    $durations = array();
    foreach($timestamps as $name => $timestamp){
        $durations['Cumulative time in milliseconds since begin'][$name] = round($timestamp - $first_timestamp, 2);
        $durations['Delta previous stamp and the stamp in milliseconds'][$name] = round($timestamp - $prev_timestamp, 2);
        $prev_timestamp = $timestamp;
    }
    $durations['Cumulative time in milliseconds since begin']['template_rendering_end'] = round($end_time - $first_timestamp, 2);
    $durations['Delta previous stamp and the stamp in milliseconds']['template_rendering_end'] = round($end_time - $prev_timestamp, 2);

	$page_run_durations = indent_print_r($durations, false);
	$page_run_durations = str_replace( 'Array', '', $page_run_durations );
	$page_run_durations = str_replace( '[', '', $page_run_durations );
	$page_run_durations = str_replace( ']', '', $page_run_durations );
	$page_run_durations = str_replace( '(', '', $page_run_durations );
	$page_run_durations = str_replace( ')', '', $page_run_durations );
	
	$memory_usage = memory_get_peak_usage()/1000000;
	$memory_usage = number_format ( $memory_usage , 3 , ',' , '.' );
	$memory_usage = "       Memory peak usage: ".$memory_usage." MB";
    $wrapper = "<!-- \n\n%s -->";
    return sprintf($wrapper, $memory_usage.$page_run_durations );
}


/**
 *  generic emailing function
 *
 *  If used, $attachments must be an array of the following format:
 *     array('file_name.txt' => array('file content', 'mime type'))
 */
function sendEmail($from, $to, $subject, $textMessage, $HTMLMessage = null, $attachments = null){
    include_once PEAR_PATH . '/Mail.php';
    include_once PEAR_PATH . '/Mail/mime.php';

    if(!defined('MAILING_METHOD')){
        trigger_error("Email configurations not found. Please define the MAILING_METHOD and MAILING_PARAMS settings in the config.php file.", E_USER_ERROR);
    }
    if('none' == MAILING_METHOD){
        trigger_error("Email functionality is disabled. Please define the MAILING_METHOD and MAILING_PARAMS settings in the config.php file.", E_USER_ERROR);
    }

    $mail_headers = array();
    $recipients = array();

    //prepare headers
    $mail_headers['Subject'] = $subject;
    $recipients[] = $to;    
    $mail_headers['Reply-To'] = $from;
    $mail_headers['From']     = $from;
    $mail_headers['Return-Path'] = BOUNCE_EMAIL_ADDRESS;

    $mime = new Mail_mime();
    $mime->setTXTBody($textMessage);
    if(!empty($HTMLMessage)){
        $mime->setHTMLBody($HTMLMessage);
    }
    if(!empty($attachments)){
        foreach($attachments as $fileName => $attachmentArr){
            $mime->addAttachment($attachmentArr[0], $attachmentArr[1], $fileName, false);
        }
    }
    $body = $mime->get();
    $headers = $mime->headers($mail_headers);

    static $checked_mailparams = false;
    static $mailparams = null;
    if(!$checked_mailparams){
        $checked_mailparams = true;
        if('' != MAILING_PARAMS){
            $mailparams = array();
            $mailparam_pairs = explode("\n", MAILING_PARAMS);
            if(count($mailparam_pairs) > 0){
                foreach($mailparam_pairs as $mailparam_pair){
                    list($param,$paramVal) = explode( '=', $mailparam_pair, 2);
                    $param = trim($param);
                    $paramVal = trim($paramVal);
                    if('true' == $paramVal){
                        $paramVal = 1; //cannot use either 'true' or true (why?)
                    }
                    if('false' == $paramVal){
                        $paramVal = 0;
                    }
                    $mailparams[$param] = $paramVal;
                }
            }
        }
    }
    $mail =& Mail::factory(MAILING_METHOD, $mailparams);

    $result = $mail->send($recipients, $headers, $body);
    if(PEAR::IsError($result)){
        trigger_error("Sending email failed.\n".$result->toString(), E_USER_WARNING);
        $statusID = 23;
    } else {
        $statusID = 3;
    }

    unset($mime);
    unset($mail);
    return $statusID;
}


/**
 * Custom error handler
 */
function handleError($error_number, $error_string, $error_file, $error_line)
{
    if( !defined('E_STRICT') ){ //PHP 4 BC attempt
        define('E_STRICT', 2048);
    }

    $errortype = array (
        E_ERROR           => "Error",
        E_WARNING         => "Warning",
        E_PARSE           => "Parsing Error",
        E_NOTICE          => "Notice",
        E_CORE_ERROR      => "Core Error",
        E_CORE_WARNING    => "Core Warning",
        E_COMPILE_ERROR   => "Compile Error",
        E_COMPILE_WARNING => "Compile Warning",
        E_USER_ERROR      => "AA Error",
        E_USER_WARNING    => "AA Warning",
        E_USER_NOTICE     => "AA Notice",
        E_STRICT          => "PHP 5 Strict Notice"
        );

    $user_message = '';

    if( defined('EXEC_STATE') && EXEC_STATE == 1 ){
        $parsing = false;
        global $User;
        if( !empty($User) ){
            $happenedTo = $User->DisplayName;
			$happenedToID = $User->PersonID;
        }else{
			$happenedTo = 'unknown';
			$happenedToID = 'unknown';
		}
        $happenedToIP = $_SERVER['REMOTE_ADDR'];		
				
		if( defined('IS_RPC') && IS_RPC ){
			$user_message = gettext("An error occurred while processing your request.");
		}else{
			$user_message = '<br/><b>'.gettext("An error occurred while processing your request.")."</b>\n";
			$user_message .= '<br/>'.gettext("Since all errors are logged and administrators are automatically notified, it is likely that we are already working on it.");			
			$bugtracker_link = '<b><a href="'.BUG_REPORT_LINK.'" target="_blank">';
			$user_message = nl2br(sprintf($user_message, $bugtracker_link, '</a></b>'));
		}
    } else {
        $parsing = true;
        $happenedTo = 's2a generator';
    }
	
    switch($error_number){
    case E_STRICT:
    case E_NOTICE:
        //ignores above types
        return true;
        break;
    /*case E_NOTICE:
        if(false !== strpos($error_file, PEAR_PATH)){
            return true;//ignores notices in PEAR library
        }*/
    case E_USER_NOTICE:
        //logs a notice to the error log and shows the error messsage to the user
        $die = false;
        break;
    case E_WARNING:
    case E_USER_WARNING:
        //generates a warning for these types but does not show an error message to the user
        $die = false;
        break;
    default:
        //shows the error message and stops executing
        $die = true;
        if( false !== strpos($error_string, '|') ){
            list($user_message, $error_string) = explode('|', $error_string);
            $error_string = 'User message: '.$user_message ."\nSystem message: ".$error_string;
            $user_message = "<p>$user_message</p>";
        }
        break;
    }

    global $ModuleID;
    global $recordID;
    global $ScreenName;
    $appInfo = '';
    if( !empty($ModuleID) ){
        $appInfo = "\n\tModule: '$ModuleID'";
    }
    if( !empty($recordID) ){
        $appInfo .= "\n\tRecord: '$recordID'";
    }
    if( !empty($ScreenName) ){
        $appInfo .= "\n\tScreenName: '$ScreenName'";
    }

    $moreInfo = '';
    if( !empty($_SERVER['REQUEST_URI']) ){
        $moreInfo .= "REQUEST_URI: {$_SERVER['REQUEST_URI']}\n";
    }
    if( !empty($_GET) ){
		$output = print_r( $_GET, true );
		$output = preg_replace( '/\n/m', "\n\t", $output );
        $moreInfo .= "REQUEST_GET:\n\t$output";
    }

    //Use this for testing only - POST data could contain sensitive information like passwords.
    //Consider cleaning out the errors.log file after finishing.
    if( !empty($_POST) ){
		$output = print_r( $_POST, true );
		$output = preg_replace( '/\n/m', "\n\t", $output );
        $moreInfo .= "\nREQUEST_POST:\n\t$output";
    }

    $date = date('c');
    $error_msg = "error_date: $date\n\nerror_of_type: {$errortype[$error_number]}\nerror_number: $error_number\n\nhappened_from_IP: $happenedToIP\nhappened_to_PersonID: $happenedToID\nhappened_to_DisplayName: $happenedTo\n\n";
    $error_msg .= "app: $appInfo\n\napp_file: $error_file\napp_file_line: $error_line\n";
	$error_string = preg_replace( '/\n/m', "\n\t", $error_string );
	$error_msg .= "app_error_string:\n\t$error_string\n\n";
    $error_msg .= $moreInfo."\n\n";
	
    $backtrace = debug_backtrace();
	$backtrace_msg = '';
    foreach( $backtrace as $key => $trace_items ){
        if( $key == 0 ){
            $error_msg .= "backtrace:\n";
        }else{
            $backtrace_msg = @"\tlevel: $key\n\tfunction: {$trace_items['function']}\n\t\tfile: {$trace_items['file']}\n\t\tfile_line: {$trace_items['line']}\n".$backtrace_msg;
        }
    }
	$error_msg .= $backtrace_msg;
	
    if( defined('EMAIL_ERRORS') && EMAIL_ERRORS ){
        $from = 'system@example.com';
        if(defined('EMAIL_SYSTEM_FROM_ADDRESS')){
            $from = EMAIL_SYSTEM_FROM_ADDRESS;
        }
        //send error report to administrator
        sendEmail($from, EMAIL_ERROR_ADDRESS, SITE_SHORTNAME . ' (error notice)', $error_msg);
    }
    if( defined('LOG_ERRORS') && LOG_ERRORS ){
        logError($error_msg);
    }

    //if we're at parse time, print and die.
    if( $parsing ){
        echo $error_msg . "\n";
        if( $die ){
            die($error_string);
        }
    }

    if( defined('IS_RPC') && IS_RPC ){
        if( $die ){
            //if we're in RPC, return a JSON object with an "error" property, die
            // create a new instance of JSON
            require_once(THIRD_PARTY_PATH .'/JSON.php');
            $json = new JSON();

            print $json->encode(array('error' => $user_message));
            die();
        }
    }else{
        //if we're "in the browser", clear the buffer and display an error page, die

        if( $die ){
            if( 0 < ob_get_level() ){
                ob_end_clean();
            }
            global $theme;
            $title = gettext("Ooops! Something went wrong!");
            $content = $user_message;
            if( empty($theme) ){
                $theme = THEME_PATH . '/' . DEFAULT_THEME;
            }
            include($theme . '/error.template.php');
            die();
        }else{
            //print($user_message);
        }
    }
    //echo $error_msg." (default error notice)\n";
}


/**
 *  Makes a print_r debug of an object (or whatever) and formats it.
 */
function text_debug_r($object, $title = '')
{
    ob_start();
        $content = '';
        if(!empty($title)){
            $content .= "$title\n";
        }
        print_r($object);
        $content .= ob_get_contents();
    ob_end_clean();
    return $content;
}


/**
 * Logs an error message
 */
function logError($message)
{
    $errorFile = GEN_LOG_PATH . '/errors.log';
    if( file_exists($errorFile) ){
        $write_mode = 'a';
		$prefix = '';
    }else{
        $write_mode = 'w';
		$prefix = "---\n\n";
    }

    if( $fp = fopen($errorFile, $write_mode) ){
		$msg = $prefix.$message;
		$msg .= "\n---\n\n";
        if( fwrite($fp, $msg) ){
            //print no output about saving to log
        }else{
            die( "s2a: could not save to file $errorFile. Please check file/folder permissions.\n" );
        }
        fclose($fp);
    }else{
        die( "s2a: could not open file $errorFile. Please check file/folder permissions.\n" );
    }
}

function log_r( $debug_object, $debug_message='' )
{
    $errorFile = GEN_LOG_PATH . '/debugging.log';
    if(file_exists($errorFile)){
        $write_mode = 'a';
    } else {
        $write_mode = 'w';
    }

    if($fp = fopen($errorFile, $write_mode)) {
		$message = $debug_message."\n ".print_r( $debug_object, true );
        $msg = str_replace("\n", "\r\n", $message); //convert unix line-endings to windows line-endings
        $msg .= "\n- - - - -\n\n";
        if(fwrite($fp, $msg)){
            //print no output about saving to log
        } else {
            die( "s2a: could not save to file $errorFile. Please check file/folder permissions.\n" );
        }
        fclose($fp);
    } else {
        die( "s2a: could not open file $errorFile. Please check file/folder permissions.\n" );
    }
}

function echo_r( $debug_object, $debug_message='' ){
	echo $debug_message."\n ".print_r( $debug_object, true );
}

function trace($message, $title = '', $get_backtrace = false)
{
    $backtrace_msg = '';
    if($get_backtrace){
        $backtrace = debug_backtrace();
        foreach($backtrace as $key => $trace_items){
            if($key == 0){
                $message .= "\nbacktrace:\n";
            } else {
                $message .= @"($key) function {$trace_items['function']}, file {$trace_items['file']},  line {$trace_items['line']}\n";
            }
        }
    }

    if(defined('EXEC_STATE') && EXEC_STATE == 1){
        if(defined('TRACE_RUNTIME') && TRACE_RUNTIME){
            static $traceFileName = 'init';
            $moreInfo = '';
            if('init' == $traceFileName){
                $traceFileName = GEN_LOG_PATH . '/trace_'.date('Y-m-d_H-i-s').'.txt';
                if(!empty($_SERVER['REQUEST_URI'])){
                    $moreInfo .= "REQUEST_URI: {$_SERVER['REQUEST_URI']}\n";
                }
                if(!empty($_GET)){
                    $moreInfo .= "\nGET: ".print_r($_GET, true);
                }
            }

            if($fp = fopen($traceFileName, 'a')) {
                $message = indent_print_r($message, false, $title);
                $message = $moreInfo ."\n" . $message;
                $message = str_replace("\n", "\r\n", $message); //convert unix line-endings to windows line-endings
                if(fwrite($fp, $message)){
                    //print no output about saving to log
                } else {
                    die( "trace: could not save to file $traceFileName. Please check file/folder permissions.\n" );
                }
                fclose($fp);
            } else {
                die( "trace: could not open file $traceFileName. Please check file/folder permissions.\n" );
            }
        }
    } else {
        echo indent_print_r($message, false, $title) . "\n";
    }
}


/**
 *  Replaces certain date format placeholders with the user's local date and time format strings
 */
function TranslateLocalDateSQLFormats($SQL)
{
    //change these to ISO formats
    static $dateFormats = null;

    //populate it if this is the first time the function is run
    if(empty($dateFormats)){
        global $User;
        if(!empty($User)){
            //get formats from user object
            $dateFormats = array(
                'GET_FORMAT(DATETIME,/*localDateTime*/\'ISO\')' => '\''.$User->getDateFormat('dateTimeDB').'\'',
                'GET_FORMAT(DATE,/*localDate*/\'ISO\')' => '\''.$User->getDateFormat('dateDB').'\'',
                'GET_FORMAT(TIME,/*localTime*/\'ISO\')' => '\''.$User->getDateFormat('timeDB').'\'',
            );
        } else {
            //get formats from default settings
            $dateFormats = array(
                'GET_FORMAT(DATETIME,/*localDateTime*/\'ISO\')' => '\''.GetDefaultDateFormat('dateTimeDB').'\'',
                'GET_FORMAT(DATE,/*localDate*/\'ISO\')' => '\''.GetDefaultDateFormat('dateDB').'\'',
                'GET_FORMAT(TIME,/*localTime*/\'ISO\')' => '\''.GetDefaultDateFormat('timeDB').'\'',
            );
        }
    }
    $SQL = strtr($SQL, $dateFormats);
    return $SQL;
}

function reduce_whitespace($string)
{
    $string = str_replace(array("\n", "\r", "\t"), ' ', $string);
    while(false !== strpos($string, '  ')){
        $string = str_replace('  ', ' ', $string);
    }
    return $string;
}

/**
 * Custom exception handler which hands over to handleError().
 *
 * @param Exception $exception 
 */
function handleException($exception)
{
//    handleError(E_USER_ERROR, $exception->getMessage(), 'exception', 0);

    $user_message = '';
    if(defined('EXEC_STATE') && EXEC_STATE == 1){
        $parsing = false;
        global $User;
        $happenedTo = '';
        if(!empty($User)){
            $happenedTo .= "{$User->DisplayName} (ID {$User->PersonID})";
        }
        $happenedTo .= ", IP {$_SERVER['REMOTE_ADDR']})";

        $user_message = '<b>'.gettext("An error occurred while processing your request.")."</b>\n";
        $user_message .= gettext("Since all errors are logged and administrators are automatically notified, it is likely that we are already working on it.

        You can help us by %sfiling an error report%s, explaining what you were trying to do, and what you would have expected to see. Please be as specific as you can.");
        $bugtracker_link = '<b><a href="'.BUG_REPORT_LINK.'" target="_blank">';
        $user_message = nl2br(sprintf($user_message, $bugtracker_link, '</a></b>'));
    } else {
        $parsing = true;
        $happenedTo = 's2a generator';
    }

    global $ModuleID;
    global $recordID;
    global $ScreenName;
    $appInfo = '';
    if(!empty($ModuleID)){
        $appInfo = "Module '$ModuleID',";
    }
    if(!empty($recordID)){
        $appInfo .= "Record '$recordID',";
    }
    if(!empty($ScreenName)){
        $appInfo .= "ScreenName '$ScreenName',";
    }

    $moreInfo = '';
    if(!empty($_SERVER['REQUEST_URI'])){
        $moreInfo .= "REQUEST_URI: {$_SERVER['REQUEST_URI']}\n";
    }
    if(!empty($_GET)){
        $moreInfo .= "\nGET: ".print_r($_GET, true);
    }

    $date = date('r');
    $error_msg = "An exception of type ".get_class($exception)." happened to $happenedTo at $date.\n";
    $error_msg .= "$appInfo File ".$exception->getFile().", line ".$exception->getLine().":\n".$exception->getMessage()."\n";
    $error_msg .= $moreInfo."\n";

    $error_msg .= "Backtrace:\n".$exception->getTraceAsString();
    /*$backtrace = debug_backtrace();

    foreach($backtrace as $key => $trace_items){
        if($key == 0){
            $error_msg .= "backtrace:\n";
        } else {
            $error_msg .= @"($key) function {$trace_items['function']}, file {$trace_items['file']},  line {$trace_items['line']}\n";
        }
    }*/

    if(defined('EMAIL_ERRORS') && EMAIL_ERRORS){
        $from = 'system@example.com';
        if(defined('EMAIL_SYSTEM_FROM_ADDRESS')){
            $from = EMAIL_SYSTEM_FROM_ADDRESS;
        }
        //send error report to administrator
        sendEmail($from, EMAIL_ERROR_ADDRESS, SITE_SHORTNAME . ' (error notice)', $error_msg);
    }
    if(defined('LOG_ERRORS') && LOG_ERRORS){
        logError($error_msg);
    }

    //if we're at parse time, print and die.
    if($parsing){
        echo $error_msg . "\n";
        die($error_string);
    }

    if( defined('IS_RPC') && IS_RPC ){
        //if we're in RPC, return a JSON object with an "error" property, die
        // create a new instance of JSON
        require_once(THIRD_PARTY_PATH .'/JSON.php');
        $json = new JSON();

        print $json->encode(array('error' => $user_message));
    }else{
        //if we're "in the browser", clear the buffer and display an error page, die
        if(0 < ob_get_level()){
            ob_end_clean();
        }
        global $theme;
        $title = gettext("Ooops! Something went wrong!");
        $content = $user_message;
        if(empty($theme)){
            $theme = THEME_PATH . '/' . DEFAULT_THEME;
        }
        include($theme . '/error.template.php');
    }
}


?>
