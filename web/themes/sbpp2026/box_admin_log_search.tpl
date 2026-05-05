{*
    SourceBans++ 2026 — admin/settings (logs) advanced-search box.

    Pair: web/pages/admin.log.search.php +
          web/includes/View/AdminLogSearchView.php (typed DTO that
          SmartyTemplateRule keeps in lockstep with this template).

    This box is included via {load_template file="admin.log.search"}
    from page_admin_settings_logs.tpl. It is intentionally
    self-contained so any future redesign that wants the legacy
    "advanced search" affordance (search by admin / message / date /
    type) can drop the load_template call back in without re-plumbing
    the form.

    Wire format (kept identical to the legacy box so the consumer
    handler in admin.settings.php?section=logs continues to parse the
    same `$_GET['advSearch']` / `$_GET['advType']` shape):
        advType=admin    advSearch=<aid>
        advType=message  advSearch=<text>
        advType=date     advSearch="<dd>,<mm>,<yyyy>,<fhh>,<fmm>,<thh>,<tmm>"
        advType=type     advSearch="m" | "w" | "e"
    The hash fragment "#^2" the legacy URL appended is theme-specific
    DOM-anchor scrolling and is dropped here; the new layout has no
    accordion to re-target.

    Why we drop sourcebans.js' search_log() global
    ----------------------------------------------
    sourcebans.js disappears at #1123 D1, so the legacy
    `onclick="search_log()"` button from box_admin_log_search.tpl
    would `ReferenceError` post-cutover. Each row gets its own submit
    button instead, with the dispatch logic inlined under
    {literal}<script>…{/literal}` — vanilla, no globals, no
    `sb.$idRequired` (the inline script owns its own DOM).

    Testability hooks (per #1123 issue body, "search-<scope>-<…>"):
        data-testid="search-log-form"            outer form
        data-testid="search-log-admin"           admin <select>
        data-testid="search-log-message"         message <input>
        data-testid="search-log-date-day"        … and friends
        data-testid="search-log-type"            type <select>
        data-testid="search-log-submit-<key>"    one per searchable field
*}
<form method="get"
      action="index.php"
      data-testid="search-log-form"
      class="card"
      style="margin-top:1rem;margin-bottom:1rem">
    <input type="hidden" name="p" value="admin">
    <input type="hidden" name="c" value="settings">
    <input type="hidden" name="section" value="logs">
    <input type="hidden" name="advType" value="" data-search-type>
    <input type="hidden" name="advSearch" value="" data-search-value>

    <div class="card__header">
        <div>
            <h3>Advanced search</h3>
            <p>Filter the system log by admin, message text, date range, or severity.</p>
        </div>
    </div>

    <div class="card__body space-y-3">
        <div class="grid gap-3" style="grid-template-columns:14rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-log-admin">Admin</label>
                <select class="select"
                        id="search-log-admin"
                        data-testid="search-log-admin">
                    <option value="">&mdash;</option>
                    {foreach from=$admin_list item="admin"}
                        <option value="{$admin.aid}">{$admin.user}</option>
                    {/foreach}
                </select>
            </div>
            <div class="text-xs text-muted">Select an admin to filter actions to that account.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-log-submit-admin"
                    data-search-key="admin"
                    data-search-from="search-log-admin">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:14rem 1fr auto;align-items:end">
            <label class="label" for="search-log-message" style="grid-column:1;align-self:end">Message</label>
            <input class="input"
                   id="search-log-message"
                   type="text"
                   placeholder="Substring to match against the log message&hellip;"
                   data-testid="search-log-message"
                   autocomplete="off">
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-log-submit-message"
                    data-search-key="message"
                    data-search-from="search-log-message">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:14rem 1fr auto;align-items:end">
            <span class="label" style="grid-column:1;align-self:end">Date range</span>
            <div class="flex items-center gap-2" style="flex-wrap:wrap">
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" placeholder="DD"
                       data-testid="search-log-date-day"
                       data-search-date="day"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">/</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" placeholder="MM"
                       data-testid="search-log-date-month"
                       data-search-date="month"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">/</span>
                <input class="input font-mono" style="width:4.25rem" type="text" maxlength="4" placeholder="YYYY"
                       data-testid="search-log-date-year"
                       data-search-date="year"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-muted text-xs" style="margin-left:0.5rem">from</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" value="00"
                       data-testid="search-log-date-fhour"
                       data-search-date="fhour"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">:</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" value="00"
                       data-testid="search-log-date-fminute"
                       data-search-date="fminute"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-muted text-xs" style="margin-left:0.5rem">to</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" value="23"
                       data-testid="search-log-date-thour"
                       data-search-date="thour"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
                <span class="text-faint">:</span>
                <input class="input font-mono" style="width:3rem" type="text" maxlength="2" value="59"
                       data-testid="search-log-date-tminute"
                       data-search-date="tminute"
                       autocomplete="off"
                       inputmode="numeric"
                       pattern="[0-9]*">
            </div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-log-submit-date"
                    data-search-key="date"
                    data-search-compose="date">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>

        <div class="grid gap-3" style="grid-template-columns:14rem 1fr auto;align-items:end">
            <div>
                <label class="label" for="search-log-type">Type</label>
                <select class="select"
                        id="search-log-type"
                        data-testid="search-log-type">
                    <option value="m">Message</option>
                    <option value="w">Warning</option>
                    <option value="e">Error</option>
                </select>
            </div>
            <div class="text-xs text-muted">Filter to a single severity level.</div>
            <button type="submit"
                    class="btn btn--secondary btn--sm"
                    data-testid="search-log-submit-type"
                    data-search-key="type"
                    data-search-from="search-log-type">
                <i data-lucide="search" style="width:14px;height:14px"></i> Search
            </button>
        </div>
    </div>
</form>

{*
    Inline submit dispatcher — vanilla, no sourcebans.js dependency.

    Each per-row submit button declares the search criterion via
    `data-search-key`. The dispatcher (1) reads either the linked
    field via `data-search-from`, or composes the date tuple via
    `data-search-compose="date"`, (2) populates the hidden
    `advType` / `advSearch` form inputs, and (3) lets the form submit
    natively. The browser navigates to the consumer URL with the
    correct query string — same wire format as the legacy
    `search_log()` global from sourcebans.js, minus the `#^2` hash
    that the sbpp2026 logs page no longer needs.
*}
<script>
{literal}
(function () {
    'use strict';
    var form = document.querySelector('[data-testid="search-log-form"]');
    if (!(form instanceof HTMLFormElement)) return;

    var typeField = form.querySelector('[data-search-type]');
    var valueField = form.querySelector('[data-search-value]');
    if (!(typeField instanceof HTMLInputElement) || !(valueField instanceof HTMLInputElement)) return;

    /**
     * @param {Element} btn
     * @returns {string}
     */
    function readValue(btn) {
        var compose = btn.getAttribute('data-search-compose');
        if (compose === 'date') {
            var keys = ['day', 'month', 'year', 'fhour', 'fminute', 'thour', 'tminute'];
            return keys.map(function (k) {
                var el = form.querySelector('[data-search-date="' + k + '"]');
                return (el instanceof HTMLInputElement) ? el.value : '';
            }).join(',');
        }
        var fromId = btn.getAttribute('data-search-from');
        if (!fromId) return '';
        var src = document.getElementById(fromId);
        if (src instanceof HTMLInputElement || src instanceof HTMLSelectElement || src instanceof HTMLTextAreaElement) {
            return src.value;
        }
        return '';
    }

    Array.prototype.forEach.call(form.querySelectorAll('button[data-search-key]'), function (btn) {
        btn.addEventListener('click', function (ev) {
            var key = btn.getAttribute('data-search-key') || '';
            var value = readValue(btn);
            if (key === '' || value === '' || value.replace(/[,]/g, '') === '') {
                ev.preventDefault();
                return;
            }
            typeField.value = key;
            valueField.value = value;
        });
    });
})();
{/literal}
</script>
