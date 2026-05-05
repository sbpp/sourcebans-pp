// ============================================================
// SBPP 2026 — Extra views (Servers, Groups, Settings, Audit log, Appeals, Account)
// ============================================================

// -----------------------------------------------------------
// SERVERS — public + admin combined view
// -----------------------------------------------------------
function ServersView({ navigate }) {
  const toast = useToast();
  return (
    <div className="p-4 sm:p-6 space-y-4 max-w-[1400px]">
      <div className="flex items-end justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Servers</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
            <span className="text-emerald-600 dark:text-emerald-400 font-medium">{SERVERS.filter(s=>s.online).length}</span>
            <span> of {SERVERS.length} online · </span>
            <span className="tabular-nums">{SERVERS.reduce((a,s)=>a+s.players,0)}</span> players right now
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" icon="refresh-cw" size="md" onClick={() => toast.push({ title: 'Refreshed server statuses' })}>Refresh</Button>
          <Button variant="primary" icon="plus" size="md">Add server</Button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        {SERVERS.map((s) => (
          <Card key={s.id} className="p-4 hover:border-zinc-300 dark:hover:border-zinc-700 transition-colors">
            <div className="flex items-start gap-3">
              <GameBadge game={s.game} size={36} />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2">
                  <div className="font-semibold text-zinc-900 dark:text-zinc-100 truncate">{s.name}</div>
                </div>
                <div className="text-[11px] font-mono text-zinc-500 truncate mt-0.5">{s.host}</div>
              </div>
              <StatusPill status={s.online ? 'online' : 'offline'} />
            </div>

            {s.online ? (
              <>
                <div className="mt-4 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                  <div className="flex items-center gap-1.5"><Icon name="map" size={13} /> {s.map}</div>
                  <div className="tabular-nums font-medium text-zinc-700 dark:text-zinc-300">{s.players}/{s.max} players</div>
                </div>
                <div className="mt-2 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                  <div className="h-full bg-brand-500 rounded-full" style={{ width: `${(s.players/s.max)*100}%` }} />
                </div>
              </>
            ) : (
              <div className="mt-4 text-xs text-zinc-500 italic">Server is currently offline.</div>
            )}

            <div className="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 flex items-center gap-1">
              <Button variant="ghost" size="sm" icon="terminal-square" onClick={() => toast.push({ title: 'RCON not connected', body: 'Configure RCON in Settings.', kind: 'warn' })}>RCON</Button>
              <Button variant="ghost" size="sm" icon="users">Players</Button>
              <div className="flex-1" />
              <Button variant="ghost" size="icon"><Icon name="more-horizontal" size={14} /></Button>
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}

// -----------------------------------------------------------
// GROUPS
// -----------------------------------------------------------
const GROUPS = [
  { id: 1, name: 'Root',     immunity: 100, members: 1,  flags: ['z','a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t'] },
  { id: 2, name: 'Admin',    immunity: 80,  members: 4,  flags: ['a','b','c','d','e','f','g','h','i','j'] },
  { id: 3, name: 'Mod',      immunity: 50,  members: 6,  flags: ['b','c','d','e','f','g'] },
  { id: 4, name: 'Trial',    immunity: 20,  members: 2,  flags: ['b','c','d'] },
];
const FLAG_LABELS = {
  a:'Reservation', b:'Generic', c:'Kick', d:'Ban', e:'Unban', f:'Slay', g:'Map', h:'Cvar', i:'Config',
  j:'Chat', k:'Vote', l:'Password', m:'RCON', n:'Cheats', o:'Custom 1', p:'Custom 2', q:'Custom 3',
  r:'Custom 4', s:'Custom 5', t:'Custom 6', z:'Root',
};

function GroupsView() {
  const [selected, setSelected] = useState(GROUPS[1]);
  return (
    <div className="p-4 sm:p-6 max-w-[1400px]">
      <div className="flex items-end justify-between gap-4 flex-wrap mb-6">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Groups</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Permission flags and immunity levels.</p>
        </div>
        <Button variant="primary" icon="plus">New group</Button>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Group list */}
        <Card className="lg:col-span-1 overflow-hidden">
          <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
            {GROUPS.map((g) => (
              <button
                key={g.id}
                onClick={() => setSelected(g)}
                className={`w-full flex items-center gap-3 px-4 py-3 text-left transition-colors ${
                  selected.id === g.id ? 'bg-zinc-50 dark:bg-zinc-800/60' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/40'
                }`}
              >
                <div className={`w-9 h-9 rounded-lg flex items-center justify-center text-white font-semibold text-xs ${
                  g.name === 'Root' ? 'bg-brand-600' :
                  g.name === 'Admin' ? 'bg-blue-600' :
                  g.name === 'Mod' ? 'bg-emerald-600' : 'bg-zinc-500'
                }`}>{g.name[0]}</div>
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{g.name}</div>
                  <div className="text-xs text-zinc-500">{g.members} {g.members === 1 ? 'member' : 'members'} · immunity {g.immunity}</div>
                </div>
                {selected.id === g.id && <Icon name="chevron-right" size={14} className="text-zinc-400" />}
              </button>
            ))}
          </div>
        </Card>

        {/* Group detail */}
        <Card className="lg:col-span-2">
          <CardHeader title={selected.name} subtitle={`${selected.members} members · immunity ${selected.immunity}`} icon="shield-check"
            action={<div className="flex gap-2"><Button variant="ghost" size="sm" icon="copy">Duplicate</Button><Button variant="danger" size="sm" icon="trash-2">Delete</Button></div>}
          />
          <div className="p-5 space-y-5">
            <div className="grid sm:grid-cols-2 gap-4">
              <div>
                <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Group name</label>
                <Input className="mt-1.5" defaultValue={selected.name} />
              </div>
              <div>
                <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Immunity (0–100)</label>
                <Input type="number" className="mt-1.5" defaultValue={selected.immunity} />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Permission flags</label>
                <span className="text-[11px] text-zinc-500">{selected.flags.length} of {Object.keys(FLAG_LABELS).length}</span>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                {Object.entries(FLAG_LABELS).map(([flag, label]) => {
                  const checked = selected.flags.includes(flag);
                  return (
                    <label key={flag} className={`flex items-center gap-2 px-3 py-2 rounded-md border cursor-pointer transition-colors ${
                      checked
                        ? 'border-brand-500/50 bg-brand-50 dark:bg-brand-950/30'
                        : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700'
                    }`}>
                      <input type="checkbox" defaultChecked={checked} className="rounded border-zinc-300 dark:border-zinc-700 text-brand-600 focus:ring-brand-500" />
                      <span className={`font-mono text-xs px-1.5 h-4 rounded inline-flex items-center ${checked ? 'bg-brand-200/60 text-brand-800 dark:bg-brand-900/60 dark:text-brand-300' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400'}`}>{flag}</span>
                      <span className="text-xs text-zinc-700 dark:text-zinc-300 truncate">{label}</span>
                    </label>
                  );
                })}
              </div>
            </div>

            <div className="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
              <Button variant="ghost">Discard</Button>
              <Button variant="primary" icon="save">Save changes</Button>
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}

// -----------------------------------------------------------
// SETTINGS
// -----------------------------------------------------------
function SettingsView() {
  const [section, setSection] = useState('general');
  const toast = useToast();
  const sections = [
    { id: 'general', icon: 'settings', label: 'General' },
    { id: 'appearance', icon: 'palette', label: 'Appearance' },
    { id: 'mail', icon: 'mail', label: 'Mail (SMTP)' },
    { id: 'integrations', icon: 'plug', label: 'Integrations' },
    { id: 'security', icon: 'lock', label: 'Security' },
    { id: 'maintenance', icon: 'wrench', label: 'Maintenance' },
  ];
  return (
    <div className="p-4 sm:p-6 max-w-[1400px]">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Settings</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Site-wide configuration. Changes apply immediately.</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[200px_1fr] gap-6">
        {/* Sub-nav */}
        <nav className="lg:sticky lg:top-20 lg:self-start">
          <div className="space-y-0.5">
            {sections.map((s) => (
              <button
                key={s.id}
                onClick={() => setSection(s.id)}
                className={`w-full flex items-center gap-2.5 px-3 h-9 rounded-md text-sm font-medium transition-colors ${
                  section === s.id ? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100' : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 hover:text-zinc-900 dark:hover:text-zinc-200'
                }`}
              >
                <Icon name={s.icon} size={14} />
                <span>{s.label}</span>
              </button>
            ))}
          </div>
        </nav>

        <div className="space-y-4 max-w-2xl">
          {section === 'general' && (
            <Card>
              <CardHeader title="General" subtitle="Public-facing site identity" icon="settings" />
              <div className="p-5 space-y-5">
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Site name</label>
                  <Input className="mt-1.5" defaultValue="Skial — SourceBans++" />
                </div>
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Site URL</label>
                  <Input className="mt-1.5" defaultValue="https://bans.skial.com" />
                </div>
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Default ban length</label>
                  <Select className="mt-1.5" defaultValue="1440">
                    <option value="60">1 hour</option>
                    <option value="1440">1 day</option>
                    <option value="10080">1 week</option>
                    <option value="0">Permanent</option>
                  </Select>
                </div>
                <div className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                  <div>
                    <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Public ban list</div>
                    <div className="text-xs text-zinc-500 mt-0.5">Allow visitors to view the ban list without signing in.</div>
                  </div>
                  <Toggle defaultOn />
                </div>
                <div className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                  <div>
                    <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Show admin names publicly</div>
                    <div className="text-xs text-zinc-500 mt-0.5">Display the banning admin's name on the public ban list.</div>
                  </div>
                  <Toggle defaultOn />
                </div>
              </div>
            </Card>
          )}

          {section === 'appearance' && (
            <Card>
              <CardHeader title="Appearance" subtitle="Theme and brand color" icon="palette" />
              <div className="p-5 space-y-5">
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Default theme</label>
                  <div className="grid grid-cols-3 gap-2 mt-1.5">
                    {[
                      { id: 'light', label: 'Light', icon: 'sun' },
                      { id: 'dark', label: 'Dark', icon: 'moon' },
                      { id: 'system', label: 'System', icon: 'monitor' },
                    ].map((t) => (
                      <label key={t.id} className="flex items-center gap-2 px-3 h-10 rounded-md border border-zinc-200 dark:border-zinc-800 hover:border-zinc-300 dark:hover:border-zinc-700 cursor-pointer">
                        <input type="radio" name="theme" defaultChecked={t.id === 'system'} className="text-brand-600 focus:ring-brand-500 border-zinc-300 dark:border-zinc-700" />
                        <Icon name={t.icon} size={14} className="text-zinc-500" />
                        <span className="text-sm text-zinc-700 dark:text-zinc-300">{t.label}</span>
                      </label>
                    ))}
                  </div>
                </div>
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Accent color</label>
                  <div className="flex flex-wrap gap-2 mt-1.5">
                    {['#ea580c','#d97706','#dc2626','#2563eb','#0d9488','#7c3aed','#db2777','#52525b'].map((c, i) => (
                      <button key={c} className={`w-9 h-9 rounded-lg border-2 ${i === 0 ? 'border-zinc-900 dark:border-white' : 'border-transparent'}`} style={{ background: c }} aria-label={c} />
                    ))}
                  </div>
                </div>
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Logo</label>
                  <div className="mt-1.5 flex items-center gap-3 p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                    <div className="w-12 h-12 rounded-lg bg-brand-600 flex items-center justify-center text-white font-bold text-lg">S</div>
                    <div className="flex-1 text-xs text-zinc-500">PNG or SVG, recommended 256×256.</div>
                    <Button variant="secondary" size="sm" icon="upload">Upload</Button>
                  </div>
                </div>
              </div>
            </Card>
          )}

          {section === 'mail' && (
            <Card>
              <CardHeader title="Mail (SMTP)" subtitle="Outgoing email for protests, notifications" icon="mail"
                action={<Button variant="secondary" size="sm" icon="send-horizontal" onClick={() => toast.push({ title: 'Test email queued', kind: 'success' })}>Send test</Button>}
              />
              <div className="p-5 space-y-4">
                <div className="grid sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">SMTP host</label>
                    <Input className="mt-1.5" defaultValue="smtp.mailgun.org" />
                  </div>
                  <div>
                    <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Port</label>
                    <Input className="mt-1.5" defaultValue="587" />
                  </div>
                </div>
                <div className="grid sm:grid-cols-2 gap-4">
                  <div>
                    <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Username</label>
                    <Input className="mt-1.5" defaultValue="postmaster@bans.skial.com" />
                  </div>
                  <div>
                    <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Password</label>
                    <Input type="password" className="mt-1.5" defaultValue="••••••••••••" />
                  </div>
                </div>
                <div>
                  <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">From address</label>
                  <Input className="mt-1.5" defaultValue="bans@skial.com" />
                </div>
              </div>
            </Card>
          )}

          {section === 'integrations' && (
            <Card>
              <CardHeader title="Integrations" subtitle="Connect external services" icon="plug" />
              <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
                {[
                  { name: 'Discord webhook', desc: 'Post new bans to a Discord channel.', icon: 'message-square', connected: true },
                  { name: 'Steam Web API',   desc: 'Resolve SteamIDs to current names + avatars.', icon: 'gamepad-2', connected: true },
                  { name: 'VPNAPI.io',       desc: 'Flag bans coming from known VPN/proxy IPs.', icon: 'shield-alert', connected: false },
                  { name: 'Sentry',          desc: 'Capture errors from the panel.', icon: 'bug', connected: false },
                ].map((iint) => (
                  <div key={iint.name} className="flex items-center gap-4 p-4">
                    <div className="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400"><Icon name={iint.icon} size={16} /></div>
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{iint.name}</div>
                      <div className="text-xs text-zinc-500 mt-0.5">{iint.desc}</div>
                    </div>
                    {iint.connected ? (
                      <div className="flex items-center gap-2">
                        <StatusPill status="online" />
                        <Button variant="secondary" size="sm">Configure</Button>
                      </div>
                    ) : (
                      <Button variant="primary" size="sm">Connect</Button>
                    )}
                  </div>
                ))}
              </div>
            </Card>
          )}

          {section === 'security' && (
            <Card>
              <CardHeader title="Security" subtitle="Authentication and session policy" icon="lock" />
              <div className="p-5 space-y-3">
                {[
                  ['Require 2FA for admins', 'All admins must set up TOTP before signing in.', false],
                  ['Steam OpenID', 'Allow admins to sign in with Steam.', true],
                  ['Lockout after failed attempts', 'Lock the account after 5 failed login attempts.', true],
                  ['Audit all admin actions', 'Write every admin action to the audit log.', true],
                ].map(([n,d,on]) => (
                  <div key={n} className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                    <div>
                      <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">{n}</div>
                      <div className="text-xs text-zinc-500 mt-0.5">{d}</div>
                    </div>
                    <Toggle defaultOn={on} />
                  </div>
                ))}
              </div>
            </Card>
          )}

          {section === 'maintenance' && (
            <Card>
              <CardHeader title="Maintenance" subtitle="Database, backups, danger zone" icon="wrench" />
              <div className="p-5 space-y-3">
                <div className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                  <div>
                    <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Database</div>
                    <div className="text-xs text-zinc-500 mt-0.5 font-mono">mariadb 10.11 · 4.2 GB · last backup 12h ago</div>
                  </div>
                  <Button variant="secondary" size="sm" icon="download">Backup now</Button>
                </div>
                <div className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
                  <div>
                    <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Cache</div>
                    <div className="text-xs text-zinc-500 mt-0.5">Smarty templates, server queries.</div>
                  </div>
                  <Button variant="secondary" size="sm" icon="trash-2">Clear</Button>
                </div>
                <div className="rounded-md border border-red-200 dark:border-red-950 bg-red-50/50 dark:bg-red-950/20 p-4 mt-4">
                  <div className="flex items-start gap-3">
                    <div className="text-red-600 dark:text-red-400 mt-0.5"><Icon name="triangle-alert" size={16} /></div>
                    <div className="flex-1">
                      <div className="text-sm font-semibold text-red-800 dark:text-red-300">Danger zone</div>
                      <div className="text-xs text-red-700/80 dark:text-red-400/80 mt-1">Wiping the panel deletes all bans, comms, admins, and audit history. This cannot be undone.</div>
                      <Button variant="danger" size="sm" className="mt-3" icon="trash-2">Wipe panel data</Button>
                    </div>
                  </div>
                </div>
              </div>
            </Card>
          )}

          <div className="flex justify-end gap-2">
            <Button variant="ghost">Discard</Button>
            <Button variant="primary" icon="save" onClick={() => toast.push({ title: 'Settings saved', kind: 'success' })}>Save</Button>
          </div>
        </div>
      </div>
    </div>
  );
}

function Toggle({ defaultOn }) {
  const [on, setOn] = useState(!!defaultOn);
  return (
    <button
      onClick={() => setOn(!on)}
      className={`relative w-9 h-5 rounded-full transition-colors ${on ? 'bg-brand-600' : 'bg-zinc-200 dark:bg-zinc-700'}`}
    >
      <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${on ? 'translate-x-4' : 'translate-x-0.5'}`} />
    </button>
  );
}

// -----------------------------------------------------------
// AUDIT LOG
// -----------------------------------------------------------
const AUDIT = (() => {
  const types = [
    { type: 'ban_added',     icon: 'ban',           color: 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40' },
    { type: 'ban_unbanned',  icon: 'check-circle',  color: 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'comm_added',    icon: 'mic-off',       color: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/40' },
    { type: 'admin_added',   icon: 'user-plus',     color: 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40' },
    { type: 'admin_removed', icon: 'user-x',        color: 'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800' },
    { type: 'group_changed', icon: 'shield-check',  color: 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-950/40' },
    { type: 'login',         icon: 'log-in',        color: 'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800' },
    { type: 'settings',      icon: 'settings',      color: 'text-zinc-600 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800' },
  ];
  const verbs = {
    ban_added: 'banned',
    ban_unbanned: 'unbanned',
    comm_added: 'muted',
    admin_added: 'invited',
    admin_removed: 'removed admin',
    group_changed: 'changed flags for group',
    login: 'signed in',
    settings: 'updated setting',
  };
  return Array.from({ length: 22 }).map((_, i) => {
    const t = types[i % types.length];
    const adm = ADMINS[i % ADMINS.length];
    return {
      id: 7000 + i,
      kind: t,
      admin: adm.name,
      target: ['xXx_360 N0SC0P3R_xXx','heavyaim 9000','toaster.exe','spysappinmysentry','idle.bot'][i % 5],
      verb: verbs[t.type],
      ip: `${10 + (i*7)%240}.${(i*13)%255}.${(i*29)%255}.${(i*5)%255}`,
      time: new Date(Date.now() - i * 17 * 60 * 1000).toISOString(),
      meta: t.type === 'settings' ? 'site_name' : t.type === 'group_changed' ? 'Mod' : t.type === 'login' ? 'via Steam' : '',
    };
  });
})();

function AuditLogView() {
  const [filter, setFilter] = useState('all');
  const filtered = AUDIT.filter(a => filter === 'all' || a.kind.type === filter);
  return (
    <div className="p-4 sm:p-6 max-w-[1400px]">
      <div className="flex items-end justify-between gap-4 flex-wrap mb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Audit log</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Every administrative action across the panel.</p>
        </div>
        <Button variant="secondary" icon="download">Export</Button>
      </div>

      <div className="flex items-center gap-2 mb-4 overflow-x-auto pb-1">
        <Chip active={filter==='all'} onClick={() => setFilter('all')} count={AUDIT.length}>All</Chip>
        <Chip active={filter==='ban_added'} onClick={() => setFilter('ban_added')} dot="bg-red-500">Bans</Chip>
        <Chip active={filter==='ban_unbanned'} onClick={() => setFilter('ban_unbanned')} dot="bg-emerald-500">Unbans</Chip>
        <Chip active={filter==='comm_added'} onClick={() => setFilter('comm_added')} dot="bg-amber-500">Comms</Chip>
        <Chip active={filter==='admin_added'} onClick={() => setFilter('admin_added')} dot="bg-blue-500">Admin changes</Chip>
        <Chip active={filter==='login'} onClick={() => setFilter('login')} dot="bg-zinc-400">Logins</Chip>
        <Chip active={filter==='settings'} onClick={() => setFilter('settings')} dot="bg-zinc-400">Settings</Chip>
      </div>

      <Card>
        <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
          {filtered.map((e) => (
            <div key={e.id} className="flex items-center gap-3 px-4 sm:px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 ${e.kind.color}`}>
                <Icon name={e.kind.icon} size={14} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-sm text-zinc-900 dark:text-zinc-100 truncate">
                  <span className="font-semibold">{e.admin}</span>
                  <span className="text-zinc-500"> {e.verb} </span>
                  {(e.kind.type !== 'login' && e.kind.type !== 'settings') && (
                    <span className="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{e.target}</span>
                  )}
                  {e.meta && <span className="text-zinc-500"> · {e.meta}</span>}
                </div>
                <div className="text-[11px] text-zinc-500 mt-0.5 font-mono">
                  {new Date(e.time).toLocaleString()} · from {e.ip}
                </div>
              </div>
              <Button variant="ghost" size="icon"><Icon name="external-link" size={13} /></Button>
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}

// -----------------------------------------------------------
// APPEALS
// -----------------------------------------------------------
const APPEALS = [
  { id: 1, status: 'open',     name: 'spysappinmysentry', steam: 'STEAM_0:1:50212881', reason: 'Cheating', body: "I was not cheating, my mouse settings just look that way. Please review the demo from May 2nd.", time: '2026-04-30T10:21:00Z', ban: BANS[2] },
  { id: 2, status: 'open',     name: 'heavyaim 9000',     steam: 'STEAM_0:0:88811023', reason: 'Toxicity', body: "I apologize for the comments. I was on tilt. Won't happen again.", time: '2026-04-29T20:18:00Z', ban: BANS[1] },
  { id: 3, status: 'replied',  name: 'toaster.exe',       steam: 'STEAM_0:1:23498765', reason: 'Mic spam', body: "Mic was open in the background, didn't realize. Push-to-talk now configured.", time: '2026-04-28T14:00:00Z', ban: BANS[3] },
  { id: 4, status: 'denied',   name: 'idle.bot',          steam: 'STEAM_0:0:13088991', reason: 'Idle vote evasion', body: "I was AFK for 2 minutes to grab water.", time: '2026-04-26T08:11:00Z', ban: BANS[7] },
  { id: 5, status: 'accepted', name: 'pyrobait',          steam: 'STEAM_0:1:71029443', reason: 'Trolling', body: "Misunderstanding with another admin. Cleared up in PMs.", time: '2026-04-22T19:42:00Z', ban: BANS[10] },
];

function AppealsView({ navigate }) {
  const [tab, setTab] = useState('open');
  const [picked, setPicked] = useState(APPEALS[0]);
  const filtered = APPEALS.filter(a => tab === 'all' || a.status === tab);
  const counts = {
    all: APPEALS.length,
    open: APPEALS.filter(a => a.status === 'open').length,
    replied: APPEALS.filter(a => a.status === 'replied').length,
    accepted: APPEALS.filter(a => a.status === 'accepted').length,
    denied: APPEALS.filter(a => a.status === 'denied').length,
  };
  const statusStyles = {
    open:     'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-500/30',
    replied:  'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-500/30',
    accepted: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-500/30',
    denied:   'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-500/30',
  };
  const toast = useToast();

  return (
    <div className="p-4 sm:p-6 max-w-[1400px]">
      <div className="flex items-end justify-between gap-4 flex-wrap mb-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Appeals</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Ban protests submitted by players.</p>
        </div>
      </div>

      <div className="flex items-center gap-2 mb-4 overflow-x-auto pb-1">
        <Chip active={tab==='open'}     onClick={() => setTab('open')}     count={counts.open}     dot="bg-amber-500">Open</Chip>
        <Chip active={tab==='replied'}  onClick={() => setTab('replied')}  count={counts.replied}  dot="bg-blue-500">Replied</Chip>
        <Chip active={tab==='accepted'} onClick={() => setTab('accepted')} count={counts.accepted} dot="bg-emerald-500">Accepted</Chip>
        <Chip active={tab==='denied'}   onClick={() => setTab('denied')}   count={counts.denied}   dot="bg-red-500">Denied</Chip>
        <Chip active={tab==='all'}      onClick={() => setTab('all')}      count={counts.all}>All</Chip>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-[380px_1fr] gap-4">
        {/* List */}
        <Card className="overflow-hidden">
          <div className="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[640px] overflow-y-auto">
            {filtered.length === 0 && (
              <div className="text-center py-12 text-sm text-zinc-500">No appeals here.</div>
            )}
            {filtered.map((a) => (
              <button
                key={a.id}
                onClick={() => setPicked(a)}
                className={`w-full flex items-start gap-3 p-3 text-left transition-colors ${
                  picked?.id === a.id ? 'bg-zinc-50 dark:bg-zinc-800/60' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/40'
                }`}
              >
                <Avatar name={a.name} size={32} />
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 min-w-0">
                    <div className="font-medium text-sm text-zinc-900 dark:text-zinc-100 truncate">{a.name}</div>
                    <span className={`inline-flex items-center px-2 h-4 rounded-full text-[10px] font-medium ring-1 ring-inset ${statusStyles[a.status]}`}>{a.status}</span>
                  </div>
                  <div className="text-xs text-zinc-500 mt-0.5 truncate">{a.reason}</div>
                  <div className="text-xs text-zinc-400 mt-1 line-clamp-2">{a.body}</div>
                  <div className="text-[10px] text-zinc-400 mt-1">{timeAgo(a.time)}</div>
                </div>
              </button>
            ))}
          </div>
        </Card>

        {/* Detail */}
        {picked ? (
          <Card>
            <CardHeader
              title={picked.name}
              subtitle={picked.steam}
              icon="megaphone"
              action={
                <span className={`inline-flex items-center px-2 h-5 rounded-full text-[11px] font-medium ring-1 ring-inset ${statusStyles[picked.status]}`}>{picked.status}</span>
              }
            />
            <div className="p-5 space-y-5">
              <div className="grid grid-cols-3 gap-3">
                <div><div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium">Original ban</div><div className="text-sm mt-1 text-zinc-900 dark:text-zinc-100">{picked.reason}</div></div>
                <div><div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium">Length</div><div className="text-sm mt-1 text-zinc-900 dark:text-zinc-100">{fmtDuration(picked.ban?.duration ?? 0)}</div></div>
                <div><div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium">Submitted</div><div className="text-sm mt-1 text-zinc-900 dark:text-zinc-100">{timeAgo(picked.time)}</div></div>
              </div>

              <div>
                <div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium mb-2">Player's statement</div>
                <Card className="p-4 bg-zinc-50 dark:bg-zinc-900/60 text-sm text-zinc-700 dark:text-zinc-300">{picked.body}</Card>
              </div>

              {picked.status === 'replied' && (
                <div>
                  <div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium mb-2">Admin response</div>
                  <Card className="p-4 bg-blue-50/50 dark:bg-blue-950/20 border-blue-200/60 dark:border-blue-900/40 text-sm text-zinc-700 dark:text-zinc-300">
                    Demo reviewed. Awaiting your reply re: input file logs from May 2nd.
                    <div className="text-xs text-zinc-500 mt-2">— arcadia · 2 days ago</div>
                  </Card>
                </div>
              )}

              <div>
                <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Reply</label>
                <Textarea rows={3} className="mt-1.5" placeholder="Write a response to the player…" />
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-zinc-200 dark:border-zinc-800">
                <Button variant="danger" icon="x" onClick={() => toast.push({ title: 'Appeal denied', kind: 'default' })}>Deny</Button>
                <Button variant="secondary" icon="message-square" onClick={() => toast.push({ title: 'Reply sent', kind: 'success' })}>Reply</Button>
                <Button variant="primary" icon="check" onClick={() => toast.push({ title: 'Appeal accepted — ban lifted', kind: 'success' })}>Accept & unban</Button>
              </div>
            </div>
          </Card>
        ) : <div />}
      </div>
    </div>
  );
}

// -----------------------------------------------------------
// YOUR ACCOUNT
// -----------------------------------------------------------
function AccountView() {
  const toast = useToast();
  return (
    <div className="p-4 sm:p-6 max-w-3xl space-y-4">
      <div className="mb-2">
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Your account</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Personal preferences and security.</p>
      </div>

      <Card>
        <div className="p-5 flex items-center gap-4">
          <Avatar name="arcadia" size={56} />
          <div className="flex-1">
            <div className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">arcadia</div>
            <div className="text-xs font-mono text-zinc-500">STEAM_0:1:23498765</div>
            <div className="mt-1.5 inline-flex items-center gap-1.5"><span className="px-2 h-5 rounded text-[11px] font-medium bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400 inline-flex items-center">Root admin</span></div>
          </div>
          <Button variant="secondary" icon="external-link">Steam profile</Button>
        </div>
      </Card>

      <Card>
        <CardHeader title="Profile" icon="user" />
        <div className="p-5 space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
            <div><label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Display name</label><Input className="mt-1.5" defaultValue="arcadia" /></div>
            <div><label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Email</label><Input type="email" className="mt-1.5" defaultValue="arcadia@skial.com" /></div>
          </div>
        </div>
      </Card>

      <Card>
        <CardHeader title="Security" icon="lock" />
        <div className="p-5 space-y-4">
          <div className="grid sm:grid-cols-2 gap-4">
            <div><label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Current password</label><Input type="password" className="mt-1.5" /></div>
            <div><label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">New password</label><Input type="password" className="mt-1.5" /></div>
          </div>
          <div className="flex items-center justify-between p-3 rounded-md border border-zinc-200 dark:border-zinc-800">
            <div>
              <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100">Two-factor authentication</div>
              <div className="text-xs text-zinc-500 mt-0.5">Authenticator app (TOTP). Currently <span className="text-emerald-600 dark:text-emerald-400 font-medium">enabled</span>.</div>
            </div>
            <Button variant="secondary" size="sm">Manage</Button>
          </div>
        </div>
      </Card>

      <Card>
        <CardHeader title="Active sessions" icon="laptop" />
        <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
          {[
            { device: 'Mac · Firefox', loc: 'Vancouver, CA', ip: '24.86.122.10', current: true,  time: 'now' },
            { device: 'iPhone · Safari', loc: 'Vancouver, CA', ip: '142.179.14.22', current: false, time: '4h ago' },
            { device: 'Windows · Chrome', loc: 'Seattle, US', ip: '73.252.14.91', current: false, time: '3d ago' },
          ].map((s, i) => (
            <div key={i} className="flex items-center gap-3 p-4">
              <div className="w-9 h-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-600 dark:text-zinc-400"><Icon name={s.device.includes('iPhone') ? 'smartphone' : 'laptop'} size={14} /></div>
              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
                  {s.device}
                  {s.current && <span className="text-[10px] uppercase tracking-wider px-1.5 h-4 rounded bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 inline-flex items-center font-medium">Current</span>}
                </div>
                <div className="text-xs text-zinc-500 mt-0.5">{s.loc} · <span className="font-mono">{s.ip}</span> · {s.time}</div>
              </div>
              {!s.current && <Button variant="ghost" size="sm" onClick={() => toast.push({ title: 'Session revoked' })}>Revoke</Button>}
            </div>
          ))}
        </div>
      </Card>
    </div>
  );
}

Object.assign(window, {
  ServersView, GroupsView, SettingsView, AuditLogView, AppealsView, AccountView, Toggle,
});
