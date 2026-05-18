{*
    SourceBans++ 2026 — page_admin_servers_adminlist.tpl
    Bound to Sbpp\View\AdminServersAdminListView (validated by SmartyTemplateRule).

    Rendered by web/pages/admin.srvadmins.php — read-only list of admins
    that have access to the selected server (directly or via a server
    group). When the SourceQuery probe succeeded, each admin row also
    surfaces their live in-game name + IP under an `In-game` column.

    Auto-escape is on globally (init.php $theme->setEscapeHtml(true));
    {$admin.user|escape} etc. are belt-and-braces because each value comes
    out of the DB (admin display names) or a third-party gameserver
    (in-game name / IP). No `nofilter` is used — every value goes through
    the escaper.
*}
<div class="page-section">
<section class="card" data-testid="server-admin-list">
    <div class="card__header">
        <div>
            <h3>Admins on this server</h3>
            <p>{$admin_count} {if $admin_count == 1}admin has{else}admins have{/if} access. Live in-game rows show the current connection details.</p>
        </div>
    </div>
    {if $admin_count == 0}
        <div class="card__body text-sm text-muted" data-testid="server-admin-empty">
            No admins are mapped to this server yet.
        </div>
    {else}
        <table class="table" data-testid="server-admin-table">
            <thead>
                <tr>
                    <th style="width:30%">Admin</th>
                    <th style="width:30%">SteamID</th>
                    <th>In-game</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$admin_list item=admin}
                    {if $admin.user}
                        <tr data-testid="server-admin-row">
                            <td>
                                <span class="font-medium">{$admin.user|escape}</span>
                            </td>
                            <td class="font-mono text-xs">{$admin.authid|escape}</td>
                            <td>
                                {if $admin.ingame}
                                    <span class="pill pill--online" data-testid="server-admin-ingame">
                                        <span style="width:6px;height:6px;border-radius:50%;background:#10b981"></span>
                                        Online
                                    </span>
                                    <div class="text-xs text-muted" style="margin-top:0.25rem">
                                        as <span class="font-medium">{$admin.iname|escape}</span>
                                        from <span class="font-mono">{$admin.iip|escape}</span>
                                    </div>
                                {else}
                                    <span class="pill pill--offline">Offline</span>
                                {/if}
                            </td>
                        </tr>
                    {/if}
                {/foreach}
            </tbody>
        </table>
    {/if}
</section>
</div>
