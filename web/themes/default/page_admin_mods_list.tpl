{*
    SourceBans++ 2026 — page / page_admin_mods_list.tpl

    First tab of the admin "Mods" page. Pair: Sbpp\View\AdminModsListView
    + web/pages/admin.mods.php (renders this and page_admin_mods_add.tpl
    inside the AdminTabs scaffold).

    Variable contract (kept in sync by SmartyTemplateRule):
        - $permission_listmods   — gate the whole tab body.
        - $permission_editmods   — gate the per-row "Edit" link.
        - $permission_deletemods — gate the per-row "Delete" button.
        - $mod_count             — total mods configured.
        - $mod_list              — :prefix_mods rows (mid, name,
                                   modfolder, icon, steam_universe,
                                   enabled).

    Variable names match the default theme's page_admin_mods_list.tpl
    (legacy `permission_*` style). The dual-theme PHPStan matrix
    (#1123 A2) cross-checks both templates against the View, so both
    have to agree on names until D1 retires the legacy theme.

    Testability hooks:
        - Each row carries data-testid="mod-row" + data-id="{$mod.mid}"
          so end-to-end tests can target a specific mod without parsing
          inner markup.
        - The Delete button uses data-action="mod-delete" + data-mid /
          data-name / data-fallback-href (#1397) to feed the inline
          page-tail script below, which opens the `#mod-delete-dialog`
          for a confirm + optional reason prompt then calls
          `Actions.ModsRemove`. The data attributes carry the
          admin-controlled mod name in attribute-safe form so it can
          never escape out of an inline JS string literal
          (#1113-style hardening).
        - The mod count badge carries data-testid="mod-count" so the
          page-tail script (and tests) can decrement / read it after
          a delete without parsing the surrounding `<p>` copy.
*}
<div class="page-section">
{if NOT $permission_listmods}
    <div class="card">
        <div class="card__body">
            <p class="text-muted">Access denied.</p>
        </div>
    </div>
{else}
    <div class="card">
        <div class="card__header">
            <div>
                <h3>Server Mods</h3>
                <p><span data-testid="mod-count">{$mod_count}</span> configured</p>
            </div>
        </div>
        {if $mod_count > 0}
            <table class="table" data-testid="mods-table">
                <thead>
                    <tr>
                        <th style="width:40%">Name</th>
                        <th>Folder</th>
                        <th><span title="SteamID Universe (X of STEAM_X:Y:Z)">SU</span></th>
                        <th>Status</th>
                        {if $permission_editmods || $permission_deletemods}
                            <th style="text-align:right">Actions</th>
                        {/if}
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$mod_list item=mod}
                        <tr id="mid_{$mod.mid}" data-testid="mod-row" data-id="{$mod.mid}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="images/games/{$mod.icon}"
                                         alt=""
                                         width="20"
                                         height="20"
                                         loading="lazy"
                                         onerror="this.style.visibility='hidden'">
                                    <span class="font-medium">{$mod.name}</span>
                                </div>
                            </td>
                            <td><span class="font-mono text-xs">{$mod.modfolder}</span></td>
                            <td class="tabular-nums">{$mod.steam_universe}</td>
                            <td>
                                {if $mod.enabled}
                                    <span class="pill pill--online">Enabled</span>
                                {else}
                                    <span class="pill pill--offline">Disabled</span>
                                {/if}
                            </td>
                            {if $permission_editmods || $permission_deletemods}
                                <td style="text-align:right">
                                    <div class="flex justify-end gap-2">
                                        {if $permission_editmods}
                                            <a class="btn btn--ghost btn--sm"
                                               href="index.php?p=admin&c=mods&o=edit&id={$mod.mid|escape:'url'}"
                                               data-testid="editmod-link">Edit</a>
                                        {/if}
                                        {if $permission_deletemods}
                                            {* #1397: data-action wires the delete button to the inline
                                               page-tail script below, which opens the
                                               `#mod-delete-dialog` <dialog> for a confirm + optional reason
                                               prompt, then calls `Actions.ModsRemove` with the
                                               trimmed reason. The pre-fix `onclick="RemoveMod(this.dataset.modName, this.dataset.modId)"`
                                               was an unguarded reference to the v1.x sourcebans.js
                                               `RemoveMod` helper deleted at #1123 D1, so every click
                                               threw `ReferenceError: RemoveMod is not defined` and
                                               the delete never fired. The fallback href lands on the
                                               mods list — there is no legacy GET handler for
                                               `o=remove` (RemoveMod always went through the JSON
                                               dispatcher), and adding one would expand scope
                                               beyond the bug; the fallback is a graceful degradation
                                               for the rare case where the JSON dispatcher itself is
                                               missing (e.g. third-party theme that stripped api.js). *}
                                            <button class="btn btn--ghost btn--sm"
                                                    type="button"
                                                    data-action="mod-delete"
                                                    data-mid="{$mod.mid}"
                                                    data-name="{$mod.name|escape}"
                                                    data-fallback-href="index.php?p=admin&amp;c=mods"
                                                    data-testid="deletemod-btn"
                                                    aria-label="Delete mod {$mod.name|escape}">Delete</button>
                                        {/if}
                                    </div>
                                </td>
                            {/if}
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="card__body">
                <p class="text-muted">No mods configured yet.</p>
            </div>
        {/if}
    </div>

    {if $permission_deletemods}
    {* ============================================================
       #1397 — mod-delete confirm + optional-reason modal scaffold.

       Mirrors the canonical `#admins-delete-dialog` (#1352) shape,
       which itself mirrors the required-reason `#bans-unban-dialog`
       (`page_bans.tpl`, #1301) and `#comms-unblock-dialog`
       (`page_comms.tpl`, #1301). The pre-fix delete button called
       a long-deleted `RemoveMod()` JS helper from sourcebans.js
       (removed at #1123 D1) — without even the
       `typeof X === 'function'` guard that the admins-delete sister
       carried, so every click threw a loud
       `ReferenceError: RemoveMod is not defined` instead of failing
       silently. v1.x also surfaced a confirm prompt before
       deleting; this dialog restores that safeguard plus an optional
       reason field that flows into the audit-log entry —
       destructive irreversible row-flips need both per AGENTS.md
       "Reason-less, no-confirm" anti-pattern.

       The reason is OPTIONAL (vs the required-reason shape on
       bans-unban / comms-unblock) because mod deletion is the end of
       a mod's lifecycle, not a moderation action against a player —
       the audit value is "who removed this mod and optionally why"
       rather than "the admin must justify lifting a punishment".
       Server-side handler accepts empty `ureason`; the audit-log
       entry omits the `Reason: …` suffix when empty.

       The dialog is gated on `$permission_deletemods` so panels with
       a read-only mods admin never paint the modal markup at all
       (cosmetic — the dialog has no visible side effect without a
       Delete button to open it, but the test-id assertion would
       false-positive if someone seeded a no-perm pageload).
       ============================================================ *}
    <dialog id="mod-delete-dialog"
            class="palette"
            aria-labelledby="mod-delete-dialog-title"
            data-testid="mod-delete-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="mod-delete-form">
            <h2 id="mod-delete-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Delete mod</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to permanently delete <strong data-testid="mod-delete-target">this mod</strong>. This cannot be undone. Historical bans and comm blocks recorded against this mod keep their rows but lose their game association, so the banlist will show &ldquo;Unknown&rdquo; in the Server / Mod column for those entries.
            </p>
            <label class="label" for="mod-delete-reason">Reason (optional)</label>
            {* aria-required (not the native `required`) parity with the
               canonical confirm-modal shape — see the matching note on
               `#admins-delete-dialog` / `#bans-unban-dialog` /
               `#comms-unblock-dialog`. We mark it `false` here because
               the reason is optional for the delete-mod surface (vs
               required for the unban / unblock surfaces); declaring
               the attribute keeps the assistive-tech contract
               explicit. *}
            <textarea class="textarea"
                      id="mod-delete-reason"
                      data-testid="mod-delete-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="mod-delete-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="mod-delete-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--danger" data-testid="mod-delete-submit" value="confirm">
                    <i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete mod
                </button>
            </div>
        </form>
    </dialog>

    {* ============================================================
       #1397 — mod-delete row-action wiring (inline page-tail JS).

       Click delegation: every Delete button in the rows above carries
       `data-action="mod-delete"` plus `data-mid` / `data-name` /
       `data-fallback-href`. The handler intercepts those clicks, opens
       the `#mod-delete-dialog` <dialog>, accepts an optional reason
       on submit, calls `sb.api.call(Actions.ModsRemove, …)`, and on
       success removes the row from the DOM, decrements the mod-count
       badge, and fires `window.SBPP.showToast` for confirmation. The
       fallback href is followed as a navigation when the JSON
       dispatcher is missing entirely (third-party theme that stripped
       api.js); since there's no legacy GET handler for `o=remove`
       (RemoveMod always went through the JSON dispatcher), the
       fallback just lands the operator back at the mods list.

       No `// @ts-check` here because the file is rendered by Smarty;
       ts-check only runs against `.js` sources in `web/scripts`. The
       shape mirrors the inline handler in page_admin_admins_list.tpl
       (`#admins-delete-dialog`, #1352) modulo the action name, the
       row testid, and the counter element selector.
       ============================================================ *}
    {literal}
    <script>
    (function () {
        'use strict';

        /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
        function api()     { return (window.sb && window.sb.api) || null; }
        /** @returns {Record<string,string>|null} */
        function actions() { return /** @type {any} */ (window).Actions || null; }
        function toast(kind, title, body) {
            var sbpp = /** @type {any} */ (window).SBPP;
            if (sbpp && typeof sbpp.showToast === 'function') {
                sbpp.showToast({ kind: kind, title: title, body: body || '' });
            }
        }
        /**
         * Flip the busy / loading state on a triggered action button. Calls
         * window.SBPP.setBusy when present (theme.js owns the spinner CSS
         * contract) and falls back to plain `disabled` so third-party themes
         * that strip theme.js still gate against double-clicks.
         * @param {Element|null} btn
         * @param {boolean} [busy] defaults to true
         */
        function setBusy(btn, busy) {
            if (!btn) return;
            var S = /** @type {any} */ (window).SBPP;
            if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
            else /** @type {HTMLButtonElement} */ (btn).disabled = busy === undefined ? true : !!busy;
        }

        /**
         * @param {string} mid
         * @returns {Element|null}
         */
        function rowForMid(mid) {
            return document.querySelector('[data-testid="mod-row"][data-id="' + mid + '"]');
        }

        /**
         * Drop one from the count badge. Reads the digits out of the badge's
         * textContent so a third-party theme that wraps the count
         * differently still works as long as the testid points at a node
         * whose text contains the digits.
         * @returns {void}
         */
        function decrementCount() {
            var el = document.querySelector('[data-testid="mod-count"]');
            if (!el) return;
            var n = Number((el.textContent || '').replace(/[^0-9]/g, ''));
            if (!Number.isFinite(n) || n <= 0) return;
            el.textContent = String(n - 1);
        }

        /** @returns {HTMLDialogElement|null} */
        function dialog() {
            return /** @type {HTMLDialogElement|null} */ (document.getElementById('mod-delete-dialog'));
        }
        /** @returns {HTMLTextAreaElement|null} */
        function reasonInput() {
            return /** @type {HTMLTextAreaElement|null} */ (document.getElementById('mod-delete-reason'));
        }
        /** @returns {HTMLElement|null} */
        function errorEl() {
            var d = dialog();
            return d ? /** @type {HTMLElement|null} */ (d.querySelector('[data-testid="mod-delete-error"]')) : null;
        }
        /** @param {string} msg */
        function showError(msg) { var e = errorEl(); if (!e) return; e.textContent = msg; e.hidden = false; }
        function clearError() { var e = errorEl(); if (!e) return; e.textContent = ''; e.hidden = true; }

        /** @type {{mid: string, name: string, fallback: string}|null} */
        var pending = null;

        /** @param {{mid: string, name: string, fallback: string}} ctx */
        function openDeleteDialog(ctx) {
            pending = ctx;
            var d = dialog();
            if (!d) {
                // Dialog markup missing (third-party theme that stripped
                // the partial). Fall back to the mods list landing —
                // there's no legacy GET handler for `o=remove`, so we
                // can't perform the delete from this code path. Loud no-op
                // is preferable to a silent no-op.
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }
            var target = d.querySelector('[data-testid="mod-delete-target"]');
            if (target) target.textContent = ctx.name || ('mod #' + ctx.mid);
            var input = reasonInput();
            if (input) input.value = '';
            clearError();
            d.removeAttribute('hidden');
            try { d.showModal(); }
            catch (_e) { d.setAttribute('open', ''); }
            if (input) { try { input.focus(); } catch (_e) { /* focus may throw if hidden */ } }
        }

        function closeDeleteDialog() {
            var d = dialog();
            if (!d) return;
            try { d.close(); } catch (_e) { /* not opened modally */ }
            d.setAttribute('hidden', '');
            pending = null;
        }

        document.addEventListener('click', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t || !t.closest) return;

            // Cancel button inside the dialog.
            if (t.closest('[data-testid="mod-delete-cancel"]')) {
                e.preventDefault();
                closeDeleteDialog();
                return;
            }

            var btn = /** @type {HTMLElement|null} */ (t.closest('[data-action="mod-delete"]'));
            if (!btn) return;
            e.preventDefault();

            var mid = btn.getAttribute('data-mid') || '';
            var name = btn.getAttribute('data-name') || ('mod #' + mid);
            var fallback = btn.getAttribute('data-fallback-href') || '';
            var a = api(), A = actions();
            if (!a || !A || !mid) {
                // No JSON dispatcher available — fall back to the mods
                // list (no legacy GET handler exists for `o=remove`).
                if (fallback) window.location.href = fallback;
                return;
            }
            openDeleteDialog({ mid: mid, name: name, fallback: fallback });
        });

        document.addEventListener('submit', function (e) {
            var form = /** @type {Element|null} */ (e.target);
            if (!form || !(/** @type {Element} */ (form)).closest) return;
            if (!form.matches('[data-testid="mod-delete-form"]')) return;
            e.preventDefault();
            if (!pending) return;

            var input = reasonInput();
            // Reason is optional for the delete-mod surface (server-side
            // handler accepts empty `ureason` and omits the audit suffix).
            // Trim whitespace so the audit-log "Reason: " prefix doesn't
            // get a blank tail when the operator typed only spaces.
            var reason = input ? input.value.trim() : '';
            clearError();

            var ctx = pending;
            var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="mod-delete-submit"]'));
            setBusy(submitBtn, true);

            var a = api(), A = actions();
            if (!a || !A) {
                setBusy(submitBtn, false);
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }

            /** @type {{mid: number, ureason?: string}} */
            var params = { mid: Number(ctx.mid) };
            if (reason !== '') params.ureason = reason;

            a.call(A.ModsRemove, params).then(function (r) {
                setBusy(submitBtn, false);
                if (!r || r.ok === false) {
                    var msg = (r && r.error && r.error.message) || 'Unknown error';
                    showError(msg);
                    toast('error', 'Delete failed', msg);
                    return;
                }
                var row = rowForMid(ctx.mid);
                if (row && row.parentNode) row.parentNode.removeChild(row);
                decrementCount();
                closeDeleteDialog();
                toast('success', 'Mod deleted', ctx.name + ' has been removed.');
            });
        });

        document.addEventListener('cancel', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t || t.id !== 'mod-delete-dialog') return;
            pending = null;
            clearError();
        });
    })();
    </script>
    {/literal}
    {/if}
{/if}
</div>
