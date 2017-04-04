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

if( isset($_GET['clear']) && '1' == $_GET['clear'] ){
    unset( $_SESSION['Search_'.$ModuleID] );
	unset( $_GET['clear'] );
}else{
	$search = $_SESSION['Search_'.$ModuleID];
}

if( empty($_POST['Search']) && empty($_POST['Chart']) ){
    $data = $search->postData;
}else{
    $data = $_POST;
}

//populate data array with posted values
if( !empty($data) ){
	foreach( $data as $fieldName=>$value ){
		if( isset($_POST[$fieldName]) ){
			$data[$fieldName] = $_POST[$fieldName];
		}
	}
}

//if the form was posted
if( (!empty($_POST['Search'])) || (!empty($_POST['Chart'])) ){


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
	$search->saveUserDefault($User->PersonID);

    //redirect depending on what submit buton was pressed by the user.
    if( !empty($_POST['Search']) ){
        $RedirectTo = "list.php?mdl=$ModuleID";
        header("Location:" . $RedirectTo);
        exit;  
    }else{
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


$clearSearchLink = '<a class="srchlnk" href="'.$targetlink .'&clear=1">'.gettext("Clear Search Filter (removing conditions)").'</a>';
//$tabs['List'] = Array("list.php?$qs", gettext("List|View the list of /**plural_record_name**/"));
$tabs['Search'] = Array("", gettext("Search"));

ob_start();
$content = '';
foreach( $fields as $key => $field ){
    if( !$field->isSubField() ){
        $content .= $field->searchRender($data, $phrases);
    }
}
ob_end_clean();

$content = renderSearchForm($content, $targetlink, null, $ModuleID);

//insert code to enable calendar controls
if( !$User->Client['is_Mobile'] ){
/**dateFields**/
}

// $content .= debug_r($search->postData, "postData");


$searchHistory = '';
$historySQL = "SELECT SearchPhrases, SearchPostData, _ModDate as SearchDate FROM `usrsh` 
 WHERE _Deleted = 0 AND UserID = {$User->PersonID} AND ModuleID = '$ModuleID' ORDER BY _ModDate DESC LIMIT 3";
$history = $dbh->getAll( $historySQL, DB_FETCHMODE_ASSOC );
dbErrorCheck($history );
$order_number = 0;
$oldestSearchDate = '';
if( count($history) > 0 ){	
	foreach( $history as $historyEntry){
		$order_number++;
		$searchPhrases = unserialize( $historyEntry['SearchPhrases'] );
		$searchPhrases = join( $searchPhrases, "<br />\n" );
		$searchPhrases = str_replace( '!themeDirectory!', $theme_web, $searchPhrases );
		$searchPostData = unserialize( $historyEntry['SearchPostData'] );		
		$hiddenInput = '<input type="hidden" name="Search" value="Search"/>';
		foreach( $searchPostData as $postKey => $postData){
			$hiddenInput .= "\n".'<input type="hidden" name="'.$postKey.'" value="'.$postData.'">';
		}
		$searchDate = $historyEntry['SearchDate'];
		$searchDateFormat = str_replace( ' ', '<span class="dttms">', $searchDate );
		$searchDateFormat = $searchDateFormat.'</span>';
		$oldestSearchDate = $searchDate;
		$searchHistory .= '<div class="searchHistory"><img src="'.$theme_web.'/img/search_icon.png"/>&nbsp;<span class="hstTtl">'.gettext("Search Filter Conditions from").' '.$searchDateFormat.'</span><br/><br/><br/>'."\n";
		$searchHistory .= $searchPhrases."<br/><br/><br/>\n";
		$searchHistory .='<form action="search.php?mdl='.$ModuleID.'" name="searchForm'.$order_number.'" method="post">';
		$searchHistory .= $hiddenInput;
		$searchHistory .= '<input class="btn" id="hstSrch" type="submit" name="Search'.$order_number.'" value="Wyszukiwanie">';
		$searchHistory .= '</form></div>';
	} 
} 
?>
