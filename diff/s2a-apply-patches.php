<?php
/**
 * Applies a patch (e.g. transforming data into a new structure).
 *
 * This file is used for applying patches defined in separate script files, providing
 * some basic amenities to the patch scripts, such as a database connection, ensuring
 * there is an Upgrade Patches module table, etc.
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
 * @version        SVN: $Revision: 1063 $
 * @last-modified  SVN: $Date: 2007-11-11 20:40:26 -0800 (Sun, 11 Nov 2007) $
 */

if(defined('APPLY_PATCHES_INCLUDED') && APPLY_PATCHES_INCLUDED){
    print "Checking for new patches...\n";
} else {
    if(!defined('PATH_SEPARATOR')){
        if(strtolower(substr(php_uname('s'), 0, 3)) == "win") {
            define('PATH_SEPARATOR', ';');
        } else {
            define('PATH_SEPARATOR', ':');
        }
    }

    //get PEAR class that handles command line arguments
    //since this is needed before we include the config.php file, this is sort-of hard coded.
    set_include_path('./pear' . PATH_SEPARATOR . get_include_path());
    require_once 'pear/Console/Getargs.php';

    $config = array();

    $config['project'] =
        array('short' => 'p',
            'min'   => 0,
            'max'   => 1,
            'desc'  => 'The s2a project name. Must be a folder under the s2a folder.',
            'default' => 'active_agenda'
        );
    $config['patch-name'] =
        array('short'   => 'n',
            'min'     => 0,
            'max'     => 1,
            'desc'    => 'The name of a patch to apply.',
            'default' => 'all'
        );
    $config['help'] =
        array('short' => 'h',
            'max'   => 0,
            'desc'  => 'Show this help.'
        );

    $args =& Console_Getargs::factory($config);
    if (PEAR::isError($args)) {
        if ($args->getCode() === CONSOLE_GETARGS_ERROR_USER) {
            // User put illegal values on the command line.
            echo Console_Getargs::getHelp($config, NULL, $args->getMessage())."\n";
        } else if ($args->getCode() === CONSOLE_GETARGS_HELP) {
            // User needs help.
            echo Console_Getargs::getHelp($config)."\n";
        }
        exit;
    }

    //getting the passed parameters
    $Project   = $args->getValue('project');
    $PatchName = $args->getValue('patch-name');
    $PatchName = trim($PatchName);

    if(empty($Project)){
        $Project = 'active_agenda';
    }

    print "s2a-apply-patches: project = $Project, patch-name = $PatchName\n";

    //assumes we're in the 's2a' folder 
    $site_folder = realpath(dirname(__FILE__).'');
    $site_folder .= '/'.$Project;

    //includes
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

    //get settings
    include_once $config_file;
    include_once $gen_config_file;

    set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

    //this include contains utility functions
    include_once INCLUDE_PATH . '/parse_util.php';

    /**
     * Sets custom error handler
     */
    set_error_handler('handleError');

    /**
     * Defines execution state as 'generating'.  Several classes and
     * functions behave differently because of this flag.
     */
    define('EXEC_STATE', 4);
}

//connect with superuser privileges - regular user has no permission to
//change table structure
$mdb2 =& GetMDB2();
$mdb2->loadModule('Manager');


//check for upp table, create if needed.
print "Looking for required tables...\n";
if(CheckTableExists('upp')){
    trace("Found Upgrade Patches table `upp`.");
} else {
    include_once CLASSES_PATH . '/module.class.php';
    $uppModule =& new Module('upp');
    $uppModule->createTable(false);
    if(!CheckTableExists('upp_l')){
        $uppModule->createTable(true);
    }
    unset($uppModule);
}

if(CheckTableExists('ver')){
    trace("Found Release Versions table `ver`.");
} else {
    include_once CLASSES_PATH . '/module.class.php';
    $verModule =& new Module('ver');
    $verModule->createTable(false);
    if(!CheckTableExists('ver_l')){
        $verModule->createTable(true);
    }
    unset($verModule);
}


//find out current release version
$SQL = "SELECT MAX(ReleaseVersion) FROM `ver`";
$releaseVersion = $mdb2->queryOne($SQL);
mdb2ErrorCheck($releaseVersion);

if(empty($releaseVersion)){
    trace("Inserting a start value in the version table");

    $releaseVersion = '0.8.2';
    $SQL = "INSERT INTO `ver` (ReleaseVersion) VALUES ('$releaseVersion');";
    $result = $mdb2->query($SQL);
    mdb2ErrorCheck($result);
}


//check for applided patches
$SQL = "SELECT PatchName, AppliedStatusID FROM `upp` WHERE ReleaseVersion = '$releaseVersion'";
$r = $mdb2->queryAll($SQL);
mdb2ErrorCheck($r);
//trace($r, 'Patches in database.');

$applied_patches = array();
if(count($r) > 0){
    foreach($r as $row){
        $applied_patches[$row['PatchName']] = $row['AppliedStatusID'];
    }
}

trace("Examining patches for version $releaseVersion.");

