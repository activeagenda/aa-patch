<?php
/**
 * HTML/PHP layout snippet for displaying the icons above tabs in most screens
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
<div id="icons">	
    <a href="#" title="&nbsp; &nbsp; <?php echo gettext("Module Documentation");?> &nbsp; &nbsp;" onclick="open('frames_popup.php?dest=<?php echo base64_encode('supportDocView.php?mdl='.$ModuleID);?>', 'documentation', 'toolbar=0,scrollbars=1,width=1024,height=600');"><img id="icDoc" src="<?php echo $theme_web; ?>/img/documentation.png" border="0" alt="<?php echo gettext("documentation") ?>"/></a>
	<?php if( isset($globalDiscussions) ){ ?>
    <a href="<?php echo $globalDiscussions;?>" title="&nbsp; &nbsp; <?php echo gettext("Feedback");?> &nbsp; &nbsp;" target="_blank" ><img id="icFor" src="<?php echo $theme_web; ?>/img/feedback.png" border="0" alt="<?php echo gettext("feedback") ?>"/></a>	
	<?php }?>
</div>