{*
    SourceBans++ 2026 — admin/admins list

    Pair: web/pages/admin.admins.php (renders this OR add OR overrides
    based on ?section=) and web/includes/View/AdminAdminsListView.php
    (typed DTO that SmartyTemplateRule keeps in lockstep with this file).

    Layout note: the embedded {load_template file="admin.admins.search"}
    runs admin.admins.search.php inline; that handler does its own
    $theme->assign / $theme->display, so the search-box variables are
    NOT part of this View's contract. Keep that boundary intact — adding
    search vars here would silently double-bind them.

    UX note: the legacy theme used MooTools' InitAccordion to expand a
    sub-row per admin with permission flags + actions. The 2026 footer
    intentionally drops sourcebans.js (#1123 D1 prep), so this template
    flattens the row into one table row with hover-revealed action
    buttons. Per-flag permission lists move to the edit-permissions
    page where they're actionable; the list page stays scannable.

    #1275 — Pattern A `?section=…` routing
    --------------------------------------
    Pre-#1275 this template opened a cross-template `.page-toc-shell`
    that spanned page_admin_admins_add.tpl + page_admin_overrides.tpl
    so all three sections stacked into one DOM and the page-level ToC
    (page_toc.tpl) emitted #fragment anchor jumps between them. #1275
    unifies on the same `?section=…` shape used by admin.servers.php /
    admin.settings.php / admin.mods.php / admin.groups.php — each
    section renders alone via AdminTabs. The shell wrappers are gone;
    the admin sidebar lives in core/admin_sidebar.tpl, mounted by
    AdminTabs.php. The search box stays embedded above the table
    because filtering is the same UX surface as browsing the list (one
    `<form>`, results re-render in place — splitting them across two
    URLs would force the user to bounce between pages to iterate
    filters). See the docblock on web/pages/admin.admins.php.
*}
{if !$can_list_admins}
    <div class="card">
        <div class="card__body">
            <p class="text-sm text-muted m-0">Access denied.</p>
        </div>
    </div>
{else}
    <div class="flex items-end justify-between gap-3 mb-4" style="flex-wrap:wrap">
        <div>
            <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Admins
                <span class="text-faint" style="font-weight:400;margin-left:0.375rem" data-testid="admin-count">({$admin_count})</span>
            </h1>
            <p class="text-sm text-muted m-0 mt-2">Click an admin row's actions to edit details, permissions, or server access.</p>
        </div>
        {if $can_add_admins}
            <a class="btn btn--primary btn--sm"
               href="index.php?p=admin&amp;c=admins&amp;section=add-admin"
               data-testid="admin-add-cta"><i data-lucide="user-plus"></i> Add admin</a>
        {/if}
    </div>

    <div data-testid="admin-admins-section-search">
        {load_template file="admin.admins.search"}
    </div>

    <div data-testid="admin-admins-section-admins">
        <div class="text-xs text-muted mb-2" data-testid="admin-nav">
            {* nofilter: server-built pagination HTML — `<displaying N - M of K results>` (integers), prev/next `<a>` from `CreateLinkR(…)`, and a page-jump `<select onchange>`. After #1207 ADM-4 every populated filter flows through `http_build_query($activeFilters)`, which percent-encodes filter values (so single quotes / angle brackets can't break out of the single-quoted `href='…'` or `onchange="… '…'…"` attributes). The page-jump `<select>` additionally `htmlspecialchars()`-escapes the base URL with `ENT_QUOTES` before interpolation. Loop counters and pre-computed page numbers are integers. No raw user input reaches the rendered string. *}
            {$admin_nav nofilter}
        </div>

        <div class="card" style="overflow:hidden">
            <table class="table" role="table" aria-label="Admins">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Bans</th>
                        <th scope="col">Server group</th>
                        <th scope="col">Web group</th>
                        <th scope="col">Immunity</th>
                        <th scope="col">Last visit</th>
                        <th scope="col" style="width:1%"></th>
                    </tr>
                </thead>
                <tbody>
                {foreach $admins as $admin}
                    <tr data-testid="admin-row" data-id="{$admin.aid}">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar" style="width:1.75rem;height:1.75rem;background:var(--brand-600);font-size:var(--fs-xs)">
                                    {$admin.user|truncate:1:'':true|upper|escape}
                                </div>
                                <div>
                                    <div class="font-medium">{$admin.user|escape}</div>
                                    <div class="text-xs text-faint" style="margin-top:0.125rem">aid {$admin.aid}</div>
                                </div>
                            </div>
                        </td>
                        <td class="tabular-nums text-muted">
                            <a href="./index.php?p=banlist&advSearch={$admin.aid|escape:'url'}&advType=admin"
                               title="Show bans">{$admin.bancount}</a>
                            <span class="text-faint"> · </span>
                            <a href="./index.php?p=banlist&advSearch={$admin.aid|escape:'url'}&advType=nodemo"
                               title="Show bans without demo">{$admin.nodemocount} w/o demo</a>
                        </td>
                        <td class="text-muted">{$admin.server_group|escape}</td>
                        <td class="text-muted">{$admin.web_group|escape}</td>
                        <td class="tabular-nums text-muted">{$admin.immunity}</td>
                        <td class="text-xs text-muted">{$admin.lastvisit|escape}</td>
                        <td>
                            <div class="row-actions" style="white-space:nowrap">
                                {if $can_edit_admins}
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editdetails&id={$admin.aid|escape:'url'}"
                                       title="Edit details"
                                       aria-label="Edit details for {$admin.user|escape}"
                                       data-testid="admin-action-edit-details">
                                        <i data-lucide="clipboard-list" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editpermissions&id={$admin.aid|escape:'url'}"
                                       title="Edit permissions"
                                       aria-label="Edit permissions for {$admin.user|escape}"
                                       data-testid="admin-action-edit-perms">
                                        <i data-lucide="shield" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editservers&id={$admin.aid|escape:'url'}"
                                       title="Edit server access"
                                       aria-label="Edit server access for {$admin.user|escape}"
                                       data-testid="admin-action-edit-servers">
                                        <i data-lucide="server" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editgroup&id={$admin.aid|escape:'url'}"
                                       title="Edit groups"
                                       aria-label="Edit groups for {$admin.user|escape}"
                                       data-testid="admin-action-edit-group">
                                        <i data-lucide="users" style="width:14px;height:14px"></i>
                                    </a>
                                {/if}
                                {if $can_delete_admins}
                                    {* #1352: data-action wires the delete button to the inline
                                       page-tail script below, which opens the
                                       `#admins-delete-dialog` <dialog> for a confirm + reason
                                       prompt, then calls `Actions.AdminsRemove` with the
                                       trimmed reason. The pre-fix `onclick="if (typeof
                                       RemoveAdmin === 'function') RemoveAdmin(...)"` was a
                                       silent no-op since #1123 D1 deleted sourcebans.js (which
                                       was the only definer of `RemoveAdmin`). The fallback
                                       href lands on the admins list — there is no legacy GET
                                       handler for `o=remove` (RemoveAdmin always went through
                                       the JSON dispatcher), and adding one would expand scope
                                       beyond the bug; the fallback is a graceful degradation
                                       for the rare case where the JSON dispatcher itself is
                                       missing (e.g. third-party theme that stripped api.js). *}
                                    <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                            data-action="admins-delete"
                                            data-aid="{$admin.aid}"
                                            data-name="{$admin.user|escape}"
                                            data-fallback-href="index.php?p=admin&amp;c=admins"
                                            title="Delete admin"
                                            aria-label="Delete admin {$admin.user|escape}"
                                            data-testid="admin-action-delete">
                                        <i data-lucide="trash-2" style="width:14px;height:14px;color:var(--danger)"></i>
                                    </button>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>

    {* ============================================================
       #1352 — admin-delete confirm + reason modal scaffold.

       Mirrors the canonical `#bans-unban-dialog` (`page_bans.tpl`,
       #1301) and `#comms-unblock-dialog` (`page_comms.tpl`, #1301)
       shapes. The pre-fix delete button called a long-deleted
       `RemoveAdmin()` JS helper from sourcebans.js (removed at
       #1123 D1) and the `typeof RemoveAdmin === 'function'` guard
       made every click a silent no-op. v1.x also surfaced a
       confirm prompt before deleting; this dialog restores that
       safeguard plus an optional reason field that flows into the
       audit-log entry — destructive irreversible row-flips need
       both per AGENTS.md "Reason-less, no-confirm" anti-pattern.

       The reason is OPTIONAL (vs the required-reason shape on
       bans-unban / comms-unblock) because admin deletion is the
       end of an admin's lifecycle, not a moderation action against
       a player — the audit value is "who removed this admin and
       optionally why" rather than "the admin must justify lifting
       a punishment". Server-side handler accepts empty `ureason`;
       the audit-log entry omits the `Reason: …` suffix when empty.
       ============================================================ *}
    <dialog id="admins-delete-dialog"
            class="palette"
            aria-labelledby="admins-delete-dialog-title"
            data-testid="admins-delete-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-delete-form">
            <h2 id="admins-delete-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Delete admin</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to permanently delete <strong data-testid="admins-delete-target">this admin</strong>. This cannot be undone &mdash; their server access is revoked immediately and any rehash queue is flushed.
            </p>
            <label class="label" for="admins-delete-reason">Reason (optional)</label>
            {* aria-required (not the native `required`) parity with the
               canonical confirm-modal shape — see the matching note on
               `#bans-unban-dialog` / `#comms-unblock-dialog`. We mark
               it `false` here because the reason is optional for the
               delete-admin surface (vs required for the unban /
               unblock surfaces); declaring the attribute keeps the
               assistive-tech contract explicit. *}
            <textarea class="textarea"
                      id="admins-delete-reason"
                      data-testid="admins-delete-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="admins-delete-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-delete-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--danger" data-testid="admins-delete-submit" value="confirm">
                    <i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete admin
                </button>
            </div>
        </form>
    </dialog>

    {* ============================================================
       #1352 — admins-delete row-action wiring (inline page-tail JS).

       Click delegation: every Delete button in the rows above carries
       `data-action="admins-delete"` plus `data-aid` / `data-name` /
       `data-fallback-href`. The handler intercepts those clicks, opens
       the `#admins-delete-dialog` <dialog>, accepts an optional reason
       on submit, calls `sb.api.call(Actions.AdminsRemove, …)`, and on
       success removes the row from the DOM, decrements the admin-count
       badge, and fires `window.SBPP.showToast` for confirmation. The
       fallback href is followed as a navigation when the JSON
       dispatcher is missing entirely (third-party theme that stripped
       api.js); since there's no legacy GET handler for `o=remove`
       (RemoveAdmin always went through the JSON dispatcher), the
       fallback just lands the operator back at the admins list.

       No `// @ts-check` here because the file is rendered by Smarty;
       ts-check only runs against `.js` sources in `web/scripts`. The
       shape mirrors the inline handler in page_bans.tpl
       (`#bans-unban-dialog`, #1301).
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
         * @param {string} aid
         * @returns {Element|null}
         */
        function rowForAid(aid) {
            return document.querySelector('[data-testid="admin-row"][data-id="' + aid + '"]');
        }

        /**
         * Drop one from the count badge. Reads the parenthesised number out
         * of the badge's textContent so a third-party theme that wraps the
         * count differently still works as long as the testid points at a
         * node whose text contains the digits.
         * @returns {void}
         */
        function decrementCount() {
            var el = document.querySelector('[data-testid="admin-count"]');
            if (!el) return;
            var n = Number((el.textContent || '').replace(/[^0-9]/g, ''));
            if (!Number.isFinite(n) || n <= 0) return;
            el.textContent = '(' + (n - 1).toLocaleString() + ')';
        }

        /** @returns {HTMLDialogElement|null} */
        function dialog() {
            return /** @type {HTMLDialogElement|null} */ (document.getElementById('admins-delete-dialog'));
        }
        /** @returns {HTMLTextAreaElement|null} */
        function reasonInput() {
            return /** @type {HTMLTextAreaElement|null} */ (document.getElementById('admins-delete-reason'));
        }
        /** @returns {HTMLElement|null} */
        function errorEl() {
            var d = dialog();
            return d ? /** @type {HTMLElement|null} */ (d.querySelector('[data-testid="admins-delete-error"]')) : null;
        }
        /** @param {string} msg */
        function showError(msg) { var e = errorEl(); if (!e) return; e.textContent = msg; e.hidden = false; }
        function clearError() { var e = errorEl(); if (!e) return; e.textContent = ''; e.hidden = true; }

        /** @type {{aid: string, name: string, fallback: string}|null} */
        var pending = null;

        /** @param {{aid: string, name: string, fallback: string}} ctx */
        function openDeleteDialog(ctx) {
            pending = ctx;
            var d = dialog();
            if (!d) {
                // Dialog markup missing (third-party theme that stripped
                // the partial). Fall back to the admins list landing —
                // there's no legacy GET handler for `o=remove`, so we
                // can't perform the delete from this code path. Loud no-op
                // is preferable to a silent no-op.
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }
            var target = d.querySelector('[data-testid="admins-delete-target"]');
            if (target) target.textContent = ctx.name || ('admin #' + ctx.aid);
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
            if (t.closest('[data-testid="admins-delete-cancel"]')) {
                e.preventDefault();
                closeDeleteDialog();
                return;
            }

            var btn = /** @type {HTMLElement|null} */ (t.closest('[data-action="admins-delete"]'));
            if (!btn) return;
            e.preventDefault();

            var aid = btn.getAttribute('data-aid') || '';
            var name = btn.getAttribute('data-name') || ('admin #' + aid);
            var fallback = btn.getAttribute('data-fallback-href') || '';
            var a = api(), A = actions();
            if (!a || !A || !aid) {
                // No JSON dispatcher available — fall back to the admins
                // list (no legacy GET handler exists for `o=remove`).
                if (fallback) window.location.href = fallback;
                return;
            }
            openDeleteDialog({ aid: aid, name: name, fallback: fallback });
        });

        document.addEventListener('submit', function (e) {
            var form = /** @type {Element|null} */ (e.target);
            if (!form || !(/** @type {Element} */ (form)).closest) return;
            if (!form.matches('[data-testid="admins-delete-form"]')) return;
            e.preventDefault();
            if (!pending) return;

            var input = reasonInput();
            // Reason is optional for the delete-admin surface (server-side
            // handler accepts empty `ureason` and omits the audit suffix).
            // Trim whitespace so the audit-log "Reason: " prefix doesn't
            // get a blank tail when the operator typed only spaces.
            var reason = input ? input.value.trim() : '';
            clearError();

            var ctx = pending;
            var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="admins-delete-submit"]'));
            if (submitBtn) submitBtn.disabled = true;

            var a = api(), A = actions();
            if (!a || !A) {
                if (submitBtn) submitBtn.disabled = false;
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }

            /** @type {{aid: number, ureason?: string}} */
            var params = { aid: Number(ctx.aid) };
            if (reason !== '') params.ureason = reason;

            a.call(A.AdminsRemove, params).then(function (r) {
                if (submitBtn) submitBtn.disabled = false;
                if (!r || r.ok === false) {
                    var msg = (r && r.error && r.error.message) || 'Unknown error';
                    showError(msg);
                    toast('error', 'Delete failed', msg);
                    return;
                }
                var row = rowForAid(ctx.aid);
                if (row && row.parentNode) row.parentNode.removeChild(row);
                decrementCount();
                closeDeleteDialog();
                toast('success', 'Admin deleted', ctx.name + ' has been removed.');
            });
        });

        document.addEventListener('cancel', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t || t.id !== 'admins-delete-dialog') return;
            pending = null;
            clearError();
        });
    })();
    </script>
    {/literal}
{/if}
