<div class="layout_box">
    <div class="table padding">
        {if $IN_SERVERS_PAGE && $access_bans}
            <div class="responsive_show:desktop" style="text-align:right; width:100%;">
                <small>Hint: Rightclick on a player to open a context menu with options to kick, ban or contact the player directly.</small>
            </div>
        {/if}
        <div class="table_box">
            <table>
                <thead>
                    <tr>
                        <th>MOD</th>
                        <th>OS</th>
                        <th>VAC</th>
                        <th class="text:left">Hostname</th>
                        <th class="text:left">IP adress</th>
                        <th>Players</th>
                        <th>Map</th>
                        <th class="responsive_show:desktop">Connect</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$server_list item="server"}
                        <tr id="server_{$server.sid}" {if $IN_SERVERS_PAGE}class="collapse" {/if}>
                            <td class="text:center">
                                <img src="images/games/{$server.icon}" alt="{$server.icon}" />
                            </td>
                            <td id="os_{$server.sid}" class="text:center"></td>
                            <td id="vac_{$server.sid}" class="text:center"></td>
                            <td id="host_{$server.sid}"><i>Querying Server Data...</i></td>
                            <td>{$server.ip}:{$server.port}</td>
                            <td id="players_{$server.sid}" class="text:center">N/A</td>
                            <td id="map_{$server.sid}" class="text:center">N/A</td>
                            <td class="text:center responsive_show:desktop">
                                <a class="button button-success" href="steam://connect/{$server.ip}:{$server.port}"><i
                                        class="fa fa-sign-in"></i> Connect</a>
                            </td>
                        </tr>
                        {if $IN_SERVERS_PAGE}
                            <tr id="server_{$server.sid}:desc" class="table_hide">
                                <td colspan="8">
                                    <div class="collapse_content" id="sinfo_{$server.sid}">
                                        <table id="playerlist_{$server.sid}" class="table" name="playerlist_{$server.sid}">
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        {/if}
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

{if $IN_SERVERS_PAGE}
    <script type="text/javascript" src="themes/{$theme}/scripts/collapse.js"></script>
    <script>
        document.querySelectorAll('.button').forEach(e => e.addEventListener('click', el => el.stopPropagation()));
    </script>
{/if}
