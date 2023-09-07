<h2 class="title text-center">Select an option to administer</h2>
<div class="admin_nav">
    <ul class="flex">
		{if $access_admins}
			 <li>
					<a href="index.php?p=admin&amp;c=admins">
					  <i class="fas fa-user"></i>
					  <span>Admin</span>
				  </a>
			 </li>
    	{/if}
        {if $access_bans}
            <li>
                <a href="index.php?p=admin&amp;c=bans">
                    <i class="fas fa-ban"></i>
                    <span>Bans</span>
                </a>
            </li>
        {/if}
		{if $access_bans}
            <li>
                <a href="index.php?p=admin&amp;c=comms">
                    <i class="fas fa-microphone-alt-slash"></i>
                    <span>Comms</span>
                </a>
            </li>
        {/if}
        {if $access_groups}
            <li>
                <a href="index.php?p=admin&amp;c=groups">
                    <i class="fas fa-users"></i>
                    <span>Group</span>
                </a>
            </li>
        {/if}
		 {if $access_servers}
            <li>
                <a href="index.php?p=admin&amp;c=servers">
                    <i class="fas fa-server"></i>
                    <span>Server</span>
                </a>
            </li>
        {/if}
        {if $access_settings}
            <li>
                <a href="index.php?p=admin&amp;c=settings">
                    <i class="fas fa-tools"></i>
                    <span>Webpanel</span>
                </a>
            </li>
        {/if}
        {if $access_mods}
            <li>
                <a href="index.php?p=admin&amp;c=mods">
                    <i class="fas fa-cubes"></i>
                    <span>Manage Mods</span>
                </a>
            </li>
        {/if}
    </ul>
</div>

<div class="admin_dashboard margin-top">
<div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-ban"></i> Bans - Comms</h2>
        </div>
        <div class="padding">
            <ul class="list-reset">
                <!-- WARNING: To fully fix this part use https://github.com/Rushaway/sourcebans-pp/commit/f05a4bcdfa59002970daeb0b8231ffc1b13c834c -->
                <li>Total bans : <span class="text:bold">{$total_bans}</span></li>
                <li>Total comms : <span class="text:bold">{$total_comms}</span></li>
                <li>Connections blocked : <span class="text:bold">{$total_blocks}</span></li>
            </ul>
        </div>
    </div>


    <div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-user-shield fa-1x"></i> Admin Information</h2>
        </div>
        <div class="padding">
		<ul class="list-reset" align="center">
					<li>We have a total of <span class="text:bold" style="font-size:xx-large"> <i style="color:#dd6b20">{$total_admins}</i></span> Admins throught all servers!</li>
					&nbsp;
					<li><i class="fas fa-rocket fa-1x"></i> Teamwork makes the <span class="text-primary">dream work</span>!</li>
					</ul>
				</div>
    </div>
	
	<div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-exclamation-circle"></i> Submission Stats</h2>
        </div>
        <div class="padding">
            <ul class="list-reset" align="right">
                <li>Pending : <span class="text:bold">{$total_submissions}</span></li>
                <li>Archived : <span class="text:bold">{$archived_submissions}</span></li>
            </ul>
        </div>
    </div>
	
    <div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-angry"></i> Protest Stats</h2>
        </div>
        <div class="padding">
            <ul class="list-reset">
                <li>Pending : <span class="text:bold">{$total_protests}</span></li>
                <li>Archived : <span class="text:bold">{$archived_protests}</span></li>
            </ul>
        </div>
    </div>
	
	<div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-server"></i> Server Information</h2>
        </div>
        <div class="padding" align="center">
            <ul class="list-reset">
				<li>We have a total of <span class="text:bold" style="font-size:x-large"> <i style="color:#dd6b20">{$total_servers}</i></span> servers registred on SourceBans.</li>
				<li>Total demo size is<span class="text:bold" style="font-size:x-large"> <i style="color:#dd6b20">{$demosize}</i></span> hosted on our WebServer.</li>
            </ul>
        </div>
    </div>

    <div class="layout_box">
        <div class="layout_box_title">
            <h2 align="center"><i class="fas fa-code-branch"></i> Version Information</h2>
        </div>
        <div class="padding" align="right">
            <ul class="list-reset">
                <li>Latest release: <span id='relver' class="text:bold">Please Wait...</span></li>
                {if $dev}
                    <li>Latest Git: <span id='svnrev' class="text:bold">Please Wait...</span></li>
                {/if}
                <li><span id='versionmsg'>Please Wait...</span></li>
            </ul>
        </div>
    </div>

    <script type="text/javascript">
        xajax_CheckVersion();
    </script>
</div>