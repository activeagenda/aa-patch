<?php
/**
 * Utility functions to be used at run-time only.
 *
 * This file contains functions that are mostly web disply/formatting-centric
 * in nature. It is included by most files that are executed at run-time.
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
 * @version        SVN: $Revision: 1627 $
 * @last-modified  SVN: $Date: 2009-05-11 22:22:15 +0200 (Pn, 11 maj 2009) $
 */


/**
 *  prepares query strings from a QS array, to be used in links
 */
function MakeQS($items)
{

    if(count($items)){
        foreach($items as $key => $value){
            $valuePairs[] = "$key=$value";
        }
        $qString = implode('&amp;', $valuePairs);
    } else {
        $qString = '';
    }
    return $qString;
}


/**
 *  formats a value according to the supplied data type
 */
function fldFormat($dt, $val, $formatString = null)
{
    global $User;
    switch($dt){
    case 'bool':
        if(1 == $val){
            $content = gettext("Yes");
        } elseif (NULL == $val) {
            $content = '-';
        } else {
            $content = gettext("No");
        }
        break;
    case 'date':
        if(empty($val)){
            $content = gettext("(no date set)");
        } else {
                //if the date is < year 2038, format it
            if (intval(substr($val, 0,4)) < 2038){
                $content = " ".strftime($User->getDateFormatPHP(), strtotime($val))." ";
            } else {
                //formatting function can't format it, just print it
                $content = " ".$val." ";
            }
        }
        break;
    case 'datetime':
        if(empty($val)){
            $content = gettext("(no date/time set)");
        } else {
            //if the date is < year 2038, format it
            if (intval(substr($val, 0,4)) < 2038){
                $content = " ".strftime($User->getDateTimeFormatPHP(), strtotime($val))." ";
            } else {
                //formatting function can't format it, just print it
                $content = " ".$val." ";
            }
        }
        break;
    case 'money':
        //we want to display only two decimals unless last two aren't zero.
        $nVal = 100 * $val;

        if(floor($nVal) == $nVal){
            $content = MASTER_CURRENCY.' '.number_format($val, 2).' ';
        } else { //there are more than 2 decimals so display the whole thing
            $content = MASTER_CURRENCY.' '.number_format($val, 4).' ';
        }
        break;
    default:
        if(!empty($formatString)){
            $val = sprintf('%'.$formatString, $val);
        }
        $content = $val;
    }
    return $content;
}


/**
 * formats a link value into an array with proper URL or mailto address, and whether or not the link is internal
 */
function linkFormat($link)
{

    $internal = false;
    $newWin = false;

    $link = str_replace('\\', '/', $link);
    if(false !== strpos($link, '@')){
        $link = 'mailto:'.$link;    //email address
    } elseif(false === strpos(strtolower($link), '://')) {
        if(false === strpos($link, 'internal:')){
            if(false !== strpos($link, 'special:')){
                $link = str_replace('special:', '', $link);
            } else {
                $link = 'http://'.$link;    //external address
                $newWin = true;
            }
        } else {
            $link = str_replace('internal:', '', $link);
            $internal = true;
        }
    } elseif(false !== strpos(strtolower($link), 'http://') || false !== strpos(strtolower($link), 'https://')) {
        $newWin = true;
    }
    $link = str_replace('&', '&amp;', $link);
    return array($link, $internal, $newWin);
}



/**
 *  Makes a print_r debug of an object (or whatever) and formats it to be legible in a browser.
 */
function debug_r($object, $title = '')
{
    ob_start();
        $content = '';
        if(!empty($title)){
            $content .= "<b>$title</b><br />\n";
        }
        print_r($object);
        $content .= strtr(ob_get_contents(), array("\n"=>"<br />\n", ' '=>'&nbsp;'));
        $content .= "<br />\n";
    ob_end_clean();
    return $content;
}



/**
 *  Quotes and escapes posted values, to make them safe for inclusion in SQL statements
 */
function dbQuote($value, $type = '')
{
    if (!empty($type)) {
        switch($type) {
        case 'date':
            return DateToISO($value);
            break;
        case 'datetime':
            return DateToISO($value, true);
            break;
        case 'time':
            return DateToISO($value, true, false);
            break;
        case 'bool':
            return ChkFormat($value);
            break;
        case 'int':
            //preserve blank values
            if('' == trim($value)) {
                return 'NULL';
            } else {
                return intval($value);
            }
            break;
        case 'money':
            //preserve blank values
            if('' == trim($value)) {
                return 'NULL';
            } else {
                //remove thousands-separating commas (some locale-specificity here wouldn't hurt at all :-)
                $value = str_replace(',', '', $value);

                return floatval($value);
            }
            break;
        case 'text':
            if (1 == get_magic_quotes_gpc()) {
                //temporarily strips slashes because they confuse HTML_Safe
                $value = stripslashes($value);
            }
            //allows some HTML but filters out (hopefully all) XSS possibilities
            require_once PEAR_PATH . '/HTML/Safe.php';
            $safe_html_parser =& new HTML_Safe();
            $value = $safe_html_parser->parse($value);
            if (1 == get_magic_quotes_gpc()) {
                //add slashes back
                $value = addslashes($value);
            }

            return dbQuote($value);
            break;
        default:
            //trap XSS problems
            $value = htmlspecialchars($value);
            return dbQuote($value);
        }

    } else {
        if('_' === trim($value)){
            return '""';
        }
        if('_' == substr($value, -1, 1)){
            $value = substr($value, 0, -1);
        }
        if('' === trim($value)){
            return 'NULL';
        }

        //escape quotes and special chars only if magic_quotes_gpc is off
        if (1 == get_magic_quotes_gpc()) {
            return "\"".trim($value)."\"";
        } else {
            return "\"".mysql_escape_string(trim($value))."\"";
        }
    }
}


/**
 *  Inserts or updates a desktop shortcut
 */
function SaveDesktopShortCut($PersonID, $Title, $Link, $Type, $ModuleID = null)
{
    global $dbh;
    $qTitle = dbQuote($Title);
    $qLink = dbQuote($Link);

    //check whether link exists
    $SQL = "SELECT COUNT(*) FROM usrds WHERE PersonID = $PersonID AND Link = $qLink";
    $numExisting = $dbh->getOne($SQL);
    dbErrorCheck($numExisting);
    if(0 < $numExisting){
        //update
        $SQL = "UPDATE usrds
        SET
            Title = $qTitle,
            Type = '$Type',
            ModuleID = '$ModuleID',
            _ModDate = NOW(),
            _Deleted = 0
        WHERE
            PersonID = $PersonID AND Link = $qLink";
    } else {
        //insert
        $SQL = "INSERT INTO usrds (PersonID, Title, Link, Type, ModuleID, _ModBy, _ModDate) VALUES ($PersonID, $qTitle, $qLink, '$Type', '$ModuleID', $PersonID, NOW())";

    }
    $r = $dbh->query($SQL);
    dbErrorCheck($r);

    $_SESSION['desktopShortcuts'][$Link] = $Title;

}


