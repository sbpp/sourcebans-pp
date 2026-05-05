// Header — 200px tall, logo float-left, search top-right (positioning lifted from main.css)
function Header({ onSearch }) {
  const [q, setQ] = React.useState('');
  return (
    <div style={hdStyles.wrap}>
      <a style={hdStyles.logo}>
        <img src="../../assets/logo.png" alt="SBPP" style={{ width: 70, height: 70 }} />
        <span style={hdStyles.wordmark}>SourceBans<span style={{ color: '#b05015' }}>++</span></span>
      </a>
      <div style={hdStyles.nav}>
        Global admin, ban &amp; communication management for the Source engine
      </div>
      <form style={hdStyles.search} onSubmit={(e) => { e.preventDefault(); onSearch && onSearch(q); }}>
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search ban list…"
          style={hdStyles.searchbox} />
        <button style={hdStyles.searchBtn}>Search</button>
      </form>
    </div>
  );
}

const hdStyles = {
  wrap: { width: 984, margin: '0 auto', height: 200, position: 'relative' },
  logo: { position: 'absolute', left: 0, top: 73, display: 'flex', alignItems: 'center', gap: 12, color: '#2a2723', textDecoration: 'none' },
  wordmark: { fontFamily: '"TF2 Build", Verdana, sans-serif', fontSize: 28, letterSpacing: '.02em', textTransform: 'uppercase' },
  nav: { position: 'absolute', left: 240, top: 100, fontFamily: '"TF2 Build", Verdana, sans-serif', fontSize: 13, color: '#2a2723' },
  search: { position: 'absolute', right: 0, top: 80, width: 300, display: 'flex', flexDirection: 'column', gap: 6 },
  searchbox: { width: '100%', padding: '10px 6px 12px 6px', background: '#dadfe1', color: '#34495e', border: 0, fontSize: 13, boxSizing: 'border-box' },
  searchBtn: {
    padding: '8px 12px', fontSize: 14, textTransform: 'uppercase', background: '#5885a2',
    border: 0, color: '#dadfe1', fontWeight: 700, cursor: 'pointer',
  },
};

window.Header = Header;
