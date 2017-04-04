<?php
/**
 * Utility to import data from XML files
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


/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
define('EXEC_STATE', 2);

//location of this script, which is th s2a directory.
$script_location = realpath(dirname(__FILE__).'');

/**
 *  defines command-line options
 */
$config = array();
$config['match'] =
    array('short'   => 'm',
        'min'     => 0,
        'max'     => 1,
        'desc'    => 'A wildcard expression that matches the IDs of the modules to import data for. Use % as wildcard character. Examples: %, ac%, act',
        'default' => '%'
    );
$config['dir'] =
    array('short'   => 'd',
        'min'     => 1,
        'max'     => 1,
        'desc'    => 'The directory to search for files.',
        'default' => 'install/master'
    );
$config['file'] =
    array('short'   => 'f',
        'min'     => 1,
        'max'     => 1,
        'desc'    => 'The path to a specific file to be imported.',
        'default' => ''
    );
$config['codetypes'] =
    array('short'   => 'c',
        'min'     => 1,
        'max'     => 1,
        'desc'    => 'The code types to be imported, if the Codes (cod) module is matched by the -m argument. You may specify single ID numbers and ranges (lower range limit, dash, upper limit), separated by commas. Use no spaces anywhere. Example: 1,5,10-15,20-30',
        'default' => ''
    );
$config['autoconfirm'] =
    array('short'   => 'a',
        'min'     => 0,
        'max'     => 0,
        'desc'    => 'When specified, the program will not prompt the user for confirmations for each file.'
    );


include $script_location . '/lib/includes/cli-startup.php';
include_once CLASSES_PATH . '/modulefields.php';
include_once CLASSES_PATH . '/data_handler.class.php';
include_once CLASSES_PATH . '/data_map.class.php';
//include_once INCLUDE_PATH . '/web_util.php';

//getting the passed parameters
$ModuleMatch    = $args->getValue('match');
$InFile         = $args->getValue('file');
$ModuleID       = $ModuleMatch;
$Dir            = rtrim($args->getValue('dir'), '/');
$Autoconfirm    = $args->getValue('autoconfirm');

print "s2a-import-data: project = $Project\n";

if(empty($ModuleMatch)){
    $ModuleMatch = '%';
}

if(empty($InFile)){

    //import one or more master files, based on supplied -m and -c parameters
//work in progress...
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

    $sql = "SELECT ModuleID FROM `mod` WHERE _Deleted = 0 {$limiter} ORDER BY ModuleID";
    $importModules = $mdb2->queryCol($sql);
    mdb2ErrorCheck($importModules);

    //find matching data files in specified directory
    if(!file_exists($Dir)){
        die("The data file directory $Dir was not found.\n");
    }
    $rawFilePaths = glob($Dir.'/*.*');
//trace($rawFilePaths);
    $dataFilePaths = array();
    foreach($rawFilePaths as $rawFilePath){
        //$extension = strtolower(substr($rawFilePath, strrpos($rawFilePath, '.')+1));
        $extension = strtolower(substr(strrchr($rawFilePath, "."), 1));
        switch($extension){
        case 'xml':
            print "XML data files are not yet supported in directory imports. Ignoring file $rawFilePath.\n";
            break;
        case 'csv':
            //print "Added file $rawFilePath.\n";
            $dataFileName = basename($rawFilePath);
            if(false === strpos($dataFileName, '_')){
                static $explanationShowed = false;
                if(!$explanationShowed){
                    print "\n";
                    print wordwrap("Cannot determine the destination module(s) of one or more CSV files mentioned below, because the file names do not follow the expected pattern, which is <module ID> <underscore> <anything>.csv (e.g. 'abc_data.csv').\n\n");
                    $explanationShowed = true;
                } 
                $dataFileModuleID = textPrompt(wordwrap("Please type the intended module ID for the file $rawFilePath here (or leave blank to ignore this file)."));
                if(empty($dataFileModuleID)){
                    print "Ignoring file $rawFilePath.\n";
                    continue;
                }
            } else {
                $dataFileModuleID = substr($dataFileName, 0, strpos($dataFileName, '_'));
            }
            if(in_array($dataFileModuleID, $importModules)){
                $dataFilePaths[$rawFilePath] = $dataFileModuleID;
            }
            break;
        default:
            print "Ignoring file $rawFilePath.\n";
            break;
        }
    }

    foreach($dataFilePaths as $dataFilePath => $dataFileModuleID){
        if(!$Autoconfirm){
            if(!prompt("\nImport file $dataFilePath into $dataFileModuleID?", 'y', true)){
                continue;
            }
        } else {
            print "Importing file $dataFilePath into $dataFileModuleID.\n";
        }
        ImportFile($dataFilePath, $dataFileModuleID);
    }
    print "\n";
} else {
    ImportFile($InFile, $ModuleID);
}