/**
 *  Removes a desktop shortcut
 */
function RemoveDesktopShortcut($PersonID, $Link)
{
    global $dbh;
    $qLink = dbQuote($Link);

    $SQL = "UPDATE usrds 
    SET 
        _ModDate = NOW(),
        _Deleted = 1
    WHERE
        PersonID = $PersonID AND Link = $qLink";
    $r = $dbh->query($SQL);
    dbErrorCheck($r);

    unset($_SESSION['desktopShortcuts'][$Link]);
}




/**
 * Returns a formatted date from a string based on a given format
 *
 * Supported formats
 *
 * %Y - year as a decimal number including the century
 * %m - month as a decimal number (range 1 to 12)
 * %d - day of the month as a decimal number (range 1 to 31)
 *
 * %H - hour as decimal number using a 24-hour clock (range 0 to 23)
 * %I
 * %M - minute as decimal number
 * %p
 * %s - second as decimal number
 * %u - microsec as decimal number
 * @param string date  string to convert to date
 * @param string format expected format of the original date
 * @return string rfc3339 w/o timezone YYYY-MM-DD YYYY-MM-DDThh:mm:ss YYYY-MM-DDThh:mm:ss.s
 */
function ParseDate( $date, $format ) {

    // Builds up date pattern from the given $format, keeping delimiters in place.
    if( !preg_match_all( "/%([YmdHIMipsu])([^%])*/", $format, $formatTokens, PREG_SET_ORDER ) ) {
        return false;
    }

    $datePattern = '';
    foreach( $formatTokens as $formatToken ) {
        if(isset($formatToken[2])){
            $delimiter = preg_quote( $formatToken[2], "/" );
        } else {
            $delimiter = '';
        }
        if($formatToken[1] == 'Y') {
        $datePattern .= '(.{1,4})'.$delimiter;
        } elseif($formatToken[1] == 'u') {
        $datePattern .= '(.{1,5})'.$delimiter;
        } else {
        $datePattern .= '(.{1,2})'.$delimiter;
        }
    }

    // Splits up the given $date
    if( !preg_match( "/".$datePattern."/", $date, $dateTokens) ) {
        return false;
    }

    $dateSegments = array();
    for($i = 0; $i < count($formatTokens); $i++) {
        $dateSegments[$formatTokens[$i][1]] = $dateTokens[$i+1];
    }
    return $dateSegments;
}


/**
 *  Formats a non-ISO-formatted date to ISO format (YYYY-MM-DD HH:MM)
 *
 *  Returns 'invalid' if the date cannot be validated.
 */
function DateToISO($pDate, $hasTime = false, $hasDate = true)
{
    if(empty($pDate)){
        return 'NULL';
    }

    global $User;

    if(false !== strpos($pDate, 'ISO')){
        $dateFormat = '%Y-%m-%d %H:%M:%s';
    } else {
        if(is_object($User)){
            if($hasTime && $hasDate) {
                $dateFormat = $User->getDateTimeFormatPHP();
            } elseif($hasDate) {
                $dateFormat = $User->getDateFormatPHP();
            } else {
                $dateFormat = $User->getTimeFormatPHP();
            }
        } else {
            if($hasTime && $hasDate) {
                $dateFormat = GetDefaultDateFormat('dateTimePHP');
            } elseif($hasDate) {
                $dateFormat = GetDefaultDateFormat('datePHP');
            } else {
                $dateFormat = GetDefaultDateFormat('timePHP');
            }
        }
    }

    $dateparts = ParseDate($pDate, $dateFormat);

    if($hasDate){
        $year = intval($dateparts['Y']);
        $month = str_pad(intval($dateparts['m']), 2, '0', STR_PAD_LEFT);
        $day = str_pad(intval($dateparts['d']), 2, '0', STR_PAD_LEFT);
    }

    if($hasTime) {
        $pm = false;
        if(isset($dateparts['I'])){
            $hour = str_pad(intval($dateparts['I']), 2, '0', STR_PAD_LEFT);
            if(false !== strpos(strtolower($dateparts['p']), 'p')){
                $pm = true;
            }
            if(12 == $hour){
                //the AM/PM clock begins 12, 1, 2, 3... So, if the hour is 12 AM, it is midnight. We also avoid adding 12 to 12 noon (12 PM)
                if(!$pm){
                    $hour = '00';
                }
            } else {
                if($pm){
                    $hour += 12;
                }
            }
        } else {
            $hour = str_pad(intval($dateparts['H']), 2, '0', STR_PAD_LEFT);
        }
        $minute = str_pad(intval($dateparts['M']), 2, '0', STR_PAD_LEFT); //this is inconsistent with http://www.php.net/date
        if(isset($dateparts['i'])){
            $minute = str_pad(intval($dateparts['i']), 2, '0', STR_PAD_LEFT);
        }
        $second = '00';
        if(isset($dateparts['s'])){
            $second = str_pad(intval($dateparts['s']), 2, '0', STR_PAD_LEFT);
        }
    }
    if($hasDate){
        if (checkdate($month, $day, $year)){
            if($hasTime){
                $ISODate = "\"$year-$month-$day $hour:$minute:$second\"";
            } else {
                $ISODate = "\"$year-$month-$day\"";
            }
        } else {
            //this is caught in the validation
            $ISODate = 'invalid';
        }
    } else {
        if($hour > 23 || $hour < 0){
            $ISODate = 'invalid';
        } elseif($minute > 59 || $minute < 0){
            $ISODate = 'invalid';
        } elseif($second > 59 || $second < 0) {
            $ISODate = 'invalid';
        } else {
            $ISODate = "\"$hour:$minute:$second\"";
        }
    }
    if('invalid' == $ISODate){
        trace($dateparts, "invalid date $pDate. dateFormat $dateFormat");
    }
    return $ISODate;
}



/**
 *  Translates submitted Checkbox values to database values
 */
function ChkFormat($value)
{
    if($value == '1') {
        return 1;
    } elseif($value == '-1') {
        return 0;
    } else {
        return 'NULL'; //"unselected"
    }

}



