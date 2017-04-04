<?php
/**
 * A graphical theme visualizer.
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

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

include_once $theme .'/component_html.php';

include_once INCLUDE_PATH . '/general_util.php';
include_once INCLUDE_PATH . '/parse_util.php';
include_once INCLUDE_PATH . '/theme_util.php';
include_once CLASSES_PATH . '/module_map.class.php';

if(empty($_GET['theme'])){
    $current_theme = DEFAULT_THEME;
} else {
    $current_theme = addslashes($_GET['theme']);
}

$content = '';

//read available themes
$theme_matches = glob(THEME_PATH .'/*/theme-config.xml');

$theme_names = array();
foreach($theme_matches as $theme_match_path){
    $theme_match_path = dirname($theme_match_path);
    $theme_name = basename($theme_match_path);
    if('templates' != $theme_name){
        $theme_names[] = $theme_name;
    }
}

//display theme selection links
$content .= '<h1>'.gettext("Available Themes").'</h1>';
$content .= '<ul>';
foreach($theme_names as $theme_name){
    $content .= "<li><a href=\"themeVisualizer.php?theme=$theme_name\">".$theme_name.'</a></li>';
}
$content .= '</ul>';

$content .= '<h1>'.sprintf(gettext("Theme %s"), $current_theme).'</h1>';

//let's parse the theme-config.xml file
$theme_config_file = THEME_PATH .'/'.$current_theme.'/theme-config.xml';
$theme_config_map =& new XMLMap($theme_config_file, 'Theme');

//look up template folder
$template_folder = THEME_PATH . '/templates/' . $theme_config_map->attributes['template'];
if(!file_exists($template_folder)){
    trigger_error("Could not find template folder $template_folder\n", E_USER_ERROR);
}

$template_config_file = $template_folder.'/template-config.xml';
$template_config_map =& new XMLMap($template_config_file, 'ThemeTemplate');

$theme_config_map = MergeConfigs($theme_config_map, $template_config_map);

//$content .= debug_r($theme_config_map);

$theme_values = array();

$content .= '<h2>'.sprintf(gettext("Theme Values"), $current_theme).'</h2>';
$theme_value_elements = $theme_config_map->selectChildrenOfFirst('ThemeValues');
//$content .= debug_r($theme_value_elements);
$table_content = '<tr><th class="l">Name</th><th class="l">Explicit Value</th><th class="l">Reference</th><th class="l">Effective Value</th><th class="l">Visualization</th><th class="l">Inherited</th></tr>';

$tdClasses = array("l", "l2");
$row_ix = 0;
$options = array();

foreach($theme_value_elements as $theme_value_element){
    $tdClass = $tdClasses[$row_ix % 2];
    $openingTd = "<td class=\"$tdClass\">";
    $table_content .= '<tr>';
    $table_content .= $openingTd.'<b>'.$theme_value_element->name.'</b></td>';
    if('characterdata' == strtolower(get_class($theme_value_element->c[0]))){
        $theme_value = $theme_value_element->c[0]->content;
        $theme_type = detectValueType($theme_value);
        $table_content .= $openingTd.$theme_value.'</td>';
        $table_content .= $openingTd.'</td>';
        $table_content .= $openingTd.$theme_value.'</td>';
    } elseif('HSVColor' == $theme_value_element->c[0]->type) {
        $theme_type = 'color';
        $colorObj = $theme_value_element->c[0]->createObject(null);
        $theme_value = $colorObj->getHex();
//print debug_r($colorObj);
        $table_content .= $openingTd.$theme_value." (h{$colorObj->hue} s{$colorObj->saturation} v{$colorObj->value})".'</td>';
        $table_content .= $openingTd.'</td>';
        $table_content .= $openingTd.$theme_value.'</td>';
    } else {

        $table_content .= $openingTd.'</td>';

        $ref_name = $theme_value_element->c[0]->name;
        $theme_value = $theme_values[$ref_name];
        $theme_type = detectValueType($theme_value);

        $table_content .= $openingTd.$ref_name.'</td>';
        $table_content .= $openingTd.$theme_value.'</td>';
    }

    switch($theme_type){
    case 'color':
        $table_content .= $openingTd.'<div style="background-color:'.$theme_value.';width:80px">&nbsp;</div></td>';
        break;
    case 'image':
        $table_content .= $openingTd.'<img style="max-height:30px;max-width:80px" src="'.THEME_WEB_PATH.'/'.$current_theme.'/'.$theme_value.'"/></td>';
        break;
    default:
        $table_content .= $openingTd.'</td>'; //no visualization possible
        break;
    }

    if(!empty($theme_value_element->attributes['inherited'])){
        $table_content .= $openingTd.$theme_value_element->attributes['inherited'].'</td>';
    } else {
        $table_content .= $openingTd.'no</td>';
    }

    $table_content .= "</tr>\n";

    $theme_values[$theme_value_element->name] = $theme_value;
    $options[$theme_type][$theme_value_element->name] = $theme_value;
    $row_ix++;
}

//wrap into table
$content .= '<table class="grid">'.$table_content.'</table>';


$content .= '<h2>'.sprintf(gettext("Theme Tags"), $current_theme).'</h2>';
$theme_tag_elements = $theme_config_map->selectElements('ThemeTag');

$tdClasses = array("l", "l2");
$row_ix = 0;
$table_content = '<tr><th class="l">Tag Name</th><th class="l">Description</th><th class="l">Value Name</th><th class="l">Effective Value</th><th class="l">Visualization</th><th class="l">Inherited</th></tr>';
foreach($theme_tag_elements as $theme_tag_element){
    $tdClass = $tdClasses[$row_ix % 2];
    $openingTd = "<td class=\"$tdClass\">";
    $table_content .= '<tr>';
    $table_content .= $openingTd.$theme_tag_element->name.'</td>'."\n";
    $table_content .= $openingTd.$theme_tag_element->attributes['description'].'</td>'."\n";

    if('characterdata' == strtolower(get_class($theme_tag_element->c[0]))){
        $theme_tag_value = $theme_tag_element->c[0]->content;
        $theme_tag_type = detectValueType($theme_tag_value);
        $table_content .= $openingTd.'</td>';
        $table_content .= $openingTd.$theme_tag_value.'</td>';
    } else {
        $ref_name = $theme_tag_element->c[0]->name;
        $theme_tag_value = $theme_values[$ref_name];
        $theme_tag_type = detectValueType($theme_tag_value);
        $table_content .= $openingTd.$ref_name.'</td>';
        $table_content .= $openingTd.$theme_tag_value.'</td>';
    }

    switch($theme_tag_type){
    case 'color':
        $table_content .= $openingTd.'<div style="background-color:'.$theme_tag_value.';width:80px">&nbsp;</div></td>';
        break;
    case 'image':
        $table_content .= $openingTd.'<img style="max-height:30px;max-width:80px" src="'.THEME_WEB_PATH.'/'.$current_theme.'/'.$theme_tag_value.'"/></td>';
        break;
    default:
        $table_content .= $openingTd.'</td>'; //no visualization possible
        break;
    }

    if(!empty($theme_tag_element->attributes['inherited'])){
        $table_content .= $openingTd.$theme_tag_element->attributes['inherited'].'</td>';
    } else {
        $table_content .= $openingTd.'no</td>';
    }

    $table_content .= "</tr>\n";
}
$content .= '<table class="grid">'.$table_content.'</table>';

$title = gettext("Theme Visualizer");
include_once $theme . '/no-tabs.template.php';