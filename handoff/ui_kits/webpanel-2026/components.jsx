// ============================================================
// SBPP 2026 — Shared components
// Atoms: Icon, Pill, Avatar, Button, Input, Select, Card, etc.
// ============================================================

const { useState, useEffect, useRef, useCallback, useMemo, createContext, useContext } = React;

// -----------------------------------------------------------
// Lucide icon helper (uses lucide UMD)
// -----------------------------------------------------------
function Icon({ name, className = '', size = 16, strokeWidth = 2, ...rest }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!ref.current || !window.lucide) return;
    ref.current.innerHTML = '';
    const el = document.createElement('i');
    el.setAttribute('data-lucide', name);
    ref.current.appendChild(el);
    window.lucide.createIcons({ attrs: { 'stroke-width': strokeWidth, width: size, height: size } });
  }, [name, size, strokeWidth]);
  return <span ref={ref} className={`inline-flex items-center justify-center shrink-0 ${className}`} aria-hidden="true" {...rest} />;
}

// -----------------------------------------------------------
// Theme (dark/light/system)
// -----------------------------------------------------------
const ThemeCtx = createContext(null);
function ThemeProvider({ children }) {
  const [mode, setMode] = useState(() => {
    try { return localStorage.getItem('sbpp-theme') || 'system'; } catch { return 'system'; }
  });
  useEffect(() => {
    const apply = () => {
      const dark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);
    };
    apply();
    try { localStorage.setItem('sbpp-theme', mode); } catch {}
    if (mode === 'system') {
      const mq = window.matchMedia('(prefers-color-scheme: dark)');
      const onChange = () => apply();
      mq.addEventListener('change', onChange);
      return () => mq.removeEventListener('change', onChange);
    }
  }, [mode]);
  return <ThemeCtx.Provider value={{ mode, setMode }}>{children}</ThemeCtx.Provider>;
}
const useTheme = () => useContext(ThemeCtx);

// -----------------------------------------------------------
// Toast system
// -----------------------------------------------------------
const ToastCtx = createContext(null);
function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);
  const push = useCallback((t) => {
    const id = Math.random().toString(36).slice(2);
    setToasts((cur) => [...cur, { id, ...t }]);
    setTimeout(() => setToasts((cur) => cur.filter((x) => x.id !== id)), t.duration || 4000);
  }, []);
  return (
    <ToastCtx.Provider value={{ push }}>
      {children}
      <div className="fixed top-4 right-4 z-[80] flex flex-col gap-2 w-[min(360px,calc(100vw-2rem))]">
        {toasts.map((t) => (
          <div key={t.id} className="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-lg p-3 flex gap-3 items-start animate-[slideIn_.2s_ease]">
            <div className={`mt-0.5 ${
              t.kind === 'error' ? 'text-red-600' :
              t.kind === 'warn' ? 'text-amber-600' :
              t.kind === 'success' ? 'text-emerald-600' : 'text-brand-600'
            }`}>
              <Icon name={t.kind === 'error' ? 'circle-x' : t.kind === 'warn' ? 'triangle-alert' : t.kind === 'success' ? 'circle-check' : 'info'} size={18} />
            </div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{t.title}</div>
              {t.body && <div className="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">{t.body}</div>}
            </div>
            <button onClick={() => setToasts((cur) => cur.filter((x) => x.id !== t.id))} className="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 -mt-1 -mr-1 p-1">
              <Icon name="x" size={14} />
            </button>
          </div>
        ))}
      </div>
      <style>{`@keyframes slideIn { from { transform: translateX(100%); opacity: 0 } to { transform: translateX(0); opacity: 1 } }`}</style>
    </ToastCtx.Provider>
  );
}
const useToast = () => useContext(ToastCtx);

// -----------------------------------------------------------
// Buttons
// -----------------------------------------------------------
function Button({ variant = 'default', size = 'md', icon, children, className = '', ...rest }) {
  const sizes = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-9 px-3.5 text-sm gap-2',
    lg: 'h-10 px-4 text-sm gap-2',
    icon: 'h-9 w-9 p-0',
  };
  const variants = {
    default: 'bg-zinc-900 text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white border border-transparent',
    primary: 'bg-brand-600 text-white hover:bg-brand-700 border border-transparent shadow-sm',
    secondary: 'bg-white text-zinc-900 hover:bg-zinc-50 border border-zinc-200 dark:bg-zinc-900 dark:text-zinc-100 dark:border-zinc-800 dark:hover:bg-zinc-800',
    ghost: 'bg-transparent text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 border border-transparent',
    danger: 'bg-red-600 text-white hover:bg-red-700 border border-transparent',
    outline: 'bg-transparent text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-zinc-900 border border-zinc-200 dark:border-zinc-800',
  };
  return (
    <button
      className={`inline-flex items-center justify-center font-medium rounded-md transition-colors disabled:opacity-50 disabled:pointer-events-none ${sizes[size]} ${variants[variant]} ${className}`}
      {...rest}
    >
      {icon && <Icon name={icon} size={size === 'sm' ? 13 : 14} />}
      {children}
    </button>
  );
}

