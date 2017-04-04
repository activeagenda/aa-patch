/**
 * javascript functions used in support documentation for displaying video
 * author         Mattias Thorslund <mthorslund@activeagenda.net>
 * copyright      2003-2009 Active Agenda Inc.
 * license        http://www.activeagenda.net/license
 **/

/**
<DocumentationSection sectionID="Introduction" title="Wprowadzenie">
<![CDATA[		
	<div id='introduction' align="center"/><script type='text/javascript'>play_FLV( 'introduction', 'tut_intro.flv')</script>
]]>
</DocumentationSection>
**/
 
function playFLV( div_id, tutorial){
  var s1 = new SWFObject('3rdparty/jw/player.swf','ply','720','540','9','#ffffff');
  s1.addParam('allowfullscreen','true');
  s1.addParam('allowscriptaccess','always');
  s1.addParam('wmode','opaque'); 
  s1.addParam('flashvars','file='+video_path+'/'+tutorial+'&frontcolor=666666&lightcolor=3333FF&stretching=fill');
  s1.write( div_id );
}

/**
<p><a href="#" onclick="playCamtasiaScreencast('bstaAddNewRecord')" >Po kliknięciu w link tutorial otworzy się w nowym oknie i automatycznie rozpocznie odtwarzanie.</a></p>
**/

function playCamtasiaScreencast ( demoname ) {
	window.open( video_path+'/'+demoname+'/'+demoname+'.html', '_blank', 'menubar=no' );
}
