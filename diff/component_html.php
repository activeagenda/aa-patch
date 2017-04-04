<?php
/**
 * HTML snippets for various renderable components
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

if(!defined('EXEC_STATE') || EXEC_STATE != 1){
    print gettext("This file should not be accessed directly.");
    trigger_error("This file should not be accessed directly.", E_USER_ERROR);
    exit;
}

global $theme_web;

//parameter1 = page to post to, commonly PHP_SELF, p2 = encryption type, p3=content
//param3 = content
//param4 = module ID of form (main module ID or grid ModuleID)
define('FORM_HTML',
'   <script type="text/javascript" src="js/CBs.js"></script>
    <script type="text/javascript" src="3rdparty/filtery.js"></script>
    <script type="text/javascript" src="3rdparty/DataRequestor.js"></script>
    <script type="text/javascript">
        var form_moduleID = "%4$s";
    </script>
    <form action="%1$s" name="mainForm" method="post" %2$s>
    <div class="sz2tabs">
    <table class="frm">
        %3$s
    </table>
    </div>
    </form>');

define('VIEWTABLE_HTML',
'   <div class="sz2tabs">
    <table class="frm">
        %s
    </table>
    </div>');

define('VIEWTABLE_EDITNAV_HTML',
'   <tr>
        <td class="flbb" colspan="2" align="right">
            <input class="btn" type="button" name="Edit" value="%s" onclick="%s"/>
            <input class="btn" type="button" name="Back" value="%s" onclick="%s"/>
        </td>
    </tr>');
define('VIEWTABLE_VIEWNAV_HTML',
'   <tr>
        <td class="flbb" colspan="2" align="right">
            <input class="btn" type="button" name="Back" value="%s" onclick="%s"/>
        </td>
    </tr>');
define('VIEWTABLE_POPUPNAV_HTML',
'   <tr>
        <td class="flbb" colspan="2" align="right">
            <input class="btn" type="button" name="Close" value="%s" onclick="opener.focus();self.close();"/>
        </td>
    </tr>');

//long phrase, short phrase, field content, class, fieldID
define('FIELD_HTML', '
    <tr>
        <td class="%4$s %6$s" title="%2$s: %1$s">
            <div id="%5$s">%2$s:</div>
        </td>
        <td class="fval %6$s">
            %3$s
        </td>
    </tr>');


//name, value, size, maxlength, align, additionalClass
define('FORM_EDIT_HTML', '<input class="edt %6$s" type="text" name="%1$s" id="id%1$s" value="%2$s" size="%3$s" maxlength="%4$s" align="%5$s"/>');

define('FORM_PWD_HTML', '<input class="edt" type="password" name="%s" id="id%1$s" value="%s" size="%s" maxlength="%s" align="%s"/>');

define('FORM_FILE_HTML', '
    <input type="hidden" name="MAX_FILE_SIZE" id="h%2$s" value="%1$s" />
    <input class="edt" type="file" name="%2$s" id="id%2$s" value="%3$s" size="%4$s" maxlength="%5$s"/>');
//name, value, size, maxlength, align
define('FORM_DATE_HTML', '<input class="edt" type="text" id="%1$s" name="%1$s" value="%2$s" size="%3$s" maxlength="%4$s" align="%5$s"/>
<img src="'.$theme_web.'/img/calendar.gif" id="cal_%1$s" style="cursor: pointer;" title="'.gettext("Date selector").'" height="16" width="16" />');

define('FORM_TIME_HTML', '<input class="edt" type="text" name="%1$s" id="id%1$s" value="%2$s" size="12" maxlength="10" align="%3$s"/>');
define('FORM_MEMO_HTML', '<textarea class="edt" name="%1$s" id="%1$s" rows="%2$s" cols="%3$s">%4$s</textarea>');

//FieldName, Items, JavaScript, FindMode, addNewLink, additionalClass
define('FORM_DROPLIST_HTML', '%4$s <select class="edt %6$s" name="%1$s" id="id%1$s" onchange="%3$s">%2$s</select> %5$s');

//FormName, FieldName, CurrentIDValue (at page load), ChildNames, JavaScript Array definition, ParentName, ???, FindMode, addNewLink, additionalClass
define('FORM_FILTER_CB_HTML', '<script type="text/javascript">
<!-- 
    //[js:]
    ar%2$s = new Array();  //ID, Name, ParentID
    %5$s
    //[:js]
-->
    </script>
    %8$s
    <select class="edt %10$s" name="%2$s" id="id%2$s" onchange="%7$s"><option/></select>
    <script type="text/javascript">
    <!--
    //[js:]
        //populate this combo box
        //ReloadChildCB(sFormName, sParentName, sChildName, arChildItems, selChildID)
        ReloadChildCB(\'%1$s\', \'%6$s\', \'%2$s\', ar%2$s, \'%3$s\')
    //[:js]
    -->
    </script>%9$s');

//FormName, PersonFieldName, PersonIDValue, JavaScript Array definition, FindMode(ppl), FindMode(org), addNewLink
define('FORM_PERSON_CB_HTML', '
    <script type="text/javascript">
    <!--
    //[js:]
        ar%2$sPeople = new Array();//PersonID, DisplayName, OrganizationID
        ar%2$sOrgs = new Array();  //OrganizationID, Name
        %4$s
    //[:js]
    -->
    </script>
    %6$s <select class="edt" id="%2$s_org" name="%2$s_org" onchange="ReloadPeople(\'%1$s\', \'%2$s\', this.value, ar%2$sPeople)"></select><br />
    %5$s <select class="edt" id="%2$s" name="%2$s"></select>
    <script type="text/javascript">
    <!--
    //[js:]
        //populate these combo boxes
        PopulatePeopleCBs(\'%1$s\', \'%2$s_org\', \'%2$s\', \'%3$s\', ar%2$sPeople, ar%2$sOrgs);
    //[:js]
    -->
    </script>%7$s');

//parameter: field name of select list to filter
define('FORM_FINDMODE_TEXT_HTML', '
    <input class="edta" type="text" name="fm_%1$s" id="fm_%1$s" value="" size="5" title="'.gettext("Type a few characters to select an item in the list to the right").'." onkeyup="filteryText(event,this.value,this.form.%1$s)" />
');


    
//special: does not use FIELD_HTML
//parameters: long phrase, content
define('FIELD_HTML_CHECKBOX', '
    <tr>
        <td class="flbl" title="%1$s">
        </td>
        <td class="fval" title="%1$s">
            %2$s
        </td>
    </tr>');

//parameters: field name, field ID, short phrase, checked ("checked" or ""), value
define('FORM_CHECKBOX', '<input class="edt" type="checkbox" id="chk_%2$s" name="%1$s" value="%5$s" %4$s />
            <label for="chk_%2$s">%3$s</label>');

//parameters: 1. field name, 2. value name, 3. checked, 4. value id
define('FORM_RADIOBUTTON', '<input type="radio" id="%1$s_%4$s" name="%1$s" value="%4$s" %3$s/>
            <label for="%1$s_%4$s">%2$s</label>');
//parameter: content
define('FORM_BUTTONROW_HTML',
'   <tr>
        <td class="flbb" colspan="2">
            %s
        </td>
    </tr>');

//parameter: content
define('FORM_CONSISTENCYROW_HTML',
'   <tr>
        <td class="ccMsg" colspan="2">
            %s
        </td>
    </tr>');

//parameters: name, value, action
define('FORM_BUTTON_HTML',
'<input class="btn" type="button" name="%1$s" id="%1$s" value="%2$s" onclick="%3$s"/>'
);
//parameters: name, value, other parameter (e.g. for onclick Delete confirmation)
define('FORM_SUBMIT_HTML',
'<input class="btn" type="submit" name="%1$s" id="%1$s" value="%2$s" %3$s/>'
);





/**********************/
/*  Grid-type strings */
/**********************/


