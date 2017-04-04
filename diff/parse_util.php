<?php
/**
 * Generally available functions to be used at parse-time.
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
 * @version        SVN: $Revision: 1645 $
 * @last-modified  SVN: $Date: 2009-05-22 22:57:31 +0200 (Pt, 22 maj 2009) $
 */

include_once INCLUDE_PATH . '/general_util.php';

/**
 *  returns a (reference to a) module object of the specified module.
 */
function &GetModule($pModuleID) {
    $debug_prefix = debug_indent("GetModule() {$pModuleID}:");
    if(empty($pModuleID)){
        die("$debug_prefix No Module ID passed.");
    }
    $returnval = false;
    include_once CLASSES_PATH . '/module.class.php';

    if(defined('EXEC_STATE') && EXEC_STATE == 4){

        //access the global foreignModules array
        global $foreignModules;

        //add structure of foreign module, unless it is either:
        //1. unavailable, 2. already done, 3. already in $foreignModules
        if(empty($foreignModules[$pModuleID])){

            $foreignModules[$pModuleID] = 'preparing';
            $t_ForeignModule =& new ForeignModule($pModuleID);

            //check whether it worked
            if ($t_ForeignModule->Parsed){

                $foreignModules[$pModuleID] =& $t_ForeignModule;

                print "$debug_prefix newly parsed $pModuleID\n";
                debug_unindent();
                return $t_ForeignModule;
            } else {

                $foreignModules[$pModuleID] = 'empty';

                print "$debug_prefix Could not add foreign module $pModuleID. Parse of its XML file was not successful.\n";

                debug_unindent();
                return $returnval;
            }
        } else {

            $t_ForeignModule =& $foreignModules[$pModuleID];

            if (is_object($t_ForeignModule)){
                if(!$t_ForeignModule->Parsed){
                    print "returning module $pModuleID which is not completely parsed yet\n";
                }
                //print "$debug_prefix returning $pModuleID from global list\n";
                debug_unindent();
                return $t_ForeignModule;
            } else {
                indent_print_r($t_ForeignModule);
                print "$debug_prefix $pModuleID not found\n";
                debug_unindent();
                return $returnval;
            }
        }

    } else {

        //we don't parse modules at run time
        debug_unindent();
        return $returnval;
    }
}



/**
 *  Fetches an XML def file, with preference for customs if present.
 */
function GetXMLFilePath($fileName)
{
    if(defined('CUSTOM_XML_PATH')){
        $path = CUSTOM_XML_PATH.'/'.$fileName;
        if(file_exists($path)){
            trace("Using custom XML file '$path'");
            return $path;
        }
    }
    return XML_PATH.'/'.$fileName;
}



/**
 *  Serializes and escapes a string/array/object so it can be easily saved to a generated file
 */
function escapeSerialize($param){
    return str_replace("'", "\\'",
                str_replace("\\", "\\\\",
                    serialize($param)
                )
            );
}



/**
 *  Indents a string with a specified number of tab characters
 */
function TabPad($string, $num){
    if(empty($num)){
        $num = 1;
    }

    if (FALSE === strpos($string, "\n")){
        //one line
        return str_repeat("\t", $num) . $string;
    } else {
        //several lines
        $lines = explode("\n", $string);
        $string = '';
        foreach($lines as $line){
            $string .= str_repeat("\t", $num) . $line . "\n";
        }
        return $string;
    }
}


