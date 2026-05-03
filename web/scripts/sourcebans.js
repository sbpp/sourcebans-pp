// @ts-check
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>

Vanilla rewrite. Talks to /api.php through sb.api.call() (see api.js) and
manipulates the DOM directly through sb.* helpers (see sb.js).

Action names (Actions.*) and permission flags (Perms.*) come from the
generated contract in scripts/api-contract.js — see #1097. Don't redeclare
them here; regenerate the contract with `composer api-contract` instead.
*************************************************************************/

// `accordion` is declared globally in scripts/globals.d.ts so InitAccordion()
// below and per-page templates that reference it share one binding. Adding a
// second `let accordion;` here would be a TS2451 redeclaration.

// =====================================================================
// Generic envelope handler — applies side-effects from a JSON response.
//
// Server responses can include:
//   data.message  { title, body, kind, redir }
//   data.remove   string|array of element ids whose row to slide-up + remove
//   data.counter  { id: number, ... }     setHTML on each id
//   data.reload   true                    schedules TabToReload()
//   data.rehash   csv-string              kicks off system.rehash_admins
// =====================================================================
/**
 * @param {SbApiEnvelope | null | false | undefined} res
 * @param {{ errorTitle?: string }} [opts]
 * @returns {boolean}
 */
function applyApiResponse(res, opts) {
    opts = opts || {};
    if (!res) return false;
    if (res.redirect) return false;          // browser already navigating
    if (res.ok === false) {
        if (res.error) {
            sb.message.error(opts.errorTitle || 'Error', res.error.message || 'Unknown error');
        }
        return false;
    }

    const data = res.data || {};

    if (data.remove) {
        /** @type {string[]} */
        const ids = Array.isArray(data.remove) ? data.remove : [data.remove];
        ids.forEach((id) => SlideUp(id));
    }
    if (data.counter) {
        Object.keys(data.counter).forEach((id) => {
            const el = sb.$id(id);
            if (el) el.innerHTML = String(data.counter[id]);
        });
    }
    if (data.rehash) {
        ShowRehashBox(data.rehash, data.message ? data.message.title : 'Done',
            data.message ? data.message.body : '', data.message ? data.message.kind : 'green',
            data.message ? data.message.redir : '');
    } else if (data.message) {
        sb.message.show(data.message.title, data.message.body, data.message.kind, data.message.redir, data.message.noclose);
    }
    if (data.reload) {
        TabToReload();
    }
    return true;
}

/**
 */
function ProcessAdminTabs() { sb.tabs.init(); }

/**
 * @param {string | number} id
 * @param {string} ttype
 */
function Swap2ndPane(id, ttype) {
    if (!sb.$id(`utab-${ttype}${id}`)) return;
    let i = 0;
    while (sb.$id(ttype + i)) {
        sb.hide(ttype + i);
        i++;
    }
    let i2 = 0;
    while (i2 < 50) {
        const tab = sb.$id(`utab-${ttype}${i2}`);
        if (tab) { tab.classList.remove('active'); tab.classList.add('nonactive'); }
        i2++;
    }
    const active = sb.$id(`utab-${ttype}${id}`);
    if (active) { active.classList.add('active'); }
    sb.show(ttype + id);
}

// Replaces MooTools Accordion. We collapse all panels by default.
/**
 * @param {string} opener
 * @param {string} element
 * @param {string} container
 * @param {number} [num]
 */
function InitAccordion(opener, element, container, num) {
    if (num == null) num = -1;
    accordion = sb.accordion(opener, element, container, num);
}

/**
 */
function ScrollRcon() {
    const objDiv = sb.$id('rcon');
    if (objDiv) objDiv.scrollTop = objDiv.scrollHeight;
}

/**
 * @param {string} id
 * @param {number} time
 * @param {number | string} height
 */
function Shrink(id, time, height) {
    sb.animateTo(id, 'height', height, time);
}

/**
 * @param {string} id
 * @param {number} [time]
 */
function FadeElOut(id, time) { sb.fadeOut(id, time); }
/**
 * @param {string} id
 * @param {number} [time]
 */
function FadeElIn (id, time) { sb.fadeIn (id, time); }

/**
 * @param {string} [redir]
 * @returns {boolean | void}
 */
function DoLogin(redir) {
    let err = 0;
    const username = sb.$idRequired('loginUsername').value;
    const password = sb.$idRequired('loginPassword').value;
    const remember = sb.$idRequired('loginRememberMe').checked;

    if (!username) { sb.setHTML('loginUsername.msg', 'You must enter your login name!'); sb.show('loginUsername.msg'); err++; }
    else           { sb.setHTML('loginUsername.msg', '');                                sb.hide('loginUsername.msg'); }

    if (!password) { sb.setHTML('loginPassword.msg', 'You must enter your password!');   sb.show('loginPassword.msg'); err++; }
    else           { sb.setHTML('loginPassword.msg', '');                                sb.hide('loginPassword.msg'); }

    if (err) return false;
    if (typeof redir === 'undefined') redir = '';

    sb.api.call(Actions.AuthLogin, { username, password, remember, redirect: redir });
}

// Slide an element up and remove it from the DOM (replaces MooTools Fx.Slide).
/**
 * @param {string} id
 */
function SlideUp(id) {
    const el = sb.$id(id);
    if (!el) return;
    sb.slideUp(el).then(() => el.remove());
}

/**
 * @param {string | number} id
 * @param {string} name
 * @param {string} type
 */
function RemoveGroup(id, name, type) {
    if (!confirm(`Are you sure you want to delete the group: '${name}'?`)) return;
    sb.api.call(Actions.GroupsRemove, { gid: id, type }).then(applyApiResponse);
}

/**
 * @param {string | number} id
 * @param {string} name
 */
function RemoveAdmin(id, name) {
    if (!confirm(`Are you sure you want to delete '${name}'?`)) return;
    sb.api.call(Actions.AdminsRemove, { aid: id }).then(applyApiResponse);
}

/**
 * @param {string | number} id
 * @param {string} name
 * @param {string} archiv
 */
function RemoveSubmission(id, name, archiv) {
    let msg;
    if (archiv === '2') msg = `Are you sure you want to restore the ban submission for '${name}' from the archive?`;
    else if (archiv === '1') msg = `Are you sure you want to move the ban submission for '${name}' to the archive?`;
    else msg = `Are you sure you want to delete the ban submission for '${name}'?`;
    if (!confirm(msg)) return;
    sb.api.call(Actions.SubmissionsRemove, { sid: id, archiv }).then(applyApiResponse);
}

/**
 * @param {string | number} id
 * @param {string} name
 * @param {string} archiv
 */
function RemoveProtest(id, name, archiv) {
    let msg;
    if (archiv === '2') msg = `Are you sure you want to restore the ban protest for '${name}' from the archive?`;
    else if (archiv === '1') msg = `Are you sure you want to move the ban protest for '${name}' to the archive?`;
    else msg = `Are you sure you want to delete the ban protest for '${name}'?`;
    if (!confirm(msg)) return;
    sb.api.call(Actions.ProtestsRemove, { pid: id, archiv }).then(applyApiResponse);
}

/**
 * @param {string | number} id
 * @param {string} name
 */
function RemoveServer(id, name) {
    if (!confirm(`Are you sure you want to delete the server: '${name}'?`)) return;
    sb.api.call(Actions.ServersRemove, { sid: id }).then(applyApiResponse);
}

/**
 * @param {string | string[]} id
 * @param {string} key
 * @param {string} page
 * @param {string} name
 * @param {string | number} confirmStep
 * @param {string} [bulk]
 */
function RemoveBan(id, key, page, name, confirmStep, bulk) {
    if (confirmStep === 0 || confirmStep === '0') {
        ShowBox('Delete Ban', `Are you sure you want to delete the ban${bulk === 'true' ? 's' : ''} for ${bulk === 'true' ? 'those players' : `'${name}'`}?`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" onclick="RemoveBan('${id}', '${key}', '${page}', '${addslashes(String(name).replace(/'/g, "\\'"))}', '1'${bulk === 'true' ? ", 'true'" : ''});" name="rban" class="btn ok" id="rban" value="Remove Ban" />&nbsp;<input type="button" onclick="closeMsg('');sb.$idRequired('bulk_action').options[0].selected=true;" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
    } else {
        const pagelink = page || '';
        window.location.href = `index.php?p=banlist${pagelink}&a=delete&id=${id}&key=${key}${bulk === 'true' ? '&bulk=true' : ''}`;
    }
}

/**
 * @param {string | string[]} id
 * @param {string} key
 * @param {string} page
 * @param {string} name
 * @param {number | string} popup
 * @param {string} [bulk]
 */