/**
 *  Translates database 'bool' values to Checkbox values
 */
function ChkUnFormat($value)
{
    if($value == '0') {
        return -1;
    } elseif ($value == '') {
        return ''; //UNselected!!
    } else {
        return 1;
    }
}

//START

/**
 * [1] handles server-side normalization passed in XML 'validate' atribute
 * [2] executes the replacment with the new string for regexr
 */
function Normalize($value, $validationString) 
{
    $validationString = trim($validationString);
    
    if( preg_match( '/^\s*regexr:\s*\/.*\/.*\/[ismxeuADSUX]*/' , $validationString ) ) {

       $validationString = preg_replace ( '/^\s*regexr:\s*/' , '' , $validationString  );
       preg_match( "/^\/(.*)\/(.*)\/([ismxeuADSUX]*)/", $validationString, $matches );
       $pattern = '/'.$matches[1].'/'.$matches[3];
       $replacement = $matches[2];

       $value = preg_replace($pattern, $replacement, $value);
    }    
    
    return $value;          
    
}
//END


/**
 * handles server-side validation
 */
function Validate($value, $shortPhrase, $validationString, $dataType = null)
{

    $retval = '';  //empty string means valid
    $validationString = trim($validationString);

    if(strlen($validationString) == 0) {

        return '';
//START
    } elseif ( preg_match( '/^\s*regexm:\s*\/.*\/[ismxeuADSUX]*/' , $validationString ) ) {
        
        $validationString = preg_replace ( '/^\s*regexm:\s*/' , '' , $validationString  );
		preg_match( "/^\/(.*)\/([ismxeuADSUX]*)\s*(.*)\s*$/", $validationString, $matches );

        // RegEx: http://www.php.net/manual/en/reference.pcre.pattern.syntax.php
        if( preg_match( '/'.$matches[1].'/'.$matches[2], $value ) ) {            
        
			return '';            
	
		} else {            
	
			$explanation='';
			if ( 0<strlen( $matches[3] ) ){
				$explanation = gettext( $matches[3] );
			} else{
			$explanation = '/'.$matches[1].'/'.$matches[2];
		    }
            return sprintf(gettext("The field '%s' must match pattern %s."), $shortPhrase, $explanation)."\n";        }
    } elseif ( preg_match( '/^\s*regexr:\s*\/.*\/.*\/[ismxeuADSUX]*/' , $validationString ) ) {               
	
		$validationString = preg_replace ( '/^\s*regexr:\s*/' , '' , $validationString  );
        preg_match( "/^\/(.*)\/(.*)\/([ismxeuADSUX]*)\s*(.*)\s*$/", $validationString, $matches );
        $pattern = '/'.$matches[1].'/'.$matches[3];
        
		if( preg_match( $pattern, $value ) ) {            
            return '';            
    
		} else {
			$explanation='';
			if ( 0<strlen( $matches[4] ) ){
				$explanation = gettext( $matches[4] );
			} else{
				$explanation = '/'.$matches[2].'/'.$matches[3];
			}
            return sprintf(gettext("The field '%s' must match pattern %s."), $shortPhrase, $explanation)."\n";                
        }
//END	
    } else {
        if(!empty($dataType)){
            switch($dataType){
            case 'datetime':
                if('invalid' == DateToISO($value, true)){
                    $retval .= sprintf(gettext("The field '%s' must contain a valid date and time."), $shortPhrase)."\n";
                }
                break;
            case 'date':
                if('invalid' == DateToISO($value)){
                    $retval .= sprintf(gettext("The field '%s' must contain a valid date."), $shortPhrase)."\n";
                }
                break;
            case 'time':
                if('invalid' == DateToISO($value, true, false)){
                    $retval .= sprintf(gettext("The field '%s' must contain a valid time."), $shortPhrase)."\n";
                }
                break;
            default;
                break;
            }
        }


        $validationStrings = split(' ', $validationString);

        foreach($validationStrings as $validationString) {

            switch($validationString) {
            case "noValidation":
                return $retval;
                break;
            case "notZero":
                if('' != trim($value)) {
                    if(!is_numeric($value)) {
                        $retval .= sprintf(gettext("The field '%s' can only contain numeric values."), $shortPhrase)."\n";
                    }
                    if((0 == intval($value)) && ('' != trim($value))) {
                        $retval .= sprintf(gettext("The field '%s' may not be zero."), $shortPhrase)."\n";
                    }
                }
                break;
            case "notEmpty":
                if('' == trim($value)) {
                    $retval .= sprintf(gettext("The field '%s' may not be empty."), $shortPhrase)."\n";
                }
                break;
            case "notNegative":
                if('' != trim($value)) {
                    if(!is_numeric($value)) {
                        $retval .= sprintf(gettext("The field '%s' can only contain numeric values."), $shortPhrase)."\n";
                    }
                    if($value < 0) {
                        $retval .= sprintf(gettext("The field '%s' may not be negative."), $shortPhrase)."\n";
                    }
                }
                break;
            case "Email":
                if('' != trim($value)) {
                    require_once PEAR_PATH . '/Validate.php';  //PEAR Validation class, used for email validation
                    if(!Validate::email(trim($value), false)){
                        $retval .= sprintf(gettext("The field '%s' must be a valid email address. The format is incorrect for an email address.") . "\n", $shortPhrase);
                    } else {
                        if(VALIDATE_EMAIL_DOMAINS){
                            if(!Validate::email(trim($value), true)){
                                $retval .= sprintf(gettext("The field '%s' must be a valid email address. The domain name (the value after \"@\") is invalid for email addresses.") . "\n", $shortPhrase);
                            }
                        }
                    }
                    /*
                    //substituted the below regex with PEAR class above, which supports DNS domain validation
                    if(!preg_match('/^[^@\s]+@([-a-z0-9]+\.)+[a-z]{2,}$/i', $value)){
                        $retval .= sprintf(gettext("The field '%s' must be a valid email address."), $shortPhrase)."\n";
                    }*/
                }
                break;
            case "Integer":
                if(!ctype_digit($value) &&  ('' != trim($value))) {
                    $retval .= sprintf(gettext("The field '%s' must be a valid whole number."), $shortPhrase)."\n";
                }
                break;
            case "Money":
                $value = str_replace('$', '', $value);
                $value = str_replace(',', '', $value);
                if((!is_numeric($value)) &&  ('' != trim($value))) {
                    $retval .= sprintf(gettext("The field '%s' must be a valid number."), $shortPhrase)."\n";
                }
                break;
            case "Number":
                if((!is_numeric($value)) &&  ('' != trim($value))) {
                    $retval .= sprintf(gettext("The field '%s' must be a valid number."), $shortPhrase)."\n";
                }
                break;
            case "RequireSelection":
                if((0 == intval($value))) {
                    if(strlen($value) > 2 && strlen($value) < 6) {
                        //possibly a moduleID field....
                    } else {
                        $retval .= sprintf(gettext("The field '%s' must have a selected value."), $shortPhrase)."\n";
                    }
                }
                break;
            case "dateFormat":
                break;
            default:
                $retval .= sprintf(gettext("The field '%s' has an unknown validation type '%s'. There is a problem in the XML file that defines this module."), $shortPhrase, $validationString);
                break;
            }
        }
    }

    return $retval;
}