// main table string

//parameters: grid title, table content
define('VIEWGRID_MAIN', '
<div class="sz2tabs">
<div class="grid">
    <div class="subfolder_span">
    <div class="grid_inner">
        %1$s
        <div class="subfolder_main">
            <div class="subfolder_inner">
            %2$s
            </div>
            <div class="subfolder_spacer">&nbsp;</div>
        </div>
    </div>
    </div>
</div>
</div>');
define('VIEWGRID_MAIN_EMAIL', '
<div class="aa_grid">
<div class="aa_gridtitle">
    %1$s
</div>
    %2$s
</div>');
define('VIEWGRID_MAIN_VERTICAL', '
<div class="vgrid">
    %s
</div>');


//parameters: phrase (grid title), openSameLink, open NewLink, count
define('VIEWGRID_TITLE',
'<div class="gridtitle">
    <a href="#" title="'.gettext("Open Module in new window").'" onclick="window.open(\'%3$s\', \'\', \'toolbar=0,resizable=1\')">
        <img src="'.$theme_web.'/img/open_new_window.gif" height="16" width="16" alt="'.gettext("new window").'" /></a>&nbsp;&nbsp;<a href="%2$s" title="'.gettext("Open Module in same window").'">
        %1$s
    </a>
    %4$s
</div>');

define('GRID_TAB', '
<div class="subfolder_tab_l">
    <div class="subfolder_tab_r">
        <div class="subfolder_tab_m">
            <div class="subfolder_label">
                <a href="#" title="'.gettext("Open Module in new window").'" onclick="window.open(\'%3$s\', \'\', \'toolbar=0,resizable=1\')">
                <img src="'.$theme_web.'/img/open_new_window.gif" height="16" width="16" alt="'.gettext("new window").'" /></a>&nbsp;&nbsp;<a href="%2$s" title="'.gettext("Open Module in same window").'">
                %1$s
                </a>
                <span class="subfolder_count">%4$s</span>
            </div>
        </div>
    </div>
</div>');


define('GRID_TAB_NOLINKS', '
<div class="subfolder_tab_l">
    <div class="subfolder_tab_r">
        <div class="subfolder_tab_m">
            <div class="subfolder_label">
                %1$s
                <span class="subfolder_count">%2$s</span>
            </div>
        </div>
    </div>
</div>');

//used when there's no data in the grid
define('VIEWGRID_NONE', '
<div class="grid_empty">
    %1$s
</div>');

//parameters: content
define('VIEWGRID_TABLE', '<table class="list">%s</table>');

//parameters: phrase (grid title), form target, form name, enctype, grid number, table content
define('EDITGRID_MAIN', '<h2>%s</h2>
<script type="text/javascript" src="js/CBs.js"></script>
<script type="text/javascript" src="3rdparty/filtery.js"></script>
<form action="%s" name="%s" method="post" %s>
<input type="hidden" name="gridnum" value="%d"/>
<div class="sz2tabs">
<div class="grid">
<table class="list" cellpadding="2" cellspacing="1">
    %s
</table>
</div>
</div>
</form>');

//rpc form
//parameters: form name, table content
define('EDITGRID_FORM', '<form action="" name="%s" method="post">
<table class="frm" cellpadding="2" cellspacing="1">
    %s
</table>
</form>');

//parameters: colspan, content
define('GRIDFORM_HTML',
'<td class=\"l3\" colspan="%s">
    <table class="frm" border="0" cellspacing="2" cellpadding="2">
        %s
    </table>
</td>');

//navigation (paging) strings

//parameters: colspan, nav string
define('GRID_NAV_ROW', '<tr><th class="p" colspan="%s">%s</th></tr>');
//parameters: theme_web
define('GRID_NAV_ONFIRSTPAGE', '<img src="%1$s/img/go-first-grey.png" title="' . gettext("This is the first page") . '" height="16" width="16" alt="'.gettext("go to the first screen").'" />&nbsp;&nbsp;<img src="%1$s/img/go-previous-grey.png" title="' . gettext("This is the first page") . '" height="16" width="16" alt="'.gettext("go to the previous screen").'" /> &nbsp; ');
//parameters: firstLink, prevLink, theme_web
define('GRID_NAV_PREVLINKS', '<a class="gridnav_first" href="%1$s"><img src="%3$s/img/go-first.png" title="' . gettext("Go to the first page") . '" height="16" width="16" alt="'.gettext("go to the first screen").'" /></a>&nbsp;&nbsp;<a class="p" href="%2$s"><img src="%3$s/img/go-previous.png" title="' . gettext("Go to the previous page") . '" height="16" width="16" alt="'.gettext("go to the previous screen").'" /></a> &nbsp; ');

//parameters: theme_web
define('GRID_NAV_ONLASTPAGE', ' &nbsp; <img src="%1$s/img/go-next-grey.png" title="' . gettext("This is the last page") . '" height="16" width="16" alt="'.gettext("go to the next screen").'" />&nbsp;&nbsp;<img src="%1$s/img/go-last-grey.png" title="' . gettext("This is the last page") . '" height="16" width="16" alt="'.gettext("go to the last screen").'" />');
//parameters: nextLink, lastLink, theme_web
define('GRID_NAV_NEXTLINKS', ' &nbsp; <a class="gridnav_first" href="%1$s"><img src="%3$s/img/go-next.png" title="' . gettext("Go to the next page") . '" height="16" width="16" alt="'.gettext("go to the next screen").'" /></a>&nbsp;&nbsp;<a class="p" href="%2$s"><img src="%3$s/img/go-last.png" title="' . gettext("Go to the last page") . '" height="16" width="16" alt="'.gettext("go to the last screen").'" /></a>');

//parameters: pageLink, pageNum
define('GRID_NAV_PAGELINK', '&nbsp;<a class="p" href="%s">%s</a> |&nbsp;');
//parameter: pageNum
define('GRID_NAV_CURRPAGE', '&nbsp;<b>%s</b> |&nbsp;');


//header strings

//parameters: content
define('GRID_HEADER_ROW', '<tr>
    %s
</tr>');
define('GRID_HEADER_ROW_EMAIL', '<tr>
    %s
</tr>');


//parameters: orderByLink, phrase
define('GRID_HEADER_CELL', '<th class="l"><a class="lh" href="%s">%s</a></th>');

define('GRID_HEADER_CELL_EMPTY', '<th class="l">&nbsp;</th>');

define('GRID_HEADER_CELL_EMAIL', '<th class="aa_l">%s</th>');

//gettext("Add New"), $theme_web
define('EDITGRID_HEADER_CELL_ADDNEW', '<th class="l" id="loc0"><a class="lh" href="javascript:editRow(\'0\');" title="%s"><img src="%s/img/grid_quickadd.gif" height="16" width="16" alt="'.gettext("add new").'" /></a></th>');

//view row for edit grids

//parameters: class, rowID, actionLink, actionPhrase, actionLink2, actionPhrase2, content
define('EDITGRID_VIEWROW', '<tr id="row%2$s">
    <td class="%1$s" id="loc%2$s">
        <a class="l" href="%3$s">%4$s</a>&nbsp;<a class="l" href="%5$s">%6$s</a>
    </td>
    %7$s
</tr>');

//parameters: class, rowID, actionLink, actionPhrase, content
define('EDITGRID_VIEWROW_NOFULLEDIT', '<tr id="row%2$s">
    <td class="%1$s" id="loc%2$s">
        <a class="l" href="%3$s">%4$s</a>
    </td>
    %5$s
</tr>');

define('UPLOADGRID_VIEWROW', '<tr id="row%2$s">
    <td class="%1$s" id="loc%2$s">
        <a class="l" href="%3$s">%4$s</a>
    </td>
    %5$s
</tr>');


//parameters: class, rowID, actionLink, actionPhrase, content
define('EDITGRID_RPCVIEWROW', '<td class="%1$s" id="loc%2$s"><a class="l" href="%3$s">%4$s</a></td> %5$s');
//parameters: colspan, nav string


//edit row for edit grids

//parameters: class, savePhrase, deletePhrase, cancelPhrase, content
define('EDITGRID_EDITROW', '<tr>
    <td class="%s">
        <input type="image" name="save" src="'.$theme_web.'/img/save.png" title="%s" value="1"/><br />
        <input type="image" name="delete" src="'.$theme_web.'/img/delete.png" title="%s" value="1"/><br />
        <input type="image" name="cancel" src="'.$theme_web.'/img/cancel.png" title="%s" value="1"/>
    </td>
    %s
</tr>');

//parameters: class, savePhrase, content
define('EDITGRID_INSERTROW', '<tr>
    <td class="%s">
        <input type="image" name="add" src="'.$theme_web.'/img/save.png" title="%s" value="1"/>
    </td>
    %s
</tr>');

//parameters: class, rowID, originally checked, content
define('CHECKGRID_ROW', '<tr>
    <td class="%s">&nbsp;
    <input type="hidden" name="RowID[]" value="%s"/>
    <input type="hidden" name="OrigChecked[]" value="%s"/>
    </td>
    %s
</tr>');

//parameters: class, columns, content
define('CHECKGRID_SAVEROW', '<tr>
    <td class="%s" colspan="%s" align="right">&nbsp;
    %s
    </td>
</tr>');

//view row (no link)
//parameters: class, rowID, firstcellcontent, content, module ID (for unique element ID)
define('VIEWGRID_ROW', '<tr>
    <td class="%1$s" id="loc%5$s%2$s">%3$s</td>
    %4$s
</tr>');
define('VIEWGRID_ROW_EMAIL', '<tr>
    %s
</tr>');

//parameters: align, class, content
define('GRID_VIEW_CELL', '<td align="%s" class="%s">%s</td>');

//parameters: 1 link, 2 CSS class, 3 theme location, 4 alt message, 5 rowID, 6 <not used>, 7 <not used>
define('LIST_GRID_NAVLINK', '<a class="%2$s" href="%1$s" id="vw%5$s">
    <img src="%3$s/img/list_viewrecord.gif" alt="%4$s" height="16" width="16" />
</a>%6$s%7$s');

//parameters: 1 link, 2 CSS class, 3 theme location, 4 alt message, 5 rowID, 6 <not used>, 7 module ID (for unique element ID)
define('VIEW_GRID_NAVLINK', '<a class="%2$s" href="#" id="fv%7$s%5$s" title="%4$s" onclick="window.open(\'%1$s\')"><img src="%3$s/img/record_new_window.gif" alt="%4$s" height="16" width="16" /></a>%6$s');

//parameters: 1 link, 2 CSS class, 3 theme location, 4 alt message, 5 rowID, 6 js alt message, 7 <not used>
define('EDIT_GRID_NAVLINK',
'<a class="%2$s" href="#" id="fe%5$s" title="%4$s" onclick="window.open(\'%1$s\')"><img src="%3$s/img/grid_fulledit.gif" alt="%4$s" height="16" width="16" /></a>&nbsp;<a class="%2$s" id="qe%5$s" title="%6$s" href="javascript:editRow(\'%5$s\')"><img src="%3$s/img/grid_quickedit.gif" alt="%6$s"/></a>%7$s');

//parameters: 1 <not used>, 2 CSS class, 3 theme location, 4 <not used>, 5 rowID, 6 js alt message, 7 <not used>
define('EDIT_GRID_NAVLINK_NOFULLEDIT', '<a class="%2$s" id="qe%5$s" title="%6$s" href="javascript:editRow(\'%5$s\')"><img src="%3$s/img/grid_quickedit.gif" alt="%6$s" height="16" width="16" /></a>%1$s%4$s%7$s');

//editing
//parameters: firstcellcontent, content
define('GRID_EDIT_ROW', '<tr>
    <td class="l">%s</td>
    %s
</tr>');
//parameters: align, class, content
define('GRID_EDIT_CELL', '<td align="%s" class="%s">%s</td>');

//parameters: align, colspan, savePhrase, deletePhrase, deleteLink, cancelPhrase, cancelLink
define('GRID_UPDATE_BUTTONS',
'<tr>
    <td align="%s" class="l" colspan="%s">
            <input class="btn" type="submit" name="Save" value="%s"/>
            <input class="btn" type="button" name="Delete" value="%s" onclick="%s"/>
            <input class="btn" type="button" name="Cancel" value="%s" onclick="%s"/>
    </td>
</tr');
//parameters: align, colspan, savePhrase
define('GRID_INSERT_BUTTONS',
'<tr>
    <td align="%s" class="l" colspan="%s">
            <input class="btn" type="submit" name="Save" value="%s"/>
    </td>
</tr');


define('POPOVER_GUIDANCE',
'<div id="guidance_popover" class="info_popover">
    <b>%s</b><br />
    %s
</div>');


define('POPOVER_DIRECTIONS', 
'<div id="directions_popover" class="info_popover">
    <b>%s</b><br />
    %s
</div>');

define('POPOVER_RESOURCES', 
'<div id="resources_popover" class="info_popover">
    <b>%s</b><br />
    %s
</div>');
?>