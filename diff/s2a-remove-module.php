<?php
/**
 * Utility to remove a module completely, including dependencies
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

print "\n";
print "\n";
print "*********************************************\n" ;
print "* remove-module: s2a module removal utility *\n" ;
print "*********************************************\n" ;
print "\n";

if(defined('REMOVE_MODULE_INCLUDED') && REMOVE_MODULE_INCLUDED){
    $moduleID = $uninstall_moduleID;
} else {
    $Project  = $_SERVER['argv'][1];
    $moduleID = $_SERVER['argv'][2];
}


if(empty($Project) || empty($moduleID)){
    die('Please specify the project as the first parameter, and the module ID as the second'."\n");
}


//assumes we're in the 'app' folder 
$site_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'');
$site_folder .= '/'.$Project;

//settings
$config_file = $site_folder . '/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}
$gen_config_file = $site_folder . '/gen-config.php';
if(!file_exists($gen_config_file)){
    print "Config file not found at $gen_config_file\n";
    exit;
}
include_once $config_file;
include_once $gen_config_file;
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());
require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class

include_once INCLUDE_PATH . '/parse_util.php';
include_once INCLUDE_PATH . '/general_util.php';


if($_SERVER['argc'] > 3 && strtolower('cachetriggers') == $_SERVER['argv'][3]){
    if(prompt("Really delete cache triggers for '$moduleID'?")){
        $cacheTriggers = true;
        $everything = false;
    } else {
        die("Exiting...\n");
    }
} else {
    if(prompt("Really delete module '$moduleID'?")){
        $cacheTriggers = true;
        $everything = true;
    } else {
        print "Module $moduleID was not uninstalled.\n";
        die("Exiting...\n");
    }
}

if($everything){
    print "Deleting module $moduleID...\n";
}
$errors = 0;


global $dbh;
$dbh = DB::connect(GEN_DB_DSN);
dbErrorCheck($dbh);

if($everything){
    print "   Removing record from 'mod' table...";
    $SQL = "DELETE FROM `mod` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing record from 'modd' table...";
    $SQL = "DELETE FROM `modd` WHERE (ModuleID = '$moduleID') OR (DependencyID = '$moduleID')";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing records from 'usrp' table...";
    $SQL = "DELETE FROM `usrp` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing records from 'usrgp' table...";
    $SQL = "DELETE FROM `usrgp` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing records from 'spt' table...";
    $SQL = "DELETE FROM `spt` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything || $cacheTriggers){
    print "   Removing records from 'smc' table...";
    $SQL = "DELETE FROM `smc` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything || $cacheTriggers){
    print "   Removing records from 'rdc' table...";
    $SQL = "DELETE FROM `rdc` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing records from 'dsbc' table...";
    $SQL = "DELETE FROM `dsbc` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Removing records from 'ccs' table...";
    $SQL = "DELETE FROM `ccs` WHERE ModuleID = '$moduleID'";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        $rows_affected = $dbh->affectedRows();
        print " successful! ($rows_affected rows deleted)\n";
    }
}

if($everything){
    print "   Dropping main table...";
    $SQL = "DROP TABLE $moduleID";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        print " successful!\n";
    }

    print "   Dropping log table...";
    $SQL = "DROP TABLE {$moduleID}_l";
    $r = $dbh->query($SQL);
    if (DB::isError($r)) {
        $errors += 1;
        print $r->getMessage()."\n";
    } else {
        print " successful!\n";
    }
}

if($everything || $cacheTriggers){
    print "   Removing SMC Triggers...\n";
    $triggerfiles = glob(GENERATED_PATH . '/*/*SMC*');
    $modelFileName = "SMCTriggersModel.php";
    foreach($triggerfiles as $triggerfile){
        print '.';
        $trigger_filename = basename($triggerfile);
        $trigModuleID = substr($trigger_filename, 0, strpos($trigger_filename, '_'));
        include($triggerfile); //sets $SMCtriggers
        if(isset($SMCtriggers[$moduleID])){
            print "\n   $triggerfile\n";
            print "   Found a $moduleID trigger. Removing.\n";
            unset($SMCtriggers[$moduleID]);
            $codeArray = array('/**SMCtriggers**/' => escapeSerialize($SMCtriggers));
            SaveGeneratedFile($modelFileName, $trigger_filename, $codeArray, $trigModuleID);
        }
    }
    print "\n";
}

if($everything || $cacheTriggers){
    print "   Removing RDC Triggers...\n";
    $triggerfiles = glob(GENERATED_PATH . '/*/*_RDCTriggers.gen');
    $modelFileName = "RDCTriggersModel.php";
    foreach($triggerfiles as $triggerfile){
        print '.';
        $trigger_filename = basename($triggerfile);
        $trigModuleID = substr($trigger_filename, 0, strpos($trigger_filename, '_'));
        include($triggerfile); //sets $SMCtriggers
        if(isset($RDCtriggers[$moduleID])){
            print "\n   examining $triggerfile\n";
            print "   Found a $moduleID trigger. Removing.\n";
            unset($RDCtriggers[$moduleID]);
            $codeArray = array('/**RDCtriggers**/' => escapeSerialize($RDCtriggers));
            SaveGeneratedFile($modelFileName, $trigger_filename, $codeArray, $trigModuleID);
        }
    }
    print "\n";
}

if($everything){
    print "   Deleting generated files...\n";
    $deleted_files_counter = 0;
    $subfolder_files = glob(GENERATED_PATH . '/'.$moduleID.'/*');
    foreach($subfolder_files as $subfolder_file){
        unlink($subfolder_file);
        print "      deleted $subfolder_file\n";
        $deleted_files_counter ++;
    }

    rmdir(GENERATED_PATH . '/'.$moduleID);
    print "      deleted subfolder $moduleID\n";
    $deleted_files_counter ++;
    print "   All matching cached files were deleted. ($deleted_files_counter files)\n";
}

if($everything){
    $moduleDefFile = XML_PATH . "/{$moduleID}ModuleDef.xml";
    print "   Deleting XML file ($moduleDefFile)...";

    if(file_exists($moduleDefFile)){
        if(unlink($moduleDefFile)){
            print " successful!\n";
        } else {
            $errors += 1;
            print " error while removing file (check file permissions).\n";
        }
    } else {
        print " file not found\n";
    }
}

if($errors > 0){
    print "There were problems uninstalling the module `$moduleID`.\n";
} else {
    print "Successfully uninstalled the module `$moduleID`.\n";
}
?>