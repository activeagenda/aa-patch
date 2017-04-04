<?php
/**
 *  Class that generates the JavaScript output definition for the Menu
 *
 *  PHP version 5
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
 * @version        SVN: $Revision: 1406 $
 * @last-modified  SVN: $Date: 2009-01-27 07:56:18 +0100 (Wt, 27 sty 2009) $
 */

class Navigator
{
var $Items = array();
var $ParseContext = array();
var $counter = 0;

function Navigator($UserID)
{
    define('LOADING_NAVIGATION', true);

    if(defined('CUSTOM_NAVIGATION_FILE')){
        $navigationFile = CUSTOM_NAVIGATION_FILE;
    } else {
        $navigationFile = APP_FOLDER . '/Navigation.xml';
    }
    $extendedNavigationFile = APP_FOLDER . '/xNavigation.xml';

    if (file_exists($navigationFile)){
            //assign SAX parser objects
            $parser = xml_parser_create();
            xml_set_object($parser, $this);
            xml_set_element_handler($parser, 'parseStartElement', 'parseEndElement');
            xml_set_character_data_handler($parser, 'parseCharacterData');

            //load the file ans parse its contents
            xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);

            $fp = fopen($navigationFile, 'r') or die("Can't read XML data.");
            while ($data = fread($fp, 4096)) {
                xml_parse($parser, $data, feof($fp)) or die("Can't parse XML data.");
            }
            fclose($fp);

            xml_parser_free($parser);

    } else {
        trigger_error("Could not open navigation menu file<br>\n", E_USER_ERROR);
    }

    if (file_exists($extendedNavigationFile)){
        $this->ParseContext = array();
        $parser = xml_parser_create();
        xml_set_object($parser, $this);
        xml_set_element_handler($parser, 'parseStartElementExtended', 'parseEndElement');
        xml_set_character_data_handler($parser, 'parseCharacterData');

        //load the file ans parse its contents
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);

        $fp = fopen($extendedNavigationFile, 'r') or die("Can't read XML data.");
        while ($data = fread($fp, 4096)) {
            xml_parse($parser, $data, feof($fp)) or die("Can't parse XML data.");
        }
        fclose($fp);

        xml_parser_free($parser);
    }
}

function parseStartElement($parser, $tag, $attr)
{
    switch($tag){
    case "Category":
        $t_item = &new NavCategory(
            $attr['phrase']
        );
        break;
    case "InternalLink":
        $secondary = null;
        $frame = null;
        $expand = null;
        $newbrowser = null;
        if(isset($attr['secondary'])){
            $secondary = $attr['secondary'];
        }
        if(isset($attr['frame'])){
            $frame = $attr['frame'];
        }
        if(isset($attr['expand'])){
            $expand = $attr['expand'];
        }
        if(isset($attr['newbrowser'])){
            $newbrowser = $attr['newbrowser'];
        }

        $t_item = &new NavInternalLink(
            $attr['primary'],
            $secondary,
            $frame,
            $expand,
            $newbrowser,
            $attr['phrase']
        );
        break;
    case "ModuleLink":
        $t_item = &new NavModuleLink(
            $attr['moduleID'],
            $attr['phrase']
        );
        break;
    case "ExternalLink":
        $t_item = &new NavExternalLink(
            $attr['target'],
            $attr['phrase']
        );
        break;
    }

    switch($tag){
    case "Category":
    case "InternalLink":
    case "ModuleLink":
    case "ExternalLink":

        //find the parent by iterating down the context
        $t_parent = &$this;
        foreach($this->ParseContext as $id){
            $t_parent =& $t_parent->Items[$id];
        }


        $this->ParseContext[] = $this->counter; //&$t_item;
        $t_parent->Items[$this->counter] = &$t_item;

        unset($t_parent);
        unset($t_item);

        //increment counter
        $this->counter++;
    }
}

function parseEndElement($parser, $tag)
{
    switch($tag){
        case "Category":
        case "InternalLink":
        case "ModuleLink":
        case "ExternalLink":
            $item = array_pop($this->ParseContext);
            //print "items = ".count($this->Items)."<br>\n";
            //print "<br>Now leaving " . $item->phrase . "<br><br>\n";
            break;
    }
}

function parseCharacterData($parser, $data)
{
    //no elements in or module definition take character data...
}