function ImportFile($InFile, $ModuleID = null){

    //check that the input file exists
    if(!file_exists($InFile)){
        die("Could not find input file $InFile.");
    }

    //determine file type: CSV or XML
    $file_extension = strtolower(substr(strrchr($InFile, "."), 1));

    switch($file_extension){
    case 'csv':
        print "Reading CSV file.\n";

        $file_basename = basename($InFile);
        $guessed_moduleID = substr($file_basename, 0, strpos($file_basename, '_'));

        if(!empty($guessed_moduleID)){
            if(empty($ModuleID)){
                if(prompt("You did not supply a module ID in the -m parameter, but the file name suggests this data should be imported into the '$guessed_moduleID' module. Is this correct?")){
                    $ModuleID = $guessed_moduleID;
                }
            } else {
                if($ModuleID != $guessed_moduleID){
                    if(!prompt("You supplied '$ModuleID' as the module ID paramerer, but the file name suggests this data belongs to the '$guessed_moduleID' module. Proceed with importing into '$ModuleID'?")){
                        die("Exit.\n");
                    }
                }
            }
        }
        if(empty($ModuleID)){
            die("A module ID parameter is required.\n");
        }

        $headers = array();
        $rows = array();

        $row_ix = 0;
        $handle = fopen($InFile, "r");
        $skipped_rows = array();
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $row_ix++;
            if(1 == $row_ix){
                $nFields = count($data);
                foreach($data as $header){
                    $headers[] = trim($header);
                }
            } else {
                if(count($data) != $nFields){
                    $skipped_rows[] = $row_ix;
                    print "WARNING: Cannot import row $row_ix. It contains ".count($data)." values but there are $nFields fields in the header.\n";
                } else {
                    foreach($data as $field_ix => $data_cell){
                        $rows[$row_ix][$headers[$field_ix]] = trim($data_cell);
                    }
                }
            }
        }
        fclose($handle);

        if(count($skipped_rows) > 0){
            print "Skipping rows numbered: ".join(',',$skipped_rows)."\n\n";

            if(!prompt(wordwrap("Some rows cannot be imported because they contain a different number of values than the fields specified in the first row. Continue importing the other rows?"))){
                die("Exited.\n");
            }
        }
        ob_start();
        $dataHandler = GetDataHandler($ModuleID);
        $dataHandler->startTransaction();

        $canceled = false;
        foreach($rows as $row_ix => $row){
            if(false === $dataHandler->importRow($row)){
                ob_end_clean();
                $canceled = true;
                if(count($dataHandler->errmsg) > 0){
                    trace($row, "Data in the row mentioned in error below");
                    print "Error(s) in line $row_ix of file $InFile:\n";
                    print "\n";
                    foreach($dataHandler->errmsg as $msg => $id){
                        print "Data could not be saved because:\n";
                        print wordwrap("$msg (error: $id)\n");
                    }
                } else {
                    print "Unknown data problem. The import got canceled but there are no error messages.\n";
                }
                break;
            } else {
                ob_end_clean();
                print "Imported row $row_ix.\n";
                ob_start();
            }
        }
        if(!$canceled){
            $dataHandler->endTransaction();
            ob_end_clean();
        }
        break;
    default:

        die("XML import is disabled while we rewrite it better...\n");
        $dataMap =& new DataMap($InFile);

        if(count($dataMap->c > 0)){
            foreach($dataMap->c as $element){
                if('Records' == $element->type){
                    $dataImporter = new DataImporter($element);
                    $dataImporter->import();
                }
            }
        }
        break;
    }
} //end function ImportFile()
