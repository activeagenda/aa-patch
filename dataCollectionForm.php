<?php
/**
 * Generates a PDF data collection form
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
 * author         Mattias Thorslund <mthorslund@activeagenda.net>
 * copyright      2003-2009 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

include_once CLASSES_PATH . '/components.php';

//main business here
$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_DataCollection.gen";

//check for cached page for this module
if (!file_exists($filename)){
    trigger_error("Could not find file '$filename'.", E_USER_ERROR);
}
include_once $filename; //provides $dataCollection

$moduleInfo = GetModuleInfo($ModuleID);
$moduleName = $moduleInfo->getProperty('moduleName');

//DEV
$saveAsName = 'form_'.$ModuleID.'_'.date( 'Y-m-d_H.i.s').'.xls';

//using PEAR class Spreadsheet_Excel_Writer
require_once(PEAR_PATH . '/Spreadsheet/Excel/Writer.php');
$workbook = new Spreadsheet_Excel_Writer();
$workbook->setVersion(8);
    
//send headers
$workbook->send($saveAsName);

//format for the header row
$labelFormat =& $workbook->addFormat();
$labelFormat->setBold();
$labelFormat->setFgColor( 27 );
$labelFormat->setBorder( 2 );
$labelFormat->setHAlign( 'center' );

$descriptionFormat =& $workbook->addFormat();
$descriptionFormat->setFgColor( 26 );
$descriptionFormat->setBorder( 1 );
$descriptionFormat->setHAlign( 'center' );
$descriptionFormat->setTextWrap();
$descriptionFormat->setVAlign( 'top');
$descriptionFormat->setItalic();

$moduleInfo = GetModuleInfo($ModuleID);
$worksheet =& $workbook->addWorksheet( $ModuleID );
$worksheet->setInputEncoding('utf-8');
$colwidths = array();

$n = 0;
foreach($dataCollection as $screenName => $screen){
    foreach($screen as $type => $fieldnames){
        if('fields' == $type){
		    foreach($fieldnames as $field){
				$label = ShortPhrase(gettext($field->phrase));
				$description = LongPhrase(gettext($field->phrase));
			    $worksheet->write(0, $n, $label, $labelFormat);
				$worksheet->write(1, $n, $description, $descriptionFormat);
			    $colwidth = strlen($label)+6;
				$worksheet->setColumn($n, $n, $colwidth);
			    $n++;
			}
		}
    }
}
$worksheet->freezePanes(array(2)); //first 2  row visible
$workbook->close();
?>