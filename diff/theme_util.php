<?php
/**
 * Shared functions and classes for the theme maintenance functionality.
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
 * @version        SVN: $Revision: 1411 $
 * @last-author    SVN: $Author: code_g $
 * @last-modified  SVN: $Date: 2009-01-28 18:26:38 +0100 (Śr, 28 sty 2009) $
 */


/**
 *  Recursive function to merge the theme config with its template config
 *
 *  Elements that get copied from the template config also get a new attribute
 *  'inherited="yes"', so that they can be identified.
 */
function &MergeConfigs(&$theme_element, &$template_element)
{
    if(count($template_element->attributes) > 0) {
        foreach($template_element->attributes as $attr_name => $attr_value){
            if(!isset($theme_element->attributes[$attr_name])){
                $theme_element->attributes[$attr_name] = $attr_value;
                $theme_element->attributes['inherited'] = 'yes';
            }
        }
    }
    if(count($template_element->c) > 0){
        foreach($template_element->c as $template_child_element){
            if(is_object($template_child_element) && is_a($template_child_element, 'element')){
                $matching_theme_elements = $theme_element->selectElements(
                    $template_child_element->type,
                    'name',
                    $template_child_element->name);

                if(count($matching_theme_elements) == 0){
                    $template_child_element->attributes['inherited'] = 'yes';
                    $theme_element->c[] = $template_child_element;
                } else {
                    foreach($matching_theme_elements as $matching_theme_element_ix => $matching_theme_element){
                        $merged_element = MergeConfigs($matching_theme_element, $template_child_element);

                        foreach($theme_element->c as $sub_theme_element_ix => $sub_theme_element){
                            if('element' == strtolower(get_class($sub_theme_element))){
                                if($sub_theme_element->name == $merged_element->name){
                                    $theme_element->c[$sub_theme_element_ix] = $merged_element; 
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    return $theme_element;
}


/**
 *  Detects whether a value is a color, an image reference or something else (very rudimentary)
 */
function detectValueType($theme_value)
{
    //detect type of value (need an attribute?) for visualization
    if(false !== strpos($theme_value, '#')){
        $value_type = 'color';
    } else {
        //detect image
        if(preg_match('/.*\.(gif|jpg|png)/', $theme_value)){
            $value_type = 'image';
        } else {
            $value_type = 'other';
        }
    }
    return $value_type;
}


/**
 *  Helper class to allow specifying colors in HSV
 */
class HSVColor
{
var $hue;
var $saturation;
var $value;
var $hue256;
var $saturation256;
var $value256;

function &Factory($element, $moduleID = null)
{
    $ref = new HSVColor($element, $moduleID);
    return $ref;
}

function HSVColor($element, $moduleID = null)
{
    $this->hue        = $this->checkRange($element->attributes['hue'], 'hue', 0, 360);
    $this->saturation = $this->checkRange($element->attributes['saturation'], 'saturation', 0, 100);
    $this->value      = $this->checkRange($element->attributes['value'], 'value', 0, 100);

    //we adjust the values to work with the PEAR package
    $this->hue256        = (256.0 * $this->hue)/360;
    $this->saturation256 = (256.0 * $this->saturation)/100;
    $this->value256      = (256.0 * $this->value) / 100;
}

function getHex()
{
    require_once PEAR_PATH . '/Image/Color.php';
    $color =& new Image_Color();

    $hex = $color->hsv2hex( $this->hue256, $this->saturation256, $this->value256);
    return '#'.strtolower($hex);
}

function checkRange($value, $type, $min, $max)
{
    $err = '';
    if($value > $max){
        $err = 'too large';
    }
    if($value < $min){
        $err = 'too small';
    }
    if(!empty($err)){
        trigger_error("Error in HSVColor definition. The attribute '$type' is $err for the range $min - $max.", E_USER_ERROR);
    }
    return $value;
}
} //end class HSVColor

?>
