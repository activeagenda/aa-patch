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

function editRow( rowID, gridModuleID ){

	eo = document.getElementById( 'editOverlay' );
	if( eo.style.display == 'block' ){
		return;
	}
    recordID = editGrids[gridModuleID]['recordID'];
	moduleID = editGrids[gridModuleID]['moduleID'];
	submoduleID = gridModuleID;
	useRowClassNum = editGrids[gridModuleID]['useRowClassNum'];
	rowClasses = editGrids[gridModuleID]['rowClasses'];	
	
	if( editRowID == rowID ){
        return cancelEditRow(rowID);
    }
    if( editRowID != '' ){
        cancelEditRow(editRowID);
    }
    editRowID = rowID;
	
    d = document.getElementById( 'gridFormDiv_'+submoduleID );	
	
    l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
    oRow = l.parentNode;
    bkpRowClass = l.className;
    for( t in oRow.childNodes ) {
        oTD = oRow.childNodes[t];
        if( oTD.tagName == 'TD' ){
            oTD.className = 'l3';
        }
    }
    for( t in l.childNodes ){
        oA = l.childNodes[t];
        if( oA.tagName == 'A' ){
            //alert(oA);
            oA.blur();
        }
    }
    editGridGetForm( d, moduleID, submoduleID, rowID );
    d.style.top = ( findPosY(l) )+'px';
	d.style.left = ( findPosX(l) )+'px'; 
	
	d.innerHTML = '<img src="themes/aa_theme/img/progress.gif" width="32" height="32">';
    d.style.display = 'block';
	eo.style.display = 'block';
	
}


