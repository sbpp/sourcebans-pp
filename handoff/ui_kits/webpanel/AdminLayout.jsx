// AdminLayout — left rail + content, taken from #admin-page-menu / #admin-page-content
function AdminLayout({ active = 'bans', onNav, children }) {
  const items = [
    ['admins', 'Admins', '../../assets/admin/admins.png'],
    ['bans', 'Bans', '../../assets/admin/bans.png'],
    ['comms', 'Comms', '../../assets/admin/comms.png'],
    ['groups', 'Groups', '../../assets/admin/groups.png'],
    ['mods', 'Mods', '../../assets/admin/mods.png'],
    ['servers', 'Servers', '../../assets/admin/servers.png'],
    ['settings', 'Settings', '../../assets/admin/settings.png'],
    ['account', 'Your Account', '../../assets/admin/your_account.png'],
  ];
  return (
    <div style={{ display: 'flex', gap: 0 }}>
      <div style={{ width: '20%', minWidth: 180 }}>
        {items.map(([k, label, ico]) => (
          <a key={k}
             onClick={() => onNav && onNav(k)}
             style={{
               display: 'flex', alignItems: 'center', gap: 8, height: 32, padding: '0 10px',
               background: active === k ? '#3d3631' : '#4f463e',
               color: '#fff', textDecoration: 'none', fontSize: 11,
               cursor: 'pointer', marginBottom: 2, fontWeight: active === k ? 700 : 400,
             }}>
            <img src={ico} alt="" style={{ width: 18, height: 18 }} />
            {label}
          </a>
        ))}
      </div>
      <div style={{ flex: 1, background: '#e0e0e0', padding: 14, marginLeft: 6 }}>
        {children}
      </div>
    </div>
  );
}

window.AdminLayout = AdminLayout;
