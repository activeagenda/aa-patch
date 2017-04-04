<?php
/**
 * Utility to upgrade Active Agenda database and data.
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
 * @version        SVN: $Revision: 1537 $
 * @last-modified  SVN: $Date: 2009-02-28 00:56:02 +0100 (So, 28 lut 2009) $
 */



define('EXEC_STATE', 2);

if(3 <= ($_SERVER['argc'])){
    $project = $_SERVER['argv'][2];
} else {
    $project = 'active_agenda';
}

$theme = '';
if(2 <= ($_SERVER['argc'])){
    $theme = $_SERVER['argv'][1];
}

//assumes we're in the 's2a' folder 
$site_folder = realpath(dirname($_SERVER['SCRIPT_FILENAME']).'');
$site_folder .= '/'.$project;

//includes
$config_file = $site_folder . '/config.php';
if(!file_exists($config_file)){
    print "Config file not found at $config_file\n";
    exit;
}

//get settings
include_once $config_file;
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//get includes
include_once INCLUDE_PATH . '/general_util.php';
include_once INCLUDE_PATH . '/parse_util.php';
include_once INCLUDE_PATH . '/theme_util.php';
include_once CLASSES_PATH . '/module_map.class.php';

/**
 * Sets custom error handler
 */
set_error_handler('handleError');


$debug_prefix = 's2a-build-theme:';

if(empty($theme)){
    $available_themes = glob($site_folder . '/web/themes/*', GLOB_ONLYDIR);
    print "\nAvailable themes:\n";
    foreach($available_themes as $available_theme){
        $theme = basename($available_theme);
        if('templates' != $theme){
            if(DEFAULT_THEME == $theme){
                $inUse = ' (current theme)';
            } else {
                $inUse = '';
            }
            print $theme.$inUse."\n";
        }
    }
    print "\n";

    if(!prompt("You did not specify a theme on the command line. Would you like to enter one now?")){
        die("Program ended.\n");
    }
    do {
        $theme = textPrompt("Please enter the name of the theme you would like to (re)build.");
        print "You entered: $theme\n";
    } while(!prompt("Is this correct?"));
}

//look for theme folder
$theme_folder = $site_folder . '/web/themes/' . $theme;
if(file_exists($theme_folder)){
    print "Found theme folder $theme_folder\n";
} else {
    die("Could not find theme folder $theme_folder\n");
}


//read theme-config.xml
$theme_config_file = $theme_folder.'/theme-config.xml';
if(file_exists($theme_config_file)){
    print "Found theme config file $theme_config_file\n";
} else {
    die("Could not find theme config file $theme_config_file\n");
}
$theme_config_map =& new XMLMap($theme_config_file, 'Theme');


//look up template folder
$template_folder = $site_folder . '/web/themes/templates/' . $theme_config_map->attributes['template'];
if(file_exists($template_folder)){
    print "Found template folder $template_folder\n";
} else {
    die("Could not find template folder $template_folder\n");
}

//read template-config.xml
$template_config_file = $template_folder.'/template-config.xml';
$template_config_map =& new XMLMap($template_config_file, 'ThemeTemplate');

//merge both configs (so the template settings act as defaults, overridden by the theme settings)
$theme_config_map = MergeConfigs($theme_config_map, $template_config_map);

//print_r($theme_config_map); //check to see that the template content got merged correctly

$theme_tags = array();
$theme_values = array();

//populate $theme_values lookup table
$theme_value_element = $theme_config_map->selectFirstElement('ThemeValues');
if(count($theme_value_element) > 0){
    $theme_value_elements = $theme_value_element->selectElements('ThemeValue');
    if(count($theme_value_elements) > 0){
        foreach($theme_value_elements as $theme_value_element){
            if(strtolower(get_class($theme_value_element->c[0])) == 'characterdata'){
                $theme_values[$theme_value_element->name] = $theme_value_element->c[0]->content;
            } elseif('HSVColor' == $theme_value_element->c[0]->type) {
                $colorObj = $theme_value_element->c[0]->createObject(null);
                $theme_values[$theme_value_element->name] = $colorObj->getHex();
            } else {
                if(isset($theme_values[$theme_value_element->c[0]->name])){
                    $theme_values[$theme_value_element->name] = $theme_values[$theme_value_element->c[0]->name];
                } else {
                    die("Could not find a ThemeValue named {$theme_value_element->c[0]->name} referenced in ThemeValue {$theme_value_element->name}. Please check your files. Note that all values must be defined before they are referenced.\n");
                }
            }
        }
    }
}
print "theme_values\n";
print_r($theme_values);

