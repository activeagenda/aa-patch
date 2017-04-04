<?php
/**
 *  Defines the User class
 *
 *  This file contains the definition of the User class, which not only tracks
 *  common user data such as person ID and name, but also handles the permission
 *  checking, i.e. verifies that the user has permissions to view or edit.
 *  Run-time only.
 *
 *  PHP version 5
 *
 *
 *  LICENSE NOTE:
 *
 *  Copyright  2003-2009 Active Agenda Inc., All Rights Reserved.
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

include_once INCLUDE_PATH . '/general_util.php';


class User
{

var $PersonID;
var $UserName;
var $DisplayName;
var $Lang; //preferred user language
var $IsAdmin = false; //SiteAdmin
var $isAuthenticated = false;
var $requireNewPassword = true; //let Login iverride this
var $Permissions = array(); //array of arrays indexed by ModuleIDs. sub-array lists allowed organizationIDs and Edit (E) ot View (V)
var $DefaultAllowedOrgs = array();
var $previousVisit; //last previous login, excluding same day
var $defaultOrgID; //a user-selected organization ID to pre-populate selected OrgCombo fields
var $browserInfo = array();
var $dateFormats = array();
var $pageFormats = array();
var $sessionTimeout = null;

//constructor
function User()
{
    require PEAR_PATH . '/Net/UserAgent/Detect.php';
    $this->browserInfo['is_IE'] = Net_UserAgent_Detect::isIE();
	
	require PEAR_PATH . '/Mobile_Detect.php';
	$detect = new Mobile_Detect;	
	$this->Client['is_Mobile'] = $detect->isMobile();
	$this->Client['is_Tablet'] = $detect->isTablet();
}


function LoadPermissions()
{
    global $dbh;

    //$SQL = "SELECT ModuleID, EditPermission, ViewPermission FROM usrp WHERE PersonID = '{$this->PersonID}' AND _Deleted = 0 ORDER BY ModuleID";
    $SQL =
"SELECT
    MAX(perm.EditPermission) AS EditPermission,
    MAX(perm.ViewPermission) AS ViewPermission,
    perm.ModuleID
FROM
    (
    SELECT
        EditPermission,
        ViewPermission,
        ModuleID
    FROM
        usrp
    WHERE
        _Deleted = 0
        AND PersonID = '{$this->PersonID}'
    UNION
    SELECT
        EditPermission,
        ViewPermission,
        ModuleID
    FROM
        usrgm
        INNER JOIN usrgp
        ON (
            usrgm.UserGroupID = usrgp.UserGroupID
            AND usrgp._Deleted = 0
        )
    WHERE
        usrgm._Deleted = 0
        AND usrgm.PersonID = '{$this->PersonID}'
    ) as perm
GROUP BY
    perm.ModuleID
ORDER BY
    perm.ModuleID";

    $modPermissions = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($modPermissions);


    $SQL = "SELECT OrganizationID FROM usrpo WHERE PersonID = '{$this->PersonID}' AND _Deleted = 0";
    $orgPermissions = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($orgPermissions);

    foreach($modPermissions as $row){
        switch ($row['EditPermission'] . $row['ViewPermission']) {
        case '22':
            $this->Permissions[$row['ModuleID']]['e'] = 2;
            $this->Permissions[$row['ModuleID']]['v'] = 2;
            break;
        case '12':
            $this->Permissions[$row['ModuleID']]['e'] = 1;
            $this->Permissions[$row['ModuleID']]['v'] = 2;
            $this->Permissions[$row['ModuleID']]['o'] = 'default';
            break;
        case '11':
            $this->Permissions[$row['ModuleID']]['e'] = 1;
            $this->Permissions[$row['ModuleID']]['v'] = 1;
            $this->Permissions[$row['ModuleID']]['o'] = 'default';
        break;
        case '02':
            $this->Permissions[$row['ModuleID']]['e'] = 0;
            $this->Permissions[$row['ModuleID']]['v'] = 2;
            break;
        case '01':
            $this->Permissions[$row['ModuleID']]['e'] = 0;
            $this->Permissions[$row['ModuleID']]['v'] = 1;
            $this->Permissions[$row['ModuleID']]['o'] = 'default';
            break;
        default:
        //  don't even do this (we want to use the keys for a list of modules with any permssion but "none"):
        //    $this->Permissions[$row['ModuleID']]['e'] = 0;
        //    $this->Permissions[$row['ModuleID']]['v'] = 0;
            break;
        }
    }

    foreach($orgPermissions as $row){
        $this->DefaultAllowedOrgs[] = $row['OrganizationID'];
    }

} //end LoadPermissions



/**
 *  Determines the type of permission a user has to a record or module
 *
 *   return values:
 *       0 = no access to view, 
 *       1 = access to view records belonging to specified organizations,
 *       2 = access to view all records in module
 *   if $OrgID was not passed, the possible results are 0 and 1 only
 **/
