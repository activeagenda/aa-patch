<?php
/**
 *  Classes for preparing data for report output
 *
 *  PHP version 5
 *
 *
 *  LICENSE NOTE:
 *
 *  Copyright  2003-2009 Active Agenda Inc., All Rights Reserved.
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
 * @version        SVN: $Revision: 1572 $
 * @last-modified  SVN: $Date: 2009-04-21 03:27:45 +0200 (Wt, 21 kwi 2009) $
 */



include_once CLASSES_PATH . '/components.php';


/**
 * methods and properties shared by the Report and SubReport classes
 */
class AbstractReport
{

var $moduleID;
var $name;  //internal name (for file names etc) - no spaces in name
var $title; //user-displayed title
var $hierarchyID; //ID in the hierarchy of reports and subreports
var $localKey;
var $fields = array();
var $orderByFields = array();
var $subReports = array();
var $rootReport;
var $singularRecordName;
var $distinct = false; //whether the SELECT statemenr includes DISTINCT


/**
 *  Removes some properties that aren't necessary after serializing
 */
function __sleep()
{
    //the properties to be saved when serializing
    $properties = get_object_vars($this);
    unset($properties['rootReport']);
    unset($properties['parentReport']);
    return array_keys($properties);
}


/**
 * returns the SQL statement for the report or subreport
 */
function buildSQL()
{
    $debug_prefix = debug_indent("AbstractReport->buildSQL() {$this->moduleID}:");

    global $SQLBaseModuleID;
    $SQLBaseModuleID = $this->moduleID;

    $selects = array();
    $joins = array();

    foreach($this->fields as $fieldName => $field){
        $selects[] = $field->makeSelectDef($this->moduleID);
        $joins = array_merge($joins, $field->makeJoinDef($this->moduleID));
    }
    $joins = SortJoins($joins);

    if($this->distinct){
        $SQL = "SELECT DISTINCT\n";
    } else {
        $SQL = "SELECT \n";
    }
    $SQL .= join(",\n", $selects);
    $SQL .= "\n";
    $SQL .= "FROM \n `{$this->moduleID}`\n";

    $joins = array_merge($joins, $this->buildParentJoin($this->moduleID));
    $where = '';
    if(isset($joins['where'])){
        $where = join("\nAND ", $joins['where']);
        unset($joins['where']);
    }
    foreach($joins as $alias => $def){
        $SQL .= "$def\n";
    }

    if(!empty($where)){
        $SQL .= "WHERE $where\n";
    }

    if(isset($this->groupByFields) && count($this->groupByFields) > 0){
        $SQL .= " GROUP BY " . join(', ', $this->groupByFields);
    }

    if(count($this->orderByFields) > 0){
        $SQL .= " ORDER BY ";
        $onFirst = true;
        foreach($this->orderByFields as $fieldName => $direction){
            if($onFirst){
                $SQL .= " $fieldName";
                $onFirst = false;
            } else {
                $SQL .= ",\n $fieldName";
            }
            if(!empty($direction)){
                $SQL .= ' '.$direction;
            }
        }
    }
    CheckSQL($SQL);
    $this->rootReport->SQLs[$this->hierarchyID] = $SQL;
    debug_unindent();
}


function buildParentJoin($localAlias)
{
    $SQL = "$localAlias._Deleted = 0 AND $localAlias.{$this->localKey} = '/**ReportRecordID**/'";

    return array('where' => array($localAlias => $SQL));
}

} //end class AbstractReport



/**
 * Represents a report document
 */
