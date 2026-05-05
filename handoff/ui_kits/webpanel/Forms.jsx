// Login screen and Submit-a-ban form — composed views

function LoginCard({ onLogin }) {
  const [user, setUser] = React.useState('IceMan');
  const [pw, setPw] = React.useState('');
  return (
    <div style={{ width: 305, background: '#e0e0e0', padding: 12, margin: '30px auto' }}>
      <div style={{ textAlign: 'center', height: 60, paddingTop: 6 }}>
        <img src="../../assets/logo.png" style={{ width: 48, height: 48 }} />
      </div>
      <Field label="Username">
        <input value={user} onChange={(e) => setUser(e.target.value)}
          style={{ width: '100%', padding: '6px 12px', fontSize: 18, border: '1px solid #ccc', borderRadius: 3, boxSizing: 'border-box' }} />
      </Field>
      <Field label="Password">
        <input type="password" value={pw} onChange={(e) => setPw(e.target.value)}
          style={{ width: '100%', padding: '6px 12px', fontSize: 18, border: '1px solid #ccc', borderRadius: 3, boxSizing: 'border-box' }} />
      </Field>
      <label style={{ display: 'block', padding: '6px 0', fontSize: 11 }}>
        <input type="checkbox" defaultChecked /> Remember me
      </label>
      <div style={{ paddingTop: 6 }}>
        <Btn kind="login" full onClick={() => onLogin && onLogin(user)}>Log in</Btn>
      </div>
      <div style={{ borderTop: '1px solid #aaa9a9', textAlign: 'center', padding: '8px 0', marginTop: 18 }}>
        <a style={{ color: '#4d4742', textDecoration: 'none', cursor: 'pointer' }}>Lost your password?</a>
      </div>
    </div>
  );
}

function SubmitBanForm({ onSubmitted }) {
  const [steamid, setSteamid] = React.useState('STEAM_0:1:12345678');
  const [reason, setReason] = React.useState('Cheating / Hacking');
  const [length, setLength] = React.useState('1 day');
  return (
    <Card>
      <SectionHeader>Submit a Ban</SectionHeader>
      <Msg tone="blue" title="Heads-up.">Bans you submit here are reviewed by an admin before going live.</Msg>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0 18px' }}>
        <Field label="SteamID" required>
          <TextInput value={steamid} onChange={(e) => setSteamid(e.target.value)} />
        </Field>
        <Field label="Mod">
          <SelectInput defaultValue="tf">
            <option value="tf">Team Fortress 2</option>
            <option value="csgo">Counter-Strike: GO</option>
            <option value="gmod">Garry's Mod</option>
          </SelectInput>
        </Field>
        <Field label="Reason" required>
          <SelectInput value={reason} onChange={(e) => setReason(e.target.value)}>
            <option>Cheating / Hacking</option>
            <option>Aimbot</option>
            <option>Wallhack</option>
            <option>VAC Ban</option>
            <option>Toxic / Griefing</option>
          </SelectInput>
        </Field>
        <Field label="Length">
          <SelectInput value={length} onChange={(e) => setLength(e.target.value)}>
            <option>10 minutes</option>
            <option>1 hour</option>
            <option>1 day</option>
            <option>1 week</option>
            <option>Permanent</option>
          </SelectInput>
        </Field>
        <Field label="Demo upload">
          <input type="file" style={{ background: '#fff', border: '1px solid #ccc', borderRadius: 3, padding: 3, width: '100%' }} />
        </Field>
        <Field label="Notes for the admin">
          <TextInput placeholder="Map, round, witnesses…" />
        </Field>
      </div>
      <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 8 }}>
        <Btn kind="cancel">Cancel</Btn>
        <Btn kind="save" onClick={() => onSubmitted && onSubmitted()}>Submit Ban</Btn>
      </div>
    </Card>
  );
}

Object.assign(window, { LoginCard, SubmitBanForm });