function UnbanBan(id, key, page, name, popup, bulk) {
    if (popup === 1 || popup === '1') {
        ShowBox('Unban Reason', `<b>Please give a short comment, why you are going to unban ${bulk === 'true' ? 'those players' : `'${name}'`}!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" onclick="UnbanBan('${id}', '${key}', '${page}', '${addslashes(String(name).replace(/'/g, "\\'"))}', '0'${bulk === 'true' ? ", 'true'" : ''});" name="uban" class="btn ok" id="uban" value="Unban Ban" />&nbsp;<input type="button" onclick="closeMsg('');sb.$idRequired('bulk_action').options[0].selected=true;" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
    } else {
        const pagelink = page || '';
        const reason = sb.$idRequired('ureason').value;
        if (!reason) { sb.setHTML('ureason.msg', 'Please leave a comment.'); sb.show('ureason.msg'); return; }
        sb.setHTML('ureason.msg', ''); sb.hide('ureason.msg');
        window.location.href = `index.php?p=banlist${pagelink}&a=unban&id=${id}&key=${key}&ureason=${reason}${bulk === 'true' ? '&bulk=true' : ''}`;
    }
}

/**
 * @returns {string}
 */
function BoxToSrvMask() {
    let s = '';
    if (!sb.$id('s1')) return s;
    /** @type {Record<string, string>} */
    const map = { s1:'a', s23:'b', s2:'c', s3:'d', s4:'e', s5:'f', s6:'g', s7:'h', s8:'i', s9:'j', s10:'k', s11:'l', s12:'m', s13:'n', s17:'o', s18:'p', s19:'q', s20:'r', s21:'s', s22:'t', s14:'z' };
    Object.keys(map).forEach((k) => { const el = sb.$id(k); if (el && el.checked) s += map[k]; });
    const imm = sb.$id('immunity');
    if (imm && imm.value) s += `#${imm.value}`;
    return s;
}

/**
 * @returns {number}
 */
function BoxToMask() {
    let m = 0;
    if (!sb.$id('p4')) return m;
    /** @type {Record<string, number>} */
    const map = {
        p4: Perms.ADMIN_LIST_ADMINS, p5: Perms.ADMIN_ADD_ADMINS, p6: Perms.ADMIN_EDIT_ADMINS, p7: Perms.ADMIN_DELETE_ADMINS,
        p9: Perms.ADMIN_LIST_SERVERS, p10: Perms.ADMIN_ADD_SERVER, p11: Perms.ADMIN_EDIT_SERVERS, p12: Perms.ADMIN_DELETE_SERVERS,
        p14: Perms.ADMIN_ADD_BAN, p16: Perms.ADMIN_EDIT_OWN_BANS, p17: Perms.ADMIN_EDIT_GROUP_BANS, p18: Perms.ADMIN_EDIT_ALL_BANS,
        p19: Perms.ADMIN_BAN_PROTESTS, p20: Perms.ADMIN_BAN_SUBMISSIONS, p38: Perms.ADMIN_UNBAN_OWN_BANS, p39: Perms.ADMIN_UNBAN_GROUP_BANS,
        p32: Perms.ADMIN_UNBAN, p33: Perms.ADMIN_DELETE_BAN, p34: Perms.ADMIN_BAN_IMPORT,
        p36: Perms.ADMIN_NOTIFY_SUB, p37: Perms.ADMIN_NOTIFY_PROTEST,
        p22: Perms.ADMIN_LIST_GROUPS, p23: Perms.ADMIN_ADD_GROUP, p24: Perms.ADMIN_EDIT_GROUPS, p25: Perms.ADMIN_DELETE_GROUPS,
        p26: Perms.ADMIN_WEB_SETTINGS, p28: Perms.ADMIN_LIST_MODS, p29: Perms.ADMIN_ADD_MODS, p30: Perms.ADMIN_EDIT_MODS, p31: Perms.ADMIN_DELETE_MODS,
        p2: Perms.ADMIN_OWNER,
    };
    Object.keys(map).forEach((k) => { const el = sb.$id(k); if (el && el.checked) m |= map[k]; });
    return m;
}

/**
 * @param {number | string} tgl
 * @param {number} start
 * @param {number} stop
 * @param {...(number | string)} _extra additional ids passed positionally; read from `arguments`
 */
function UpdateCheckBox(tgl, start, stop, ..._extra) {
    void _extra;
    for (let i = start; i <= stop; i++) {
        const el = sb.$id(`p${i}`);
        if (el) el.checked = sb.$idRequired(`p${tgl}`).checked;
    }
    if (arguments.length > 3) {
        for (let lp = 4; lp <= arguments.length; lp++) {
            const el = sb.$id(`p${arguments[lp - 1]}`);
            if (el) el.checked = sb.$idRequired(`p${tgl}`).checked;
        }
    }
}

/**
 */
function ProcessGroup() {
    sb.api.call(Actions.GroupsAdd, {
        name:     sb.$idRequired('groupname').value,
        type:     sb.$idRequired('grouptype').value,
        bitmask:  BoxToMask(),
        srvflags: BoxToSrvMask(),
    }).then(applyApiResponse);
}

/**
 */
function update_web() {
    sb.setHTML('webperm', '');
    const v = sb.$idRequired('webg').value;
    if (v === 'c' || v === 'n') { sb.setHTML('web.msg', 'Please Wait...'); sb.show('web.msg'); }

    let height = 1;
    if (v === 'c') height = 390;
    else if (v === 'n') height = 410;
    else { sb.setHTML('webperm', ''); }
    Shrink('webperm', 1000, height);

    if (v === 'c' || v === 'n') {
        setTimeout(() => {
            sb.api.call(Actions.AdminsUpdatePerms, { type: 1, value: v }).then((r) => {
                if (r && r.ok) {
                    const id = (r.data && r.data.id) || 'web';
                    sb.setHTML(id + 'perm', (r.data && r.data.permissions) || '');
                    if (r.data && !r.data.is_owner) {
                        const w = sb.$id('wrootcheckbox'); if (w) w.style.display = 'none';
                        const s = sb.$id('srootcheckbox'); if (s) s.style.display = 'none';
                    }
                    sb.setHTML(id + '.msg', '');
                }
            });
        }, 1000);
    } else {
        sb.setHTML('web.msg', ''); sb.hide('web.msg');
    }
}

/**
 */
function update_server_groups() {
    sb.setHTML('nsgroup', '');
    if (sb.$idRequired('serverg').value === 'n') {
        sb.setHTML('group.msg', 'Please Wait...'); sb.show('group.msg');
        Shrink('nsgroup', 500, 50);
        setTimeout(() => {
            sb.api.call(Actions.GroupsAddServerGroupName, {}).then((r) => {
                if (r && r.ok && r.data) {
                    sb.setHTML('nsgroup', r.data.html || '');
                    sb.setHTML('group.msg', '');
                }
            });
        }, 500);
    } else {
        Shrink('nsgroup', 500, 5);
        sb.setHTML('group.msg', ''); sb.hide('group.msg');
    }
}

/**
 */
function ProcessAddAdmin() {
    let mask    = BoxToMask();
    let srvMask = BoxToSrvMask();
    let serverPass = '-1';

    let grp = '';
    document.getElementsByName('group[]').forEach((el) => {
        const cb = /** @type {HTMLInputElement} */ (el);
        if (cb.checked) grp += `,${cb.value}`;
    });

    let svr = '';
    document.getElementsByName('servers[]').forEach((el) => {
        const cb = /** @type {HTMLInputElement} */ (el);
        if (cb.checked) svr += `,${cb.value}`;
    });

    const serverg = sb.$idRequired('serverg').value;
    if (serverg === '-3') srvMask = '';
    const webg = sb.$idRequired('webg').value;
    if (webg === '-3')    mask    = 0;

    if (sb.$id('a_useserverpass') && sb.$idRequired('a_useserverpass').checked) {
        serverPass = sb.$idRequired('a_serverpass').value;
    }

    const params = {
        mask, srv_mask: srvMask,
        name:      sb.$idRequired('adminname').value,
        steam:     sb.$idRequired('steam').value,
        email:     sb.$idRequired('email').value,
        password:  sb.$idRequired('password').value,
        password2: sb.$idRequired('password2').value,
        server_group: serverg,
        web_group:    webg,
        server_password: serverPass,
        web_name:     sb.$id('webname')    ? sb.$idRequired('webname').value    : 0,
        server_name:  sb.$id('servername') ? sb.$idRequired('servername').value : 0,
        servers:        grp,
        single_servers: svr,
    };
    sb.api.call(Actions.AdminsAdd, params).then(applyApiResponse);
}

/**
 */
function ProcessEditAdminPermissions() {
    const mask = BoxToMask();
    const srvMask = BoxToSrvMask();
    const aid = sb.$idRequired('admin_id').value;
    const imm = sb.$id('immunity');
    if (imm && !IsNumeric(imm.value)) {
        ShowBox('Error', 'Immunity must be a numerical value (0-9)', 'red', '', true);
        return;
    }
    sb.api.call(Actions.AdminsEditPerms, { aid, web_flags: mask, srv_flags: srvMask }).then(applyApiResponse);
}

/**
 * @param {string} type
 * @param {string} name
 */
function ProcessEditGroup(type, name) {
    const mask = BoxToMask();
    const srvMask = BoxToSrvMask();
    const group = sb.$idRequired('group_id').value;

    if (!name) {
        ShowBox('Error', 'You have to type a name for the group.', 'red', '', true);
        sb.setHTML('groupname.msg', 'You have to type a name for the group.');
        sb.show('groupname.msg');
        return;
    }
    sb.setHTML('groupname.msg', ''); sb.hide('groupname.msg');

    const imm = sb.$id('immunity');
    if (imm && !IsNumeric(imm.value)) {
        ShowBox('Error', 'Immunity must be a numerical value (0-9)', 'red', '', true);
        return;
    }

    /** @type {Record<string, {id: string, type: string, name: string, access: string}>} */
    let overrides = {};
    /** @type {{type: string, name: string, access: string}} */
    let newOverride = { type: '', name: '', access: '' };
    if (type === 'srv') {
        // <form name="group_overrides_form"> — `document.forms.namedItem` is
        // the typed alternative to `document.group_overrides_form`. Each
        // override field is a repeated `name="override_*[]"`, which the DOM
        // exposes as either a single Element or a RadioNodeList depending on
        // count; coerce both to a flat array of HTMLInputElement / -Select.
        const form = document.forms.namedItem('group_overrides_form');
        const idsRaw = form ? form.elements.namedItem('override_id[]') : null;
        if (idsRaw != null) {
            /** @param {Element | RadioNodeList | null} v */
            const toArr = (v) => v == null
                ? []
                : (v instanceof RadioNodeList ? Array.from(v) : [/** @type {Element} */ (v)]);
            const ids    = /** @type {HTMLInputElement[]} */ (toArr(idsRaw));
            const types  = /** @type {HTMLSelectElement[]} */ (toArr(form ? form.elements.namedItem('override_type[]')   : null));
            const names  = /** @type {HTMLInputElement[]}  */ (toArr(form ? form.elements.namedItem('override_name[]')   : null));
            const access = /** @type {HTMLSelectElement[]} */ (toArr(form ? form.elements.namedItem('override_access[]') : null));

            for (let i = 0; i < ids.length; i++) {
                overrides[i] = {
                    id:     ids[i].value,
                    type:   types[i].options[types[i].selectedIndex].value,
                    name:   names[i].value,
                    access: access[i].options[access[i].selectedIndex].value,
                };
            }
        }
        const t = sb.$id('new_override_type');
        const n = sb.$id('new_override_name');
        const a = sb.$id('new_override_access');
        newOverride = {
            type:   t ? t.options[t.selectedIndex].value : '',
            name:   n ? n.value : '',
            access: a ? a.options[a.selectedIndex].value : '',
        };
    }

    sb.api.call(Actions.GroupsEdit, {
        gid: group, web_flags: mask, srv_flags: srvMask, type, name,
        overrides: JSON.stringify(overrides),
        new_override: JSON.stringify(newOverride),
    }).then(applyApiResponse);
}

/**
 */
function update_server() {
    sb.setHTML('serverperm', '');
    const v = sb.$idRequired('serverg').value;
    if (v === 'c' || v === 'n') { sb.setHTML('server.msg', 'Please Wait...'); sb.show('server.msg'); }
    let height = 1;
    if (v === 'c') height = 580;
    else if (v === 'n') height = 590;
    else { sb.setHTML('serverperm', ''); }
    Shrink('serverperm', 1000, height);

    if (v === 'c' || v === 'n') {
        setTimeout(() => {
            sb.api.call(Actions.AdminsUpdatePerms, { type: 2, value: v }).then((r) => {
                if (r && r.ok) {
                    const id = (r.data && r.data.id) || 'server';
                    sb.setHTML(id + 'perm', (r.data && r.data.permissions) || '');
                    if (r.data && !r.data.is_owner) {
                        const w = sb.$id('wrootcheckbox'); if (w) w.style.display = 'none';
                        const s = sb.$id('srootcheckbox'); if (s) s.style.display = 'none';
                    }
                    sb.setHTML(id + '.msg', '');
                }
            });
        }, 1000);
    } else {
        sb.setHTML('server.msg', ''); sb.hide('server.msg');
    }
}

/**
 */
function process_add_server() {
    let grp = '';
    document.getElementsByName('groups[]').forEach((el) => {
        const cb = /** @type {HTMLInputElement} */ (el);
        if (cb.checked) grp += `,${cb.value}`;
    });
    sb.api.call(Actions.ServersAdd, {
        ip:       sb.$idRequired('address').value,
        port:     sb.$idRequired('port').value,
        rcon:     sb.$idRequired('rcon').value,
        rcon2:    sb.$idRequired('rcon2').value,
        mod:      Number(sb.$idRequired('mod').value),
        enabled:  sb.$idRequired('enabled').checked,
        group:    grp,
    }).then(applyApiResponse);
}

/**
 */
function process_edit_server() {
    if (sb.$idRequired('rcon').value !== sb.$idRequired('rcon2').value) {
        sb.setHTML('rcon2.msg', "Passwords don't match.");
        sb.show('rcon2.msg');
        return;
    }
    sb.hide('rcon2.msg');
    const form = document.forms.namedItem('editserver');
    if (form) form.submit();
}

/**
 */
function search_bans() {
    let type = '', input = '';
    if (sb.$idRequired('name').checked)            { type = 'name';   input = sb.$idRequired('nick').value; }
    if (sb.$idRequired('steam_').checked)          { type = (sb.$idRequired('steam_match').value === '1' ? 'steam' : 'steamid'); input = sb.$idRequired('steamid').value; }
    if (sb.$idRequired('ip_').checked)             { type = 'ip';     input = sb.$idRequired('ip').value; }
    if (sb.$idRequired('reason_').checked)         { type = 'reason'; input = sb.$idRequired('ban_reason').value; }
    if (sb.$idRequired('date').checked)            { type = 'date';   input = `${sb.$idRequired('day').value},${sb.$idRequired('month').value},${sb.$idRequired('year').value}`; }
    if (sb.$idRequired('length_').checked) {
        type = 'length';
        const length = (sb.$idRequired('length').value === 'other') ? sb.$idRequired('other_length').value : sb.$idRequired('length').value;
        input = `${sb.$idRequired('length_type').value},${length}`;
    }
    if (sb.$idRequired('ban_type_').checked)       { type = 'btype';   input = sb.$idRequired('ban_type').value; }
    if (sb.$idRequired('bancount').checked)        { type = 'bancount';input = sb.$idRequired('timesbanned').value; }
    if (sb.$idRequired('admin').checked)           { type = 'admin';   input = sb.$idRequired('ban_admin').value; }
    if (sb.$idRequired('where_banned').checked)    { type = 'where_banned'; input = sb.$idRequired('server').value; }
    if (sb.$idRequired('comment_').checked)        { type = 'comment'; input = sb.$idRequired('ban_comment').value; }
    if (type !== '' && input !== '') window.location.href = `index.php?p=banlist&advSearch=${input}&advType=${type}`;
}

/** @type {string[]} */
const webSelected = [];
/** @type {string[]} */
const srvSelected = [];
/**
 * @param {HTMLSelectElement} ob
 * @param {1 | 2} type
 */
function getMultiple(ob, type) {
    const target = type === 1 ? webSelected : type === 2 ? srvSelected : null;
    if (!target) return;
    while (ob.selectedIndex !== -1) {
        target.push(ob.options[ob.selectedIndex].value);
        ob.options[ob.selectedIndex].selected = false;
    }
}
/**
 */
function search_admins() {
    let type = '', input = '';
    if (sb.$idRequired('name_').checked)        { type = 'name';        input = sb.$idRequired('nick').value; }
    if (sb.$idRequired('steam_').checked)       { type = (sb.$idRequired('steam_match').value === '1' ? 'steam' : 'steamid'); input = sb.$idRequired('steamid').value; }
    if (sb.$idRequired('admemail_').checked)    { type = 'admemail';    input = sb.$idRequired('admemail').value; }
    if (sb.$idRequired('webgroup_').checked)    { type = 'webgroup';    input = sb.$idRequired('webgroup').value; }
    if (sb.$idRequired('srvadmgroup_').checked) { type = 'srvadmgroup'; input = sb.$idRequired('srvadmgroup').value; }
    if (sb.$idRequired('srvgroup_').checked)    { type = 'srvgroup';    input = sb.$idRequired('srvgroup').value; }
    if (sb.$idRequired('admwebflags_').checked) { type = 'admwebflag';  input = webSelected.toString(); }
    if (sb.$idRequired('admsrvflags_').checked) { type = 'admsrvflag';  input = srvSelected.toString(); }
    if (sb.$idRequired('admin_on_').checked)    { type = 'server';      input = sb.$idRequired('server').value; }
    if (type !== '' && input !== '') window.location.href = `index.php?p=admin&c=admins&advSearch=${input}&advType=${type}`;
}

/**
 */
function search_log() {
    let type = '', input = '';
    if (sb.$idRequired('admin_').checked)   { type = 'admin';   input = sb.$idRequired('admin').value; }
    if (sb.$idRequired('message_').checked) { type = 'message'; input = sb.$idRequired('message').value; }
    if (sb.$idRequired('date_').checked)    { type = 'date';    input = `${sb.$idRequired('day').value},${sb.$idRequired('month').value},${sb.$idRequired('year').value},${sb.$idRequired('fhour').value},${sb.$idRequired('fminute').value},${sb.$idRequired('thour').value},${sb.$idRequired('tminute').value}`; }
    if (sb.$idRequired('type_').checked)    { type = 'type';    input = sb.$idRequired('type').value; }
    if (type !== '' && input !== '') window.location.href = `index.php?p=admin&c=settings&advSearch=${input}&advType=${type}#^2`;
}

let icname = '';
/**
 * @param {string} name
 */
function icon(name) {
    sb.setHTML('icon.msg', `Uploaded: <b>${name}</b>`);
    icname = name;
    if (sb.$id('icon_hid')) sb.$idRequired('icon_hid').value = name;
}

/**
 * @returns {0 | undefined}
 */
function ProcessMod() {
    let err = 0;
    if (!sb.$idRequired('name').value)   { sb.setHTML('name.msg',   'You must enter the name of the mod you are adding.'); sb.show('name.msg'); err++; }
    else                          { sb.setHTML('name.msg',   ''); sb.hide('name.msg'); }
    if (!sb.$idRequired('folder').value) { sb.setHTML('folder.msg', "You must enter mod's folder name.");                   sb.show('folder.msg'); err++; }
    else                          { sb.setHTML('folder.msg', ''); sb.hide('folder.msg'); }
    if (err) return 0;

    sb.api.call(Actions.ModsAdd, {
        name:           sb.$idRequired('name').value,
        folder:         sb.$idRequired('folder').value,
        icon:           icname,
        steam_universe: Number(sb.$idRequired('steam_universe').value),
        enabled:        sb.$idRequired('enabled').checked,
    }).then(applyApiResponse);
}

/**
 * @param {string} title
 * @param {string} msg
 * @param {string} [color]
 * @param {string} [redir]
 * @param {boolean} [noclose]
 */
function ShowBox(title, msg, color, redir, noclose) {
    sb.message.show(title, msg, color, redir, noclose);
}
/**
 * @param {string} [redir]
 */
function closeMsg(redir) { sb.message.close(redir); }

/**
 */
function TabToReload() {
    const url = window.location.toString();
    const nurl = url.replace(`#^${url[url.length - 1]}`, '');
    setTimeout(() => { window.location.href = nurl; }, 2000);
}

/**
 * @param {string} type
 * @param {string | number} id
 */
function CheckEmail(type, id) {
    let err = 0;
    if (!sb.$idRequired('subject').value) { sb.setHTML('subject.msg', 'You must type a subject for the email.'); sb.show('subject.msg'); err++; }
    else                           { sb.setHTML('subject.msg', ''); sb.hide('subject.msg'); }
    if (!sb.$idRequired('message').value) { sb.setHTML('message.msg', 'You must type a message for the email.'); sb.show('message.msg'); err++; }
    else                           { sb.setHTML('message.msg', ''); sb.hide('message.msg'); }
    if (err > 0) return;
    sb.api.call(Actions.SystemSendMail, {
        subject: sb.$idRequired('subject').value,
        message: sb.$idRequired('message').value,
        type, id,
    }).then(applyApiResponse);
}

/**
 * @param {string} sText
 * @returns {boolean}
 */
function IsNumeric(sText) {
    const ValidChars = '0123456789.';
    for (let i = 0; i < sText.length; i++) {
        if (ValidChars.indexOf(sText.charAt(i)) === -1) return false;
    }
    return true;
}

/**
 * @param {SbElLike} el
 */
function ButtonOver(el) {
    el = sb.$id(el);
    if (!el) return;
    if (el.classList.contains('btn'))    { el.classList.remove('btn');    el.classList.add('btnhvr'); }
    else                                  { el.classList.remove('btnhvr'); el.classList.add('btn'); }
}

/**
 */
function ClearLogs() {
    if (!confirm('Are you sure you want to delete all of the log entries?')) return;
    window.location.href = 'index.php?p=admin&c=settings&log_clear=true#^2';
}

/**
 * @param {string} name
 * @param {string | number} id
 */
function RemoveMod(name, id) {
    if (!confirm(`Are you sure you want to delete '${name}'?`)) return;
    sb.api.call(Actions.ModsRemove, { mid: id }).then(applyApiResponse);
}

/**
 */
function UpdateGroupPermissionCheckBoxes() {
    sb.setHTML('perms', '');
    const v = sb.$idRequired('grouptype').value;
    if (v !== '3' && v !== '0') { sb.setHTML('type.msg', 'Please Wait...'); sb.show('type.msg'); }

    let height = 2;
    if (v === '1') height = 285;
    else if (v === '2') height = 435;
    else { sb.hide('type.msg'); }
    Shrink('perms', 1000, height);

    if (v !== '3' && v !== '0') {
        setTimeout(() => {
            sb.api.call(Actions.GroupsUpdatePerms, { gid: Number(v) }).then((r) => {
                if (r && r.ok && r.data) {
                    sb.setHTML('perms', r.data.permissions || '');
                    if (!r.data.is_owner) {
                        const w = sb.$id('wrootcheckbox'); if (w) w.style.display = 'none';
                        const s = sb.$id('srootcheckbox'); if (s) s.style.display = 'none';
                    }
                    sb.setHTML('type.msg', ''); sb.hide('type.msg');
                }
            });
        }, 1000);
    }
}

/**
 * @param {HTMLSelectElement} newPage
 * @param {string} type
 * @param {string} [advSearch]
 * @param {string} [advType]
 */
function changePage(newPage, type, advSearch, advType) {
    const next = newPage.options[newPage.selectedIndex].value;
    const searchlink = (advSearch && advType) ? `&advSearch=${advSearch}&advType=${advType}` : '';
    if (next === '0') return;
    /** @type {Record<string, string>} */
    const dest = {
        A:  `index.php?p=admin&c=admins${searchlink}&page=${next}`,
        B:  `index.php?p=banlist${searchlink}&page=${next}`,
        C:  `index.php?p=commslist${searchlink}&page=${next}`,
        L:  `index.php?p=admin&c=settings${searchlink}&page=${next}#^2`,
        P:  `index.php?p=admin&c=bans&ppage=${next}#^1`,
        PA: `index.php?p=admin&c=bans&papage=${next}#^1~p1`,
        S:  `index.php?p=admin&c=bans&spage=${next}#^2`,
        SA: `index.php?p=admin&c=bans&sapage=${next}#^2~s1`,
    };
    if (dest[type]) window.location.href = dest[type];
}

/**
 * @param {string} check
 * @param {string} type
 */
function ShowKickBox(check, type) {
    ShowBox('Ban Added',
        `The ban has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check=${check}&type=${type}"></iframe>`,
        'green', 'index.php?p=admin&c=bans', true);
}

/**
 * @param {string} servers
 * @param {string} title
 * @param {string} msg
 * @param {string} color
 * @param {string} redir
 */
function ShowRehashBox(servers, title, msg, color, redir) {
    if (servers === '' || servers == null) {
        ShowBox(title, msg, color, redir, true);
        return;
    }
    msg = `${msg}<br /><hr /><i>Rehashing Admin and Group data on all related servers...</i><div id="rehashDiv" name="rehashDiv" width="100%"></div>`;
    ShowBox(title, msg, color, redir, true);
    sb.hide('dialog-control');

    sb.api.call(Actions.SystemRehashAdmins, { servers }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const div = sb.$id('rehashDiv');
        if (!div) return;
        const total = r.data.results.length;
        /** @type {Array<{sid: number, success: boolean}>} */ (r.data.results).forEach((row, i) => {
            const text = row.success
                ? `Server #${row.sid} (${i + 1}/${total}): <font color='green'>successful</font>.<br />`
                : `Server #${row.sid} (${i + 1}/${total}): <font color='red'>Can't connect to server.</font>.<br />`;
            div.insertAdjacentHTML('beforeend', text);
        });
        sb.show('dialog-control');
    });
}

/**
 * @returns {0 | undefined}
 */
function ProcessComment() {
    if (!sb.$idRequired('commenttext').value) {
        sb.setHTML('commenttext.msg', 'You have to type your comment'); sb.show('commenttext.msg');
        return 0;
    }
    sb.setHTML('commenttext.msg', ''); sb.hide('commenttext.msg');

    const cid = sb.$idRequired('cid').value;
    const params = {
        bid: sb.$idRequired('bid').value,
        cid,
        ctype: sb.$idRequired('ctype').value,
        ctext: sb.$idRequired('commenttext').value,
        page:  Number(sb.$idRequired('page').value),
    };
    if (cid === '-1' || Number(cid) === -1) {
        sb.api.call(Actions.BansAddComment, params).then(applyApiResponse);
    } else {
        sb.api.call(Actions.BansEditComment, params).then(applyApiResponse);
    }
}

/**
 * @param {string | number} cid
 * @param {string} type
 * @param {string | number} page
 */
function RemoveComment(cid, type, page) {
    if (!confirm('Are you sure you want to delete the comment?')) return;
    sb.api.call(Actions.BansRemoveComment, { cid, ctype: type, page: Number(page) }).then(applyApiResponse);
}

/**
 */
function TickSelectAll() {
    let i = 0;
    while (sb.$id(`chkb_${i}`)) {
        sb.$idRequired(`chkb_${i}`).checked = sb.$idRequired('tickswitch').value === '0';
        i++;
    }
    if (sb.$idRequired('tickswitch').value === '0') {
        sb.$idRequired('tickswitch').value = '1';
        sb.$idRequired('tickswitch').setAttribute('title', 'Deselect All');
        sb.$idRequired('tickswitchlink').setAttribute('title', 'Deselect All');
        sb.$idRequired('tickswitchlink').innerHTML = 'Deselect All';
    } else {
        sb.$idRequired('tickswitch').value = '0';
        sb.$idRequired('tickswitch').setAttribute('title', 'Select All');
        sb.$idRequired('tickswitchlink').setAttribute('title', 'Select All');
        sb.$idRequired('tickswitchlink').innerHTML = 'Select All';
    }
}

/**
 * @param {HTMLSelectElement} action
 * @param {string} bankey
 */
function BulkEdit(action, bankey) {
    const option = action.options[action.selectedIndex].value;
    const ids = [];
    let i = 0;
    while (sb.$id(`chkb_${i}`)) { if (sb.$idRequired(`chkb_${i}`).checked) ids.push(sb.$idRequired(`chkb_${i}`).value); i++; }
    if (option === 'U') UnbanBan(ids, bankey, '', 'Bulk Unban',  '1', 'true');
    if (option === 'D') RemoveBan(ids, bankey, '', 'Bulk Delete', '0', 'true');
}

/**
 * @param {string} fid
 * @param {string} name
 */
function BanFriendsProcess(fid, name) {
    if (!confirm(`Are you sure you want to ban all steam community friends of '${name}'?`)) return;
    ShowBox(`Banning friends of ${name}`, `Banning all steam community friends of '${name}'.<br />Please wait...`, 'blue', '', true);
    sb.hide('dialog-control');
    sb.api.call(Actions.BansBanFriends, { friendid: fid, name }).then((r) => { applyApiResponse(r); });
}

/**
 * @param {string | number} sid
 * @param {string} name
 * @param {number} popup
 */
function OpenMessageBox(sid, name, popup) {
    if (popup === 1) {
        ShowBox('Send Message', `<b>Please type the message you want to send to <br>'${name}'.</b><br>You need to have basechat.smx enabled as we use<br><i>&lt;sm_psay&gt;</i>.<br><textarea rows="3" cols="40" name="ingamemsg" id="ingamemsg" style="overflow:auto;"></textarea><br><div id="ingamemsg.msg" class="badentry"></div>`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" name="ingmsg" class="btn ok" id="ingmsg" value="Send Message" />&nbsp;<input type="button" onclick="closeMsg('');" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
        sb.$idRequired('ingmsg').addEventListener('click', () => OpenMessageBox(sid, name, 0));
    } else if (popup === 0) {
        const message = sb.$idRequired('ingamemsg').value;
        if (!message) { sb.setHTML('ingamemsg.msg', 'Please type your message.'); sb.show('ingamemsg.msg'); return; }
        sb.setHTML('ingamemsg.msg', ''); sb.hide('ingamemsg.msg');
        sb.hide('dialog-control');
        sb.$idRequired('ingamemsg').readOnly = true;
        sb.api.call(Actions.BansSendMessage, { sid, name, message }).then(applyApiResponse);
    }
}

/**
 * @param {string | number} sid
 * @param {string} name
 * @param {number} conf
 */
function KickPlayerConfirm(sid, name, conf) {
    if (conf === 0) {
        ShowBox('Kick Player', `<b>Are you sure you want to kick player <br>'${name}'?</b>`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" name="kbutton" class="btn ok" id="kbutton" value="Yes" />&nbsp;<input type="button" onclick="closeMsg('');" name="astop" class="btn cancel" id="astop" value="No" />`);
        sb.$idRequired('kbutton').addEventListener('click', () => KickPlayerConfirm(sid, name, 1));
    } else if (conf === 1) {
        sb.hide('dialog-control');
        sb.api.call(Actions.BansKickPlayer, { sid, name }).then(applyApiResponse);
    }
}

/**
 * @param {string} filename
 */
function mapimg(filename) { sb.setHTML('mapimg.msg', `Uploaded: <b>${filename}</b>`); }

/**
 * @param {number} length
 * @param {number} type
 * @param {string} reason
 */
function selectLengthTypeReason(length, type, reason) {
    const banlength = sb.$id('banlength');
    if (banlength) {
        for (let i = 0; i <= banlength.length; i++) {
            if (banlength.options[i] && banlength.options[i].value === String(length / 60)) {
                banlength.options[i].selected = true;
                break;
            }
        }
    }
    const ttype = sb.$id('type');
    if (ttype && ttype.options[type]) ttype.options[type].selected = true;

    const list = sb.$id('listReason');
    if (list) {
        for (let i = 0; i <= list.length; i++) {
            if (!list.options[i]) continue;
            if (list.options[i].innerHTML === reason) { list.options[i].selected = true; break; }
            if (list.options[i].value === 'other') {
                sb.$idRequired('txtReason').value = reason;
                sb.$idRequired('dreason').style.display = 'block';
                list.options[i].selected = true;
                break;
            }
        }
    }
}

/**
 * @param {string | number} sid
 * @param {string} name
 */
function ViewCommunityProfile(sid, name) {
    ShowBox('View Community Profile', `Generating Community Profile link for "${name}", please wait...`, 'blue', '', true);
    sb.hide('dialog-control');
    sb.api.call(Actions.BansViewCommunity, { sid, name }).then((r) => {
        if (r && r.ok && r.data && r.data.url) {
            window.open(r.data.url);
            sb.message.show('Community Profile', `<b>Watch the profile <a href="${r.data.url}" target="_blank">here</a>.</b>`, 'green', '', true);
            sb.show('dialog-control');
        } else {
            applyApiResponse(r);
        }
    });
}

/**
 * @param {unknown} str
 * @returns {string}
 */
function addslashes(str) {
    return (`${str}`).replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

/**
 * @param {string} id
 * @param {string} key
 * @param {string} page
 * @param {string} name
 * @param {string | number} confirmStep
 */
function RemoveBlock(id, key, page, name, confirmStep) {
    if (confirmStep === 0 || confirmStep === '0') {
        ShowBox('Delete Block', `Are you sure you want to delete the block for ${name}?`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" onclick="RemoveBlock('${id}', '${key}', '${page}', '${addslashes(String(name).replace(/'/g, "\\'"))}', '1');" name="rban" class="btn ok" id="rban" value="Remove Block" />&nbsp;<input type="button" onclick="closeMsg('');sb.$idRequired('bulk_action').options[0].selected=true;" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
    } else {
        const pagelink = page || '';
        window.location.href = `index.php?p=commslist${pagelink}&a=delete&id=${id}&key=${key}`;
    }
}

/**
 * @param {string} id
 * @param {string} key
 * @param {string} page
 * @param {string} name
 * @param {number | string} popup
 */
function UnGag(id, key, page, name, popup) {
    if (popup === 1 || popup === '1') {
        ShowBox('UnGag Reason', `<b>Please give a short comment, why you are going to ungag '${name}'!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" onclick="UnGag('${id}', '${key}', '${page}', '${addslashes(String(name).replace(/'/g, "\\'"))}', '0');" name="uban" class="btn ok" id="uban" value="UnGag Player" />&nbsp;<input type="button" onclick="closeMsg('');" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
    } else {
        const pagelink = page || '';
        const reason = sb.$idRequired('ureason').value;
        if (!reason) { sb.setHTML('ureason.msg', 'Please leave a comment.'); sb.show('ureason.msg'); return; }
        sb.setHTML('ureason.msg', ''); sb.hide('ureason.msg');
        window.location.href = `index.php?p=commslist${pagelink}&a=ungag&id=${id}&key=${key}&ureason=${reason}`;
    }
}

/**
 * @param {string} id
 * @param {string} key
 * @param {string} page
 * @param {string} name
 * @param {number | string} popup
 */
function UnMute(id, key, page, name, popup) {
    if (popup === 1 || popup === '1') {
        ShowBox('UnMute Reason', `<b>Please give a short comment, why you are going to unmute '${name}'!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>`, 'blue', '', true);
        sb.setHTML('dialog-control', `<input type="button" onclick="UnMute('${id}', '${key}', '${page}', '${addslashes(String(name).replace(/'/g, "\\'"))}', '0');" name="uban" class="btn ok" id="uban" value="UnMute Player" />&nbsp;<input type="button" onclick="closeMsg('');" name="astop" class="btn cancel" id="astop" value="Cancel" />`);
    } else {
        const pagelink = page || '';
        const reason = sb.$idRequired('ureason').value;
        if (!reason) { sb.setHTML('ureason.msg', 'Please leave a comment.'); sb.show('ureason.msg'); return; }
        sb.setHTML('ureason.msg', ''); sb.hide('ureason.msg');
        window.location.href = `index.php?p=commslist${pagelink}&a=unmute&id=${id}&key=${key}&ureason=${reason}`;
    }
}

/**
 */
function search_blocks() {
    let type = '', input = '';
    if (sb.$idRequired('name').checked)         { type = 'name';   input = sb.$idRequired('nick').value; }
    if (sb.$idRequired('steam_').checked)       { type = (sb.$idRequired('steam_match').value === '1' ? 'steam' : 'steamid'); input = sb.$idRequired('steamid').value; }
    if (sb.$idRequired('reason_').checked)      { type = 'reason'; input = sb.$idRequired('ban_reason').value; }
    if (sb.$idRequired('date').checked)         { type = 'date';   input = `${sb.$idRequired('day').value},${sb.$idRequired('month').value},${sb.$idRequired('year').value}`; }
    if (sb.$idRequired('length_').checked) {
        type = 'length';
        const length = (sb.$idRequired('length').value === 'other') ? sb.$idRequired('other_length').value : sb.$idRequired('length').value;
        input = `${sb.$idRequired('length_type').value},${length}`;
    }
    if (sb.$idRequired('ban_type_').checked)    { type = 'btype';   input = sb.$idRequired('ban_type').value; }
    if (sb.$idRequired('bancount').checked)     { type = 'bancount';input = sb.$idRequired('timesbanned').value; }
    if (sb.$idRequired('admin').checked)        { type = 'admin';   input = sb.$idRequired('ban_admin').value; }
    if (sb.$idRequired('where_banned').checked) { type = 'where_banned'; input = sb.$idRequired('server').value; }
    if (sb.$idRequired('comment_').checked)     { type = 'comment'; input = sb.$idRequired('ban_comment').value; }
    if (type !== '' && input !== '') window.location.href = `index.php?p=commslist&advSearch=${input}&advType=${type}`;
}

/**
 * @param {string} check
 * @param {string} type
 * @param {string} length
 */
function ShowBlockBox(check, type, length) {
    ShowBox('Block Added',
        `The block has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.blockit.php?check=${check}&type=${type}&length=${length}"></iframe>`,
        'green', 'index.php?p=admin&c=comms', true);
}

/**
 * @param {HTMLElement} event
 * @param {string} target
 */
function openTab(event, target) {
    const menu = sb.$id('admin-page-menu');
    if (!menu) return;
    for (let i = 0; i < menu.children.length - 1; i++) menu.children[i].classList.remove('active');
    event.classList.add('active');

    // getElementsByClassName returns HTMLCollectionOf<Element>; we know each
    // .tabcontent is an HTMLElement at runtime so we can read .style.
    const content = /** @type {HTMLCollectionOf<HTMLElement>} */ (document.getElementsByClassName('tabcontent'));
    for (let i = 0; i < content.length; i++) {
        content[i].style.display = (content[i].id === target) ? 'block' : 'none';
    }
}

/**
 * @param {number | string} tab
 */
function swapTab(tab) {
    const menu = sb.$id('admin-page-menu');
    if (!menu) return;
    const items = menu.children;
    const idx = Number(tab);
    if (!Number.isNaN(idx) && idx <= items.length) {
        const item = /** @type {HTMLElement | undefined} */ (items[idx]);
        if (item) item.click();
    }
}

// =====================================================================
// Server host helpers — replace xajax_ServerHostPlayers / ServerHostProperty.
// =====================================================================

/**
 * Populate the host_$sid / players_$sid / etc cells for a server tile.
 * @param {number | string} sid
 * @param {string} [type]
 * @param {string} [obId]
 * @param {number | string} [tplsid]
 * @param {number | string} [open]
 * @param {boolean} [inHome]
 * @param {number} [trunchostname]
 */
function LoadServerHost(sid, type, obId, tplsid, open, inHome, trunchostname) {
    type          = type          || 'servers';
    obId          = obId          || '';
    tplsid        = tplsid        || '';
    open          = open          || '';
    trunchostname = trunchostname || 48;

    sb.api.call(Actions.ServersHostPlayers, { sid, trunchostname }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const d = r.data;

        if (d.error === 'connect') {
            const ipPort = `${sb.escapeHtml(d.ip)}:${sb.escapeHtml(d.port)}`;
            const html = d.is_owner
                ? `<b>Error connecting</b> (<i>${ipPort}</i>) <small><a href="https://sbpp.github.io/faq/">Help</a></small>`
                : `<b>Error connecting</b> (<i>${ipPort}</i>)`;
            if (type === 'servers') {
                sb.setHTML(`host_${sid}`, html);
                if (!d.is_owner) {
                    sb.setHTML(`players_${sid}`, 'N/A');
                    sb.setHTML(`os_${sid}`,      'N/A');
                    sb.setHTML(`vac_${sid}`,     'N/A');
                    sb.setHTML(`map_${sid}`,     'N/A');
                }
                if (!inHome) {
                    sb.hide(`sinfo_${sid}`);
                    sb.show(`noplayer_${sid}`);
                    sb.setStyle(`serverwindow_${sid}`, 'height', '64px');
                    if (sb.$id(`sid_${sid}`)) sb.setStyle(`sid_${sid}`, 'color', '#adadad');
                }
            }
            if (type === 'id' && obId) sb.setHTML(obId, html);
            return;
        }

        if (type === 'servers') {
            // d.hostname is already htmlspecialchars()'d server-side. d.map /
            // d.mapfull come from the gameserver verbatim — set as text.
            sb.setHTML(`host_${sid}`,    d.hostname);
            sb.setText(`players_${sid}`, `${d.players}/${d.maxplayers}`);
            sb.setHTML(`os_${sid}`,      `<i class='${sb.escapeHtml(d.os_class)} fa-2x'></i>`);
            if (d.secure) sb.setHTML(`vac_${sid}`, `<i class='fas fa-shield-alt fa-2x'></i>`);
            sb.setText(`map_${sid}`,     d.map);
            if (!inHome) {
                const img = sb.$id(`mapimg_${sid}`);
                if (img) { img.src = d.mapimg; img.alt = d.mapfull; img.title = d.map; }
                if (d.players === 0) {
                    sb.hide(`sinfo_${sid}`); sb.show(`noplayer_${sid}`);
                    sb.setStyle(`serverwindow_${sid}`, 'height', '64px');
                } else {
                    sb.show(`sinfo_${sid}`); sb.hide(`noplayer_${sid}`);
                    renderPlayerTable(sid, d.player_list, d.can_ban);
                    const ph = d.player_list.length;
                    const height = ph > 15 ? (329 + 16 * (ph - 15) + 4 * (ph - 15)) : 329;
                    sb.setStyle(`serverwindow_${sid}`, 'height', height + 'px');
                }
            }
        } else if (type === 'id') {
            sb.setHTML(obId, d.hostname);
        } else {
            sb.setHTML(`ban_server_${type}`, d.hostname);
        }

        if (tplsid && open && tplsid === open) {
            InitAccordion('tr.opener', 'div.opener', 'mainwrapper', Number(open));
            sb.show('dialog-control');
            sb.hide('dialog-placement');
        }
    });
}

/**
 * @param {number | string} sid
 * @param {Array<{name: string, frags: number | string, time_f: string, steamid?: string}>} players
 * @param {boolean} canBan
 */
function renderPlayerTable(sid, players, canBan) {
    const list = sb.$id(`playerlist_${sid}`);
    if (!list) return;
    list.innerHTML = '';

    const headers = ['Name', 'Score', 'Time'];
    const trh = list.insertRow();
    headers.forEach((h, idx) => {
        const td = trh.insertCell();
        td.className = 'listtable_top';
        td.style.height = '16px';
        if (idx === 0) td.style.width = '45%';
        if (idx === 1) td.style.width = '10%';
        const b = document.createElement('b'); b.textContent = h; td.appendChild(b);
    });

    players.forEach((p, i) => {
        const tr = list.insertRow();
        tr.className = 'tbl_out';
        tr.id = `player_s${sid}p${i}`;
        tr.onmouseout  = () => { tr.className = 'tbl_out'; };
        tr.onmouseover = () => { tr.className = 'tbl_hover'; };
        ['Name', 'Frags', 'TimeF'].forEach((field) => {
            const td = tr.insertCell();
            td.className = 'listtable_1';
            td.textContent = String(field === 'Name' ? p.name : field === 'Frags' ? p.frags : p.time_f);
        });

        if (canBan) {
            const items = [
                { name: 'Kick',        callback: () => KickPlayerConfirm(sid, p.name, 0) },
                { name: 'Block Comms', callback: () => { window.location.href = `index.php?p=admin&c=comms&action=pasteBan&sid=${encodeURIComponent(sid)}&pName=${encodeURIComponent(p.name)}`; } },
                { name: 'Ban',         callback: () => { window.location.href = `index.php?p=admin&c=bans&action=pasteBan&sid=${encodeURIComponent(sid)}&pName=${encodeURIComponent(p.name)}`; } },
                { separator: true },
                { name: 'View Profile',callback: () => ViewCommunityProfile(sid, p.name) },
                { name: 'Send Message',callback: () => OpenMessageBox(sid, p.name, 1) },
            ];
            AddContextMenu(`#player_s${sid}p${i}`, 'contextmenu', true, 'Player Commands', items);
        }
    });
}

/**
 * Replace xajax_ServerHostProperty: fetch and assign one property.
 * @param {number | string} sid
 * @param {string} obId
 * @param {string} obProp
 * @param {number} [trunchostname]
 */
function LoadServerHostProperty(sid, obId, obProp, trunchostname) {
    sb.api.call(Actions.ServersHostProperty, { sid, trunchostname: trunchostname || 48 }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const text = (r.data.error === 'connect')
            ? `Error connecting (${r.data.ip}:${r.data.port})`
            : r.data.hostname;
        const el = sb.$id(obId);
        if (!el) return;
        if (obProp === 'innerHTML') el.innerHTML = text;
        else el.setAttribute(obProp, text);
    });
}

/**
 * Replace xajax_ServerHostPlayers_list (used on the public servers page).
 * @param {Array<number | string>} sids
 * @param {string} type
 * @param {string} obId
 */
function LoadServerHostPlayersList(sids, type, obId) {
    sb.api.call(Actions.ServersHostPlayersList, { sids }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        /** @type {string[]} */
        const lines = r.data.lines || [];
        const html = lines.map((l) => `${l}<br />`).join('');
        if (type === 'id') sb.setHTML(obId, html);
        else sb.setHTML(`ban_server_${type}`, html);
    });
}