function parseStartElementExtended($parser, $tag, $attr)
{
//    trace($attr, "starting $tag");

    if(isset($attr['phrase'])){
        $t_parent = &$this;
        if(count($this->ParseContext) > 0){
            foreach($this->ParseContext as $id){
                $t_parent =& $t_parent->Items[$id];
            }
        }

        $found = false;
        //find item with a matching phrase
        foreach($t_parent->Items as $itemID => $item){
//trace("item phrase is '{$item->phrase}'; phrase attribute is '{$attr['phrase']}'");
            if(trim($item->phrase) == trim(ShortPhrase($attr['phrase']))){
                trace($attr, "found matching phrase '{$attr['phrase']}' ($itemID), drilling down");
                $found = true;

                //$parent =& $item;
                $this->ParseContext[] = $itemID;
                break;
            }
        }
        if(!$found){
            //add the item
            trace("found no matching phrase '{$attr['phrase']}', adding item");
            $this->parseStartElement($parser, $tag, $attr);
        }
    }
}

function parseEndElementExtended($parser, $tag)
{
    trace($attr, "ending $tag");
}

function render($menuType = '')
{
    switch($menuType){
    case 'G5':
        return $this->renderG5Menu();
        break;
    }

    $menuCode = '';
    $counter = 1;
    foreach($this->Items as $item){
        $menuCode .= $item->render("$counter");
        $menuCode .= "\n";
        $counter++;
    }

    $content = '<script type="text/javascript">' . "\n" . $menuCode . '</script>';

    return $content;
}

function renderG5Menu()
{
    $topname = 'nav-top';
    $menuCode = "addMenu('Nav', '{$topname}');\n";

    $counter = 1;
    foreach($this->Items as $item){
        $menuCode .= $item->render("{$topname}_{$counter}", 'G5');
        $menuCode .= "\n";
        $counter++;
    }

    $menuCode .= "endMenu();\n";
    $content = $menuCode;

    return $content;
}


function renderHTML($selModuleID = '')
{

    $content = '';
    $counter = 1;
    foreach($this->Items as $item){
        $content .= $item->renderHTML();
        $content .= "\n";
        $counter++;
    }


    return $content;
}


function getPhrases()
{
    $phrases = array();
    foreach($this->Items as $item){
        $phrases = array_merge($item->getPhrases(), $phrases);
    }
    return $phrases;
}
}  //end class Navigator


//navigation item classes

//abstract class
class NavItem 
{
var $Items = array();
var $phrase;
var $longPhrase;
var $itemWidth = 180;
var $itemHeight = 20;

function getNumVisibleChildren()
{
    $num = 0;
    foreach($this->Items as $item){
        if($item->isVisible()){
            $num++;
        }
    }

    return $num;
}

function isVisible(){
    return true; //override for Categories and Modules
}

function render($myID, $menuType = '')
{
    return gettext($this->phrase); //override this
}

function renderChildren($myID, $menuType = '')
{
    $content = '';

    if(count($this->Items) > 0){
        $counter = 1;
        foreach($this->Items as $item){

            $itemContent = $item->render("{$myID}_{$counter}", $menuType);
            if(!empty($itemContent)){
                $content .= "\n";
                $content .= $itemContent;

                $counter++;
            }
        }
    }

    return $content;
}


function renderHTML($showClassName = FALSE)
{
    if($this->phrase == 'Navigate'){
        $content .= $this->renderChildrenHTML();
    } else {
        if($showClassName){
            $content = "<li>".get_class($this) .' '. gettext($this->phrase); 
        } else {
            $content = '<li><a href="#">&nbsp;</a><b>'.gettext($this->phrase).'</b>'; 
        }
        $content .= $this->renderChildrenHTML();
        
        $content .= "</li>";
    }
    return $content;
}

function renderChildrenHTML()
{
    if($this->phrase == 'Navigate'){
        $id = 'id="nav"';
    } else {
        $id = '';
    }

    if(count($this->Items) > 0){
        $content = "<ul $id>\n";
        $counter = 1;
        foreach($this->Items as $item){

            $content .= "\n";
            $content .= $item->renderHTML();

            $counter++;
        }
        $content .= "\n</ul>\n";
    }

    return $content;
}

function getPhrases()
{
    $phrases = array($this->phrase); //no gettext call here
    if(count($this->Items) > 0){
        foreach($this->Items as $item){
            $phrases = array_merge($item->getPhrases(), $phrases);
        }
    }
    return $phrases;
}

function checkPhrase($phrase)
{
    if(empty($phrase)){
        return ' ';
    } else {
        return $phrase;
    }
}

} //end class NavItem