$theme_tag_elements = $theme_config_map->selectElements('ThemeTag');
if(count($theme_tag_elements) > 0){
    foreach($theme_tag_elements as $theme_tag_element){
        if(count($theme_tag_element->c) == 0){
            $theme_tags['/**'.$theme_tag_element->name.'**/'] = '';
            continue;
        }
        if(strtolower(get_class($theme_tag_element->c[0])) == 'characterdata'){
            $theme_tags['/**'.$theme_tag_element->name.'**/'] = $theme_tag_element->c[0]->content;
        } else {
            if(!isset($theme_values[$theme_tag_element->c[0]->name])){
                trigger_error("The ThemeTag named '{$theme_tag_element->name}' references a ThemeValue named '{$theme_tag_element->c[0]->name}', which is not found in the theme-config.xml nor in the template-config.xml file.", E_USER_ERROR);
            }
            $theme_tags['/**'.$theme_tag_element->name.'**/'] = $theme_values[$theme_tag_element->c[0]->name];
        }
    }
}

print "theme_tags\n";
print_r($theme_tags);

$theme_tag_placeholders = array_keys($theme_tags);

//copy files from template folder, substituting values
$template_file_paths = glob($template_folder.'/*.*');

foreach($template_file_paths as $template_file_path){
    do {
        if(false !== strpos($template_file_path, '~')){
            break; //skip to next file
        }
        if(false !== strpos($template_file_path, '.xml')){
            break; //skip to next file
        }
        //read template file
        $template_file_content = file_get_contents($template_file_path);

        //substitute contents
        $theme_file_content = str_replace($theme_tag_placeholders, $theme_tags, $template_file_content);

        //write to theme folder
        $theme_file_name = basename($template_file_path);
        $theme_file_path = $theme_folder .'/'.$theme_file_name;

        WriteFile($theme_file_path, $theme_file_content);

    } while(false);
}


//preserve the theme tags in a file
$theme_tag_data = '';
foreach($theme_tags as $theme_tag_placeholder => $theme_tag_value){
    $theme_tag_data .= "$theme_tag_placeholder\t$theme_tag_value\n";
}
WriteFile($theme_folder.'/themetags.txt', $theme_tag_data);


if(!file_exists($theme_folder.'/img')){
    mkdir($theme_folder.'/img');
}


//copy un-matched image files in /img
$template_image_paths  = glob($template_folder.'/img/*.*');
foreach($template_image_paths as $template_image_path){
    do {
        $image_file_name = basename($template_image_path);
        $image_file_path = $theme_folder.'/img/'.$image_file_name;
        if(file_exists($image_file_path)){
            break; //skip to next file
        }
        print "copying file $image_file_path\n";
        if(!copy($template_image_path, $image_file_path)){
            die("Could not copy file $image_file_path\n");
        }
    } while(false);
}

print "Finished building the theme $theme!\n";


/**
 *  Function that simply writes the substituted themes files
 */
function WriteFile($theme_file_path, $theme_file_content)
{
    //print $theme_file_content;
    if(!$theme_file_handle = fopen($theme_file_path, 'w')){
        die("Cannot open file $theme_file_path for writing\n");
    }
    if (fwrite($theme_file_handle, $theme_file_content) === false) {
        die("Cannot write file $theme_file_path.\n");
    }
    fclose($theme_file_handle);
    print "Wrote $theme_file_path.\n";
}
?>