function SaveGeneratedFile($modelFileName, $createFileName, $replaceValues, $moduleID = null){
    $debug_prefix = debug_indent("SaveGeneratedFile():");

    $modelFileName = MODELS_PATH . '/'.$modelFileName;
    if (file_exists($modelFileName)){
        //get the Model file
        $fp = fopen($modelFileName, 'r');
        $contents = fread($fp, filesize($modelFileName));
        fclose($fp);
        print "$debug_prefix Reading $modelFileName...\n";

        //replace placeholders in model with data from the code array
        foreach($replaceValues as $placeholder=>$code){
            $contents = str_replace($placeholder, $code, $contents);
        }

        //eliminate code between /**-remove_begin-**/ and /**-remove_end-**/ tags (can be nested)
        $offset = 0;
        $remove_starts = array();
        $remove_ends = array();

        $begin_tag = '/**-remove_begin-**/';
        $end_tag = '/**-remove_end-**/';

        if(false  !== strpos($contents, $begin_tag)){
            print "$debug_prefix begin removing\n";
            //indent_print_r($contents, true, 'BEFORE');
            while(false  !== strpos($contents, $begin_tag, $offset)){
                $start_pos = strpos($contents, $begin_tag, $offset);
                $end_pos = strpos($contents, $end_tag, $offset);
                $offset = strpos($contents, '/**-remove_', $offset);
                //$offset = 0;
                print "$debug_prefix looping... $start_pos, end: $end_pos, offset: $offset\n";

                if($start_pos == $offset){
                    $remove_starts[] = $start_pos;
                    $offset = $offset + strlen($begin_tag);
                } elseif($end_pos == $offset){
                    $remove_ends[] = $end_pos;
                    $offset = $offset + strlen($end_tag);
                } else {
                    indent_print_r($contents, true, 'what is wrong?');
                    die('something wrong with the remove-tags (nested wrong?)');
                }

                if(0 == count($remove_starts) && 0 == count($remove_ends)){
                    break;
                }
                if(count($remove_starts) == count($remove_ends)){
                    indent_print_r($remove_starts);
                    indent_print_r($remove_ends);
                    $start = reset($remove_starts);
                    $end   = end($remove_ends) + strlen($end_tag);

                    if($start > $end){
                        print "$debug_prefix remove start: $start, end: $end, offset: $offset\n";
                        indent_print_r($remove_starts);
                        indent_print_r($remove_ends);
                        indent_print_r($contents, true, 'Err');
                        die("how does this happen??");
                    }

                    //removes the code between $start and $end:
                    print "$debug_prefix remove start: $start, end: $end, offset: $offset\n";

                    $contents = substr($contents, 0, $start) . substr($contents, $end);

                    $remove_starts = array();
                    $remove_ends = array();
                }
                if($offset > strlen($contents)){
                    $offset = strlen($contents); //avoids a warning in "while" above
                }

            }
        }

        if(!empty($moduleID)){
            $generationLocation = GENERATED_PATH . '/'.$moduleID;
            if(!file_exists($generationLocation)){
                if(!mkdir($generationLocation)){
                    die( "$debug_prefix could not create folder '$moduleID'.\n" );
                }
            }
         } else {
            $generationLocation = GENERATED_PATH;
         }

        //write the new file to the disk
        if($fp = fopen($generationLocation . "/{$createFileName}", 'w')) {
            if(fwrite($fp, $contents)){
                print "$debug_prefix file $createFileName saved.\n";
            } else {
                die( "$debug_prefix could not create file $createFileName.\n" );
            }
            fclose($fp);

            //make sure cached files can be executed
            $perm = 0775;
            chmod($generationLocation . "/{$createFileName}", $perm);
            print "$debug_prefix set file permission to ".decoct($perm)."\n";
        }
        else {
            die("$debug_prefix Unable to write file $createFileName.\n");
        }
    } else {
        print "$debug_prefix Could not find $modelFileName\n";
    }

    debug_unindent();
}


function prompt($q, $defaultReply = null, $allowQuit = false)
{
    $quitMsg = ''; 
    if($allowQuit){
        $quitMsg = " Type 'q' to quit the program.";
    }

    $responseHints = 'Type y for Yes, n for No';

    $prompt = "$q\n";
    if($allowQuit){
        $responseHints .= ', q to Quit';
    }
    if(!empty($defaultReply)){
        $responseHints .= " (default $defaultReply)";
    }
    print $prompt . $responseHints.': ';

    $response = false;
    $has_response = false;
    while($resp = fgets(STDIN)){
        $resp = strtolower(trim($resp));

        if(empty($resp) && !empty($defaultReply)){
            $resp = $defaultReply;
        }

        if('y' == $resp){
            $response = true;
            $has_response = true;
        } elseif('n' == $resp){
            $response = false;
            $has_response = true;
        } elseif($allowQuit && 'q' == $resp){
            die("Quit\n");
        }

        if($has_response){
            return $response;
        } else {
            if('' != trim($resp)){
                print "\nError: Your response '$resp' was not understood.";
                print $prompt . $responseHints.'> ';
            }
        }
    }
}


function textPrompt($q)
{
   print "$q\n";
   print "Press ENTER when finished: ";
   $response = fread(STDIN, 30);

   return trim($response);
}

function shellQuery($sql, $db_name = ''){
   global $mysql_command;
   global $root_pwd;

   print "shellQuery '$sql'\n";
   $temp_file_path = tempnam('/tmp', 'aatmp');
   $temp_h = fopen($temp_file_path, 'w');
   fwrite($temp_h, $sql);
   fclose($temp_h);

    $arg_temp_file_path = escapeshellarg($temp_file_path);
   //for some reason mysql --execute='$sql'" wouldn't work at all, so we use the temp file
   if(empty($db_name)){
      $command = "$mysql_command -u root -p{$root_pwd} < $arg_temp_file_path";
   } else {
      $command = "$mysql_command -u root -p{$root_pwd} $db_name < $arg_temp_file_path";
   }

   $result = shellCommand($command, true);

   unlink($temp_file_path);
   return $result;
}


