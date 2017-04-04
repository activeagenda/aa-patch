/**
 * General JS functions for s2a/Active Agenda
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


/*****************************************************
 functions findPosX and findPosY from 
 www.quirksmode.org - no copyright reserved by author
******************************************************/
function findPosX(obj)
{
    var curleft = 0;
    if (obj.offsetParent)
    {
        while (obj.offsetParent)
        {
            curleft += obj.offsetLeft
            obj = obj.offsetParent;
        }
    }
    else if (obj.x)
        curleft += obj.x;
    return curleft;
}

function findPosY(obj)
{
    var curtop = 0;
    if (obj.offsetParent)
    {
        while (obj.offsetParent)
        {
            curtop += obj.offsetTop
            obj = obj.offsetParent;
        }
    }
    else if (obj.y)
        curtop += obj.y;
    return curtop;
}

/*
functions for EditGrid "AJAX" form - by MJT
*/
var bkpRowClass = '';
var editRowID = '';

function editRow(rowID){
    if(editRowID == rowID){
        return cancelEditRow(rowID);
    }
    if(editRowID != ''){
        cancelEditRow(editRowID);
    }
    editRowID = rowID;
    d = document.getElementById('gridFormDiv');
    l = document.getElementById('loc'+rowID.toString());
    oRow = l.parentNode;
    bkpRowClass = l.className;
    for(t in oRow.childNodes) {
        oTD = oRow.childNodes[t];
        if(oTD.tagName == 'TD'){
            oTD.className = 'l3';
        }
    }
    for(t in l.childNodes){
        oA = l.childNodes[t];
        if(oA.tagName == 'A'){
            //alert(oA);
            oA.blur();
        }
    }
    editGridGetForm(d, moduleID, submoduleID, rowID);
    d.style.top = (l.offsetHeight+ findPosY(l))+'px';
    d.style.left = (l.offsetWidth+ findPosX(l))+'px';
    d.innerHTML = '<br/>Pobieranie danych z serwera...<br/>';
    d.style.display = 'block';
}


function cancelEditRow(rowID){
    editRowID = '';
    d = document.getElementById('gridFormDiv');
    if(rowID){
        l = document.getElementById('loc'+rowID.toString());
        oRow = l.parentNode;
        for(t in oRow.childNodes) {
            oTD = oRow.childNodes[t];
            if(oTD.tagName == 'TD'){
                oTD.className = bkpRowClass;
            }
        }

        for(t in l.childNodes){
            oA = l.childNodes[t];
            if(oA.tagName == 'A'){
                oA.blur();
            }
        }
    }
    bkpRowClass = '';

    d.style.display = 'none';

    //removing TinyMCE from the textareas (avoids "this.getDoc() has no properties" error on a second save)
    arTextAreas = d.getElementsByTagName('textarea');
    for(i = 0; i < arTextAreas.length; i++){
        oTextArea = arTextAreas[i];
        tinyMCE.execCommand('mceRemoveControl', false, oTextArea.id);
    }
}

function editGridGetForm(d, moduleID, submoduleID, rowID){
    var req = new DataRequestor();
    req.addArg(_GET, "mdl", moduleID);
    req.addArg(_GET, "smd", submoduleID);
    req.addArg(_GET, "rid", recordID);
    req.addArg(_GET, "grw", rowID);
    req.addArg(_GET, "action", 'getform');
    editRowID = rowID;

    req.getURL('rpc/EditGridRPC.php');
    req.onload = function (indata, obj){

        if(sessionTimeout(indata)){
            return false;
        }

        resp = eval('('+indata+')');
        if(resp["error"]){
            alert(resp["error"]);
        }
        cont = resp['content']; //variable name "content" seems to be reserved in IE???
        d.innerHTML = cont;

        //getting TinyMCE to show in the popup forms
        arTextAreas = d.getElementsByTagName('textarea');
        for(i = 0; i < arTextAreas.length; i++){
            oTextArea = arTextAreas[i];
            //alert(oTextArea.value);
            tinyMCE.execCommand('mceAddControl', false, oTextArea.id);
        }

        setupFormTooltips();
        setupFormEffects();

        //assigning innerHTML does not cause contained javascript to get executed (all browsers??)
        jscode = '';
        copystart = cont.indexOf('[js:]');
        copyend = null;
        while(copystart > -1){ 
            copyend = cont.indexOf('[:js]', copyend);
            jscode = cont.substring(copystart+5, copyend);
            eval(jscode);
            copystart = cont.indexOf('[js:]', copyend);
            copyend = copyend + 5
        }
    }
}