class NavCategory extends NavItem
{
var $_visible_calculated = false;
var $_visible = false;

function NavCategory($pPhrase)
{
    $this->phrase = NavItem::checkPhrase(ShortPhrase($pPhrase));
    $this->longPhrase = NavItem::checkPhrase(LongPhrase($pPhrase));
}

function isVisible()
{
    if($this->_visible_calculated){
        return $this->_visible;
    } else {
        $this->_visible_calculated = true;

        //only visible if at least one child is visible
        foreach($this->Items as $item){
            if($item->isVisible()){
                $this->_visible = true;
                return true;
            }
        }
    }
    $this->_visible = false;
    return false;
}

function render($myID, $menuType = '')
{
    $childrenContent = $this->renderChildren($myID, $menuType);

    if(!empty($childrenContent)){
        $numItems = $this->getNumVisibleChildren();
        $phrase = gettext($this->phrase);

        switch($menuType){
        case 'G5':
        default:
            $parentID = substr($myID, 0, strrpos($myID, '_')); 
            if('nav-top' == $parentID){
                $group = '';
            } else {
                $group = 'cat';
            }

            if(0 === $numItems){
                $content = "addLink(\"$parentID\", \"$phrase\", \"\", \"\", \"$group\");";
            } else {
                $content = "addSubMenu(\"$parentID\", \"$phrase\", \"\", \"\", \"$myID\", \"$group\");";
            }
            break;
        }
        //include children:
        $content .= $childrenContent;

        return $content;

    } else {
        return '';
    }
}

function renderHTML($showClassName = FALSE)
{
    $content = '';
    if($this->isVisible()){
        if($this->phrase == 'Navigate'){
            $content .= $this->renderChildrenHTML();
        } else {
            if($showClassName){
                $content = "<li>".get_class($this) .' '. gettext($this->phrase); 
            } else {
                $content = '<li><a href="#">&nbsp;</a><b>'.gettext($this->phrase).'</b>'; 
            }
            $content .= $this->renderChildrenHTML();

            $content .= "</li>";
        }
        return $content;
    } else {
        return '';
    }
}
}

class NavInternalLink extends NavItem
{
var $primary;   //primary target
var $secondary; //secondary target (is loaded in opposite frame when MultiFrame is used)
var $frame;     //the frame to load $primary into. valid values are 'upper' and 'lower'
var $expand = 'both';   //what frame to expand. the other valid values are 'upper' and 'lower'
var $newbrowser; //whether to open a new browser

function NavInternalLink($pPrimary, $pSecondary, $pFrame, $pExpand, $pNewBrowser, $pPhrase){

    $this->primary = $this->addPageExtenstion($pPrimary);
    $this->secondary = $this->addPageExtenstion($pSecondary);

    $this->frame = $pFrame;
    $this->expand = $pExpand;
    $this->newbrowser = $pNewBrowser;
    $this->phrase = NavItem::checkPhrase(ShortPhrase($pPhrase));
    $this->longPhrase = NavItem::checkPhrase(LongPhrase($pPhrase));
}


function render($myID, $menuType = '')
{

    $numItems = $this->getNumVisibleChildren();
    $phrase = gettext($this->phrase);
    $linkto = $this->primary;

    switch($menuType){
    case 'G5':
    default:
        $parentID = substr($myID, 0, strrpos($myID, '_')); 
        if(0 === $numItems){
            $content = "addLink(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"$linkto\", \"\");";
        } else {
            $content = "addSubMenu(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"$linkto\", \"$myID\", \"\");";
        }
        break;
    }
    //include children:
    $content .= $this->renderChildren($myID, $menuType);

    return $content;
}


function renderHTML($showClassName = FALSE)
{
    $linkto = $this->primary;
    $content = "<li>";
    if($showClassName){
        $content .= get_class($this);
    } 
    $content .= '<a href="'.$linkto.'"><b>'.gettext($this->phrase).'</b></a>'; 
    $content .= $this->renderChildrenHTML();

    return $content;
}

function addPageExtenstion($link)
{
    $ext = 'php';
    if(false === strpos(strtolower($link), '.'.$ext)){
        if(false === strpos(strtolower($link), '?')){
            $link .= '.'.$ext;
        } else {
            $parts = split('?', $link, 1);
            $link = $parts[0] . '.' . $ext . '?' . $parts[1];
        }
    } 
    return $link;
}
}