class Report extends AbstractReport
{

var $moduleName;
var $datasets = array();
var $SQLs = array();
var $reportLocations = array();
var $reportPage;
var $displayFormat;
var $isLoaded = false;
var $mode = 'list';
var $groupByFields = array();


/**
 *  Factory method
 */
function &Factory($element, $moduleID)
{
	global $rowCounter;
	
    trace("Instantiating Report {$element->name}");
    $object = new Report();
	
	$object->reportName = $element->getAttr('name');

    $object->moduleID = $element->getAttr('moduleID', true);

    $moduleInfo = GetModuleInfo($object->moduleID);
    $object->moduleName = $moduleInfo->getProperty('moduleName');

    $object->name = $element->name;

    $object->displayFormat = $element->getAttr('displayFormat');
    if(empty($object->displayFormat)){
        $object->displayFormat = 'html-linear';
    }

    $object->title = $element->getAttr('title');
    if(empty($object->title)){
        $object->title = $element->name;
    }

    $object->mode = $element->getAttr('mode');
    $object->distinct = ('yes' == strtolower($element->getAttr('distinct')));
	
	$object->maxRecords = $element->getAttr('maxRecords');
    if(empty($object->maxRecords)){
        $object->maxRecords = 1;
	}
	$object->rowCounter = $element->getAttr('rowCounter');	
	
	$object->fileExtension = $element->getAttr('fileExtension');
    if(empty($object->fileExtension)){
        $object->fileExtension = 'xml';
	}
	
    $object->hierarchyID = $object->moduleID;
    $object->rootReport =& $object;

    $module = GetModule($object->moduleID);
    $rowIDField = end($module->PKFields);
    $object->localKey = $rowIDField;

    $object->singularRecordName = $element->getAttr('singularRecordName');
    if(empty($object->singularRecordName)){
        $object->singularRecordName = $module->SingularRecordName;
    }

    //header (title) field
    if(!empty($element->attributes['headerField'])){
        $object->headerField = $element->getAttr('headerField');
        $field_element = new Element($object->headerField,'ReportField',array('invisible' => true));
        $object->fields[$object->headerField] = $field_element->createObject($object->moduleID);
    }

    //sets up SubReports so they have a reference BACK to the report
    foreach($element->c as $content){
        switch($content->type){
        case 'ReportLocation':
            switch($content->getAttr('level')){
            case 'Record':
                $object->reportLocations['Record'] = $content->getAttr('group');
                break;
            case 'List':
                $object->reportLocations['List'] = $content->getAttr('group');
                break;
            default:
                //ignores any other levels for now
                break;
            }
            break;
        case 'ReportField':
            $field = $content->createObject($object->moduleID);
            $object->fields[$content->name] = $field; 

            if('summarize' == $object->mode && 'groupby' == $field->summarize){
                $object->groupByFields[] = $field->name;
            }
            break;
        case 'OrderByField':
            $object->orderByFields[$content->name] = $content->getAttr('direction');
            break;
        case 'SubReport':
            $subReport = $content->createObjectWithRef($object->moduleID, null, $object);
            $subModuleID = $content->getAttr('moduleID', true);
            $object->subReports[$subModuleID] = $subReport;

            if(!isset($object->fields[$subReport->parentKey])){
                $field_element = new Element($subReport->parentKey, 'ReportField',array('invisible' => true));
                $object->fields[$subReport->parentKey] = $field_element->createObject($object->moduleID);
            }
            if(count($subReport->conditions) > 0){
                foreach($subReport->conditions as $conditionField => $conditionValue){
                    if(false !== strpos($conditionValue, '*')){
                        $conditionValueField = str_replace(array('*', '[', ']'), '', $conditionValue);
                        $field_element = new Element($conditionValueField, 'ReportField',array('invisible' => true));
                        $object->fields[$conditionValueField] = $field_element->createObject($object->moduleID);
                    }
                }
            }


            unset($subReport);
            break;
        case 'ReportPage':
            $object->reportPage = $content->createObjectWithRef($object->moduleID, null, $object);
            break;
        default:
            die("Unexpected {$content->type} content in Report element {$report->moduleID}-{$element->name}");
        }
    }

    $object->buildSQL();

    return $object;
}


function &getSubReportByHierarchyID($hierarchyID)
{
    $hierarchy = preg_split('/_/', $hierarchyID);

    //trims away the first element in the array which is the root report's moduleID
    $first = array_shift($hierarchy);

    $currentReport =& $this;
    trace(array_keys($currentReport->subReports), "subreport ids");
    foreach($hierarchy as $moduleID){
        if(isset($currentReport->subReports[$moduleID])){
            trace("getting subreport $moduleID");
            $currentReport =& $currentReport->subReports[$moduleID];
        } else {
            trigger_error("Could not find the subreport with hierarchy id '$hierarchyID'.", E_USER_ERROR);
        }
    }
    return $currentReport;
}


/**
 *  Populates the report's datasets property with data.
 */
function loadData($recordID)
{
    global $dbh;

    if(empty($recordID)){
        trace("This is a List level report.");
        if(in_array('List', array_keys($this->reportLocations))){
            global $ModuleID;
            $ModuleInfo = GetModuleInfo($ModuleID);
            $listPK = $ModuleInfo->getPKField();

            if(isset($_SESSION['Search_'.$ModuleID])){
                $search = $_SESSION['Search_'.$ModuleID];
            } else {

                $search = new Search(
                    $this->moduleID,
                    array($listPK)
                );
            }
            $searchSQL = $search->getCustomListSQL(array($listPK));
            $searchSQL = 'IN ('.$searchSQL.')';
        } else {
            trigger_error(gettext("This report is not designed to be displayed as a List report"), E_USER_ERROR);
        }

        foreach($this->SQLs as $hierarchyID => $SQL){
            $SQL = str_replace('= \'/**ReportRecordID**/\'', $searchSQL, $SQL.' LIMIT '.$this->maxRecords);
            $SQL = TranslateLocalDateSQLFormats($SQL);
            $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
            dbErrorCheck($result);
            $this->dataSets[$hierarchyID] = $result;
        }
    } else {
        trace("This is a Record level report.");

        foreach($this->SQLs as $hierarchyID => $SQL){
            $SQL = str_replace('/**ReportRecordID**/', $recordID, $SQL);
            $SQL = TranslateLocalDateSQLFormats($SQL);
            $result = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
            dbErrorCheck($result);
            $this->dataSets[$hierarchyID] = $result;
        }
    }
    $this->isLoaded = true;
} //end loadData()


/**
 *  Fills in the FDF form of a PDF file and sends the output to browser.
 *
 *  This requires the 'pdftk' command-line utility from accesspdf.com.
 */
function renderPDF($recordID)
{
    global $report_render_mode;
    $report_render_mode = 'pdf';

    $this->loadData($recordID);
    $dataPages = $this->reportPage->generateData($this->dataSets);

    trace($this->dataSets, "Data retrieved from database");
    trace($dataPages, "Data transformed for report");

    $pdfDoc = PDF_PATH .'/'. $this->reportPage->filename;

    header( 'content-disposition: attachment; filename="'.$this->reportPage->filename.'_'.date( 'Y-m-d_H.i.s').'.xdp"' );
   	header( 'Content-type: application/vnd.adobe.xdp+xml' );
	foreach($dataPages as $dataPageID => $dataPage){
		echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
		echo '<xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve">', "\n";
		echo '<f href="'.$pdfDoc.'"/>', "\n";
		echo "\t<fields>\n";
		foreach($dataPage as $fieldName => $fieldValue){
			echo "\t\t", '<field name="'.$fieldName.'">', "\n\t\t\t",'<value>'.$fieldValue.'</value>', "\n\t\t", '</field>', "\n";	
		}
		echo "\t</fields>\n</xfdf>\n";
	}   

} //end renderPDF()

/**
 *  Fills in the fiel format  Excel 2003 XML  Word 2003 XML and others like FreeMind and sends the output to browser.
  */
function renderMSXML($recordID)
{
    global $report_render_mode;
	global $rowCounter;
	if( !empty($this->rowCounter) ){
        $rowCounter = $this->rowCounter;
	}
    //$report_render_mode = 'xls';

    $this->loadData($recordID);
    trace($this->dataSets, "Data retrieved from database");
    trace($dataPages, "Data transformed for report");

    $msxmlHeader= MSXML_PATH .'/'. $this->reportName.'/header.xml';	
	$msxmlPreSnipet= MSXML_PATH .'/'. $this->reportName.'/'.$this->hierarchyID.'.pre.xml';	
	$msxmlSnipet= MSXML_PATH .'/'. $this->reportName.'/'.$this->hierarchyID.'.xml';
	if( !file_exists($msxmlSnipet) ){
        trigger_error("Cannot find source file $msxmlSnipet.", E_USER_ERROR);
    }
	$msxmlAfterSnipet= MSXML_PATH .'/'. $this->reportName.'/'.$this->hierarchyID.'.after.xml';
	$msxmlPostSnipet= MSXML_PATH .'/'. $this->reportName.'/'.$this->hierarchyID.'.post.xml';
	$msxmlFooter= MSXML_PATH .'/'. $this->reportName.'/footer.xml';	

    header( 'content-disposition: attachment; filename="'.$this->reportName.'_'.date( 'Y-m-d_H.i.s').'.'.$this->fileExtension.'"' );
   	header( 'Content-type: text/xml' );
	
	if( file_exists($msxmlHeader) ){
        echo file_get_contents($msxmlHeader), "\n";	
    } 	
	
	if( !file_exists($msxmlPreSnipet) ){
		$rowsPreSnipet = '';
    }else{
		$rowsPreSnipet = file_get_contents($msxmlPreSnipet)."\n";
	}
	$rowsSnipet = file_get_contents($msxmlSnipet);
	if( !file_exists($msxmlAfterSnipet) ){
		$rowsAfterSnipet = '';
    }else{
		$rowsAfterSnipet = file_get_contents($msxmlAfterSnipet)."\n";
	}
	if( !file_exists($msxmlPostSnipet) ){
		$rowsPostSnipet = '';
    }else{
		$rowsPostSnipet = file_get_contents($msxmlPostSnipet)."\n";
	}
	
	$content = '';
	if(count($this->dataSets[$this->hierarchyID]) > 0){
        foreach($this->dataSets[$this->hierarchyID] as $row){ 
			$content .=  $rowsPreSnipet;
			$rowsContent = $rowsSnipet;
            foreach($this->fields as $fieldName => $field){              
				$fieldValue = $field->simpleRender($row);
                $fieldValue = strip_tags( $fieldValue );
				$fieldValue = html_entity_decode( $fieldValue );				
				$fieldValue = htmlspecialchars( $fieldValue );
				$rowsContent = str_replace($fieldName, $fieldValue, $rowsContent);
            }
			//Enviroment variable like  _HOSTNAME, _ROWCOUNTER
			$rowsContent = str_replace('_HOSTNAME', 'http://'.SERVER_EXT_ADRR, $rowsContent);
			$rowsContent = str_replace('_ROWCOUNTER', $rowCounter++, $rowsContent);
			
			$content .=  $rowsContent."\n";
			$content .=  $rowsAfterSnipet;
			
            if(count($this->subReports)>0){
                foreach($this->subReports as $subReport){
                    $subParentID = $row[$subReport->parentKey];
                    $content .= $subReport->renderMSXML( $subParentID, $this );
                }
            }
			$content .=  $rowsPostSnipet;
        } 		
	echo $content;
	if( file_exists($msxmlFooter) ){
        echo file_get_contents($msxmlFooter);	
    }
	} //renderMSXML()
}
/**
 * returns the report's data (including subreports) as HTML
 */
function renderHTML($recordID)
{
    $this->loadData($recordID);
    trace($this->dataSets, "Data retrieved from database");

    $content = '<h1>'.gettext($this->title).'</h1>';
    if(count($this->dataSets[$this->hierarchyID]) > 0){
        foreach($this->dataSets[$this->hierarchyID] as $row){
            $content .= '<div class="reportrecord">';
            if(!empty($this->headerField)){
                $content .= '<h2>'. $row[$this->headerField].'</h2>';
            } else {
                $content .= '<h2>'.gettext($this->singularRecordName).'</h2>';
            }
            $content .= '<table>';
            foreach($this->fields as $fieldName => $field){
                // Bug wit xxID field!
				if($field->isVisible()){
                    $content .= '<tr>';
                    $content .= '<td>'.gettext($field->phrase).':</td><td>'.$field->simpleRender($row).'</td>';
                    $content .= '</tr>';
                }
            }
            $content .= '</table>';
            if(count($this->subReports)>0){
                foreach($this->subReports as $subReport){
                    $subParentID = $row[$subReport->parentKey];
                    $content .= $subReport->renderHTML($subParentID, $this);
                }
            }
            $content .= '</div>';
        }
    } else {
        $content = '<p>' . gettext("There is no data to be displayed.") . '</p>';
    }
    $content = '<div class="report">'.$content.'</div>';
    return $content;
}


/**
 * returns the report's data (including subreports) as XML
 */
function renderXML($recordID)
{
    $this->loadData($recordID);

    $content = '<S2aData>
    <Records moduleID="'.$this->moduleID.'">';

    foreach($this->dataSets[$this->hierarchyID] as $row){
        $globalIDInsert = '';
        if(!empty($row['_GlobalID'])){
            $globalIDInsert = "globalID=\"{$row['_GlobalID']}\"";
        }
        $content .= "<Record $globalIDInsert>";

        foreach($this->fields as $fieldName => $field){
            $content .= "<RecordValue fieldName=\"$fieldName\" value=\"".htmlspecialchars($field->viewRender($row))."\" />";
        }

        if(count($this->subReports)>0){
            foreach($this->subReports as $subReport){
                $subParentID = $row[$subReport->parentKey];
                $content .= $subReport->renderXML($subParentID, $this);
            }
        }
        $content .= '</Record>';
    }
    $content .= '</Records> 
</S2aData>';
    return $content;
}

}  //end class Report