/**
 *  replaces placeholders with data values
 */
function PopulateValues($SQL, &$data)
{
    $pattern = '/\'\[\*(\w*)\*\]\'/';
    $matches = array();
    if(preg_match_all ( $pattern, $SQL, $matches)){
        //print debug_r($matches[1], 'PopulateValues');
        foreach($matches[1] as $fieldName){
            $SQL = str_replace(
                '\'[*'.$fieldName.'*]\'',
                dbQuote($data[$fieldName]),
                $SQL
            );
        }
    }
    if(false !== strpos($SQL, '[**UserID**]')){
        global $User;
        $SQL = str_replace('[**UserID**]', $User->PersonID, $SQL);
    }
    if(false !== strpos($SQL, '/**DynamicModuleID**/')){
        global $ModuleID;
        $SQL = str_replace('/**DynamicModuleID**/', $ModuleID, $SQL);
    }
    if(false !== strpos($SQL, '[**OwnerOrganizationID**]')){
        global $ModuleID;
        global $ownerField;
        if(!empty($ownerField)){
            $SQL = str_replace('[**OwnerOrganizationID**]', $data[$ownerField], $SQL);
        }
    }
    return $SQL;
}


function isConsistent($moduleID = null, $recordID = null)
{
    if(empty($moduleID)){
        global $ModuleID;
        global $recordID;
        $moduleID = $ModuleID;
    }

    $mdb2 =& GetMDB2();

    $SQL = "SELECT Inconsistent FROM `ccs` WHERE ModuleID = '$moduleID' AND RecordID = '$recordID' AND _Deleted = 0";
trace($SQL);
    $inconsistent = $mdb2->queryOne($SQL);
    mdb2ErrorCheck($inconsistent);

    return empty($inconsistent);
}


/**
 *  Returns a consistency message if the record is inconsistent.
 */
function renderConsistencyMessage($moduleID = null, $recordID = null, $format = 'html')
{
    //need only display on first form on page
    static $rendered = false;
    if($rendered){
        return '';
    }
    $rendered = true;

    if(empty($moduleID)){
        global $ModuleID;
        global $recordID;
        $moduleID = $ModuleID;
    }

    $mdb2 =& GetMDB2();

    $SQL = "SELECT Inconsistent, Triggers, Targets FROM `ccs` WHERE ModuleID = '$moduleID' AND RecordID = '$recordID' AND _Deleted = 0";
    $result = $mdb2->queryRow($SQL);
    mdb2ErrorCheck($result);

    if(count($result) == 0 || !$result['Inconsistent']){
//        trace("Record $moduleID #$recordID is consistent.");
        return '';
    }
//    trace("Record $moduleID #$recordID is inconsistent.");

    $file_path = GENERATED_PATH.'/'.$moduleID.'/'.$moduleID.'_Consistency.gen';
    if(file_exists($file_path)){
        include $file_path; //returns $consistencySQLs, $phrases
    } else {
        trigger_error("Cannot find consistency file $filename.", E_USER_ERROR);
    }

    $condition_triggers = explode(',', $result['Triggers']);
    $condition_targets = explode(',', $result['Targets']);

    $message = gettext("This record is in an inconsistent state. To fix this problem, you must:\n");
    if('html' == $format) {
        $message .= '<ul>';
    }
    $messages = array();
    $conditions = array(); //unique list of conditions (as keys). value is whether the condition has any triggers or not

    foreach($condition_targets as $condition_target){
        $condition = substr($condition_target, 0, strpos($condition_target, 'T'));
        $conditions[$condition] = null;
        if('html' == $format) {
            $messages[$condition_target] = '<li>'.$phrases[$condition_target]."</li>\n";
        } else {
            $messages[$condition_target] = '* '.$phrases[$condition_target]."\n";
        }
    }

    foreach($condition_triggers as $condition_trigger){
        $condition = substr($condition_target, 0, strpos($condition_target, 'T'));
        if(!is_array($conditions[$condition])){
            $conditions[$condition] = array();
        }
        $conditions[$condition] = array_merge($conditions[$condition], $phrases[$condition_trigger]);
    }

    foreach($conditions as $condition => $trigger_fields){
        if(is_array($trigger_fields)){

            if('html' == $format) {
                $messages[$condition.'Tb']= '</ul>';
            }
            $messages[$condition.'Tc'] = gettext("This is required because ");
            if(count($trigger_fields) > 1){
                $last_field = array_pop($trigger_fields);
                $messages[$condition.'Tr'] = join(', ', $trigger_fields);
                $messages[$condition.'Tr'] .= gettext(", and ").$last_field;
            } else {
                $messages[$condition.'Tr'] = reset($trigger_fields);
            }
            $messages[$condition.'Ts'] = '.';
        } else {
            $messages[$condition.'Tb'] = gettext("This is always required.");
        }
    }
    ksort($messages);

    $message .= join('', $messages) . "\n";
    //trace($result);
    //trace($condition_phrases);

    return $message;
}


/**
 *  formats a ViewTable
 */
function renderViewTable($content, $edit=NULL, $backlink=NULL, $editlink=NULL)
{
    $consistencyMsg = renderConsistencyMessage();
    if(!empty($consistencyMsg)){
        $content = sprintf(FORM_CONSISTENCYROW_HTML, $consistencyMsg) . $content;
    }

    $content = sprintf(VIEWTABLE_HTML, $content);
    return $content;
}



/**
 *  formats a popupViewTable
 */
function renderPopupViewTable($content)
{
    $content .= sprintf(VIEWTABLE_POPUPNAV_HTML, gettext("Close"));
    $content = sprintf(VIEWTABLE_HTML, $content);
    return $content;
}



/**
 *  formats an edit screen form
 */
