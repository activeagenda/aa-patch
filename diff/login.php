<?php
/**
 * Handles logins
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

/**
 * Defines execution state as 'web'.  Several classes and
 * functions behave differently because of this flag.
 */
if(!defined('EXEC_STATE')){
    define('EXEC_STATE', 1);
}

//general settings
require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

$newLang='';
if(defined('DEFAULT_LOCALE')){
        $newLang = DEFAULT_LOCALE;
    } else {
        $newLang = 'en_US';
    }
putenv("LC_ALL=$newLang.UTF-8");
setlocale(LC_ALL, "$newLang.UTF-8");
bindtextdomain('active_agenda', LOCALE_PATH);
textdomain('active_agenda');

$msg = '';



//skip some stuff if this page was included by the logout page
if(!(defined('USER_LOGOUT') && USER_LOGOUT)){  


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

    //user object class
    require_once CLASSES_PATH . '/user.class.php';

    require_once PEAR_PATH . '/DB.php';
    $dbh = DB::connect(DB_DSN, array('persistent' => true));
    dbErrorCheck($dbh);

    //start session handling
    if (! session_start()) {
        //logs error "session could not be created"
        trigger_error("Session could not be created.", E_USER_ERROR);
    }


    if (isset($_GET['dest'])) {
        $dest = (base64_decode($_GET['dest']));
        if(false !== strpos($dest, 'frames.php')){
            $dest = 'home.php';
        } else {
            //block some XSS possibilities
            $dest = urldecode($dest);
            $dest = strtr($dest, "\r\n<>();", '       '); 
        }
        $dest_enc = base64_encode($dest);

        $RedirectTo = "dest=" . str_replace('\\', '', $dest_enc);
    } else {
        $RedirectTo = '';
    }

    if (isset( $_POST['UserName'])) {
        //need to check for cookie again (also done with JavaScript in the template file)
        if(empty($_COOKIE[SITE_SESSIONNAME])){
            $msg = '<div style="width:400px;margin:10px auto;border:3px solid #cc0000;padding:10px">';
            $msg .= '<h1 style="border:none;margin:0">Cookies Required</h1>';
            $msg .= '<p align="justify">You are seeing this message because your browser does not accept cookies. You might find <b><a href="http://www.google.com/cookies.html" target="_blank">these instructions</a></b> helpful.</p>';
            $msg .= '<p align="justify">Active Agenda does not require many cookies, but <b>one</b> cookie is very important. This cookie will last only until you log out, or until you close your browser.</p>';
            $msg .= '<p align="justify">Please note that you will <b>not be able to log in</b> to Active Agenda without enabling cookies.</p>';
            $msg .= '</div>';
        } else {
            //the User class will escape these
            $UserName = trim($_POST['UserName']);
            $Password = $_POST['Password'];

            $User = new User();

            if ( $User->Login($UserName, $Password)) {

                //preload module info
                global $dbh;

                //preload user's desktop shortcuts
                $SQL = "SELECT Link, Title FROM usrds WHERE PersonID = {$User->PersonID} ORDER BY Type, Title";
                $rows = $dbh->getAssoc($SQL);
                foreach($rows as $link => $title){
                    $_SESSION['desktopShortcuts'][$link] = $title;
                }

                //save the user object to the session
                $_SESSION['User'] = $User;
                $User->advanceSessionTimeout();

                if($User->requireNewPassword){
                    header("Location:myPassword.php?toFrames=1&" . $RedirectTo);
                    exit;
                } else {
                    header("Location:frames.php?" . $RedirectTo);
                    exit;
                }
            } else {

                //login failed, so we reload the login screen and display
                //an error message.
                //in the future we might want to track number of failed
                //and lock out connections from logging in for a
                //specified time...

                $msg = "<b>" . gettext("Login failed.") . "</b><br>\n";
                $msg .= gettext("Please check the spelling of username and password") . ".<r>\n";
                $msg .= gettext("Check that CAPS LOCK is not accidentally on.");
            }
        }
    } else {
        //print "no user name";
    }
}

include_once INCLUDE_PATH . '/web_util.php';
$theme = GetThemeLocation();
$theme_web = GetThemeWebLocation();

$title = SITE_NAME;
$errmsg = $msg;
//$RedirectTo;

include_once $theme.'/login.template.php';
?>