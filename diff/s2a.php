<?php
/**
 * Utility to (re-)generate all modules, or thise that match the supplied criteria
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
 * @version        SVN: $Revision: 1647 $
 * @last-modified  SVN: $Date: 2009-05-22 23:49:45 +0200 (Pt, 22 maj 2009) $
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
$config['match'] =
    array('short'   => 'm',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'A wildcard expression that matches the IDs of the modules to generate. Use "%" or "_" as wildcard characters ("%" for matching multiple characters, "_" for matching a single character) "*" or "?" may also be used but may be trapped by the shell. Examples: %, ac%, act',
        'default' => '%'
    );
$config['startat'] =
    array('short'   => 's',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'Within the matched module IDs, skip all modules prior to the module ID specified here.',
        'default' => 'optional'
    );
$config['listsource'] =
    array('short'   => 'l',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'Obsolete parameter, retained for backwards compatibility only. Does nothing anymore.',
        'default' => 'db'
    );
$config['debug'] =
    array('short'   => 'd',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'When to generate separate debug logs for modules. Options: \'always\', \'onfail\', \'never\'',
        'default' => 'onfail'
    );
$config['gen-info'] =
    array('short'   => 'gi',
        'min'     => 0,
        'max'     => 0,
        'default' => 'no',
        'desc'    => 'Display extra info on the generation of each module.'
    );
$config['cleartriggers'] =
    array('short'   => 't',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'Removes all triggers before starting the generate procedure. Options: \'yes\', \'no\'',
        'default' => 'yes'
    );
$config['no-upgrade'] =
    array('short'   => 'nu',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'Skips the upgrade check.'
    );
$config['no-autogen'] =
    array('short'   => 'na',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'Disables the automatic generating of modules that appear to be able to solve a generating error.'
    );
$config['export-masterdata'] =
    array('short'   => 'em',
        'min'     => 0,
        'max'     => 0,
        'default' => 'no',
        'desc'    => 'Exports master data to files.'
    );
$config['no-merged-xml'] =
    array('short'   => 'nm',
        'min'     => 0,
        'max'     => 0,
        'default' => 'false',
        'desc'    => 'Whether to suppress writing the resulting XML of applied module add-ons to a file.'
    );
$config['no-master-data-check'] =
    array('short'   => 'nc',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'Whether to skip the verification that all master data modules contain some data.'
    );

//handles command-line options and general setup
include $script_location . '/lib/includes/cli-startup.php';

//getting the passed parameters
$Match               = $args->getValue('match');
$StartAt             = $args->getValue('startat');
$Debug               = $args->getValue('debug');
$ClearTriggers       = $args->getValue('cleartriggers');
$GenInfo             = $args->getValue('gen-info');
$NoUpgrade           = $args->getValue('no-upgrade');
$NoAutogen           = $args->getValue('no-autogen');
$NoMergeExport       = $args->getValue('no-merged-xml');
$ExportMasterData    = $args->getValue('export-masterdata');
$SkipMasterDataCheck = $args->getValue('no-master-data-check');

global $Project; //this is used globally by patches
global $ExportMasterData;
global $NoAutogen;

//clean them up
if(empty($Match)){
    $Match = '%';
}

$Match = str_replace(array('*','-'), array('%',''), $Match);

if(empty($Debug)){
    $Debug = 'onfail';
} else {
    $Debug = trim(strtolower($Debug));
    $opts = array('onfail', 'always', 'never');
    if(! in_array($Debug, $opts) ){
        die("'debug' option accepts only one of the following: ".join(', ', $opts)."\n");
    }
}


$bClearTriggers = false;
if(!empty($ClearTriggers)){
    if(trim(strtolower($ClearTriggers)) == 'yes'){
        $bClearTriggers = true;
    }
}



print "\n";
print "\n";
print "****************************************\n" ;
print "* s2a: Batch module generating utility *\n" ;
print "****************************************\n" ;
print "s2a: project is $Project\n";
print "s2a: generating modules matching '$Match'";
if(!empty($StartAt)){
    print " from $StartAt and forward";
}

print "\n\n";




/*********************************
 *   INITIALIZATION & SETUP      *
 *********************************/

global $moduleList; //list of modules that will be generated
$moduleList = array();

$start_time = getMicroTime();

//verify that memory limit is not too low or generating won't work properly
checkMemoryLimit('64M');