/**
 * Replace xajax_ServerPlayers (poll).
 * @param {number | string} sid
 */
function LoadServerPlayers(sid) {
    sb.api.call(Actions.ServersPlayers, { sid }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const tbl = sb.$id(`player_detail_${sid}`);
        if (tbl) {
            // Build rows with DOM ops so attacker-controlled player names
            // (which Source servers send unescaped) cannot inject HTML.
            tbl.innerHTML = '';
            /** @type {Array<Record<string, unknown>>} */ (r.data.players).forEach((p) => {
                const tr = tbl.insertRow ? tbl.insertRow() : tbl.appendChild(document.createElement('tr'));
                ['name', 'frags', 'time'].forEach((field) => {
                    const td = (tr.insertCell ? tr.insertCell() : tr.appendChild(document.createElement('td')));
                    td.className = 'listtable_1';
                    td.textContent = String(p[field] ?? '');
                });
            });
        }
        setTimeout(() => LoadServerPlayers(sid), 5000);
        const op = sb.$id(`opener_${sid}`);
        if (op) op.removeAttribute('onclick');
    });
}

/**
 * @param {string | number} sid
 */
function LoadRefreshServer(sid) { LoadServerHost(sid); }

// =====================================================================
// Setup ban / re-ban / paste — read fields, prefill the form.
// =====================================================================
/**
 * @param {Record<string, any>} d
 * @param {{ resetSelectLengthType?: boolean }} [opts]
 */
