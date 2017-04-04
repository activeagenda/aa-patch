<?php
/**
 * HTML/PHP layout template for the Login screen
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

if(!defined('EXEC_STATE') || EXEC_STATE != 1){
    print gettext("This file should not be accessed directly.");
    trigger_error("This file should not be accessed directly.", E_USER_ERROR);
    exit;
}

$introFile = APP_FOLDER . '/login_intro.php';
if(file_exists($introFile)){
    include_once($introFile); //returns $introText, $defaultUserName, $defaultPassword
} else {
    $introText = gettext("Please Sign In");
    $defaultUserName = '';
    $defaultPassword = '';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo $title;?></title>
    <link rel="shortcut icon" href="<?php echo $theme_web;?>/img/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css" />
    <!--[if lt IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" />
    <![endif]-->
    <!--[if gte IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie7.css" />
    <![endif]-->
    <script type="text/javascript">
        <!--
        //break out of the frames
        if(top.frames.length != 0){
            top.location = self.document.location;
        }
        -->
    </script>
    <style type="text/css" media="screen">
        div.loginTitle {
            font: bold 26pt "Verdana", "Helvetica", "Arial", sans-serif;
            text-align: center;
            color: #0c2578;
        }
        body {
            background-color: #ffffff;
            background-image:url('<?php echo $theme_web; ?>/img/watermark.gif');
            background-repeat: no-repeat;
            background-position: left center;
            text-align: center;
        }
        .frm, .flbl, .fval, .flbb {
            background: transparent;
            color: #0c2578;
        }
        .flbb {
            text-align:left;
        }
        #copyright {
            border-style: groove;
        }
    </style>
</head>
<body onload="document.forms[0].UserName.focus()">
    <br />
    <br />
    <center><img src="<?php echo $theme_web; ?>/img/logo_big.png" alt="logo image"/></center>
    <br />

    <div class="loginTitle"><?php echo $title;?></div>
    <script type="text/javascript">
        <!--
         //courtesy of Peter-Paul Koch, www.quirksmode.org
         //http://www.quirksmode.org/about/copyright.html
         function readCookie(name) {
            var nameEQ = name + "=";
            var ca = document.cookie.split(';');
            for(var i=0;i < ca.length;i++) {
               var c = ca[i];
               while (c.charAt(0)==' ') c = c.substring(1,c.length);
               if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
            }
            return null;
         }
         //detect that cookies are enabled:
         if(!readCookie('<?php echo SITE_SESSIONNAME; ?>')){
            document.write('<div style="width:400px;margin:10px auto;border:3px solid red;padding:10px">');
            document.write('<h1 style="border:none;margin:0">Cookies Required<\/h1>');
            document.write('<p align="justify">You are seeing this message because your browser does not accept cookies. You might find <b><a href="http://www.google.com/cookies.html" target="_blank">these instructions<\/a><\/b> helpful.<\/p>');
            document.write('<p align="justify">Active Agenda does not require many cookies, but <b>one<\/b> cookie is very important. This cookie will last only until you log out, or until you close your browser.<\/p>');
            document.write('<p align="justify">Please note that you will <b>not be able to log in<\/b> to Active Agenda without enabling cookies.<\/p>');
            document.write('<\/div>');
         }
        -->
    </script>
    <noscript>
        <div style="width:400px;margin:10px auto;border:3px solid red;padding:10px">
            <h1 style="border:none;margin:0">JavaScript Required</h1>
            <p align="justify">You are seeing this message because <b>JavaScript is disabled in your browser</b>, or perhaps not available at all.</p>
            <p align="justify">Please <b>enable JavaScript</b> in your browser settings before logging in. You might find <b><a href="http://www.google.com/support/bin/answer.py?answer=23852" target="_blank">these instructions</a></b> useful. If your browser does not support JavaScript at all, please use one that does.</p>
            <p align="justify">Without JavaScript, <b>many features in the application will not work</b>. In fact, you will not be able to do much at all, since the navigation menu requires JavaScript.</p>
        </div>
    </noscript>
<?php
    if(!empty($logout_message)){
?>
    <p align="center"><?php echo gettext($logout_message) ?></p>
<?php
    } else {
?>
    <p align="center"><?php echo gettext($introText) ?></p>
<?php
    }
    //error message "login failed"
    if ($errmsg != null) {
        print("<p class=\"errmsg\" align=\"center\">$errmsg</p>\n");
    }
?>
    <form action="login.php?<?php echo $RedirectTo;?>" method="post">
        <table class="frm" style="margin: 0 auto">
        <tr>
            <td class="flbl">
                <?php echo gettext("Username:");?>
            </td>
            <td class="fval">
                <input class="edt" type="text" name="UserName" size="20" maxlength="25" value="<?php echo $defaultUserName;?>" />
            </td>
        </tr>
        <tr>
            <td class="flbl">
                <?php echo gettext("Password:");?>
            </td>
            <td class="fval">
                <input class="edt" type="password" name="Password" size="20" maxlength="25" value="<?php echo $defaultPassword;?>" />
            </td>
        </tr>
        <tr>
            <td class="flbb">
            </td>
            <td class="flbb">
                <input class="btn" type="submit" name="Submit" value="<?php echo gettext('Sign In');?>" />
            </td>
        </tr>
        </table>
    </form>
<?php 
    include 'footer.snip.php';
?>
</body>
</html>