/**
 * Executes a shell command
 */
function shellCommand($command, $print = true, $die = true)
{
    $debug_prefix = 'shellCommand';
   if($print){
      print "$debug_prefix Executing command: $command\n";
   }
   ob_start();

   system($command, $errcode);
   $result = ob_get_contents();
   ob_end_clean();

   if($errcode > 0){
      print "shell output:\n";
      print $result;
    if($die){
      die("$debug_prefix Error $errcode. Could not perform the following command:\n$command\n");
    }
   } elseif($print){
      print $result;
   }
   return $result;
}


/**
 *  Recursive function to find correct MySQL folder (needed for windows only)
 */
function findMySQLexe($start_folder = null)
{
    if(isWindows()) {
        $found_path = '';
        if(!empty($start_folder)){
            //$start_folder = str_replace('\\', '\\\\', $start_folder);
            $folders = array(
                $start_folder
            );
        } else {
            $folders = array(
                'C:\\Program Files\\MySQL',
                'C:\\Program Files\\xampp\\MySQL'
            );
        }

        foreach($folders as $folder){
            $file_list = glob($folder . '\\*');
            foreach($file_list as $file_path){
                if(false !== strpos(strtolower($file_path), 'mysql.exe')){
                    return $file_path;
                } elseif(is_dir($file_path)){
                    if($found_path = findMySQLexe($file_path)){
                        return $found_path;
                    }
                }
            }
        }
        return $found_path;
    } else {
        return shellCommand('which mysql', false, false);
    }
}


/**
 * Looks up the mysql_reserved.txt file and loads the list as a static array
 *
 *  This list is used by the application to ensure that field names aren't using
 *  reserved words, which would cause errors in SQL statements.
 */
function CheckReservedWords($word)
{
    static $reserved_words = array();

    //load list if not already loaded
    if(count($reserved_words) == 0){
        $file_path = S2A_FOLDER . '/util/mysql_reserved.txt';
        if(file_exists($file_path)){
            $file_contents = file_get_contents($file_path);
            $file_contents = strtolower($file_contents);
            $file_contents = str_replace(array("\n", "\r\n"), array('', ''), $file_contents);
            $reserved_words = explode(',', $file_contents);
        } else {
            trigger_error("Could not load list of mysql reserved words. File $file_path not found.", E_USER_WARNING);
            return true;
        }
    }


    if(in_array(strtolower($word), $reserved_words)){
        trigger_error("Field name $word is invalid: it is a reserved word.", E_USER_ERROR);
    }

    return true;
}


/**
 *  Determines whether the operating System is Windows or not
 */
function isWindows()
{
    return strtolower(substr(php_uname('s'), 0, 3)) == "win";
}


/**
 *  Attempts to determine the location of the PHP executable.
 *
 *  If this cannot be found, the user will be prompted.
 */
function findPHPexe()
{
    static $exe_location = '';
    if(!empty($exe_location)){
        return $exe_location;
    }
    if(defined('GEN_PHP_EXEC')){
        $exe_location = GEN_PHP_EXEC;
    } else {
        if(isWindows()) {
            //starts by guessing a few common locations
            if(file_exists('C:\\php\\cli\\php.exe')){
                $exe_location = 'C:\\php\\cli\\php.exe';
            } elseif(file_exists('C:\\php4\\cli\\php.exe')){
                $exe_location = 'C:\\php4\\cli\\php.exe';
            } elseif(file_exists('C:\\"Program Files\\xampp\\php\\php.exe"')){
                $exe_location = 'C:\\"Program Files\\xampp\\php\\php.exe"';
            } elseif(!empty($_ENV['PHPRC'])) {
                if(file_exists($_ENV['PHPRC'].'\\php-cli.exe')) {
                    $exe_location = $_ENV['PHPRC'].'\\php-cli.exe';
                } elseif (file_exists($_ENV['PHPRC'].'\\php.exe')) {
                    $exe_location = $_ENV['PHPRC'].'\\php.exe';
                } elseif (file_exists($_ENV['PHPRC'].'\\php-win.exe')) {
                    $exe_location = $_ENV['PHPRC'].'\\php-win.exe';
                }
            } elseif(file_exists('C:\\php\\php-cli.exe')){
                $exe_location = 'C:\\php\\php-cli.exe';
            } elseif(file_exists('C:\\php4\\php-cli.exe')){
                $exe_location = 'C:\\php4\\php-cli.exe';
            } elseif(file_exists('C:\\php\\php-win.exe')){
                $exe_location = 'C:\\php\\php-win.exe';
            } elseif(file_exists('C:\\php4\\php-win.exe')){
                $exe_location = 'C:\\php4\\php-win.exe';
            } elseif(file_exists('C:\\php\\php.exe')){
                $exe_location = 'C:\\php\\php.exe';
            } elseif(file_exists('C:\\php4\\php.exe')){
                $exe_location = 'C:\\php4\\php.exe';
            }
        } else { //linux and other *nix
            if(!empty($_SERVER['_'])){
                $exe_location = $_SERVER['_'];
            } elseif(file_exists('/opt/lampp/bin/php')){
                $exe_location = '/opt/lampp/bin/php';
            } else {
                $exe_location = shellCommand('which php', false, false);
            }
        }
    }

    if(!file_exists($exe_location)){
        if(prompt("Cannot find the location of the PHP executable. This is the same as the PHP command you probably typed in order to start this script. Would you like to enter it manually?")){
            do {
                $exe_location = textPrompt("Please type the location of the PHP executable.");
                if(!file_exists($exe_location) && !prompt("That doesn't look right. Try again?")){
                    die("Exiting program\n");
                }
            } while(!file_exists($exe_location));
        } else {
            die("Exiting program\n");
        }
    }
    return $exe_location;
}