function renderForm($content, $targetlink, $deletelink, $cancellink, $nextScreen, $enctype, $moduleID, $addButtons = true)
{
    if($addButtons){
        //make buttons:
        //first insert Save button
        $buttons = sprintf(FORM_SUBMIT_HTML, "Save", gettext("Save"), '');
        if(0 < strlen($deletelink)) {
            $buttons .= '<input type="hidden" name="Delete" id="Delete" value=""/>';
            $buttons .= "&nbsp;".sprintf(FORM_BUTTON_HTML, "Delete_btn", gettext("Delete"), 'confirmDelete(this);');
        }
        if(0 < strlen($cancellink)) {
            $buttons .= "&nbsp;".sprintf(FORM_BUTTON_HTML, "Cancel", gettext("Cancel"), "location='$cancellink'");
        }

        //add button row
        $content .= sprintf(FORM_BUTTONROW_HTML, $buttons);
    }

    $consistencyMsg = renderConsistencyMessage();
    if(!empty($consistencyMsg)){
        $content = sprintf(FORM_CONSISTENCYROW_HTML, $consistencyMsg) . $content;
    }

    //insert all the content in the form
    $content = sprintf(FORM_HTML, $targetlink, $enctype, $content, $moduleID);

    return $content;
}



/**
 *  formats an edit screen form
 */
function renderForm2($content, $targetlink, $params = array())
{
    $renderButtons = true;
    if(isset($params['render_buttons'])){
        $renderButtons = $params['render_buttons'];
    }

    if(empty($params['single_record_name'])) {
        $recordName = gettext("record");
    } else {
        $recordName = $params['single_record_name'];
    }

    if($renderButtons){
        //save button
        $buttons = sprintf(FORM_SUBMIT_HTML, "Save", gettext("Save"), '');

        //delete button
        if($params['delete_button']) {
            $buttons .= '<input type="hidden" name="Delete" id="Delete" value=""/>';
            $buttons .= '&nbsp;'.sprintf(FORM_BUTTON_HTML, 'Delete_btn', gettext("Delete"), 'confirmDelete(this);');
        }
        //cancel button
        if(isset($params['cancel_link']) && 0 < strlen($params['cancel_link'])) {
            $buttons .= '&nbsp;'.sprintf(FORM_BUTTON_HTML, 'Cancel', gettext("Cancel"), "location='{$params['cancel_link']}'");
        }

        //add button row
        $content .= sprintf(FORM_BUTTONROW_HTML, $buttons);

        $checkboxes = '';
        if($params['is_main_form']) {
            if(isset($params['clone']) && $params['clone']) {
                if(isset($_POST['CloneForm']) && $_POST['CloneForm']){
                    $checked = 'checked';
                } else {
                    $checked = '';
                }
                $checkboxes .= '&nbsp;'.sprintf(FORM_CHECKBOX, 'CloneForm', 'CloneForm', sprintf(gettext("Clone form data as new %s"),$recordName), $checked, 1);
            }
            if(isset($params['keep_add_new']) && $params['keep_add_new']) {
                if(isset($_POST['KeepNew']) && $_POST['KeepNew']){
                    $checked = 'checked';
                } else {
                    $checked = '';
                }
                $checkboxes .= '&nbsp;'.sprintf(FORM_CHECKBOX, 'KeepNew', 'KeepNew', sprintf(gettext("Reset form to add another %s"),$recordName), $checked, 1);
            }
        }
        if(!empty($checkboxes)){
            $content .= sprintf(FORM_BUTTONROW_HTML, $checkboxes);
        }
    }


    $consistencyMsg = renderConsistencyMessage();
    if(!empty($consistencyMsg)){
        $content = sprintf(FORM_CONSISTENCYROW_HTML, $consistencyMsg) . $content;
    }

    //insert all the content in the form
    $content = sprintf(FORM_HTML, $targetlink, $params['form_enctype'], $content, $params['module_id']);

    return $content;
}



/**
 *  formats a search screen form
 */
function renderSearchForm($content, $targetlink, $chartLink, $moduleID)
{
    //make buttons:
    //first insert Save button
    $buttons = sprintf(FORM_SUBMIT_HTML, "Search", gettext("Search"), '');
    $buttons .= "&nbsp;".sprintf(FORM_SUBMIT_HTML, "Chart", gettext("Chart"), '');

    //add button row
    $content .= sprintf(FORM_BUTTONROW_HTML, $buttons);

    //insert all the content in the form
    $content = sprintf(FORM_HTML, $targetlink, '', $content, $moduleID);

    return $content;
}



/**
 * Generates the HTML for a LabelFields section
 */
function renderLabelFields($moduleID, $recordID)
{
    $content = '';
    require_once CLASSES_PATH . '/components.php';

    $label_filename = GENERATED_PATH . '/'.$moduleID.'/'.$moduleID.'_LabelSection.gen';
    if (!file_exists($label_filename)){
        return gettext("ERROR: file not found: ").$label_filename;
    } else {
        global $singularRecordName; //some screens use this for title
        include $label_filename;
    }

    //get label data
    global $dbh;
    $labelSQL = TranslateLocalDateSQLFormats($labelSQL);
    $r = $dbh->getRow(str_replace('/**RecordID**/', $recordID, $labelSQL), DB_FETCHMODE_ASSOC);
    dbErrorCheck($r);

    if(count($r) > 0) {

        //display label
        foreach($fields as $key => $field){
            if (!$field->isSubField() && 'IsBestPractice' != $field->name){
                $content .= $field->render($r, $phrases);
            }
        }

        $content = renderViewTable($content);

        if(!empty($recordLabelField)){
            global $recordLabel;
            $recordLabel = $r[$recordLabelField];
        }

    } else {
        $content = gettext("ERROR: Empty query result.");
    }

    return $content;
}



/**
 * retrieves RDC triggers for module, and updates RDC for affected records
 *
 * (obsolete)
 */