/**
 * Represents submodule data in a report document
 */
class SubReport extends AbstractReport
{

var $parentKey;
var $conditions = array();
var $parentReport;
var $headerField;


/**
 *  Factory method
 */
function &Factory($element, $moduleID, &$callerRef){
    $debug_prefix = debug_indent("SubReport::Factory() {$moduleID}_{$element->attributes['moduleID']}:");
    trace($debug_prefix);
    $object = new SubReport();

    $object->moduleID = $element->getAttr('moduleID', true);

    $object->name = $element->name;
    $object->title = $element->getAttr('title');
    if(empty($object->title)){
        $object->title = $element->name;
    }
    $object->distinct = ('yes' == strtolower($element->getAttr('distinct')));

    $object->parentReport =& $callerRef;
    $object->rootReport =& $callerRef->rootReport;

    $object->hierarchyID = $callerRef->hierarchyID . '_' . $object->moduleID;

    $parentModule = GetModule($moduleID);
    $currentModule = $parentModule->getSubModule($object->moduleID);
    if(empty($currentModule)){
        print "$debug_prefix Submodule {$object->moduleID} not found in $moduleID ".get_class($parentModule)." object\n";

        switch($object->moduleID){
        case 'ntf':
        case 'att':
        case 'cos':
        case 'lnk':
		case 'rmd':
        case 'nts':
            $currentModule =& GetModule($object->moduleID);
            break;
        default:
            break;
        }
    }

    //loads localKey, parentKey and conditions
    $object->conditions = $currentModule->conditions;
    if(empty($element->attributes['parentKey'])){
        $object->localKey = $currentModule->localKey;
        $object->parentKey = $currentModule->parentKey;
    } else {
        $object->localKey = $element->getAttr('localKey');
        $object->parentKey = $element->getAttr('parentKey');
        $condition_elements = $element->selectElements('SubReportCondition');
        if(count($condition_elements)>0){
            foreach($condition_elements as $condition_element){
                $object->conditions[$condition_element->getAttr('field', true)] = $condition_element->getAttr('value', true);
            }
        }
    }
    //we make the localkey/parentkey join into a condition as well
    $object->conditions[$object->localKey] = '[*'.$object->parentKey.'*]';

    $object->singularRecordName = $element->getAttr('singularRecordName');
    if(empty($object->singularRecordName)){
        $object->singularRecordName = $currentModule->SingularRecordName;
    }

    //ensure the localKey is included
    if(!isset($object->fields[$object->localKey])){
        $field_element = new Element($object->localKey,'ReportField',array('invisible' => true));
        $object->fields[$object->localKey] = $field_element->createObject($object->moduleID);
    }

    //header (title) field
    if(!empty($element->attributes['headerField'])){
        $object->headerField = $element->getAttr('headerField');
        $field_element = new Element($object->headerField,'ReportField',array('invisible' => true));
        $object->fields[$object->headerField] = $field_element->createObject($object->moduleID);
    }

    //loads report fields and subReports
    foreach($element->c as $content){
        switch($content->type){
        case 'ReportField':
            //$object->fields[$content->name] = 'ReportField';
            $object->fields[$content->name] = $content->createObject($object->moduleID);
            break;
        case 'OrderByField':
            $object->orderByFields[$content->name] = $content->getAttr('direction');
            break;
        case 'SubReport':
            $subReport =& $content->createObjectWithRef($object->moduleID, null, $object);
            $subModuleID = $content->getAttr('moduleID');
            $object->subReports[$subModuleID] = $subReport;

            //adds parent key field to root report as an invisible field if it's not there.
            if(!isset($object->fields[$subReport->parentKey])){
                $field_element = new Element($subReport->parentKey,'ReportField',array('invisible' => true));
                $object->fields[$subReport->parentKey] = $field_element->createObject($object->moduleID);
            }

            if(count($subReport->conditions) > 0){
                foreach($subReport->conditions as $conditionField => $conditionValue){
                    if(false !== strpos($conditionValue, '*')){
                        $conditionValueField = str_replace(array('*', '[', ']'), '', $conditionValue);
                        $field_element = new Element($conditionValueField, 'ReportField',array('invisible' => true));
                        $object->fields[$conditionValueField] = $field_element->createObject($object->moduleID);
                    }
                }
            }

            unset($subReport);
            break;
        case 'SubReportCondition':
            //skip -- already handled
            break;
        default:
            die("Unexpected {$content->type} content in Report element {$object->moduleID}-{$element->name}");
        }
    }

    $object->buildSQL();

    debug_unindent();
    return $object;
}


/**
 *  Builds the SQL snip that contains the joins with the parent report(s)
 */
function buildParentJoin($localAlias){

    $parentModuleID = $this->parentReport->moduleID;

    $SQL = "INNER JOIN `{$parentModuleID}` AS {$parentModuleID}_p
    ON (`$localAlias`._Deleted = 0 ";

    $joins = array();
    if(count($this->conditions) > 0){
        foreach($this->conditions as $conditionFieldName => $conditionValue){
            $conditionField = GetModuleField($this->moduleID, $conditionFieldName);
            $qualName = $conditionField->getQualifiedName($localAlias);
            $joins = array_merge($joins, $conditionField->makeJoinDef($localAlias));

            if(FALSE === strpos($conditionValue, '*')){
                $SQL .= "\nAND '$conditionValue' = $qualName";
            } else {
                //assume the field is a parent module field
                if(preg_match('/\[\*([\w]+)\*\]/', $conditionValue, $matches)){
                    $parentFieldName = $matches[1];
                    $parentModuleField = GetModuleField($parentModuleID, $parentFieldName);
                    $parentQualName = $parentModuleField->getQualifiedName($parentModuleID.'_p');
                    $joins = array_merge($joins, $parentModuleField->makeJoinDef($parentModuleID.'_p'));
                    $SQL .= "\nAND $parentQualName = $qualName";
                }
            }
        }
    }
    $SQL .= ")\n";

    $joins[$localAlias] = $SQL;

    //ask parent for "grandParent join"
    $joins = array_merge($joins, $this->parentReport->buildParentJoin($parentModuleID.'_p'));

    return $joins;
}

//START
function renderMSXML($recordID, &$rootReport)
{
	global $rowCounter;
	
	$msxmlPreSnipet= MSXML_PATH .'/'.$rootReport->reportName.'/'.$this->hierarchyID.'.pre.xml';	
	$msxmlSnipet= MSXML_PATH .'/'.$rootReport->reportName.'/'.$this->hierarchyID.'.xml';
	$msxmlAfterSnipet= MSXML_PATH .'/'. $this->reportName.'/'.$this->hierarchyID.'.after.xml';

	if(!file_exists($msxmlSnipet)){
        trigger_error("Cannot find source file $msxmlSnipet.", E_USER_ERROR);
	}
	$msxmlPostSnipet= MSXML_PATH .'/'.$rootReport->reportName.'/'.$this->hierarchyID.'.post.xml';
	if(!file_exists($msxmlPreSnipet)){
		$rowsPreSnipet = '';
    }else{
		$rowsPreSnipet = file_get_contents($msxmlPreSnipet);
	}
	$rowsSnipet = file_get_contents($msxmlSnipet);
	if(!file_exists($msxmlAfterSnipet)){
		$rowsAfterSnipet = '';
    }else{
		$rowsAfterSnipet = file_get_contents($msxmlAfterSnipet);
	}

	if(!file_exists($msxmlPostSnipet)){
		$rowsPostSnipet = '';
    }else{
		$rowsPostSnipet = file_get_contents($msxmlPostSnipet);
	}
	
	$content = '';
    if(count($rootReport->dataSets[$this->hierarchyID]) > 0){
        foreach($rootReport->dataSets[$this->hierarchyID] as $row){
            if($row[$this->localKey] == $recordID){
				$content .=  $rowsPreSnipet."\n";
				$rowsContent = $rowsSnipet;
                foreach($this->fields as $fieldName => $field){                   
					$fieldValue = $field->simpleRender($row);
					$fieldValue = strip_tags( $fieldValue );
					$fieldValue = html_entity_decode( $fieldValue );				
					$fieldValue = htmlspecialchars( $fieldValue );
					$rowsContent = str_replace( $fieldName, $fieldValue, $rowsContent );					
                } 
				// Enviromental variables:
				$rowsContent = str_replace('_HOSTNAME', 'http://'.SERVER_EXT_ADRR, $rowsContent);
				$rowsContent = str_replace('_ROWCOUNTER', $rowCounter++, $rowsContent);
				
				$content .=  $rowsContent."\n";
				$content .=  $rowsAfterSnipet."\n";	
				
                if(count($this->subReports)>0){
                    foreach($this->subReports as $subReport){
                        $subParentID = $row[$subReport->parentKey];
                        $content .= $subReport->renderMSXML( $subParentID, $rootReport );
                    }
                }
				$content .=  $rowsPostSnipet."\n";
				
            }
        }
    }
    return $content;
}
//END
/**
 * returns the subreport's data (including subreports) as HTML
 */
function renderHTML($recordID, &$rootReport)
{
    $content = '';
    if(count($rootReport->dataSets[$this->hierarchyID]) > 0){
        foreach($rootReport->dataSets[$this->hierarchyID] as $row){
            if($row[$this->localKey] == $recordID){
                //$object->headerField
                $content .= '<div class="subreport">';
                if(!empty($this->headerField)){
                    $content .= '<div class="reporttitle">['.gettext($this->singularRecordName).']</div>';
                    //$content .= '<h2>'.$this->singularRecordName .': '. $row[$this->headerField].'</h2>';
                    $content .= '<h2>'.$row[$this->headerField].'</h2>';
                } else {
                    $content .= '<h2>'.gettext($this->singularRecordName).'</h2>';
                }

                $content .= '<table>';
                foreach($this->fields as $fieldName => $field){
                    // Bug with the _XxxID field?
					if($field->isVisible()){
                        $content .= '<tr>';
                        $content .= '<td>'.gettext($field->phrase).':</td><td>'.$field->simpleRender($row).'</td>';
                        $content .= '</tr>';
                    }
                }
                $content .= '</table>';
                if(count($this->subReports)>0){
                    foreach($this->subReports as $subReport){
                        $subParentID = $row[$subReport->parentKey];
                        $content .= $subReport->renderHTML($subParentID, $rootReport);
                    }
                }
                $content .= '</div>';
            }
        }
    }

    return $content;
}


/**
 *  Returns the subreport's data as XML
 */
function renderXML($recordID, &$rootReport)
{
    $content = '';

    foreach($rootReport->dataSets[$this->hierarchyID] as $row){
        $content .= '<Records moduleID="'.$this->moduleID.'">';
        if($row[$this->localKey] == $recordID){
            $globalIDInsert = '';
            if(!empty($row['_GlobalID'])){
                $globalIDInsert = "globalID=\"{$row['_GlobalID']}\"";
            }
            $content .= "<Record $globalIDInsert>";
            foreach($this->fields as $fieldName => $field){
                $content .= "<RecordValue fieldName=\"$fieldName\" value=\"".htmlspecialchars($field->viewRender($row))."\" />";
            }

            if(count($this->subReports)>0){
                foreach($this->subReports as $subReport){
                    $subParentID = $row[$subReport->parentKey];
                    $content .= $subReport->renderXML($subParentID, $rootReport);
                }
            }
            $content .= '</Record>';
        }
        $content .= '</Records>';
    }

    return $content;
}

} //end class SubReport



/**
 * Custom field class for report-viewable fields
 */
class ReportField extends ViewField
{

var $moduleFieldName;
var $summarize; //groupby, sum, etc...
var $invisible = false; //whether to display it in HTML reports
var $transformation;
var $dataType;
var $displayDecimals;
var $roundingMethod;
var $displayFormat;

/**
 *  Factory method
 */
function Factory(&$element, $moduleID)
{
    return new ReportField($element, $moduleID);
}


/**
 *  Constructor
 */
function ReportField(&$element, $moduleID)
{
    $this->name = $element->name;
    $this->moduleID = $moduleID;

    $this->displayDecimals = $element->getAttr('displayDecimals');
    $this->roundingMethod  = $element->getAttr('roundingMethod');
    $this->displayFormat  = $element->getAttr('displayFormat');
    $this->dataType = $element->getAttr('type');

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            if('Transformation' == $sub_element->type){
                $this->transformation = $sub_element->createObject($this->moduleID);
            }
        }

