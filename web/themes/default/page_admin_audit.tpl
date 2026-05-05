{*
    page_admin_audit.tpl — Admin → Audit log (#1123 B19) — legacy-theme stub.

    The audit log page is brand-new in #1123 B19; the legacy `default/`
    bundle never carried a per-page audit view (admin.settings.php's
    "System Log" tab was the closest equivalent). The full redesign
    lives in `web/themes/sbpp2026/page_admin_audit.tpl`. This stub
    exists only so the dual-theme PHPStan matrix (#1123 A2) can satisfy
    SmartyTemplateRule's "template file must exist" check for both
    themes; the stub marker phrase ("hasn't been redesigned yet") trips
    the rule's stub short-circuit so the View ↔ template property parity
    isn't enforced against a placeholder.

    D1 deletes `themes/default/` outright and renames `themes/sbpp2026/`
    in its place, so this file disappears at cutover.
*}
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td class="listtable_top"><b>Audit Log</b></td>
    </tr>
</table>
<div class="panel" style="padding: 1em">
    <p>This page hasn't been redesigned yet for the legacy theme — switch to <code>sbpp2026</code> to see the audit log.</p>
</div>