function checkPermission($moduleID, $permissionType, $orgID = 0, $handleAccessDenied = true)
{
    if ($this->IsAdmin) {
        return 2;
    } else {
        $checkModuleID = $this->getPermissionModuleID($moduleID);

        if(0 == $orgID){
            if(array_key_exists($checkModuleID, $this->Permissions)){
                return $this->Permissions[$checkModuleID][$permissionType];
            } else {
                return 0;
            }
        } else {
            if(array_key_exists($checkModuleID, $this->Permissions)){  
                $permission = $this->Permissions[$checkModuleID];
                switch($permission[$permissionType]){
                case 2:
                    return 2;
                case 1:
                    $orgs = $this->getPermittedOrgs($moduleID);
                    return in_array($orgID, $orgs);
                default:
                    return 0;
                }
            } else {
                return 0;
            }
        }
    }
} //end checkPermission


/*  function PermissionToView
    return values: 
        0 = no access to view,
        1 = access to view records belonging to specified organizations,
        2 = access to view all records in module
    if $OrgID was not passed, the possible results are 0 and 1 only
*/
function PermissionToView($moduleID, $orgID = 0)
{
    return $this->checkPermission($moduleID, 'v', $orgID);
}


/**
 * Determines whether a user has permission to view a specific record.
 *
 * For modules that have an OwnerOrganizationID field, this function will look up
 * the record's owner organization in a separate SQL query.
 */
function PermissionToViewRecord($moduleID, $recordID)
{
    if($this->IsAdmin){
        return 2;
    }

    $moduleInfo = GetModuleInfo($moduleID);
    $ownerField = $moduleInfo->getProperty('ownerField');
    $recordIDField = $moduleInfo->getProperty('recordIDField');
    if(!empty($ownerField)){
        global $dbh;
        //$SQL = "SELECT `$ownerField` FROM `$moduleID` WHERE `$recordIDField` = '$recordID'";

        require(GENERATED_PATH.'/'.$moduleID.'/'.$moduleID.'_OwnerFieldSQL.gen'); //returns $ownerFieldSQL
        $ownerFieldSQL = str_replace('/**RecordID**/', $recordID, $ownerFieldSQL);
trace($ownerFieldSQL, 'PermissionToViewRecord');
        $orgID = $dbh->getOne($ownerFieldSQL);
        dbErrorCheck($orgID);
    } else {
        $orgID = null;
    }
    return $this->checkPermission($moduleID, 'v', $orgID);
}


/*  function PermissionToEdit
    to be called by pages to see if the user has permission to edit a
    particular module...
    return values: 
        0 = no access to edit, 
        1 = access to edit records belonging to specified organizations,
        2 = access to edit all records in module
    if $OrgID was passed, $PermittedOrgs is set to return an array of 
    permitted OrganizationIDs.
*/
function PermissionToEdit($moduleID, $orgID = 0)
{
    return $this->checkPermission($moduleID, 'e', $orgID);
}


function getPermissionModuleID($moduleID)
{

    if(defined('LOADING_NAVIGATION') && LOADING_NAVIGATION){
        static $permissionModuleIDs = array();

        if(count($permissionModuleIDs) == 0){
            //load parentModuleIDs file only once
            if(file_exists(GENERATED_PATH . '/ParentModuleIDs.gen')){
                include(GENERATED_PATH . '/ParentModuleIDs.gen');
            }
        }

        if(!isset($permissionModuleIDs[$moduleID])){
            return $moduleID;
        } else {
            return $permissionModuleIDs[$moduleID];
        }
    }

    $moduleInfo = GetModuleInfo($moduleID);
    return $moduleInfo->getPermissionModuleID();
}


function getPermittedOrgs($moduleID)
{
    $checkModuleID = $this->getPermissionModuleID($moduleID);
    $permission = $this->Permissions[$checkModuleID];

    if(array_key_exists('o', $permission)){
        if('default' == $permission['o']){
            return $this->DefaultAllowedOrgs;
        } else {
            return $permission['o']; //specific orgs for this module
        }
    } else {
        return array();
    }
}

