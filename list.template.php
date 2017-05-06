<?php
/**
 * HTML/PHP layout template for the List screens
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
    <link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css">
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
    <!--Reload the page to renew the naviagation frame-->
	<?php echo $JSredirect; ?>
	
    <!-- yahoo user interface library -->
	<link rel="stylesheet" type="text/css" href="3rdparty/yui/fonts/fonts-min.css">
	<link rel="stylesheet" type="text/css" href="3rdparty/yui/menu/assets/skins/sam/menu.css">
    <script type="text/javascript" src="3rdparty/yui/yahoo/yahoo-min.js"></script>
    <script type="text/javascript" src="3rdparty/yui/event/event-min.js"></script>
    <script type="text/javascript" src="3rdparty/yui/dom/dom-min.js"></script>
    <script type="text/javascript" src="3rdparty/yui/dragdrop/dragdrop-min.js"></script>
    <script type="text/javascript" src="3rdparty/yui/container/container-min.js"></script>	
	<script type="text/javascript" src="3rdparty/yui/yahoo-dom-event/yahoo-dom-event.js"></script>
	<script type="text/javascript" src="3rdparty/yui/menu/menu-min.js"></script>
    <!-- DataRequestor (could be replaced with yahoo's connection class) -->
    <script type="text/javascript" src="3rdparty/DataRequestor.js"></script>

    <!-- Active Agenda functions -->
    <script type="text/javascript" src="js/lib.js"></script>
    <script type="text/javascript" src="js/tabs.js"></script>

    <script type="text/javascript">
        var moduleID = '<?php echo $moduleID;?>';

        YAHOO.namespace("activeagenda");
        function yInit() {
<?php if(!empty($parentInfo)){ ?>
            // Instantiate a Panel from markup
            YAHOO.activeagenda.poprel = new YAHOO.widget.Panel("pop_relations", { width:"300px", visible:false, constraintoviewport:false, context:['relations','tr','br']} );
            YAHOO.activeagenda.poprel.render();
<?php } //end if ?>
            return true;
        }
	<?php if( !$User->Client['is_Mobile'] ){ ?>
        YAHOO.util.Event.addListener(window, "load", attachTabEffects);
	<?php } //end if ?>
		YAHOO.util.Event.addListener(window, "load", setupFormTooltips);
        YAHOO.util.Event.addListener(window, "load", yInit);
		
		oMenu = new YAHOO.widget.Menu("recordmenu"); 
        oMenu.subscribe("itemAdded", function (p_sType, p_aArgs) {
            var oMenuItem = p_aArgs[0];  
            oMenuItem.cfg.subscribe("configChanged", onMenuItemConfigChange);
        });
        oMenu.addItems([              
            <?php echo $MenuTabs;?>
            ]);
			
	function displayRecordMenu ( e, myTarget, ismousexy ) {
		thisMenu = oMenu.getItemGroups();
		<?php echo $MenuUrl;?>		
		
		if( ismousexy == 1 ){	
			if ( !e ) var e = window.event;
			var posx = 0;
			var posy = 0;				
			if( e.pageX || e.pageY ){
				posx = e.pageX;
				posy = e.pageY;
			}else if( e.clientX || e.clientY ){
				posx = e.clientX + document.body.scrollLeft
					+ document.documentElement.scrollLeft;
				posy = e.clientY + document.body.scrollTop
					+ document.documentElement.scrollTop;
			}	
			oMenu.cfg.setProperty( "xy", [posx-20,posy-10] );
			oMenu.render( document.body );
			oMenu.show();
		}else{			
			var xy = YAHOO.util.Dom.getXY( myTarget );
			xy[0] = xy[0]-40;
			xy[1] = xy[1]-15;
			oMenu.cfg.setProperty( "xy", xy );		
			oMenu.render( document.body ); 
			oMenu.show();		
		}
			
	}
    </script>
	<style type="text/css">
    /* Needed by YUI */
		div.yuimenu .bd {    
			zoom: normal;    
		}
	</style>