//examining patch folder, find matching version subfolder
$patch_folder_location = './util/patches/'.$releaseVersion;
if(!file_exists($patch_folder_location)){
    if(!mkdir($patch_folder_location, 0777, true)){
        trigger_error("Could not create a patch folder at '$patch_folder_location'.", E_USER_ERROR);
        exit;
    }
}

//read patch files from there
$patch_files = glob($patch_folder_location.'/patch*.php');
//print_r($patch_files);

if(count($patch_files) > 0){
    $patches = array();
    foreach($patch_files as $patch_file){
        include_once($patch_file); //provides $patches, declares a class for each patch.
    }
    $skipped_patches = array();

    print "Checking Patches:\n";
    foreach($patches as $patchName => $patchClass){

        if(!isset($applied_patches[$patchName]) || !$applied_patches[$patchName]){
            if(!isset($applied_patches[$patchName])){
                trace("Checked $patchName: New patch file");
            } else {
                trace("Checked $patchName: Not Applied");
            }
            if(call_user_func(array($patchClass, 'needsPatch'))){
                print "\n";
                print "Needs patch '$patchName'.\n";
                $patch_description = call_user_func(array($patchClass, 'getDescription'));
                print "\n";
                print "Description:\n";
                print wordwrap($patch_description)."\n";
                print "\n";
                if(prompt("Apply this patch now?")){
                    print "Checking requirements for '$patchName'.\n";
                    $requirements = call_user_func(array($patchClass, 'checkRequirements'));

                    //if(count($requirements) > 0){
                    if(isset($requirements['needsFix']) && $requirements['needsFix']){
                        print "Unmet requirements:\n";
                        print join("\n", $requirements['messages']) ."\n";

                        if(prompt("Apply these requirements now?")){
                            $success = call_user_func(array($patchClass, 'applyRequirements'), $requirements['fixes']);
                            if(!$success){
                                trigger_error("There was a problem applying requirements for the patch '$patchName'.", E_USER_ERROR);
                            } else {
                                print "The requirements were applied successfully!\n";
                            }
                        } else {
                            if(prompt("Exit the program?")){
                                print "Exiting.\n";
                                exit;
                            }
                        }
                    }

                    $patch_description = addslashes(trim($patch_description));
                    if(!isset($applied_patches[$patchName])){
                        $SQL = "INSERT INTO `upp` (PatchName, ReleaseVersion, Description, AppliedStatusID) VALUES ('$patchName', '$releaseVersion', '$patch_description', 0)";
                        $result = $mdb2->query($SQL);
                        mdb2ErrorCheck($result);
                    }

                    print "Applying patch... ";
                    if(call_user_func(array($patchClass, 'applyPatch'))){
                        if(!call_user_func(array($patchClass, 'needsPatch'))){
                            print "Patch applied successfully!\n";
                            $SQL = "UPDATE `upp` SET AppliedStatusID = 1 WHERE PatchName = '$patchName';";
                            $result = $mdb2->query($SQL);
                            mdb2ErrorCheck($result);
                        } else {
                            print "needsPatch still returned true\n";
                        }
                    } else {
                        print "applyPatch returned false\n";
                    }
                } else {
                    if(prompt("Exit the program?")){
                        print "Exiting.\n";
                        exit;
                    }
                }
            } else {
                //record that patch not required:
                trace("Confirmed $patchName Not Required");

                $patch_description = call_user_func(array($patchClass, 'getDescription'));
                $patch_description = addslashes(trim($patch_description));
                if(!isset($applied_patches[$patchName])){
                    $SQL = "INSERT INTO `upp` (PatchName, ReleaseVersion, Description, AppliedStatusID) VALUES ('$patchName', '$releaseVersion', '$patch_description', 2)";
                } else {
                    //this can happen if the patch was applied from elsewhere (perhaps manually) if the automatic patch failed
                    $SQL = "UPDATE `upp` SET AppliedStatusID = 2 WHERE PatchName = '$patchName';";
                }
                $result = $mdb2->query($SQL);
                mdb2ErrorCheck($result);
            }
        } else {
            switch($applied_patches[$patchName]){
            case 1:
                trace("Checked $patchName: Already Applied");
                break;
            case 2:
                trace("Checked $patchName: Not Required");
                break;
            default:
                trace("Checked $patchName: Status unknown");
                break;
            }
        }
    }


} else {
    print "No patch files found.\n";
}


/**
 *  Abstract class for patch management
 *
 *  Each individual patch should extend this class
 */
class Patch
{
function getDescription()
{
    trigger_error("The getDescription method must be overriden.", E_USER_ERROR);
    return false;
}

function checkRequirements($applyRequirements = false)
{
    return array('needsFix' => false);
}

function applyRequirements($fixes)
{
    return true;
}

function needsPatch()
{
    trigger_error("The needsPatch method must be overriden.", E_USER_ERROR);
    return false;
}

function applyPatch()
{
    trigger_error("The applyPatch method must be overriden.", E_USER_ERROR);
    return false;
}
} //end class Patch

?>