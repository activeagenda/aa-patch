<?php 
 /**
 * Classes for XML mapping
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
 * @version        SVN: $Revision: 1520 $
 * @last-modified  SVN: $Date: 2009-02-14 01:10:37 +0100 (So, 14 lut 2009) $
 */


/**
 * Generic element class
 */
class Element
{


/**
 * The content of the element's name attribute, if any. If there's
 * no name attribute, this will contain the element's tag name.
 */
var $name;


/**
 * The element's tag name.
 *
 * If the tag name is equal to a valid class name of a class that implements
 * s2a's Factory method, an object of that class can be instantiated by way of 
 * the createObject() method below.
 */
var $type;


/**
 * An associative array of all the element's attributes.
 */
var $attributes = array();


/**
 * An array of all the element's contents, i.e. character data and contained elements.
 */
var $c   = array();


/**
 * Constructor
 */
function Element($name, $type, $attributes, $c = null)
{
    $this->name       = $name;
    $this->type       = $type;
    $this->attributes = $attributes;

    if(!empty($c)){
        $this->c =& $c;
    }
}


/**
 * Returns an instance of the class named in the $type property
 * unless the class name is overridden in the $type parameter
 */
function &createObject($moduleID, $type = null, $callerRef = null)
{
    if(empty($type)){
        $type = $this->type;
    }

    $valid = class_exists($type);
    if($valid !== true){
        if('PermissionGrid' == $type){
            include_once(INCLUDE_PATH . '/usrpPermissionGrid.php');
            $valid = true;
            //return true;
        }
        $test_type = str_replace('GridField', 'Field', $type);
        if(class_exists(str_replace('GridField', 'Field', $test_type))){
            $valid = true;
            $type = $test_type;
        }
    }

    //if $this->type exists as a class, create and return it
    if($valid === true){
        if(empty($callerRef)){
            $object = call_user_func(array($type, 'Factory'), $this, $moduleID);
        } else {
            trigger_error("Element_createObject: Calling createObject with a reference to the calling object. This requires using the new method createObjectWithRef() instead.", E_USER_ERROR);
        }

        if($object !== false){
            return $object;
        } else {
            trigger_error("Element_createObject: Factory method is not defined for the class $type.", E_USER_ERROR);
        }
    } else {
        trigger_error("Element_createObject: $type is not defined as a class", E_USER_ERROR);
    }
}


/**
 *  Duplicate function for passing the calling context object as a reference.
 *
 *  This is a workaround because of PHP 4 limitations (passing by reference to an
 *  optional parameter is only possible with the deprecated call time syntax.
 */
function &createObjectWithRef($moduleID, $type = null, &$callerRef)
{
    if(empty($type)){
        $type = $this->type;
    }

    $valid = class_exists($type);
    if($valid !== true){
        if('PermissionGrid' == $type){
            include_once(INCLUDE_PATH . '/usrpPermissionGrid.php');
            $valid = true;
            //return true;
        }
        $test_type = str_replace('GridField', 'Field', $type);
        if(class_exists(str_replace('GridField', 'Field', $test_type))){
            $valid = true;
            $type = $test_type;
        }
    }

    //if $this->type exists as a class, create and return it
    if($valid === true){
        if(empty($callerRef)){
            $object = call_user_func(array($type, 'Factory'), $this, $moduleID);
        } else {
            $object = call_user_func(array($type, 'Factory'), $this, $moduleID, $callerRef);
        }

        if($object !== false){
            return $object;
        } else {
            trigger_error("Element_createObject: Factory method is not defined for the class $type.", E_USER_ERROR);
        }
    } else {
        trigger_error("Element_createObject: $type is not defined as a class", E_USER_ERROR);
    }
}


/**
 * Returns a MetaDoc object for the class represented in $type.
 */
function createDoc($moduleID, $type = NULL)
{
    if(empty($type)){
        $type = $this->type;
    }

    $valid = class_exists($type);
    if($valid !== true){
        if('PermissionGrid' == $type){
            include_once(INCLUDE_PATH . '/usrpPermissionGrid.php');
        }
        $test_type = str_replace('GridField', 'Field', $type);
        if(class_exists(str_replace('GridField', 'Field', $test_type))){
            $valid = true;
            $type = $test_type;
        }
    }

    //if $this->type exists as a class, create and return it
    if($valid === true){
//print "creating $type {$this->name}\n";
        $object = call_user_func(array($type, 'DocFactory'), $this, $moduleID);
        if($object !== false){
            return $object;
        } else {
            trigger_error("Element_createDoc: DocFactory method is not defined for the class $type.", E_USER_ERROR);
        }
    } else {
        die("Element_createDoc: $type is not defined as a class");
    }
}


/**
 * returns the content as in the XML file
 */
function getContent($innerOnly = false, $level = 0)
{
    $attributes = '';
    $content = '';
    $indents = str_repeat('   ', $level);

    if(!$innerOnly){
        if(count($this->attributes) > 0){
            foreach($this->attributes as $attr_name => $attr_value){
                $attributes .= $attr_name.'="'.$attr_value.'" ';
            }
            $attributes = ' '.rtrim($attributes);
        }
    }

    if(count($this->c) > 0){
        if(!$innerOnly){
            $content .= "$indents<{$this->type}{$attributes}>";
        }
        foreach($this->c as $contentItem){
            $content .= "\n";
            switch(strtolower(get_class($contentItem))){
            case 'characterdata':
                $content .= "$indents   ".$contentItem->getContent(!$innerOnly);
                break;
            case 'element':
                $content .= $contentItem->getContent(false, $level + 1);
                break;
            default:
                die('unknown content type in element object');
            }
        }

        if(!$innerOnly){
            $content .= "\n$indents</{$this->type}>";
        }
        return $content;
    } else {
        if($innerOnly){
             return '';
        } else {
            return "$indents<{$this->type}{$attributes}/>";
        }
    }
}


/**
 * returns the content of contained elements and character data
 */
function getText($trim = true)
{
    $text = '';
    if(count($this->c) > 0){
        foreach($this->c as $contentItem){
            $text .= $contentItem->getText($trim)."\n";
        }
    }
    if($trim){
        $text = trim($text);
    }
    return $text;
}


/**
 * Private function to help implement the "select" methods below
 */
function _matchesCriteria(&$element, $elementType, $attribute, $attributeValue){
    if($element->type == $elementType){ //first test - match element tag name
        if(empty($attribute)){ //no more tests - return success
            return true;
        } else {  //also check attribute with value
            if(isset($element->attributes[$attribute]) && $element->attributes[$attribute] == $attributeValue){ //check that the attribute exists and that it matches the value
                return true;
            }
            //special: "name" attribute might not always be set
            if('name' == $attribute){
                if($element->name == $attributeValue){
                    return true;
                }
            }
        }
    }

    //all other cases
    return false;
}

/**
 * Returns sub-elements where the type matches the $elementType parameter
 */
function &selectElements($elementType, $attribute = null, $attributeValue = null, $returnOnFirst = false, $recurse = false)
{
    $matches = array();
    if(count($this->c) > 0){
        foreach($this->c as $ix => $obj){
            if(is_object($obj) && is_a($obj, 'Element')){
                if($this->_matchesCriteria($obj, $elementType, $attribute, $attributeValue)){
                    $matches[] = $obj;
                    if($returnOnFirst){
                        return $matches;
                    }
                }
                if($recurse){
                    //keep looking
                    if(count($obj->c) > 0){
                        $matches = array_merge((array)$matches, (array)$obj->selectElements($elementType, $attribute, $attributeValue));
                        if($returnOnFirst && count($matches) > 0){
                            return $matches;
                        }
                    }
                }
            }
        }
    }

    return $matches;
}


function selectFirstElement($element, $attribute = null, $attributeValue = null, $recurse = false)
{
    $elements = $this->selectElements($element, $attribute, $attributeValue, true, $recurse);
    if(count($elements) > 0){
        return reset($elements);
    } else {
        return false;
    }
}


function &selectChildrenOfFirst($element, $attribute = null, $attributeValue = null, $recurse = false)
{
    $element = $this->selectFirstElement($element, $attribute, $attributeValue, $recurse);
    return $element->c;
}


/**
 *  Returns the value of an attribute without generating a PHP Notice if it's not set.
 */
function getAttr($attributeName, $required = false, $defaultVal = null)
{
    if(!isset($this->attributes[$attributeName])){
        if($required){
            print_r($this->attributes);
            trigger_error("The attribute $attributeName is required in the {$this->type} {$this->name}.", E_USER_ERROR);
        }
        return $defaultVal;
    } else {
        return $this->attributes[$attributeName];
    }
}
} //end class Element




