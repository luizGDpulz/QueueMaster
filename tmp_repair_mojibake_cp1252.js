const fs = require('fs');
const files = process.argv.slice(2);
const suspiciousRegex = /[\u00C3\u00C2\u00E2\u0192\u201A\u201E\u2020\u2021\u02C6\u2030\u0160\u2039\u0152\u017D\u2018\u2019\u201C\u201D\u2022\u2013\u2014\u02DC\u2122\u0161\u203A\u0153\u017E\u0178\uFFFD]/;
const scoreRegex = suspiciousRegex;
const cp1252Map = new Map([
  ['\u20AC', 0x80], ['\u201A', 0x82], ['\u0192', 0x83], ['\u201E', 0x84], ['\u2026', 0x85], ['\u2020', 0x86], ['\u2021', 0x87], ['\u02C6', 0x88],
  ['\u2030', 0x89], ['\u0160', 0x8A], ['\u2039', 0x8B], ['\u0152', 0x8C], ['\u017D', 0x8E], ['\u2018', 0x91], ['\u2019', 0x92], ['\u201C', 0x93],
  ['\u201D', 0x94], ['\u2022', 0x95], ['\u2013', 0x96], ['\u2014', 0x97], ['\u02DC', 0x98], ['\u2122', 0x99], ['\u0161', 0x9A], ['\u203A', 0x9B],
  ['\u0153', 0x9C], ['\u017E', 0x9E], ['\u0178', 0x9F],
]);
function score(value) {
  const matches = value.match(new RegExp(scoreRegex, 'g'));
  return matches ? matches.length : 0;
}
function encodeCp1252(value) {
  const bytes = [];
  for (const char of value) {
    if (cp1252Map.has(char)) {
      bytes.push(cp1252Map.get(char));
      continue;
    }
    const code = char.codePointAt(0);
    if (code <= 0xFF) {
      bytes.push(code);
    } else {
      bytes.push(0x3F);
    }
  }
  return Buffer.from(bytes);
}
function repairLine(line) {
  let current = line;
  for (let i = 0; i < 4; i += 1) {
    if (!suspiciousRegex.test(current)) break;
    const repaired = encodeCp1252(current).toString('utf8');
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
    .replace(/[\u2014\u2013]/g, '-')
    .replace(/\uFFFD/g, '');
}
for (const file of files) {
  const original = fs.readFileSync(file, 'utf8');
  const eol = original.includes('\r\n') ? '\r\n' : '\n';
  const repaired = original.split(/\r?\n/).map(repairLine).join(eol);
  if (repaired !== original) {
    fs.writeFileSync(file, repaired, 'utf8');
  }
}