        $this->phrase = $element->getAttr('phrase');
    } else {

        $this->moduleFieldName = $element->getAttr('moduleFieldName');
        if(empty($this->moduleFieldName)){
            $this->moduleFieldName = $this->name;
        }

        $mf = GetModuleField($moduleID, $this->moduleFieldName);

        $this->phrase = $element->getAttr('phrase');
        if(empty($this->phrase)){
            $this->phrase = ShortPhrase($mf->phrase);
        }

        if(empty($this->dataType)){
            $this->dataType = $mf->dataType;
        }

        if(empty($this->displayFormat)){
            $this->displayFormat = $mf->displayFormat;
        }
    }

    $this->summarize = $element->getAttr('summarize');
    if(!empty($element->attributes['invisible'])){
        if(
            true === $element->getAttr('invisible')
            || 'yes' == strtolower($element->getAttr('invisible'))
        ){
            $this->invisible = true;
        }
    }

} //end ReportField() - constructor


/**
 *  Returns the field's SQL SELECT expression, using the corresponding ModuleField
 */
function makeSelectDef($moduleID)
{
    if(empty($this->transformation)){
        $mf = GetModuleField($this->moduleID, $this->moduleFieldName);
        $select = $mf->makeSelectDef($moduleID, false);
    } else {
        $select = $this->transformation->makeSelectDef($moduleID);
    }

    if(!empty($this->summarize)){
        switch($this->summarize){
        case 'groupby':
            //handled separately
            break;
        case 'sum':
            $select = "SUM($select)";
            break;
        default:
            trigger_error("Unknown summarization function '{$this->summarize}'.");
            break;
        }
    }

    $select = $select . ' AS ' . $this->name;
    return $select;
}