</head>
<body class="yui-skin-sam">
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
                    <a href="<?php echo PROVIDER_LINK ?>" target="_blank"><img src="<?php echo $theme_web; ?>/img/logo_thumb.png"/></a>
                </div>
<?php
                include_once($theme . '/icons.snip.php');
?>
                <div id="tabwrapper">
                    <div id="tabcontainer">
                        <div id="tabcontainer_inner">
<?php
                foreach ($tabs as $tab_key=>$tab_value) {
                    $tabprop = '';
                    if(isset($tab_value[2])){
                        $tabprop = $tab_value[2];
                    }
                    switch($tabprop){
                    case 'self':
?>
                            <div class="tabsel" id="<?php echo 'cont'.$tab_key; ?>">
                                <div class="tabb">
									<img class="lstpct" src="<?php echo $theme_web; ?>/img/list_black.png"/><br/>
									<?php echo ShortPhrase($tab_value[1]); ?>
									&nbsp;
                                </div>
                            </div>
<?php
                        break;
                    case 'disabled':
?>
                            <div class="tabdisabled">
                                <div class="tabb" id="<?php echo 'tab'.$tab_key; ?>" title="<?php echo addslashes(LongPhrase($tab_value[1])); ?>">
                                    <?php echo ShortPhrase($tab_value[1]); ?>
                                </div>
                            </div>
<?php
                        break;
                    case 'download':
                        $target = '';
                        if($User->browserInfo['is_IE']){
                            $target = ' target="'.$tab_value[2].'"';
                        }
?>
                            <div class="tabunsel" id="<?php echo 'cont'.$tab_key; ?>">
                                <a class="tabb" id="<?php echo 'tab'.$tab_key; ?>" href="<?php echo $tab_value[0]; ?>"<?php echo $target; ?> ><?php echo ShortPhrase($tab_value[1]);?></a>
                            </div>
<?php
                        break;
                    default:
                        if(empty($tab_value[3])){
                            $tab_class = 'tabunsel';
                        } else {
                            $tab_class = $tab_value[3];
                        }
?>
                            <div class="<?php echo $tab_class; ?>" id="<?php echo 'cont'.$tab_key; ?>">
                                <a class="tabb" id="<?php echo 'tab'.$tab_key; ?>" href="<?php echo $tab_value[0]; ?>" >
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

<?php 
        include_once($theme . '/dashcut.snip.php');
		include_once $theme . '/crumbs.snip.php';
?>
            </div><!-- end #sidearea -->
        </div><!-- end #sideshim -->        
        <?php echo $content;?>
		<div id="fnlf">
		<?php if( $fnAllowEdit ){ ?>
		<a href="list.php?mdl=modfn&filter=1&RelatedModuleID=<?php echo $ModuleID; ?>">
		<?php echo gettext("Module remarks for users"); ?>
		</a>
		<?php } ?>
		</div>
		<div id="pulf"><a href="list.php?mdl=pu&filter=1&RelatedModuleID=<?php echo $ModuleID; ?>">
		<?php echo gettext("Power users supporting this module"); ?>
		</a></div>
		<?php 
			echo $modFootnotes;
			echo $listReports;
		?>		
	</div><!-- end #content -->
<?php if(!empty($parentInfo)){ ?>
    <div id="pop_relations">
        <div class="hd">
            <?php echo gettext("Relations") ?>
        </div>
        <div class="bd" id="pop_relations_content"><?php echo gettext("No data"); ?>.</div>
        <div class="ft"></div>
    </div>
<?php 
    }
    include 'footer.snip.php';
?>
</body>
<?php if( $User->Client['is_Mobile'] ){ ?>
	<script type="text/javascript">
		window.parent.parent.scrollTo(0,0);
	</script>
<?php } ?>
</html>
<?php  echo getDuration();?>