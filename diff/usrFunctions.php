<?php
/**
 * Utility functions needed by the usr module.
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
 * @version        SVN: $Revision: 1748 $
 * @last-modified  SVN: $Date: 2009-09-08 07:31:56 +0200 (Wt, 08 wrz 2009) $
 */

function verifyPassword($fieldName){
    global $messages;

    $pass = $_POST[$fieldName];
    if(empty($pass)){
        //no password: allowed for updates, not for inserts
        global $recordID;
        if(empty($recordID)){
            $messages[] = array('e', gettext("You must supply a password"));
            return false;
        } else {
            $messages[] = array('m', gettext("No password change"));
            global $data;
            unset($data[$fieldName]);
            unset($_POST[$fieldName]);
            return true;
        }
    } else {
        if($_POST[$fieldName] == $_POST[$fieldName.'_confirm']){
            $_POST[$fieldName] = encryptPassword($_POST[$fieldName]);

            return true;
        } else {
            $messages[] = array('e', gettext("The passwords you supplied did not match"));
            return false;
        }
    }

}

function encryptPassword($password){
    return crypt($password, CRYPT_SEED);
}


function checkForDeletedRow(){
    //changes $exisiting to true if a deleted row exists
    //this is useful because the PK field (PersonID) does not auto_increment

    global $dbh;
    global $data;

    $qPersonID = dbQuote($data['PersonID'], 'int');

    $SQL = "SELECT PersonID FROM usr WHERE PersonID = $qPersonID";
    $r = $dbh->getOne($SQL);
    dbErrorCheck($r);

    global $existing;

    if(empty($r)){
        $existing = false;
    } else {
        //"undeleete" the row
        $SQL = "UPDATE usr SET _Deleted = 0 WHERE PersonID = $qPersonID";
        $r = $dbh->query($SQL);
        dbErrorCheck($r);
        $existing = true;
    }
}


/**
 * Inserts rudimentary default permissions for a new user.
 */
function saveDefaultPermissions($personID)
{
    global $User;
    global $messages;

    $mdb2 =& GetMDB2();
    $mdb2->loadModule('Extended', null, false);

    //checks existing view permissions
    $SQL = "SELECT ModuleID, ViewPermission FROM `usrp` WHERE PersonID = $personID AND _Deleted = 0";
    $existingPerms = $mdb2->getAssoc($SQL);
    mdb2ErrorCheck($existingPerms);

    //adds permission to view Code Items
    if(!isset($existingPerms['cod'])){
        $SQL = "INSERT INTO usrp (PersonID, ModuleID, EditPermission, ViewPermission, _ModDate, _ModBy, _Deleted) VALUES ($personID, 'cod', 0, 2, NOW(), {$User->PersonID}, 0)";
        $res = $mdb2->exec($SQL);
        mdb2ErrorCheck($res);

        $messages[] = array('m', gettext("Added View permission to Code Items."));
    }

    //adds view org permission to Desktop Shortcuts module (there should be a "view own" permission level instead!)
    if(!isset($existingPerms['usrds'])){
        $SQL = "INSERT INTO usrp (PersonID, ModuleID, EditPermission, ViewPermission, _ModDate, _ModBy, _Deleted) VALUES ($personID, 'usrds', 0, 2, NOW(), {$User->PersonID}, 0)";
        $res = $mdb2->exec($SQL);
        mdb2ErrorCheck($res);

        $messages[] = array('m', gettext("Added View permission to Desktop Shortcuts."));
    }

    //add permitted org for the user's own organization, as a start
    $SQL = "SELECT COUNT(*) FROM `usrpo` WHERE PersonID = $personID";
    $posExists = $mdb2->queryOne($SQL);
    mdb2ErrorCheck($posExists);

    if(!$posExists){
        $SQL = "INSERT INTO usrpo (PersonID, OrganizationID, _ModDate, _ModBy, _Deleted) SELECT $personID, OrganizationID, NOW(), {$User->PersonID}, 0 FROM `ppl` WHERE PersonID = $personID";
        $res = $mdb2->exec($SQL);
        mdb2ErrorCheck($res);

        $SQL = "SELECT org.Name FROM ppl INNER JOIN org ON (ppl.OrganizationID = org.OrganizationID) WHERE ppl.PersonID = $personID";
        $orgName = $mdb2->queryOne($SQL);
        mdb2ErrorCheck($orgName);

        $messages[] = array('m', sprintf(gettext("Added organization %s as a permitted organization."), $orgName));
    }
}
?>