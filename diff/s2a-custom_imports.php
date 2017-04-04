<?php
/**
 * Utility to import specific flat files.
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
 */

$Project = $_SERVER[argv][1];
$importType = $_SERVER[argv][2];
$fileName = $_SERVER[argv][3];
if(empty($fileName)){
    print '
    s2a-custom-imports: Performs a customized import into Active Agenda

    BASIC USAGE:
    ./s2a-custom-imports.php <project_name> <importType> <fileName>

    ';
    die('Not enough parameters.');
}


//assumes we're in the 's2a' folder 
$site_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'');
$site_folder .= '/'.$Project;

//includes
$config_file = $site_folder . '/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}

$gen_config_file = $site_folder . '/gen-config.php';
if(!file_exists($gen_config_file)){
    print "Config file not found at $gen_config_file\n";
    exit;
}

//get settings
include_once $config_file;
include_once $gen_config_file;

require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class

//utility functions
include_once INCLUDE_PATH . '/parse_util.php';

//connect with superuser privileges - regular user has no permission to
//delete records
global $dbh;
$dbh = DB::connect(GEN_DB_DSN);
dbErrorCheck($dbh);

/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 2);

$props = array(); //keeps all the info that's specific to the import type

switch($importType){
case 'bodyParts':
    $props['codeTypeID'] = 51;

    $props['tMod'] = 'linbt';
    $props['tIDFld'] = 'BodyPartTypeID';
    $props['tDivFld'] = 'BodyPartTypeDivision';
    $props['tLabelFld'] = 'PartType';
    $props['tCatIDFld'] = 'BodyPartCategoryID';

    $props['lMod'] = 'linbp';
    $props['lIDFld'] = 'BodyPartID';
    $props['lDivFld'] = 'BodyPartDivision';
    $props['lLabelFld'] = 'BodyPartTitle';
    $props['lTypeIDFld'] = 'BodyPartTypeID';
    break;
case 'exposures':
    $props['codeTypeID'] = 195;

    $props['tMod'] = 'linet';
    $props['tIDFld'] = 'InjuryExposureTypeID';
    $props['tDivFld'] = 'InjuryExposureTypeDivision';
    $props['tLabelFld'] = 'ExposureType';
    $props['tCatIDFld'] = 'InjuryExposureCategoryID';

    $props['lMod'] = 'linex';
    $props['lIDFld'] = 'InjuryExposureID';
    $props['lDivFld'] = 'InjuryExposureDivision';
    $props['lLabelFld'] = 'InjuryExposureTitle';
    $props['lTypeIDFld'] = 'InjuryExposureTypeID';
    break;
case 'nature':
    $props['codeTypeID'] = 73;

    $props['tMod'] = 'linnt';
    $props['tIDFld'] = 'InjuryNatureTypeID';
    $props['tDivFld'] = 'InjuryNatureTypeDivision';
    $props['tLabelFld'] = 'NatureType';
    $props['tCatIDFld'] = 'InjuryNatureCategoryID';

    $props['lMod'] = 'linna';
    $props['lIDFld'] = 'InjuryNatureID';
    $props['lDivFld'] = 'InjuryNatureDivision';
    $props['lLabelFld'] = 'InjuryNatureTitle';
    $props['lTypeIDFld'] = 'InjuryNatureTypeID';
    break;
case 'sources':
    $props['codeTypeID'] = 27;

    $props['tMod'] = 'linst';
    $props['tIDFld'] = 'InjurySourceTypeID';
    $props['tDivFld'] = 'InjurySourceTypeDivision';
    $props['tLabelFld'] = 'SourceType';
    $props['tCatIDFld'] = 'InjurySourceCategoryID';

    $props['lMod'] = 'linsc';
    $props['lIDFld'] = 'InjurySourceID';
    $props['lDivFld'] = 'InjurySourceDivision';
    $props['lLabelFld'] = 'InjurySourceTitle';
    $props['lTypeIDFld'] = 'InjurySourceTypeID';
    break;
default:
    die('the only supported import types are: bodyParts, exposures, nature, sources');
}


if(file_exists($fileName)){
    print "Importing data for $importType from file $fileName.\n";
} else {
    die("File $fileName does not exist.");
}

