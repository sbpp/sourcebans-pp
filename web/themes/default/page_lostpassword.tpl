<div id="lostpassword"> 
	<div id="login-content">

		<div id="msg-red" style="display:none;">
			<i><img src="./images/warning.png" alt="Warning" /></i>
			<b>Error</b>
			<br />
			The email address you supplied is not registered on the system.</i>
		</div>
		<div id="msg-blue" style="display:none;">
			<i><img src="./images/info.png" alt="Warning" /></i>
			<b>Information</b>
			<br />
			Please check your email inbox (and spam) for a link which will help you reset your password.</i>
		</div>

	  	<h4>
	  		Please type your email address in the box below to have your password reset. 
	  	</h4><br />
	  	
  		<div id="loginPasswordDiv">
	    	<label for="email">Your E-Mail Address:</label><br />
	   		<input id="email" class="loginmedium" type="text" name="password" value="" />
		</div>
		
		<div id="loginSubmit">
			{sb_button text=Ok onclick="xajax_LostPassword($('email').value);" class=ok id=alogin submit=false}
		</div>
		
	</div>
</div>