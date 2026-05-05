// ============================================================
// SBPP 2026 — Views + App shell
// ============================================================

// -----------------------------------------------------------
// DASHBOARD
// -----------------------------------------------------------
function StatCard({ label, value, delta, icon, hint }) {
  const positive = delta && delta.startsWith('+');
  return (
    <Card className="p-5">
      <div className="flex items-start justify-between">
        <div className="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">{label}</div>
        <div className="text-zinc-400"><Icon name={icon} size={16} /></div>
      </div>
      <div className="mt-2 flex items-baseline gap-2">
        <div className="text-3xl font-semibold tracking-tight tabular-nums text-zinc-900 dark:text-zinc-100">{value}</div>
        {delta && (
          <span className={`text-xs font-medium ${positive ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>{delta}</span>
        )}
      </div>
      {hint && <div className="text-xs text-zinc-500 dark:text-zinc-500 mt-1">{hint}</div>}
    </Card>
  );
}

function Sparkline({ data, color = '#ea580c' }) {
  const max = Math.max(...data, 1);
  const w = 220, h = 56;
  const step = w / (data.length - 1);
  const points = data.map((v, i) => `${i*step},${h - (v/max)*h*0.9 - 2}`).join(' ');
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-14">
      <defs>
        <linearGradient id="spark" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.25" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon fill="url(#spark)" points={`0,${h} ${points} ${w},${h}`} />
      <polyline fill="none" stroke={color} strokeWidth="1.5" points={points} />
    </svg>
  );
}

function DashboardView({ navigate, onPlayer }) {
  const recent = BANS.slice(0, 8);
  const sparkData = [3,5,4,7,6,9,8,11,9,12,10,14,13,11,15,17,14,16,19,18,16,20,22,19];
  return (
    <div className="p-4 sm:p-6 space-y-6 max-w-[1400px]">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Dashboard</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Activity across your servers, last 7 days.</p>
      </div>

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <StatCard label="Total bans" value="1,284" delta="+127" icon="ban" hint="vs last week" />
        <StatCard label="Active bans" value="412" delta="+23" icon="user-x" hint="vs last week" />
        <StatCard label="Comm blocks" value="312" delta="−4" icon="mic-off" hint="vs last week" />
        <StatCard label="Servers online" value="5/6" icon="server" hint="Vortex Surf offline" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Card className="lg:col-span-2">
          <CardHeader title="Bans over time" subtitle="Last 24 days" icon="trending-up"
            action={<Select className="!h-8 !text-xs"><option>Last 24 days</option><option>Last 7 days</option><option>Last 90 days</option></Select>}
          />
          <div className="p-5">
            <div className="flex items-baseline gap-2 mb-2">
              <div className="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">1,284</div>
              <div className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">+11.0%</div>
            </div>
            <Sparkline data={sparkData} />
            <div className="flex justify-between text-[10px] text-zinc-400 mt-1 tabular-nums">
              <span>Apr 8</span><span>Apr 14</span><span>Apr 20</span><span>Apr 26</span><span>May 2</span>
            </div>
          </div>
        </Card>

        <Card>
          <CardHeader title="Team coverage" subtitle="Admins active in the last 24h" icon="users" />
          <div className="p-5 space-y-4">
            <div className="flex items-baseline gap-2">
              <div className="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{ADMINS.slice(0,5).length}<span className="text-zinc-300 dark:text-zinc-700 font-normal"> / {ADMINS.length}</span></div>
              <div className="text-xs text-zinc-500 dark:text-zinc-400">on duty</div>
            </div>

            <div>
              <div className="flex items-center justify-between text-[11px] text-zinc-500 mb-1.5">
                <span>Avg. report response</span>
                <span className="tabular-nums text-zinc-700 dark:text-zinc-300 font-medium">4m 12s</span>
              </div>
              <div className="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                <div className="h-full bg-emerald-500/80 dark:bg-emerald-400/80" style={{ width: '72%' }} />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between text-[11px] text-zinc-500 mb-1.5">
                <span>Hours covered today</span>
                <span className="tabular-nums text-zinc-700 dark:text-zinc-300 font-medium">21 / 24</span>
              </div>
              <div className="grid gap-[2px]" style={{ gridTemplateColumns: 'repeat(24, minmax(0, 1fr))' }}>
                {Array.from({ length: 24 }).map((_, h) => {
                  const covered = h < 21 && h !== 4 && h !== 5;
                  return (
                    <div key={h} className={`h-4 rounded-[2px] ${covered ? 'bg-zinc-300 dark:bg-zinc-600' : 'bg-zinc-100 dark:bg-zinc-800'}`} title={`${h}:00`} />
                  );
                })}
              </div>
              <div className="flex justify-between text-[10px] text-zinc-400 mt-1 tabular-nums">
                <span>00</span><span>06</span><span>12</span><span>18</span><span>24</span>
              </div>
            </div>

            <div className="flex -space-x-1.5 pt-1">
              {ADMINS.slice(0,5).map((a) => (
                <div key={a.id} title={`${a.name} · ${a.role}`} className="ring-2 ring-white dark:ring-zinc-900 rounded-full">
                  <Avatar name={a.name} size={24} />
                </div>
              ))}
              <div className="ring-2 ring-white dark:ring-zinc-900 rounded-full bg-zinc-100 dark:bg-zinc-800 h-6 w-6 flex items-center justify-center text-[10px] text-zinc-500 tabular-nums">+{Math.max(0, ADMINS.length - 5)}</div>
            </div>
          </div>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <Card className="lg:col-span-2">
          <CardHeader
            title="Recent bans"
            subtitle="Latest 8 enforcement actions"
            icon="clock"
            action={<Button variant="ghost" size="sm" onClick={() => navigate('bans')}>View all <Icon name="arrow-right" size={13} /></Button>}
          />
          <div className="divide-y divide-zinc-100 dark:divide-zinc-800">
            {recent.map((b) => (
              <button
                key={b.id}
                onClick={() => onPlayer(b)}
                className={`w-full flex items-center gap-3 px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/40 border-l-[3px] ${stateAccent[b.state]} text-left`}
              >
                <Avatar name={b.name} size={32} />
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{b.name}</span>
                    <StatusPill status={b.state} />
                  </div>
                  <div className="text-xs text-zinc-500 dark:text-zinc-400 truncate mt-0.5">
                    {b.reason} <span className="text-zinc-300 dark:text-zinc-700">·</span> {b.server.name}
                  </div>
                </div>
                <div className="hidden sm:block text-xs text-zinc-500 text-right shrink-0">
                  <div>{fmtDuration(b.duration)}</div>
                  <div className="text-[10px] text-zinc-400 mt-0.5">{timeAgo(b.banned)}</div>
                </div>
              </button>
            ))}
          </div>
        </Card>

        <Card>
          <CardHeader title="Servers" subtitle="Live status" icon="server" />
          <div className="p-2">
            {SERVERS.map((s) => (
              <div key={s.id} className="flex items-center gap-3 px-3 py-2.5 rounded-md hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                <GameBadge game={s.game} size={22} />
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">{s.name}</div>
                  <div className="text-[10px] font-mono text-zinc-500 truncate">{s.host}</div>
                </div>
                <div className="text-right shrink-0">
                  <StatusPill status={s.online ? 'online' : 'offline'} />
                  {s.online && <div className="text-[10px] text-zinc-500 mt-0.5 tabular-nums">{s.players}/{s.max}</div>}
                </div>
              </div>
            ))}
          </div>
        </Card>
      </div>
    </div>
  );
}

// -----------------------------------------------------------
// BAN LIST  (the marquee view)
// -----------------------------------------------------------
function BanListView({ onPlayer, navigate }) {
  const [search, setSearch] = useState('');
  const [stateFilter, setStateFilter] = useState('all');
  const [serverFilter, setServerFilter] = useState('all');
  const [loading, setLoading] = useState(true);
  const toast = useToast();

  useEffect(() => {
    const t = setTimeout(() => setLoading(false), 800);
    return () => clearTimeout(t);
  }, []);

  const filtered = useMemo(() => {
    return BANS.filter((b) => {
      if (stateFilter !== 'all' && b.state !== stateFilter) return false;
      if (serverFilter !== 'all' && b.server.id !== Number(serverFilter)) return false;
      if (search) {
        const q = search.toLowerCase();
        if (!b.name.toLowerCase().includes(q) && !b.steam.toLowerCase().includes(q) && !b.ip.includes(q)) return false;
      }
      return true;
    });
  }, [search, stateFilter, serverFilter]);

  const stateCounts = useMemo(() => ({
    all: BANS.length,
    permanent: BANS.filter(b => b.state === 'permanent').length,
    active: BANS.filter(b => b.state === 'active').length,
    expired: BANS.filter(b => b.state === 'expired').length,
    unbanned: BANS.filter(b => b.state === 'unbanned').length,
  }), []);

  return (
    <div className="p-4 sm:p-6 space-y-4 max-w-[1400px]">
      <div className="flex items-end justify-between gap-4 flex-wrap">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Ban list</h1>
          <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{filtered.length.toLocaleString()} of {BANS.length.toLocaleString()} bans</p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" icon="download" size="md">Export CSV</Button>
          <Button variant="primary" icon="plus" size="md" onClick={() => navigate('addban')}>Add ban</Button>
        </div>
      </div>

      {/* Sticky filter bar */}
      <div className="sticky top-14 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-zinc-50/95 dark:bg-zinc-950/95 backdrop-blur border-b border-zinc-200 dark:border-zinc-800">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1 max-w-md">
            <Input
              icon="search"
              placeholder="Player, SteamID, or IP…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <Select value={serverFilter} onChange={(e) => setServerFilter(e.target.value)} className="!w-auto sm:min-w-[180px]">
              <option value="all">All servers</option>
              {SERVERS.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>
            <Select className="!w-auto">
              <option>All time</option>
              <option>Today</option>
              <option>Last 7 days</option>
              <option>Last 30 days</option>
            </Select>
          </div>
        </div>

        <div className="flex items-center gap-2 mt-3 overflow-x-auto pb-1 -mb-1">
          <Chip active={stateFilter === 'all'}       onClick={() => setStateFilter('all')}       count={stateCounts.all}>All</Chip>
          <Chip active={stateFilter === 'permanent'} onClick={() => setStateFilter('permanent')} count={stateCounts.permanent} dot="bg-red-500">Permanent</Chip>
          <Chip active={stateFilter === 'active'}    onClick={() => setStateFilter('active')}    count={stateCounts.active}    dot="bg-amber-500">Active</Chip>
          <Chip active={stateFilter === 'expired'}   onClick={() => setStateFilter('expired')}   count={stateCounts.expired}   dot="bg-zinc-400">Expired</Chip>
          <Chip active={stateFilter === 'unbanned'}  onClick={() => setStateFilter('unbanned')}  count={stateCounts.unbanned}  dot="bg-emerald-500">Unbanned</Chip>
          {(stateFilter !== 'all' || serverFilter !== 'all' || search) && (
            <button onClick={() => { setStateFilter('all'); setServerFilter('all'); setSearch(''); }} className="text-xs text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-200 ml-2">Clear</button>
          )}
        </div>
      </div>

      {/* Table (desktop) / Card list (mobile) */}
      <Card className="overflow-hidden">
        {/* Desktop table */}
        <div className="hidden md:block">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-900/40 border-b border-zinc-200 dark:border-zinc-800">
                <th className="text-left font-semibold px-5 py-2.5">Player</th>
                <th className="text-left font-semibold px-3 py-2.5 hidden lg:table-cell">SteamID</th>
                <th className="text-left font-semibold px-3 py-2.5">Reason</th>
                <th className="text-left font-semibold px-3 py-2.5 hidden xl:table-cell">Server</th>
                <th className="text-left font-semibold px-3 py-2.5">Length</th>
                <th className="text-left font-semibold px-3 py-2.5 hidden lg:table-cell">Banned</th>
                <th className="text-left font-semibold px-3 py-2.5">Status</th>
                <th className="px-5 py-2.5"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
              {loading ? (
                Array.from({length: 6}).map((_, i) => (
                  <tr key={i}>
                    <td className="px-5 py-3"><div className="flex items-center gap-3"><Skel className="w-7 h-7 rounded-full" /><Skel className="h-3 w-32" /></div></td>
                    <td className="px-3 py-3 hidden lg:table-cell"><Skel className="h-3 w-32" /></td>
                    <td className="px-3 py-3"><Skel className="h-3 w-20" /></td>
                    <td className="px-3 py-3 hidden xl:table-cell"><Skel className="h-3 w-28" /></td>
                    <td className="px-3 py-3"><Skel className="h-3 w-12" /></td>
                    <td className="px-3 py-3 hidden lg:table-cell"><Skel className="h-3 w-16" /></td>
                    <td className="px-3 py-3"><Skel className="h-5 w-16 rounded-full" /></td>
                    <td className="px-5 py-3"></td>
                  </tr>
                ))
              ) : filtered.map((b) => (
                <tr
                  key={b.id}
                  onClick={() => onPlayer(b)}
                  className={`group cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/40 border-l-[3px] ${stateAccent[b.state]} transition-colors`}
                >
                  <td className="px-5 py-3">
                    <div className="flex items-center gap-3 min-w-0">
                      <Avatar name={b.name} size={28} />
                      <span className="font-medium text-zinc-900 dark:text-zinc-100 truncate">{b.name}</span>
                    </div>
                  </td>
                  <td className="px-3 py-3 hidden lg:table-cell font-mono text-xs text-zinc-500">{b.steam}</td>
                  <td className="px-3 py-3 text-zinc-600 dark:text-zinc-400">{b.reason}</td>
                  <td className="px-3 py-3 hidden xl:table-cell">
                    <div className="flex items-center gap-2 min-w-0">
                      <GameBadge game={b.server.game} size={16} />
                      <span className="truncate text-zinc-600 dark:text-zinc-400">{b.server.name}</span>
                    </div>
                  </td>
                  <td className="px-3 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">{fmtDuration(b.duration)}</td>
                  <td className="px-3 py-3 hidden lg:table-cell text-zinc-500 text-xs">{timeAgo(b.banned)}</td>
                  <td className="px-3 py-3"><StatusPill status={b.state} /></td>
                  <td className="px-5 py-3">
                    <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button
                        onClick={(e) => { e.stopPropagation(); toast.push({ title: 'Ban edited', body: `${b.name}'s ban duration updated.`, kind: 'success' }); }}
                        className="p-1.5 rounded text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        title="Edit"
                      >
                        <Icon name="pencil" size={13} />
                      </button>
                      {b.state !== 'unbanned' && (
                        <button
                          onClick={(e) => { e.stopPropagation(); toast.push({ title: 'Ban lifted', body: `${b.name} has been unbanned.`, kind: 'success' }); }}
                          className="p-1.5 rounded text-zinc-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-950/40"
                          title="Unban"
                        >
                          <Icon name="check" size={13} />
                        </button>
                      )}
                      <button
                        onClick={(e) => { e.stopPropagation(); toast.push({ title: 'Copied SteamID', kind: 'default' }); }}
                        className="p-1.5 rounded text-zinc-500 hover:text-zinc-900 dark:hover:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                        title="Copy SteamID"
                      >
                        <Icon name="copy" size={13} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {!loading && filtered.length === 0 && (
            <div className="py-16 text-center">
              <div className="inline-flex w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 items-center justify-center text-zinc-400 mb-3">
                <Icon name="search-x" size={20} />
              </div>
              <div className="text-sm font-medium text-zinc-700 dark:text-zinc-200">No bans match those filters</div>
              <div className="text-xs text-zinc-500 mt-1">Try clearing them, or add a new ban.</div>
            </div>
          )}
        </div>

        {/* Mobile cards */}
        <div className="md:hidden divide-y divide-zinc-100 dark:divide-zinc-800">
          {loading ? (
            Array.from({length: 4}).map((_, i) => (
              <div key={i} className="p-4 flex items-center gap-3">
                <Skel className="w-10 h-10 rounded-full" />
                <div className="flex-1 space-y-2"><Skel className="h-3 w-1/2" /><Skel className="h-2 w-3/4" /></div>
              </div>
            ))
          ) : filtered.map((b) => (
            <button
              key={b.id}
              onClick={() => onPlayer(b)}
              className={`w-full text-left flex items-center gap-3 p-3 border-l-[3px] ${stateAccent[b.state]}`}
            >
              <Avatar name={b.name} size={36} />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 min-w-0">
                  <div className="font-medium text-zinc-900 dark:text-zinc-100 truncate text-sm">{b.name}</div>
                  <StatusPill status={b.state} />
                </div>
                <div className="text-xs text-zinc-500 mt-0.5 truncate">{b.reason} · {fmtDuration(b.duration)}</div>
                <div className="text-[10px] font-mono text-zinc-400 mt-0.5 truncate">{b.steam}</div>
              </div>
              <Icon name="chevron-right" size={14} className="text-zinc-400" />
            </button>
          ))}
        </div>
      </Card>

      {/* Pagination */}
      {!loading && filtered.length > 0 && (
        <div className="flex items-center justify-between text-xs text-zinc-500">
          <div>Showing <span className="text-zinc-700 dark:text-zinc-300 font-medium">1–{Math.min(50, filtered.length)}</span> of <span className="text-zinc-700 dark:text-zinc-300 font-medium">{filtered.length}</span></div>
          <div className="flex items-center gap-1">
            <Button variant="outline" size="sm" disabled><Icon name="chevron-left" size={13} /> Prev</Button>
            <Button variant="outline" size="sm">Next <Icon name="chevron-right" size={13} /></Button>
          </div>
        </div>
      )}
    </div>
  );
}

// -----------------------------------------------------------
// PLAYER DRAWER
// -----------------------------------------------------------
function PlayerDrawer({ ban, onClose }) {
  const toast = useToast();
  if (!ban) return null;
  const tabs = ['Overview','History','Comms','Notes'];
  const [tab, setTab] = useState('Overview');
  return (
    <Drawer open={!!ban} onClose={onClose}>
      {/* Header */}
      <div className="flex items-center justify-between px-5 h-14 border-b border-zinc-200 dark:border-zinc-800 shrink-0">
        <div className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Player details</div>
        <div className="flex items-center gap-1">
          <Button variant="ghost" size="icon"><Icon name="external-link" size={14} /></Button>
          <Button variant="ghost" size="icon" onClick={onClose}><Icon name="x" size={16} /></Button>
        </div>
      </div>

      {/* Body */}
      <div className="flex-1 overflow-y-auto">
        <div className="p-5">
          <div className="flex items-start gap-4">
            <Avatar name={ban.name} size={56} />
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2 flex-wrap">
                <h2 className="text-xl font-semibold text-zinc-900 dark:text-zinc-100 truncate">{ban.name}</h2>
                <StatusPill status={ban.state} />
              </div>
              <div className="text-xs font-mono text-zinc-500 mt-1">{ban.steam}</div>
              <div className="text-xs font-mono text-zinc-400">{ban.ip}</div>
            </div>
          </div>

          <div className="grid grid-cols-3 gap-2 mt-5">
            <Button variant="secondary" icon="external-link" size="sm">Steam profile</Button>
            <Button variant="secondary" icon="copy" size="sm" onClick={() => toast.push({ title: 'SteamID copied' })}>Copy ID</Button>
            {ban.state !== 'unbanned' ? (
              <Button variant="primary" icon="check" size="sm" onClick={() => { toast.push({ title: 'Ban lifted', kind: 'success' }); onClose(); }}>Unban</Button>
            ) : (
              <Button variant="danger" icon="ban" size="sm">Re-ban</Button>
            )}
          </div>
        </div>

        {/* Tabs */}
        <div className="border-b border-zinc-200 dark:border-zinc-800 px-5">
          <div className="flex gap-4">
            {tabs.map((t) => (
              <button
                key={t}
                onClick={() => setTab(t)}
                className={`h-10 text-sm font-medium border-b-2 transition-colors ${tab === t ? 'border-brand-600 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'}`}
              >{t}</button>
            ))}
          </div>
        </div>

        {tab === 'Overview' && (
          <div className="p-5 space-y-5">
            <div className="grid grid-cols-2 gap-3">
              {[
                ['Reason', ban.reason],
                ['Length', fmtDuration(ban.duration)],
                ['Server', ban.server.name],
                ['Banned by', ban.admin],
                ['Banned at', new Date(ban.banned).toLocaleString()],
                ['Ban ID', `#${ban.id}`],
              ].map(([k,v]) => (
                <div key={k}>
                  <div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium">{k}</div>
                  <div className="text-sm text-zinc-900 dark:text-zinc-100 mt-1">{v}</div>
                </div>
              ))}
            </div>

            <div>
              <div className="text-[10px] uppercase tracking-wider text-zinc-400 font-medium mb-2">Admin notes</div>
              <Card className="p-3 text-xs text-zinc-600 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-900/60">
                Caught with /sm_ws spinning during scrim warmup. Demo posted to #moderation. Permanent unless they appeal with sufficient evidence of innocence.
              </Card>
            </div>
          </div>
        )}

        {tab === 'History' && (
          <div className="p-5 space-y-3">
            {[
              { type: 'ban', text: 'Banned · Cheating', time: ban.banned, admin: ban.admin },
              { type: 'comm', text: 'Muted · Mic spam (1d)', time: '2025-03-14T18:22:00Z', admin: 'mooncrash' },
              { type: 'kick', text: 'Kicked · AFK', time: '2025-02-09T11:05:00Z', admin: 'velvetsky' },
              { type: 'ban', text: 'Banned · Toxicity (7d) — expired', time: '2024-11-30T20:00:00Z', admin: 'NotJaffa' },
            ].map((e, i) => (
              <div key={i} className="flex gap-3">
                <div className={`w-7 h-7 rounded-full shrink-0 flex items-center justify-center ${
                  e.type === 'ban' ? 'bg-red-50 text-red-600 dark:bg-red-950/50 dark:text-red-400' :
                  e.type === 'comm' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400' :
                  'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'
                }`}>
                  <Icon name={e.type === 'ban' ? 'ban' : e.type === 'comm' ? 'mic-off' : 'log-out'} size={13} />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-sm text-zinc-900 dark:text-zinc-100">{e.text}</div>
                  <div className="text-xs text-zinc-500 mt-0.5">{e.admin} · {timeAgo(e.time)}</div>
                </div>
              </div>
            ))}
          </div>
        )}

        {tab === 'Comms' && (
          <div className="p-5 text-sm text-zinc-500 dark:text-zinc-400">
            <div className="text-center py-12">
              <div className="inline-flex w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 items-center justify-center text-zinc-400 mb-3">
                <Icon name="mic-off" size={16} />
              </div>
              <div className="text-zinc-700 dark:text-zinc-300">No active communication blocks</div>
            </div>
          </div>
        )}

        {tab === 'Notes' && (
          <div className="p-5 space-y-3">
            <Textarea rows={4} placeholder="Add an admin note (visible to other admins only)…" />
            <div className="flex justify-end"><Button variant="primary" size="sm" icon="send-horizontal">Post note</Button></div>
          </div>
        )}
      </div>
    </Drawer>
  );
}

// -----------------------------------------------------------
// SUBMIT A BAN  (public form)
// -----------------------------------------------------------
function SubmitView() {
  const toast = useToast();
  const [submitted, setSubmitted] = useState(false);
  return (
    <div className="p-4 sm:p-6 max-w-3xl">
      <div className="mb-6">
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Submit a ban request</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Report a player for cheating, harassment, or other violations. An admin will review your submission.</p>
      </div>

      {submitted ? (
        <Card className="p-8 text-center">
          <div className="inline-flex w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-950/40 items-center justify-center text-emerald-600 dark:text-emerald-400 mb-4">
            <Icon name="check" size={20} />
          </div>
          <div className="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Submission received</div>
          <div className="text-sm text-zinc-500 dark:text-zinc-400 mt-2 max-w-md mx-auto">Thanks. Your report has been queued for admin review. You'll get a Steam DM if we need more info.</div>
          <Button variant="secondary" className="mt-6" onClick={() => setSubmitted(false)}>Submit another</Button>
        </Card>
      ) : (
        <Card className="p-5 sm:p-6 space-y-5">
          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Your Steam name</label>
              <Input className="mt-1.5" placeholder="Your in-game name" />
            </div>
            <div>
              <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Your email</label>
              <Input className="mt-1.5" type="email" placeholder="you@example.com" />
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Offender's SteamID or profile URL <span className="text-red-500">*</span></label>
            <Input icon="user" className="mt-1.5" placeholder="STEAM_0:1:23498765 or https://steamcommunity.com/id/…" />
            <div className="text-[11px] text-zinc-500 mt-1">We'll verify this against our records.</div>
          </div>

          <div className="grid sm:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Server <span className="text-red-500">*</span></label>
              <Select className="mt-1.5">
                <option>Select a server…</option>
                {SERVERS.map(s => <option key={s.id}>{s.name}</option>)}
              </Select>
            </div>
            <div>
              <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Reason <span className="text-red-500">*</span></label>
              <Select className="mt-1.5">
                <option>Select a reason…</option>
                {REASONS.map(r => <option key={r}>{r}</option>)}
              </Select>
            </div>
          </div>

          <div>
            <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">What happened? <span className="text-red-500">*</span></label>
            <Textarea rows={5} className="mt-1.5" placeholder="Describe what happened, when, and any context an admin would need…" />
          </div>

          <div>
            <label className="text-xs font-medium text-zinc-700 dark:text-zinc-300">Evidence</label>
            <div className="mt-1.5 border-2 border-dashed border-zinc-200 dark:border-zinc-800 rounded-md p-6 text-center hover:border-brand-300 dark:hover:border-brand-800 transition-colors cursor-pointer">
              <Icon name="upload-cloud" size={20} className="text-zinc-400 mx-auto" />
              <div className="text-sm text-zinc-700 dark:text-zinc-300 mt-2">Drop a demo, screenshots, or video link</div>
              <div className="text-xs text-zinc-500 mt-1">.dem, .png, .jpg, .mp4 — or paste a YouTube / streamable URL below</div>
              <Input className="mt-3 max-w-sm mx-auto" placeholder="https://…" />
            </div>
          </div>

          <div className="flex items-start gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
            <input type="checkbox" id="ack" className="mt-0.5 rounded border-zinc-300 dark:border-zinc-700 text-brand-600 focus:ring-brand-500" />
            <label htmlFor="ack" className="text-xs text-zinc-600 dark:text-zinc-400">I confirm this is an honest report and any false submissions may result in my own account being restricted.</label>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button variant="ghost">Cancel</Button>
            <Button variant="primary" icon="send-horizontal" onClick={() => { setSubmitted(true); toast.push({ title: 'Report submitted', body: 'Admins will review shortly.', kind: 'success' }); }}>Submit report</Button>
          </div>
        </Card>
      )}
    </div>
  );
}

// -----------------------------------------------------------
// ADMIN PANEL (overview)
// -----------------------------------------------------------
function AdminPanelView({ navigate }) {
  return (
    <div className="p-4 sm:p-6 space-y-6 max-w-[1400px]">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Admin panel</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Manage admins, groups, servers, and panel settings.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[
          { id: 'admins',   icon: 'users',         title: 'Admins',     desc: '12 active · 1 inactive', count: 13 },
          { id: 'groups',   icon: 'shield-check',  title: 'Groups',     desc: 'Permissions and immunity', count: 4 },
          { id: 'servers',  icon: 'server',        title: 'Servers',    desc: '5 of 6 online', count: 6 },
          { id: 'addban',   icon: 'plus-circle',   title: 'Add ban',    desc: 'Manually ban a SteamID or IP' },
          { id: 'settings', icon: 'settings',      title: 'Settings',   desc: 'SMTP, theme, integrations' },
          { id: 'logs',     icon: 'scroll-text',   title: 'Audit log',  desc: 'Admin actions across the panel' },
        ].map((card) => (
          <button
            key={card.id}
            onClick={() => navigate(card.id)}
            className="text-left bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 hover:border-zinc-300 dark:hover:border-zinc-700 hover:shadow-sm transition-all"
          >
            <div className="flex items-start justify-between">
              <div className="w-9 h-9 rounded-lg bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-700 dark:text-zinc-300">
                <Icon name={card.icon} size={16} />
              </div>
              {card.count && <span className="text-xs tabular-nums text-zinc-500">{card.count}</span>}
            </div>
            <div className="mt-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100">{card.title}</div>
            <div className="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{card.desc}</div>
          </button>
        ))}
      </div>

      {/* Admins table */}
      <Card className="overflow-hidden">
        <CardHeader title="Admins" subtitle="Everyone with panel access" icon="users"
          action={<Button variant="primary" size="sm" icon="plus">Invite admin</Button>}
        />
        <table className="w-full text-sm">
          <thead>
            <tr className="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-900/40 border-b border-zinc-200 dark:border-zinc-800">
              <th className="text-left font-semibold px-5 py-2.5">Name</th>
              <th className="text-left font-semibold px-3 py-2.5 hidden sm:table-cell">SteamID</th>
              <th className="text-left font-semibold px-3 py-2.5">Role</th>
              <th className="text-left font-semibold px-3 py-2.5 hidden md:table-cell">Last active</th>
              <th className="text-left font-semibold px-3 py-2.5">Status</th>
              <th className="px-5 py-2.5"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
            {ADMINS.map((a, i) => (
              <tr key={a.id} className="group hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                <td className="px-5 py-3">
                  <div className="flex items-center gap-3">
                    <Avatar name={a.name} size={28} />
                    <span className="font-medium text-zinc-900 dark:text-zinc-100">{a.name}</span>
                  </div>
                </td>
                <td className="px-3 py-3 hidden sm:table-cell font-mono text-xs text-zinc-500">{a.steam}</td>
                <td className="px-3 py-3">
                  <span className={`inline-flex items-center px-2 h-5 rounded text-[11px] font-medium ${
                    a.role === 'Root' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' :
                    a.role === 'Admin' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400' :
                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                  }`}>{a.role}</span>
                </td>
                <td className="px-3 py-3 hidden md:table-cell text-zinc-500 text-xs">{['2m ago','14m ago','3h ago','1d ago','12d ago'][i]}</td>
                <td className="px-3 py-3"><StatusPill status={a.active ? 'online' : 'offline'} /></td>
                <td className="px-5 py-3">
                  <div className="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100">
                    <Button variant="ghost" size="icon"><Icon name="pencil" size={13} /></Button>
                    <Button variant="ghost" size="icon"><Icon name="more-horizontal" size={14} /></Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}

// -----------------------------------------------------------
// COMM BLOCKS — reuses the ban list pattern, slimmer
// -----------------------------------------------------------
function CommsView({ onPlayer }) {
  return (
    <div className="p-4 sm:p-6 space-y-4 max-w-[1400px]">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Comm blocks</h1>
        <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Active mutes and gags.</p>
      </div>
      <Card className="overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-[10px] uppercase tracking-wider text-zinc-500 dark:text-zinc-400 bg-zinc-50/50 dark:bg-zinc-900/40 border-b border-zinc-200 dark:border-zinc-800">
              <th className="text-left font-semibold px-5 py-2.5">Player</th>
              <th className="text-left font-semibold px-3 py-2.5">Type</th>
              <th className="text-left font-semibold px-3 py-2.5 hidden sm:table-cell">Reason</th>
              <th className="text-left font-semibold px-3 py-2.5">Length</th>
              <th className="text-left font-semibold px-3 py-2.5 hidden md:table-cell">Admin</th>
              <th className="text-left font-semibold px-3 py-2.5">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100 dark:divide-zinc-800">
            {COMMS.map((c) => (
              <tr key={c.id} onClick={() => onPlayer(c)} className={`cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800/40 border-l-[3px] ${stateAccent[c.state]}`}>
                <td className="px-5 py-3">
                  <div className="flex items-center gap-3"><Avatar name={c.name} size={26} /><span className="font-medium">{c.name}</span></div>
                </td>
                <td className="px-3 py-3">
                  <span className="inline-flex items-center gap-1 text-zinc-600 dark:text-zinc-400">
                    <Icon name={c.type === 'mute' ? 'mic-off' : 'message-square-off'} size={13} /> {c.type}
                  </span>
                </td>
                <td className="px-3 py-3 hidden sm:table-cell text-zinc-600 dark:text-zinc-400">{c.reason}</td>
                <td className="px-3 py-3 tabular-nums text-zinc-600 dark:text-zinc-400">{fmtDuration(c.duration)}</td>
                <td className="px-3 py-3 hidden md:table-cell text-zinc-500">{c.admin}</td>
                <td className="px-3 py-3"><StatusPill status={c.state} /></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </div>
  );
}

Object.assign(window, {
  DashboardView, BanListView, PlayerDrawer, SubmitView, AdminPanelView, CommsView,
});
