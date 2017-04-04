<?php
/**
 * anonedit.php created by Mike O'Meara for the purpose of creating a very
 * small footprint window to enter information (mostly populated through URL)
 * created by modifying edit.php on 9/3/07 & updating 9/8/08
 *
 * Edit Screen replacement for anonymous submission of forms
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
 * author         Mike O'Meara <momeara@orqa.org>
 * copyright      2003-2009 Active Agenda Inc., 2008 ORQA, LLC
 * license        http://www.activeagenda.net/license
 **/

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//including all general application setup in this file, so page_startup.php not called
//require_once INCLUDE_PATH . '/page_startup.php';

/**
 * Defines execution state as 'web'.  Several classes and
 * functions behave differently because of this flag.
 */
define('EXEC_STATE', 1);



/**
 * Class file includes.
 */
require_once CLASSES_PATH . '/user.class.php';

require_once PEAR_PATH . '/DB.php';
    $dbh = DB::connect(DB_DSN, array('persistent' => true));
    dbErrorCheck($dbh);


/**
 * Utility functions includes.
 */
require_once INCLUDE_PATH . '/general_util.php';
require_once INCLUDE_PATH . '/web_util.php';


/**
 * Sets custom error handler
 */
set_error_handler('handleError');


/**
 * Use a unique session name instead of PHPSESSID
 */
session_name(SITE_SESSIONNAME);


/**
 * Set time stamp for performance monitoring
 */
setTimeStamp('page_startup');

/**
 * Starts session handling.
 */
if (! session_start()) {
    trigger_error("Session could not be created.", E_USER_ERROR);
}



/**
 * Get our user object from session.
 */
if(isset($_SESSION['AnonUser'])){
    $User = $_SESSION['AnonUser'];
} else {
    $User = null;
}

/**
 * Gets the theme, or falls back to the DEFAULT_THEME
 */
$theme = GetThemeLocation();
$theme_web = GetThemeWebLocation();


if(!defined('ANON_USER')){
    trigger_error(gettext("Anonymous data entry is not enabled.|ANON_USER is not defined"), E_USER_ERROR);
}


/**
 * Check that (anonymous) user is logged in and that session hasn't expired.  Otherwise,
 * AUTHENTICATE AS ANONYMOUS USER
 * 
 * In order to make session timouts less disruptive, we preserve the request 
 * string so that the login page can take the user back to the requested 
 * page. Encoding is used so that the request can be preserved correctly.
 */
if (empty($User) || intval($_SESSION['Timeout']) < time()) {
    if(defined('IS_POPUP') && IS_POPUP){
        die(gettext("Your session has timed out (or you have logged out). Please close this window and log in from the main window."));
    } elseif(defined('IS_RPC') && IS_RPC) {
        die('session timeout');
    } else {
    	  //ANON_USER and ANON_PASS are defined in config.php
        $UserName = ANON_USER;
        $Password = ANON_PASS;
        $User = new User();

        if ( $User->Login($UserName, $Password)) {

            //preload user's desktop shortcuts
            $SQL = "SELECT Link, Title FROM usrds WHERE PersonID = {$User->PersonID} ORDER BY Type, Title";
            $rows = $dbh->getAssoc($SQL);
            foreach($rows as $link => $title){
                $_SESSION['desktopShortcuts'][$link] = $title;
            }

            //save the user object to the session
            $_SESSION['AnonUser'] = $User;
            $User->advanceSessionTimeout();

         } else {

            //login failed, so we reload the login screen and display
            //an error message.
            //in the future we might want to track number of failed
            //and lock out connections from logging in for a
            //specified time...

            $language = 'en_US';
            putenv("LC_ALL=$language");

            setlocale(LC_ALL, $language);
            bindtextdomain("active_agenda", LOCALE_PATH);
            textdomain("active_agenda");
            $msg = "<b>" . gettext("Login failed.") . "</b><br>\n";
            $msg .= gettext("Please check the spelling of username and password") . ".<r>\n";
            $msg .= gettext("Check that CAPS LOCK is not accidentally on.");
        }
    }
}



/**
 * Update session timeout.
 */
$User->advanceSessionTimeout();



/**
 * Connect to database. The $dbh object is used whenever a database call is made.
 */
require_once PEAR_PATH . '/DB.php';
$dbh = DB::connect(DB_DSN, array('persistent' => true));
dbErrorCheck($dbh);



