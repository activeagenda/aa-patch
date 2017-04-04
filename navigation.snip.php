<?php
/**
 * HTML/PHP layout template for the Navigation frame
 *
 * NOTE that this template is different in that it only customizes the <BODY> part 
 * of the document (excluding the BODY tag itself. <HEADER> is declared in 
 * web/navigation.php
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
?>

<table id="nav_table" cellspacing="0">
<tr>
<?php if( $User->Client['is_Mobile'] ){ ?>
<td id="nav_menu">
    <img src="<?php echo $theme_web; ?>/img/nav_starthere.gif" alt="<?php echo gettext("menu"); ?>"/>    
</td>
<td class="navicon">
    <a href="#" onclick="parent.frames[1].location = 'home.php'" title="<?php echo gettext("Home - Dashboard"); ?>">
        <img src="<?php echo $theme_web; ?>/img/nav_dashboard.png" alt="<?php echo gettext("dashboard"); ?>"/>        
    </a>
</td>
<td class="navicon" id="nav_shortcuts">   
        <img src="<?php echo $theme_web; ?>/img/nav_bugreport.gif" alt="<?php echo gettext("Shortcuts"); ?>"/>         
</td>
<?php }else{ ?>
<td id="nav_menu">
	<?php echo gettext("Main Menu"); ?><br/>
    <img src="<?php echo $theme_web; ?>/img/nav_starthere.gif" alt="<?php echo gettext("menu"); ?>"/>    
</td>
<td class="navicon">
    <a href="#" onclick="parent.frames[1].location = 'home.php'" title="<?php echo gettext("Home - Dashboard"); ?>">
		<?php echo gettext("Dashboard") ?><br/>
        <img src="<?php echo $theme_web; ?>/img/nav_dashboard.png" alt="<?php echo gettext("dashboard"); ?>"/>        
    </a>
</td>
<td class="navicon" id="nav_shortcuts"> 
		<?php echo gettext("my Shortcuts"); ?><br/>   
        <img src="<?php echo $theme_web; ?>/img/nav_bugreport.gif" alt="<?php echo gettext("Shortcuts"); ?>"/>         
</td>
<?php 	} ?>
<td id="nav_sitename">
    <span id="nav_stnm"><?php echo SITE_NAME; ?></span> <?php echo $user_info;?>
</td>
<?php if( !$User->Client['is_Mobile'] ){ ?>
<?php 	if( TUTORIAL ){ ?>
<td class="navicon" id="nav_guide">
    <a href="#" title="<?php echo gettext("Quick Start Guide"); ?>" onclick="open('frames_popup.php?dest=c3VwcG9ydERvY1ZpZXcucGhwP21kbD10dXQ=', 'documentation', 'toolbar=0,scrollbars=1,width=1024,height=600');">
        <?php echo gettext("Quick Guide"); ?><br/>
		<img src="<?php echo $theme_web; ?>/img/nav_quick_guide.gif" alt="<?php echo gettext("quickstart"); ?>"/>        
    </a>
</td>
<?php 	} 
		if( GLOSSARY ){ ?>
<td class="navicon">
    <a href="#" title="<?php echo gettext("Look up a term in the Glossary"); ?>" onclick="open('frames_popup.php?dest=Z2xvc3NhcnkucGhw', 'glossary', 'toolbar=0,scrollbars=1,width=600,height=600');">
        <?php echo gettext("Glossary"); ?><br/>
		<img src="<?php echo $theme_web; ?>/img/nav_glossary.gif" alt="<?php echo gettext("glossary"); ?>"/>        
    </a>
</td>
<?php 	} 
		if( GLOBAL_FORUM ){ ?>
<td class="navicon">
    <a href="<?php echo FORUM_LINK_GLOBAL ?>" target="_blank" title="<?php echo gettext("Dicusssion Forums"); ?>">
        <?php echo gettext("Forums"); ?><br/>
		<img src="<?php echo $theme_web; ?>/img/nav_discussions.gif" alt="<?php echo gettext("discussion forums"); ?>"/>        
    </a>
</td>
<?php 	} 
		if( BUG_REPORT ){ ?>
<td class="navicon">
    <a href="<?php echo BUG_REPORT_LINK.'?subject='.$User->UserName.'@'.SERVER_EXT_ADRR.'-> ' ?>" target="_blank" title="<?php echo gettext("File a Bug Report or Feature Request"); ?>">
        <?php echo gettext("Report Bug"); ?><br/>
		<img src="<?php echo $theme_web; ?>/img/nav_email.gif" alt="<?php echo gettext("report bug"); ?>"/>        
    </a>
</td>
<?php 	} ?>
<td class="navicon">
    <a href="#" onclick="refreshThePage()">
		<?php echo gettext("Reload"); ?><br/>
        <img src="<?php echo $theme_web; ?>/img/nav_reload_page.gif" title="<?php echo gettext("Reload the page below"); ?>" alt="<?php echo gettext("reload"); ?>"/>
        
    </a>
</td>

<td class="navicon">
    <a href="logout.php">
        <?php echo gettext("Log Out"); ?>
		<img src="<?php echo $theme_web; ?>/img/nav_exit.gif" title="<?php echo gettext("Log&nbsp;out"); ?>" alt="<?php echo gettext("logout"); ?>"/>        
    </a>
</td>
<?php }else{ ?>
<td class="navicon">
    <a href="logout.php">
		<img src="<?php echo $theme_web; ?>/img/nav_exit.gif" title="<?php echo gettext("Log&nbsp;out"); ?>" alt="<?php echo gettext("logout"); ?>"/>        
    </a>
</td>
<?php 	} ?>
</tr>
</table>