// -----------------------------------------------------------
// Status pill
// -----------------------------------------------------------
function StatusPill({ status, className = '' }) {
  const styles = {
    permanent:   'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-500/30',
    active:      'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-500/30',
    expired:     'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-500/30',
    unbanned:    'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-500/30',
    online:      'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-400',
    offline:     'bg-zinc-100 text-zinc-500 ring-zinc-500/20 dark:bg-zinc-900 dark:text-zinc-500',
  };
  const label = {
    permanent: 'Permanent',
    active: 'Active',
    expired: 'Expired',
    unbanned: 'Unbanned',
    online: 'Online',
    offline: 'Offline',
  }[status] || status;
  return (
    <span className={`inline-flex items-center gap-1 px-2 h-5 rounded-full text-[11px] font-medium ring-1 ring-inset ${styles[status]} ${className}`}>
      {status === 'online' && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />}
      {label}
    </span>
  );
}

// State accent (for left border on rows)
const stateAccent = {
  permanent: 'border-l-red-500',
  active:    'border-l-amber-500',
  expired:   'border-l-zinc-300 dark:border-l-zinc-700',
  unbanned:  'border-l-emerald-500',
};

// -----------------------------------------------------------
// Avatar
// -----------------------------------------------------------
function Avatar({ name, size = 32, className = '' }) {
  const initials = (name || '?').split(/[\s_-]+/).map(s => s[0]).filter(Boolean).slice(0,2).join('').toUpperCase() || '?';
  // Stable color from name
  let h = 0;
  for (let i = 0; i < (name||'').length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  const hue = h % 360;
  return (
    <div
      className={`rounded-full flex items-center justify-center font-semibold text-white shrink-0 ${className}`}
      style={{ width: size, height: size, background: `hsl(${hue} 55% 45%)`, fontSize: Math.max(10, size * 0.36) }}
      aria-hidden="true"
    >
      {initials}
    </div>
  );
}

// -----------------------------------------------------------
// Inputs
// -----------------------------------------------------------
function Input({ icon, className = '', ...rest }) {
  return (
    <div className="relative">
      {icon && (
        <div className="absolute inset-y-0 left-0 pl-2.5 flex items-center text-zinc-400 pointer-events-none">
          <Icon name={icon} size={14} />
        </div>
      )}
      <input
        className={`w-full h-9 ${icon ? 'pl-8' : 'pl-3'} pr-3 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-colors ${className}`}
        {...rest}
      />
    </div>
  );
}

function Select({ children, className = '', ...rest }) {
  return (
    <div className="relative">
      <select
        className={`appearance-none w-full h-9 pl-3 pr-8 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-sm text-zinc-900 dark:text-zinc-100 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none ${className}`}
        {...rest}
      >
        {children}
      </select>
      <div className="absolute inset-y-0 right-0 pr-2.5 flex items-center text-zinc-400 pointer-events-none">
        <Icon name="chevron-down" size={14} />
      </div>
    </div>
  );
}

function Textarea({ className = '', ...rest }) {
  return (
    <textarea
      className={`w-full p-3 rounded-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-sm text-zinc-900 dark:text-zinc-100 placeholder:text-zinc-400 dark:placeholder:text-zinc-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-colors resize-y ${className}`}
      {...rest}
    />
  );
}

// -----------------------------------------------------------
// Card / Section
// -----------------------------------------------------------
function Card({ children, className = '', as: As = 'div' }) {
  return (
    <As className={`bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl ${className}`}>
      {children}
    </As>
  );
}

function CardHeader({ title, subtitle, action, icon, className = '' }) {
  return (
    <div className={`flex items-start justify-between gap-3 px-5 py-4 border-b border-zinc-200 dark:border-zinc-800 ${className}`}>
      <div className="flex items-start gap-3 min-w-0">
        {icon && <div className="mt-0.5 text-zinc-500"><Icon name={icon} size={16} /></div>}
        <div className="min-w-0">
          <div className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{title}</div>
          {subtitle && <div className="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{subtitle}</div>}
        </div>
      </div>
      {action}
    </div>
  );
}

// -----------------------------------------------------------
// Filter chip
// -----------------------------------------------------------
function Chip({ active, onClick, children, count, dot }) {
  return (
    <button
      onClick={onClick}
      className={`inline-flex items-center gap-1.5 h-7 px-2.5 rounded-full text-xs font-medium transition-colors border
        ${active
          ? 'bg-zinc-900 text-white border-zinc-900 dark:bg-zinc-100 dark:text-zinc-900 dark:border-zinc-100'
          : 'bg-white text-zinc-600 border-zinc-200 hover:bg-zinc-50 hover:text-zinc-900 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200'
        }`}
    >
      {dot && <span className={`w-1.5 h-1.5 rounded-full ${dot}`} />}
      {children}
      {typeof count === 'number' && (
        <span className={`tabular-nums text-[10px] ${active ? 'opacity-70' : 'text-zinc-400 dark:text-zinc-600'}`}>{count}</span>
      )}
    </button>
  );
}

// -----------------------------------------------------------
// Skeleton row
// -----------------------------------------------------------
function Skel({ className = 'h-4 w-full' }) {
  return <div className={`skel rounded ${className}`} />;
}

// -----------------------------------------------------------
// Drawer (right-side)
// -----------------------------------------------------------
function Drawer({ open, onClose, children, width = 'w-[min(560px,100vw)]' }) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50">
      <div className="absolute inset-0 bg-zinc-950/40 backdrop-blur-[2px] animate-[fadeIn_.2s_ease]" onClick={onClose} />
      <div className={`absolute right-0 top-0 h-full ${width} bg-zinc-50 dark:bg-zinc-950 border-l border-zinc-200 dark:border-zinc-800 shadow-2xl flex flex-col animate-[slideInR_.25s_cubic-bezier(.4,0,.2,1)]`}>
        {children}
      </div>
      <style>{`
        @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
        @keyframes slideInR { from { transform: translateX(100%) } to { transform: translateX(0) } }
      `}</style>
    </div>
  );
}

