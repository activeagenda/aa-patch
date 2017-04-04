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

/**CUSTOM_CODE|classdef**/

//list of objects containing the field information
/**fields**/

$Subfields = array();
foreach( $fields as $field ){
	if( !empty($field->Fields) ){
		foreach( $field->Fields as $subfield ){
			$subfield->parentName = $field->name;
		}
		$Subfields = array_merge( $Subfields, $field->Fields );
	}
}
$fieldsset  = array_merge( $fields, $Subfields );
/**hasEditableFields**/

/**skipSaveFields**/

$singularRecordName = gettext("/**singular_record_name**/");

//field value array
$data = array(
/**data**/
);

if(empty($_POST)){
	foreach($_GET as $fieldName=>$value){
		if(isset($_GET[$fieldName])){
			if( $fieldName == 'scr' || $fieldName == 'rid' 
				|| $fieldName == 'sr' || $fieldName == 'mdl'){ 
				continue;
			}
			$_POST[$fieldName] = $_GET[$fieldName];
		}
    }    
}
 
foreach($data as $fieldName=>$value){
	if(isset($_POST[$fieldName])){
		$data[$fieldName] = $_POST[$fieldName];
	}
}


//list of grids
/**GRIDS|DEFINE**/

/**guidanceGrid**/

/**PKField**/
/**ownerField**/

/**disbleGlobalModules**/

//handle any posted grid form
/**GRIDS|SAVE**/

$tabsQSargs = $qsArgs;
$EditScreenName = $tabsQSargs['scr'];
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);
unset($tabsQSargs['delete']);
$cancelQS = MakeQS($tabsQSargs);
unset($tabsQSargs['sr']);
$tabsQS = MakeQS($tabsQSargs);
$nextScreen = "/**nextScreen**/";
$nextlink = "edit.php?$tabsQS&scr=$nextScreen";
$form_enctype = '';

/**CUSTOM_CODE|init**/

$getSQL = "/**SQL|GET**/";

$getSQL = TranslateLocalDateSQLFormats($getSQL);

// Passing delete comand by URL and GET
$deleteByGET = false;
if( !empty( $_GET['delete'] ) ){
	$_POST['Delete'] = 1;
	$deleteByGET = true;
	unset( $_GET['delete'] );
}

$screenPhrase = gettext("/**screen_phrase**/");

