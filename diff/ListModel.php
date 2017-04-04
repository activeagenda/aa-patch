<?php
/**
 * Template file for generated files (alt. a generated file)
 *
 * PHP version 5
 *
 *
 * LICENSE NOTE:
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
 * @version        SVN: $Revision: 1548 $
 * @last-modified  SVN: $Date: 2009-03-05 16:27:47 +0100 (Cz, 05 mar 2009) $
 */

    //check if user has permission to view or edit record
    $allowEdit = $User->CheckListScreenPermission();

    $pageTitle = gettext("/**module_name**/");
    $screenPhrase = gettext("List");

    //remove search filter if user requested it
    if(isset($_GET['clear']) && '1' == $_GET['clear']){
        unset($_SESSION['Search_'.$ModuleID]);
    } 

    $expected_params = array('mdl', 'sr', 'ob', 'pp');

    //clean up query string
    foreach($qsArgs as $qsField => $qsVal){
        if(!in_array($qsField, $expected_params)){
            unset($qsArgs[$qsField]);
        }
    }
    $qs = MakeQS($qsArgs);

    $tabsQS = $qs; //legacy or needed?
    $sQS = $qs;    //legacy or needed?

    $linked = array_keys($linkFields);

    $tabs['List'] = Array('', gettext("List"), 'self');

    if ($allowEdit){
        /**tabs|EDIT**/
    } else {
        /**tabs|VIEW**/
    }


    //phrases for table headers, in field order
    /**headers**/

    //table column alignment values
    /**fieldAlign**/

    //table column data types - to format values (Yes/No, date, number, etc.)
    /**fieldTypes**/

    /**fieldFormats**/

    /**linkFields**/

    $useBestPractices = false; //default value
    /**useBestPractices**/

    /**defaultOrderBys**/

    $listFilterSQL = $User->getListFilterSQL($ModuleID);

    $nColumns = count($headers);

    $offset = 0;
    if(!empty($_GET['o'])){
        $offset = intval($_GET['o']);
    }

    $clearSearch = false;
    if(isset($_GET['clear']) && '1' == $_GET['clear']){
        $clearSearch = true;
    }

    $filterByURL = false;
    if(isset($_GET['filter']) && '1' == $_GET['filter']){
        $filterByURL = true;
        $clearSearch = true;
    }

    if(!$clearSearch && isset($_SESSION['Search_'.$ModuleID])){
        $search = $_SESSION['Search_'.$ModuleID];
    } else {
        //create an empty Search object
        $search = GetNewSearch($ModuleID);

        if(!$clearSearch && !$filterByURL){
            $search->loadUserDefault($User->PersonID);
        }
        if($filterByURL){
            $search->loadURLFilter();
        }
        $_SESSION['Search_'.$ModuleID] = $search;
    }

    $search = $_SESSION['Search_'.$ModuleID];
    $perPage = 10;
    if(!empty($_GET['pp'])){
        $perPage = intval($_GET['pp']);
    }

    $listData =& new ListData($ModuleID, $search->getListSQL(null, true), $perPage, $fieldTypes, true, null, $fieldFormats);
    $nRows = $listData->getCount();
    if(0 == $nRows){
        $content = gettext("The request returned no data. Please try a different search.").'<br /><br />';
    } else {
        $startRow = 0;
        if(!empty($_GET['sr'])){
            $startRow = intval($_GET['sr']);
        }
        $renderer =& new ListRenderer(
            $ModuleID,
            $listData,
            $headers,
            'view.php?',
            'list.php?',
            $fieldAlign,
            'list',
            $linkFields
        );
        $renderer->useBestPractices = $useBestPractices;
        $content = $renderer->render($startRow, $defaultOrderBys);
    }

    //return just the table HTML if this is an AJAX-style call
    if(isset($_GET['rpc']) && 1 == $_GET['rpc']){
        die($content);
    }

    //add the link to let user clear the filter
    if(is_object($search)){
        if(isset($_GET['defaultFilter']) && '1' == $_GET['defaultFilter']){
            $search->saveUserDefault($User->PersonID);
        }

        $content .= "<br />\n";
        $content .= '<div class="searchFilter"><b>'.gettext("Search Filter Conditions").':</b><br />'."\n";
        $content .= $search->getPhrases();

        if($search->hasConditions()){

            $content .= "<br />\n<br />\n";
            if($search->isUserDefault){
                $content .= gettext("This is your default search for this module.");
            } else {
                $defaultSearchLink = '<a href="list.php?defaultFilter=1&amp;'.$sQS.'">'.gettext("Make this my Default Filter for this module").'</a>';
                $content .= $defaultSearchLink;
            }

            $content .= "<br />\n";
            $clearSearchLink = '<a href="list.php?clear=1&amp;'.$sQS.'">'.gettext("Clear Search Filter (removing conditions)").'</a>';
            $content .= $clearSearchLink;
        }

        $content .= "<br />\n<br />\n<b>".gettext("Download Data").":</b><br />\n";

        $content .= '<div class="dl_icon">';
        $content .= '<a href="dataDownload.php?type=1&amp;'.$sQS.'" title="'.gettext("Download as Comma-separated Values (flat file)").'"><img src="'.$theme_web.'/img/dl-csv.png" alt="csv"/><br />';
        $content .= gettext("CSV");
        $content .= '</a></div>';

        $content .= '<div class="dl_icon">';
        $content .= '<a href="dataDownload.php?type=2&amp;'.$sQS.'" title="'.gettext("Download as an XML file").'"><img src="'.$theme_web.'/img/dl-xml.png"  alt="xml"/><br />';
        $content .= gettext("XML");
        $content .= '</a></div>';

        $content .= '<div class="dl_icon">';
        $content .= '<a href="dataDownload.php?type=3&amp;'.$sQS.'" title="'.gettext("Download as a spreadsheet file").'"><img src="'.$theme_web.'/img/dl-spreadsheet.png"  alt="spreadsheet"/><br />';
        $content .= gettext("Spreadsheet");
        $content .= '</a></div>';
        
        $content .= "\n";
        //custom code
        /**CUSTOM_CODE|accReassign**/

        $content .= '<div class="dl_icon_clear">&nbsp;</div>';
        $content .= "</div><br />\n";
    }
?>
