<?php
/**
 * HTML/PHP layout template for the Search screens
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
setTimeStamp('template_rendering_startup');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="<?php echo $lang639_1;?>">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="<?php echo TINY_MCE_LANG; ?>">
	<meta name="robots" content="noindex, nofollow">
    <title><?php echo $title;?></title>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css">
    <!--[if lt IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" >
    <![endif]-->
    <!--[if gte IE 7]>
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie7.css" >
    <![endif]-->
	
	<?php if( $User->Client['is_Mobile'] ){ ?>
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/mobile.css">
	<?php }else{ ?>
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/not_mobile.css">
	<?php } //end if ?>
	
	<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/print.css" media="print">
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/menuG5.css">

    <!-- yahoo user interface library -->
    <script type="text/javascript" src="3rdparty/yui/yahoo/yahoo-min.js"></script>
    <script type="text/javascript" src="3rdparty/yui/event/event-min.js" ></script>
    <script type="text/javascript" src="3rdparty/yui/dom/dom-min.js" ></script>
    <script type="text/javascript" src="3rdparty/yui/container/container-min.js" ></script>

    <script type="text/javascript" src="js/lib.js"></script>
    <script type="text/javascript" src="js/tabs.js"></script>
    <?php echo  $jsIncludes; ?>
    <script type="text/javascript">
        var pageLoaded = false;
        YAHOO.namespace("activeagenda");
	<?php if( !$User->Client['is_Mobile'] ){ ?> 	
        YAHOO.util.Event.addListener(window, "load", attachTabEffects);
        YAHOO.util.Event.addListener(window, "load", setupTabTooltips);
	<?php } //end if ?>		
        YAHOO.util.Event.addListener(window, "load", setupFormTooltips);
    </script>
</head>
<body onload="pageLoaded = true;">    
    <div id="content">        
        <div id="sideshim">
		 <div id="relations_label"><?php echo $parentInfo;?>
		<a href="#" title="&nbsp; &nbsp; <?php echo gettext("Module Documentation");?> &nbsp; &nbsp;" onclick="open('frames_popup.php?dest=<?php echo base64_encode('supportDocView.php?mdl='.$ModuleID);?>', 'documentation', 'toolbar=0,scrollbars=1,width=1024,height=600');"><img id="icDoc" src="<?php echo $theme_web; ?>/img/documentation.png" border="0" alt="<?php echo gettext("documentation") ?>"/></a>
		 </div>
		 <div id="pagetitle_label_lc">
				<img src="<?php echo $theme_web; ?>/img/list_blue.png"/>&nbsp;
				<?php echo $title;?>
         </div>
         <div id="sidearea">
            <div id="logo">
                <a href="<?php echo PROVIDER_LINK ?>" target="_blank"><img src="<?php echo $theme_web; ?>/img/logo_thumb.png" /></a>
            </div>
            <?php
                include_once($theme . '/icons.snip.php');
            ?>
            <div id="tabwrapper">
                <div id="tabcontainer">
                    <div id="tabcontainer_inner">
<?php
                        foreach ($tabs as $tab_key=>$tab_value) {
                            if(empty($tab_value[0])){
?>
                    <div class="tabsel" id="<?php echo 'cont'.$tab_key; ?>">
                        <div class="tabb">
							<img class="lstpct" src="<?php echo $theme_web; ?>/img/list_black.png"/><br/>
                            <?php echo  ShortPhrase($tab_value[1]); ?>							
                        </div>
                    </div>
<?php
                            } else {
?>
                    <div class="tabunsel" id="<?php echo 'cont'.$tab_key; ?>">
                        <a class="tabb" id="<?php echo 'tab'.$tab_key; ?>" href="<?php echo $tab_value[0]; ?>" title="<b><?php echo ShortPhrase($tab_value[1]);?>:</b><br/><br/><?php echo addslashes(LongPhrase($tab_value[1])); ?>">						
						<img class="lstpct" src="<?php echo $theme_web; ?>/img/list_white.png"/><br/>
						<?php echo ShortPhrase($tab_value[1]);?>
						</a>
                    </div>
<?php
                            }
                        }
?>
                    </div>
                </div>
            </div>
			<?php echo  $chartToDashHTML; ?>
<?php 
        include_once $theme . '/crumbs.snip.php';       
?>
           
        </div>
        </div>
        <?php echo $content;?>
    </div>
<?php 
    include 'footer.snip.php';
?>
</body>
<?php if( $User->Client['is_Mobile'] ){ ?>
	<script type="text/javascript">
		window.parent.parent.scrollTo(0,0);
	</script>
<?php } ?>
</html>
<?php  echo getDuration(); ?>