/*populates screen messages differently depending on whether the record exists in db or not*/
if( $recordID != 0 ){
    $existing = true;

    $pageTitle = gettext("/**singular_record_name**/");

    /**CUSTOM_CODE|before_get**/

    //retrieve record
    /**SQL|GET_BEGIN**/
    $r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $getSQL), DB_FETCHMODE_ASSOC);
    dbErrorCheck($r);
    /**SQL|GET_END**/

    switch (count($r)){
    case 0:
		$linkHere = "edit.php?mdl=$ModuleID&amp;rid=$recordID&amp;scr=$ScreenName";
		$plainLink = str_replace('&amp;', '&', $linkHere);
		if( !empty($_GET['shortcut']) AND $_GET['shortcut'] == 'remove'){
			RemoveDesktopShortcut($User->PersonID, $plainLink);
			$JSredirect= '<script type="text/javascript"> parent.location.href="frames.php?dest='.base64_encode($linkHere).'"; </script>';
		}
				
		$dash_shortcutTitle = '';
		if( isset($_SESSION['desktopShortcuts']) && isset($_SESSION['desktopShortcuts'][$plainLink]) ){
			$dash_shortcutTitle = $_SESSION['desktopShortcuts'][$plainLink];			
			$dash_shortcutTitle = $dash_shortcutTitle.' ( '.ShortPhrase($screenPhrase).' )';
			$dash_shortcutTitle =
			"<a href=\"edit.php?mdl=$ModuleID&amp;rid=$recordID&amp;scr=$ScreenName&amp;shortcut=remove\" 
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
				$content = gettext("This record does not exist, or could not be found.")
				 .'</a><br/><br/>'.$dash_shortcutTitle.'<br/>';
			}else{
				$ModNameOrg = $r[0]['DisplayName'].' / '.$r[0]['Organization'];
				$user_message = gettext('The record has been deleted on %s at %s by the user:');
				$content = sprintf( $user_message, $ModDate, $ModTime )
				 .'<a href="view.php?mdl=ppl&rid='.$ModBy.'"> '.$ModNameOrg
				 .'</a><br/><br/>'.$dash_shortcutTitle.'<br/>';
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

    //populate data array, combining POSTed data with DB record:
    //POST data takes precedence
    foreach($r[0] as $fieldName=>$dbValue){
        //(checking for gridnum avoids interference with any posted edit grid)
        if (empty($_POST['gridnum']) && isset($_POST[$fieldName])){
            $data[$fieldName] = $_POST[$fieldName];
        } else {
            $data[$fieldName] = $dbValue;
        }
    }


    /**CUSTOM_CODE|get**/
} else {
    //inserting a record
    $existing = false;
    $pageTitle = gettext("/**module_name**/");
	
    /**CUSTOM_CODE|new**/
}
$formEntryExisting = $existing;

//check if user has permission to edit record
if( !empty( $EditScrPermission) AND !empty($EditScrPermission[$EditScreenName]) ){	
	$allowEdit = $User->CheckEditScreenPermission1( $EditScrPermission[$EditScreenName] );
}else{
	$allowEdit = $User->CheckEditScreenPermission();
}
//if not, it terminates and display error msg.

//phrases for field names, in field order
/**phrases**/

//if the form was posted by clicking the Save button
if( !empty($_POST['Save']) ){
    /**DB_SAVE_BEGIN**/

    /**CUSTOM_CODE|save**/

    //validate submitted data:
	foreach( $data as $fieldName=>$dataValue ){
	
		if( $fields[$fieldName]->dataType == 'float' 
			or $fields[$fieldName]->dataType == 'money'
			or $fields[$fieldName]->dataType == 'int' ){
			if( !empty($dataValue) ){
				$dataValue = str_replace( ' ', '', $dataValue );
				$data[$fieldName] = str_replace( DECIMAL_SEPARATOR, '.', $dataValue );
			}
			if( isset( $_POST[$fieldName] ) ){
				$_POST[$fieldName] = $data[$fieldName];
			}			
		}
		
		if( $fields[$fieldName]->dataType == 'datetime' ){
			if( !empty($dataValue) ){
				$dataValue = trim( $dataValue );
				$timestamp = date_create_from_format('d.m.y G:i', $dataValue );
				if( $timestamp === false ){
					$timestamp = date_create_from_format('d.m.y G.i', $dataValue );
				}	
				if( $timestamp === false ){
					$timestamp = date_create_from_format('d.m.y G', $dataValue );
				}
				if( $timestamp === false ){
					$timestamp = date_create_from_format('y-m-d G', $dataValue );
				}
				if( $timestamp === false ){
					$timestamp = date_create_from_format('Y-m-d G', $dataValue );
				}
				if( $timestamp === false ){
					$timestamp = date_create( $dataValue );
				}			
				if( $timestamp === false ){
					$data[$fieldName] = $dataValue;
				}else{
					//$data[$fieldName] = date_format( $timestamp, 'Y-m-d H:i' );
					$dateformat = str_replace( "%", '', $User->getDateTimeFormatPHP() );
					$dateformat = str_replace( "I", 'g', $dateformat  );
					$dateformat = str_replace( "M", 'i', $dateformat  );
					$dateformat = str_replace( "p", 'a', $dateformat  );
					$data[$fieldName] = date_format( $timestamp, $dateformat );
				}
			}
			if( isset( $_POST[$fieldName] ) ){
				$_POST[$fieldName] = $data[$fieldName];
			}			
		}
		
		if( $fields[$fieldName]->dataType == 'date' ){
			if( !empty($dataValue) ){
				$dataValue = str_replace( ' ', '', $dataValue );
				$timestamp = date_create_from_format('d.m.y', $dataValue );
				if( $timestamp === false ){
					$timestamp = date_create( $dataValue );
				}
				if( $timestamp === false ){
					$data[$fieldName] = $dataValue;
				}else{
					//$data[$fieldName] = date_format( $timestamp, 'Y-m-d' );
					$data[$fieldName] = date_format( $timestamp, str_replace( "%", '', $User->getDateFormatPHP()) );
				}
			}
			if( isset( $_POST[$fieldName] ) ){
				$_POST[$fieldName] = $data[$fieldName];
			}			
		}
		
		if( $fields[$fieldName]->dataType == 'time' ){	
			if( !empty($dataValue) ){
				$dataValue = str_replace( ' ', '', $dataValue );
				$timestamp = date_create_from_format('G', $dataValue );			
				if( $timestamp === false ){
					$timestamp = date_create( $dataValue );
				}			
				if( $timestamp === false ){
					$data[$fieldName] = $dataValue;
				}else{
					$today = new DateTime();
					$midnight =  new DateTime( date_format( $today , 'Y-m-d' ) );				
					$interval = date_diff($midnight, $timestamp);
					if( $interval->days > 0 OR $timestamp->getOffset() != $midnight->getOffset() ){
						$data[$fieldName] = $dataValue;
					}else{
						$data[$fieldName] = date_format( $timestamp, 'H:i' );
					}
				}
			}
			if( isset( $_POST[$fieldName] ) ){
				$_POST[$fieldName] = $data[$fieldName];
			}			
		}
		
	}
	
    $vMsgs = "";
    /**VALIDATE_FORM**/

    if( 0 != strlen($vMsgs) ){
        //prepend a general error message
        $vMsgs = gettext("The record has not been saved, because:")."\n".$vMsgs;
        $vMsgs = nl2br($vMsgs);

        //return error messages
        $messages[] = array('e', $vMsgs);

    }else{
//START   	    
	    /**CUSTOM_CODE|normalize**/
//END
        /**CUSTOM_CODE|check_deleted_row_exists**/

        $dh = GetDataHandler($ModuleID);
//		$dbRecordID = $dh->saveRow($data, $recordID, $skipSaveFields);
        $dbRecordID = $dh->saveRow($_POST, $recordID, $skipSaveFields);
        if( is_array( $dbRecordID ) AND isset( $dbRecordID['code'] ) ){
			$errmsg = gettext("The record has not been saved, because:")."\n- ".$dbRecordID['native_code'];
			$errmsg = nl2br($errmsg);
            $messages[] = array('e', $errmsg);
		}elseif( false === $dbRecordID ){
			$errmsg = gettext("The record has not been saved, because:");
			foreach($dh->errmsg as $err => $id){
				$errmsg .= "\n".$err;
			}
			$errmsg = nl2br($errmsg);
			$messages[] = array('e', $errmsg);        	
		}else{
				$recordID = $dbRecordID;
		}		
		
        //recreate $nextlink b/c of new record ID when inserting
        $inserted = false;
        if( !$existing ){
            $qsArgs['rid'] = $recordID; //pass both to tabs and other links
            $tabsQSargs = $qsArgs;
            unset($tabsQSargs['scr']);
            //$tabsQSargs['rid'] = $recordID;
            $tabsQS = MakeQS($tabsQSargs);
			$cancelQS =  $tabsQS;
            $nextlink = "edit.php?$tabsQS&scr=$nextScreen";
			$existing = true;
            $inserted = true;
        }
    }

    /**CUSTOM_CODE|save_end**/
    /**DB_SAVE_END**/
   
	if( count($messages) == 0 ){
		/**RE-GET_BEGIN**/
		//only executed on screens that need it: have ViewField with Update, or Calculated/Summary fields
		$r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $getSQL), DB_FETCHMODE_ASSOC);
		dbErrorCheck($r);
		if( count($r) > 0 ) {
			foreach( $r[0] as $fieldName=>$dbValue ){
				//(checking for gridnum avoids interference with any posted edit grid)
				//if(empty($_POST['gridnum']) && isset($_POST[$fieldName])){
				//    $data[$fieldName] = $_POST[$fieldName];
				//} else {
					$data[$fieldName] = $dbValue;
				//}
			}
		}else{
			$messages[] = array('e', gettext("Error: Empty query result."));
		}
		/**RE-GET_END**/
	}
    

    //note: assumes all messages up til this point were errors
    if( count($messages) == 0 ){
		$editError = false;
        //add success message
        if( $inserted ){
            $messages[] = array('m', gettext("The record was added successfully."));
        }else{
            $messages[] = array('m', gettext("The record was updated successfully."));
        }
		
		// if no error messages than operation is OK
		unset( $_GET['sr'] );
		unset($qsArgs['sr']);
		$sr = GetRecordSr($ModuleID, $recordID, $PKField);
		if( isset( $sr ) ){
			$qsArgs['sr'] = $sr;
			list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $sr, 'edit.php');
			$tabsQS = $tabsQS.'&amp;sr='.$sr;			
		}else{
			$prevLink = null;
			$nextLink = null;
		}
		
		//Go the record postion on the list if no GoFrame is set
		// Form destination changed to view.php. For compatibilty the name GoForm stays the same
		if( empty($_POST['GoForm']) AND !$formEntryExisting ){
			//redirect to the nondefault screen set by onNewGoEditScreen="..."
			/**go_EditScreen**/
			if( isset( $goEditScreen ) ){							
				header( 'Location:edit.php?scr='.$goEditScreen.'&'.str_replace( 'amp;', '', $tabsQS ) );
				exit;
			}else{
				header( 'Location:list.php?'.str_replace( 'amp;', '', $tabsQS ) );
				exit;
			}
		}
		
		if( !empty($_POST['GoForm']) AND !$formEntryExisting ){
			//redirect to record on the list screen
			header( 'Location:view.php?'.str_replace( 'amp;', '', $tabsQS) );			
			exit;
		}
		
		/**go_ListScreen**/
		if( $goListScreen ){
			header( 'Location:list.php?'.str_replace( 'amp;', '', $tabsQS ) );
			exit;
		}
		
		/**go_ViewScreen**/
		if( $goViewScreen ){
			header( 'Location:view.php?'.str_replace( 'amp;', '', $tabsQS) );	
			exit;
		}
		
		/**go_EditScreen1**/
		if( isset( $goEditScreen1  ) ){							
			header( 'Location:edit.php?scr='.$goEditScreen1.'&'.str_replace( 'amp;', '', $tabsQS ) );
			exit;
		}
		
    }else{
		$editError = true;
	}	
}



