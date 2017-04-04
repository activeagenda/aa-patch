<?php
/**
 * The code that closes the popup frame
 *
 * LICENSE NOTE:
 *
 * Copyright  2003-2008 Active Agenda Inc., All Rights Reserved.
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
 * copyright      2003-2008 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

require_once '../config.php';
set_include_path(PEAR_PATH . PATH_SEPARATOR . get_include_path());

//this causes session timeouts to display a message instead of redirecting to the login screen
DEFINE('IS_POPUP', true);

//main include file - performs all general application setup
require_once INCLUDE_PATH . '/page_startup.php';

setTimeStamp('template_rendering_startup');
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta http-equiv="Content-Language" content="<?php echo TINY_MCE_LANG; ?>">
<meta name="robots" content="noindex, nofollow">
<title>Popup navigation frame</title>
<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style.css">
<!--[if IE]>
<link rel="stylesheet" type="text/css" href="<?php echo $theme_web; ?>/style-ie.css" >
<![endif]-->
</head>
<?php if( $_GET['clrf'] == 1 ){ ?>
<body id="navframe" onload="callingUrl=self.parent.opener.location.href;">    
	<script type="text/javascript">
        function reloadAndClose(){
            self.parent.close();
            self.parent.opener.focus();
            if(self.parent.opener.document.forms.length > 0){
                self.parent.opener.document.forms[0].submit();
            } else {
				if( callingUrl == self.parent.opener.location.href ){					
					self.parent.opener.location.reload(true);
				}
            }
        }
    </script>	
    <div id="popupTitleCR"><?php echo SITE_NAME; ?></div>	
    <div id="popupClose">        
        <a href="#" onclick="reloadAndClose()">
		<?php echo gettext("Close and Reload");?>&nbsp;&nbsp;<img src="<?php echo $theme_web; ?>/img/cross-button.png"/>
		</a>
    </div>	
</body>
<?php }else{ ?>
<body id="navframe">
    <div id="popupTitle"><?php echo SITE_NAME; ?></div>	
</body>
<?php } ?>
</html>
<?php  echo getDuration();?>