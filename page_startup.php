<?php
/**
 * General include file to be used by most pages accessed from a browser.
 *
 * The general_include file handles the general setup and initialization of
 * an Active Agenda page request.  It starts the session handling, checks the
 * user authentication, and sets up the language to be used on the page.
 * 
 * This file should be included by any file accessed from a browser, except 
 * for login and logout files (they use the session differently).  This file 
 * should be included at the top of the main file requested in a page request, 
 * just after the general.config include (which must precede it).  
 *
 * If the requested file makes use of objects in the session whose classes are 
 * not included by this file, those includes must be placed before the include 
 * for this file.  Pop-up files define an IS_POPUP constant; that should also 
 * precede this file.
 *
 * PHP version 5
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
 * @version        SVN: $Revision: 1616 $
 * @last-modified  SVN: $Date: 2009-05-09 03:16:17 +0200 (So, 09 maj 2009) $
 */


/**
 * Defines execution state as 'web'.  Several classes and
 * functions behave differently because of this flag.
 */
DEFINE('EXEC_STATE', 1);



/**
 * Class file includes.
 */
require_once CLASSES_PATH . '/user.class.php';




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
 * Sets custom exception handler
 */
set_exception_handler('handleException');

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
if( isset($_SESSION['User']) ){
    $User = $_SESSION['User'];
} else {
    $User = null;
}

/**
 * Gets the theme, or falls back to the DEFAULT_THEME
 */
$theme = GetThemeLocation();
$theme_web = GetThemeWebLocation();

/**
 * Check that user is logged in and that session hasn't expired.  Otherwise, 
 * redirect to login page.  
 * 
 * In order to make session timouts less disruptive, we preserve the request 
 * string so that the login page can take the user back to the requested 
 * page. Encoding is used so that the request can be preserved correctly.
 */
if( empty($User) || intval($_SESSION['Timeout']) < time() ){

    if( defined('IS_POPUP') && IS_POPUP ){
        die(gettext("Your session has timed out (or you have logged out). Please close this window and log in from the main window."));
    }elseif( defined('IS_RPC') && IS_RPC ) {
        die('session timeout');
    }else{
        $redirectLink = base64_encode($_SERVER['REQUEST_URI']);
        header("Location:login.php?dest='$redirectLink'");
        exit;
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
 *  Checks for password update requirement
 */
if( $User->requireNewPassword ){
    //redirects to password screen directly
    if( false === strpos($_SERVER['PHP_SELF'], 'myPassword.php') ){
        $redirectLink = base64_encode($_SERVER['REQUEST_URI']);
        header("Location:myPassword.php?dest='$redirectLink'");
        exit;
    }
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
$qsArgs = $_GET;
unset($qsArgs['shortcut']);
$qs = MakeQS($qsArgs);



/**
 * Retrieves (and sanitizes) the requested module ID, if any.
 *
 * Module IDs are 3-5 characters long.
 */
if( isset($_GET['mdl']) ){
    $ModuleID = substr(addslashes($_GET['mdl']), 0, 5);
}else{
    if( isset($_GET['mdle']) ){
        $ModuleID = substr(addslashes($_GET['mdle']), 0, 5);
    }
    $ModuleID = '';
}

/**
 * Tells browser we intend to send utf-8
 */
header('Content-Type: text/html; charset=UTF-8');

/**
 * Determines the user's preferred language
 */
if( isset($User->Lang) ){
    $newLang = $User->Lang;
}else{
    //sniff the browser settings and try to match
    $site_langs = array('sv_SE', 'en_US');
    $user_langs = preg_split('/,/', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);

    if( empty($user_langs[0]) ){
        //$newLang = 'en_US';
        if( defined('DEFAULT_LOCALE') ){
            $newLang = DEFAULT_LOCALE;
        }else{
            $newLang = 'en_US';
        }
    }else{
        $found = false;
        foreach( $user_langs as $key => $value ){
            $value = str_replace('-', '_', $value);
            if( strpos($value, ';') ){
                $value = substr($value, 0, strpos($value, ';'));
            }
            $user_langs[$key] = $value;

            //looks for direct matches
            foreach( $site_langs as $sitelang ){
                $lcSiteLang = "_" . strtolower(trim($sitelang));
                $lcUserLang = strtolower(trim($value));
                $pos = strpos($lcSiteLang, $lcUserLang);

                if( ($pos > 0 ) && !$found ){
                    $newLang = $sitelang;
                    $found = true;
                }
            }

            //looks for indirect matches (matching language but ignoring locale)
            foreach( $site_langs as $sitelang ){
                //remove _XX from site language and user language
                $lcSiteLang = "_" . strtolower(substr(trim($sitelang), 0, 2));
                $lcUserLang = strtolower(substr(trim($value), 0, 2));

                $pos = strpos($lcSiteLang, $lcUserLang);
                if( ($pos > 0 ) && !$found ){
                    $newLang = $sitelang;
                    $found = true;
                }
            }
        }
        if( !$found ){
            if( defined('DEFAULT_LOCALE') ){
                $newLang = DEFAULT_LOCALE;
            }else{
                $newLang = 'en_US';
            }
        }
    }
    $User->Lang = $newLang;

    //saves the user back with the new language setting
    $_SESSION['User'] = $User;
}


/**
 * Sets the locale (governs gettext) according to preferred language
 */
putenv("LC_ALL=$newLang.UTF-8");
setlocale(LC_ALL, "$newLang.UTF-8");
bindtextdomain('active_agenda', LOCALE_PATH);
textdomain('active_agenda');
$lang639_1 = substr( $newLang, 0, 2 );
setTimeStamp('page_startup_end');

?>
