<?php
/**
 * Displays th Glossary
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


//this causes session timeouts to display a message instead of redirecting to the login screen 
DEFINE('IS_POPUP', true);

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

$requestedLetter = 'A';
if(isset($_GET['r'])){
    $requestedLetter = trim(substr($_GET['r'],0,1));
}

//using famous letters of the alphabet as tabs
$tabs = array();
$letterSQL = "SELECT DISTINCT UPPER(LEFT(GlossaryItem, 1)) AS Letter from glo ORDER BY Letter ASC;";

$r = $dbh->getCol($letterSQL);
dbErrorCheck($r);

if(count($r) > 0) {
    $content = '';

    //add row of alphabet navigation links
    foreach($r as $linkLetter){
        if($linkLetter == $requestedLetter){
            $tabs[$linkLetter] = array('', $linkLetter);
        } else {
            $tabs[$linkLetter] = array('glossary.php?r='.$linkLetter, $linkLetter.'|View definitions beginning with the letter '.$linkLetter.'.');
        }
    }
    $content .= "\n";

    $SQL = "SELECT GlossaryItem, Definition FROM `glo` WHERE Display = 1 AND GlossaryItem LIKE '$requestedLetter%' ORDER BY GlossaryItem ASC";


    $letterContent = array(); //compiled content, by letter
    $letters = array(); //letters represented in list (no need to make links to unrepresented letters)

    //get glossary items
    $r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($r);

    if(count($r) > 0){
        $currentLetter = strtoupper($r[0]['GlossaryItem'][0]); //first letter of item title...
        foreach($r as $row){
            if(strtoupper($row['GlossaryItem'][0]) != $currentLetter){
                $currentLetter = strtoupper($row['GlossaryItem'][0]);
            }

            $content .= "<div class=\"glossaryDef\">";
            $content .= "<h4>{$row['GlossaryItem']}</h4>\n";
            $defs = split("\n",$row['Definition']);
            foreach($defs as $def){
                $content .= "<p>{$def}</p>\n";
            }
            $content .= "</div>\n";
        }
    } else {
        $content = sprintf(gettext("No glossary items found for letter %s."), $requestedLetter);
    }

} else {
    $content = gettext("No glossary items found.");
}




$jsIncludes = '';
$title = gettext("Glossary List");
$screenPhrase = $currentLetter;
$closeLink = 'javascript:self.close();opener.focus();';
$closeLabel = gettext("Close");

include_once $theme . '/popup.template.php';
?>