// ============================================================
// SBPP 2026 — Screens
// ============================================================

// Mock data ---------------------------------------------------
const SERVERS = [
  { id: 1, name: 'Skial | Vanilla TF2',          host: 'tf2.skial.com:27015',     game: 'tf2',   players: 24, max: 24, online: true,  map: 'pl_badwater' },
  { id: 2, name: 'Skial | 2Fort 24/7',           host: 'tf2.skial.com:27025',     game: 'tf2',   players: 18, max: 32, online: true,  map: 'ctf_2fort' },
  { id: 3, name: 'Vortex | Casual',              host: 'cs2.vortex.gg:27015',     game: 'cs2',   players: 9,  max: 10, online: true,  map: 'de_mirage' },
  { id: 4, name: 'Vortex | Surf',                host: 'cs2.vortex.gg:27016',     game: 'cs2',   players: 0,  max: 32, online: false, map: '—' },
  { id: 5, name: 'GMod DarkRP — Mainline',       host: 'gmod.example.org:27015',  game: 'gmod',  players: 38, max: 64, online: true,  map: 'rp_downtown_v4c_v2' },
  { id: 6, name: 'L4D2 Versus',                  host: '141.92.10.14:27015',      game: 'l4d2',  players: 7,  max: 8,  online: true,  map: 'c1m1_hotel' },
];

const ADMINS = [
  { id: 1, name: 'arcadia',     steam: 'STEAM_0:1:23498', role: 'Root',   active: true },
  { id: 2, name: 'NotJaffa',    steam: 'STEAM_0:0:88811', role: 'Admin',  active: true },
  { id: 3, name: 'velvetsky',   steam: 'STEAM_0:1:71029', role: 'Admin',  active: true },
  { id: 4, name: 'mooncrash',   steam: 'STEAM_0:0:13088', role: 'Mod',    active: true },
  { id: 5, name: 'pyrobait',    steam: 'STEAM_0:1:50212', role: 'Mod',    active: false },
];

const REASONS = ['Cheating','Aimbot','Wallhack','Mic spam','Racism','Toxicity','Ghosting','Exploiting','Trolling','Idle vote evasion'];

function makeBans() {
  const players = [
    'xXx_360 N0SC0P3R_xXx','heavyaim 9000','toaster.exe','vsh_demoknight','sniper main','noclip enjoyer',
    'med_pls','ze0n','RailgunRobby','spysappinmysentry','SootheTheFootage','enginerd','crit-rocketed',
    'idle.bot','demoknight tf2','GabeTheCrate','BLU spy','market gardener','boomer pyro','goldensentry',
  ];
  const states = ['permanent','active','active','active','expired','expired','unbanned'];
  const out = [];
  for (let i = 0; i < 50; i++) {
    const state = states[i % states.length];
    out.push({
      id: 8000 + i,
      name: players[i % players.length],
      steam: `STEAM_0:${i % 2}:${(83410000 + i*1234) % 99999999}`,
      ip: `${10 + (i*7)%240}.${(i*13)%255}.${(i*29)%255}.${(i*5)%255}`,
      reason: REASONS[i % REASONS.length],
      duration: state === 'permanent' ? 0 : (state === 'active' ? [60, 1440, 10080, 43200][i % 4] : 1440),
      banned: new Date(Date.now() - (i+1) * 36e5 * (i % 7 + 1)).toISOString(),
      admin: ADMINS[i % ADMINS.length].name,
      server: SERVERS[i % SERVERS.length],
      state,
    });
  }
  return out;
}
const BANS = makeBans();

const COMMS = BANS.slice(0, 18).map((b, i) => ({
  ...b,
  id: 9000 + i,
  type: i % 2 === 0 ? 'mute' : 'gag',
}));