class NavExternalLink extends NavItem
{
var $target;
function NavExternalLink($pTarget, $pPhrase)
{
    $this->target = $pTarget;
    $this->phrase = NavItem::checkPhrase(ShortPhrase($pPhrase));
    $this->longPhrase = NavItem::checkPhrase(LongPhrase($pPhrase));
}

function render($myID, $menuType = '')
{
    $numItems = $this->getNumVisibleChildren();
    $phrase = gettext($this->phrase);
    if(false === strpos(strtolower($this->target), '://')) {
        if(false === strpos(strtolower($this->target), 'file:\\\\')) {
            $linkto = 'http://'.$this->target;
        } else {
            $linkto = $this->target;
        }
    } else {
        $linkto = $this->target;
    }

    switch($menuType){
    case 'G5':
    default:
        $parentID = substr($myID, 0, strrpos($myID, '_')); 

        if(0 == $numItems){
            $content = 
            "addLink(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"$linkto\", \"\");";
        } else {
            "addSubMenu(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"$linkto\", \"$myID\", \"\");";
        }
        break;
    }
    //include children:
    $content .= $this->renderChildren($myID, $menuType);

    return $content;

}

function renderHTML($showClassName = FALSE)
{
    $linkto = 'http://'.$this->target;
    $content = "<li>";
    if($showClassName){
        $content .= get_class($this);
    } 
    $content .= '<a href="'.$linkto.'"><b>'.gettext($this->phrase).'</b></a>'; 
    $content .= $this->renderChildrenHTML();

    return $content;
}
}


class NavModuleLink extends NavInternalLink
{
var $moduleID;
var $_visible_calculated = false;
var $_visible = false;
var $_showLink = true;

function NavModuleLink($pModuleID, $pPhrase)
{
    $this->moduleID = $pModuleID;
    $this->primary = "list.php?mdl=$pModuleID";
    $this->secondary = "search.php?mdl=$pModuleID";
    $this->frame = 'lower';
    $this->expand = 'both';
    $this->newbrowser = false;
    $this->phrase = NavItem::checkPhrase(ShortPhrase($pPhrase));
    $this->longPhrase = NavItem::checkPhrase(LongPhrase($pPhrase));
}


/**
 *  Whether the item will be displayed or not
 *
 *  The decision whether the link will be displayed or not is made in the render() method below.
 */
function isVisible()
{
    if($this->_visible_calculated){
        return $this->_visible;
    }
    $this->_visible_calculated = true;

    global $User;

    //hides non-implemented modules
    if(defined('MENU_FILTER_IMPLEMENTED_MODULES') && MENU_FILTER_IMPLEMENTED_MODULES){
        if(in_array($this->moduleID, $_SESSION['VisibleModules'])){
            $implemented = true;
        } else {
            $implemented = false;
        }
    } else {
        $implemented = true;
    }

    if($implemented){
        //if the user has no permission to this item but to a child
        if(!$User->PermissionToView($this->moduleID)){
            $this->_showLink = false;
            $this->_visible = false;
            foreach($this->Items as $item){
                if($item->isVisible()){
                    $this->_visible = true;
                }
            }
        } else {
            $this->_visible = true;
        }
    } else {
        $this->_visible = false;
        foreach($this->Items as $item){
            if($item->isVisible()){
                $this->_visible = true;
            }
        }
    }
    return $this->_visible;
}

function render($myID, $menuType = '')
{
    global $User;

    if($this->isVisible()){
        if($this->_showLink){
            return parent::render($myID, $menuType);
        } else {
            //forced view render w/o link...
            $numItems = $this->getNumVisibleChildren();
            $phrase = gettext($this->phrase);
            switch($menuType){
            case 'G5':
            default:
                $parentID = substr($myID, 0, strrpos($myID, '_')); 

                if(0 == $numItems){
                    $content = "addLink(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"\", \"\");";
                } else {
                    $content = "addSubMenu(\"$parentID\", \"$phrase\", \"{$this->longPhrase}\", \"\", \"$myID\", \"\");";
                }
                break;
            }
            //include children:
            $content .=  $this->renderChildren($myID, $menuType);

            return $content;
        }
    }
}

function renderHTML($showClassName = FALSE)
{
    global $User;

    if($User->PermissionToView($this->moduleID)){
        if($showClassName){
            $content = "<li>".get_class($this) .' '. gettext($this->phrase); 
        } else {
            if(count($this->Items) == 0){
                $class = ' class="pad"';
                $expandlink = '&nbsp;';
            } else {
                $class = '';
                $expandlink = '<a href="#">&nbsp;</a>';
            }
            $content = '<li>'.$expandlink.'<b><a'.$class.' href="'.$this->primary.'">'.gettext($this->phrase).'</a></b>'; 
        }
        $content .= $this->renderChildrenHTML();
        $content .= "</li>";
        return $content;
    } else {
        if($this->isVisible()){
            //same as render a category
            $content = NavItem::renderHTML($showClassName);

            return $content;
        } else {
            return '';
        }
    }
}
}

?>
