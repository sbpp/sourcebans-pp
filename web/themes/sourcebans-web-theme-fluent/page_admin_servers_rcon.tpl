-{if NOT $permission_rcon}-
  <section class="error padding">
    <i class="fas fa-exclamation-circle"></i>
  <div class="error_title">Oops, there's a problem (╯°□°）╯︵ ┻━┻</div>

  <div class="error_content">
    Access Denied!
  </div>

  <div class="error_code">
    Error code: <span class="text:bold">403 Forbidden</span>
  </div>
</section>
-{else}-
<div id="admin-page-content">
  <div class="admin_tab_content_title">
    <h2 class="fas fa-laptop-code"> RCON Console</h2>
  </div>

  <div class="padding">
    <div id="rcon" class="form-text form-text:rcron">
      <pre>
        <div id="rcon_con">***********************************************************<br />*&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;SourceBans RCON console&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;Type your comand in the box below and hit enter&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;Type 'clr' to clear the console&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />***********************************************************<br />
        </div>
        </pre>
    </div>

    <div class='flex flex-ai:end flex-jc:space-between margin-top'>
      <div class="flex:11 margin-right">
        <label for="cmd" class="form-label form-label:bottom">Command:</label>
        <input type="text" class="form-input form-full" id="cmd" name="cmd" />
      </div>

      <input type="button" onclick="SendRcon();" class="button button-success btn" id="rcon_btn" value="Send">
    </div>
  </div>
</div>

<script>
  $E('html').onkeydown = function(event) {
    var event = new Event(event);
    if (event.key == 'enter') SendRcon();
  };

  function SendRcon() {
    xajax_SendRcon('-{$id}-', $('cmd').value, true);
    $('cmd').value = 'Executing, Please Wait...';
    $('cmd').disabled = 'true';
    $('rcon_btn').disabled = 'true';

  }

  var scroll = new Fx.Scroll($('rcon'),{duration: 500, transition: Fx.Transitions.Cubic.easeInOut});
    if (scroll) scroll.toBottom();
  </script>
-{/if}-