const fs = require('fs');
const path = 'c:\\xampp\\htdocs\\blood_donation\\src\\pages\\AdminDashboard.js';
let text = fs.readFileSync(path, 'utf8');
const start = '          )}\n                          <td>\n';
const marker = '\n          {activeTab === "dashboard" && (\n';
const i = text.indexOf(start);
const j = i >= 0 ? text.indexOf(marker, i) : -1;
if (i === -1 || j === -1) {
  console.log('missing', i, j);
  process.exit(1);
}
fs.writeFileSync(path, text.slice(0, i + 12) + text.slice(j), 'utf8');
console.log('ok');