// -----------------------------------------------------------
// Format helpers
// -----------------------------------------------------------
function timeAgo(date) {
  const d = (typeof date === 'string') ? new Date(date) : date;
  const s = Math.floor((Date.now() - d.getTime()) / 1000);
  if (s < 60) return `${s}s ago`;
  if (s < 3600) return `${Math.floor(s/60)}m ago`;
  if (s < 86400) return `${Math.floor(s/3600)}h ago`;
  if (s < 86400*30) return `${Math.floor(s/86400)}d ago`;
  return d.toLocaleDateString();
}

function fmtDuration(mins) {
  if (mins === 0) return 'Permanent';
  if (mins < 60) return `${mins}m`;
  if (mins < 60*24) return `${Math.round(mins/60)}h`;
  if (mins < 60*24*7) return `${Math.round(mins/(60*24))}d`;
  if (mins < 60*24*30) return `${Math.round(mins/(60*24*7))}w`;
  return `${Math.round(mins/(60*24*30))}mo`;
}

// -----------------------------------------------------------
// Game badge (small colored square as placeholder for game icons)
// -----------------------------------------------------------
function GameBadge({ game, size = 18 }) {
  const map = {
    tf2:    { c: '#cf6a32', t: 'TF2' },
    csgo:   { c: '#1f4d6b', t: 'CS' },
    cs2:    { c: '#274c7a', t: 'CS2' },
    gmod:   { c: '#1a4470', t: 'GM' },
    css:    { c: '#36322c', t: 'SS' },
    l4d2:   { c: '#7a2317', t: 'L4' },
    rust:   { c: '#9a4a1a', t: 'RU' },
  };
  const g = map[game] || { c: '#52525b', t: '?' };
  return (
    <span
      className="rounded-[3px] flex items-center justify-center font-bold text-white shrink-0"
      style={{ width: size, height: size, background: g.c, fontSize: Math.max(8, size * 0.5) }}
    >{g.t}</span>
  );
}

// Export everything
Object.assign(window, {
  React, useState, useEffect, useRef, useCallback, useMemo, createContext, useContext,
  Icon, ThemeProvider, useTheme, ToastProvider, useToast,
  Button, StatusPill, stateAccent, Avatar, Input, Select, Textarea,
  Card, CardHeader, Chip, Skel, Drawer,
  timeAgo, fmtDuration, GameBadge,
});