/**
 * mini-class for character data
 */
class CharacterData {
var $content;

function CharacterData($content)
{
    $this->content = $content;
}

function getContent($wrapCDATA = false)
{
    if(!$wrapCDATA){
        return $this->content;
    }

    //wrap content in a CDATA section if there is content that can be confused with an XML element
    if(false !== strpos($this->content, '<')){
        return '<![CDATA['.$this->content.']]>';
    }

    return $this->content;
}

/**
 * returns the content as text
 */
function getText($trim = true)
{
    if($trim){
        return trim($this->content);
    } else {
        return $this->content;
    }
}
} //end class CharacterData





/**
 * Generic XML map class
 *
 * Parses an XML file into an Element tree
 */
class XMLMap extends Element {

var $parseContext   = array();
var $parsed         = false;
var $XMLFileName    = '';
var $rootElement    = '';

function XMLMap($XMLFileName, $rootElement){
    $this->XMLFileName = $XMLFileName;
    $this->rootElement = $rootElement;
    $this->parseXMLFile();
}


/**
 * Parses and loads the XML data into element objects
 */
function parseXML($XMLData){

    //assign SAX parser object and handlers
    $parser = xml_parser_create();
    xml_set_object($parser, $this);
    xml_set_element_handler($parser, 'parseStartElement', 'parseEndElement');
    xml_set_character_data_handler($parser, 'parseCharacterData');

    //turn off case folding
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, FALSE);