function editGridSaveForm(moduleID, submoduleID, rowID){
    d = document.getElementById('gridFormDiv');
    l = document.getElementById('loc'+rowID.toString());
    oRow = l.parentNode;
    editRowID = rowID;

    var req = new DataRequestor();
    req.addArg(_GET, "mdl", moduleID);
    req.addArg(_GET, "smd", submoduleID);
    req.addArg(_GET, "rid", recordID);
    req.addArg(_GET, "grw", rowID);
    req.addArg(_POST, "save", 'save');

    tinyMCE.triggerSave();

    if('0' == rowID.toString()){
        dbAction = 'add';  //i.e. definitely insert
    } else {
        dbAction = 'save'; //i.e. update/insert as needed
    }
    req.addArg(_GET, "action", dbAction);

    //loop through form fields & add them
    fe = document.forms[submoduleID].elements;
    for(elemID in fe){
        elem = fe[elemID];
        if(elem && elem.type != 'button'){
            if(elem.type == 'radio'){
                if(elem.checked){
                    req.addArg(_POST, elem.name, elem.value);
                }
            } else {
                req.addArg(_POST, elem.name, elem.value);
            }
        }
    }
    req.getURL('rpc/EditGridRPC.php');
    if(dbAction == 'add'){
        req.onload = callbackAddRecord;
    } else {
        req.onload = callbackSaveRecord;
    }
}

function editGridDeleteRow(moduleID, submoduleID, rowID){
    if(confirm('Rzeczywiście usunąć?')){
        l = document.getElementById('loc'+rowID.toString());
        oRow = l.parentNode;
        editRowID = rowID;
        var req = new DataRequestor();
        req.addArg(_GET, 'mdl', moduleID);    
        req.addArg(_GET, 'smd', submoduleID);
        req.addArg(_GET, 'rid', recordID);
        req.addArg(_GET, 'grw', rowID);
        req.addArg(_GET, 'action', 'delete');
        req.addArg(_POST, 'delete', 'delete');
        req.getURL('rpc/EditGridRPC.php');
        req.onload = callbackDeleteRecord;
    } else {
        cancelEditRow(rowID);
    }
}

function callbackSaveRecord(data, obj)
{
    if(sessionTimeout(data)){
        return false;
    }

    resp = eval('('+data+')');
    if(resp['error']){
        alert(resp['error']);
        return;
    }
    rowID = resp['rowID'];
    l = document.getElementById('loc'+rowID.toString());
    oRow = l.parentNode;
    cells = resp['cells'];
    for(cellID in cells){
        oCell = oRow.cells[cellID];
        oCell.innerHTML = cells[cellID].innerHTML;
    }
    cancelEditRow(rowID);
}

function callbackAddRecord(data, obj)
{
    if(sessionTimeout(data)){
        return false;
    }

    resp = eval('('+data+')');
    if(resp['error']){
        alert(resp['error']);
        return;
    }
    l = document.getElementById('loc0');
    oHeaderRow = l.parentNode;
    oTBody = oHeaderRow.parentNode;

    oTopRow = oTBody.rows[0];
    if(oTopRow.cells.length > 1){
        insertLoc = 1;
    } else {
        insertLoc = 2;
    }

    oRow = oTBody.insertRow(insertLoc); //after header
    cells = resp['cells'];
    for(cellID in cells){

        oCell = oRow.insertCell(cellID);
        oCell.innerHTML = cells[cellID].innerHTML;
        if(cells[cellID]['id']){
            oCell.id = 'loc'+cells[cellID]['id'];
            oRow.id = 'row'+cells[cellID]['id'];
        }
        oCell.className = rowClasses[useRowClassNum];
    }
    if(useRowClassNum == 0){
        useRowClassNum = 1;
    } else {
        useRowClassNum = 0;
    }
    cancelEditRow('');
}

