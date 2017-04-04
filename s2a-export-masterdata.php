<?php
/**
 * Exports the contents of one or several (or all) master modules to files.
 *
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
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
define('EXEC_STATE', 2);

$script_location = realpath(dirname(__FILE__).'');

// defines command-line options
$config = array();
$config['match'] =
    array('short'   => 'm',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'A wildcard expression that matches the IDs of the modules to export data from. Use "%" or "_" as wildcard characters ("%" for matching multiple characters, "_" for matching a single character) "*" or "?" may also be used but may be trapped by the shell. Examples: %, ac%, act',
        'default' => '%'
    );
$config['outdir'] =
    array('short'   => 'd',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'The destination directory for files to be written.',
        'default' => 'install/master'
    );
$config['codetypes'] =
    array('short'   => 'c',
        'min'     => 1,
        'max'     => 1,
        'desc'    => 'The code types to be exported, if the Codes (cod) module is matched by the -m argument. You may specify single ID numbers and ranges (lower range limit, dash, upper limit), separated by commas. Use no spaces anywhere. Example: 1,5,10-15,20-30',
        'default' => ''
    );
$config['recordIDs'] =
    array('short'   => 'r',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'Includes record ID fields in the export. This is not recommended for an export intended to be imported in a different Active Agenda site, unless the record IDs are known to be the same in the receiving site\'s database.'
    );

//handles command-line options and general setup
include $script_location . '/lib/includes/cli-startup.php';

//getting the passed parameters
$ModuleMatch            = $args->getValue('match');
$Outdir                 = $args->getValue('outdir');
$CodeTypes              = $args->getValue('codetypes');
$ExportRecordIDFields   = $args->getValue('recordIDs');

if(empty($ModuleMatch)){
    $ModuleMatch = '%';
}
$ModuleMatch = str_replace(array('*','?'), array('%','_'), $ModuleMatch);


print "s2a-export-masterdata: module match = $ModuleMatch\n";

if(empty($Outdir)){
    $fileDir = S2A_FOLDER.'/install/master';
} else {
    $fileDir = $Outdir;
}

if(!file_exists($fileDir)){
    if(!prompt("The directory $fileDir does not exist. Create it now?")){
        die("Exited.\n");
    }
    $success = mkdir($fileDir, 0744, true);
    if(!$success){
        die("Could not create directory '$fileDir'.\n");
    }
}

//converts CodeTypes expression to list of ID values
$codeTypeIDs = array();
if(!empty($CodeTypes)){
    $ct_ranges = explode(',', $CodeTypes);
    foreach($ct_ranges as $ct_range){
        $ct_limits = explode('-', $ct_range);
        $ct_range_start = reset($ct_limits);
        $ct_range_end = end($ct_limits);
        if(!is_numeric($ct_range_start) || !is_numeric($ct_range_end)){
            die("Code Type range '$ct_range' is invalid\n");
        }
        $codeTypeIDs = array_merge($codeTypeIDs, range($ct_range_start, $ct_range_end));
    }
    $codeTypeIDs = array_unique($codeTypeIDs);
    sort($codeTypeIDs);
}

//sql snip to get master data modules (modify to match against $ModuleMatch)
if('%' == $ModuleMatch){
    $limiter = '';
} else {
    $limiters = array();
    $sqlMatches = explode(',', $ModuleMatch);
    foreach($sqlMatches as $sqlMatch){
        $sqlMatch = trim($sqlMatch);
        if(false !== strpos($sqlMatch, '%') || false !== strpos($sqlMatch, '_')){
            $expr = 'LIKE';
        } else {
            $expr = '=';
        }
        $limiters[] = "`mod`.ModuleID $expr '$sqlMatch' ";
    }
    $limiter = ' AND ('.join(' OR ', $limiters) . ')';
}

$mdb2 =& GetMDB2();

$sql = "SELECT ModuleID FROM `mod` WHERE MasterData = 1 {$limiter}AND _Deleted = 0 ORDER BY ModuleID";
$filterModules = $mdb2->queryCol($sql);
mdb2ErrorCheck($filterModules);

if(empty($moduleFilter)){
    $masterModules = $filterModules;
} else {
    $sql = "SELECT ModuleID FROM `mod` WHERE MasterData = 1 AND _Deleted = 0 ORDER BY ModuleID";
    $masterModules = $mdb2->queryCol($sql);
    mdb2ErrorCheck($masterModules);
}

if(count($masterModules) == 0){
    die("No master data modules matched $ModuleMatch\n");
}


foreach($filterModules as $moduleID){
    print "Processing module '$moduleID'.\n";

    $selectedFields = array();
    $blockedFields = array();

    //get moduleFields
    $moduleFields = GetModuleFields($moduleID);
	
	//get resolvable field info
    $resolvablePath = GENERATED_PATH."/$moduleID/{$moduleID}_Resolvable.gen";
    $resolvableFields = null; //re-initialize
    include $resolvablePath; //sets $resolvableFields

    //determine saveable fields, substituting resolvable fields for ID fields where possible
    

    foreach($resolvableFields as $fieldName => $resolveInfo){
        //if the foreign module of the resolvable field (look up by modulefield) is a master data module, include the resolvable field
        $foreignModuleID = $moduleFields[$fieldName]->getForeignModuleID();
        if('cod' == $foreignModuleID || isset($masterModules[$foreignModuleID])){
            $selectedFields[] = $fieldName;
            $blockedFields[] = $resolveInfo['resolvesTo'];
            //print "added $fieldName\n";
        }
    }
    foreach($moduleFields as $fieldName => $moduleField){
        //include remotefields, tablefields with names not ending in "ID" or blocked.
        switch(strtolower(get_class($moduleField))){
        case 'tablefield':
        case 'remotefield':
            //continues below
            break;
        default:
            //skips to next field in $moduleFields
            continue 2; //the 2 is necessary: the first level is the switch.
        }

        if(in_array($fieldName, $blockedFields)){
            continue;
        }
        if('cod' == $moduleID && in_array($fieldName, array('CodeTypeID', 'CodeID'))){
            $selectedFields[] = $fieldName;
            continue;
        }
        if('codt' == $moduleID && 'CodeTypeID' == $fieldName){
            $selectedFields[] = $fieldName;
            continue;
        }
		if( empty( $ExportRecordIDFields ) ){
			if('ID' == substr($fieldName, -2)){
				continue;
			}
		}		
        if('_ModDate' == $fieldName ){
            continue;
        }
        if('_ModBy' == $fieldName ){
            continue;
        }
		if('_Deleted' == $fieldName ){
            continue;
        }
		if('_TransactionID' == $fieldName ){
            continue;
        }
		if('_GlobalID' == $fieldName ){
            continue;
        }
        $selectedFields[] = $fieldName;
    }

    //trace($selectedFields, 'selected fields for export');
    if(count($selectedFields) == 0){
        trigger_error("The module '$moduleID' did not contain any exportable fields suitable for a master data module. Is it really a master data module?", E_USER_WARNING);
        continue;
    }

    $selects = array();
    $joins = array();
    $SQLBaseModuleID = $moduleID;
    foreach($selectedFields as $fieldName){
        $selects[] = GetSelectDef($fieldName, $moduleID);
        $joins = array_merge($joins, GetJoinDef($fieldName, $moduleID));
    }

    $sql = 'SELECT ' . join(', ', $selects)."\n FROM `$moduleID`\n";
    if(count($joins) > 0){
        $joins = SortJoins($joins);
        $sql .= join("\n", $joins);
    }
    $sql .= "\n WHERE `$moduleID`._Deleted = 0";

    if('cod' == $moduleID){
        if(0 == count($codeTypeIDs)){
            $ct_sql = "SELECT CodeTypeID FROM codt WHERE _Deleted = 0 ORDER BY CodeTypeID";
            $codeTypeIDs = $mdb2->queryCol($ct_sql);
            mdb2ErrorCheck($codeTypeIDs);
        }
        foreach($codeTypeIDs as $codeTypeID){
            $c_sql = $sql . " AND CodeTypeID = '$codeTypeID' ORDER BY CodeID";
            $codeData = $mdb2->queryAll($c_sql);
            mdb2ErrorCheck($codeData);
            if(count($codeData)){
                $filePath = $fileDir.'/'.$moduleID.'_'.$codeTypeID.'_master.csv';
                SaveCSVFile($codeData, $selectedFields, $filePath);
            } else {
                print "Warning: No codes found for code type $codeTypeID.\n";
            }
        }
    } else {
        $masterData = $mdb2->queryAll($sql);
        mdb2ErrorCheck($masterData);
        $filePath = $fileDir.'/'.$moduleID.'_master.csv';

        SaveCSVFile($masterData, $selectedFields, $filePath);
    } 
}

print "Finished.\n";

function SaveCSVFile($data, $headers, $filePath)
{
        $fileHandle = fopen($filePath, 'w');
        if(!$fileHandle){
            die("Could not open file $filePath for writing.\n");
        }
        $result = fputcsv($fileHandle, $headers);
        if(false === $result){
            die("Could not write data to $filePath.\n");
        }
        foreach($data as $row){
            $result = fputcsv($fileHandle, $row);
            if(false === $result){
                die("Could not write more data to $filePath.\n");
            }
        }
        fclose($fileHandle);
}

//end file s2a-export-masterdata.php