function getRecordPermissionWhereSQL($moduleID)
{
	$globalmodules = array( 'ntf', 'att', 'cos', 'lnk', 'nts', 'rmd' );
	if( in_array( $moduleID, $globalmodules ) ){
		$accessSQL = ' AND ( ';
		
		//is Admin
		$accessSQL .= $this->PersonID.' in (select PersonID from usr where usr.isAdmin = 1 and usr._Deleted = 0)';
		
		// Access by record ownership: none, owner,
		$accessSQL .= 'OR (select rdc.OwnedBy from rdc where rdc.ModuleID = '.$moduleID.'.RelatedModuleID and rdc.RecordID = '.$moduleID.'.RelatedRecordID and rdc._Deleted = 0) in ( 0,'.$this->PersonID.' )';  
	
		//Workgroup member
		$accessSQL .= 'OR '.$this->PersonID.' in 
	(select usrwm.PersonID from  `'.$moduleID.'` as module, usrwm, usrw, rdc where usrwm.WorkGroupID = rdc.WorkGroupID
	and usrwm._Deleted = 0 and usrw._Deleted = 0 and rdc._Deleted = 0 and module._Deleted = 0
	and rdc.ModuleID = module.RelatedModuleID and rdc.RecordID = module.RelatedRecordID 
	and usrwm.WorkGroupID = usrw.WorkGroupID ) ';

		// Access by person allowed in usrp by the admin to view all records
		$accessSQL .= 'OR ('.$moduleID.'.RelatedModuleID, '.$moduleID.'.RelatedRecordID ) in 
		(select module.RelatedModuleID, module.RelatedRecordID from `'.$moduleID.'` as module, rdc, usrp where
		module._Deleted = 0 and rdc._Deleted = 0 and usrp._Deleted = 0
		and rdc.OwnedBy = usrp.PersonID and usrp.AllAccess = 1 and usrp.ModuleID = usrp.ModuleID
		and rdc.ModuleID = module.RelatedModuleID and rdc.RecordID = module.RelatedRecordID )';		

		// Access by person allowed in usrp by the admin to view records by  every owner's group member if allowed for the record owner
		$accessSQL .= ' OR ('.$moduleID.'.RelatedModuleID, '.$moduleID.'.RelatedRecordID ) in 
		(select module.RelatedModuleID, module.RelatedRecordID from `'.$moduleID.'` as module, rdc, usrp, usrgm where
		module._Deleted = 0 and rdc._Deleted = 0 and usrp._Deleted = 0 and usrgm._Deleted
		and rdc.ModuleID = module.RelatedModuleID and rdc.RecordID = module.RelatedRecordID 
		and usrp.GroupAccess = 1 and usrp.ModuleID  = \''.$moduleID.'\' and usrp.PersonID  = rdc.OwnedBy 
		and '.$this->PersonID.' in (select PersonID  from usrgm where usrgm.UserGroupID in 
        (select UserGroupID from usrgm where usrgm.PersonID = rdc.OwnedBy 
		and usrgm._Deleted = 0 and rdc._Deleted = 0) ) )';
	
		// Access to every record granted by admin to every member of the group
		$accessSQL .= ' OR  '.$moduleID.'.RelatedModuleID in 
		(select module.RelatedModuleID from `'.$moduleID.'` as module, rdc, usrgp, usrgm, usrg where
		module._Deleted = 0 and rdc._Deleted = 0 and usrgp._Deleted = 0 and usrgm._Deleted = 0 and usrg._Deleted =0 
		and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID 
		and rdc.ModuleID = module.RelatedModuleID and rdc.RecordID = module.RelatedRecordID 
		and usrgp.AllAccess = 1 and usrgp.ModuleID = module.RelatedModuleID 		
		and usrgp.UserGroupID in
		(select UserGroupID from usrgm where usrgm.PersonID = '.$this->PersonID.'  
        and usrgp._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
        and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID ) )';
		
		// Access to the owner's record only for owner's group members
				$accessSQL .= ' OR ('.$moduleID.'.RelatedModuleID, '.$moduleID.'.RelatedRecordID ) in 
		(select module.RelatedModuleID, module.RelatedRecordID from `'.$moduleID.'` as module, rdc, usrgp, usrgm, usrg where 
		module._Deleted = 0 and rdc._Deleted = 0 and usrgp._Deleted = 0 and usrgm._Deleted = 0 and usrg._Deleted =0 
		and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID 
		and rdc.ModuleID = module.RelatedModuleID and rdc.RecordID = module.RelatedRecordID 
		and usrgp.GroupAccess = 1 and usrgp.ModuleID = module.RelatedModuleID
		and usrgp.UserGroupID in 
		(select UserGroupID from usrgm where usrgm.PersonID = rdc.OwnedBy  and usrgm._Deleted = 0 and rdc._Deleted = 0)
        and '.$this->PersonID.' in 
       (select PersonID from usrgm 
        where usrgm._Deleted = 0 
		and usrgm.UserGroupID in 
		(select UserGroupID from usrgm where usrgm.PersonID = rdc.OwnedBy  
        and usrgp._Deleted = 0 and module._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
        and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID ) ) )';		
		
		$accessSQL .= ' ) ';
	} else {
		$accessSQL = ' AND ( ';

		// Access by record ownership: none, owner, admin
		$accessSQL .= ' ('.$moduleID.'._OwnedBy = 0 OR `'.$moduleID.'`._OwnedBy = '.$this->PersonID.' OR '.$this->PersonID.' in (select PersonID from usr where usr.isAdmin = 1 and usr._Deleted = 0) 
	OR '.$this->PersonID.' in (select usrwm.PersonID from `'.$moduleID.'` as module, usrwm, usrw where 
	usrwm.WorkGroupID = module._WorkGroupID and usrwm._Deleted = 0 and usrwm.WorkGroupID = usrw.WorkGroupID and usrw._Deleted = 0) ) ';

		// Access by person allowed in usrp by the admin to view all records
		$accessSQL .= ' OR `'.$moduleID.'`._OwnedBy IN (select usrp.PersonID from usrp where usrp.AllAccess = 1 
		and usrp.ModuleID = \''.$moduleID.'\' and usrp._Deleted = 0) ';

		// Access by person allowed in usrp by the admin to view records by  every owner's group member if allowed for the record owner
		$accessSQL .= ' OR '.$moduleID.'._OwnedBy IN 
	(select module._OwnedBy  from  `'.$moduleID.'` as module, usrp, usrgm where 
usrp.GroupAccess = 1 and usrp.ModuleID  = \''.$moduleID.'\' and usrp.PersonID  = module._OwnedBy 
and usrp._Deleted = 0  and module._Deleted = 0 and usrgm._Deleted = 0
and '.$this->PersonID.' in (select PersonID  from usrgm where 
usrgm.UserGroupID in (select UserGroupID from usrgm where usrgm.PersonID = module._OwnedBy and usrgm._Deleted = 0) ) )';

		// Access to every record granted by admin to every member of the group
		$accessSQL .= ' OR '.$moduleID.'._OwnedBy IN 
(select module._OwnedBy  from `'.$moduleID.'` as module, usrgp, usrgm, usrg where
usrgp.AllAccess = 1 and usrgp.ModuleID = \''.$moduleID.'\'  
and usrgp._Deleted = 0 and module._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID 
and usrgp.UserGroupID in 
(select UserGroupID from usrgm where usrgm.PersonID = '.$this->PersonID.'  
and usrgp._Deleted = 0 and module._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID ) )  ';
   
		// Access to the owner's record only for owner's group members
		$accessSQL .= ' OR '.$moduleID.'._OwnedBy IN 
(select module._OwnedBy  from `'.$moduleID.'` as module, usrgp, usrgm, usrg where
usrgp.GroupAccess = 1 and usrgp.ModuleID = \''.$moduleID.'\'  
and usrgp._Deleted = 0 and module._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID 
and usrgp.UserGroupID in (select UserGroupID from usrgm where usrgm.PersonID = module._OwnedBy  and usrgm._Deleted = 0)
and '.$this->PersonID.' in (select PersonID from usrgm 
where usrgm._Deleted = 0 and usrgm.UserGroupID in (select UserGroupID from usrgm where usrgm.PersonID = module._OwnedBy  
and usrgp._Deleted = 0 and module._Deleted = 0 and  usrg._Deleted = 0 and usrgm._Deleted = 0 
and usrg.UserGroupID = usrgp.UserGroupID and usrg.UserGroupID = usrgm.UserGroupID ) ) ) ';

		$accessSQL .= ' ) ';
	} 
	return $accessSQL;
}


