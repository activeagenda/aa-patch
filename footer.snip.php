<?php
/**
 * HTML/PHP layout snippet for the License footer
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

if(!defined('EXEC_STATE') || EXEC_STATE != 1){
    print gettext("This file should not be accessed directly.");
    trigger_error("This file should not be accessed directly.", E_USER_ERROR);
    exit;
}

?>
<div id="footerbar"><?php echo FOOTER_BAR;?></div>
<div id="copyrightwrapper">
<div id="copyright">
Copyright &#169; <a href="http://www.activeagenda.net" target="_blank">Active Agenda, Inc.</a> | 
<a href="http://www.activeagenda.net/sponsors" target="_blank">Sponsors</a> | <a href="http://www.activeagenda.net/contributors" target="_blank">Contributors</a><br/>
Distributed under the <a href="license.html" target="_blank">Reciprocal Public License</a><br/>
<a href="https://s1st.pl/regulations.html" target="_blank"><?php echo gettext('Web Site Regulations'); ?></a> | 
<a href="https://s1st.pl/privacy.html" target="_blank"><?php echo gettext('Site Privacy'); ?></a>
</div>
<?php if( !$noCogsVisible ){ ?>
<img  src="<?php echo $theme_web; ?>/img/cogs_sm.png"/>
<?php } ?>	
</div>
<br />
<br />