function callbackDeleteRecord(data, obj)
{
    if(sessionTimeout(data)){
        return false;
    }
    resp = eval('('+data+')');
    if(resp['error'].toString()){
        alert(resp['error'].toString());
        return;
    }
    rowID = resp['rowID'];
    l = document.getElementById('loc'+rowID.toString());
    oRow = l.parentNode;
    cells = resp['cells'];
    if(cells && cells.length > 0){
        for(cellID in cells){
            oCell = oRow.cells[cellID];
            oCell.innerHTML = cells[cellID].innerHTML;
        }
        cancelEditRow(rowID);
    } else {
        oRow.parentNode.removeChild(oRow);
        cancelEditRow('');
    }
}


function updateList(listDiv, callUrl){
    var req = new DataRequestor();

    if (d = document.getElementById(listDiv)) {
		d.style.cursor = 'wait';

		req.getURL(callUrl);
			req.onload = function(indata, obj) {

			if(sessionTimeout(indata)){
				return false;
			}
			d.innerHTML = indata;
			d.style.cursor = 'default';
		};
	};
}


function dismissIntro(moduleID, all)
{
    if(all){
        alert('Rezygnuję z Wprowadzenia we wszystkich modułach.');
        noIntro = 'allModules';
    } else {
        alert('Rezygnuję z Wprowadzenia w tym module.');
        noIntro = moduleID;
    }
    document.location = 'list.php?noIntro='+noIntro+'&mdl='+moduleID;
}


function setupFormEffects(oEvent)
{
    if(document.forms.length > 0){
        nForms = document.forms.length;
        for(i = 0; i < nForms; i++){
            oForm = document.forms[i];
            if(oForm.elements.length > 0){
                //for(elementID in oForm.elements){
                nElements = oForm.elements.length;
                for(elementID = 0; elementID < nElements; elementID++){
                    oElement = oForm.elements[elementID];
                    if(oElement != undefined && typeof(oElement) == 'object'){
                    //alert('elementID '+ elementID);
                        if(YAHOO.util.Dom.hasClass(oElement, 'edt')){
                            if(oElement.tagName == 'INPUT' && oElement.type == 'radio'){
                                //alert(oElement.type);
                                if(oElement.checked){
                                    oElement.origValue = oElement.value;
                                } else {
                                    oElement.origValue = 'unchecked';
                                }
                                oElement.origChecked = oElement.checked;
                                //YAHOO.util.Event.addListener(oElement, "click", indicateUnsavedRadioChanges);
                                YAHOO.util.Event.addListener(oElement, "change", indicateUnsavedRadioChanges);
                            } else {
                                //alert(oElement.name.indexOf('_org'));
                                if(oElement.name.indexOf('_org') == -1){
                                    oElement.origValue = oElement.value;
                                    YAHOO.util.Event.addListener(oElement, "change", indicateUnsavedChanges);
                                }
                            }
                        }
                        YAHOO.util.Event.addListener(oElement, "focus", fieldFocus);
                        YAHOO.util.Event.addListener(oElement, "blur", fieldBlur);
                    }
                }
            }
        }
    }
}

function indicateUnsavedChanges(oEvent, oElement)
{
    if(!oElement){
        oElement = YAHOO.util.Event.getTarget(oEvent);
    }
    arElements = getFormRowArray(oElement);
    if(oElement.origValue != oElement.value){
        YAHOO.util.Dom.addClass(arElements, 'unsaved');
    } else {
        YAHOO.util.Dom.removeClass(arElements, 'unsaved');
    }
}

function indicateUnsavedTMCEChanges(oMCE)
{
    if(oMCE.isDirty){
        oElement = document.getElementById(oMCE.formTargetElementId);
        oElement.value = 'changed';
        indicateUnsavedChanges('', oElement);
    }
}

function indicateUnsavedDateChanges(oCalendar)
{
    oElement = oCalendar.params.inputField;
    indicateUnsavedChanges('', oElement);
}

