<?php
/**
 * Template file for generated files (alt. a generated file)
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
 * @version        SVN: $Revision: 1627 $
 * @last-modified  SVN: $Date: 2009-05-11 22:22:15 +0200 (Pn, 11 maj 2009) $
 */


//list of objects containing the field information
/**fields**/

/**ownerField**/

//phrases for field names, in field order (note: phrases are used in search filter)
/**phrases**/

$tabsQSargs = $qsArgs;
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);
$tabsQS = MakeQS($tabsQSargs);


$pageTitle = gettext("/**module_name**/");
$screenPhrase = gettext("/**screen_phrase**/");
global $SQLBaseModuleID;
$SQLBaseModuleID = $ModuleID;

$search = $_SESSION['Search_'.$ModuleID];

if(empty($_POST['Search']) && empty($_POST['Chart'])){
    $data = $search->postData;
} else {
    $data = $_POST;
}

//populate data array with posted values
foreach($data as $fieldName=>$value){
    if(isset($_POST[$fieldName])){
        $data[$fieldName] = $_POST[$fieldName];
    }
}


//if the form was posted
if((!empty($_POST['Search'])) || (!empty($_POST['Chart']))){


    //List fields (used for generating the complete SQL search statement):
    /**list_fields**/


    //link fields (fields that provide a URL, and not necessarily displayed
    //as a separate column in the list screen
    /**linkFields**/


    //create a Search definition object
    $search = new Search(
        $ModuleID,
        $listFields,
        $fields,
        $_POST
    );


    //then post it to the Search session object.
    $_SESSION['Search_'.$ModuleID] = $search;


    //redirect depending on what submit buton was pressed by the user.
    if(!empty($_POST['Search'])){

        $RedirectTo = "list.php?mdl=$ModuleID";
        header("Location:" . $RedirectTo);
        exit;
        

    } else {

        //handle "Chart" (TO DO)
        $RedirectTo = "charts.php?mdl=$ModuleID";
        header("Location:" . $RedirectTo);
        exit;

    }

}

//moved down from above
$qs = MakeQS($qsArgs);

//links for rendering the form
$targetlink = "search.php?$qs";
$cancellink = "list.php?$qs";

$tabs['List'] = Array("list.php?$qs", gettext("List|View the list of /**plural_record_name**/"));
$tabs['Search'] = Array("", gettext("Search"));

ob_start();
$content = '';
foreach($fields as $key => $field){
    if (!$field->isSubField()){
        $content .= $field->searchRender($data, $phrases);
    }
}
ob_end_clean();

$content = renderSearchForm($content, $targetlink, null, $ModuleID);

//insert code to enable calendar controls
/**dateFields**/

// $content .= debug_r($search->postData, "postData");

?>