/**
 *  if page logging is enabled, save the page request
 */
if(defined('USER_LOG_PAGE_ACCESS') && USER_LOG_PAGE_ACCESS){
    if (!empty($_POST['save']) || !empty($_POST['Save'])){
        $action = 'save';
    } elseif (!empty($_POST['delete']) || !empty($_POST['Delete'])){
        $action = 'delete';
    } else {
        $action = 'access';
    }
    $User->saveLogEntry(3, $action, true);
}


/**
 * Splits the query string into an associative array, $qsArgs. 
 * 
 * $qsArgs is used when building various links on the page.  By using an 
 * associative array, we can add, remove, or change a few values while 
 * preserving the rest.  The utility function MakeQS() is used for
 * converting the modified array back into a string.
 *
 * The $qs variable is used when the original request string is needed.
 */
$qsArgs = array();
$qs = $_SERVER['QUERY_STRING'];
if(!empty($qs)){
    foreach(split('&', $qs) as $valuePair){
        if(empty($valuePair)){
            continue;
        }
        list($key, $value) = split('=', $valuePair);
        $qsArgs[$key] = $value;
    }
}
unset($qsArgs['shortcut']);
$qs = MakeQS($qsArgs);



/**
 * Retrieves (and sanitizes) the requested module ID, if any.
 *
 * Module IDs are 3-5 characters long.
 */
if(isset($_GET['mdl'])){
    $ModuleID = substr(addslashes($_GET['mdl']), 0, 5);
} else {
    $ModuleID = '';
}

/**
 * Tells browser we intend to send utf-8
 */
header('Content-Type: text/html; charset=UTF-8');

/**
 * Determines the user's preferred language
 */