function indicateUnsavedRadioChanges(oEvent)
{
    oElement = YAHOO.util.Event.getTarget(oEvent);
    arElements = getFormRowArray(oElement);
    //alert(oElement.id + ' ' + oElement.origValue + ' ' + oElement.value);
    if(oElement.checked){
        if(!oElement.origChecked){
            YAHOO.util.Dom.addClass(arElements, 'unsaved');
        } else {
            YAHOO.util.Dom.removeClass(arElements, 'unsaved');
        }
    } else {
        //alert('not same field');
        if(oElement.origValue != oElement.value){
            YAHOO.util.Dom.addClass(arElements, 'unsaved');
        } else {
            YAHOO.util.Dom.removeClass(arElements, 'unsaved');
        }
    }
}


function getFormRowArray(oElement)
{
    oElementRow = oElement.parentNode.parentNode;
    nElements = oElementRow.childNodes.length;
    arElements = new Array();
    for(elementID = 0; elementID < nElements; elementID++){
        node = oElementRow.childNodes[elementID];
        if(node.tagName == 'TD'){
            arElements.push(node);
        }
    }
    return arElements;
}


function fieldFocus(oEvent)
{
    oElement = YAHOO.util.Event.getTarget(oEvent);
    YAHOO.util.Dom.addClass(oElement, 'focus');
}

function fieldBlur(oEvent)
{
    oElement = YAHOO.util.Event.getTarget(oEvent);
    YAHOO.util.Dom.removeClass(oElement, 'focus');
}


function showRelationsPopover(moduleID, callerObj)
{
    //still using DataRequiestor when we could use yahoo connection
    var req = new DataRequestor();
    req.addArg(_GET, "mdl", moduleID);

    req.getURL('rpc/related.php');
    req.onload = function(indata, obj)
    {
        if(sessionTimeout(indata)){
            return false;
        }
        YAHOO.activeagenda.poprel.setBody(indata);
        YAHOO.activeagenda.poprel.show();
    }

    if(callerObj){
        callerObj.blur();
    }
}

function showTitlePopover(popoverID, callerObj){
    offsetX = 15;
    popoverWidth = 400;
    fudge = 50;
    popoverObj = document.getElementById(popoverID);
    popoverObj.callerObj = callerObj;
    popoverObj.style.top = (callerObj.offsetHeight + findPosY(callerObj))+'px';
    posX = offsetX + findPosX(callerObj);

    if(window.innerWidth < posX + popoverWidth + fudge){
        posX = window.innerWidth - popoverWidth - fudge;
    }
    popoverObj.style.left = (posX)+'px';
    popoverObj.style.display = 'block';
}

function hideTitlePopover(popoverID){
    popoverObj = document.getElementById(popoverID);
    popoverObj.style.display = 'none';
}


function toggleRelPop(moduleID, callerObj)
{
// this does not work in IE (same name between element and local variable)
//    popover = window.document.getElementById('popover');

    popoverObj = document.getElementById('pop_relations');
//alert(popoverObj);
    if(popoverObj.style.display == 'block'){
        popoverObj.style.display = 'none';
    } else {

        popoverContentObj = document.getElementById('pop_relations_content');
        popoverContentObj.innerHTML="ładowanie...";

        showTitlePopover('pop_relations', callerObj);

        var req = new DataRequestor();
        req.addArg(_GET, "mdl", moduleID);

        req.getURL('rpc/related.php');
        req.onload = function (indata, obj){

            if(sessionTimeout(indata)){
                return false;
            }

            popoverContentObj.innerHTML = indata;
        }
    }
    if(callerObj){
        callerObj.blur();
    }
}

function toggleRelSection(sectionID, callerObj, showMsg, hideMsg)
{
    sectionObj = document.getElementById(sectionID);
    if(sectionObj.style.display == 'block'){
        sectionObj.style.display = 'none';
        callerObj.innerHTML = showMsg;
    } else {
        sectionObj.style.display = 'block';
        callerObj.innerHTML = hideMsg;
    }
}


