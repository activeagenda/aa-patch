<?php
/**
 *  Class that generates the JavaScript output definition for the shortcuts menu
 *
 *  PHP version 5
 *
 */

class Shortcuts
{


function Shortcuts($UserID)
{
	$this->PersonID = $UserID;
}


function render($menuType = '')
{
    switch($menuType){
    case 'G5':
        return $this->renderG5Menu();
        break;
    }
}

function renderG5Menu()
{
    $menuCode = "addMenu('Dsc', 'dsc-top');\n";
	$menuCode .= "addSubMenu('dsc-top', ' ', '', '', 'dsc-top_1', '');\n";

	global $dbh;
	$SQL = "SELECT  `usrds`.Type AS Type, `usrds`.Title AS Title, ";
	$SQL .= "IFNULL( CAST( `usrds`.Link AS CHAR ), '') AS InternalLink ";
	// Czy nie dodaæ orderby nazwa i po tem po type grouop type?
	$SQL .= "FROM `usrds` WHERE usrds._Deleted = 0 AND `usrds`.PersonID = ".$this->PersonID." ORDER BY Title, Type";
	$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
	dbErrorCheck($r);
	
	if(count($r) > 0){
		foreach($r as $shortcut){
			// addSubMenu("nav-top_1", "RPM path", "RPM path", "list.php?mdl=xr", "nav-top_1_2", "");
			$menuCode .= "addLink('dsc-top_1','".$shortcut['Title']." &#9656&nbsp;".strip_tags($shortcut['Type'],'<br>')."', '".$shortcut['Title']."', '";
			$menuCode .= $shortcut['InternalLink']."', '');\n";
			$counter++;		
		}
	}	
    
    $menuCode .= "endMenu();\n";
    $content = $menuCode;

    return $content;
}


}

?>