if (isset($User->Lang)) {
    $newLang = $User->Lang;
} else {
    //sniff the browser settings and try to match
    $site_langs = array('sv_SE', 'en_US');
    $user_langs = split(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

    if (empty($user_langs[0])){
        //$newLang = 'en_US';
        if(defined('DEFAULT_LOCALE')){
            $newLang = DEFAULT_LOCALE;
        } else {
            $newLang = 'en_US';
        }
    } else {
        $found = false;
        foreach($user_langs as $key => $value){
            $value = str_replace('-', '_', $value);
            if (strpos($value, ';')){
                $value = substr($value, 0, strpos($value, ';'));
            }
            $user_langs[$key] = $value;

            //looks for direct matches
            foreach($site_langs as $sitelang){
                $lcSiteLang = "_" . strtolower(trim($sitelang));
                $lcUserLang = strtolower(trim($value));
                $pos = strpos($lcSiteLang, $lcUserLang);

                if (($pos > 0 ) && !$found){
                    $newLang = $sitelang;
                    $found = true;
                }
            }

            //looks for indirect matches (matching language but ignoring locale)
            foreach($site_langs as $sitelang){
                //remove _XX from site language and user language
                $lcSiteLang = "_" . strtolower(substr(trim($sitelang), 0, 2));
                $lcUserLang = strtolower(substr(trim($value), 0, 2));

                $pos = strpos($lcSiteLang, $lcUserLang);
                if (($pos > 0 ) && !$found){
                    $newLang = $sitelang;
                    $found = true;
                }
            }
        }
        if (!$found){
            if(defined('DEFAULT_LOCALE')){
                $newLang = DEFAULT_LOCALE;
            } else {
                $newLang = 'en_US';
            }
        }
    }
    $User->Lang = $newLang;

    //saves the user back with the new language setting
    $_SESSION['AnonUser'] = $User;
}


/**
 * Sets the locale (governs gettext) according to preferred language
 */
putenv("LC_ALL=$newLang.UTF-8");
setlocale(LC_ALL, "$newLang.UTF-8");
bindtextdomain('active_agenda', LOCALE_PATH);
textdomain('active_agenda');

setTimeStamp('page_startup_end');
//END OF page_startup.php code

include_once CLASSES_PATH . '/grids.php';
include_once CLASSES_PATH . '/modulefields.php';

include_once $theme .'/component_html.php';

//get the record ID
$recordID = 0;
if(isset($_GET['rid'])){
    $recordID = intval($_GET['rid']);
    if($recordID == 0){
        if(strlen($_GET['rid']) >= 3){
            $recordID = substr($_GET['rid'], 0, 5);
        }
    }
}

$ScreenName = addslashes($_GET['scr']);
$jsIncludes = '';
$screenPhrase = '';

$moduleInfo = GetModuleInfo($ModuleID);

//if no screen name was supplied, go to the first screen
if(empty($ScreenName)){
    $ScreenName = $moduleInfo->getProperty('firstAnonEditScreen');
} else {
    //validate the supplied ScreenName
    $includeFile = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_ScreenList.gen";
    if(file_exists($includeFile)){
        include $includeFile; //provides $screenList
        if(!isset($screenList[$ScreenName])){
            trigger_error(gettext("The address that you typed or clicked is invalid.|This module has no screen by the name")." '$ScreenName'.", E_USER_ERROR);
        }
        if('anoneditscreen' != $screenList[$ScreenName]){
            trigger_error("'$ScreenName' ".gettext("is not an AnonEditScreen (it's a")." '{$screenList[$ScreenName]}').", E_USER_ERROR);
        }
    } else {
        trigger_error(gettext("The address that you typed or clicked is invalid.|Could not find a Screen List to verify the requested screen."), E_USER_ERROR);
    }
}

$filename = GENERATED_PATH . "/{$ModuleID}/{$ModuleID}_AnonEdit{$ScreenName}.gen";

//check for cached page for this module
if (!file_exists($filename)){
    trigger_error(gettext("Could not find file")." '$filename'. ", E_USER_ERROR);
}

$messages = array(); //init

//the included file sets $content variable used by template below
include($filename);
trace($getSQL, 'getSQL');
// XMLbase doesn't need this module
//include_once(GENERATED_PATH . '/moddr/moddr_GlobalViewGrid.gen');
if(!empty($ownerField) && isset($grid)){ //unfortunate name returned by include above
    $directionCount = $grid->getRecordCount();
    if(intval($directionCount) > 0){
        $content .= sprintf(
            POPOVER_DIRECTIONS,
            ShortPhrase($grid->phrase),
            $grid->render('anonedit.php', $qsArgs)
            );
    }
} else {
    $directionCount = 0;
}
//print "Owner org: {$data[$ownerField]}\n";

if(isset($guidanceGrid)){
    $content .= sprintf(
        POPOVER_GUIDANCE,
        ShortPhrase($guidanceGrid->phrase),
        $guidanceGrid->render('anonedit.php', $qsArgs)
    );
}

unset($grid);
include_once(GENERATED_PATH . '/res/res_GlobalViewGrid.gen');
if(!empty($ownerField) && isset($grid)){ //unfortunate name returned by include above
    $resourceCount = $grid->getRecordCount();
    if(intval($resourceCount) > 0){
        $content .= sprintf(
            POPOVER_RESOURCES,
            ShortPhrase($grid->phrase),
            $grid->render('anonedit.php', $qsArgs)
            );
    }
} else {
    $resourceCount = 0;
}

$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar_stripped.js"></script>'."\n";
$LangPrefix = substr($User->Lang, 0, 2);
$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/lang/calendar-'.$LangPrefix.'.js"></script>'."\n";
$jsIncludes .= '<script type="text/javascript" src="3rdparty/jscalendar/calendar-setup_stripped.js"></script>'."\n";
$jsIncludes .= '<script type="text/javascript">
function confirmDelete(sender){
    if(confirm(\''.gettext("Delete this record?").'\')){
        sender.form[\'Delete\'].value = "Delete";
        sender.form.submit();
    }
}
</script>'."\n";

$globalDiscussions = DISCUSSION_LINK_GLOBAL . $moduleInfo->getProperty('globalDiscussionAddress');
$localDiscussions = DISCUSSION_LINK_LOCAL . $moduleInfo->getProperty('localDiscussionAddress');

$screenPhrase = ShortPhrase($screenPhrase);

if(isset($_GET['sr'])){
    list($prevLink,$nextLink) = GetSeqLinks($ModuleID, $_GET['sr'], 'anonedit.php');
}

$moduleID = $ModuleID;
if($existing){
    $recordLabel = $recordLabelField;
    $title = $pageTitle.' - '.$screenPhrase;
} else {
    $recordLabel = sprintf(gettext("Entering a new %s record"), $singularRecordName);
    $title = $pageTitle.' - '.gettext("New Record");
}
//$recordID;
//$content;
//$globalDiscussions;
//$localDiscussions;

include_once $theme . '/no-tabs.template.php';
?>
