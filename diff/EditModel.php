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
/**hasEditableFields**/

/**skipSaveFields**/

$singularRecordName = gettext("/**singular_record_name**/");

//field value array
$data = array(
/**data**/
);

if(empty($_POST)){
    //pre-populate fields with URL values
    if(0 === $recordID){
        foreach($data as $fieldName=>$value){
            if(isset($_GET[$fieldName])){
                $data[$fieldName] = $_GET[$fieldName];
            }
        }
    }
} else {
    foreach($data as $fieldName=>$value){
        if(isset($_POST[$fieldName])){
            $data[$fieldName] = $_POST[$fieldName];
        }
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
unset($tabsQSargs['scr']);
unset($tabsQSargs['gid']);
unset($tabsQSargs['grw']);
$tabsQS = MakeQS($tabsQSargs);
$nextScreen = "/**nextScreen**/";
$nextlink = "edit.php?$tabsQS&scr=$nextScreen";
$form_enctype = '';

/**CUSTOM_CODE|init**/

$getSQL = "/**SQL|GET**/";

$getSQL = TranslateLocalDateSQLFormats($getSQL);

if(!empty($_POST['CloneForm'])){
    $recordID = 0;
}


$screenPhrase = gettext("/**screen_phrase**/");

/*populates screen messages differently depending on whether the record exists in db or not*/
if($recordID != 0) {
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
        trigger_error(gettext("This record does not exist, or could not be found.|Record not found."), E_USER_ERROR);
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

//check if user has permission to edit record
$allowEdit = $User->CheckEditScreenPermission();
//if not, it terminates and display error msg.

//phrases for field names, in field order
/**phrases**/

//if the form was posted by clicking the Save button
if(!empty($_POST['Save'])){
    /**DB_SAVE_BEGIN**/

    /**CUSTOM_CODE|save**/

    //validate submitted data:
    $vMsgs = "";
    /**VALIDATE_FORM**/

    if(0 != strlen($vMsgs)){
        //prepend a general error message
        $vMsgs = gettext("The record has not been saved, because:")."\n".$vMsgs;
        $vMsgs = nl2br($vMsgs);

        //return error messages
        $messages[] = array('e', $vMsgs);

    } else {
//START   	    
	    /**CUSTOM_CODE|normalize**/
//END
        /**CUSTOM_CODE|check_deleted_row_exists**/

        $dh = GetDataHandler($ModuleID);
//        $recordID = $dh->saveRow($data, $recordID, $skipSaveFields);
        $dbRecordID = $dh->saveRow($_POST, $recordID, $skipSaveFields);

        if(false === $dbRecordID){
            $errmsg = gettext("The record has not been saved, because:");
            foreach($dh->errmsg as $err => $id){
                $errmsg .= "\n".$err;
            }
            $errmsg = nl2br($errmsg);
            $messages[] = array('e', $errmsg);
        } else {
            $recordID = $dbRecordID;
        }

        //recreate $nextlink b/c of new record ID when inserting
        $inserted = false;
        if(!$existing){
            $qsArgs['rid'] = $recordID; //pass both to tabs and other links
            $tabsQSargs = $qsArgs;
            unset($tabsQSargs['scr']);
            //$tabsQSargs['rid'] = $recordID;
            $tabsQS = MakeQS($tabsQSargs);
            $nextlink = "edit.php?$tabsQS&scr=$nextScreen";
            if(empty($_POST['KeepNew'])){
                $existing = true;
            } else {
                $recordID = 0;
            }
            $inserted = true;
        }
    }

    /**CUSTOM_CODE|save_end**/
    /**DB_SAVE_END**/

    if(empty($_POST['KeepNew'])){
        if(count($messages) == 0){
            /**RE-GET_BEGIN**/
            //only executed on screens that need it: have ViewField with Update, or Calculated/Summary fields
            $r = $dbh->getAll(str_replace('/**RecordID**/', $recordID, $getSQL), DB_FETCHMODE_ASSOC);
            dbErrorCheck($r);
            if(count($r) > 0) {
                foreach($r[0] as $fieldName=>$dbValue){
                    //(checking for gridnum avoids interference with any posted edit grid)
                    if(empty($_POST['gridnum']) && isset($_POST[$fieldName])){
                        $data[$fieldName] = $_POST[$fieldName];
                    } else {
                        $data[$fieldName] = $dbValue;
                    }
                }
            } else {
                $messages[] = array('e', gettext("Error: Empty query result."));
            }
            /**RE-GET_END**/
        }
    } else {
        foreach($data as $fieldName => $fieldValue){
            $data[$fieldName] = '';
        }
    }

    //note: assumes all messages up til this point were errors
    if (count($messages) == 0){
        //add success message
        if ($inserted){
            $messages[] = array('m', gettext("The record was added successfully."));
        } else {
            $messages[] = array('m', gettext("The record was updated successfully."));
        }
    }
}
/**SQL|DELETE_BEGIN**/
if(!empty($_POST['Delete'])){

    $dh = GetDataHandler($ModuleID);
    $result = $dh->deleteRow($recordID);

    $deletelink = "list.php?$tabsQS";

    //redirect to list screen
    header("Location:" . $deletelink);
    exit;
}
/**SQL|DELETE_END**/

/**CUSTOM_CODE|after_save**/


if(!empty($_POST['KeepNew'])){
    unset($qsArgs['rid']); //ensures next submit will cause a new record
}

$qs = MakeQS($qsArgs);

//List tab
$tabs['List'] = Array("list.php?$tabsQS", gettext("List|View the list of /**plural_record_name**/"));

//target for FORMs
$targetlink = "edit.php?$qs";

$cloneAsNew = false;
$keepAddNew = false;

//formatting that depends on whether the record exists or not
if($existing){
    //delete button only appears on the first EditScreen.
    $deletelink = '/**deletelink**/';
    $cancellink = "view.php?$tabsQS";

    /**clone_as_new**/

    /**tabs|EDIT**/

} else {
    $deletelink = '';
    $cancellink = "list.php?$tabsQS";

    /**keep_add_new**/

    /**tabs|ADD**/
}

/**CUSTOM_CODE|form**/

$content = '';
foreach($fields as $key => $field){
    if (!$field->isSubField()){
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
    'clone'              => $cloneAsNew,
    'keep_add_new'       => $keepAddNew,
    'single_record_name' => $singularRecordName
);

$content = renderForm2($content, $targetlink, $formProps);

//insert code to enable calendar controls
/**dateFields**/

/**CUSTOM_CODE|after_form**/

//display edit grids here
/**GRIDS|DISPLAY**/

/**CUSTOM_CODE|after_grids**/

/**RecordLabelField**/
?>