// ListTable — reproducing .listtable / .listtable_top / td.listtable_1 styling
function ListTable({ columns, rows, onRowClick }) {
  return (
    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 10, fontFamily: 'Verdana, sans-serif', color: '#000' }}>
      <thead>
        <tr>
          {columns.map((c, i) => (
            <th key={i} style={{
              background: '#2a2723', color: '#e6e6e6', fontWeight: 700, fontSize: 11,
              padding: '6px 10px', height: 30, textTransform: 'uppercase', textAlign: 'left',
              width: c.width,
            }}>{c.label}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((row, ri) => {
          const stateBg = {
            unbanned: '#c8f7c5',
            permanent: '#f1a9a0',
            banned: '#fde3a7',
          }[row._state] || (ri % 2 ? '#eaebeb' : '#e0e0e0');
          return (
            <tr key={ri}
                onClick={() => onRowClick && onRowClick(row)}
                style={{ cursor: onRowClick ? 'pointer' : 'default' }}>
              {columns.map((c, ci) => (
                <td key={ci} style={{
                  background: stateBg,
                  padding: 4, fontSize: 10,
                  borderBottom: '1px solid #ccc',
                  borderLeft: ci === 0 ? 0 : '1px solid #ccc',
                }}>{c.render ? c.render(row) : row[c.key]}</td>
              ))}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

window.ListTable = ListTable;