//database connect:
//connects with super-user privileges - regular user should have no permission to change table structure
require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class
global $dbh;
$dbh = DB::connect(GEN_DB_DSN);
dbErrorCheck($dbh);

//start log file
saveLog('s2a starting at '.date('r')."\n", true);
saveLog('arguments: ' . join($_SERVER['argv'], ' ')."\n");

//check patches
if(!$NoUpgrade){
    CheckPatches();
}

//clears triggers if requested
if($bClearTriggers){
    print "Clearing all trigger files. Make sure to run a full generation job in order to re-create them.\n";
    $triggerPattern = '*Triggers.gen';
    $arr = glob(GENERATED_PATH.'/*/'.$triggerPattern);

    $errors = '';
    print "\n";
    foreach($arr as $filepath){
        print '.';
        if(!unlink($filepath)){
            $errors .= "Can't remove file $filepath\n";
        }
    }

    $triggerPattern = '*RDCUpdate.gen';
    $arr = glob(GENERATED_PATH.'/*/'.$triggerPattern);
    print "\n";
    foreach($arr as $filepath){
        print '.';
        if(!unlink($filepath)){
            $errors .= "Can't remove file $filepath\n";
        }
    }
    print "\n";
    if(!empty($errors)){
        print $errors;
    }

}

print "s2a: Getting list of module definition files... ";
$file_ParseList = array();
$str_fileMatch = str_replace('%', '*', $Match);
$fileMatches = explode(',', $str_fileMatch);
foreach($fileMatches as $fileMatch){
    $fileMatch = trim($fileMatch);
    $arr = glob(XML_PATH.'/'.$fileMatch.'ModuleDef.xml');
    foreach($arr as $filepath){
        $file_ParseList[basename($filepath, 'ModuleDef.xml')] = 'default';
    }

    //overrides with custom modules, if any
    if(defined('CUSTOM_XML_PATH')){
        $arr = glob(CUSTOM_XML_PATH.'/'.$fileMatch.'ModuleDef.xml');
        if(count($arr) > 0){
            foreach($arr as $filepath){
                $file_ParseList[basename($filepath, 'ModuleDef.xml')] = 'custom';
            }
        }
    }
}
print "Done.\n";

MakeCentralAddOnMap(true);