function getListFilterSQL($moduleID, $die = false)
{
    $moduleInfo = GetModuleInfo($moduleID);
	
	$accessSQL = $this->getRecordPermissionWhereSQL($moduleID);
    $ownerField = $moduleInfo->getProperty('ownerField');

    if( !empty($ownerField) ){
        //see what type of view permission the user has
        $permissionLevel = $this->checkPermission($moduleID, 'v');
        switch(intval($permissionLevel)){
        case 2:
            return $accessSQL;
            break;
        case 1:
            $ownerFieldFilter = $moduleInfo->getProperty('ownerFieldFilter');

            $orgArray = $this->getPermittedOrgs($moduleID);
            $orgString = join(',', $orgArray);
            return sprintf(' AND ' . $ownerFieldFilter, $orgString).$accessSQL;
            break;
        default:
            return ' AND 0 = 1 ';
        }
    } else {
        //we don't check access permission to the module here - do this at the top of the List screen
       // return '';
	   return $accessSQL;
    }
} //end getListFilterSQL

// Check access persmission for view and edit actions
function getRecordPermissionSQL($moduleID, $rid)
{
    $moduleInfo = GetModuleInfo($moduleID);
	$recordIDField = $moduleInfo->getPKField();    
	
	$accessSQL = 'SELECT EXISTS( select * from `'.$moduleID.'` where  '.$recordIDField .' = '.$rid;
	$accessSQL .= $this->getRecordPermissionWhereSQL($moduleID);
	$accessSQL .= ' ) as result';
   
	return $accessSQL;
}