function UpdateRDCache($moduleID, $recordID, $PKFieldName, $delete = false)
{
    global $dbh;

    //get triggers file
    $triggerFile = GENERATED_PATH . "/{$moduleID}/{$moduleID}_RDCTriggers.gen";

    if(file_exists($triggerFile)){
        $RDCtriggers = array();
        include_once($triggerFile); //sets $RDCtriggers

        if(count($RDCtriggers) > 0){
            foreach($RDCtriggers as $triggerModuleID => $triggerSQL){

                if(false !== strpos($triggerSQL, '/**RecordID**/')){
                    $triggerSQL = str_replace('/**RecordID**/', $recordID, $triggerSQL);
                } else {
                    //this can be removed once all installations have been fully parsed
                    $triggerSQL .= " AND {$moduleID}.$PKFieldName = '$recordID'";
                }

                $triggerRecordIDs = $dbh->getCol($triggerSQL);

                if(dbErrorCheck($triggerRecordIDs, false, false)){
                    if(count($triggerRecordIDs)>0){
                        $strTriggerRecordIDs = join(',', $triggerRecordIDs);

                        if($delete){
                            $SQL = "UPDATE `rdc` SET _Deleted = 1 WHERE ModuleID = '$triggerModuleID' AND RecordID IN ($strTriggerRecordIDs)";

                            trace($SQL, "RDC delete");
                        } else {
                            //get existing cached records       
                            $SQL = "SELECT RecordID FROM `rdc` WHERE ModuleID = '$triggerModuleID' AND RecordID IN ($strTriggerRecordIDs)";

                            $cachedRecordIDs = $dbh->getCol($SQL);
                            $strCachedRecordIDs = join(',',$cachedRecordIDs);

                            $insertIDs = array_diff($triggerRecordIDs, $cachedRecordIDs);

                            //get cached SQL file:
                            $RDCUpdateFile = GENERATED_PATH . "/{$triggerModuleID}/{$triggerModuleID}_RDCUpdate.gen";
                            if(file_exists($RDCUpdateFile)){
                                include($RDCUpdateFile); //imports $RDCinsert and $RDCupdate

                                if(!empty($strCachedRecordIDs)){ //should always be something?
                                    //update existing
                                    $RDCupdate = str_replace('[*updateIDs*]', $strCachedRecordIDs, $RDCupdate);

                                    $r = $dbh->query($RDCupdate);
                                    dbErrorCheck($r);
                                }
                                //insert new, if any
                                if(count($insertIDs)>0){
                                    $RDCinsert = str_replace('[*insertIDs*]', join(',', $insertIDs), $RDCinsert);
                                    $r = $dbh->query($RDCinsert);
                                    dbErrorCheck($r);

                                }
                            }
                        }
                    }
                } else {
                    trigger_error("Warning: RDC update for module ($triggerModuleID) failed in $triggerFile.", E_USER_NOTICE);
                }
            }
        } 
    } else {
       // print "DEBUG: No triggers for $moduleID<br>\n";
    }

}

/**
 * retrieves SMC triggers for module, and updates SMC for affected records
 */
function UpdateSMCache($moduleID, $recordID, $PKFieldName)
{
    global $dbh;

    //get triggers file
    $triggerFile = GENERATED_PATH . "/{$moduleID}/{$moduleID}_SMCTriggers.gen";

    $insertSQL = "INSERT INTO `smc` (ModuleID, RecordID, SubModuleID, SubRecordID)\n";

    if(file_exists($triggerFile)){
        include_once($triggerFile); //sets $SMCtriggers

        foreach($SMCtriggers as $triggerModuleID => $triggerSQL){
            $triggerSQL .= " LEFT OUTER JOIN smc ON 
                `$moduleID`.$PKFieldName = smc.SubRecordID
                AND smc.ModuleID = '$triggerModuleID'
                AND smc.SubModuleID = '$moduleID' ";

            $triggerSQL = str_replace(array('/*SubModuleID*/', '/*SubRecordID*/'), array($moduleID, $recordID), $triggerSQL);

            $SQL = $insertSQL . $triggerSQL;
            $SQL .= "\nWHERE smc.ModuleID IS NULL\n";
            $SQL .= " AND `{$moduleID}`.$PKFieldName = '$recordID'";

             //   print debug_r($SQL, "SQL for $triggerModuleID");
             trace($SQL, "SMC SQL for $triggerModuleID");

            $r = $dbh->query($SQL);
            dbErrorCheck($r);
        }
    }
}


function GetGlobalTabs($ModuleID, $recordID, $selModuleID = null)
{
    if(empty($recordID)){ //if there's no record ID ($recordID is 0 or ''), we're in "new record" mode, so don't show global tabs
        return '';
    }

    $labels = array(
        'act' => gettext("Actions"),
        'att' => gettext("Attachments"),
        'cos' => gettext("Costs"),
        'lnk' => gettext("Links"),
        'nts' => gettext("Notes")
    );

    //don't show global module tabs in global modules
    if(in_array($ModuleID, array_keys($labels))){
        return '';
    }

    global $disableGlobalModules;
    if($disableGlobalModules){
        return '';
    }

    global $qsArgs;
    $gQsArgs = $qsArgs;
    unset($gQsArgs['gmd']);

    if('list' == $recordID){
        $gQsArgs['rid'] = 'list';
    }
    $globalQS = MakeQS($gQsArgs);

    $globalSummary = GetGlobalSummary($ModuleID, $recordID);

    //class, globalModuleID, link, mouseovermsg, label
    $normalTab = "<div class=\"%1\$s\">
    <a class=\"tabb\" id=\"tab_%2\$s\" href=\"%3\$s\" title=\"%4\$s\">%5\$s</a>
</div>\n";

    //class, label
    $selectedTab = "<div class=\"%s\">
    <div class=\"tabb\">
        %s
    </div>
</div>\n";

    $html = '';
    foreach($labels as $globalModuleID => $label){
        if($selModuleID == $globalModuleID){
            if(!empty($globalSummary[$globalModuleID])){
                $class = 'tabseld';
            } else {
                $class = 'tabsel';
            }
            $html .= sprintf(
                $selectedTab,
                $class,
                $label
                );
        } else {
            if(!empty($globalSummary[$globalModuleID])){
                $class = 'tabgd';
            } else {
                $class = 'tabg';
            }

            //class, globalModuleID, link, mouseovermsg, label
            $html .= sprintf(
                $normalTab,
                $class,
                $globalModuleID,
                'global.php?'.$globalQS.'&amp;gmd='.$globalModuleID,
                sprintf(gettext("Edit %s for this record"), $label),
                $label
                );
        }
    }

    return $html;
}


