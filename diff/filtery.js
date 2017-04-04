/*
  filtery functions adapted by Mattias Thorslund from an example by Justin Whitford
  
  source: www.evolt.org
*/

//helper function to sort items by rank (3rd sub-array field) | MJT
function subArraySort(a, b){
    /*if(a[0] < 1){
        return 1;
    }
    if(b[0] < 1){
        return -1;
    }
    */
    //compare the rank field
    if(a[2] < b[2]){
        return -1;
    }
    if(a[2] < b[2]){
        return 1;
    }
    
    //compare the text field
    if(a[1] < b[1]){
        return -1;
    }
    if(a[1] < b[1]){
        return 1;
    }
    
    //both items have the same rank
    return 0;
}

function filteryText(e, pattern, list){
    var code;
    if (!e) var e = window.event;
    if (e.keyCode) code = e.keyCode;
    else if (e.which) code = e.which;
    //var character = String.fromCharCode(code);
    //alert('Character was ' + character);
    if(code != 9){
        //if (!list.bak){  //backup interferes with my hierarchically dependent filtering fields, so the easiest workaround is to re-create it every time... | MJT 10/26/2004
        list.bak = new Array();
        for (n=0;n<list.length;n++){
            list.bak[list.bak.length] = new Array(list[n].value, list[n].text);
        }
        //}
        
        oCB = list[0].parentNode;
        
        //cache selected item | MJT
        selectedItemValue = oCB.value.toString();
        
        match = new Array();
        nomatch = new Array();
        
        //copy "unselected" item
        unsel = new Array(list.bak[0][0], list.bak[0][1]);
        //alert(unsel[1]);
        
        for (n=1;n<list.bak.length;n++){
            rank = list.bak[n][1].toLowerCase().indexOf(pattern.toLowerCase());
            if(rank !=-1 && list.bak[n][0] > 0){ //don't match 0 = unselected
            match[match.length] = new Array(list.bak[n][0], list.bak[n][1], rank);
            }else{
            nomatch[nomatch.length] = new Array(list.bak[n][0], list.bak[n][1]);
            }
        }
        
        //sort matched items by rank | MJT
        match.sort(subArraySort);
        
        //init selIndex
        if(pattern == ''){
            selIndex = 0; //select "unselected" item
        } else {
            if(match.length > 0) {
                selIndex = 1; //select first (best) match
            } else {
                selIndex = 0; //select "unselected" item
            }
        }
        
        //put "unselected" item first
        list[0].value = unsel[0];
        list[0].text = unsel[1];
        
        for (n=0;n<match.length;n++){
            list[n+1].value = match[n][0];
            list[n+1].text = match[n][1];
        
            //if selectedItem is still in the (match) list, select it | MJT
            if(selectedItemValue == match[n][0]){
            selIndex = n+1;
            }
        }
        for (n=0;n<nomatch.length;n++){
            list[n+match.length+1].value = nomatch[n][0];
            list[n+match.length+1].text = nomatch[n][1];
        }
        
        list.selectedIndex=selIndex;
        
        //fire CB onChange | MJT
        oCB.onchange();
        indicateUnsavedChanges(null, oCB);
    }
}

function filteryAlpha(pattern,list){
    
    //if (!list.bak){  //backup interferes with my hierarchically dependent filtering fields, so the easiest workaround is to re-create it every time... | MJT 10/26/2004
    list.bak = new Array();
    for (n=0;n<list.length;n++){
      list.bak[list.bak.length] = new Array(list[n].value, list[n].text);
    }
  //}
  match = new Array();
  nomatch = new Array();
  for (n=0;n<list.bak.length;n++){
    //only match if the pattern matches the beginning of the value | MJT
    if(list.bak[n][1].toLowerCase().indexOf(pattern.toLowerCase()) == 0){
      match[match.length] = new Array(list.bak[n][0], list.bak[n][1]);
    }else{
      nomatch[nomatch.length] = new Array(list.bak[n][0], list.bak[n][1]);
    }
  }
  for (n=0;n<match.length;n++){
    list[n].value = match[n][0];
    list[n].text = match[n][1];
  }
  for (n=0;n<nomatch.length;n++){
    list[n+match.length].value = nomatch[n][0];
    list[n+match.length].text = nomatch[n][1];
  }
  list.selectedIndex=0;
}