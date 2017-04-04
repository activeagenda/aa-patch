<?php
/**
 * Template file for generated files (alt. a generated file)
 *
 * PHP version 5
 *
 *
 * LICENSE NOTE:
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
 * @version        SVN: $Revision: 1548 $
 * @last-modified  SVN: $Date: 2009-03-05 16:27:47 +0100 (Cz, 05 mar 2009) $
 */

    //check if user has permission to view or edit record
	
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
	
	if( !empty( $EditScrPermission) ){	
		$allowEdit = true;
	}else{
		$allowEdit = $User->CheckListScreenPermission();
	}    

    $pageTitle = gettext("/**module_name**/");
    $screenPhrase = gettext("List");

    //remove search filter if user requested it
    if(isset($_GET['clear']) && '1' == $_GET['clear']){
        unset($_SESSION['Search_'.$ModuleID]);
    } 

    $expected_params = array('mdl', 'sr', 'ob', 'pp');

    //clean up query string
    foreach($qsArgs as $qsField => $qsVal){
        if(!in_array($qsField, $expected_params)){
            unset($qsArgs[$qsField]);
        }
    }
    $qs = MakeQS($qsArgs);

    $tabsQS = $qs; //legacy or needed?
    $sQS = $qs;    //legacy or needed?

    $linked = array_keys($linkFields);

    $tabs['List'] = Array('', gettext("List"), 'self');

    if ($allowEdit){
        /**tabs|EDIT**/
    } else {
        /**tabs|VIEW**/
    }


    //phrases for table headers, in field order
    /**headers**/

    //table column alignment values
    /**fieldAlign**/

    //table column data types - to format values (Yes/No, date, number, etc.)
    /**fieldTypes**/

    /**fieldFormats**/

    /**linkFields**/

    $useBestPractices = false; //default value
    /**useBestPractices**/

    /**defaultOrderBys**/

    $listFilterSQL = $User->getListFilterSQL($ModuleID);

    $nColumns = count($headers);

    $offset = 0;
    if(!empty($_GET['o'])){
        $offset = intval($_GET['o']);
    }

    $clearSearch = false;
    if(isset($_GET['clear']) && '1' == $_GET['clear']){
        $clearSearch = true;
    }

    $filterByURL = false;
    if(isset($_GET['filter']) && '1' == $_GET['filter']){
        $filterByURL = true;
        $clearSearch = true;
    }

    if(!$clearSearch && isset($_SESSION['Search_'.$ModuleID])){
        $search = $_SESSION['Search_'.$ModuleID];
    } else {
        //create an empty Search object
        $search = GetNewSearch($ModuleID);

        if(!$clearSearch && !$filterByURL){
            $search->loadUserDefault($User->PersonID);
        }
        if($filterByURL){
            $search->loadURLFilter();
        }
        $_SESSION['Search_'.$ModuleID] = $search;
    }

    $search = $_SESSION['Search_'.$ModuleID];
	
	//Page number on the list
    $perPage = 5;
	if( isset( $_SESSION[$ModuleID.'_ListSeq']['rpp'] ) ){
		$perPage = $_SESSION[$ModuleID.'_ListSeq']['rpp'];
	}	
    if( !empty($_GET['pp']) ){
        $perPage = intval($_GET['pp']);
    }
	$filtercontent = '';
	if(is_object($search)){        
		if(isset($_GET['defaultFilter']) && '0' == $_GET['defaultFilter']){
            $search->removeUserDefault($User->PersonID);
        }
		
        $filtercontent .= "<br />\n";
        $filtercontent .= '<div class="searchFilter"><a href="search.php?mdl='.$ModuleID.'" title="&nbsp; &nbsp; '.gettext("Search").' &nbsp; &nbsp;"><img src="'.$theme_web.'/img/search_icon.png"/>&nbsp;<b>'.gettext("Search Filter Conditions").':</b></a><br/><br/>'."\n";
		$filtercontent .= str_replace( '!themeDirectory!', $theme_web, $search->getPhrases() );
		
        if($search->hasConditions()){			
            $filtercontent .= "<br/><br/>\n";                
			$defaultFilterLink = 'defaultFilter=0&amp;';
            $clearSearchLink = '<a href="list.php?clear=1&amp;'.$defaultFilterLink.$sQS.'">'.gettext("Clear Search Filter (removing conditions)").'</a>';
            $filtercontent .= $clearSearchLink;
        }       
        
        $filtercontent .= "\n";
        //custom code
        /**CUSTOM_CODE|accReassign**/

        $filtercontent .= "</div><br />\n";
    }
	$content = '';
    $listData = new ListData($ModuleID, $search->getListSQL(null, true), $perPage, $fieldTypes, true, null, $fieldFormats);
    $nRows = $listData->getCount();
    if(0 == $nRows){
        $content .= '<div class="emptywrap">'.gettext("The request returned no data. Please try a different search.").'</div>';
    } else {
        $startRow = 0;
        if(!empty($_GET['sr'])){
            $startRow = intval($_GET['sr']);
        }
		// Record menu on the list		
		/**tabs|RECORDMENU**/
		
		// list rendering			
		if( $allowEdit ){	
			if( !empty($NoEditScrPermission) ){
				foreach( $NoEditScrPermission as $EditScreenName=> $NoPermissionModuleID){
					if( !empty($recordMenuEntries[$EditScreenName]) ) unset( $recordMenuEntries[$EditScreenName] );
					if( !empty($recordMenuURL[$EditScreenName]) ) unset( $recordMenuURL[$EditScreenName] );
				}
			}
			$MenuTabs = '['.join( ",\n",$recordMenuEntries ).'],'.$MenuFixTabs;
			
			$i = 0;
			foreach( $recordMenuURL as $screenName => $screenURL){
				$recordMenuURL[$screenName] = "thisMenu[0][$i].cfg.setProperty(\"url\", \"$screenURL&\" + myTarget);\n";				
				$i++;
			}
			$MenuUrl = join( "",$recordMenuURL )."\n".$MenuFixUrl;
			
			if( /**allowDelete**/ ){	
				$renderer = new ListRenderer(
					$ModuleID,
					$listData,
					$headers,
					'view.php?',
					'list.php?',
					$fieldAlign,
					'list',
					$linkFields,
					null,
					'edit.php?',
					$_GET['rid'],
					80,
					$tabs['New'][0]
					);
			}else{
				$renderer = new ListRenderer(
					$ModuleID,
					$listData,
					$headers,
					'view.php?',
					'list.php?',
					$fieldAlign,
					'list',
					$linkFields,
					null,
					null,
					$_GET['rid'],
					80,
					$tabs['New'][0]
					);
			}
		}else{
			if( !empty( $recordMenuEntries['View'] ) ){
				$recordMenuEntriesV['View'] = $recordMenuEntries['View'];
				$recordMenuURLV['View'] = "thisMenu[0][0].cfg.setProperty(\"url\", \"".$recordMenuURL['View']."&\" + myTarget);\n";
			}
			if( !empty( $recordMenuEntries['RecordReports'] ) ){
				$recordMenuEntriesV['RecordReports'] = $recordMenuEntries['RecordReports'];
				$recordMenuURLV['RecordReports'] = "thisMenu[0][1].cfg.setProperty(\"url\", \"".$recordMenuURL['RecordReports']."&\" + myTarget);\n";
			}
			
			$MenuTabs = '['.join( ",\n",$recordMenuEntriesV ).'],'.$MenuFixTabs;
			$MenuUrl = join( "",$recordMenuURLV )."\n".$MenuFixUrl;
			
			$renderer = new ListRenderer(
				$ModuleID,
				$listData,
				$headers,
				'view.php?',
				'list.php?',
				$fieldAlign,
				'list',
				$linkFields,
				null,
				null,
				$_GET['rid'],
				80
			);
		}			
        $renderer->useBestPractices = $useBestPractices;
        $content .= $renderer->render($startRow, $defaultOrderBys);
    }

    //return just the table HTML if this is an AJAX-style call
    if(isset($_GET['rpc']) && 1 == $_GET['rpc']){
        die($content);
    }
	$content = $filtercontent.$content 
   
?>
