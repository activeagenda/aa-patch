<?php
/**
 * Displays an HTML report
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

//must precede page_startup.php
include_once CLASSES_PATH . '/search.class.php';

//main include file - performs all general application setup
require_once(INCLUDE_PATH . '/page_startup.php');


//main business here
$ReportName = addslashes($_GET['rpt']);
$recordID = intval($_GET['rid']);

$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Report_{$ReportName}.gen";

//check for cached page for this module
if (!file_exists($filename)){
    trigger_error("Could not find grid file '$filename'.", E_USER_ERROR);
}

include_once CLASSES_PATH . '/report.class.php';

//the included file sets $content variable used by template below
include_once($filename);

$content .= $report->renderHTML($recordID);

$title = gettext($report->title);
//$user_info;
//$tabs;
//$generalTabs;
$screenPhrase = ShortPhrase($screenPhrase);
//$content;

include_once $theme . '/popup.template.php';
?>