function applyBanFields(d, opts) {
    opts = opts || {};
    if (sb.$id('nickname'))      sb.$idRequired('nickname').value      = d.nickname || '';
    if (sb.$id('fromsub'))       sb.$idRequired('fromsub').value       = d.subid    || '';
    if (sb.$id('steam'))         sb.$idRequired('steam').value         = d.steam    || '';
    if (sb.$id('ip'))            sb.$idRequired('ip').value            = d.ip       || '';
    if (sb.$id('txtReason'))     sb.$idRequired('txtReason').value     = '';
    sb.setHTML('demo.msg', '');
    if (typeof selectLengthTypeReason === 'function') {
        selectLengthTypeReason(d.length || 0, d.type || 0, d.reason || '');
    }
    if (d.demo) {
        sb.setHTML('demo.msg', d.demo.origname || '');
        if (typeof window.demo === 'function') window.demo(d.demo.filename, d.demo.origname);
    }
    if (typeof swapTab === 'function') swapTab(0);
}

/**
 * @param {string | number} subid
 */
function LoadSetupBan(subid) {
    sb.api.call(Actions.BansSetupBan, { subid }).then((r) => {
        if (r && r.ok && r.data) applyBanFields(r.data);
    });
}

/**
 * @param {string | number} bid
 */
