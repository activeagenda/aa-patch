<?php
/**
 * Creates downloadable data on the fly: XML, CSV or Excel
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

//this include contains the search class
include_once CLASSES_PATH . '/search.class.php';
include_once CLASSES_PATH . '/components.php';

//this causes session timeouts to display a message instead of redirecting to the login screen 
DEFINE('IS_POPUP', true);

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

$listFieldsFileName = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ListFields.gen";

//check for cached page for this module
if (!file_exists($listFieldsFileName)){
    trigger_error("Could not find list fields file '$listFieldsFileName'.", E_USER_ERROR);
}

include_once $listFieldsFileName; //returns $fieldHeaders, $fieldTypes, $listFields, $linkFields, $fieldAlign

//remove IsBestPractice
unset($fieldHeaders['IsBestPractice']);
unset($fieldTypes['IsBestPractice']);
unset($linkFields['IsBestPractice']);
unset($fieldAlign['IsBestPractice']);

$headers = array();
foreach($fieldHeaders as $fieldName => $fieldHeader){
    $fieldHeader = ShortPhrase($fieldHeader);
    $headers[] = $fieldHeader;
}
$headerCount=count( $headers );

$search = $_SESSION["Search_$ModuleID"];

if(!is_object($search)){
    trigger_error("An active search is required.", E_USER_ERROR);
}

if(!isset($_GET['type'])){
    trigger_error("Invalid request URL. A type is required.", E_USER_ERROR);
}

//check that there's a person selected
$fileTypeSelected = intval($_GET['type']);

$SQL = $search->getListSQL();
$SQL .= $User->getListFilterSQL($ModuleID, true);  //also checks permission

//execute SQL statement
$r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
dbErrorCheck($r);
$rowCount = count( $r )+1; 
$calcCounter = 0;
switch($fileTypeSelected){
case 1:
    //csv
    $saveAsName = $ModuleID.'_'.date( 'Y-m-d_H.i.s').'.utf8.csv';

    header("Content-Type: text/plain");
    header("Content-Disposition: attachment; filename=$saveAsName");
    print '"'.join('", "', $headers) .'"'. "\r\n";
    
	foreach($r as $row){
        foreach($row as $name => $value){
			$value = strip_tags($value);
			$value = html_entity_decode( $value );
			if( $fieldTypes[$name]  == 'float' or $fieldTypes[$name]  == 'money'){
				$value= str_replace('.', DECIMAL_SEPARATOR, $value ); 
			}
            $row[$name] = addslashes($value);
        }
		$row2print = array();
		foreach($fieldHeaders as $fieldName => $fieldHeader){
			$row2print[$fieldName] = $row[$fieldName];
		}
        print '"';
        print join('","', $row2print);
        print "\"\r\n";
		
		if( ++$calcCounter > 32000) { 
			break;
		}
    }

    break;
case 2:
    //xml
    $saveAsName = $ModuleID.'_'.date( 'Y-m-d_H.i.s').'.xml';

    header("Content-Type: text/xml");
    header("Content-Disposition: attachment; filename=$saveAsName");
	print "<?xml version='1.0' encoding='UTF-8'?>\n";
    print '<document module="'.$ModuleID.'" generated="'.date( 'Y-m-d H.i.s'). '">'."\n";
    if(count($r) > 0){
        foreach($r as $row){
            print "<record>\r\n";
            foreach($row as $name => $value){
				$value = strip_tags($value);
				$value = html_entity_decode( $value );				
				$value = htmlspecialchars( $value);				
                print "<$name>$value</$name>\r\n";
            }
            print "</record>\r\n";
			
			if( ++$calcCounter > 32000) { 
				break;
			}
        }
    }
    print "</document>\r\n";

    break;
case 3:
    //csv - semicolon, MS encoding
    //csv
    $saveAsName = $ModuleID.'_'.date( 'Y-m-d_H.i.s').'.excel2003.xml';

    header("Content-Type: text/xml");
    header("Content-Disposition: attachment; filename=$saveAsName");
	print'<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Version>11.9999</Version>
 </DocumentProperties>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>9405</WindowHeight>
  <WindowWidth>19200</WindowWidth>
  <WindowTopX>-30</WindowTopX>
  <WindowTopY>930</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Top" ss:Indent="1"/>
   <Borders/>
   <Font x:CharSet="238" ss:Size="12"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="s21">
   <Alignment ss:Horizontal="Center" ss:Vertical="Bottom"/>
  </Style>
  <Style ss:ID="s22">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font x:CharSet="238" x:Family="Swiss" ss:Size="11" ss:Color="#333333"
    ss:Bold="1"/>
   <Interior ss:Color="#CCFFFF" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s23" ss:Parent="Default">
   <NumberFormat ss:Format="Short Time"/>
  </Style>
  <Style ss:ID="s24" ss:Parent="Default">
   <NumberFormat ss:Format="Short Date"/>
  </Style>
  <Style ss:ID="s25" ss:Parent="Default">
   <NumberFormat ss:Format="General Date"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="Lista">
  <Names>
   <NamedRange ss:Name="Print_Titles" ss:RefersTo="=Lista!R1"/>
  </Names>
  <Table ss:ExpandedColumnCount="'.$headerCount.'" ss:ExpandedRowCount="'.$rowCount.'" x:FullColumns="1"
   x:FullRows="1" ss:DefaultColumnWidth="120" ss:DefaultRowHeight="15">
   <Column ss:StyleID="s21" ss:AutoFitWidth="1" ss:Width="39.75"/>
	';
	print ' <Row ss:Height="15.75">'."\n";
	foreach( $headers as $header ){
		print '   <Cell ss:StyleID="s22"><Data ss:Type="String">'.$header.'</Data><NamedCell
      ss:Name="Print_Titles"/>'."</Cell>\n";
	}
	print "</Row>\n";
    foreach($r as $row){
		print "<Row>\n";
		foreach($row as $name => $value){
			$value = strip_tags($value);
			$value = html_entity_decode( $value );			
			$row[$name] = $value;
        }
		$row2print = array();
		foreach($fieldHeaders as $fieldName => $fieldHeader){
			$row2print[$fieldName] = $row[$fieldName];
		}
		
        foreach($row2print as $name => $value){
			$xlsType = '';
			$xlsStyle = '';
			switch( $fieldTypes[$name] ){
				case 'float':
				case 'int':
				case 'money':
					$xlsType = 'Number';
					break;
				case 'date':
					$xlsType = 'DateTime';
					$value = $value.'T00:00:00.000';
					$xlsStyle = ' ss:StyleID="s24"';
					break;
				case 'time':
					$xlsType = 'DateTime';
					$value = '1899-12-31T'.$value.'.000';
					$xlsStyle = ' ss:StyleID="s23"';
					break;
				case 'datetime':
					/*$xlsType = 'DateTime';
					$value = str_replace( ' ', 'T', $value );
					$value = $value.'.000';
					$xlsStyle = ' ss:StyleID="s25"';
					break;*/
				default:
					$xlsType = 'String';
			}
			print '   <Cell'.$xlsStyle.'><Data ss:Type="'.$xlsType.'">'.$value."</Data></Cell>\n";
        }
		print "  </Row>\n"; 
		
		if( ++$calcCounter > 32000) { 
			break;
		}
    }
	print'</Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Layout x:Orientation="Landscape"/>
    <Header x:Data="&amp;LActive Agenda&amp;R&amp;P/&amp;N"/>
    <Footer x:Data="&amp;L&amp;F&amp;R&amp;D"/>
    <PageMargins x:Bottom="0.984251969" x:Left="0.78740157499999996"
     x:Right="0.78740157499999996" x:Top="0.984251969"/>
   </PageSetup>
   <Print>
    <ValidPrinterInfo/>
    <PaperSizeIndex>9</PaperSizeIndex>
    <HorizontalResolution>600</HorizontalResolution>
    <VerticalResolution>600</VerticalResolution>
    <Gridlines/>
   </Print>
   <Selected/>
   <FreezePanes/>
   <SplitHorizontal>1</SplitHorizontal>
   <TopRowBottomPane>1</TopRowBottomPane>
   <ActivePane>2</ActivePane>
   <Panes>
    <Pane>
     <Number>3</Number>
    </Pane>
    <Pane>
     <Number>2</Number>
     <ActiveRow>0</ActiveRow>
    </Pane>
   </Panes>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>';
    break;
default:
    trigger_error(gettext("Invalid request URL. Unknown file type requested."), E_USER_ERROR);
    break;
}
?>