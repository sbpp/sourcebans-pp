<div id="login"> 
	<div id="login-content">
	  	<div id="loginUsernameDiv">
	    	<label for="loginUsername">Username:</label><br />
	    	<input id="loginUsername" class="loginmedium" type="text" name="username"value="" />
		</div>
		<div id="loginUsername.msg" class="badentry"></div>
  		
		<div id="loginPasswordDiv">
	    	<label for="loginPassword">Password:</label><br />
	   		<input id="loginPassword" class="loginmedium" type="password" name="password" value="" />
		</div>
		<div id="loginPassword.msg" class="badentry"></div>
	  	
		<div id="loginRememberMeDiv">
	    	<input id="loginRememberMe" type="checkbox" class="checkbox" name="remember" value="checked" vspace="5px" />    <span class="checkbox" style="cursor:pointer;" onclick="($('loginRememberMe').checked?$('loginRememberMe').checked=false:$('loginRememberMe').checked=true)">Remember me</span>
  		</div>
		
  		<div id="loginSubmit">
			<a href="steamopenid.php" style="float:left;"><img src="images/steamlogin.png"></a>
			
			-{sb_button text="Login" onclick=$redir class="ok" id="alogin" submit=false}-
		</div>
		
		<div id="loginOtherlinks">
			<a href="?">Back to the Homepage</a> - <a href="index.php?p=lostpassword">Lost your password?</a>
		</div>
	</div>
</div>
	
<script>
	$E('html').onkeydown = function(event){
	    var event = new Event(event);
	    if (event.key == 'enter' ) -{$redir}-
	};$('loginRememberMeDiv').onkeydown = function(event){
	    var event = new Event(event);
	    if (event.key == 'space' ) $('loginRememberMeDiv').checked = true;
	};$('button').onkeydown = function(event){
	    var event = new Event(event);
	    if (event.key == 'space' ) -{$redir}-
	};
</script>