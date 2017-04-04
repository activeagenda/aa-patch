<?php
/**
 * Handles content for the Audit Screen
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2008 Active Agenda Inc., All Rights Reserved.
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
 * copyright      2003-2008 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

//get the record ID
$recordID = intval($_GET['rid']);
if($recordID == 0){
    if(strlen($_GET['rid']) >= 3){
        $recordID = "'".substr($_GET['rid'], 0, 5)."'";
    }
}

$moduleInfo = GetModuleInfo($ModuleID);
$filename = GENERATED_PATH . '/'.$ModuleID.'/'.$ModuleID.'_ViewSer.gen';

//check for cached page for this module
if ( !file_exists($filename) ){
    trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find file")." '$filename'.", E_USER_ERROR);
}

//the included file sets $content variable used by template below
include($filename);


$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_Audit.gen";

//check for cached page for this module
if( !file_exists($filename) ){
    trigger_error("Could not find file '$filename'. ", E_USER_ERROR);
}

$messages = array(); //init

//the included file sets $recordIDField
include($filename);

//START
if( !isset( $_SESSION['User']->ModulesPerm->$ModuleID )  ){
	$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_EditScreenPermissions.gen";
	if ( !file_exists($filename) ){
		trigger_error(gettext("Could not find file:")." '$filename'. ", E_USER_ERROR);
	}
	include($filename);
	
	$_SESSION['User']->ModulesPerm->$ModuleID = '';
	$_SESSION['User']->ModulesNoPerm->$ModuleID = '';
	if( !empty($EditScrPermission ) ){
		foreach( $EditScrPermission as $EditScreenName => $PermissionModuleID ){
			if( 0 == $User->PermissionToEdit( $PermissionModuleID ) ){
				unset($EditScrPermission[$EditScreenName]);
				$NoEditScrPermission[$EditScreenName]= $PermissionModuleID;
			}	
		}	
		$_SESSION['User']->ModulesPerm->$ModuleID = $EditScrPermission;
		$_SESSION['User']->ModulesNoPerm->$ModuleID = $NoEditScrPermission;
	}
	$User = $_SESSION['User'];	
}else{
	$EditScrPermission = $User->ModulesPerm->$ModuleID;
	$NoEditScrPermission = $User->ModulesNoPerm->$ModuleID; 
}

if ( $User->checkRecordPermission( $ModuleID, $recordID) == 0 ){
	trigger_error(gettext("You don't have permission to view this record."), E_USER_ERROR);
};

if( !empty( $EditScrPermission) ){	
		$allowEdit = true;
	}else{
		$allowEdit = $User->CheckViewScreenPermission();
	}
//END

//get data
$logTable = $ModuleID.'_l';

//$SQL = "SELECT $logTable._ModDate as _Modified_On_Date, ppl.DisplayName as _Modified_By_Person, $logTable._Deleted as _Deleted, $logTable._TransactionID as _TransactionID, $logTable.* FROM $logTable LEFT OUTER JOIN ppl ON $logTable._ModBy = ppl.PersonID WHERE $logTable.$recordIDField = $recordID ORDER BY $logTable._ModDate DESC;"; 
$SQL = "SELECT $logTable._ModDate as _Modified_On_Date, ppl.DisplayName as _Modified_By_Person, org.Name as _Organization_Name, $logTable._Deleted as _Record_Deleted, $logTable._TransactionID as _TransactionID, $logTable.* 
FROM $logTable, ppl, org WHERE $logTable._ModBy = ppl.PersonID AND $logTable.$recordIDField = $recordID  
AND org.OrganizationID = ppl.OrganizationID ORDER BY $logTable._ModDate ASC;"; 
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);

if( count($r) > 0 ){
    //begin the audit grid..
    $content = '<table width="100%">';

    //add headers
    $tr = '<tr class="rcdlg">%s</tr>';
    $th = '<th class="l" width="%s" title="%s">%s</th>';
	$th1 = '<th class="adth" width="%s" title="%s">%s</th>';	
    foreach($r[0] as $fh => $fd){
		switch( $fh ){
			case '_Modified_On_Date':
				$fh = gettext('_Modified_On_Date');
				$headRow .= sprintf($th1, '1%', $fh, $fh);
				break;
			case '_Modified_By_Person':
				$fh = gettext('_Modified_By_Person');
				$headRow .= sprintf($th1, '1%', $fh, $fh);
				break;
			
			case '_Organization_Name':
				$fh = gettext('_Organization_Name');
				$headRow .= sprintf($th1, '1%', $fh, $fh);
				break;
			case '_Record_Deleted':
				$fh = gettext('_Record_Deleted');
				$headRow .= sprintf($th1, '1%', $fh, $fh);
				break;
			default:
				$headRow .= sprintf($th, '1%', $fh, $fh);
		}		
    }
	$headRowTr = sprintf($tr, $headRow);
    $content .= $headRowTr;
	
    //add rows
    $td = '<td class="%s" align="center" title="%s">%s</td>';
	$td1 = '<td class="%s" id="adtl" title="%s">%s</td>';
	
	$contentRowsAll = '';
	$rowOLD = array();
    $tdFormatting = array("l", "l2");
    foreach( $r as $rowNum => $row ){
        $contentRow = ''; 
        foreach( $row as $fh => $fd ){
			if( $fh == '_Modified_On_Date' OR $fh == '_Modified_By_Person' OR $fh == '_Organization_Name' OR $fh == '_Record_Deleted' ){
			
				if( $fh == '_Modified_By_Person' OR $fh == '_Record_Deleted' ){	
					if( $rowOLD[$fh] != $fd  ){	
						$fd = '<b>'.$fd.'</b>';
					}
				}
				
				if( $fh == '_Record_Deleted' ){
					if( $fd == 0 ){
						$fd = gettext('No');
					}else{
						$fd = gettext('Yes');
					}
				}				
				$contentRow .= sprintf($td1, $tdFormatting[$rowNum % 2], $fd, $fd);			
			}else{
				if( $fh == '_TransactionID' OR $fh == '_RecordID' OR $fh == '_ModDate' 
				    OR $fh ==  '_ModBy' OR $fh == '_Deleted' ){
					//
				}else{
					if( $rowOLD[$fh] != $fd  ){	
						$fd = '<b>'.$fd.'</b>';
					}
				}
				$contentRow .= sprintf($td, $tdFormatting[$rowNum % 2], $fd, $fd);
			}
        }					
		$rowOLD = $row;
        $contentRowTr = sprintf($tr, $contentRow);
		$contentRowsAll = $contentRowTr.$contentRowsAll;
    }
    $content .= $contentRowsAll.$headRowTr.'</table>';
}else{
    $content .= gettext("There is no data to view here.");
}

$jsIncludes = '';

$parentInfo = GetParentInfo( $ModuleID );
$viewLink = "view.php?mdl=$ModuleID&amp;rid=$recordID&amp;sr=".$_GET['sr'];
$isAuditScreen = True;
include_once $theme . '/audit.template.php';
?>