/**
 *  Returns the field's SQL join expression, using the corresponding ModuleField
 */
function makeJoinDef($moduleID)
{
    if(empty($this->transformation)){
        $mf = GetModuleField($this->moduleID, $this->moduleFieldName);
        return $mf->makeJoinDef($moduleID);
    } else {
        return $this->transformation->makeJoinDef($moduleID);
    }
}

/**
 *  Returns whether the field should be displayed (in HTML) or not
 */
function isVisible()
{
    return !$this->invisible;
}

} //end ReportField



/**
 * Class for formatting report pages (a way of mapping DB fields to PDF page fields)
 */
class ReportPage
{
var $rootReport;
var $pageFields = array(); //page fields not attached to a line
var $pageLines = array();      //lines contain page fields
var $pageFieldGroups = array();
var $pageSummaryFields = array();
var $pageBreakFields = array();
var $pageMetaFields = array();
var $filename;
var $subReportID; //to be retired
var $subReportRefs = array();
var $offsets = array();
var $subReportLocalKey;//to be retired
var $subReportParentKey;//to be retired


/**
 *  Factory method
 */
function &Factory(&$element, $moduleID, &$callerRef)
{
    return new ReportPage($element, $moduleID, $callerRef);
}


/**
 *  Constructor
 */
function ReportPage(&$element, $moduleID, &$callerRef)
{
    $this->rootReport =& $callerRef;
    $this->filename = $element->getAttr('filename', true);

    foreach($element->c as $sub_element){
        switch($sub_element->type){
        case 'PageField':
            $object = $sub_element->createObjectWithRef($moduleID, null, $this->rootReport);

            $this->pageFields[$sub_element->getAttr('name', true)] = $object;
            if($object->pageBreak){
                $this->pageBreakFields[] = $object;
            }
            break;
        case 'PageSummaryField':
            $this->pageSummaryFields[$sub_element->getAttr('name', true)] = $sub_element->createObject($moduleID);
            break;
        case 'PageMetaField':
            $this->pageMetaFields[$sub_element->getAttr('name', true)] = $sub_element->getAttr('type');
            break;
        case 'PageLines':

            if(count($this->rootReport->subReports) > 0){
                $subReportID = $sub_element->getAttr('subReportID');
                $subReport = $this->rootReport->subReports[$subReportID];
                $hierKey = $moduleID .'_'. $subReportID;
                $this->subReportRefs[$hierKey] = array(
                    'parentKey' => $subReport->parentKey,
                    'localKey' => $subReport->localKey
                );
            } else {
                $hierKey = $moduleID;
            }

            foreach($sub_element->c as $line_element){
                foreach($line_element->c as $field_element){
                    $this->pageLines[$hierKey][$line_element->getAttr('id')][] = $field_element->createObjectWithRef($hierKey, null, $this->rootReport);
                }
            }
            break;
        case 'PageFieldGroup':
            $subReport = $this->rootReport->subReports[$sub_element->getAttr('subReportID')];
            $sub_element->attributes['localKey'] = $subReport->localKey;
            $sub_element->attributes['parentKey'] = $subReport->parentKey;
            $fieldGroup = $sub_element->createObjectWithRef($moduleID, null, $callerRef);
            $this->pageFieldGroups[] = $fieldGroup;
            break;
        default:
            break;
        }
    }
}


/**
 *  Returns the supplied data sets transformed into paged results useful for the PDF forms
 */
function generateData(&$datasets)
{

    $dataPages = array();
    $continue = true;
    $moduleID = $this->rootReport->moduleID;

    $this->offsets['_main_'] = 0;

    if(count($this->subReportRefs) > 0){
        foreach($this->subReportRefs as $subReportID => $subReportRef){
            $this->offsets[$subReportID] = 0;
        }
    }

    while($continue) {
        $result = $this->generateDataPage($datasets);
        list($continue, $dataPage) = $result;

        if($continue){
            $dataPages[] = $dataPage;
        } else {
            break;
        }
    }

    if(count($this->pageMetaFields) > 0){
        foreach($this->pageMetaFields as $pageMetaField => $type){
            switch($type){
            case 'current_page_nbr':
                foreach($dataPages as $dataPageID => $dataPage){
                    $dataPage[$pageMetaField] = $dataPageID+1; //start page number at 1
                    $dataPages[$dataPageID] = $dataPage;
                }
                break;
            case 'total_nbr_pages':
                $pageCount = count($dataPages);
                foreach($dataPages as $dataPageID => $dataPage){
                    $dataPage[$pageMetaField] = $pageCount;
                    $dataPages[$dataPageID] = $dataPage;
                }
                break;
            case 'module_name':
                foreach($dataPages as $dataPageID => $dataPage){
                    $dataPage[$pageMetaField] = $this->rootReport->moduleName;
                    $dataPages[$dataPageID] = $dataPage;
                }
                break;
            default:

                break;
            }
        }
    }

    return $dataPages;

} //end generateData()



/**
 *  returns one page of data mapped to the page fields (fields in the PDF document)
 */
function generateDataPage(&$datasets)
{

    $pageData = array();
    $moduleID = $this->rootReport->moduleID;
    $rootOffset =& $this->offsets['_main_'];

    if(count($datasets[$moduleID]) <= $rootOffset){
        return array(false, array());
    }

    $breakPage = false;
    $incrementRootOffset = true;

    //populates the direct page fields
    foreach($this->pageFields as $pageField){
        $pageData[$pageField->name] = $pageField->getValue($datasets[$moduleID][$rootOffset]);
    }

    if(count($this->subReportRefs) > 0){
        $hasSubReport = true; //so pageLines are for the subreport data
    } else {
        $hasSubReport = false; //so pageLines are for the main data
    }

    //PageLines is a way to map resultset rows against multiple fields in a page
    if(count($this->pageLines) > 0){
        if($hasSubReport){
            $parentDataRow = $datasets[$moduleID][$rootOffset];
            foreach($this->pageLines as $hierKey => $pageLines){
                $subDataset = $datasets[$hierKey];
                $parentKeyName = $this->subReportRefs[$hierKey]['parentKey'];
                $parentKeyValue = $parentDataRow[$parentKeyName];
                $subOffset =& $this->offsets[$hierKey];

                $pageLineIDs = array_keys($pageLines);
                $lastPageLineIx = end($pageLineIDs);
                $pageLineIx = reset($pageLineIDs);
                $onFirstRow = true;

                //get lines in the $subDataset that match the $parentKeyValue, starting with the correct offset
                while(
                    isset($subDataset[$subOffset]) &&
                    ($subDataset[$subOffset][$this->subReportRefs[$hierKey]['localKey']] == $parentKeyValue)
                ){
                    if($onFirstRow){
                        $onFirstRow = false;
                        $this->setPageBreakFields($subDataset[$subOffset]);
                    } else {
                        if($this->checkPageBreak($subDataset[$subOffset])){
                            break;
                        }
                    }

                    foreach($pageLines[$pageLineIx] as $pageFieldIx => $pageField){
                        //pageField returns an array if the dataSet value is longer than allowed
                        $fieldValue = $pageField->getValue($subDataset[$subOffset]);
                        if(is_array($fieldValue)){

                            //populate each subsequent field in same column with a line of $fieldValue:
                            foreach($fieldValue as $fieldValueLine){
                                $pageField = $pageLines[$pageLineIx][$pageFieldIx];
                                $pageFieldName = $pageField->name;
                                $pageData[$pageFieldName] = $fieldValueLine;
                            }
                        } else {
                            $pageData[$pageField->name] = $fieldValue;
                        } 
                    }

                    $pageLineIx = next($pageLineIDs);
                    $subOffset++;

                    if(false === $pageLineIx){
                        //mark that this subreport needs to trigger a page break
                        $breakPage = true;
                        $incrementRootOffset = false;
                        break; //no need to keep looping, next page should continue from here
                    }
                }
                unset($subOffset); //unlinks reference to $this->offsets[$hierKey]
            }
        } else {
            //same thing for the case where pageLines belong to the main module (or rewrite to combine with above?)
            $pageLines =& $this->pageLines[$moduleID];
            $dataset = $datasets[$moduleID];
            $pageLineIDs = array_keys($pageLines);
            $lastPageLineIx = end($pageLineIDs);
            $pageLineIx = reset($pageLineIDs);
            $onFirstRow = true;

            //get lines in the $subDataset that match the $parentKeyValue, starting with the correct offset
            while(isset($dataset[$rootOffset])){
                if($onFirstRow){
                    $onFirstRow = false;
                    $this->setPageBreakFields($dataset[$rootOffset]);
                } else {
                    if($this->checkPageBreak($dataset[$rootOffset])){
                        break;
                    }
                }
                foreach($pageLines[$pageLineIx] as $pageFieldIx => $pageField){
                    //pageField returns an array if the dataSet value is longer than allowed
                    $fieldValue = $pageField->getValue($dataset[$rootOffset]);
                    if(is_array($fieldValue)){

                        //populate each subsequent field in same column with a line of $fieldValue:
                        foreach($fieldValue as $fieldValueLine){
                            $pageField = $pageLines[$pageLineIx][$pageFieldIx];
                            $pageFieldName = $pageField->name;
                            $pageData[$pageFieldName] = $fieldValueLine;
                            $pageLineIx = next($pageLineIDs);

                            if(!$pageLineIx){
                                //mark that this needs to trigger a page break

                            }
                        }
                    } else {
                        $pageData[$pageField->name] = $fieldValue;
                    }
                }

                $pageLineIx = next($pageLineIDs);
                $rootOffset++;
                if(false === $pageLineIx){
                    break; //no need to keep looping, next page should continue from here
                }

            }
            $incrementRootOffset = false;
        }
    } else {

        //PageFieldGroups only supported when there are no PageLines
        if(count($this->pageFieldGroups) > 0){
            foreach($this->pageFieldGroups as $pageFieldGroup){
                $pageData = array_merge($pageData, $pageFieldGroup->populatePage($datasets, $rootOffset));
            }
        }
    }

    if(count($this->pageSummaryFields) > 0){
        foreach($this->pageSummaryFields as $pageSummaryField){
            $pageData[$pageSummaryField->name] = $pageSummaryField->getValue($pageData);
        }
    }

    if($incrementRootOffset){
        $rootOffset++;
    }

    return array(true, $pageData);
} //end generateDataPage


/**
 *  Initializes the pageBreakFields
 */
function setPageBreakFields(&$dataRow)
{
    if(count($this->pageBreakFields) > 0){
        foreach($this->pageBreakFields as $pageBreakFieldID => $pageBreakField){
            $pageBreakField->startValue = $pageBreakField->getValue($dataRow);
            $this->pageBreakFields[$pageBreakFieldID] = $pageBreakField;
        }
    }
} //end setPageBreakFields


/**
 *  Returns true if any set pageBreakFields were changed
 */
function checkPageBreak(&$dataRow)
{
    if(count($this->pageBreakFields) > 0){
        foreach($this->pageBreakFields as $pageBreakField){
            if($pageBreakField->checkPageBreak($dataRow)){
                return true;
            }
        }
        return false;
    } else {
        return false;
    }
} //end checkPageBreak

} //end class ReportPage