function LoadPrepareReban(bid) {
    sb.api.call(Actions.BansPrepareReban, { bid }).then((r) => {
        if (r && r.ok && r.data) applyBanFields(r.data);
    });
}

/**
 * @param {string | number} sid
 * @param {string} name
 * @param {number} [type]
 */
function LoadPasteBan(sid, name, type) {
    sb.api.call(Actions.BansPaste, { sid, name, type: type || 0 }).then((r) => {
        if (r && r.ok && r.data) {
            applyBanFields(r.data);
            sb.show('dialog-control');
            sb.hide('dialog-placement');
        } else if (r && r.ok === false && r.error) {
            sb.message.error('Error', r.error.message);
            sb.show('dialog-control');
        }
    });
}

/**
 * @param {string | number} bid
 */
function LoadPrepareReblock(bid) {
    sb.api.call(Actions.CommsPrepareReblock, { bid }).then((r) => {
        if (r && r.ok && r.data) applyBanFields(r.data);
    });
}

/**
 * @param {string | number} sid
 * @param {string} name
 */
function LoadPasteBlock(sid, name) {
    sb.api.call(Actions.CommsPaste, { sid, name }).then((r) => {
        if (r && r.ok && r.data) {
            applyBanFields(r.data);
            sb.show('dialog-control');
            sb.hide('dialog-placement');
        } else if (r && r.ok === false && r.error) {
            sb.message.error('Error', r.error.message);
            sb.show('dialog-control');
        }
    });
}

