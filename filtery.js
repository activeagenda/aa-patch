/*
  filtery functions adapted by Mattias Thorslund from an example by Justin Whitford
  
  source: www.evolt.org
*/

//helper function to sort items by rank (3rd sub-array field) | MJT
function subArraySort(a, b){    
    //compare the rank field
    if(a[2] < b[2]){
        return -1;
    }
    if(a[2] > b[2]){
        return 1;
    }
    
    //compare the text field
	var atext = a[1];
	var btext = b[1];
	
	//Ą Ę Ś Ć Ż Ź Ó Ł  Ń ą ę ś ć ż ź ó ł ń
	atext = atext.replace('Ą','Az');
	atext = atext.replace('Ę','Ez');
	atext = atext.replace('Ś','Sz');
	atext = atext.replace('Ż','Zzz');
	atext = atext.replace('Ź','Zz');
	atext = atext.replace('Ł','Lz');
	atext = atext.replace('Ó','Oz');
	atext = atext.replace('Ć','Cz');
	atext = atext.replace('Ń','Nz');
	
	atext = atext.replace('ą','az');
	atext = atext.replace('ę','ez');
	atext = atext.replace('ś','sz');
	atext = atext.replace('ż','zzz');
	atext = atext.replace('ź','zz');
	atext = atext.replace('ł','lz');
	atext = atext.replace('ó','oz');
	atext = atext.replace('ć','cz');
	atext = atext.replace('ń','nz');
	
	btext = btext.replace('Ą','Az');
	btext = btext.replace('Ę','Ez');
	btext = btext.replace('Ś','Sz');
	btext = btext.replace('Ż','Zzz');
	btext = btext.replace('Ź','Zz');
	btext = btext.replace('Ł','Lz');
	btext = btext.replace('Ó','Oz');
	btext = btext.replace('Ć','Cz');
	btext = btext.replace('Ń','Nz');
	
	btext = btext.replace('ą','az');
	btext = btext.replace('ę','ez');
	btext = btext.replace('ś','sz');
	btext = btext.replace('ż','zzz');
	btext = btext.replace('ź','zz');
	btext = btext.replace('ł','lz');
	btext = btext.replace('ó','oz');
	btext = btext.replace('ć','cz');
	btext = btext.replace('ń','nz');	
	
	atext = atext.toLowerCase();
	btext = btext.toLowerCase();
	
	return atext.localeCompare( btext );
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
			list[n].disabled = false;
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
            //rank = list.bak[n][1].toLowerCase().indexOf(pattern.toLowerCase());
			rank = list.bak[n][1].substring(0,pattern.length).toLowerCase().indexOf(pattern.toLowerCase());
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
           
        }
        for (n=0;n<nomatch.length;n++){
            list[n+match.length+1].value = nomatch[n][0];
            list[n+match.length+1].text = nomatch[n][1];
			list[n+match.length+1].disabled = true
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