class PageFieldGroup
{
var $pageFields = array();
var $subReportID;
var $parentModuleID;
var $localKey;
var $parentKey;

function &Factory(&$element, $moduleID, &$rootReport)
{
    $object = new PageFieldGroup();
    $object->subReportID = $element->getAttr('subReportID');
    $object->parentModuleID = $moduleID;
    $object->localKey = $element->getAttr('localKey');
    $object->parentKey = $element->getAttr('parentKey');

    if(count($element->c) > 0){
    foreach($element->c as $sub_element){
        $object->pageFields[$sub_element->getAttr('name', true)] = $sub_element->createObjectWithRef($moduleID, null, $rootReport);
    }
    } else {
        trigger_error("PageFieldGroup must have some fields", E_USER_ERROR);
    }

    return $object;
}

function populatePage(&$datasets, $offset)
{
    $groupData = array();

    $hierKey = $this->parentModuleID .'_'. $this->subReportID;
    $parentReportData = $datasets[$this->parentModuleID];
    $subReportData = $datasets[$hierKey];

    $parentKeyValue = $parentReportData[$offset][$this->parentKey];

    //find the correct subReportData row:
    $found = false;
    foreach($subReportData as $subReportDataRow){
        if($subReportDataRow[$this->localKey] == $parentKeyValue){
            $found = true;
            break;
        }
    }

    if($found){
        //print debug_r($subReportDataRow, "subReportDataRow");

        //populate each field in group
        foreach($this->pageFields as $pageField){
            $groupData[$pageField->name] = $pageField->getValue($subReportDataRow);
        }
    }

    //print debug_r($groupData, "groupData");

    return $groupData;
}
}  //end class PageFieldGroup