/**
 * @param {string | number} bid
 */
function LoadPrepareBlockFromBan(bid) {
    sb.api.call(Actions.CommsPrepareBlockFromBan, { bid }).then((r) => {
        if (r && r.ok && r.data) applyBanFields(r.data);
    });
}

// =====================================================================
// Setup edit server — replace xajax_SetupEditServer.
// =====================================================================
/**
 * @param {string | number} sid
 */
function LoadSetupEditServer(sid) {
    sb.api.call(Actions.ServersSetupEdit, { sid }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const d = r.data;
        if (sb.$id('address'))     sb.$idRequired('address').value     = d.ip   || '';
        if (sb.$id('port'))        sb.$idRequired('port').value        = d.port || '';
        if (sb.$id('mod'))         sb.$idRequired('mod').value         = String(d.mod);
        if (sb.$id('serverg'))     sb.$idRequired('serverg').value     = String(d.group);
        if (sb.$id('insert_type')) sb.$idRequired('insert_type').value = String(d.sid);
        if (typeof window.SwapPane === 'function') window.SwapPane(1);
    });
}

// =====================================================================
// Replace xajax_CheckVersion — fills #relver / #svnrev / #versionmsg.
// =====================================================================
/**
 */
function LoadCheckVersion() {
    sb.api.call(Actions.SystemCheckVersion, {}).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const d = r.data;
        /** @param {boolean} ok */
        const colour = (ok) => ok ? '#aa0000' : '#00aa00';
        sb.setHTML('relver', d.release_latest);
        let msg = `<span style='color:${colour(d.release_update)};'><strong>${d.release_msg}</strong></span>`;
        if (d.dev) {
            msg += `<br><span style='color:${colour(d.dev_update)};'><strong>${d.dev_msg}</strong></span>`;
            sb.setHTML('svnrev', d.dev_latest || '');
        }
        sb.setHTML('versionmsg', msg);
    });
}