if('%' == $Match){
    $limiter = '';
} else {
    $limiters = array();
    $sqlMatches = explode(',', $Match);
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


$SQL = "SELECT `mod`.ModuleID, Avg(Duration) AS AvgDuration FROM `mod` LEFT OUTER JOIN modgt ON `mod`.ModuleID = modgt.ModuleID WHERE 1=1 $limiter GROUP BY `mod`.ModuleID ORDER BY ModuleID";

print "s2a: Getting estimates for module generating times... ";

$mdb2 =& GetMDB2();
$mdb2->loadModule('Extended', null, false); //in order to use getAssoc() below
$moduleList = $mdb2->getAssoc($SQL);

$errcodes = mdb2ErrorCheck($moduleList, false, false, MDB2_ERROR_NOSUCHTABLE);
switch($errcodes['code']){
case 0:
    break;
case MDB2_ERROR_NOSUCHTABLE:
    $moduleList = array();
    break;
default:
    mdb2ErrorCheck($moduleList); //handles unknown errors
    break;
}

print "Done.\n";

if(!empty($file_ParseList)){
    //look for module files that have been removed
    $modulesNotInFolder = array_diff(
        array_keys($moduleList),
        array_keys($file_ParseList)
    );
    if(count($modulesNotInFolder) > 0){
        print "Matched module defs not in folder\n";
        print join(
            $modulesNotInFolder,
            ', '
        );
        print "\n";
        if(prompt("The list above contains module definitions that have been removed from the folder but are still in the database.\nWould you like to SKIP these modules and continue generating? (Answering 'n' will exit the program)")){

            //remove $modulesNotInFolder from $moduleList
            foreach($modulesNotInFolder as $moduleNotInFolder){
                unset($moduleList[$moduleNotInFolder]);
            }

            if(prompt("The module definitions may have been removed because these modules are obsolete.\nWould you like to UNINSTALL these modules?\n(This will irrevocably delete any data in the database tables of these modules.)")){
                define('REMOVE_MODULE_INCLUDED', true); //this is used by the included file
                foreach($modulesNotInFolder as $uninstall_moduleID){
                    include(S2A_FOLDER . '/s2a-remove-module.php');
                }
            }

        } else {
            print "Exiting...\n";
            exit;
        }
    }

    //look for new module files
    $modulesNotInDB = array_diff(
        array_keys($file_ParseList),
        array_keys($moduleList)
    );

    if(count($modulesNotInDB) > 0){
        print "\n";
        print "Matched module defs not in database\n";
        print join(
            $modulesNotInDB,
            ','
        );
        print "\n";

        if(prompt("The list above contains new module definitions. Would you like to install them?")){
            $newModuleList = array_flip($modulesNotInDB); //makes the moduleIDs into index keys once again
            foreach($newModuleList as $newModuleID => $dummyValue){
                $newModuleList[$newModuleID] = null;
            }
            $moduleList = array_merge($newModuleList, $moduleList);
        } else {
            if(!prompt("Continue generating modules?")){
                print "Exiting...\n";
                exit;
            }
        }

    }
}

if(0 == count($moduleList)){
    print "No modules matched the expression '$Match'.\n";
    exit;
}

foreach($moduleList as $moduleID => $avgTime){
    if(isset($file_ParseList[$moduleID]) && 'custom' == $file_ParseList[$moduleID]){
        $moduleList[$moduleID] = array($avgTime, 'custom');
    }
}

$confirmModules = array(); 
$failedModules = GenerateModules($Project, $moduleList, $StartAt);

if(count($failedModules) > 0){
    foreach($failedModules as $failedModuleID => $errorMessage){
        if('confirm' == $errorMessage){
            $confirmModules[$failedModuleID] = false;
            unset($failedModules[$failedModuleID]);
        }
    }
}

print "Requires confirmation: ".count($confirmModules)."; Failed: ".count($failedModules)."\n";

if(count($confirmModules) > 0){
    $sql_change_files = glob(GEN_LOG_PATH.'/*_dbChanges.gen');

    print "\n";
    print " xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
    print "x                                                                    x\n";
    print "x  s2a: Potentially destructive table changes require confirmation.  x\n" ;
    print "x                                                                    x\n";
    print " xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
    print "\n";
    print "Changes to table structure that would DROP table fields, or\n";
    print "change data types so that loss of data could occur, were\n";
    print "NOT automatically applied but saved to the following files:\n";
    print "\n";
    foreach($sql_change_files as $sql_change_file){
        print "$sql_change_file\n";
    }
    print "\n";
    print "You can review and apply these changes right now, by\n";
    print "answering 'y', below.\n";
    print "\n";
    print "You may also do this later, by running the command-line \n";
    print "utility s2a-apply-table-changes.php, like this:\n";
    print "\n";
    print "cd [your s2a folder location]\n";
    print "php s2a-apply-table-changes.php\n";
    print "\n";
    print "If the PHP executable cannot be found, you must specify\n";
    print "its location as you type the command:\n";
    print "\n";
    print "(Example)\n";
    print "cd [your s2a folder location]\n";
    print "c:\php4\php s2a-apply-table-changes.php\n";
    print "(Substitute c:\php4\php with the absolute path to the\n";
    print "PHP executable on your system.)\n";
    print "\n";
    print "\n";

    if(prompt("Would you like to review and apply the table changes now?")){
        define('APPLY_TABLE_CHANGES_INCLUDED', true); //this is used by the included file
        include_once(S2A_FOLDER . '/s2a-apply-table-changes.php');
    }

    //re-generate the confirmed modules
    $confirmedModules = array();
    $skippedModules = array();
    foreach($confirmModules as $confirm_moduleID => $confirmed){
        if($confirmed){
            $confirmedModules[$confirm_moduleID] = $moduleList[$confirm_moduleID];
        } else {
            $skippedModules[] = $confirm_moduleID;
        }
    }

    $failedConfimedModules = array();
    if(count($confirmedModules) > 0){
        print "Regeneating modules with confirmed (and applied) table changes.\n";
        $failedConfimedModules = GenerateModules($Project, $confirmedModules);
    }

    //transfer failed modules from this round to the failedModules retry list.
    if(count($failedConfimedModules) > 0){
        foreach($failedConfimedModules as $failedModuleID => $errorMessage){
            $failedModules[$failedModuleID] = $errorMessage;
        }
    }

    if(count($skippedModules) > 0){
        print "\n";
        print "NOTE: Table changes in the following were skipped:\n";
        print join(', ', $skippedModules)."\n".
        print "You may need to address these discrepancies somehow.\n";
        print "\n";
    }
}


if(count($failedModules) > 0){
    print "\n";
    print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
    print "x s2a: Some modules were not generated correctly: x\n" ;
    print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
    print "\n";
    print join("\n\n", $failedModules);
    print "\n";
    print "Just the moduleIDs: ". join(',', array_keys($failedModules));
    print "\n";
    print "To analyze why a module did not generate correctly, look into the log files\nat".GEN_LOG_PATH.".\n";

    print "\n";
    print "When generating many modules, errors might occur because of dependencies\n";
    print "with changes to modules that haven't been generated at the time of the error.\n";
    print "Therefore, it can be useful to try re-generating the failed modules again.\n";
    if(prompt("Would you like to retry the failed modules once?")){
        $retryList = array();
        foreach($failedModules as $failedModuleID => $errorMessage){
            $retryList[$failedModuleID] = $moduleList[$failedModuleID];
        }
        $failedModules = GenerateModules($Project, $retryList);
        if(count($failedModules) > 0){
            print "\n";
            print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
            print "x s2a: Some modules were STILL not generated correctly: x\n" ;
            print "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx\n" ;
            print "\n";
            print join("\n\n", $failedModules);
        }
    }


} else {
    print "\n";
    print "+-------------------------------------------------------+\n" ;
    print "| s2a: All selected modules were successfully generated |\n" ;
    print "+-------------------------------------------------------+\n" ;
}

$end_time = getMicroTime();
$duration = round($end_time - $start_time, 2);
$minutes = floor($duration/60);
$seconds = $duration % 60;
print "\n";
print "Total generating time: $minutes minutes $seconds seconds ($duration s)\n" ;
print "\n";
print "\n";


//check for missing master data here
if(!$SkipMasterDataCheck){
    print "Checking generated modules for missing master data.\n";

    $generatedModules = array_keys($moduleList);

    //get lists of master data modules, dependencies and code dependencies
    $sql = 'SELECT ModuleID FROM `mod` WHERE _Deleted = 0 AND MasterData = 1 AND ModuleID IN (\''.join('\',\'', $generatedModules).'\')';
    //trace($sql);
    $generatedMasterModules = $mdb2->queryCol($sql);
    mdb2errorCheck($generatedMasterModules);

    //trace($generatedMasterModules, 'generatedMasterModules');

    $sql = 'SELECT d.ModuleID FROM `mod` AS m INNER JOIN `modd` AS d ON (m.ModuleID = d.ModuleID AND d._Deleted = 0) WHERE m.MasterData = 1 AND d.DependencyID IN (\''.join('\',\'', $generatedModules).'\')';
    $dependedMasterModules = $mdb2->queryCol($sql);
    mdb2errorCheck($dependedMasterModules);
    //trace($dependedMasterModules, 'dependedMasterModules'); //does modd contain "too many" dependencies? 

    $affectedMasterModules = array_merge($generatedMasterModules, $dependedMasterModules);
    $affectedMasterModules = array_unique($affectedMasterModules);
    sort($affectedMasterModules);

    //removes any failed modules
    $affectedMasterModules = array_diff($affectedMasterModules, array_keys($failedModules));
    //trace($affectedMasterModules, 'affectedMasterModules');

    $modulesMissingData = array();
    foreach($affectedMasterModules as $affectedMasterModuleID){
        $sql = "SELECT COUNT(*) FROM `$affectedMasterModuleID` WHERE _Deleted = 0";
        //trace($sql);
        $nRows = $mdb2->queryOne($sql);
        mdb2errorCheck($nRows);
        if(0 == $nRows){
            $modulesMissingData[] = $affectedMasterModuleID;
        }
    }
    //trace($modulesMissingData, 'modulesMissingData');

    //checks code dependencies, codes for missing data
    $sql = 'SELECT d.CodeTypeID FROM `mod` AS m INNER JOIN `codtd` AS d ON (m.ModuleID = d.DependencyID AND d._Deleted = 0) WHERE m.ModuleID IN (\''.join('\',\'', $generatedModules).'\')';
    $dependedCodeTypes = $mdb2->queryCol($sql);
    mdb2errorCheck($dependedCodeTypes);
    $dependedCodeTypes = array_unique($dependedCodeTypes);
    sort($dependedCodeTypes);
    //trace($dependedCodeTypes);

    $sql = 'SELECT codt.CodeTypeID FROM codt LEFT OUTER JOIN `cod` ON (codt.CodeTypeID = cod.CodeTypeID AND cod._Deleted = 0) WHERE codt._Deleted = 0 AND cod.CodeID IS NULL AND codt.CodeTypeID IN (\''.join('\',\'', $dependedCodeTypes).'\')';
    //trace($sql);

    $codeTypesMissingData = $mdb2->queryCol($sql);
    mdb2errorCheck($codeTypesMissingData);

    $isMissingMasterModuleData = (0 < count($modulesMissingData));
    $isMissingCodes = (0 < count($codeTypesMissingData));

    if($isMissingMasterModuleData || $isMissingCodes){
        $message = "\n";
        $message .= "*************************\n";
        $message .= "** Missing Master Data **\n";
        $message .= "*************************\n";
        $strSpecifier = '';
        if($isMissingCodes){
            $strSpecifier = 'code types';
        }
        if($isMissingCodes && $isMissingMasterModuleData){
            $strSpecifier .= ' and ';
        }
        if($isMissingMasterModuleData){
            $strSpecifier .= 'master data modules';
        }

        $message .= "The following $strSpecifier (listed below) contain no data. This affects the ability to enter data into the generated modules because choices in some drop-lists will be unavailable. You may enter data manually into these $strSpecifier, or you may import data from CSV files located in the s2a/install/master directory. You may also prepare custom CSV files for import, using the supplied files as examples.\n";
        print wordwrap($message);

        if($isMissingCodes){
            print "\nCode types related to the generated modules contain no data:\n";
            print wordwrap(join(',',$codeTypesMissingData)."\n");
        }
        if($isMissingMasterModuleData){
            print "\nMissing master module data:\n";
            print wordwrap(join(',',$modulesMissingData)."\n");
        }
        print "\n";
        print "The command for importing a single CSV data file is:\n";
        print "php s2a-import-data.php -f <path/to/file> -m <moduleID>\n\n";
    } else {
        print "No missing master data was detected.\n\n";
    }
}



/****************
*   FUNCTIONS   *
****************/

//parses a module with a seperate process
function CreateModule($Project, $moduleID){
    static $SaveNavigationPhrases = true;
    global $Debug;
    global $ExportMasterData;
    global $GenInfo;
    global $NoMergeExport;

    $SaveNavigationPhrases_switch = '';
    if($SaveNavigationPhrases){
        $SaveNavigationPhrases_switch = '-np';
    }

    $ExportMasterData_switch = '';
    if($ExportMasterData){
        $ExportMasterData_switch = '-em';
    }

    $GenInfo_switch = '';
    if($GenInfo){
        $GenInfo_switch = '-gi';
    }

    $NoMergeExport_switch = '';
    if($NoMergeExport){
        $NoMergeExport_switch = '-nm';
    }

    $central_addons_switch = '-ca';

    $phpcmd = findPHPexe();

    $command = $phpcmd .' '. S2A_FOLDER."/s2a-generate-module.php -p $Project -m $moduleID $SaveNavigationPhrases_switch $ExportMasterData_switch $GenInfo_switch $central_addons_switch $NoMergeExport_switch 2>&1";
    //trace($command, '$command');

    $SaveNavigationPhrases = false;

    $cmdHandle = popen($command, 'r');
    $datestamp = date('Y-m-d_H-i-s');
    $dumpFile = GEN_LOG_PATH . '/debug_'.$moduleID.'_'.$datestamp.'.log';
    $dumpHandle = fopen($dumpFile, 'w');

    if(!$cmdHandle){
        print " ERROR:\nCould not run command $command\n";
    }
    if(!$dumpHandle){
        print " ERROR:\nCould not open file $datestamp for writing\n";
    }
    $line = '';
    while(!feof($cmdHandle)){
        $prevLine = $line;
        $line = fgets($cmdHandle, 4096); //reads line by line
        fwrite($dumpHandle, $line);
    }

    pclose($cmdHandle);
    fclose($dumpHandle);

    if(empty($line)){
        $lastLine = trim($prevLine);
    } else {
        $lastLine = trim($line);
    }
    saveLog(str_pad($moduleID, 6, ' ').': '.$lastLine . "\n");

    if(false === strpos($lastLine, 'generate-module: Created module')){

        if(false !== strpos($lastLine, 'CONFIRMATION REQUIRED')){
            print "\n$lastLine\n";

            if('always' != $Debug){
                clearLog($moduleID);
            }
            return 'confirm';
        } else {

            print " ERROR:\n $lastLine\n";
            $error_msg = "s2a: module '$moduleID' failed with the error message: \n $lastLine";

            switch($Debug){
            case 'onfail':
            case 'always':
                if(!defined('DEBUG_LOG_FORMAT') || DEBUG_LOG_FORMAT == 'win'){ //defaults to 'win'
                    $convFHr = fopen($dumpFile, 'r');
                    $convFHw = fopen($dumpFile.'.txt', 'w');
                    $line = '';
                    while(!feof($convFHr)){
                        $line = fgets($convFHr, 4096); //reads line by line
                        $line = str_replace("\n", "\r\n", $line); //convert unix line-endings to windows line-endings
                        fwrite($convFHw, $line);
                    }
                    fclose($convFHr);
                    fclose($convFHw);
                    unlink($dumpFile);
                }
                break;
            default:
                //don't keep the log
                clearLog($moduleID);
            }

            global $NoAutogen;
            if(!$NoAutogen && handleFailedModule($Project, $lastLine, $moduleID)){
                return ''; //all the cleanup stuff is done when handleFailedModule() calls CreateModule() the last time.
            }
        }

        return $error_msg;
    } else {
        $parseTime = substr($lastLine, strpos($lastLine, ' in ') + 4);
        $parseTime = str_replace("\r\n", '', $parseTime);
        print " SUCCESSFUL: $parseTime";

        if('always' != $Debug){
            clearLog($moduleID);
        }

        global $moduleList;
        $moduleList[$moduleID] = 'successful';

        return '';
    }
} //end function CreateModule


function saveLog($msg, $new = false){
    global $logFile;
    if($new){
        $datestamp = date('Y-m-d_H-i-s');
        $logFile = GEN_LOG_PATH . '/s2a'.$datestamp.'.log';
        $write_mode = 'w';
    } else {
        $write_mode = 'a';
    }

    if($fp = fopen($logFile, $write_mode)) {
        $msg = str_replace("\n", "\r\n", $msg); //convert unix line-endings to windows line-endings
        if(fwrite($fp, $msg)){
            //print no output about saving to log
        } else {
            die( "s2a: could not save to file $logFile. Please check file/folder permissions.\n" );
        }
        fclose($fp);
    } else {
        die( "s2a: could not open file $logFile. Please check file/folder permissions.\n" );
    }
} //end function saveLog



function clearLog($moduleID)
{
    $pattern = 'debug_'.$moduleID.'_*.log*';
    $arr = glob(GEN_LOG_PATH.'/'.$pattern);

    $errors = '';
    foreach($arr as $filepath){
        if(!unlink($filepath)){
            $errors .= "Can't remove file $filepath\n";
        }
    }
    if(!empty($errors)){
        print $errors;
    }
} //end function clearLog


function GenerateModules($project, $moduleList, $startAt = null)
{
    //start with the first module in the list, unless specified
    if(empty($startAt)){
        $startAt = reset(array_keys($moduleList));
    }
    $started = false;
    $nTotal  = count($moduleList);
    $nCurrent = 0;
    $failedModules = array();
    global $NoAutogen;

    foreach($moduleList as $module_id => $generating_info){
        $str_custom = '';
        if(is_array($generating_info)){
            $avg_time = $generating_info[0];
            if('custom' == $generating_info[1]){
                $str_custom = ' [custom]';
            }
        } else {
            $avg_time = $generating_info;
        }
        if($module_id == $startAt){
            $started = true;
        }
        if($started){
            $nCurrent++;

            if(empty($avg_time)){
                print "s2a: Generating$str_custom '$module_id' ($nCurrent/$nTotal); No Time Estimate ";
            } else {
                print "s2a: Generating$str_custom ";
                print str_pad('\''.$module_id.'\'',7,' ',STR_PAD_RIGHT);
                print " ";
                print str_pad("($nCurrent/$nTotal)",9,' ',STR_PAD_LEFT);
                print "; est: ";
                print str_pad(number_format($avg_time, 2),6,' ',STR_PAD_LEFT);
                print " s";
            }
            $error_msg = CreateModule($project, $module_id);
            if(0 == strlen($error_msg)){
                //success
            } else {
                $failedModules[$module_id] = $error_msg;
            }
            print "\n";
        } else {
            $moduleList[$module_id] = 'skipped';
            $nTotal = $nTotal -1;
        }
    }

    if(!$started){
        print "All matched modules were skipped (no module matched '$startAt')\n";
        exit;
    }
    return $failedModules;
} //end function GenerateModules


function CheckPatches()
{
    define('APPLY_PATCHES_INCLUDED', true); //this is used by the included file
    include_once(S2A_FOLDER . '/s2a-apply-patches.php');
}


/**
 * Checks whether the configured memory limit is at least as large as the passed parameter, and prompts the user if needed.
 */
function checkMemoryLimit($strMinLimit)
{
    $minLimit = memoryStringToBytes($strMinLimit);
    $strConfigured = ini_get('memory_limit');
    $configured = memoryStringToBytes($strConfigured);
    if($configured < $minLimit){
        $ini_file = get_cfg_var('cfg_file_path');
        if(prompt("The configured PHP memory limit of $configured bytes ($strConfigured) is less than the required $minLimit bytes ($strMinLimit). The generating job could fail if you continue before increasing the memory_limit setting. The configuration file used is at $ini_file.\nContinue?")){
            print "Continued.\n";
        } else {
            die("Exited.\n");
        }
    }
}


/**
 * Returns the number of bytes represented by expressions like 12G, 32M, etc.
 *
 * From an example at http://www.php.net/manual/en/function.ini-get.php
 */
function memoryStringToBytes($val)
{
    $val = trim($val);
    $unit = strtolower(substr($val,strlen(intval($val)),1));
    switch($unit) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}


/**
 * Will attempt to regenrate the depended-on module that caused a module to fail generating
 */
function handleFailedModule($project, $errorMessage, $dependentModuleID)
{
    static $error_list = array();

    if(isset($error_list[$errorMessage]) && $error_list[$errorMessage] != 'resolved') {
        return false; //we have encountered this error already and it could not be resolved.
    }
    $error_list[$errorMessage] = 'unresolved';
    $dependedModuleID = findDependedModule($errorMessage);
    if(false === $dependedModuleID){
        return false; //could not figure out which module to regenerate
    }

    $error_list[$errorMessage] = 'attempting';
    print "\ns2a: Generating '$dependedModuleID' to resolve the problem.";

    $error_msg = CreateModule($project, $dependedModuleID);
    if(0 == strlen($error_msg)){
        //success
    } elseif('confirm' == $error_msg) {
        //possible success
    } else {
        print "\ns2a: Generating the depended-on module $dependedModuleID failed. Will try to handle the error.";

        //recurse down and try the module that the depended-on module depends on ;-)
        if(handleFailedModule($project, $error_msg, $dependedModuleID)){
            //success
        } else {
            print "\ns2a: Generating $dependedModuleID was unsuccessful.\n";
            return false;
        }
    }

    print "\ns2a: Re-trying '$dependentModuleID' now that the problem may have been resolved.";
    //retry the originally failed module now that the depended-on module has been successfully regenerated
    $error_msg = CreateModule($project, $dependentModuleID);
    if(0 == strlen($error_msg)){
        //success
        //print "\ns2a: Successfully regenerated the originally failed module $dependentModuleID.\n";
    } else {
        if(!isset($error_list[$error_msg])){
            print "\ns2a: The originally failed module $dependentModuleID failed with a new error message. Will try to handle the error.\n";

            if(handleFailedModule($project, $error_msg, $dependentModuleID)){
                //success
            } else {
                print "\ns2a: Regenerating $dependentModuleID was unsuccessful.\n";
                return false;
            }
        }
    }

    $error_list[$errorMessage] = 'resolved';
    return true;
}



/**
 * Attempts to identify what module caused an error message
 */
function findDependedModule($errorMessage)
{
    //Regex patterns of common MySQL error messages. May be appended with more.
    $errorPatterns = array(
        array(
            'pattern' => "/Table '(\w*)\.([a-z]*)_?l?' doesn't exist/", //database name, table name
            'table_loc' => 2
        ),
        array(
            'pattern' => "/Unknown column '([a-z]*)\d?_?l?\.(\w*)' in/", //table name, column name
            'table_loc' => 1
        )
    );

    foreach($errorPatterns as $patternInfo){

        $nMatches = preg_match($patternInfo['pattern'], $errorMessage, $matches);
        if($nMatches > 0){
            //print_r($matches);
            //trace("found depended module {$matches[$patternInfo['table_loc']]}");
            return $matches[$patternInfo['table_loc']];
            break;
        }
    }
    return false; //unsuccessful in finding a depended-on module
}
?>