/**
 * Returns a list of add-on files relevant to a module (if $moduleID is passed) or of all modules.
 */
function GetCentralAddOns($moduleID, $use_file = true)
{
    if($use_file){
        $filename = GENERATED_PATH.'/moduleCentralAddOns.gen';
        if(file_exists($filename)){
            include $filename;
        } else {
            $addOnMap = MakeCentralAddOnMap(true);
        }
    } else {
        $addOnMap = MakeCentralAddOnMap();
    }

    if(isset($addOnMap[$moduleID])){
        return $addOnMap[$moduleID];
    }
    return array();
}


/**
 * Builds a multidimensional array of what modules should include what central module add-ons and saves it to a file
 */
function MakeCentralAddOnMap($saveToFile = false)
{
    $addOnFiles = array();
    $addOnMap = array();

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

    //regular central add-ons
    $reg_addOns = glob($addOnXMLPath . "/*_CentralModuleAddOn.xml");
    if(count($reg_addOns) > 0){
        foreach($reg_addOns as $addOnPath){
            $addOnFile = basename($addOnPath);
            $addOnFiles[$addOnFile] = $addOnPath;
        }
    }

    //custom central add-ons override the regular ones
    if(defined('CUSTOM_XML_PATH')){
        $custom_addOns = glob(CUSTOM_XML_PATH."/*_CentralModuleAddOn.xml");
        if(count($custom_addOns) > 0){
            foreach($custom_addOns as $addOnPath){
                $addOnFile = basename($addOnPath);
                $addOnFiles[$addOnFile] = $addOnPath;
            }
        }
    }

    foreach($addOnFiles as $addOnPath){
        $addonXML = simplexml_load_file($addOnPath);
        $attrs = $addonXML->attributes();
        if(isset($attrs['applyToModules'])){
            $applyToModules = explode(' ', $attrs['applyToModules']);
            //trace($applyToModules);
            foreach($applyToModules as $applyToModuleID){
                if(!empty($applyToModuleID)){
                    $addOnMap[$applyToModuleID][] = $addOnPath;
                }
            }
        } else {
            trigger_error("Skipping the central module add-on file $addOnPath because it does not specify what modules it applies to. The root element must contain an attribute named 'applyToModules'.", E_USER_WARNING);
        }
    }

    if($saveToFile){
        //save this to a temp. generated file
        $php = '<?php $addOnMap = '.var_export($addOnMap, true).';?>';
        $filename = GENERATED_PATH.'/moduleCentralAddOns.gen';
        $res = file_put_contents($filename, $php);
        if(false === $res){
            print "Could not write $filename.\n";
        } else {
            print "Wrote $filename ($res bytes)\n";
        }
    }
    return $addOnMap;
}


function CheckTableExists($tableName)
{
    $mdb2 =& GetMDB2();
    $SQL = "SELECT count(*) FROM `$tableName` WHERE 1 = 0";
    $r = $mdb2->queryOne($SQL);
    $err = mdb2ErrorCheck($r, false, false, -18);
//trace($err, 'checkTableExists');
    switch($err['code']){
    case 0:
        return true;
        break;
    case -18:
        return false;
        break;
    default:
        //a different error
        mdb2ErrorCheck($res);
    }
}
?>