/*
 Throttle by Nicholas Zakas to work around MSIE's resize nasties.
 http://www.nczonline.net/blog/2007/11/30/the_throttle_function
 */
function throttle(method, scope) {
    clearTimeout(method._tId);
    method._tId= setTimeout(function(){
        method.call(scope);
    }, 300);
}


/* IE formatting*/
function fixListWrap(obj){
    oSideArea = document.getElementById('sidearea');

    offsetY = oSideArea.offsetHeight + oSideArea.offsetTop - 0;
    offsetX = oSideArea.offsetLeft + oSideArea.offsetParent.offsetLeft;
    if(offsetY >= obj.offsetTop){
        newWidth = offsetX - 28; //ie 7 can go down to 23 px
        return newWidth + 'px';
    } else {
          return (offsetX + 150) + 'px';
    }
}



function layoutFixIE(){
    oSideArea = document.getElementById('sidearea');
    oSideArea.style.left = '0px';
    oSideShim = document.getElementById('sideshim');
    oSideShim.style.right = '20px';

    oContent = document.getElementById('content');
    if(!oContent){
        oContent = document.getElementById('content_notitle');
        strContent = 'content_notitle';
    } else {
        strContent = 'content';
    }

    /*alert(navigator.appVersion);*/
    objs = YAHOO.util.Dom.getElementsByClassName('sz2tabs', 'div', strContent);
    for (objIx in objs){
        obj = objs[objIx];
        obj.style.width = fixListWrap(obj);
    }

    //stretches content area to cover all tabs
    oSideArea = document.getElementById('sidearea');
    neededHeight = oSideArea.offsetHeight + 20;
    if(neededHeight > oContent.offsetHeight){
        oContent.style.height = neededHeight;
    }
}

function layoutFix(){
    oSideArea = document.getElementById('sidearea');
    offsetY = oSideArea.offsetHeight + oSideArea.offsetTop;

    oContent = document.getElementById('content');
    if(!oContent){
        oContent = document.getElementById('content_notitle');
        strContent = 'content_notitle';
    } else {
        strContent = 'content';
    }

    objs = YAHOO.util.Dom.getElementsByClassName('sz2tabs', 'div', strContent);
    overlap = 0;
    for(objIx in objs){
        obj = objs[objIx];
        largestWidth = 0;
        if(offsetY > obj.offsetTop){
            for(child_objIx in obj.childNodes){
                currentWidth = obj.childNodes[child_objIx].offsetWidth;
                if(currentWidth > largestWidth){
                    largestWidth = currentWidth;
                }
            }
            currentOverlap = largestWidth + obj.offsetLeft - oSideArea.offsetLeft;
            if(currentOverlap > overlap){
                overlap = currentOverlap;
            }
        }
    }
    if(overlap > 0){
        oContent.style.width = oContent.offsetWidth + overlap + 'px';
    }
    if(oContent.offsetWidth < (document.body.clientWidth - 15)){
        //oContent.style.width = 'auto'; //seems to not cause a redraw?
        oContent.style.width = (document.body.clientWidth - 15) + 'px';
    }
    if(oContent.offsetWidth > document.body.clientWidth){
        //oContent.style.width = 'auto';
        //shrink down sz2tabs objects?
    }
}


function setRadioValue(oRadioBtn, sFieldID)
{
    //alert(oRadioBtn.checked);
    //alert(sFieldID);
    oField = document.getElementById(sFieldID);
    if(oRadioBtn.checked){
        oField.value = oRadioBtn.value;
        indicateUnsavedChanges(null, oField);
    }
}

function setupFormTooltips(containerID){
    contextElements = YAHOO.util.Dom.getElementsByClassName('flbl', 'td', 'content');
    YAHOO.activeagenda.formtooltip = new YAHOO.widget.Tooltip("ttf", { context:contextElements, width:320 , autodismissdelay:20000} );
}


function sessionTimeout(indata){
    if('session timeout' == indata){
        if(confirm("Na skutek wygaśnięcia sesji dane nie mogą być pobrane z serwera. Kliknij OK w celu ponownego zalogowania się.")){
            location.reload();
        }
        return true;
    }
    return false;
}