class PageField
{
var $name;
var $reportField;
var $maxLength;
var $overflowAction;
var $conditionValue;
var $trueResult;
var $falseResult;
var $format;
var $pageBreak;
var $replaceEmptyValue;

var $displayDecimals;
var $roundingMethod;
var $displayFormat;
var $viewRender = false;

function &Factory(&$element, $hierarchyID, &$rootReport)
{
    $object = new PageField();
    $object->name = $element->getAttr('name', true);
    $reportFieldName = $element->getAttr('reportField');
    $object->maxLength = $element->getAttr('maxLength');
    $object->overflowAction = $element->getAttr('overflowAction');
    $object->format = $element->getAttr('format');
    $object->replaceEmptyValue = $element->getAttr('replaceEmptyValue');
    $object->viewRender = ('yes' == strtolower($element->getAttr('viewRender')));

    $object->pageBreak = ('yes' == strtolower($element->getAttr('pageBreak')));

    //copies attributes from matching ReportField
    if($hierarchyID == $rootReport->moduleID){
        $object->reportField = $rootReport->fields[$reportFieldName];
    } else {
        $subReport =& $rootReport->getSubReportByHierarchyID($hierarchyID);
        $object->reportField = $subReport->fields[$reportFieldName];
    }
    $object->displayDecimals = $object->reportField->displayDecimals;
    $object->roundingMethod  = $object->reportField->roundingMethod;
    $object->displayFormat   = $object->reportField->displayFormat;

    if(isset($element->attributes['conditionValue'])){
        $object->conditionValue = $element->getAttr('conditionValue');
        $object->trueResult = $element->getAttr('trueResult');
        $object->falseResult = $element->getAttr('falseResult', false, 'Off');
    }
    return $object;
}

function getValue(&$data)
{
    $rawValue = null;
    $reportField =& $this->reportField;
    if(isset($data[$reportField->name])){
        if($this->viewRender){
            $rawValue = $reportField->viewRender($data);
        } else {
            $rawValue = $data[$reportField->name];
        }
        trace($rawValue, 'rawValue from reportField '.$this->reportField->name);
    }

    global $report_render_mode;
    if('pdf' == $report_render_mode){
        //the pdftk documentation says RTF formatting can be used but I was unsuccessful |  MJT 2007-06-05
        $rawValue = $this->html2txt($rawValue);
    }

    if(isset($this->replaceEmptyValue) && ('' == $rawValue)){
        $rawValue = $this->replaceEmptyValue;
    }

    //ViewField style formatting:
    if(is_numeric($rawValue) && ('' != $this->displayDecimals)){
        if(isset($this->roundingMethod) && 'round' != $this->roundingMethod){
            $tempMultiplier = pow(10, $this->displayDecimals);
            $tempValue = $rawValue * $tempMultiplier;
            switch($this->roundingMethod){
            case 'ceil':
                $tempValue = ceil($tempValue);
                break;
            case 'floor':
            default:
                $tempValue = floor($tempValue);
                break;
            }
            $rawValue = $tempValue / $tempMultiplier;
        }
        $rawValue = number_format($rawValue, $this->displayDecimals);
    }

    //displayFormat
    if(!empty($this->displayFormat)){
        $rawValue = sprintf('%'.$this->displayFormat, $rawValue);
    }



    if(!empty($this->format)){
        switch($this->format){
        case 'monthday':
            if(!empty($rawValue)){
                $rawValue = date('m/d', strtotime($rawValue));
            }
            break;
        case 'year_2':
            if(!empty($rawValue)){
                $rawValue = date('y', strtotime($rawValue));
            }
            break;
        default:
            //otherwise, support all PHP date formats
            if(!empty($rawValue)){
                $rawValue = date($this->format, strtotime($rawValue));
            }
            break;
        }
    }

    if('' == $this->conditionValue){
        if(empty($this->maxLength)){
            return $rawValue;
        } else {
            if(strlen($rawValue) > $this->maxLength){
                if('nextline' == $this->overflowAction){
                    $wrapped = wordwrap($rawValue, $this->maxLength, '-|-');
                    return explode('-|-', $wrapped);
                } else {
                    return substr($rawValue, 0, $this->maxLength);
                }
            } else {
                return $rawValue;
            }
        }
    } else {
        $matchType = 'equals';
        if(false !== strpos($this->conditionValue, ':')){
            list($matchType, $conditionValue) = preg_split('/:/', $this->conditionValue);
        } else {
            $conditionValue = $this->conditionValue;
        }

        $isMatched = false;
        switch($matchType){
        case 'contains':
            $isMatched = in_array($conditionValue, preg_split('/, /', $rawValue));
            break;
        case 'equals':
        default:
            $isMatched = $rawValue == $conditionValue;
        }

        if($isMatched){
            return $this->trueResult;
        } else {
            return $this->falseResult;
        }
    }
    return '';
}

function checkPageBreak(&$data)
{
    $value = $this->getValue($data);
    if($this->startValue == $value){
        return false;
    } else {
        return true;
    }
}


/**
 *  Returns a simply formatted text string (only attempts to capture bullets and numbered lists)
 */
function html2txt($html)
{
    $html=str_replace("\n",' ',$html);
    $a=preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);

