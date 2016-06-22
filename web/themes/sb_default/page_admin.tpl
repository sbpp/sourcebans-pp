<h3>Please select an option to administer.</h3>
<div id="cpanel">
	<ul>
		{if $access_admins}
			<li>
				<a href="index.php?p=admin&amp;c=admins">
				<img src="themes/default/images/admin/admins.png" alt="Admin Settings" border="0" /><br />
				Admin Settings
		  		</a>
			</li>
		{/if}
		{if $access_servers}
			<li>
				<a href="index.php?p=admin&amp;c=servers">
				<img src="themes/default/images/admin/servers.png" alt="Server Admin" border="0" /><br />
				Server Settings
		  		</a>
			</li>
		{/if}
		{if $access_bans}
			<li>
				<a href="index.php?p=admin&amp;c=bans">
				<img src="themes/default/images/admin/bans.png" alt="Edit Bans" border="0" /><br />
				Bans
		  		</a>
			</li>
		{/if}
		{if $access_groups}
			<li>
				<a href="index.php?p=admin&amp;c=groups">
				<img src="themes/default/images/admin/groups.png" alt="Edit Groups" border="0" /><br />
				Group Settings
		  		</a>
			</li>
		{/if}
		{if $access_settings}
			<li>
				<a href="index.php?p=admin&amp;c=settings">
				<img src="themes/default/images/admin/settings.png" alt="SourceBans Settings" border="0" /><br />
				Webpanel Settings
		  		</a> 
			</li>
		{/if}
		{if $access_mods}
			<li>
				<a href="index.php?p=admin&amp;c=mods">
				<img src="themes/default/images/admin/mods.png" alt="Mods" border="0" /><br />
				Manage Mods
		  		</a>
			</li>
		{/if}
	</ul>
</div>	
<br />

<table width="100%" border="0" cellpadding="3" cellspacing="0">
	<tr>
		<td width="33%" align="center"><h3>Version Information</h3></td>
		<td width="33%" align="center"><h3>Admin Information</h3></td>
		<td width="33%" align="center"><h3>Ban Information</h3></td>
	</tr>
	<tr>
		<td>Latest release: <strong id='relver'>Please Wait...</strong></td>
		<td>Total admins: <strong>{$total_admins}</strong></td>
		<td>Total bans: <strong>{$total_bans}</strong></td>
	</tr>
	<tr>
		<td>
			{if $sb_svn}
				Latest Git: <strong id='svnrev'>Please Wait...</strong>
			{/if}		
		</td>
		<td>&nbsp;</td>
		<td>Connections Blocked: <strong>{$total_blocks}</strong></td>
	</tr>
	<tr>
		<td id='versionmsg'>Please Wait...</td>
		<td> <strong> </strong></td>
		<td>Total demo size: <strong>{$demosize}</td>
	</tr>
	<tr>
		<td width="33%" align="center"><h3>Server Information</h3></td>
		<td width="33%" align="center"><h3>Protest Information</h3></td>
		<td width="33%" align="center"><h3>Submission Information</h3></td>
	</tr>
	<tr>
		<td>Total Servers: <strong>{$total_servers}</strong></td>
		<td>Pending Protests: <strong>{$total_protests}</strong></td>
		<td>Pending Submissions: <strong>{$total_submissions}</strong></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td>Archived Protests: <strong>{$archived_protests}</strong></td>
		<td>Archived Submissions: <strong>{$archived_submissions}</strong></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
	</tr>
</table>
<script type="text/javascript">xajax_CheckVersion();</script>