// ============================================================
// LOGIN
// ============================================================
function LoginScreen({ onLogin }) {
  return (
    <div className="min-h-screen grid lg:grid-cols-2 bg-zinc-50 dark:bg-zinc-950">
      {/* Left: form */}
      <div className="flex items-center justify-center p-6 sm:p-12">
        <div className="w-full max-w-sm">
          <div className="flex items-center gap-2.5 mb-10">
            <div className="w-9 h-9 rounded-lg bg-brand-600 flex items-center justify-center text-white font-bold">S</div>
            <div>
              <div className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">SourceBans++</div>
              <div className="text-xs text-zinc-500 dark:text-zinc-400">Admin panel</div>
            </div>
          </div>
          <h1 className="text-2xl font-semibold text-zinc-900 dark:text-zinc-100 tracking-tight">Sign in</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Use your admin credentials, or sign in with Steam.</p>

          <div className="mt-8 space-y-3">
            <Button variant="secondary" size="lg" className="w-full justify-center" onClick={onLogin}>
              <Icon name="gamepad-2" size={16} /> Continue with Steam
            </Button>
            <div className="relative my-5">
              <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-zinc-200 dark:border-zinc-800" /></div>
              <div className="relative flex justify-center"><span className="bg-zinc-50 dark:bg-zinc-950 px-2 text-[11px] uppercase tracking-wide text-zinc-400">Or with credentials</span></div>
            </div>
            <div>
              <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Username</label>
              <Input className="mt-1.5" placeholder="arcadia" defaultValue="arcadia" />
            </div>
            <div>
              <div className="flex items-center justify-between">
                <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Password</label>
                <a className="text-xs text-brand-600 hover:text-brand-700 dark:text-brand-400">Forgot?</a>
              </div>
              <Input type="password" className="mt-1.5" placeholder="••••••••" defaultValue="hunter2hunter2" />
            </div>
            <label className="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400 select-none">
              <input type="checkbox" defaultChecked className="rounded border-zinc-300 dark:border-zinc-700 text-brand-600 focus:ring-brand-500" />
              Remember me on this device
            </label>
            <Button variant="primary" size="lg" className="w-full justify-center mt-2" onClick={onLogin}>
              Sign in <Icon name="arrow-right" size={14} />
            </Button>
          </div>

          <div className="mt-10 text-xs text-zinc-400 dark:text-zinc-600">
            Need access? Ask a Root admin to invite your Steam ID.
          </div>
        </div>
      </div>

      {/* Right: marketing/info panel */}
      <div className="hidden lg:flex relative bg-zinc-900 dark:bg-zinc-900 text-zinc-100 p-12 items-end overflow-hidden">
        <div className="absolute inset-0 opacity-30" style={{
          backgroundImage: 'radial-gradient(circle at 20% 30%, #ea580c33 0, transparent 40%), radial-gradient(circle at 80% 70%, #5885a233 0, transparent 40%)',
        }} />
        <div className="absolute top-12 right-12 left-12 grid grid-cols-3 gap-3 opacity-90">
          {[{n:'Bans this week', v:'127'},{n:'Servers online', v:'5/6'},{n:'Active admins', v:'12'}].map((s, i) => (
            <div key={i} className="bg-white/5 backdrop-blur border border-white/10 rounded-lg p-3">
              <div className="text-[10px] uppercase tracking-wider text-zinc-400">{s.n}</div>
              <div className="text-2xl font-semibold mt-1 tabular-nums">{s.v}</div>
            </div>
          ))}
        </div>
        <div className="relative max-w-md">
          <div className="text-[10px] uppercase tracking-[0.2em] text-brand-400 font-semibold mb-3">Operator console</div>
          <h2 className="text-3xl font-semibold tracking-tight leading-tight">Cleaner servers,<br/>fewer headaches.</h2>
          <p className="text-sm text-zinc-400 mt-3">Ban management for Source-engine communities. Built by admins, for admins — now properly responsive, with dark mode and a command palette.</p>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// SIDEBAR
// ============================================================
function Sidebar({ route, setRoute, mobileOpen, setMobileOpen }) {
  const sections = [
    {
      label: 'Public',
      items: [
        { id: 'dashboard', icon: 'layout-dashboard', label: 'Dashboard' },
        { id: 'bans',      icon: 'ban',              label: 'Ban list',     count: 1284 },
        { id: 'comms',     icon: 'mic-off',          label: 'Comm blocks',  count: 312 },
        { id: 'submit',    icon: 'flag',             label: 'Submit a ban' },
        { id: 'protest',   icon: 'megaphone',        label: 'Appeals' },
        { id: 'servers',   icon: 'server',           label: 'Servers' },
      ],
    },
    {
      label: 'Admin',
      items: [
        { id: 'admin',     icon: 'shield',           label: 'Admin panel' },
        { id: 'addban',    icon: 'plus-circle',      label: 'Add ban' },
        { id: 'admins',    icon: 'users',            label: 'Admins' },
        { id: 'groups',    icon: 'shield-check',     label: 'Groups' },
        { id: 'settings',  icon: 'settings',         label: 'Settings' },
      ],
    },
  ];

  const content = (
    <>
      {/* Logo */}
      <div className="h-14 px-4 flex items-center gap-2.5 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
        <div className="w-7 h-7 rounded-md bg-brand-600 flex items-center justify-center text-white font-bold text-sm">S</div>
        <div className="min-w-0 leading-tight">
          <div className="text-sm font-semibold text-zinc-900 dark:text-zinc-100 truncate">SourceBans++</div>
          <div className="text-[10px] text-zinc-500 dark:text-zinc-500 truncate">skial.com</div>
        </div>
        <button onClick={() => setMobileOpen(false)} className="lg:hidden ml-auto text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 p-1">
          <Icon name="x" size={16} />
        </button>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-5">
        {sections.map((sec) => (
          <div key={sec.label}>
            <div className="px-3 mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-600">{sec.label}</div>
            <div className="space-y-0.5">
              {sec.items.map((item) => {
                const active = route === item.id;
                return (
                  <button
                    key={item.id + (active ? '-on' : '-off')}
                    data-active={active ? 'true' : 'false'}
                    onClick={() => { setRoute(item.id); setMobileOpen(false); }}
                    className="sidebar-link w-full flex items-center gap-2.5 px-3 h-8 rounded-md text-sm font-medium transition-colors"
                  >
                    <Icon name={item.icon} size={15} />
                    <span className="flex-1 text-left">{item.label}</span>
                    {item.count && (
                      <span className="sidebar-count text-[10px] tabular-nums px-1.5 h-4 inline-flex items-center rounded">{item.count.toLocaleString()}</span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        ))}
      </nav>

      {/* User card */}
      <div className="border-t border-zinc-200 dark:border-zinc-800 p-2 shrink-0">
        <button className="w-full flex items-center gap-2.5 p-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
          <Avatar name="arcadia" size={28} />
          <div className="flex-1 min-w-0 text-left">
            <div className="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">arcadia</div>
            <div className="text-[10px] text-zinc-500 dark:text-zinc-500 truncate">Root admin</div>
          </div>
          <Icon name="chevrons-up-down" size={14} className="text-zinc-400" />
        </button>
      </div>
    </>
  );

  return (
    <>
      {/* Desktop */}
      <aside className="hidden lg:flex flex-col w-60 shrink-0 bg-white dark:bg-zinc-950 border-r border-zinc-200 dark:border-zinc-800 h-screen sticky top-0">
        {content}
      </aside>

      {/* Mobile drawer */}
      {mobileOpen && (
        <div className="lg:hidden fixed inset-0 z-40">
          <div className="absolute inset-0 bg-zinc-950/50" onClick={() => setMobileOpen(false)} />
          <aside className="relative flex flex-col w-64 h-full bg-white dark:bg-zinc-950 border-r border-zinc-200 dark:border-zinc-800 animate-[slideInL_.2s_ease]">
            {content}
          </aside>
          <style>{`@keyframes slideInL { from { transform: translateX(-100%) } to { transform: translateX(0) } }`}</style>
        </div>
      )}
    </>
  );
}

// ============================================================
// TOPBAR
// ============================================================
function Topbar({ route, onMenu, onPalette, breadcrumbs }) {
  const { mode, setMode } = useTheme();
  const cycleMode = () => {
    setMode(mode === 'light' ? 'dark' : mode === 'dark' ? 'system' : 'light');
  };
  const modeIcon = mode === 'dark' ? 'moon' : mode === 'system' ? 'monitor' : 'sun';

  return (
    <header className="h-14 sticky top-0 z-30 bg-white/80 dark:bg-zinc-950/80 backdrop-blur border-b border-zinc-200 dark:border-zinc-800 flex items-center gap-2 px-3 sm:px-5">
      <button onClick={onMenu} className="lg:hidden p-2 -ml-2 text-zinc-600 dark:text-zinc-400">
        <Icon name="menu" size={18} />
      </button>

      <nav className="hidden sm:flex items-center gap-1.5 text-sm min-w-0">
        {breadcrumbs.map((b, i) => (
          <React.Fragment key={i}>
            {i > 0 && <Icon name="chevron-right" size={12} className="text-zinc-300 dark:text-zinc-700" />}
            <span className={`truncate ${i === breadcrumbs.length - 1 ? 'text-zinc-900 dark:text-zinc-100 font-medium' : 'text-zinc-500 dark:text-zinc-400'}`}>{b}</span>
          </React.Fragment>
        ))}
      </nav>

      <div className="flex-1" />

      <button
        onClick={onPalette}
        className="hidden md:flex items-center gap-2 h-9 px-3 rounded-md border border-zinc-200 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 text-sm text-zinc-500 dark:text-zinc-400 hover:bg-white dark:hover:bg-zinc-800 transition-colors min-w-[260px]"
      >
        <Icon name="search" size={14} />
        <span className="flex-1 text-left">Search players, SteamIDs, servers…</span>
        <kbd className="px-1.5 py-0.5 rounded bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-[10px] font-mono text-zinc-500">⌘K</kbd>
      </button>
      <button onClick={onPalette} className="md:hidden p-2 text-zinc-600 dark:text-zinc-400"><Icon name="search" size={18} /></button>

      <button
        onClick={cycleMode}
        title={`Theme: ${mode}`}
        className="p-2 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800"
      >
        <Icon name={modeIcon} size={16} />
      </button>
      <button className="p-2 text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 relative">
        <Icon name="bell" size={16} />
        <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-brand-500 ring-2 ring-white dark:ring-zinc-950" />
      </button>
    </header>
  );
}

// ============================================================
// COMMAND PALETTE
// ============================================================
function CommandPalette({ open, onClose, navigate }) {
  const [q, setQ] = useState('');
  useEffect(() => {
    if (!open) setQ('');
    if (!open) return;
    const onKey = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  const results = useMemo(() => {
    const ql = q.toLowerCase();
    const navItems = [
      { kind: 'nav', id: 'dashboard', icon: 'layout-dashboard', label: 'Dashboard' },
      { kind: 'nav', id: 'bans',      icon: 'ban', label: 'Ban list' },
      { kind: 'nav', id: 'comms',     icon: 'mic-off', label: 'Comm blocks' },
      { kind: 'nav', id: 'submit',    icon: 'flag', label: 'Submit a ban' },
      { kind: 'nav', id: 'admin',     icon: 'shield', label: 'Admin panel' },
      { kind: 'nav', id: 'addban',    icon: 'plus-circle', label: 'Add ban' },
      { kind: 'action', id: 'theme',  icon: 'moon', label: 'Toggle theme' },
      { kind: 'action', id: 'logout', icon: 'log-out', label: 'Sign out' },
    ];
    const players = BANS.slice(0, 12).filter(b =>
      !ql || b.name.toLowerCase().includes(ql) || b.steam.toLowerCase().includes(ql)
    ).slice(0, 5);
    const filteredNav = navItems.filter(n => !ql || n.label.toLowerCase().includes(ql));
    return { nav: filteredNav, players };
  }, [q]);

  if (!open) return null;
  return (
    <div className="fixed inset-0 z-[60] flex items-start justify-center pt-[10vh] px-4">
      <div className="absolute inset-0 bg-zinc-950/40 backdrop-blur-[2px] animate-[fadeIn_.15s]" onClick={onClose} />
      <div className="relative w-full max-w-xl bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-2xl overflow-hidden animate-[slideUp_.18s_ease]">
        <div className="flex items-center gap-3 px-4 h-12 border-b border-zinc-200 dark:border-zinc-800">
          <Icon name="search" size={16} className="text-zinc-400" />
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search players, SteamIDs, pages…"
            className="flex-1 bg-transparent outline-none text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400"
          />
          <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500 border border-zinc-200 dark:border-zinc-700">esc</kbd>
        </div>
        <div className="max-h-[420px] overflow-y-auto p-2">
          {results.nav.length > 0 && (
            <div className="mb-2">
              <div className="px-2 py-1 text-[10px] uppercase tracking-wider font-semibold text-zinc-400">Navigate</div>
              {results.nav.map((n) => (
                <button key={n.id} onClick={() => { navigate(n.id); onClose(); }} className="w-full flex items-center gap-3 px-2 h-9 rounded-md text-sm text-zinc-700 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                  <Icon name={n.icon} size={15} className="text-zinc-400" />
                  <span className="flex-1 text-left">{n.label}</span>
                  <Icon name="corner-down-left" size={13} className="text-zinc-400 opacity-0 group-hover:opacity-100" />
                </button>
              ))}
            </div>
          )}
          {results.players.length > 0 && (
            <div>
              <div className="px-2 py-1 text-[10px] uppercase tracking-wider font-semibold text-zinc-400">Players</div>
              {results.players.map((p) => (
                <button key={p.id} onClick={onClose} className="w-full flex items-center gap-3 px-2 h-10 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800">
                  <Avatar name={p.name} size={24} />
                  <div className="flex-1 min-w-0 text-left">
                    <div className="text-sm text-zinc-800 dark:text-zinc-200 truncate">{p.name}</div>
                    <div className="text-[10px] font-mono text-zinc-500 truncate">{p.steam}</div>
                  </div>
                  <StatusPill status={p.state} />
                </button>
              ))}
            </div>
          )}
        </div>
        <style>{`
          @keyframes slideUp { from { transform: translateY(6px); opacity: 0 } to { transform: translateY(0); opacity: 1 } }
        `}</style>
      </div>
    </div>
  );
}

// Export everything
Object.assign(window, {
  SERVERS, ADMINS, REASONS, BANS, COMMS,
  LoginScreen, Sidebar, Topbar, CommandPalette,
});
