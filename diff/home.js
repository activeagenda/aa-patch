/**
 * javascript functions used on home.php only (and in template files)
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

function loadChart(chartID, url){
    chartImg = document.getElementById(chartID);
    chartImg.src = url;
}


function togglePopOver(gridID){
    callerObj = document.getElementById(gridID);
    dashMenu = callerObj.parentNode;
    popoverObj = document.getElementById('popover');
    if(popoverObj.style.display == 'none' || popoverObj.callerObj != callerObj){
        if(openedDashMenu){
            openedDashMenu.className = 'dashMenu';
        }
        dashMenu.className = 'dashMenu_hi';
        openedDashMenu = dashMenu;
        showPopOver(gridID);
    } else {
        popoverObj.style.display = 'none';
        dashMenu.className = 'dashMenu';
    }
    callerObj.blur();
}


function closePopOver(){
    dashMenus = document.getElementsByTagName('DIV');
    for(i in dashMenus){
        if(dashMenus[i].className == 'dashMenu_hi'){
            dashMenu = dashMenus[i];
            break;
        }
    }
    popoverObj.style.display = 'none';
    dashMenu.className = 'dashMenu';
}


//function showPopOver(callerObj, gridID){
function showPopOver(gridID){
    popoverObj = document.getElementById('popover');
    popoverContentObj = document.getElementById('popover_content');
    callerObj = document.getElementById(gridID);
    dashMenu = callerObj.parentNode;

    popoverObj.callerObj = callerObj;
    popoverObj.style.top = (callerObj.offsetHeight+ findPosY(callerObj))+'px';
    popoverObj.style.left = (findPosX(callerObj))+'px';

    popoverContentObj.innerHTML="ładowanie...";
    popoverObj.style.display = 'block';

    //call dashboardGridRPC.php for grid content
    var req = new DataRequestor();
    req.addArg(_GET, "mdl", gridID);

    req.getURL('rpc/dashboardGridRPC.php');
    req.onload = function (indata, obj){

    if('session timeout' == indata){
        alert("Na skutek wygaśnięcia sesji dane nie mogą być pobrane z serwera. Kliknij OK w celu ponownego zalogowania się.");
        location.reload();
        return false;
    }
    resp = eval('('+indata+')');
    if(resp["error"]){
        alert(resp["error"]);
    }

    rowcountObj = document.getElementById(gridID+'_count');
    rowcountObj.innerHTML = resp['rowcount'];
    popoverContentObj.innerHTML = resp['content'];
    }
}


function imgOver(oImg, replaceSrc){
    oImg.origSrc = oImg.src;
    oImg.src = replaceSrc;
}


function imgOut(oImg){
    if(oImg.origSrc){
        oImg.src = oImg.origSrc;
    }
}


function forceImgOut(oChart){
    icons = oChart.getElementsByTagName('img');
    for(iconID in icons){
        imgOut(icons[iconID]);
    }
}


function moveChartUp(dsbcID){
    var req = new DataRequestor();
    req.addArg(_GET, 'dsbc', dsbcID);
    req.addArg(_GET, 'action', 'up');

    req.getURL('rpc/dashboardChartRPC.php');
    req.onload = function (indata, obj){
        resp = eval('('+indata+')');
        if(resp["error"]){
            alert(resp["error"]);
            return;
        }
        if(resp["content"]){
            //swap the chart div with the previous
            oChart = document.getElementById('chart'+ dsbcID);
            oParent = oChart.parentNode;
            oSibling = oChart.previousSibling;
            oParent.removeChild(oChart);
            oParent.insertBefore(oChart, oSibling);
            forceImgOut(oChart);
        } else {
            alert('Już przy pierwszym wykresie');
        }
    }
}


function moveChartDown(dsbcID){
    var req = new DataRequestor();
    req.addArg(_GET, 'dsbc', dsbcID);
    req.addArg(_GET, 'action', 'dn');

    req.getURL('rpc/dashboardChartRPC.php');

    req.onload = function (indata, obj){
        resp = eval('('+indata+')');
        if(resp["error"]){
            alert(resp["error"]);
            return;
        }
        if(resp["content"]){
            //swap the chart div with the next
            oChart = document.getElementById('chart'+ dsbcID);
            oParent = oChart.parentNode;
            oSibling = oChart.nextSibling;
            oParent.removeChild(oChart);
            if(oSibling == oParent.lastChild){
                oParent.appendChild(oChart);
            } else {
                oParent.insertBefore(oChart, oSibling.nextSibling);
            }
            forceImgOut(oChart);
        } else {
            alert('Już przy ostatnim wykresie');
        }
    }
}


function removeChart(dsbcID){
    if(!confirm('Usunąć ten wykres?')){
        return false;
    }

    var req = new DataRequestor();
    req.addArg(_GET, 'dsbc', dsbcID);
    req.addArg(_GET, 'action', 'rm');

    req.getURL('rpc/dashboardChartRPC.php');
    req.onload = function (indata, obj){
        resp = eval('('+indata+')');
        if(resp["error"]){
            alert(resp["error"]);
            return;
        }
        if(resp["content"] == 'success'){
            oChart = document.getElementById('chart'+ dsbcID);
            oParent = oChart.parentNode;
            oParent.removeChild(oChart);
        }
    }
}
