<?php
/**
 * Verifies the uploaded files and compares them with the data in the 
 * Attachments module.
 *
 * This file reads from the Attachments module, and compares with
 * the folders and files of the uploads folder (specified in the 
 * UPLOAD_PATH constant in the config,php file).
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


$Project = $_SERVER[argv][1];
$Action = $_SERVER[argv][2]; //either 'query' (default) or 'cleanup'

if(empty($note)){
    $note = 'query';
}


//assumes we're in the 's2a' folder 
$site_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'');
$site_folder .= '/'.$Project;

//includes
$config_file = $site_folder . '/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}

//get settings
include_once $config_file;
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class

//this include contains utility functions
include_once INCLUDE_PATH . '/parse_util.php';
include_once INCLUDE_PATH . '/web_util.php'; //need TextTable class from here

/**
 * Sets custom error handler
 */
set_error_handler('handleError');

/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 2);

//connect to database with web user privileges - no need for superuser privileges
global $dbh;
$dbh = DB::connect(DB_DSN);
dbErrorCheck($dbh);




$SQL = "SELECT
    AttachmentID,
  CONCAT(RelatedModuleID, '/att_', RelatedModuleID, '_', RelatedRecordID, '_', AttachmentID, '.dat') AS ServerFile,
  Filesize,
  _Deleted
FROM
  `att`
ORDER BY
  RelatedModuleID,
  RelatedRecordID,
  AttachmentID";

$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

$cols = array();

$cols[] = 'ServerFile';
$cols[] = 'DB Filesize';
$cols[] = 'Actual Filesize';
$cols[] = '_Deleted';
$cols[] = 'Status';
$cols[] = 'Note';

/*print_r($cols);
print_r($r);*/

$data = array();

foreach($r as $rowNum => $row){
    $filename = UPLOAD_PATH . '/'. $row['ServerFile'];
    $note = '';
    $filesize = '';
//print "checking $filename \n";
    if(true == $row['_Deleted']){
        if(file_exists($filename)){
            $filesize = filesize($filename);
            $fileStatus = 'exists';
        } else {
            $fileStatus = 'missing';
        }
    } else {
        if(file_exists($filename)){
            $filesize = filesize($filename);
            if(empty($row['Filesize'])){
                if('cleanup' == $Action){
                    $note = 'updating file size';
                    $SQL = "UPDATE `att` SET
                    Filesize = '$filesize',
                    _ModDate = NOW(),
                    _ModBy = 0
                    WHERE AttachmentID = {$row['AttachmentID']}";
                    $result = $dbh->query($SQL);
                    dbErrorCheck($result);
                } else {
                    $note = 'db file size missing';
                }
            } elseif($row['Filesize'] != $filesize){
                $note = 'incorrect db file size';
            }
            $fileStatus = 'exists';
        } else {
            $fileStatus = 'missing';
            if('cleanup' == $Action){
                $note = 'deleting';
                $SQL = "UPDATE `att` SET
                    _Deleted = 1
                    WHERE AttachmentID = {$row['AttachmentID']}";
                $result = $dbh->query($SQL);
                dbErrorCheck($result);
            }
        }
    }

    $data[$rowNum][] = $row['ServerFile'];
    $data[$rowNum][] = $row['Filesize'];
    $data[$rowNum][] = $filesize;
    $data[$rowNum][] = $row['_Deleted'];

    $data[$rowNum][] = $fileStatus;
    $data[$rowNum][] = $note;

}
$textTable =& new TextTable($data, $cols);

print $textTable->render();




?>