<?php
/**
 * Utility to make graph files
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
 * @version        SVN: $Revision: 1514 $
 * @last-modified  SVN: $Date: 2009-02-11 18:52:48 +0100 (Śr, 11 lut 2009) $
 */

$Project = '';
if(isset($_SERVER['argv']['1'])){
    $Project = $_SERVER['argv']['1'];
}

if(empty($Project)){
    $Project = 'active_agenda';
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


//get settings
include_once $config_file;

set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//require_once PEAR_PATH . '/DB.php' ;  //PEAR DB class

//utility functions
include_once INCLUDE_PATH . '/parse_util.php';


/**
 * Sets custom error handler
 */
set_error_handler('handleError');

$mdb2 = GetMDB2();

$sql = "SELECT ModuleID, Name FROM `mod` ORDER BY ModuleID";
$mdb2->loadModule('Extended', null, false); //in order to use getAssoc() below
$modules = $mdb2->getAssoc($sql);
mdb2ErrorCheck($modules);


/**
 * Defines execution state as 'non-generating command line'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 2);

$dotContent = "digraph submodules{\n";
/*$dotContent .= "    //concentrate=true;\n";
$dotContent .= "    ratio=\".25\";\n";
$dotContent .= "    size=\"30,10\";\n";
$dotContent .= "    page=\"8.5,11\";\n";
$dotContent .= "    margin=0.2;\n";*/
//$dotContent .= "    node [width=.5,height=.5,fontsize=10];\n";

foreach($modules as $module_id => $module_name){
    $dotContent .= "    $module_id [label=\"$module_name\\n$module_id\"];\n";
}

$dotPartFiles = glob(GENERATED_PATH.'/*/*_submodules.dot');

//modules to ignore because of the complexity they add (too many edges)
$ignored_modules = array('bpc', 'filr', 'gui', 'lrn', 'prta', 'prti', 'rsk');

print "examining module dot files: ";
if(count($dotPartFiles) > 0){
    foreach($dotPartFiles as $dotPartFile){
        print ".";
        $dotPartContent = file_get_contents($dotPartFile);

        $massagedContent = massageContent($dotPartContent, $ignored_modules);

        $dotContent .= $massagedContent."\n";
    }
}
print "\n";

$dotContent .= '    subgraph cluster_notes{';
$dotContent .= '        label="Ignored to reduce complexity:"';
$dotContent .= join(";\n", $ignored_modules).";\n";
$dotContent .= '    }';

$dotContent .= '}';

$smDotFile = GENERATED_PATH . "/submodules.dot";
if($fp = fopen($smDotFile, 'w')) {
    if(fwrite($fp, $dotContent)){
        print "Saved file $smDotFile\n";
    } else {
        die( "s2a: could not save to file $smDotFile. Please check file/folder permissions.\n" );
    }
    fclose($fp);
} else {
    die( "s2a: could not open file $smDotFile. Please check file/folder permissions.\n" );
}

print "\n";
print "reducing complexity:\n";
$rdDotFile = GENERATED_PATH . "/submodules-reduced.dot";
$cmd = "tred $smDotFile > $rdDotFile";
shellCommand($cmd);

print "\n";
print "allowing more flexible layout:\n";
$ufDotFile = GENERATED_PATH . "/submodules-reduced-unflatten.dot";
$cmd = "unflatten -l 5 $rdDotFile > $ufDotFile";
shellCommand($cmd);

print "\n";
print "building PostScript file:\n";
$psFile = GENERATED_PATH . "/submodules-reduced-unflatten.ps";
$cmd = "dot -Tps2 $ufDotFile -o $psFile";
shellCommand($cmd);

print "\n";
print "building SVG file:\n";
$svgFile = GENERATED_PATH . "/submodules-reduced-unflatten.svg";
$cmd = "dot -Tsvg $ufDotFile -o $svgFile";
shellCommand($cmd);

print "\n";
print "all done!\n";

//removes some very well-connected modules
function massageContent($content, $ignored_modules)
{
    $lines = split("\n", $content);
    $output = '';
    foreach($lines as $line){
        $ignore = false;
        foreach($ignored_modules as $ignored_module){
            if(false !== strpos($line, " $ignored_module ")){
//                print "skipping $ignored_module\n";
                $ignore = true;
            } else {
                if(false !== strpos($line, " $ignored_module;")){
//                    print "skipping $ignored_module\n";
                    $ignore = true;
                }
            }
        }
        if(!$ignore){
            $output .= $line;
        }
    }
    return $output;
}
?>