function checkRecordPermission($moduleID, $rid)
{
	global $dbh;
	
	$checkRecordSQL = $this->getRecordPermissionSQL( $moduleID, $rid );
	$row = $dbh->getRow( $checkRecordSQL, null,DB_FETCHMODE_ASSOC);
	return $row['result'];
}

//checks view permission; redirects if no permission
//returns EDIT permission (!)
function CheckViewScreenPermission()
{
    global $ModuleID;
    global $recordID;
    global $data;

    $moduleInfo = GetModuleInfo($ModuleID);
    $ownerField = $moduleInfo->getProperty('ownerField');

    if(!empty($ownerField)){
        $permission = $this->PermissionToView($ModuleID, $data[$ownerField]);
    } else {
        $permission = $this->PermissionToView($ModuleID);
    }

    switch($permission){
    case 2:
    case 1:
        if(!empty($ownerField)){
            return $this->PermissionToEdit($ModuleID, $data[$ownerField]);
        } else {
            return $this->PermissionToEdit($ModuleID);
        }
        break;
    default:
        $this->_handleAccessDenied($ModuleID, $recordID);
    }
}


//checks view permission; redirects if no permission
//returns EDIT permission (!)
function CheckListScreenPermission()
{
    global $ModuleID;
    $permission = $this->PermissionToView($ModuleID);

    switch($permission){
    case 2:
    case 1:
            return $this->PermissionToEdit($ModuleID);
        break;
    default:
        $this->_handleAccessDenied($ModuleID, null);
        global $qs;
        header('Location:nopermission.php?'.$qs);
        exit();
    }
}


//checks edit permission; redirects if no permission
function CheckEditScreenPermission()
{
    global $ModuleID;
    global $data;
    global $recordID;

    $moduleInfo = GetModuleInfo($ModuleID);
    $ownerField = $moduleInfo->getProperty('ownerField');

    if(!empty($ownerField)){
        $permission = $this->PermissionToEdit($ModuleID, $data[$ownerField]);
    } else {
        $permission = $this->PermissionToEdit($ModuleID);
    }

    switch($permission){
    case 2:
    case 1:
        return true;
        break;
    default:
        $this->_handleAccessDenied($ModuleID, $recordID, 'edit');
    }
}

function CheckEditScreenPermission1( $SubModuleID )
{
    global $ModuleID;
    global $data;
    global $recordID;
// It doesn't need to be sub.
    $moduleInfo = GetModuleInfo( $SubModuleID );
    $ownerField = $moduleInfo->getProperty('ownerField');

    if( !empty($ownerField) ){
        $permission = $this->PermissionToEdit( $SubModuleID, $data[$ownerField] );
    } else {
        $permission = $this->PermissionToEdit( $SubModuleID );
    }

    switch($permission){
    case 2:
    case 1:
        return true;
        break;
    default:
        $this->_handleAccessDenied($ModuleID, $recordID, 'edit');
    }
}

function BoolCheckEditScreenPermission()
{
    global $ModuleID;
    global $data;
    global $recordID;

    $moduleInfo = GetModuleInfo($ModuleID);
    $ownerField = $moduleInfo->getProperty('ownerField');

    if(!empty($ownerField)){
        $permission = $this->PermissionToEdit($ModuleID, $data[$ownerField]);
    } else {
        $permission = $this->PermissionToEdit($ModuleID);
    }

    switch($permission){
    case 2:
    case 1:
        return true;
        break;
    default:
        return false;
    }
}

