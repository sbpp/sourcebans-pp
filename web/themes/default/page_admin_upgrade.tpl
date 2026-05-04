{if NOT $permission_owner}
    Access Denied!
{else}
    <h3>Schema upgrade (1.x → 2.0)</h3>
    <p>
        This page wraps the same code path as <code>php web/bin/upgrade.php</code>.
        Use it to compare the live database against the canonical schema in
        <code>web/install/includes/sql/struc.sql</code> and apply the additive
        changes (missing tables, missing columns, missing settings keys).
        The migrator never drops anything; running it twice is a no-op.
    </p>

    {if $plan_error}
        <div class="badentry">
            <strong>Could not compute the diff:</strong>
            {$plan_error}
        </div>
    {else}
        <div id="upgrade-summary">
            {if $plan.ok}
                <p><strong>Schema is up to date — nothing to do.</strong></p>
            {else}
                <p><strong>Pending changes: {$plan.total}</strong></p>

                {if $plan.tables}
                    <h4>Missing tables ({$plan.tables|@count})</h4>
                    <ul>
                        {foreach from=$plan.tables item=t}
                            <li><code>:prefix_{$t.name}</code></li>
                        {/foreach}
                    </ul>
                {/if}

                {if $plan.columns}
                    <h4>Missing columns ({$plan.columns|@count})</h4>
                    <ul>
                        {foreach from=$plan.columns item=c}
                            <li><code>:prefix_{$c.table}.{$c.column}</code></li>
                        {/foreach}
                    </ul>
                {/if}

                {if $plan.settings}
                    <h4>Missing settings keys ({$plan.settings|@count})</h4>
                    <ul>
                        {foreach from=$plan.settings item=s}
                            <li><code>{$s.key}</code> = <code>{$s.value}</code></li>
                        {/foreach}
                    </ul>
                {/if}
            {/if}
        </div>

        <br />
        <div id="upgrade-actions">
            {sb_button text="Refresh dry-run" onclick="UpgradeRefresh();" class="cancel" id="upgrade-refresh"}
            &nbsp;
            {if NOT $plan.ok}
                {sb_button text="Apply changes" onclick="UpgradeApply();" class="ok" id="upgrade-apply"}
            {/if}
        </div>

        <br />
        <div id="upgrade-results" style="display:none;">
            <h4>Apply log</h4>
            <table width="100%" cellspacing="0" cellpadding="0" class="listtable">
                <tr>
                    <td width="10%" class="listtable_top"><strong>Status</strong></td>
                    <td width="15%" class="listtable_top"><strong>Kind</strong></td>
                    <td width="25%" class="listtable_top"><strong>Target</strong></td>
                    <td class="listtable_top"><strong>Summary</strong></td>
                </tr>
                <tbody id="upgrade-results-body"></tbody>
            </table>
            <div id="upgrade-results-error" class="badentry" style="display:none;"></div>
        </div>
    {/if}
{/if}
