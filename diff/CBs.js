/**
 * Combo box functions for s2a/Active Agenda
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

function SetSel(oCB, iID){
    oCB.selectedIndex = iID;
}

function AddOptions(oCB, arOptions) {
    ctr = 0; 
    len = arOptions.length;

    while(ctr < len) {
        arOption = arOptions[ctr];
        oOption = new Option(arOption[1], arOption[0], false, false);
        oCB.options[oCB.length] = oOption;
        ctr++;
    }
}

function RemoveOptions(oCB){    
    while(oCB.length > 0){
        oCB.options[0] = null;
    }
}

function ReloadPeople(sFormName, sFieldName, selOrgID, arPeople){
    //get a reference to the right select field
    var oPersonField = document.forms[sFormName].elements[sFieldName];

    RemoveOptions(oPersonField);
    arFiltered = FilterPeopleByOrg(arPeople, selOrgID);
    AddOptions(oPersonField, arFiltered);
    indicateUnsavedChanges(null, oPersonField);
    //oPersonField.focus();
}


function ReloadChildCB(sFormName, sParentName, sChildName, arChildItems, selChildID){
    //get a reference to the right select field
    var oChildField = document.forms[sFormName].elements[sChildName];
    var oParentField = document.forms[sFormName].elements[sParentName];

    selParentID = oParentField.value;
    cacheChildID = oChildField.value; //preserve selected value in case reloaded list is same
    
    RemoveOptions(oChildField);
    arFiltered = FilterPeopleByOrg(arChildItems, selParentID);
    AddOptions(oChildField, arFiltered);
    
    //select item, if given
    if(selChildID){
        var ixSelItem = GetSelectedIndex(arFiltered, selChildID);
        SetSel(oChildField, ixSelItem);
    } else {
        var ixSelItem = GetSelectedIndex(arFiltered, cacheChildID);
        SetSel(oChildField, ixSelItem);
    }
    if(pageLoaded){
        //alert(oChildField.name);
        if(selChildID == cacheChildID){
            return; //don't reload child list if not needed
        }
        oChildField.onchange();
        indicateUnsavedChanges(null, oChildField);
    }
}

function UpdateFields(sFormName, oParentField, arChildFieldNames){
    var ctr = 0;
    var len = arChildFieldNames.length;
    var sChildName;
    while(ctr < len){
        sChildName = arChildFieldNames[ctr];
        if(document.forms[sFormName].elements[sChildName]){
            try {
                arChildItems = eval('ar'+sChildName);
                ReloadChildCB(sFormName, oParentField.name, sChildName, arChildItems, 0);
            }
            catch(e) {
               //would be nice to only suppress ReferenceErrors here but browser implementations differ (IE returns TypeError)
            }
        } else {
            oViewField = document.getElementById(sFormName + '_' + sChildName);
            if(oViewField){
                //alert('exists: ' + sChildName);
                oViewField.innerHTML = 'ładowanie...';
                
                var req = new DataRequestor();
                req.addArg(_GET, "mdl", form_moduleID);
                req.addArg(_POST, "sender", oParentField.name);
                req.addArg(_POST, "value", oParentField.value);
                req.addArg(_POST, "recipient", sChildName);
                req.addArg(_POST, "formname", sFormName);
                req.getURL('rpc/ViewFieldUpdate.php');
                req.onload = function (data, obj) {
                    if('session timeout' == data){
                        alert("Na skutek wygaśnięcia sesji dane nie mogą być pobrane z serwera. Kliknij OK w celu ponownego zalogowania się.");
                        location.reload();
                        return false;
                    }

                    
                    if('ERROR' == data.substring(0,5)){
                        alert("Wystąpił bład:\n" + data);
                        return false;
                    }
                    if('<br' == data.substring(0,3)){
                        alert("Wystąpił błąd:\n" + data);
                        return false;
                    }
                    data = 'res = ' + data;
                    eval(data); //returns object (i.e. assoc array "available")
                    
                    if(res['error']){
                        cont = res['error'];
                        //return false;
                    } else {
                        cont = res['content'];
                    }
                    //alert(res['error']);return;
        //alert(data);

                    oField = document.getElementById(res['formname'] + '_' + res['recipient']);
                    if(!oField){
                        oField = oViewField;
                    }
                    oField.innerHTML = cont;
                }
                
            } else {
                //alert('does not exist : ' + sChildName);
            }
        }
        ctr++;
    }
}

function UpdateChildCBs(sFormName, oParentField, arChildFieldNames){
    var ctr = 0;
    var len = arChildFieldNames.length;
    var sChildName;
    
    while(ctr < len){
        sChildName = arChildFieldNames[ctr];
        arChildItems = eval('ar'+sChildName);
        ReloadChildCB(sFormName, oParentField.name, sChildName, arChildItems, 0);
        ctr++;
    }

}

function GetSelectedIndex(arOptions, selItemID){        
    ctr = 0;
    len = arOptions.length;
    found = false;
    
    //loop through array until we have found selItemID
    while(ctr < len) {
        arOption = arOptions[ctr];
        if(String(arOption[0]) == String(selItemID)){
            ix = ctr;
            found = true;
            break;
        }
        ctr++;
    }
    
    if (found){
        return ix;
    } else {
        return 0;
    }
}

function GetOrgIDofPerson(arPeople, ixSelPerson){
    arPerson = arPeople[ixSelPerson];
    return arPerson[2];//third element is OrganizationID
}

function FilterPeopleByOrg(arPeople, selOrgID){
    arFiltered = Array();
    ctr = 1; //starts filtering from the SECOND item
    len = arPeople.length;
    nextInsert = 1;
    
    //always add the "blank" element
    arPerson = arPeople[0];
    arFiltered[0] = arPerson;
    
    while(ctr < len) {
        arPerson = arPeople[ctr];
        if(String(selOrgID) == String(arPerson[2])){ 
            arFiltered[nextInsert] = arPerson;
            nextInsert++;
        }
        ctr++;
    }
    
    return arFiltered;
}

function PopulatePeopleCBs(sFormName, sOrgFieldName, sPersonFieldName, selPersonID, arPeople, arOrgs){

    //get references to the select fields
    var oOrgField = document.forms[sFormName].elements[sOrgFieldName];
    var oPersonField = document.forms[sFormName].elements[sPersonFieldName];
    
    //get array index of selected person
    var ixSelPerson = GetSelectedIndex(arPeople, selPersonID);
    
    //get org ID of selected person (using array index)
    var selOrgID = GetOrgIDofPerson(arPeople, ixSelPerson);

    //get array insex of selected org
    var ixSelOrg = GetSelectedIndex(arOrgs, selOrgID);
    
    AddOptions(oOrgField, arOrgs);
    arPeopleFiltered = FilterPeopleByOrg(arPeople, selOrgID);
    AddOptions(oPersonField, arPeopleFiltered);
    
    //get array index of filtered array
    ixSelPerson = GetSelectedIndex(arPeopleFiltered, selPersonID);
    
    //select the right items
    SetSel(oOrgField, ixSelOrg);
    SetSel(oPersonField, ixSelPerson);
}