//open the text file
$textFile = $fileName;
$nodes = array();
$lines = array();
$currentID = '*';
if($fp = fopen($textFile, 'r')){
    //repair any wrapped lines
    $saveLine = '';
    while(!feof($fp)){
        $line = fgets($fp); //reads line by line
        $line = trim($line);
        $data = split(' ', $line, 2);

        if(0 != strlen($line)){ //ignore blank lines
            if(!is_numeric($data[0])){
                $lines[$currentID] .= ' '. trim($line); //appends wrapped line
            } else {
                $currentID = trim($data[0]);
                $lines[$currentID] = trim($data[1]);
            }
        }
    }

    foreach($lines as $idTag=>$definition){
        $nodes[$idTag] =& new Node($idTag, array('def'=>$definition));
    }
    
    //assign nodes to their parents
    foreach(array_keys($nodes) as $idTag){
        $node =& $nodes[$idTag]; //shouldn't be necessary (?) but the foreach returns a copy
        $parentIdTag = substr($node->idTag, 0, -1);
        $parentFound = false;
        while(strlen($parentIdTag) > 0 && !$parentFound){
            if(isset($nodes[$parentIdTag])){
                $parentNode =& $nodes[$parentIdTag];
                $parentNode->addChild(&$node);
                unset($parentNode);
                $parentFound = true;
            } else {
                //continue looking for a parent node
                $parentIdTag = substr($parentIdTag, 0, -1);
            }
        }
        if(!$parentFound){
            print "no parent found for {$idTag} (assuming it is a category)\n";
        }
        unset($node);
    }
    
    //print_r($nodes); //this returns very large output...
    
    $categories = array();
    $types = array();
    $leaves = array();
    
    foreach($nodes as $idTag=>$node){
        if($node->isLeaf()){
            $top =& $node->getTopNode();
            $mid =& $node->getMidNode();
            
            //print "| {$top->idTag} | {$mid->idTag} | $idTag |\n";
            $categories[$top->idTag] = true;
            $types[$mid->idTag] = true;
            $leaves[$idTag] = true;
            
            unset($top);
            unset($mid);
        }
    }

    //print_r($categories);
    //print_r($types);
    //print_r($leaves);

    //get the translation for the codes
    $getCodeSQL = "SELECT Value, CodeID FROM `cod` WHERE CodeTypeID = '{$props['codeTypeID']}' ORDER BY Value";

    $insertCodeSQL = "INSERT INTO `cod` 
    (CodeTypeID, CodeID, SortOrder, Value, Description, _ModDate, _Deleted) 
    VALUES ('{$props['codeTypeID']}', ?, ?, ?, ?, NOW(), 0)";

    $updateCodeSQL = "UPDATE `cod` SET 
        SortOrder = ?,
        Value = ?,
        Description = ?,
        _ModDate = NOW(),
        _Deleted = 0
    WHERE CodeTypeID = '{$props['codeTypeID']}' AND CodeID = ?";
    
    //get translation array
    $codeTr = $dbh->getAssoc($getCodeSQL);
    dbErrorCheck($codeTr);
    
    /*print "Translation table for Categories (code ID {$props['codeTypeID']})\n";
    print_r($codeTr);*/
    
    $codeIns = $dbh->prepare($insertCodeSQL);
    $codeUpd = $dbh->prepare($updateCodeSQL);
    
    $maxCodeID = $dbh->getOne("SELECT MAX(CodeID) FROM `cod` WHERE CodeTypeID = '{$props['codeTypeID']}'");
    dbErrorCheck($maxCodeID);
    $nextCodeID = intval($maxCodeID) + 1;
    foreach(array_keys($categories) as $categoryID){
        $node =& $nodes[$categoryID];
        
        if(array_key_exists($categoryID, $codeTr)){
            //print "$categoryID matched an item in (".join(',', array_keys($codeTr)).")\n";
            //update
            print "Updating category $categoryID\n";
            //SortOrder, Value, Description, CodeID
            $r = $dbh->execute($codeUpd, 
                array(
                    $categoryID,
                    $categoryID,
                    ucwords(strtolower($node->data['def'])),
                    $codeTr[$categoryID]
                )
            );
            dbErrorCheck($r);
        } else {
            //print "$categoryID not in (".join(',', array_keys($codeTr)).")\n";
            //insert
            print "Inserting category $categoryID\n";
            //CodeID, SortOrder, Value, Description
            $r = $dbh->execute($codeIns, array($nextCodeID, $categoryID, $categoryID, ucwords(strtolower($node->data['def']))));
            dbErrorCheck($r);
            
            $nextCodeID++;
        }
        unset($node);
    }
    
    //check the codes again
    //get translation array
    $codeTr = $dbh->getAssoc($getCodeSQL);
    dbErrorCheck($codeTr);
    
    /*print "Updated translation table for Categories (code ID {$props['codeTypeID']})\n";
    print_r($codeTr);*/
    
    
    
    //get the translation for the types
    $getTypeSQL = "SELECT {$props['tDivFld']}, {$props['tIDFld']} FROM {$props['tMod']} ORDER BY {$props['tDivFld']}";
    
    $insertTypeSQL = "INSERT INTO `{$props['tMod']}` 
    ({$props['tDivFld']}, {$props['tLabelFld']}, {$props['tCatIDFld']}, _ModDate, _Deleted) 
    VALUES (?, ?, ?, NOW(), 0)";

    $updateTypeSQL = "UPDATE `{$props['tMod']}` SET 
        {$props['tDivFld']} = ?,
        {$props['tLabelFld']} = ?,
        {$props['tCatIDFld']} = ?,
        _ModDate = NOW(),
        _Deleted = 0
    WHERE {$props['tIDFld']} = ?";
    
    $typeTr = $dbh->getAssoc($getTypeSQL);
    dbErrorCheck($typeTr);
    
    $typeIns = $dbh->prepare($insertTypeSQL);
    $typeUpd = $dbh->prepare($updateTypeSQL);
    
    foreach(array_keys($types) as $typeID){
        $node =& $nodes[$typeID];
        $categoryNode = $node->getTopNode();
        $categoryValue = $categoryNode->idTag;
        
        if(array_key_exists($typeID, $typeTr)){
            //print "$typeID matched an item in (".join(',', array_keys($typeTr)).")\n";
            //update
            print "Updating type $typeID\n";
            $r = $dbh->execute($typeUpd,
                array(
                    $typeID,
                    ucwords(strtolower($node->data['def'])),
                    $codeTr[$categoryValue],
                    $typeTr[$typeID]
                )
            );
            dbErrorCheck($r);
        } else {
            //print "$typeID not in (".join(',', array_keys($typeTr)).")\n";
            //insert
            print "Inserting type $typeID\n";
            $r = $dbh->execute($typeIns,
                array(
                    $typeID,
                    ucwords(strtolower($node->data['def'])),
                    $codeTr[$categoryValue]
                )
            );
            dbErrorCheck($r);
        }
        unset($node);
    }
    
    //re-get the type translation
    $typeTr = $dbh->getAssoc($getTypeSQL);
    dbErrorCheck($typeTr);
    
    
    
    
    //get the translation for the leaves
    $getLeafSQL = "SELECT {$props['lDivFld']}, {$props['lIDFld']} FROM {$props['lMod']} ORDER BY {$props['lDivFld']}";
    
    $insertLeafSQL = "INSERT INTO `{$props['lMod']}` 
    ({$props['lDivFld']}, {$props['lLabelFld']}, {$props['lTypeIDFld']}, _ModDate, _Deleted) 
    VALUES (?, ?, ?, NOW(), 0)";

    $updateLeafSQL = "UPDATE `{$props['lMod']}` SET 
        {$props['lDivFld']} = ?,
        {$props['lLabelFld']} = ?,
        {$props['lTypeIDFld']} = ?,
        _ModDate = NOW(),
        _Deleted = 0
    WHERE {$props['lIDFld']} = ?";
    
    $leafTr = $dbh->getAssoc($getLeafSQL);
    dbErrorCheck($leafTr);
    
    $leafIns = $dbh->prepare($insertLeafSQL);
    $leafUpd = $dbh->prepare($updateLeafSQL);
    
    foreach(array_keys($leaves) as $leafID){
        $node =& $nodes[$leafID];

        $typeNode = $node->getMidNode();
        $typeValue = $typeNode->idTag;

        if(array_key_exists($leafID, $leafTr)){
            //print "$leafID matched an item in (".join(',', array_keys($leafTr)).")\n";
            //update
            print "Updating leaf $leafID\n";
            $r = $dbh->execute($leafUpd,
                array(
                    $leafID,
                    ucwords(strtolower($node->data['def'])),
                    $typeTr[$typeValue],
                    $leafTr[$leafID]
                )
            );
            dbErrorCheck($r);
        } else {
            //print "$leafID not in (".join(',', array_keys($leafTr)).")\n";
            //insert
            print "Inserting leaf $leafID\n";
            $r = $dbh->execute($leafIns,
                array(
                    $leafID,
                    ucwords(strtolower($node->data['def'])),
                    $typeTr[$typeValue]
                )
            );
            dbErrorCheck($r);
        }
        unset($node);
    }





    print "\nAll done!\n\n";

}


/*
 *  helper class
 */

class Node
{
var $idTag;
var $data = array(); //data property
var $parent = null;
var $children = array();

function Node($idTag, $data)
{
    $this->idTag = $idTag;
    $this->data = $data;
}

function isTop()
{
    return empty($this->parent);
}

function isLeaf()
{
    return count($this->children) == 0;
}

function addChild(&$child)
{
    if(array_key_exists($child->idTag, $this->children)){
        echo "Node '{$child->idTag}' exists already as a child of '{$this->idTag}'\n";
        return false;
    }
    $this->children[$child->idTag] =& $child;
    $child->assignParent($this);
    return true;
}

function assignParent(&$parent)
{
    if(!empty($this->parent)){
        echo "Node '{$this->idTag}' already has a parent: '{$this->parent->idTag}'\n";
        return false;
    } else {
        $this->parent =& $parent;
    }
}

function &getTopNode()
{
    if($this->isTop()){
        return $this;
    } else {
        return $this->parent->getTopNode();
    }
}

function &getMidNode()
{
    if($this->isTop()){
        return $this;
    } elseif($this->parent->isTop()){
        return $this;
    } else {
        return $this->parent->getMidNode();
    }
}

} //end class Node
?>