/**SQL|DELETE_BEGIN**/
if( !empty( $_POST['Delete'] ) ){
	if( /**DELETE_BY_GET**/ ){		
		//retrieve record
		/**SQL|GET_BEGIN**/
		$r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $getSQL), DB_FETCHMODE_ASSOC);
		dbErrorCheck($r);
		/**SQL|GET_END**/

		switch (count($r)){
		case 0:
			trigger_error(gettext("This record does not exist, or could not be found."), E_USER_ERROR);
			break;
		case 1:
			break;
		default:
			trigger_error(gettext("More than one record was found."), E_USER_WARNING);
			break;
		}

		foreach($r[0] as $fieldName=>$dbValue){			
			$data[$fieldName] = $dbValue;
		}
	}

	$vMsgs = "";
    /**VALIDATE_OWNEDBY**/

    if( 0 != strlen($vMsgs) ){
        //prepend a general error message
        $vMsgs = gettext("The record has not been deleted, because:")."\n".$vMsgs;
        $vMsgs = nl2br($vMsgs);

        //return error messages
        $messages[] = array('e', $vMsgs);

    }else{	
		$dh = GetDataHandler($ModuleID);
		$result = $dh->deleteRow($recordID);
		if( is_array($result) AND isset($result['code']) ){
			$vMsgs = gettext("The record has not been deleted, because:")."\n- ".$result['native_code'];
			$vMsgs = nl2br($vMsgs);
			$messages[] = array('e', $vMsgs);
		}else{

			$deletelink = "list.php?$tabsQS";
			$rcount = $_SESSION[$ModuleID . '_ListSeq']['count']-1;
			$redirectSr = $_GET['sr'];
			if( $_GET['sr'] == $rcount ){
				if( $_GET['sr'] == 0 ){
					$redirectSrLnk = '';
				}else{
					$redirectSr = $redirectSr -1;
					$redirectSrLnk = '&sr='.$redirectSr;
				}
			}else{
				$redirectSrLnk = '&sr='.$redirectSr;
			}
			$deletelink = 'list.php?mdl='.$ModuleID.$redirectSrLnk;
			//redirect to list screen
			header("Location:" . $deletelink);
			exit;
		}
	}
}
/**SQL|DELETE_END**/