    //parse data (will call the assigned handlers)
    xml_parse($parser, $XMLData, true) or trigger_error("Can't parse XML data ({$this->XMLFileName}).", E_USER_ERROR);

    xml_parser_free($parser);
    $this->parsed = true;
}


/**
 * Loads a file (defaults to $XMLFileName) and hands the data to parseXML
 */
function parseXMLFile($fileName = null)
{
    if(!empty($fileName)){
        $this->XMLFileName = $fileName;
    }

    if($this->parsed){
        trace("Already parsed {$this->XMLFileName}, ignoring.");
        return;
    }

    if (file_exists($this->XMLFileName)){
        $filedata = file_get_contents($this->XMLFileName) or trigger_error("{$this->XMLFileName}: Can't read XML data from file.", E_USER_ERROR);
        $this->parseXML($filedata);
    } else {
        trigger_error("Could not find file '{$this->XMLFileName}'.", E_USER_ERROR);
    }
}


/**
 * SAX startElement function
 *
 * called each time a new element is found
 */
function parseStartElement($parser, $tag, $attributes)
{
  //print "found tag $tag\n";
  //print_r($this->parseContext);
    

    switch($tag){
    case $this->rootElement:
        $this->type = $tag;
        $this->attributes = $attributes;
        break;
    default:
        if(isset($attributes['name']) && !empty($attributes['name'])){
            $name = $attributes['name'];
        } else {
            $name = $tag;
        }
//print "$name: ($tag)\n";

        $element = & new Element($name, $tag, $attributes);
        $parent = &$this;

//print join(', ', $this->parseContext)."\n";

        //loop through parse context to find parent
        if(count($this->parseContext) > 0){
            foreach($this->parseContext as $childID){
                $parent =& $parent->c[$childID];
            }
        }

        //add element to parent
        $parent->c[] =& $element;
        $id = end(array_keys($parent->c));

        //array_push($this->parseContext, $name);
        array_push($this->parseContext, $id);
    }
}


/**
 * SAX end element function
 *
 * called each time an element ends
 */
function parseEndElement($parser, $tag)
{
    switch($tag){
    case 'Module':
        break;
    default:
        array_pop($this->parseContext);
    }
}

/**
 * SAX character data function
 *
 * called each time text data is found (except comments, process instructions etc)
 */
function parseCharacterData($parser, $data)
{

    if(strlen(trim($data)) > 0){
        $characterData = new CharacterData($data);
        $parent =& $this;

        //loop through parsecontext to find parent
        if(count($this->parseContext) > 0){
            foreach($this->parseContext as $contextName){
                $parent =& $parent->c[$contextName];
            }
        }

        $parent->c[] = $characterData;
    }
}

}  //end class XMLMap


/**
 * XML Map class specific to parsing ModuleDef files
 */
class ModuleMap extends XMLMap {

//attributes of the Module tag
var $moduleID;
var $rootElement = 'Module';


/**
 * Constructor
 */
function ModuleMap($moduleID)
{
    $this->moduleID = $moduleID;
    $this->XMLFileName = GetXMLFilePath($this->moduleID .'ModuleDef.xml');
    $this->parseXMLFile();
}

} //end class ModuleMap




/**
 * Function to print an XML map
 */
function recurseMap($object, $level = 0)
{

    $class = strtolower(get_class($object));
    print str_repeat('.  ', $level); 
    if(!empty($object->name)){
        $title = $object->name . " ({$object->type})";
    } else {
        $title = $class;
    }
    print "{$title} \n";

    $vars = get_object_vars($object);

    if(is_array($vars) && count($vars) > 0){
        foreach($vars as $name => $child){
            if(is_object($child)){
                recurseMap($child, $level+2);
            } else {

                print str_repeat('   ', $level+1); 
                if(is_array($child)){
                    print "arry $name: \n";
                    foreach($child as $k => $v){
                        if(is_object($v)){
                            recurseMap($v, $level+2);
                        } else {
                            print str_repeat('   ', $level+2);
                            print "$k: $v\n";
                        }
                    }
                } else {
                    
                    print "prop $name: $child\n";
                }
            }
        }
    }
}

?>