<form action="" method="post">
  <div id="admin-page-content">
  <div id="msg-green" style="display:none;">
    <i><img src="./images/yay.png" alt="Warning" /></i>
    <b>Admin Updated</b>
    <br />
    The admin info has been updated.<br /><br />
    <i>Redirecting back to admins page</i>
  </div>
  <div id="add-group">
    <h3>Admin Groups</h3>
    For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
    Choose the new groups that you want <b>{$group_admin_name}</b> to appear in.<br /><br />
    <table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
      <tr>
        <td valign="middle"><div class="rowdesc">{help_icon title="Web Group" message="Choose the group you want this admin to appear in for web permissions"}Web Admin Group</div></td>
        <td>
          <div align="left" id="wadmingroup">
              <select name="wg" id="wg" class="submit-fields">
                <option value="-2">Please Select...</option>
                <option value="-1">No Group</option>
                <optgroup label="Groups" style="font-weight:bold;">
              {$web_lst}
            </optgroup>
              </select>
            </div>
            <div id="group.msg" class="badentry"></div>
            </td>
      </tr>
      
      <tr id="nsgroup" valign="top" style="height:5px;overflow:hidden;">
     </tr>
     
     <tr>
        <td valign="middle"><div class="rowdesc">{help_icon title="Server Group" message="Choose the group you want this admin to appear in for server admin permissions"}Server Admin Group </div></td>
        <td><div align="left" id="wadmingroup">
          <select name="sg" id="sg" class="submit-fields">
            <option value="-2">Please Select...</option>
            <option value="-1">No Group</option>
            
            <optgroup label="Groups" style="font-weight:bold;">
          {$group_lst}
        </optgroup>
            </select>
            </div>
            <div id="group.msg" class="badentry"></div>
            </td>
      </tr>
      
      <tr id="nsgroup" valign="top" style="height:5px;overflow:hidden;">
     </tr>
     
      <tr>
        <td>&nbsp;</td>
        <td>
          {sb_button text="Save Changes" class="ok" id="agroups" submit=true}
          &nbsp;
          {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="aback"}
          </td>
      </tr>
    </table>
    <script>
      $('wg').value = {$group_admin_id};
      {if $tmp}
      $('sg').value = {$tmp};
      {else}
      $('sg').value = -1;
      {/if}
    </script>
    </div>
  </div>
</form>