    $value = '';
    $currentTags = array(); //used as a stack
    $listMode = '';
    $li_num = 0;  //number sequence of items in ordered list

    foreach($a as $i=>$e)
    {
        if($i%2==0)
        {
                if(strlen($e) > 0){
                    $value .= htmldecode($e);
                }
        }
        else
        {
            //Tag
            if($e{0}=='/') {
                $tag = array_pop($currentTags);
                while(strtoupper($e) !== '/'.$tag && count($currentTags) > 0){ //handle empty or wrongly nested tags (not fool proof)
                    $tag = array_pop($currentTags);
                }

                //tags that should add a new line on closing
                switch($tag){
                    case 'P':
                    case 'LI':
                        $value .= "\n";
                    break;
                default:
                    break;
                }

            } else {
                $a2=explode(' ',$e);
                $tag=strtoupper(array_shift($a2));

                array_push($currentTags, $tag);

                switch($tag){
                case 'LI':
                    //look for parent of '<li>' element
                    $parent_ix = count($currentTags) -2;
                    if($parent_ix >= 0){
                        $listMode = $currentTags[$parent_ix];
                    } else {
                        $listMode = ''; //this would indicate invalid HTML syntax
                    }
                    switch($listMode){
                    case 'UL':
                        $value .= '* ';
                        break;
                    case 'OL':
                        $li_num++;
                        $value .= $li_num.'. ';
                        break;
                    default:
                        break;
                    }
                    break;
                case 'OL':
                    $li_num = 0;
                    break;
                case 'BR':
                    $value .= "\n";
                    break;
                default:
                    break;
                }

            }
        }
    }

    $value = rtrim($value);
    return $value;
}

}  //end class PageField


class PageSummaryField
{
var $name;
var $mode;
var $refs = array();
var $matchValue;

function Factory(&$element, $moduleID)
{
    $object = new PageSummaryField();
    $object->name = $element->getAttr('name', true);
    $object->mode = $element->getAttr('mode');
    $object->matchValue = $element->getAttr('matchValue');

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            $object->refs[] = $sub_element->name;
        }
    }

    return $object;
}

function getValue(&$pageData)
{
    //print debug_r($data);
    if('countValue' == $this->mode){
        $matches = 0;
        foreach($this->refs as $ref){
            if($this->matchValue == $pageData[$ref]){
                $matches++;
            }
        }
        return $matches;
    } elseif('numeric' == $this->mode) {
        $value = 0;
        foreach($this->refs as $ref){
            if(is_numeric($pageData[$ref])){
                $value += $pageData[$ref];
            }
        }
        return $value;
    }

    return 0;
}
}  //end class PageSummaryField



class Transformation
{
var $parameters = array();
var $functionName;

function Factory(&$element, $moduleID)
{
    $object = new Transformation();
    $object->functionName = $element->getAttr('function', true);

    if(count($element->c) > 0){
        foreach($element->c as $sub_element){
            $object->parameters[] = $sub_element->createObject($moduleID);
        }
    }

    return $object;
}

function makeSelectDef($moduleID)
{
    switch($this->functionName){
    case 'year':
        $expression = 'YEAR(%1$s)';
        break;
    case 'year_firstday':
        $expression = 'DATE_FORMAT(%1$s,\'%Y-01-01\')';
        break;
    case 'equals':
        $expression = 'IF(%1$s = %2$s, 1, 0)';
        break;
    default:
        trigger_error("Unknown transformation function named '{$this->functionName}'", E_USER_ERROR);
        break;
    }

    if(count($this->parameters) == $this->_numberExpectedParameters()){
        $param_count = 1;
        foreach($this->parameters as $parameter){
            $expression = str_replace("%$param_count\$s", $parameter->makeSelectDef($moduleID), $expression);
            $param_count++;
        }
        return $expression;

    } else {
        trigger_error("Wrong number of parameters supplied for transformation function '{$this->functionName}'", E_USER_ERROR);
    }
}

function makeJoinDef($moduleID)
{
    if(count($this->parameters) == $this->_numberExpectedParameters()){
        $paramJoins = array();
        foreach($this->parameters as $parameter){
            $paramJoins = array_merge($paramJoins, $parameter->makeJoinDef($moduleID));
        }
        return $paramJoins;

    } else {
        trigger_error("Wrong number of parameters supplied for transformation function '{$this->functionName}'", E_USER_ERROR);
    }
}

function _numberExpectedParameters()
{
    switch($this->functionName){
    case 'year':
    case 'year_firstday':
        $nExpected = 1;
        break;
    case 'equals':
        $nExpected = 2;
        break;
    default:
        trigger_error("Unknown transformation function named '{$this->functionName}'", E_USER_ERROR);
        break;
    }
    return $nExpected;
}

}  //end class Transformation



class ModuleFieldRef
{
var $moduleID;
var $name;

function Factory(&$element, $moduleID)
{
    $object = new ModuleFieldRef();
    $object->moduleID = $moduleID;
    $object->name = $element->getAttr('name', true);

    return $object;
}

function makeSelectDef($moduleID)
{
    $mf = GetModuleField($this->moduleID, $this->name);
    return $mf->makeSelectDef($moduleID, false);
}

function makeJoinDef($moduleID)
{
    $mf = GetModuleField($this->moduleID, $this->name);
    return $mf->makeJoinDef($moduleID);
}
}  //end class ModuleFieldRef


class StaticValue
{
var $value;

function Factory(&$element, $moduleID)
{
    $object = new StaticValue();
    $object->value = $element->getAttr('value', true);

    return $object;
}

function makeSelectDef($moduleID)
{
    return '\''.$this->value.'\'';
}

function makeJoinDef($moduleID)
{
    return array();
}
}  //end class StaticValue
?>