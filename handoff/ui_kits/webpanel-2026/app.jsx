// ============================================================
// SBPP 2026 — App shell wiring
// ============================================================

function App() {
  const [route, setRoute] = useState('dashboard');
  const [mobileOpen, setMobileOpen] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [drawerBan, setDrawerBan] = useState(null);
  const [authed, setAuthed] = useState(true);

  // ⌘K
  useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen((o) => !o);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  if (!authed) {
    return <LoginScreen onLogin={() => setAuthed(true)} />;
  }

  const titles = {
    dashboard: ['Dashboard'],
    bans:      ['Public', 'Ban list'],
    comms:     ['Public', 'Comm blocks'],
    submit:    ['Public', 'Submit a ban'],
    protest:   ['Public', 'Appeals'],
    servers:   ['Public', 'Servers'],
    admin:     ['Admin'],
    addban:    ['Admin', 'Add ban'],
    admins:    ['Admin', 'Admins'],
    groups:    ['Admin', 'Groups'],
    settings:  ['Admin', 'Settings'],
    logs:      ['Admin', 'Audit log'],
    account:   ['Your account'],
  };

  const view = (() => {
    switch (route) {
      case 'dashboard': return <DashboardView navigate={setRoute} onPlayer={setDrawerBan} />;
      case 'bans':      return <BanListView onPlayer={setDrawerBan} navigate={setRoute} />;
      case 'comms':     return <CommsView onPlayer={setDrawerBan} />;
      case 'submit':    return <SubmitView />;
      case 'admin':     return <AdminPanelView navigate={setRoute} />;
      case 'addban':    return <AddBanView navigate={setRoute} />;
      case 'admins':    return <AdminPanelView navigate={setRoute} />;
      case 'servers':   return <ServersView navigate={setRoute} />;
      case 'groups':    return <GroupsView />;
      case 'settings':  return <SettingsView />;
      case 'logs':      return <AuditLogView />;
      case 'protest':   return <AppealsView navigate={setRoute} />;
      case 'account':   return <AccountView />;
      default:          return <PlaceholderView title={titles[route]?.[titles[route].length-1] || route} />;
    }
  })();

  return (
    <div className="min-h-screen flex bg-zinc-50 dark:bg-zinc-950">
      <Sidebar route={route} setRoute={setRoute} mobileOpen={mobileOpen} setMobileOpen={setMobileOpen} />
      <div className="flex-1 min-w-0 flex flex-col">
        <Topbar
          route={route}
          breadcrumbs={titles[route] || [route]}
          onMenu={() => setMobileOpen(true)}
          onPalette={() => setPaletteOpen(true)}
        />
        <main className="flex-1 min-w-0">{view}</main>
      </div>

      <PlayerDrawer ban={drawerBan} onClose={() => setDrawerBan(null)} />
      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} navigate={setRoute} />

      {/* Floating sign-out (demo only) */}
      <button
        onClick={() => setAuthed(false)}
        className="fixed bottom-3 left-3 text-[10px] text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 px-2 py-1 rounded bg-white/80 dark:bg-zinc-900/80 border border-zinc-200 dark:border-zinc-800 backdrop-blur z-40"
        title="Demo: jump to login"
      >
        ↩ Login screen
      </button>
    </div>
  );
}

// -----------------------------------------------------------
// Add Ban (admin form)
// -----------------------------------------------------------
function AddBanView({ navigate }) {
  const toast = useToast();
  return (
    <div className="p-4 sm:p-6 max-w-2xl">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Add ban</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Manually ban a SteamID or IP address.</p>
      </div>
      <Card className="p-5 sm:p-6 space-y-5">
        <div>
          <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">SteamID or IP <span className="text-red-500">*</span></label>
          <Input icon="user" className="mt-1.5" placeholder="STEAM_0:1:23498765 or 192.168.1.1" />
        </div>
        <div>
          <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Player name (optional)</label>
          <Input className="mt-1.5" placeholder="Last known in-game name" />
        </div>
        <div className="grid sm:grid-cols-2 gap-4">
          <div>
            <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Reason <span className="text-red-500">*</span></label>
            <Select className="mt-1.5">
              {REASONS.map(r => <option key={r}>{r}</option>)}
            </Select>
          </div>
          <div>
            <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Length <span className="text-red-500">*</span></label>
            <Select className="mt-1.5" defaultValue="1440">
              <option value="60">1 hour</option>
              <option value="1440">1 day</option>
              <option value="10080">1 week</option>
              <option value="43200">1 month</option>
              <option value="0">Permanent</option>
            </Select>
          </div>
        </div>
        <div>
          <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Admin notes (optional)</label>
          <Textarea rows={3} className="mt-1.5" placeholder="Visible to other admins only" />
        </div>
        <div className="flex justify-end gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
          <Button variant="ghost" onClick={() => navigate('admin')}>Cancel</Button>
          <Button variant="primary" icon="ban" onClick={() => { toast.push({ title: 'Ban added', body: 'Player has been banned.', kind: 'success' }); navigate('bans'); }}>Add ban</Button>
        </div>
      </Card>
    </div>
  );
}

function PlaceholderView({ title }) {
  return (
    <div className="p-6 max-w-3xl">
      <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 capitalize">{title}</h1>
      <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">This view is stubbed in the demo. The shell, drawer, palette, and core flows live on the other tabs.</p>
      <Card className="p-8 mt-6 text-center text-sm text-zinc-500">
        <Icon name="construction" size={20} className="text-zinc-400 mx-auto mb-2" />
        Coming soon
      </Card>
    </div>
  );
}

// Mount
ReactDOM.createRoot(document.getElementById('root')).render(
  <ThemeProvider>
    <ToastProvider>
      <App />
    </ToastProvider>
  </ThemeProvider>
);
