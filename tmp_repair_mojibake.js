const fs = require('fs');
const path = process.argv[2];
const content = fs.readFileSync(path, 'utf8');
const suspiciousRegex = /[\u00C3\u00C2\u00E2\uFFFD]/;
const scoreRegex = /[\u00C3\u00C2\u00E2\uFFFD]/g;
function score(value) {
  const matches = value.match(scoreRegex);
  return matches ? matches.length : 0;
}
function repairLine(line) {
  let current = line;
  for (let i = 0; i < 4; i += 1) {
    if (!suspiciousRegex.test(current)) break;
    const repaired = Buffer.from(current, 'latin1').toString('utf8');
    if (score(repaired) < score(current)) {
      current = repaired;
      continue;
    }
    break;
  }
  if (current.trimStart().startsWith('//')) {
    current = current.replace(/[\u2500\u2501]+/g, '--');
  }
  return current
    .replace(/\u2014/g, '-')
    .replace(/\u2013/g, '-');
}
const eol = content.includes('\r\n') ? '\r\n' : '\n';
const repaired = content.split(/\r?\n/).map(repairLine).join(eol);
if (repaired !== content) {
  fs.writeFileSync(path, repaired, 'utf8');
}