function GetGlobalSummary($moduleID, $recordID){
    global $dbh;

    if('list' == $recordID ){

        $SQL = "SELECT RecordID FROM `mod` WHERE ModuleID = '$moduleID'";
        $mdb2 =& GetMDB2();
        $moduleID = 'mod';
        $recordID = $mdb2->queryOne($SQL);
        mdb2ErrorCheck($recordID);

        $recordConditionSQL = "AND RelatedRecordID = '$recordID'";
    } else {
        $recordConditionSQL = "AND RelatedRecordID = '$recordID'";
    }

     $SQL = "SELECT 'act' AS ModuleID, COUNT(*) AS records
 FROM `act`
WHERE RelatedModuleID = '$moduleID' $recordConditionSQL AND _Deleted = 0
 UNION
 SELECT 'att' AS ModuleID, COUNT(*) AS records
 FROM `att`
WHERE RelatedModuleID = '$moduleID' $recordConditionSQL AND _Deleted = 0
 UNION
 SELECT 'cos' AS ModuleID, COUNT(*) AS records
 FROM `cos`
WHERE RelatedModuleID = '$moduleID' $recordConditionSQL AND _Deleted = 0
 UNION
 SELECT 'lnk' AS ModuleID, COUNT(*) AS records
 FROM `lnk`
WHERE RelatedModuleID = '$moduleID' $recordConditionSQL AND _Deleted = 0
 UNION
 SELECT 'nts' AS ModuleID, COUNT(*) AS records
 FROM `nts`
WHERE RelatedModuleID = '$moduleID' $recordConditionSQL AND _Deleted = 0
";

    $res = $dbh->getAssoc($SQL);
    dbErrorCheck($res);
    return $res;
}


/**
 *  returns file system path to the current theme folder
 */
function GetThemeLocation()
{
    global $User;
    if(!empty($User->theme)){
        $theme = $User->theme;
    } else {
        $theme = DEFAULT_THEME;
    }
    return THEME_PATH . '/' . $theme;
}


/**
 *  returns web path to the current theme folder
 */
function GetThemeWebLocation()
{
    global $User;
    if(!empty($User->theme)){
        $theme = $User->theme;
    } else {
        $theme = DEFAULT_THEME;
    }
    return THEME_WEB_PATH . '/' . $theme;
}


/**
 * returns the HTML for page title icons, as needed
 */
function GetPageTitleIcons()
{
    $content = '';
    global $theme_web;

    //see if this is a best practice record
    global $useBestPractices;
    if($useBestPractices){
        global $data;
        if($data['IsBestPractice']){
            $content .= '<img src="'.$theme_web.'/img/best_practice.png" alt="(This record is a Best Practice)"/>';
        }
    }

    //determine whether to display Directions
    global $directionCount; //must be generated before calling this function
    if(intval($directionCount) > 0){
        if(!empty($content)){
            //adds some spacing between the icons
            $content .= '&nbsp;';
        }
        $content .= '<a href="#" onmouseover="showTitlePopover(\'directions_popover\', this)" onmouseout="hideTitlePopover(\'directions_popover\')">';
        $content .= '<img src="'.$theme_web.'/img/directions.gif" alt="Directions"/>';
        $content .= '</a>';
    }

    //determine whether to display Guidance
    global $guidanceGrid;
    if(!empty($guidanceGrid)){
        $guidanceCount = $guidanceGrid->getRecordCount();
        if(intval($guidanceCount) > 0){
            if(!empty($content)){
                //adds some spacing between the icons
                $content .= '&nbsp;';
            }
            $content .= '<a href="#" onmouseover="showTitlePopover(\'guidance_popover\', this)" onmouseout="hideTitlePopover(\'guidance_popover\')">';
            $content .= '<img src="'.$theme_web.'/img/guidance.png" alt="Guidance"/>';
            $content .= '</a>';
        }
    }

    //determine whether to display Guidance
    global $resourceCount; //must be generated before calling this function
    if(intval($resourceCount) > 0){
        if(!empty($content)){
            //adds some spacing between the icons
            $content .= '&nbsp;';
        }
        $content .= '<a href="#" onmouseover="showTitlePopover(\'resources_popover\', this)" onmouseout="hideTitlePopover(\'resources_popover\')">';
        $content .= '<img src="'.$theme_web.'/img/resources.png" alt="Resources"/>';
        $content .= '</a>';
    }

    if(!empty($content)){
        $content = '&nbsp;&nbsp;' . $content;
    }
    return $content;
}



function GetParentInfo($moduleID)
{
    global $dbh;
    global $theme_web;

    $SQL = "SELECT 
    `modd`.DependencyID AS ParentModuleID,
    `mod`.Name AS ParentModule
FROM
    `modd`
    INNER JOIN `mod`
    ON (`modd`.DependencyID = `mod`.ModuleID
    AND `modd`.SubModDependency = 1
    )
WHERE `modd`.ModuleID = '$moduleID'
ORDER BY ParentModule";
    $r = $dbh->getAll($SQL, DB_FETCHMODE_ASSOC);
    dbErrorCheck($r);

    $relations_icon = '<a href="#" onclick="showRelationsPopover(\''.$moduleID.'\', this)" title="'.gettext("Relations").'"><img src="'.$theme_web.'/img/module_relations.gif" alt="'.gettext("Relations").'" /></a>&nbsp;';

    switch(count($r)){
    case 0:
        return $relations_icon;
    case 1:
        $link =  '<a href="list.php?mdl='.$r[0]['ParentModuleID'].'">';
        $link .= sprintf(gettext("Parent Module: %s"), gettext($r[0]['ParentModule']) );
        $link .= '</a>';
        return $relations_icon . $link;
    default:
        return $relations_icon . sprintf(gettext("Parent Modules: %s"), count($r));
    }
}



/**
 *  Returns the URLs for the "next" and "previous" records, used by View and Edit screens.
 */