/**CUSTOM_CODE|after_save**/


$qs = MakeQS($qsArgs);

//List tab
$tabs['List'] = Array("list.php?$qs", gettext("List|View the list of ").gettext("/**plural_record_name**/"));

//target for FORMs
$targetlink = "edit.php?$qs";

//formatting that depends on whether the record exists or not
if( $existing ){
    //delete button only appears on the first EditScreen.
    $deletelink = '/**deletelink**/';
    $cancellink = "view.php?$cancelQS";

    /**clone_as_new**/

    /**tabs|EDIT**/
	if( !empty( $NoEditScrPermission) ){
		foreach( $NoEditScrPermission as $EditScreenName=> $NoPermissionModuleID ){
			if( !empty($tabs[$EditScreenName]) ) unset( $tabs[$EditScreenName] );
		}
	}

}else{
    $deletelink = '';
    $cancellink = "list.php?$tabsQS";

    /**keep_add_new**/
	
	$filename = GENERATED_PATH . '/'.$ModuleID.'/'.$ModuleID.'_ListCtxTabs.gen';
	if ( !file_exists($filename) ){
		trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$filename'.", E_USER_ERROR);
	}
	include($filename);
	//  START
    $tabs['New'] = array( "", gettext("Add New") );
   // END
}

/**CUSTOM_CODE|form**/

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
foreach( $fieldsset as $key => $field ){
    if( !$field->isSubField() ){
        $content .= $field->render($data, $phrases);
    }
}

$formProps = array(
    /**is_main_form**/
    'delete_button'      => strlen($deletelink) > 0,
    'cancel_link'        => $cancellink,
    'next_screen'        => $nextScreen,
    'form_enctype'       => $form_enctype,
    'module_id'          => $ModuleID,
    'render_buttons'     => $hasEditableFields,
    'single_record_name' => $singularRecordName,
	'is_existing'		 => $formEntryExisting
);

$content = renderForm2($content, $targetlink, $formProps);

//insert code to enable calendar controls
if( !$User->Client['is_Mobile'] ){
/**dateFields**/
}

/**CUSTOM_CODE|after_form**/

$content .= '<div id="enfl"></div>';

//display edit grids here
/**GRIDS|DISPLAY**/

/**CUSTOM_CODE|after_grids**/

?>