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

    /**CUSTOM_CODE|init**/

    //list of objects containing the field information
    /**fields**/

    //list of grids
    /**grids**/

    //phrases for field names, in field order
    /**phrases**/

    /**ownerField**/

    /**disbleGlobalModules**/
    /**useBestPractices**/

    //retrieve record
    $data = array();

    /**SQL|GET_BEGIN**/
    $SQL = "/**SQL|GET**/";

    $SQL = TranslateLocalDateSQLFormats($SQL);

    /**CUSTOM_CODE|before_get**/

    //get data
    $r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $SQL), DB_FETCHMODE_ASSOC);
    dbErrorCheck($r);

    /**SQL|GET_END**/	
    switch (count($r)){
    case 0:
		$linkHere = "view.php?mdl=$ModuleID&amp;rid=$recordID";
		$plainLink = str_replace('&amp;', '&', $linkHere);
		if( !empty($_GET['shortcut']) AND $_GET['shortcut'] == 'remove' ){
			RemoveDesktopShortcut($User->PersonID, $plainLink);
			$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>';
		}
		
		$dash_shortcutTitle = '';
		if(isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$plainLink])){
			$dash_shortcutTitle = $_SESSION['desktopShortcuts'][$plainLink];
			$dash_shortcutTitle = $dash_shortcutTitle.' ( '.gettext('View').' )';
			$dash_shortcutTitle =
			"<a href=\"view.php?mdl=$ModuleID&amp;rid=$recordID&amp;shortcut=remove\" 
			title=\"&nbsp; &nbsp; ".gettext("Click here to remove this page from the shortcuts on your home page.")." &nbsp; &nbsp;\">
			".gettext("Remove shortcut")."«&nbsp;<img src=\"themes/aa_theme/img/nav_bugreport.png\">
			".gettext("my Shortcuts")."»:  $dash_shortcutTitle</a>";
		}		
		
		$moduleInfo = GetModuleInfo($ModuleID);
		$recordIDField = $moduleInfo->getProperty('recordIDField');
		$SQL = "SELECT _ModBy, DATE(_ModDate) as ModDate, TIME(_ModDate) as ModTime FROM `$ModuleID` WHERE _Deleted = 1 AND $recordIDField = /**RecordID**/";
		$r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $SQL), DB_FETCHMODE_ASSOC);		
		dbErrorCheck($r);
		if( count( $r )>0 ){		
			$ModDate = $r[0]['ModDate'];
			$ModTime = $r[0]['ModTime'];
			$ModBy = $r[0]['_ModBy'];
			$SQL = "SELECT ppl.DisplayName as DisplayName, org.Name as Organization FROM ppl, org 
			WHERE ppl._Deleted = 0 AND org._Deleted = 0 AND ppl.PersonID = $ModBy AND ppl.OrganizationID = org.OrganizationID";
			$r = $dbh->getAll( $SQL, DB_FETCHMODE_ASSOC);		
			dbErrorCheck($r);
			if ( count( $r ) == 0 ){				
				$content = gettext("This record does not exist, or could not be found.");
			}else{
				$ModNameOrg = $r[0]['DisplayName'].' / '.$r[0]['Organization'];
				$user_message = gettext('The record has been deleted on %s at %s by the user:');
				$content = sprintf( $user_message, $ModDate, $ModTime )
				 .'<a href="view.php?mdl=ppl&rid='.$ModBy.'"> '
				 .$ModNameOrg.'</a><br/><br/>'.$dash_shortcutTitle.'<br/>';
			}
			$title = gettext('Record deleted');
			include_once $theme . '/nopermission.template.php';
			die;
		}else{		
			trigger_error(gettext("This record does not exist, or could not be found."), E_USER_ERROR);
		}
        break;
    case 1:
        break;
    default:
        trigger_error(gettext("More than one record was found."), E_USER_WARNING);
        break;
    }
	    
	$data = array_merge($data, $r[0]); //assign first (only) row

    /**CUSTOM_CODE|get**/

    //check if user has permission to view or edit record - will redirect if no permission at all
    //$allowEdit = $User->CheckViewScreenPermission();
    //$allowEdit = true;
	
	if( !empty( $EditScrPermission) ){	
		$allowEdit = true;
	}else{
		$allowEdit = $User->CheckViewScreenPermission();
	}

    $tabsQSargs = $qsArgs;
    unset($tabsQSargs['scr']);
    $tabsQS = MakeQS($tabsQSargs);

    //List tab
    $tabs['List'] = Array("list.php?$qs", gettext("List|View the list of ").gettext("/**plural_record_name**/"));

    if( $allowEdit ){
        /**tabs|EDIT**/
		if( !empty( $NoEditScrPermission) ){
			foreach( $NoEditScrPermission as $EditScreenName=> $NoPermissionModuleID){
				if( !empty($tabs[$EditScreenName]) ) unset( $tabs[$EditScreenName] );
			}
		}
    } else {
        /**tabs|VIEW**/
    }

    /**RecordLabelField**/

	$email_Subject = SanitizeEmailSubject( $recordLabelField );
	$linkRecord = "view.php?mdl=$ModuleID&amp;rid=$recordID";
	$encodedlinkRecord = 'frames.php?dest='.base64_encode( $linkRecord  );
	if( isset($_SERVER['HTTPS']) ) {
		$protocol_server_ext_adrr = 'https://'.SERVER_EXT_ADRR;
	}else{
		$protocol_server_ext_adrr = 'http://'.SERVER_EXT_ADRR;
	}
	$mailtoRecordTopicSubject = '?subject='.$email_Subject.'&body='.$protocol_server_ext_adrr.'/'.$encodedlinkRecord;
	
    $content = '';
    foreach($fields as $key => $field){
        if (!$field->isSubField()){
            $content .= $field->render($data, $phrases);
        }
    }

    $pageTitle = gettext("/**singular_record_name**/");
    $screenPhrase = gettext("/**screen_phrase**/");

    $backlink = "list.php?$qs";

    $nextScreen = "/**nextScreen**/";
    $editlink = "edit.php?$tabsQS&scr=$nextScreen";
    $content = renderViewTable($content, $allowEdit, $backlink, $editlink);

    //add byline info on record modification info
	$content .= "<div id=\"RecordInfo\" class=\"recinfo\">";
	$content .= '<a href="list.php?mdl=pu&filter=1&RelatedModuleID='.$ModuleID.'">
				'.gettext("Power users supporting this module").'</a>&nbsp;&nbsp;|&nbsp;&nbsp;';    
    $content .= '<a href="audit.php?mdl='.$ModuleID.'&rid='.$recordID.'&sr='.$_GET['sr'].'">'."{$phrases['_ModDate']} {$data['_ModDate']}".'</a> ';    
    $content .= "</div>";

    //display view grids here
/**VIEWGRIDS**/

if( isset($_GET['sr']) &&0 < strlen($_GET['sr']) ){
    list($prevLink,$nextLink) = GetSeqLinks( $ModuleID, $_GET['sr'], 'view.php' );
}
$siteNavigationSnip = null;
if( !empty($prevLink) ){
	$siteNavigationSnip .= '<div id="stNvSn"><a  id="arrowLeft" href="'.$prevLink.'" title="'.gettext("previous").'"></a> ';
}else{ 
	$siteNavigationSnip .=  '<div id="stNvSn"><div class="plchldr"></div>';
} 
if( isset($tabs['List'][0]) ){
	$siteNavigationSnip .= '<a class="arrowTab" href="'.$tabs['List'][0].'" title="&nbsp; &nbsp; '.gettext("List").' &nbsp; &nbsp;"></a>';
}
if( !empty($nextLink) ){
	$siteNavigationSnip .= ' <a  id="arrowRight" href="'.$nextLink.'" title="'.gettext("next").'"></a></div>';
}else{ 
	$siteNavigationSnip .=  '</div>';
} 

//View Screen Sections here
/**VIEWSCREENSECTIONS**/
?>