function cancelEditRow(rowID){
    editRowID = '';
    d = document.getElementById('gridFormDiv_'+submoduleID);
	eo = document.getElementById('editOverlay');
    if( rowID ){
        l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
        oRow = l.parentNode;
        for( t in oRow.childNodes ) {
            oTD = oRow.childNodes[t];
            if( oTD.tagName == 'TD' ){
                oTD.className = bkpRowClass;
            }
        }

        for( t in l.childNodes ){
            oA = l.childNodes[t];
            if( oA.tagName == 'A' ){
                oA.blur();
            }
        }
    }
    bkpRowClass = '';

    d.style.display = 'none';
	eo.style.display = 'none';

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

        if( sessionTimeout(indata) ){
            return false;
        }

        resp = eval('('+indata+')');
        if( resp["error"] ){
            alert(resp["error"]);
        }
        cont = resp['content']; //variable name "content" seems to be reserved in IE???
        d.innerHTML = cont;

        //getting TinyMCE to show in the popup forms
        arTextAreas = d.getElementsByTagName('textarea');
        for( i = 0; i < arTextAreas.length; i++ ){
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
    d = document.getElementById('gridFormDiv_'+submoduleID);
    l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
    oRow = l.parentNode;
    editRowID = rowID;

    var req = new DataRequestor();
    req.addArg(_GET, "mdl", moduleID);
    req.addArg(_GET, "smd", submoduleID);
    req.addArg(_GET, "rid", recordID);
    req.addArg(_GET, "grw", rowID);
    req.addArg(_POST, "save", 'save');

    tinyMCE.triggerSave();

    if( '0' == rowID.toString() ){
        dbAction = 'add';  //i.e. definitely insert
    }else{
        dbAction = 'save'; //i.e. update/insert as needed
    }
    req.addArg(_GET, "action", dbAction);

    //loop through form fields & add them
    fe = document.forms[submoduleID].elements;
    for( elemID in fe ){
        elem = fe[elemID];
        if( elem && elem.type != 'button' ){
            if( elem.type == 'radio' ){
                if( elem.checked ){
                    req.addArg(_POST, elem.name, elem.value);
                }
            }else{
                req.addArg(_POST, elem.name, elem.value);
            }
        }
    }
    req.getURL('rpc/EditGridRPC.php');
    if( dbAction == 'add' ){
        req.onload = callbackAddRecord;
    }else{
        req.onload = callbackSaveRecord;
    }
}

function editGridDeleteRow(moduleID, submoduleID, rowID){
    if( confirm('Czy chesz usunąć ten rekord?') ){
        l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
        oRow = l.parentNode;
        editRowID = rowID;
        var req = new DataRequestor();
        req.addArg(_GET, 'mdl', moduleID);    
        req.addArg(_GET, 'smd', submoduleID);
        req.addArg(_GET, 'rid', recordID);
        req.addArg(_GET, 'grw', rowID);
        req.addArg(_GET, 'action', 'delete');
        req.addArg(_POST, 'delete', 'delete');
		
		//loop through form fields & add them
		fe = document.forms[submoduleID].elements;
		for( elemID in fe ){
			elem = fe[elemID];
			if( elem && elem.type != 'button' ){
				if( elem.name == '_OwnedBy'){
					req.addArg(_POST, elem.name, elem.value);
				}
			}
		}
		
        req.getURL('rpc/EditGridRPC.php');
        req.onload = callbackDeleteRecord;
    }else{
        cancelEditRow(rowID);
    }
}

function callbackSaveRecord(data, obj)
{
    if( sessionTimeout(data) ){
        return false;
    }

    resp = eval('('+data+')');
    if( resp['error'] ){
        alert(resp['error']);
        return;
    }
    rowID = resp['rowID'];
    l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
    oRow = l.parentNode;
    cells = resp['cells'];
    for( cellID in cells ){
        oCell = oRow.cells[cellID];
        oCell.innerHTML = cells[cellID].innerHTML;
    }
    cancelEditRow(rowID);
}

function callbackAddRecord(data, obj)
{
    if( sessionTimeout(data) ){
        return false;
    }

    resp = eval('('+data+')');
    if( resp['error'] ){
        alert(resp['error']);
        return;
    }
    l = document.getElementById( submoduleID+'_loc0' );
    oHeaderRow = l.parentNode;
    oTBody = oHeaderRow.parentNode;

    oTopRow = oTBody.rows[0];
    if( oTopRow.cells.length > 1 ){
        insertLoc = 1;
    }else{
        insertLoc = 2;
    }

    oRow = oTBody.insertRow(insertLoc); //after header
	oRow.className='lrw';

    cells = resp['cells'];
    for( cellID in cells ){

        oCell = oRow.insertCell(cellID);
        oCell.innerHTML = cells[cellID].innerHTML;
        if( cells[cellID]['id'] ){
            oCell.id = submoduleID+'_loc'+cells[cellID]['id'];
            oRow.id = 'row'+cells[cellID]['id'];
        }
        oCell.className = rowClasses[useRowClassNum];
    }
    if( useRowClassNum == 0 ){
        useRowClassNum = 1;
    }else{
        useRowClassNum = 0;
    }
    cancelEditRow('');
}

function callbackDeleteRecord(data, obj)
{
    if( sessionTimeout(data) ){
        return false;
    }
    resp = eval('('+data+')');
    if( resp['error'].toString() ){
        alert(resp['error'].toString());
        return;
    }
    rowID = resp['rowID'];
    l = document.getElementById( submoduleID+'_loc'+rowID.toString() );
    oRow = l.parentNode;
    cells = resp['cells'];
    if( cells && cells.length > 0 ){
        for( cellID in cells ){
            oCell = oRow.cells[cellID];
            oCell.innerHTML = cells[cellID].innerHTML;
        }
        cancelEditRow(rowID);
    }else{
        oRow.parentNode.removeChild(oRow);
        cancelEditRow('');
    }
}


function updateList(listDiv, callUrl){
    var req = new DataRequestor();

    if ( d = document.getElementById(listDiv) ) {
		d.style.cursor = 'wait';

		req.getURL(callUrl);
			req.onload = function(indata, obj) {

			if( sessionTimeout(indata) ){
				return false;
			}
			d.innerHTML = indata;
			d.style.cursor = 'default';
		};
	};
}

function setupFormEffects(oEvent)
{
    if( document.forms.length > 0 ){
        nForms = document.forms.length;
        for(i = 0; i < nForms; i++){
            oForm = document.forms[i];
            if( oForm.elements.length > 0 ){
                //for(elementID in oForm.elements){
                nElements = oForm.elements.length;
                for( elementID = 0; elementID < nElements; elementID++ ){
                    oElement = oForm.elements[elementID];
                    if( oElement != undefined && typeof(oElement) == 'object' ){
                    //alert('elementID '+ elementID);
                        if( YAHOO.util.Dom.hasClass(oElement, 'edt') ){
                            if( oElement.tagName == 'INPUT' && oElement.type == 'radio' ){
                                //alert(oElement.type);
                                if( oElement.checked ){
                                    oElement.origValue = oElement.value;
                                }else{
                                    oElement.origValue = 'unchecked';
                                }
                                oElement.origChecked = oElement.checked;
                                //YAHOO.util.Event.addListener(oElement, "click", indicateUnsavedRadioChanges);
                                YAHOO.util.Event.addListener(oElement, "change", indicateUnsavedRadioChanges);
                            }else{
                                //alert(oElement.name.indexOf('_org'));
                                if( oElement.name.indexOf('_org') == -1 ){
                                    oElement.origValue = oElement.value;
                                    YAHOO.util.Event.addListener(oElement, "change", indicateUnsavedChanges);
                                }
                            }
                        }
                        YAHOO.util.Event.addListener( oElement, "focus", fieldFocus );
                        YAHOO.util.Event.addListener( oElement, "blur", fieldBlur );
                    }
                }
            }
        }
    }
}

function indicateUnsavedChanges(oEvent, oElement)
{
    if( !oElement ){
        oElement = YAHOO.util.Event.getTarget(oEvent);
    }
    arElements = getFormRowArray(oElement);
    if( oElement.origValue != oElement.value ){
        YAHOO.util.Dom.addClass(arElements, 'unsaved');
    }else{
        YAHOO.util.Dom.removeClass(arElements, 'unsaved');
    }
}

function indicateUnsavedTMCEChanges(oMCE)
{
    if( oMCE.isDirty ){
        oElement = document.getElementById(oMCE.editorId);
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
    if( oElement.checked ){
        if( !oElement.origChecked ){
            YAHOO.util.Dom.addClass(arElements, 'unsaved');
        }else{
            YAHOO.util.Dom.removeClass(arElements, 'unsaved');
        }
    }else{
        //alert('not same field');
        if( oElement.origValue != oElement.value ){
            YAHOO.util.Dom.addClass(arElements, 'unsaved');
        }else{
            YAHOO.util.Dom.removeClass(arElements, 'unsaved');
        }
    }
}


function getFormRowArray(oElement)
{
    oElementRow = oElement.parentNode.parentNode;
    nElements = oElementRow.childNodes.length;
    arElements = new Array();
    for( elementID = 0; elementID < nElements; elementID++ ){
        node = oElementRow.childNodes[elementID];
        if( node.tagName == 'TD' ){
            arElements.push(node);
        }
    }
    return arElements;
}


function fieldFocus(oEvent)
{
    oElement = YAHOO.util.Event.getTarget(oEvent);
    YAHOO.util.Dom.addClass( oElement, 'focus' );
}

function fieldBlur(oEvent)
{
    oElement = YAHOO.util.Event.getTarget(oEvent);
    YAHOO.util.Dom.removeClass( oElement, 'focus' );
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
    contextElements1 = YAHOO.util.Dom.getElementsByClassName('flbl',  'td', 'content_record');
    YAHOO.activeagenda.formtooltip = new YAHOO.widget.Tooltip("ttf", { context:contextElements1, width:250 , autodismissdelay:20000, showDelay:500} );
	
	contextElements2 = YAHOO.util.Dom.getElementsByClassName('flbl',  'td', 'content');
    YAHOO.activeagenda.formtooltip = new YAHOO.widget.Tooltip("ttf", { context:contextElements2, width:250 , autodismissdelay:20000, showDelay:500} );
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

function confirmAndDelete(destination){    
        if(confirm("Czy chcesz usunąć ten rekord?")){
            location.href = destination;
        }
}

function GetZoomFactor () {
            var factor = 1;
            if (document.body.getBoundingClientRect) {
                    // rect is only in physical pixel size in IE before version 8 
                var rect = document.body.getBoundingClientRect ();
                var physicalW = rect.right - rect.left;
                var logicalW = document.body.offsetWidth;
                    // the zoom level is always an integer percent value
                factor = Math.round ((physicalW / logicalW) * 100) / 100;
            }
            return factor;
        }

function GetScrollTop () {
	if ('pageYOffset' in window) {  // all browsers, except IE before version 9		/
		var scrollTop = window.pageYOffset;
	}
	else {      // Internet Explorer before version 9
		var zoomFactor = GetZoomFactor ();
		var scrollTop = Math.round (document.documentElement.scrollTop / zoomFactor);
	}
	return scrollTop;
}


function submitenter(myfield,e)
{
	var keycode;
	if (window.event) keycode = window.event.keyCode;
	else if (e) keycode = e.which;
	else return true;

	if (keycode == 13)
	   {		
		elem = document.mainForm.elements['Search'];
		if( elem && elem.type == 'submit' ){
			fireEvent("Search",'click');
		}else{
			fireEvent("Save",'click');
		}		
		return false;
	}
	else
	   return true;
}

function fireEvent(objID,event)
{
	element = document.getElementById(objID);
    if (document.createEventObject){
    // dispatch for IE
    var evt = document.createEventObject();
    return element.fireEvent('on'+event,evt)
    }
    else{
    // dispatch for firefox + others
    var evt = document.createEvent("HTMLEvents");
    evt.initEvent(event, true, true ); // event type,bubbling,cancelable
    return !element.dispatchEvent(evt);
    }
}

function setFocusOnFirstInput()
{
	if( document.forms.mainForm != null ){
		if( document.forms.mainForm.elements[0]!= null ){
			try{ document.forms.mainForm.elements[0].focus(); }catch(e){}
		}
	}
}

function setFocusOnFirstInputInForm( inputformname )
{
	if( navigator.appName == 'Microsoft Internet Explorer' ){		
			setTimeout(function() {
				try{ document.forms[inputformname].elements[0].focus(); }catch(e){}
			}, 300);		
	}else{
		document.forms[inputformname].elements[0].focus(); 
	}
}

function submitmainForm()
{
	fe = document.mainForm.elements;
	for( elemI = 0; elemI < fe.length; elemI++ ){
		elem = fe[elemI];
		if( elem && elem.type == 'submit' ){
			if( elem.name == 'Save'){
				var hiddenField = document.createElement("input"); 
				hiddenField.setAttribute("type", "hidden");
				hiddenField.setAttribute("name", "Save");
				hiddenField.setAttribute("value", "Save");
				document.mainForm.appendChild( hiddenField );	
				document.mainForm.submit();				
			}
			if( elem.name == 'Search'){
				var hiddenField = document.createElement("input"); 
				hiddenField.setAttribute("type", "hidden");
				hiddenField.setAttribute("name", "Search");
				hiddenField.setAttribute("value", "Search");
				document.mainForm.appendChild( hiddenField );	
				document.mainForm.submit();
			}
		}
		// Hack as IE8 don't know what to to if no selected="selected" (bug) and speed
		if( elem.options ){			
			if( typeof elem.selectedIndex == 'undefined' ){
				elem.selectedIndex = 0;
				elem.value = 0;				
			}
		} 
		
	}
	
	return true;
}