function GetSeqLinks($moduleID, $currentSeqID, $pageName){
    $currentSeqID = intval($currentSeqID);

    if(empty($_SESSION[$moduleID . '_ListSeq'])){
        return array(null, null);
    } else {
        $listSequence =& $_SESSION[$moduleID . '_ListSeq'];
    }

    global $qsArgs;
    $sqQSArgs = $qsArgs;
    unset($sqQSArgs['rid']);
    unset($sqQSArgs['sr']);
    $sqQS = MakeQS($sqQSArgs);

    $nPageRows = $listSequence['rpp'];

    $prevSeq = $currentSeqID-1;
    $pageStartRow = floor($prevSeq / $nPageRows) * $nPageRows;
    if(isset($listSequence['rows'][$prevSeq])){
        $pageStartRow = floor($prevSeq / $nPageRows) * $nPageRows;
        $prevRecordID = $listSequence['rows'][$prevSeq];
        $prevLink = $pageName.'?'.$sqQS.'&amp;rid='.$prevRecordID.'&amp;sr='.$prevSeq;
    } else {
        if($prevSeq < 0){
            $prevLink = null;
        } else {
            $SQL = $listSequence['sql'];
            $SQL .= " LIMIT $pageStartRow, $nPageRows";
            global $dbh;
            $result = $dbh->getCol($SQL);
            dbErrorCheck($result);
            foreach($result as $rowIX => $recordID){
                $listSequence['rows'][$pageStartRow + $rowIX] = $recordID;
            }
            $prevRecordID = $listSequence['rows'][$prevSeq];
            $prevLink = $pageName.'?'.$sqQS.'&amp;rid='.$prevRecordID.'&amp;sr='.$prevSeq;
        }
    }

    $nextSeq = $currentSeqID+1;
    $pageStartRow = floor($nextSeq / $nPageRows) * $nPageRows;
    if(isset($listSequence['rows'][$nextSeq])){
        $nextRecordID = $listSequence['rows'][$nextSeq];
        $nextLink = $pageName.'?'.$sqQS.'&amp;rid='.$nextRecordID.'&amp;sr='.$nextSeq;
    } else {

        if($nextSeq >= $listSequence['count']){
            $nextLink = null;
        } else {

            $SQL = $listSequence['sql'];
            $SQL .= " LIMIT $nextSeq, $nPageRows";
            global $dbh;
            $result = $dbh->getCol($SQL);
            dbErrorCheck($result);
            foreach($result as $rowIX => $recordID){
                $listSequence['rows'][$nextSeq + $rowIX] = $recordID;
            }

            $nextRecordID = $listSequence['rows'][$nextSeq];
            $nextLink = $pageName.'?'.$sqQS.'&amp;rid='.$nextRecordID.'&amp;sr='.$nextSeq;
        }
    }

    return(array($prevLink, $nextLink));
}


/**
 *  Strips HTML entities, converting them back to their corresponding characters
 *
 *  Adapted from the comments to htmlspecialchars: http://us.php.net/htmlspecialchars.
 */
function htmldecode($encoded)
{
    static $translation = null;
    if(empty($translation)){
        $translation = array_flip(get_html_translation_table(HTML_ENTITIES));

        //also add the apostrophe
        $translation['&apos;'] = '\'';
        $translation['&#039;'] = '\'';
        $translation['&#39;'] = '\'';
    }
    return strtr($encoded, $translation);
}



/**
 *  Helper class to render text tables
 */
class TextTable {
    var $colWidths = array();
    var $rowHeights = array();
    var $data = array();
    var $headers = array();
    var $hasHeaders = false;

    function TextTable($data, $headers = null){
        $this->data = $data;
        if(!empty($headers)){
            $this->hasHeaders = true;
            $this->headers = $headers;
        }
    }

    function render(){
        $content = '';

        if($this->hasHeaders){
            $data = array_merge(array($this->headers), $this->data);
        } else {
            $data = $this->data;
        }

        //calculate dimensions for all rows and columns
        foreach($data as $row_ix => $row){
            foreach($row as $col_ix => $value){
                list($w, $h) = $this->getCellDimensions($value);
                if($this->colWidths[$col_ix] < $w){
                    $this->colWidths[$col_ix] = $w;
                }
                if($rowHeights[$row_ix] < $h){
                    $this->rowHeights[$row_ix] = $h; 
                }
            }
        }

        $separator = $this->getSeparator($this->colWidths);

        $content = $separator;

        foreach($data as $row_ix => $row){
            $content .= $this->formatRow($row, $this->rowHeights[$row_ix], $this->colWidths) . "\n";
            if($this->hasHeaders && 0 == $row_ix){
                $content .= $separator;
            }
        }
        $content .= $separator;

        return $content;
    }

    function getSeparator($colWidths){
        $content = '+';
        foreach($colWidths as $colWidth){
            $content .= '-' . str_pad('', $colWidth, '-') . '-+';
        }
        $content .= "\n";
        return $content;
    }

    function formatRow($row, $height, $colWidths){
        $rowLines = array();

        //walk through cells of row
        foreach($row as $col_ix => $cellValue){

            $cellLines = explode("\n", $cellValue);
            $cellHeight = count($cellLines);
            $align = STR_PAD_RIGHT;

            //prepend pipe on each row
            if(0 == $col_ix){
                $align = STR_PAD_LEFT;
                for($line_ix = 0; $line_ix < $height; $line_ix++){
                    $rowLines[$line_ix] = '|';
                }
            }

            //walk through lines of cell
            for($line_ix = 0; $line_ix < $height; $line_ix++){
                if($line_ix < $cellHeight){
                    $rowLines[$line_ix] .= ' '. str_pad(trim($cellLines[$line_ix]), $colWidths[$col_ix], ' ', $align) . ' |';
                } else {
                    $rowLines[$line_ix] .= ' '. str_pad('', $colWidths[$col_ix]) . ' |';
                }
            } 
        }

        return join("\n", $rowLines);
    }

    function getCellDimensions($value){
        $lines = explode("\n", $value);
        $maxLineLength = 0;
        foreach($lines as $line){
            if(strlen($line) > $maxLineLength){
                $maxLineLength = strlen($line);
            }
        }
        return array($maxLineLength, count($lines));
    }
}


/**
 *  Adds a url to the breadcrumb list
 */
function AddBreadCrumb($pageName)
{
    //don't add RPC calls to crumbs
    if(defined('IS_RPC') && IS_RPC){
        return;
    }

    $crumbs_length = 20;
    global $linkHere;
    if(empty($linkHere)){
        $path = $_SERVER['REQUEST_URI'];
    } else {
        $path = $linkHere;
    }
    if(isset($_SESSION['crumbs'][$path])){
        unset($_SESSION['crumbs'][$path]);
    }
    $_SESSION['crumbs'][$path] = array($pageName, time());

    if(count($_SESSION['crumbs']) > $crumbs_length){
        array_shift($_SESSION['crumbs']);
    }
}


function GenerateBreadCrumbs()
{
    if(isset($_SESSION['crumbs'])){
        $content .= '<p>'.gettext("You have been here").':</p>';
        //$content .= '<pre>'.debug_r($_SESSION['crumbs']).'</pre>';
        $content .= '<ul>';
        $crumb = end($_SESSION['crumbs']);
        while($crumb){
            $key = key($_SESSION['crumbs']);
            $ago = '';
            if(is_array($crumb)){
                list($crumb, $time) = $crumb;
            }
            if($_SERVER['REQUEST_URI'] == $key){
                //$content .= "<b>$crumb $ago</b><br />";
            } else {
                $content .= "<li><a href=\"$key\">$crumb $ago</a></li>";
            } 
            $crumb = prev($_SESSION['crumbs']);
        }
        $content .= '</ul>';
    } else {
        $content .= '<p>'.gettext("You have no breadcrumbs").'</p>';
    }
    return $content;
}
?>