// =====================================================================
// Replace xajax_CheckPassword / CheckSrvPassword — show inline error.
// =====================================================================
/**
 * @param {string | number} aid
 * @param {string} pass
 */
function LoadCheckPassword(aid, pass) {
    sb.api.call(Actions.AccountCheckPassword, { aid, password: pass }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        if (!r.data.matches) {
            sb.show('current.msg'); sb.setHTML('current.msg', 'Incorrect password.');
            if (typeof window.set_error === 'function') window.set_error(1);
        } else {
            sb.hide('current.msg');
            if (typeof window.set_error === 'function') window.set_error(0);
        }
    });
}

/**
 * @param {string | number} aid
 * @param {string} pass
 */
function LoadCheckSrvPassword(aid, pass) {
    sb.api.call(Actions.AccountCheckSrvPassword, { aid, password: pass }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        if (!r.data.matches) {
            sb.show('scurrent.msg'); sb.setHTML('scurrent.msg', 'Incorrect password.');
            if (typeof window.set_error === 'function') window.set_error(1);
        } else {
            sb.hide('scurrent.msg');
            if (typeof window.set_error === 'function') window.set_error(0);
        }
    });
}

/**
 * @param {string | number} aid
 * @param {string} newPass
 * @param {string} oldPass
 */
function LoadChangePassword(aid, newPass, oldPass) {
    sb.api.call(Actions.AccountChangePassword, { aid, new_password: newPass, old_password: oldPass }).then(applyApiResponse);
}