function _handleAccessDenied($moduleID, $recordID, $accessType = 'view', $die = false)
{

    global $dbh;
    $escaped_moduleID = $dbh->escapeSimple($moduleID);
    $escaped_recordID = $dbh->quoteSmart($recordID);

    //handle logging
    $recordID = intval($_GET['rid']);

    $this->saveLogEntry(10, "Access Denied to $accessType $escaped_moduleID, record $escaped_recordID.", true);

    //die or redirect
    if($die){
        die("Access denied");
    } else {
        global $qs;
        header('Location:nopermission.php?'.$qs);
        exit();
    }
}


//login function
function Login($pUserName, $pPassword)
{
    //returns true or false

    //connect to DB
    require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class
    $dbh = DB::connect(DB_DSN);
    dbErrorCheck($dbh);

    $username = $dbh->quote($pUserName);
    $password = $pPassword;

    $SQL = "SELECT
        P.PersonID,
        P.DisplayName,
        U.IsAdmin,
        U.RequireNewPassword,
        C.Value AS Lang,
        P.OrganizationID,
        U.SessionTimeout
    FROM
        usr AS U
        INNER JOIN ppl AS P
            ON P.PersonID = U.PersonID
        LEFT OUTER JOIN cod as C
            ON U.LangID = C.CodeID
            AND C.CodeTypeID = 138
    WHERE
        U._Deleted = 0 AND U.LoginLocked = 0 
		AND ( U.AccountValidFrom  <=  CURDATE() OR U.AccountValidFrom IS NULL )
		AND ( CURDATE() <= U.AccountValidTo OR U.AccountValidTo IS NULL )
        AND U.UserName = $username
        AND U.Password = '".crypt($password, CRYPT_SEED)."'";

    //execute query - get just one row
    $Row = $dbh->getRow($SQL, null, DB_FETCHMODE_ASSOC);
    dbErrorCheck($Row);
    if( isset($Row['PersonID']) ){
        $this->isAuthenticated = true;
        $this->UserName = $username;
		$this->Login = $pUserName;
        $this->DisplayName = $Row['DisplayName'];
        $this->PersonID = $Row['PersonID'];
        $this->IsAdmin = $Row['IsAdmin'];
        $this->Lang = $Row['Lang'];
        $this->requireNewPassword = $Row['RequireNewPassword'];        
        $this->defaultOrgID = $Row['OrganizationID'];
        $this->sessionTimeout = $Row['SessionTimeout'];

        if (! $this->IsAdmin) {
            $this->LoadPermissions();
        }

        //this will be used for restricting the modules being displayed in the navigation menu
        if(defined('MENU_FILTER_IMPLEMENTED_MODULES') && MENU_FILTER_IMPLEMENTED_MODULES){
            $SQL = 'SELECT DISTINCT
                `modd`.ModuleID
            FROM 
                `irmml`
                INNER JOIN `modd` ON
                    `irmml`.ModuleID = `modd`.DependencyID
            WHERE `irmml`._Deleted = 0
            UNION
            SELECT
                `irmml`.ModuleID
            FROM `irmml`
            WHERE `irmml`._Deleted = 0';
            $r = $dbh->getCol($SQL);
            dbErrorCheck($r);

            //adding global modules + road map module itself
            array_push( $r, 'act', 'att', 'cos', 'lnk', 'nts', 'irm', 'rmd' );
            $_SESSION['VisibleModules'] = $r;
        }


        $SQL = "SELECT
            MAX(_ModDate) AS LastLogin
        FROM usrl
        WHERE 
            PersonID = '{$this->PersonID}' 
            AND _ModDate < CURDATE()
            AND EventTypeID = 1";
        $r = $dbh->getOne($SQL);
        dbErrorCheck($r);
        $this->previousVisit = $r;

        $this->saveLogEntry(1, 'login', true);

        //prevents session fixation
        session_regenerate_id();

        //get user group timeouts, if needed
        if(defined('SESSION_USER_TIMEOUT_ENABLED') && SESSION_USER_TIMEOUT_ENABLED){
            if(empty($this->sessionTimeout)){
                $SQL = "SELECT
                    MIN(usrg.SessionTimeout)
                FROM usrg
                    INNER JOIN usrgm
                    ON usrg.UserGroupID = usrgm.UserGroupID
                    AND usrgm._Deleted = 0
                WHERE usrgm.PersonID = {$this->PersonID}
                    AND usrg._Deleted = 0";

                trace($SQL, 'user group timeout SQL');

                $r = $dbh->getOne($SQL);
                dbErrorCheck($r);
                $this->sessionTimeout = $r;
                trace($r, 'user group timeout is');
            }
        }

        if(defined('NOTIFY_LOGINS_EMAIL') && NOTIFY_LOGINS_EMAIL){
            $IP = $_SERVER['REMOTE_ADDR'];
            $addr = gethostbyaddr($IP);
            $email_msg = "User {$this->DisplayName} (ID {$this->PersonID}) logged in from IP $IP  ($addr).";

            $from = 'no-reply@'.$_SERVER["HOSTNAME"];
            if(defined('EMAIL_SYSTEM_FROM_ADDRESS')){
                $from = EMAIL_SYSTEM_FROM_ADDRESS;
            }

            //send login notice to administrator
            sendEmail($from, EMAIL_LOGIN_NOTIFICATION_ADDRESS, SITE_SHORTNAME . ' (successful login)', $email_msg);
        }

        $this->_loadLocalSettings();

        return true;
    } else {
        if(defined('NOTIFY_LOGINS_EMAIL') && NOTIFY_LOGINS_EMAIL){
            $IP = $_SERVER['REMOTE_ADDR'];
            $addr = gethostbyaddr($IP);
            $email_msg = "Failed login from IP $IP ($addr). Username $username.";
            //send login notice to administrator
            $from = 'no-reply@'.$_SERVER["HOSTNAME"];
            if(defined('EMAIL_SYSTEM_FROM_ADDRESS')){
                $from = EMAIL_SYSTEM_FROM_ADDRESS;
            }

            sendEmail($from, EMAIL_LOGIN_NOTIFICATION_ADDRESS, SITE_SHORTNAME . ' (failed login)', $email_msg);
        }

        $this->saveLogEntry(9, 'failed login', true);
        return false;
    }

}


