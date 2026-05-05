// Top tabs / nav bar — exact recreation of #tabsWrapper + #tabs from main.css
function TopTabs({ active = 'dashboard', user = 'IceMan', onNav }) {
  const items = [
    ['dashboard', 'Dashboard'],
    ['banlist', 'Ban List'],
    ['servers', 'Servers'],
    ['submit', 'Submit a Ban'],
    ['protest', 'Protest a Ban'],
    ['admin', 'Admin Panel'],
  ];
  return (
    <div style={ttStyles.bar}>
      <div style={ttStyles.inner}>
        {items.map(([k, label]) => (
          <a key={k}
             onClick={() => onNav && onNav(k)}
             style={{ ...ttStyles.tab, ...(active === k ? ttStyles.tabActive : null) }}>
            {label}
          </a>
        ))}
        <span style={ttStyles.spacer} />
        <span style={ttStyles.user}>Welcome, <b style={{ color: '#bd754b' }}>{user}</b></span>
        <a style={{ ...ttStyles.tab, ...ttStyles.logout }} onClick={() => onNav && onNav('logout')}>Logout</a>
      </div>
    </div>
  );
}

const ttStyles = {
  bar: { width: '100%', height: 50, background: '#38322c', textAlign: 'left' },
  inner: { width: 984, margin: '0 auto', display: 'flex', alignItems: 'stretch', height: 50 },
  tab: {
    color: '#eee', padding: '0 16px', lineHeight: '50px', fontSize: 11,
    fontWeight: 800, letterSpacing: '.04em', textTransform: 'uppercase',
    textDecoration: 'none', cursor: 'pointer', transition: 'all .25s ease',
  },
  tabActive: { background: '#2a2723', color: '#fff' },
  spacer: { flex: 1 },
  user: { color: '#eee', lineHeight: '50px', fontSize: 12, marginRight: 14 },
  logout: { background: '#b05015', color: '#fff' },
};

window.TopTabs = TopTabs;