/**
 * @param {string | number} aid
 * @param {string} srv
 */
function LoadChangeSrvPassword(aid, srv) {
    sb.api.call(Actions.AccountChangeSrvPassword, { aid, srv_password: srv }).then(applyApiResponse);
}

/**
 * @param {string | number} aid
 * @param {string} email
 * @param {string} password
 */
function LoadChangeEmail(aid, email, password) {
    sb.api.call(Actions.AccountChangeEmail, { aid, email, password }).then(applyApiResponse);
}

// =====================================================================
// Replace xajax_GeneratePassword.
// =====================================================================
/**
 */
function LoadGeneratePassword() {
    sb.api.call(Actions.AdminsGeneratePassword, {}).then((r) => {
        if (r && r.ok && r.data && r.data.password) {
            if (sb.$id('password'))  sb.$idRequired('password').value  = r.data.password;
            if (sb.$id('password2')) sb.$idRequired('password2').value = r.data.password;
        }
    });
}

// =====================================================================
// Replace xajax_ClearCache, xajax_SelTheme, xajax_ApplyTheme.
// =====================================================================
/**
 */
function LoadClearCache() {
    sb.api.call(Actions.SystemClearCache, {}).then((r) => {
        if (r && r.ok) {
            const el = sb.$id('clearcache.msg');
            if (el) el.innerHTML = '<span style="color: green; font-size: xx-small; ">Cache cleared.</span>';
        }
    });
}

/**
 * @param {string} theme
 */
function LoadSelTheme(theme) {
    sb.api.call(Actions.SystemSelTheme, { theme }).then((r) => {
        if (r && r.ok && r.data) {
            sb.setHTML('current-theme-screenshot', `<img width="250px" height="170px" src="${r.data.screenshot}">`);
            sb.setHTML('theme.name', r.data.name);
            sb.setHTML('theme.auth', r.data.author);
            sb.setHTML('theme.vers', r.data.version);
            sb.setHTML('theme.link', `<a href="${r.data.link}" target="_new">${r.data.link}</a>`);
            sb.setHTML('theme.apply', `<input type="button" onclick="LoadApplyTheme('${r.data.theme}')" name="btnapply" class="btn ok" id="btnapply" value="Apply Theme" />`);
        } else if (r && r.ok === false && r.error) {
            alert(r.error.message);
        }
    });
}

/**
 * @param {string} theme
 */
function LoadApplyTheme(theme) {
    sb.api.call(Actions.SystemApplyTheme, { theme }).then((r) => {
        if (r && r.ok) window.location.reload();
    });
}

// =====================================================================
// Replace xajax_SendRcon.
// =====================================================================
/**
 * @param {string | number} sid
 * @param {string} command
 * @param {boolean} [output]
 */
function LoadSendRcon(sid, command, output) {
    sb.api.call(Actions.ServersSendRcon, { sid, command, output: output !== false }).then((r) => {
        const cmdEl = sb.$id('cmd');
        const btnEl = sb.$id('rcon_btn');
        if (cmdEl) { cmdEl.value = ''; cmdEl.disabled = false; }
        if (btnEl) btnEl.disabled = false;
        // Some pages overwrite the built-in `window.scroll` with a tiny
        // helper namespace `{ toBottom: () => void }`. The DOM typing
        // (Window.scroll(x, y)) collides with the namespace shape, so we
        // narrow through `any` at the call site instead of redeclaring it
        // in globals.d.ts. Programs that don't define it just no-op.
        const wScroll = /** @type {any} */ (window.scroll);
        if (typeof wScroll === 'object' && wScroll && typeof wScroll.toBottom === 'function') wScroll.toBottom();
        if (!r || !r.ok || !r.data) return;
        const out = sb.$id('rcon_con');
        if (!out) return;
        const d = r.data;
        if (d.kind === 'clear') { out.innerHTML = ''; return; }
        if (d.kind === 'error') {
            // Build the error line with textContent — gameserver-controlled
            // bytes never reach innerHTML.
            const div = document.createElement('div');
            div.textContent = `> Error: ${d.error || ''}`;
            out.appendChild(div);
            out.appendChild(document.createElement('br'));
            return;
        }
        if (d.kind === 'append') {
            const cmdLine = document.createElement('div');
            cmdLine.textContent = `-> ${d.command || ''}`;
            out.appendChild(cmdLine);

            // Preserve newlines from the rcon response without splicing
            // anything into innerHTML.
            String(d.output || '').split('\n').forEach((line, i, arr) => {
                const span = document.createElement('span');
                span.textContent = line;
                out.appendChild(span);
                if (i < arr.length - 1) out.appendChild(document.createElement('br'));
            });
            out.appendChild(document.createElement('br'));
            return;
        }
    });
}

// =====================================================================
// Replace xajax_GetGroups + xajax_BanFriends — group ban orchestration.
// =====================================================================
/**
 * @param {string} friendid
 */
function LoadGetGroups(friendid) {
    sb.api.call(Actions.BansGetGroups, { friendid }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        /** @type {Array<{url?: string, name?: string, member_count?: number | string}>} */
        const groups = r.data.groups || [];
        const tbl = sb.$id('steamGroupsTable');
        if (!tbl) return;
        if (groups.length === 0) {
            sb.message.error('Error', 'There was an error retrieving the group data. Maybe the player isn\'t member of any group or his profile is private?', 'index.php?p=banlist');
            sb.setHTML('steamGroupsText', '<i>No groups...</i>');
            return;
        }
        groups.forEach((g, i) => {
            // Steam group names/URLs are attacker-controlled (any Steam user
            // can pick a group name with HTML in it). Build the row with DOM
            // ops — never let g.name or g.url touch innerHTML/setAttribute
            // for href without sanitisation.
            const safeUrl = encodeURIComponent(String(g.url ?? ''));
            const tr = tbl.insertRow();

            const td1 = tr.insertCell();
            td1.className = 'listtable_1';
            td1.style.padding = '0px';
            td1.style.width   = '3px';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.id   = `chkb_${i}`;
            cb.value = String(g.url ?? '');
            td1.appendChild(cb);

            const td2 = tr.insertCell();
            td2.className = 'listtable_1';
            const a = document.createElement('a');
            a.href = `http://steamcommunity.com/groups/${safeUrl}`;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = String(g.name ?? '');
            td2.appendChild(a);
            td2.appendChild(document.createTextNode(' ('));
            const span = document.createElement('span');
            span.id = `membcnt_${i}`;
            span.setAttribute('value', String(g.member_count ?? 0));
            span.textContent = String(g.member_count ?? 0);
            td2.appendChild(span);
            td2.appendChild(document.createTextNode(' Members)'));
        });
        sb.hide('steamGroupsText');
        sb.show('steamGroups');
    });
}

/**
 * @param {string} grpurl
 * @param {string} queue
 * @param {string} reason
 * @param {string} last
 */
function LoadBanMemberOfGroup(grpurl, queue, reason, last) {
    sb.api.call(Actions.BansBanMemberOfGroup, { grpurl, queue, reason, last }).then((r) => {
        if (!r || !r.ok || !r.data) return;
        const a = r.data.amount;
        const sumLine = `<p>Banned ${a.total - a.before - a.failed}/${a.total} players of group '${r.data.grpurl}'. | ${a.before} were banned already. | ${a.failed} failed.</p>`;
        if (queue === 'yes') {
            const div = sb.$id('steamGroupStatus');
            if (div) div.insertAdjacentHTML('beforeend', sumLine);
            if (r.data.grpurl === r.data.last) {
                sb.message.success('Groups banned successfully', 'The selected Groups were banned successfully. For detailed info check below.');
                sb.show('dialog-control');
            }
        } else {
            sb.message.success('Group banned successfully', `Banned ${a.total - a.before - a.failed}/${a.total} players of group '${r.data.grpurl}'.<br>${a.before} were banned already.<br>${a.failed} failed.`);
            sb.show('dialog-control');
        }
    });
}

/**
 * @param {string} groupuri
 * @param {string} isgrpurl
 * @param {string} queue
 * @param {string} reason
 * @param {string} last
 */
function LoadGroupBan(groupuri, isgrpurl, queue, reason, last) {
    sb.api.call(Actions.BansGroupBan, { groupuri, isgrpurl, queue, reason, last }).then((r) => {
        if (!r || !r.ok || !r.data) {
            applyApiResponse(r, { errorTitle: 'Error parsing the group url' });
            return;
        }
        if (r.data.message) sb.message.show(r.data.message.title, r.data.message.body, r.data.message.kind, '', true);
        sb.hide('dialog-control');
        LoadBanMemberOfGroup(r.data.grpname, r.data.queue, r.data.reason, r.data.last);
    });
}

// `ProcessBan()` lives page-local in admin.bans.php and admin.comms.php
// (each calls sb.api.call(Actions.BansAdd / Actions.CommsAdd) against its
// own form). No global wrapper here on purpose — both pages would collide.

