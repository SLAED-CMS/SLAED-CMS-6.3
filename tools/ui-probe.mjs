// Author: Eduard Laas
// 2005 - 2026 SLAED
// License: MIT
// Website: slaed.net
//
// The CLI probes, reached from the two runners that need them. A probe is PHP that boots the real core from a terminal
// and answers one job as JSON - tests/Support/<name>_probe.php - and it exists because some things a browser has to be
// shown cannot be arranged from a browser: a theme has to be selected through the account row before the request that
// renders it, and the OAuth card renders only for a browser already carrying a pending flow.
//
// Both runners walk tools/ui-shots.json, so both meet the same `probe` key on the same page, and the seeding is written
// here once rather than twice. A page names its probe; the seed runs `make` before the walk and answers with the cookie
// every context is then handed, and `gone` takes the state away afterwards. A run that dies before that leaves nothing
// behind for long - the probe stamps its row with its own expiry.

import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

// One job of one probe, answered as JSON. Whatever the probe writes it writes under a scratch directory outside the
// repository: a directory left inside storage/cache is one the next reader has to work out the provenance of
export function getProbeAnswer(file, name, ...rest) {
  const work = join(tmpdir(), 'slaed_' + file);
  const out = execFileSync('php', [join(root, 'tests/Support/' + file + '_probe.php'), name, work, ...rest], { encoding: 'utf8' });
  const said = JSON.parse(out);
  if (said.error) throw new Error(file + ' probe: ' + said.error);
  return said;
}

// Seed the state every page of the manifest names a probe for, and answer with the cookies and what has to be taken back
export function setSeededState(conf) {
  const out = { cookies: [], sown: [] };
  for (const kind of new Set((conf.pages || []).filter((one) => one.probe).map((one) => one.probe))) {
    const said = getProbeAnswer(kind, 'make');
    out.sown.push([kind, said.token]);
    out.cookies.push({ name: said.cookie, value: said.token, url: conf.base, secure: !!said.secure, httpOnly: true, sameSite: 'Lax' });
  }
  return out;
}

// Take back what the seed sowed, and never let that failing be the thing that fails a walk which already finished
export function deleteSeededState(seed) {
  for (const [kind, tok] of seed.sown) {
    try {
      getProbeAnswer(kind, 'gone', tok);
    } catch (err) {
      console.log('  the ' + kind + ' probe could not clear its own state: ' + err.message);
    }
  }
}
