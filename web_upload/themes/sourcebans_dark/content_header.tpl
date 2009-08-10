<div id="msg-red-debug" style="display:none;" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Debug</b>
	<br />
	<div id="debug-text">
	</div>
</div>

<div id="dialog-placement" style="vertical-align:middle;display:none;opacity:0;text-align:center;width:892px;margin:0 auto;position:fixed !important;position:absolute;overflow:hidden;top:10px;left:100px;"> 
<table width="460px" id="dialog-holder" class="dialog-holder" border="0" cellspacing="0" cellpadding="0" >
	<tbody width="460px">
	  <tr>
	    <td class="dialog-topleft"></td>
	    <td class="dialog-border"></td>
	    <td class="dialog-topright"></td>
	  </tr>
	  <tr>
	    <td class="dialog-border"></td>
	    <td class="box-content">
			<div id="dragbar" style="cursor:pointer">
			<ilayer width="100%" onSelectStart="return false">
			<layer width="100%" onMouseover="dragswitch=1;if (ns4) drag_drop_ns(dialog-placement)" onMouseout="dragswitch=0">
	    	<h2 align="left" id="dialog-title" class="ok"></h2>
	        </layer></ilayer></div>
	        <div class="dialog-content" align="left">
	        	<div class="dialog-body">
	            	<div class="clearfix">
	                    <div style="float: left; margin-right: 15px;" id="dialog-icon" class="icon-info">&nbsp;</div>
	                    <div style="width:360px;float: right; padding-bottom: 5px; font-size: 11px;" id="dialog-content-text"></div>
	                </div>
	            </div>
	            <div class="dialog-control" id="dialog-control">    
	            </div>
	        </div>
		</td>
	    <td class="dialog-border"></td>
	  </tr>
	  <tr>
	    <td class="dialog-bottomleft"></td>
	    <td class="dialog-border"></td>
	    <td class="dialog-bottomright"></td>
	  </tr>
	</tbody>
</table>
</div>


<div id="content_title">
	{$main_title}
</div>
<div id="breadcrumb">
</div>
<div id="content">