function Logout()
{

    $this->saveLogEntry(2, 'logout', false);

}


/**
 * Returns a user object of a specified user
 *
 * This is in order to allow command-line scripts to perform tasks on behalf of the user.
 * Note that this is a static function.
 */
function &Masquerade($pUserName)
{
    if(!defined('EXEC_STATE') || 2 != EXEC_STATE){
        trigger_error("The Masquerade function may not be used in this context.", E_USER_ERROR);
        return null;
    }

    global $dbh;
    $username = $dbh->quote($pUserName);
    $SQL = "SELECT
        P.PersonID,
        P.DisplayName,
        U.IsAdmin,
        U.RequireNewPassword,
        C.Value AS Lang,
        P.OrganizationID
    FROM
        usr AS U
        INNER JOIN ppl AS P
            ON P.PersonID = U.PersonID
        LEFT OUTER JOIN cod as C
            ON U.LangID = C.CodeID
            AND C.CodeTypeID = 138
    WHERE
        U._Deleted = 0 AND U.LoginLocked = 0
        AND U.UserName = $username";

    $row = $dbh->getRow($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($row);

    if (!isset($row['PersonID'])) {
        trigger_error("No user found by the name '$pUserName'.\n", E_USER_ERROR);
        return null;
    }

    $user = new User();
    $user->isAuthenticated = true;
    $user->UserName = $username;
    $user->DisplayName = $row['DisplayName'];
    $user->PersonID = $row['PersonID'];
    $user->IsAdmin = $row['IsAdmin'];
    $user->Lang = $row['Lang'];
    $user->requireNewPassword = $row['RequireNewPassword'];
    $user->defaultOrgID = $row['OrganizationID'];

    if (! $user->IsAdmin) {
        $user->LoadPermissions();
    }

    return $user;
}


function Block()
{
    //TODO: lock the user out from the application (prevents from logging in)

}


function _loadLocalSettings()
{
    if(count($this->dateFormats) == 0){
        //default settings:
        $this->dateFormats = array(
            'date'       => 'YYYY-MM-DD',
            'dateDB'     => '%Y-%m-%d',
            'dateTime'   => 'YYYY-MM-DD HH:MM',
            'dateTimeDB' => '%Y-%m-%d %H:%i',
            'datePHP'    => '%Y-%m-%d',
            'dateTimePHP'=> '%Y-%m-%d %H:%M',
            'dateCal'    => '%Y-%m-%d',
            'dateTimeCal'=> '%Y-%m-%d %H:%M',
            'timeFormat' => '24',
            'timePHP'    => '%H:%M',
            'timeDB'     => '%H:%i',
            'mondayFirst'=> true,
            'weekNumbers'=> true
        );
        $this->pageFormats = array('A4', 'A3');

        $localSettingsFilePath = LOCALE_PATH .'/'. $this->Lang .'/settings.php';
        if(file_exists($localSettingsFilePath)){
            include_once $localSettingsFilePath;

            //copies local settings while ensuring that any missing entries have a default value
            foreach($this->dateFormats as $formatName => $formatValue){
                if(isset($dateFormats[$formatName])){
                    $this->dateFormats[$formatName] = $dateFormats[$formatName];
                }
            }

            if(count($pageFormats) > 0){
                $this->pageFormats = $pageFormats;
            }
        }
    }
}


function getDateFormat($formatID = null)
{
    $this->_loadLocalSettings();

    if(empty($formatID)){
        $formatID = 'date';
    }
    return $this->dateFormats[$formatID];
}


function getDateFormatPHP()
{
    $this->_loadLocalSettings();
    return $this->dateFormats['datePHP'];
}


function getTimeFormat()
{
    $this->_loadLocalSettings();
    return $this->dateFormats['timeFormat'];
}

function getTimeFormatPHP()
{
    $this->_loadLocalSettings();
    if('12' == $this->dateFormats['timeFormat']){
        $format = '%I:%M %p';
    } else {
        $format = '%H:%M';
    }
    return $format;
}

function getDateTimeFormatPHP()
{
    $this->_loadLocalSettings();
    return $this->dateFormats['dateTimePHP'];
}


//same as getDateFormat but returns a string usable by the calendar control
function getDateFormatCal()
{
    $this->_loadLocalSettings();
    return $this->dateFormats['dateCal'];
}


//returns calendar init settings based on user's language/locale
function getCalFormat($hasTime = false)
{
    $this->_loadLocalSettings();
    if($hasTime){
        $settings = "\tifFormat    : \"{$this->dateFormats['dateTimeCal']}\",\n";
        $settings .= "\tdaFormat    : \"{$this->dateFormats['dateTimeCal']}\",\n";
        $settings .= "\ttimeFormat  : {$this->dateFormats['timeFormat']},";
    } else {
        $settings = "\tifFormat    : \"{$this->dateFormats['dateCal']}\",\n";
        $settings .= "\tdaFormat    : \"{$this->dateFormats['dateCal']}\",\n";
    }
    $bools = array('false', 'true');
    $settings .= "\tmondayFirst : {$bools[$this->dateFormats['mondayFirst']]},\n";
    $settings .= "\tweekNumbers : {$bools[$this->dateFormats['weekNumbers']]},";
    return $settings;
}


function saveLogEntry($pEventTypeID, $pEventDescription, $saveURL = true)
{
    global $dbh;

    if( empty($dbh) ){
        //connect to DB
        require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class
        $dbh = DB::connect(DB_DSN);
        dbErrorCheck($dbh);
    }

    $escaped_requestURL = '';
    if($saveURL){
        if( get_magic_quotes_gpc() ){
            $escaped_requestURL = $_SERVER['REQUEST_URI']; //addslashes($_SERVER['REQUEST_URI']);
        }else{
            $escaped_requestURL = $dbh->escapeSimple($_SERVER['REQUEST_URI']);
        }
    }

    $personID = intval($this->PersonID); //makes it 0 if empty

    $IP = $_SERVER['REMOTE_ADDR'];
	$Mobile = $this->Client['is_Mobile']; 
	$Tablet = $this->Client['is_Tablet'];
	
    //log user event
    $SQL = "INSERT INTO usrl (
        PersonID,
        EventTypeID,
        EventDescription,
        EventURL,
        RemoteIP,
		Mobile,
		Tablet,
        _ModDate
    ) VALUES (
        '{$personID}',
        $pEventTypeID,
        '$pEventDescription',
        '$escaped_requestURL',
        '$IP',
		'$Mobile',
		'$Tablet',
        NOW())";

    $r = $dbh->query($SQL);

    dbErrorCheck($r);
}


function advanceSessionTimeout()
{
    if(defined('SESSION_DEFAULT_TIMEOUT')){
        $timeout = time() + SESSION_DEFAULT_TIMEOUT * 60;
    } else {
        $timeout = time() + 1200; //20 minutes
    }

    if(defined('SESSION_USER_TIMEOUT_ENABLED') && SESSION_USER_TIMEOUT_ENABLED){
        //fancy user timeout stuff go here
        if(!empty($this->sessionTimeout)){
            $timeout = time() + ($this->sessionTimeout * 60);
        }
    }

    $_SESSION['Timeout'] = $timeout;
}

} //end class User

?>
