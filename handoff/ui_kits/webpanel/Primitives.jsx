// Page title + breadcrumb (#content_title + #breadcrumb)
function PageTitle({ title, crumbs = [] }) {
  return (
    <div style={{ marginBottom: 14 }}>
      <div style={{ fontSize: 26, color: '#4d4742', marginBottom: 4 }}>{title}</div>
      <div style={{ fontSize: 12, textTransform: 'uppercase', color: '#4d4742' }}>
        {crumbs.map((c, i) => (
          <span key={i}>{i ? ' » ' : ''}<a style={{ color: '#4d4742', textDecoration: 'none' }}>{c}</a></span>
        ))}
      </div>
    </div>
  );
}

// Section header — h3 grey rule with white uppercase label
function SectionHeader({ children, right }) {
  return (
    <div style={{
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      background: '#a69e97', color: '#e6e6e6', fontSize: 12, fontWeight: 700,
      padding: 10, margin: '5px 0 10px',
    }}>
      <span>{children}</span>
      {right && <span style={{ fontWeight: 400, fontSize: 11 }}>{right}</span>}
    </div>
  );
}

// Card — flat #e0e0e0 surface, 1px #ccc border
function Card({ children, style }) {
  return (
    <div style={{ background: '#e0e0e0', border: '1px solid #ccc', padding: 10, ...style }}>{children}</div>
  );
}

// Buttons
function Btn({ kind = 'game', children, onClick, full }) {
  const base = {
    fontFamily: 'Verdana, sans-serif', fontSize: 13, color: '#fff',
    border: '1px solid', borderRadius: 3, padding: '6px 12px',
    transition: 'all .25s ease', cursor: 'pointer',
    textTransform: kind === 'login' ? 'uppercase' : 'none',
    width: full ? '100%' : undefined,
  };
  const palette = {
    game:   { background: '#8cc152', borderColor: '#8cc152' },
    ok:     { background: '#729e42', borderColor: '#729e42' },
    save:   { background: '#7d4071', borderColor: '#7d4071' },
    cancel: { background: '#cf7336', borderColor: '#cf7336' },
    refresh:{ background: '#3bafda', borderColor: '#3bafda' },
    login:  { background: '#5885a2', borderColor: '#5885a2', padding: '8px 12px', fontSize: 14 },
  };
  return <button style={{ ...base, ...palette[kind] }} onClick={onClick}>{children}</button>;
}

// Inline message block
function Msg({ tone = 'green', title, children }) {
  const map = {
    red:   { bg: '#fefad3', bd: '#e80909', fg: '#e80909' },
    green: { bg: '#fcf7c9', bd: '#339933', fg: '#339933' },
    blue:  { bg: '#fcf7c9', bd: '#0066ff', fg: '#0066ff' },
    amber: { bg: '#ffdd87', bd: '#ffce54', fg: '#8a6d3b' },
  };
  const t = map[tone];
  return (
    <div style={{ background: t.bg, border: `1px solid ${t.bd}`, color: t.fg, padding: 8, marginBottom: 8 }}>
      {title && <b style={{ fontSize: 13 }}>{title}</b>} {children}
    </div>
  );
}

// Form inputs
function Field({ label, required, children, error }) {
  return (
    <label style={{ display: 'block', marginBottom: 10 }}>
      <div style={{ fontSize: 11, color: '#444', marginBottom: 4, fontWeight: 700 }}>
        {label} {required && <span style={{ color: '#f00' }}>*</span>}
      </div>
      {children}
      {error && <div style={{ color: '#cc0000', fontSize: 11, paddingTop: 3 }}>{error}</div>}
    </label>
  );
}

const inputBase = {
  background: '#fff', fontSize: 13, padding: '6px 12px',
  border: '1px solid #ccc', borderRadius: 3, fontFamily: 'Verdana, sans-serif',
  boxSizing: 'border-box',
};

function TextInput(props) { return <input {...props} style={{ ...inputBase, width: '100%', ...(props.style || {}) }} />; }
function SelectInput({ children, ...props }) {
  return <select {...props} style={{ ...inputBase, padding: '5px 8px', width: '100%', ...(props.style || {}) }}>{children}</select>;
}

Object.assign(window, { PageTitle, SectionHeader, Card, Btn, Msg, Field, TextInput, SelectInput });
