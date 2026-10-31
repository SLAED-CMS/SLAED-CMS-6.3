# Updates

From 8.0 a site learns of a new release by itself, downloads it, verifies its signature and installs it at the click of
its administrator; `slaed.net` serves the releases, keeps a minimal statistic and gives the owner the desk of releases.
This document is for whoever releases SLAED or works on the updater. The administrator of a site reads the help of the
panel instead: `admin/info/update/ru.md` for the page "Обновления", `modules/master/admin/info/ru.md` for the master.

The rules that bind the code are in `.rules/api.md` (the API, the one external host) and `.rules/architecture.md`
(the gate of the entries); where this document and a rule differ, the rule wins and this document is corrected.

## Table of Contents

- [The Parts](#the-parts)
- [Releasing](#releasing)
- [Signature and Keys](#signature-and-keys)
- [Manifest and Packages](#manifest-and-packages)
- [Notes](#notes)
- [The Master](#the-master)
- [The Client](#the-client)
- [Settings, Languages and Themes](#settings-languages-and-themes)
- [Database Steps Between Releases](#database-steps-between-releases)
- [Storage](#storage)
- [Installation](#installation)
- [What a Site Refuses](#what-a-site-refuses)
- [Rehearsal](#rehearsal)
- [Moving slaed.net to 8.0](#moving-slaednet-to-80)
- [Statistics and Privacy](#statistics-and-privacy)
- [Security](#security)
- [Tests](#tests)
- [Why It Is Built This Way](#why-it-is-built-this-way)

## The Parts

```
OWNER (own machine)                MASTER slaed.net (module master)        GitHub
───────────────────                ────────────────────────────────        ──────
release.php build v8.0.1           storage/update/master/                  Release v8.0.1
sign.php      <- key password        inbox/   <- SFTP                      (archives, for people)
rehearse.php v8.0.1                  8.0.1/   archives, manifests, signatures
release.php publish ──SFTP──────►
                    ──gh─────────────────────────────────────────────────► 
                                   Panel -> Master:
                                     Releases · Sites · Statistics · Notes · Keys · Settings

CLIENT (every site 8.x)
  scheduler job updatecheck, once a day
    GET https://slaed.net/api/v1/master/check?sid=…&ch=stable&v=8.0.0&…
    <- manifest + signature + new key statements + notes
    -> signature, freshness, version -> banner "8.0.1 available"
  Panel -> Обновления:
    Download (slaed.net) or upload the archive by hand
    -> size and SHA-256 -> unpack into stage/ -> files against files.json
    -> Install: close the site -> backup -> files -> database steps -> open
       a failure rolls everything back
```

| Part | Code |
| --- | --- |
| Client | class `Update` (`core/classes/update.php`), the panel page `admin/modules/update.php`, the gate `core/gate.php`, the executor `core/apply.php`, the rescuer `core/rescue.php`, the light mode `admin/update.php`, the scheduler job `updatecheck` |
| Signature fallback | class `Signature` (`core/classes/signature.php`), Ed25519 verification without `sodium` |
| Database backup | the portion mode of `Backup` (`core/classes/backup.php`), callable without the core |
| Version | `SL_VERSION` and `SL_CODENAME` in `core/version.php`; `$conf['version']` is built from both at boot |
| API | the branch `/api/` of `public/index.php`, `ApiRouter` and its classes in `core/classes/api/` |
| Master | the module `modules/master/`, in the repository but never in a release archive |
| Tools | `tools/release.php`, `tools/sign.php`, `tools/rehearse.php`, CLI only, in the repository without a secret but never in a release archive: a fork takes them from the repository for its own master |

A fork changes the source of its updates by code only: the host `Update::HOST` and the factory keys `Update::PRIMARY`
and `Update::RECOVERY`. The panel has no field for a server or a key, on purpose.

Only the last release is supported: a fix ships as a new release, old branches are not maintained, and `min` of a
manifest only keeps a site from jumping over something incompatible. The automatic installation of security fixes and
the other ideas after 8.0 wait in the list of ideas of the owner.

## Releasing

The number `8.0.1` below only shows the shape of the commands; the real 8.0.1 is the empty release described under
this list, so a release that carries a database step is a later one.

```
1. Prepare    tests green, docs/VERSIONS.md, UPGRADING.md, SL_VERSION in core/version.php,
              a database step when the schema changed (and the same change in table.sql),
              the branches of PHP, MariaDB and MySQL with their end of support in getSupportEnd() of core/monitor.php
2. Tag        git tag v8.0.1            (local only; publish pushes it)
3. Build      php tools/release.php build v8.0.1
                checks: the tag exists, the tree is clean, SL_VERSION is 8.0.1 and above the previous release,
                        no symlink and no foreign top folder in the archive,
                        the full archive grew by no more than 20 % against the previous release
                git archive of the tag -> slaed-8.0.1.zip (with storage/update/files.json inside)
                delta by the two files.json -> slaed-8.0.1-from-8.0.0.zip
                manifest-beta.json and manifest-stable.json
4. GO         php tools/sign.php 8.0.1  -> shows version, hashes, commit -> "y" -> key password
                -> the signatures of both manifests; unpublished, nobody sees them
5. Rehearse   php tools/rehearse.php v8.0.1
                the new release clean -> the previous release clean -> update by the full archive and by the delta
                with the real signatures -> self-check, integrity, schema = schema of the clean install
                -> "Вернуть предыдущую версию" -> rehearsal.json with the hashes of the archives
6. Publish    php tools/release.php publish v8.0.1
                refused: no green rehearsal of these hashes, test factory keys in the archive,
                         or origin is not the repository of Update::PAGE (github.com/SLAED-CMS/SLAED)
                git push of the tag -> gh release create --repo (both archives, the notes) -> SFTP into inbox/
                through .part and rename
                -> downloads back from both places and compares the hashes
7. Desk       Master -> Releases: Accept -> To beta -> (a few days later) To stable
```

- **Build options.** `--from=v8.0.0` names the base of the delta (default: the previous tag), `--min=8.0.0` the lowest
  version that may update (default: the first release with an updater, the oldest tag that has `core/version.php`),
  `--expires=45` the lifetime of the manifest in days, `--max=<MB>` confirms a size above the 20 % limit and is
  required for the first release, `--security` sets the flag that turns the banner of the sites red. The output is
  `storage/release/<version>/` (gitignored); a rebuild under the same number wipes the folder and its old signatures.
- **A red rehearsal burns no number.** The tag is local, no signature is served: delete the tag, fix, then build,
  sign and rehearse again under the same number.
- **The first release after 8.0 is an empty 8.0.1** that changes only the version, beta first, then stable: it proves
  the whole chain on real sites while they are few and a fault is cheap.
- **`sign.php --refresh 8.0.1`** signs the served manifests again with a new `time` and the same lifetime; once a month,
  since a manifest lives 45 days. An expired manifest is simply not accepted: no update is offered, nothing breaks.
  The tab "Выпуски" of the master warns 10 days before `expires` of a served manifest.
- **Withdrawing a release** is a pause on the master; sites are never rolled back, the fix is the next version. The
  owner marks a manual pause on the GitHub release too (`gh release edit`); the automatic pause cannot, the server has
  no access to GitHub. To serve the previous release again to sites below the paused one, the owner signs its
  manifest again (`sign.php --refresh`): a site that saw a fresher manifest refuses an older `time`.
- **Settings of the owner's machine** live in `tools/.env` (gitignored): `SLAED_KEY=<path of the primary key file>`,
  `SLAED_SFTP=user@slaed.net:22`, `SLAED_SFTP_PATH=<the folder storage/update/master of slaed.net>`. The machine needs
  PHP CLI with `sodium`, `git`, `gh` and `sftp`; how OSPanel switches `sodium` on is in `.rules/global.md`,
  "Toolchain". Publishing goes by SFTP only (the system OpenSSH `sftp`, a login by SSH key), never by FTP.
- **The GitHub release** lives in the repository `Update::PAGE` names, the one the banner of every site links to, so
  `publish` refuses while `origin` points elsewhere and passes that repository to every `gh` call; the address of
  `origin` is never printed. It carries the two signed archives, byte for byte those of the master, and notes that name
  which archive installs and which updates, with their SHA-256, and a link to `docs/VERSIONS.md` of the tag. GitHub adds
  "Source code" archives of the tag by itself: they honour `export-ignore` but lack `storage/update/files.json` and the
  cut of `config/modules.php`, so the notes call them sources for developers, not a release. Manifests and signatures
  never go to GitHub: only the master serves them.
- **Signing never runs in CI or on a server.** Build and publish never touch a key; signing is its own command with
  its own password.

## Signature and Keys

- **Algorithm.** Ed25519 (`sodium_crypto_sign_detached()`), a detached signature over the exact bytes of a file with a
  type tag in front: `SLAED-UPDATE-MANIFEST-v1\n`, `SLAED-KEYCHANGE-v1\n`, `SLAED-NOTE-v1\n` (`Update::MANIFEST`,
  `KEYCHANGE`, `NOTE`). The file itself carries no tag, so a manifest stays plain JSON; the signature lies beside it as
  `<name>.sig`, strict base64 of 64 bytes. A site verifies first and decodes the same bytes after
  (`JSON_THROW_ON_ERROR`), never re-encoding them. Keys and signatures are strict base64 of exactly 32 and 64 bytes.
- **Two keys.** The primary key signs releases and notes; the recovery key only signs key statements. A release
  verifies only under the primary key in force; a statement only under the recovery key in force and with a `kseq`
  above the stored one; a statement that replaces the recovery key needs the signature of the primary key in force as
  well.
- **Factory keys** are the constants `Update::PRIMARY` and `Update::RECOVERY` of the core; `Update::getFactoryKeys()`
  starts every key chain from them. The keys a site trusts after a statement, the `kseq` and the revoked keys lie in
  `storage/update/client/keys.json`, which only the handler of statements writes; a package that holds this path is
  refused. The master and the tools compute the keys in force the same way, `Update::getKeyChain()` over the factory
  keys and every statement in order.
- **Key file.** JSON of type `slaed-key`: the role, the public key, the salt and the Argon2id parameters, a
  `secretbox` of the 32-byte seed. The password has at least 10 characters.
- **Fingerprints** are the SHA-256 of the raw public key; `sign.php` prints them in groups of four, the first 16
  characters are the `kid` a manifest names. README.md publishes both factory fingerprints, and `release.php publish`
  refuses when a factory key is missing from the README or is one of the test keys: a release with a test key could
  never be corrected by anything.
- **Where the private keys live.** Never in the repository, on a server or in CI. The primary key is a password
  encrypted file on a flash drive or in a password manager; the recovery key is paper and a separate flash drive in a
  safe.

**The key ceremony** creates a key file under a password asked twice:

```
php tools/sign.php --keys new primary  <flash drive>/primary.key
php tools/sign.php --keys new recovery <other flash drive>/recovery.key
```

The new recovery key also shows its seed once, in groups of eight hex digits, for paper;
`--keys new recovery <file> --seed` restores the file from the paper. The ceremony runs in the owner's own terminal,
never through a tool that records the session. The two `public` lines it prints go into `Update::PRIMARY` and
`Update::RECOVERY`, the two `fingerprint` lines into README.md.

**Changing a key** is a key statement `{type: keychange, kseq, primary, recovery, time}`:

```
php tools/sign.php --keys change --primary=<new primary file> --with=<recovery key file>
php tools/sign.php --keys change --recovery=<new recovery file> --with=<recovery key file> --key=<primary key file>
php tools/release.php publish --keys=<kseq>
```

The statement lies at `storage/release/keys/<kseq>.json` with `.sig` (the recovery key in force) and `.psig` (the
primary in force, only when the recovery key is replaced), and publish puts it into `inbox/keys/` of the master. The
master serves statements straight from there in the order of `kseq`, up to the first that fails: the signature is
the proof, there is nothing to accept. A site applies every statement above its `kseq` before it reads the manifest of
the same answer. Replaced keys go to `revoked` and never come back. After a change of the primary key, the owner
signs every served manifest and note again under the new key.

**Test keys.** `tests/Fixtures/update/primary.key` and `recovery.key`, password `slaed-test`, are no secret. A test
run trusts them through the constant `UPDATE_KEYS`, which `tests/Support/test_keys.php` defines before the core in the
PHPUnit bootstrap, in the shared probe boot and in the router of the built-in server — the way a test relocates
`UPDATE_DIR`. No request and no setting can define a constant, so the source of trust still changes by code only.

## Manifest and Packages

The manifest is JSON: `type` (`release`), `version`, `channel`, `min` (the lowest version that may update), `php` (the
lowest PHP, from `require.php` of `composer.json` of the tag), `security` (flag), `full` and `delta` — per archive
`size`, `usize` (the size unpacked) and `sha256`, the delta also `from`; `commit`, `time`, `expires` (`time` plus the
lifetime), `kid`. It holds no free text and no address: a site builds the address of the archive itself,
`https://slaed.net/api/v1/master/file?v=<version>&kind=full|delta`. `version` and `from` are strictly `X.Y.Z`, with no
suffix such as `-beta`: the channel is the field `channel`. `build` writes `kid` empty and `time` and `expires` as a
draft; `sign.php` sets all three at the moment it signs, and again on `--refresh`.

- **The full archive** is the tree of the tag plus `storage/update/files.json`; it serves a new installation and every
  site older than the previous release.
- **The delta** holds only the files that are new or whose hash differs from `files.json` of the version `from` — two
  lists compared, not `git diff`, so a rename and `export-ignore` count by themselves — plus every settings file of
  `config/`, changed or not (the merge reads the whole delivery: by `node.php` it knows the Node types), and the same
  complete `files.json` of the new version. A site exactly on `from` takes the delta, every other site the full
  archive; one manifest signs both.
- **`files.json`** lists every shipped file with its SHA-256: `{"version", "files": {path: {"sha256", "size", "site"}}}`;
  `site: true` marks a site file (`Update::isSiteFile()`), the list does not name itself. Every archive from 8.0.0 on
  carries it, so a site always knows what it holds: a file missing from the new list is deleted, a file whose hash on
  disk differs from the old list was changed on the site and is listed before installation.
- **Site files.** The settings files of `config/` and `public/robots.txt` (the job `sitemap` writes its `Sitemap:`
  line) are site files: put only when absent, never checked by the integrity check, never in the list of changed
  files, never deleted by "missing from the new list". Only a PHP step of a version deletes a settings file.
  `public/sitemap.xml` is not in git at all.
- **What the archive leaves out** (owner, 2026-10-18: a release carries only what a site runs). `export-ignore` in
  `.gitattributes` keeps in the repository and on the stand, out of every archive: the modules of slaed.net `master` and
  `changelog` with the templates `changelog*.html` only the changelog renders in `admin` and `lite`, `public/update.php`
  (the owner's internal tool) and `storage/update/sql/table_update6_3.sql`; `tests/`, `tools/`, `docs/`; the stands
  `public/demo/` and `public/phoenix/`; the files of development of the root — `composer.json` (the
  build reads the PHP of the manifest from the tag through `git show`, not from the archive), `package.json`,
  `phpunit.xml`, `phpstan.neon`, `phpstan-bootstrap.php`, `phpstan-stubs.php`, `.php-cs-fixer.dist.php`,
  `.editorconfig`, `.gitmessage`, `.gitignore`, `.gitattributes` — and the documents `README.md`, `UPGRADING.md`,
  `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`. The root keeps `LICENSE` (the MIT licence travels with every
  copy), `.htaccess` and `nginx.conf.example`. `uploads/presentation/` of the module `presentation` and the GeoIP bases
  of `storage/geoip/` ship; a delta carries a GeoIP base only when it changed. A path left out later is deleted from the
  sites by the next update, since the new list no longer names it.
- **No word of a module the archive lacks** (owner, 2026-10-18). `release.php build` cuts from `config/modules.php` of
  the archive the row of every module whose folder the tag holds and the archive does not (`setArchiveModules()`;
  the result must return the same array without those keys, or the build stops); the stand keeps its rows. The names
  of such a module live in its own language files, read by `getModuleLabel()`; its jobs in its `scheduler.php`. The
  module `presentation` shows the commits of the changelog only while `modules/changelog/` is there. The CSS of the
  changelog stays in the two themes: the tokens of `base.css` are a frozen API.
- A site installed from git, without `files.json`, updates from the panel with a warning: old files are not deleted,
  changed files are not recognized.

## Notes

```
owner:   php tools/release.php note --level warn --ver "<8.0.2" --until 2026-12-31   (every site below 8.0.2)
         php tools/release.php note --level info --sid <sid>                           (one site)
         -> text ru and en -> php tools/sign.php --note <id> -> php tools/release.php publish --note=<id>
master:  Master -> Оповещения: accept (signature checked), withdraw
client:  its notes in the answer of master/check -> signature -> banner
```

- The signed bytes: the tag `SLAED-NOTE-v1\n`, then id, level (`info`, `warn`, `danger`), range of versions, `sid` of
  the addressee or empty, deadline, text ru and en. The `sid` inside the signature means a personal note cannot be
  passed to another site.
- `note` writes `storage/release/notes/<id>.json` (the id is the time of writing; the text is asked up to a line with
  one dot), `sign.php --note` writes `<id>.sig`, `publish --note` puts the pair into `inbox/notes/`. Every file sent
  by SFTP is downloaded back and compared by SHA-256.
- Plain text only, escaped on output, up to 1000 characters per language; a link is shown only when it leads to
  `https://slaed.net/` or `https://github.com/SLAED-CMS/`, otherwise it prints as text. A panel not in Russian shows en.
- A site refuses a wrong signature, another `sid`, a version outside the range and a passed deadline, and never shows
  an id twice. "Скрыть" is remembered on the site and never told to the master. A note the master withdrew vanishes
  with the next check, an expired one by itself. There is no back channel: a site writes nothing to the master but its
  daily check.

## The Master

The module `master` runs on slaed.net only. It is open in the repository, a fork needs it for its own master, but it
is `export-ignore`, so it never reaches a site through an archive; the owner uploads it by SFTP and installs it
through "Модули" (`modules/master/sql/table.sql`). The updater never touches it, since `files.json` names none of its
files. Signature and manifest format come from the class `Update` of the core; the master keeps no copy.

```
modules/master/
  api.php       the service: check, file (both auth public, GET)
  common.php    handlers, acceptance from inbox/, serving, the jobs
  scheduler.php the jobs masterclean and masterwatch, their rows and handlers
  admin/        the panel, tabs through getTplAdminTabs(); admin/lang with the name _MASTER
  lang/         the name _MASTER for the site context
  sql/table.sql _master_site, _master_stat, _master_release, _master_note
```

**`master/check`.** A site sends `sid` (32 lowercase hex, type `hex` of `ApiInput`), `ch` (channel), `kseq`, `v`
(version), `php` (`X.Y.Z`), `db` (`mysql` or `mariadb`) and `dbv` (`X.Y.Z`), `ext` (bits: 1 `sodium`, 2 `zip`, 4 `phar`
with `zlib`), `ep` and `es` (the count of PHP and database errors since the last check), `host` (the domain, type
`host`: lowercase punycode labels with a dot and a letter zone, so `localhost` and an IP answer 422; empty or absent,
the master erases the stored one), `fv` and `fs` (version and step of the last failed installation, sent once). Only
`sid` and `v` are required. The master updates the row of the site and answers `{manifest, keys, notes}`: the
manifest `{body, sig}` or `null`, the key statements above the sent `kseq` `{kseq, body, sig, psig}`, the notes in
force for this site `{id, body, sig}`; every `body` is the signed bytes in base64.

- **Which manifest.** The highest release in `beta` or `stable` above the version of the site, in its channel (a
  `stable` site takes `stable`, a `beta` site `beta` and `stable`), in its share and not below `min`. A site outside
  the share gets the lowest one that fits. The share is `crc32(sid.'|'.version) % 100 < share`, so every release has
  a first cohort of its own, not the same sites every time.
- **`master/file?v=8.0.1&kind=full|delta`** serves the archive from `storage/update/master/8.0.1/` through
  `getFileStream()` in the cache mode `private`: resume, HEAD, ETag. A paused or unaccepted release answers 404. The
  action only reads (a `public` action of `.rules/api.md`): downloads are not counted; an installation shows in the
  version of the next check.
- `v` is strictly `X.Y.Z` and `kind` only `full` or `delta`, checked by `ApiInput` before the handler, so a version
  never becomes a path outside `storage/update/master/`. Both actions are limited per address by `checkApiFlood`
  before any query.

**The panel.**
- **Выпуски:** the list of `inbox/` -> "Принять" (both signatures and the SHA-256 of the archives, by the same code as
  the client; move into `<version>/`) -> "В beta", "В stable", "Пауза", "Доля %". A pause shows who set it, the owner
  or the automatic pause with its figures. Accepting a version again (after `--refresh`) replaces the manifests and
  keeps the state.
- **Сайты:** domain (when told), `sid`, version, channel, PHP, database, extensions, errors, first and last visit,
  the last failed installation; search by domain; silent sites. The list is never shown outside or exported. A row
  carries the hint how to send this site a personal note.
- **Статистика:** per day versions, channels, PHP, databases, the share without `sodium`/`zip`, errors per version
  (the signal to stop a rollout), failed installations per version and step.
- **Оповещения:** accept from `inbox/notes/`, the notes in force, "Снять".
- **Ключи:** fingerprints of the factory keys and of the keys in force, `kseq`, the statements; read only.
- **Настройки:** the retention of silent sites (180 days) and of daily totals (730 days), the threshold of the
  automatic pause (5 %). `config/master.php` is written by the master only and is not in git. Saving the settings
  writes the two jobs into the scheduler.
- **The journal** is the link of the header, not a tab: the entries of this module in `admin.log`, where
  `checkAdminPost()` records every action.

| Table | Holds | Key |
| --- | --- | --- |
| `_master_site` | the last state of a site: `sid`, domain (empty: not told), version, channel, PHP, database, extensions, errors, first and last visit, the last failed installation (version, step, time) | PK `sid` |
| `_master_stat` | the daily total: day, channel, version, PHP, database -> number of sites, sum of errors, failed installations | PK over every dimension |
| `_master_release` | version, state (waiting, beta, stable, pause), who paused (owner or automatic), share, time of acceptance and change | PK version |
| `_master_note` | id, addressee (`sid` or empty), range, level, deadline, state, signed bytes and signature | PK id |

**Jobs.** `masterclean` once a day writes the daily total and deletes sites silent longer than their retention and
old totals. `masterwatch` once an hour is the automatic pause: for every release in beta or stable it counts, since
its acceptance, the failed installations (sites whose last failure is on this version and that still report the
previous one) and the successful ones (sites that report this version); a site that failed and later installed counts
only as success. At 5 failures or more with a share above the threshold, the release is paused with the mark
"автопауза", the owner gets a mail at `adminmail` of slaed.net with the version, the figures and the most frequent
steps, and `admin.log` gets an entry. The job never lifts a pause; only the owner does. Both jobs are declared in
`modules/master/scheduler.php` alone (owner, 2026-10-18): the core collects the `scheduler.php` of the modules
(`getSchedulerModuleJobs()`), runs a job while its module is active and answers "Отключено" while it is not
(`addSchedulerModuleJob()`), and names no job of the master itself. Saving "Настройки" writes the two rows into the
scheduler of slaed.net; the shipped `config/scheduler.php` holds neither.

## The Client

- **The check.** The job `updatecheck` runs once a day at the minute of the day `crc32(sid)` gives, so the sites of one
  host do not reach the master at once; `setup.php` writes the identifier and the cron line with its minute. With the
  check switched off only the button "Проверить" asks. A master that does not answer is asked again the next day.
  The one external request goes to `Update::HOST` only, HTTPS only, without redirects, the answer limited to 1 MB.
- **The identifier** is 32 random hex characters with the SHA-256 of `homeurl` beside it in `state.json`: a site copied
  to another address gets a new identifier by itself, and a new identifier rewrites the minute of `updatecheck`.
- **The domain** is the host of `homeurl` in lowercase (IDN in punycode), without a path; `localhost`, an IP and the
  zones `.local`, `.test`, `.localhost` are never sent. It is sent by default, for support, personal notes and the
  picture of live installations; switched off, the master erases it with the next check.
- **Error counts** are numbers only, counted at the check from the journals of `Logger` (channels PHP and database)
  since the last check; no text and no path ever leaves the site.
- **The banner** on the start page of the panel and on "Обновления": "Доступна 8.0.1", red with the flag `security`.
  Every word is a language constant; only the version and the flags come from the answer. The link to the release is
  built from the number by the fixed pattern `https://github.com/SLAED-CMS/SLAED/releases/tag/v<version>`.
- **The page "Обновления"** (`admin/modules/update.php`) is a module of the core, like "Планировщик": always there,
  never switched off. "Обзор" holds the version, the last check, the version found, the `sid` with "Скопировать" (the
  owner of a site names it to support) and the buttons "Проверить", "Скачать", "Загрузить архив", "Проверить
  установку", "Установить", "Вернуть предыдущую версию", "Применить шаги базы", "Проверить целостность"; "Настройки"
  the channel, the switch of the check with the list of what is sent and where, `keepbak`, the switch of the domain;
  the journal is the link of the header into `admin.log`.
- **Settings** are `update.check` in `config/update.php`: `upcheck` (1), `channel` (`stable`), `keepbak` (1), `upsite`
  (1); `update.steps` holds the marks of executed database steps. The file is not shipped, so the merge never touches
  it; `setup.php` writes it whole, beside the feature marks (`fields`, `points`, `ratings`, `node`) it already held.
- **A pause withdraws the offer** from sites that already saw it: an answer without a manifest or with one below the
  version found resets the version found in `state.json`, and the banner goes out. "Загрузить архив" asks the master
  again first and accepts an archive only under the manifest the master serves now; without the master the upload by
  hand is closed with an explanation. So a paused release cannot be installed even from an archive of GitHub.
- **Download** runs under `work.lock` through `master/file`, at most `Update::SPAN` = 40 s or half of
  `max_execution_time` per request, and resumes by `Range`; a server without `Range` starts over. The archive is
  checked by size and SHA-256, its central directory is read by `Update` itself, and it unpacks into `stage/` through
  `ZipArchive` or `PharData`; the files are held against `files.json`, and the archive is deleted after unpacking.
- **Without `sodium`** the class `Signature` verifies (a port of `crypto_sign_open` of TweetNaCl, public domain, with
  the refusals of libsodium: S not below L, a non-canonical key, points of small order of the key and of R); it is
  loaded only when the extension is missing and answers "invalid" on 32-bit PHP. **Without `zip`** the content comes
  from the stream `phar://` of every entry; `PharData` needs `phar` and `zlib`. With neither `zip` nor that pair the
  installation from the panel is closed and the page explains the manual path. "Обзор" names which path the site takes.
- **"Проверить целостность"** compares the hashes of the shipped files with `files.json` and lists PHP files in
  `admin`, `core`, `lang` and `public` that the list does not name (`Update::AUDIT`) — a detector of a break-in. The
  panel entry renamed by the installer (`$conf['security']['afile']`) is compared with the entry `public/admin.php`
  of the list, and a `public/setup.php` deleted by the installer is no missing file.

## Settings, Languages and Themes

An installation keeps what the site saved and brings what is new; at runtime nothing changes: `getConfig()` reads only
`config/`, `getLang()` only `lang/`, there is no second layer.

| File | Unchanged on the site (hash = old `files.json`) | Changed on the site |
| --- | --- | --- |
| `config/<name>.php` | always a site file | missing options are added |
| `public/robots.txt` | always a site file | put only when absent |
| `lang/`, `admin/lang/`, `modules/<module>/lang/`, `modules/<module>/admin/lang/` of shipped modules | replaced whole | replaced whole, the old file in the backup of the installation |
| `public/templates/admin`, `public/templates/lite` | replaced | replaced, the old file in the backup of the installation |
| other themes `public/templates/<name>` | untouched | untouched |
| all other code | replaced | installation stops until "перезаписать" is ticked |

- **The merge** (`Update::getConfigMerge()`, `addConfigMerge()`) adds every key the site lacks at any depth with the
  value of the delivery. An associative map merges recursively; a list (`array_is_list()` of the delivered value) is one
  value and never merges by index. The sections a site creates and deletes itself are taken whole: a Node type section
  (`node.types.<type>`, `fields.node.<type>`, `uploads.<type>`, `ratings['node.<type>']`) present on the site is the
  site's, an absent one is never added, so a deleted type never returns; a field set `fields.<set>` present is the
  site's, absent it is added whole; so are the pages and the menus of the layout (`layout.pages`, `layout.menus`), so a
  page a site deleted never returns, while a new key of `layout.theme` is added. Everything else, `modules.modules` and
  `scheduler.jobs` included, merges
  recursively: a new module and a new job of a release are added, a new field of a record goes into every record. A
  new Node type reaches sites only by a PHP step of its version: without its row in `_node_types` it is no type.
- `setConfigFile()` writes as a string the ordinary files, in its Closure form `node`, `fields`, `uploads`, `ratings`
  and `layout`, which keep their native types; a site without the file gets it whole. Like every save of the panel, the string form keeps scalars as strings, so
  readers take numbers through `intval()`. `config/system.php`, `header.php` and `chmod.php` are code, not settings,
  and follow the row "all other code".
- **Renaming, changing the meaning or deleting an option** is a PHP step of the version through `setConfigFile()`.
- **The shape of the layout** carries its version `v` (`Layout::VERSION`). `Layout` reads a file of another version as
  an empty layout, so a release that changes the shape brings a PHP step that moves `config/layout.php` of every site to
  it, through the Closure form, as a step moves any stored data; the merge never does it.
- **Languages and themes** are replaced whole, so the wording of a release reaches every site; edits of an
  administrator in these files are lost on update (the old files lie in the backup of the installation), and the
  pages "Языки", "Шаблоны" and "Стили" warn about it (`_UPDATE_LANGS`, `_UPDATE_THEMES`). They never stop an
  installation (`Update::isSwapFile()`, `Update::THEMES`). A third-party theme that lacks a fragment a new core
  expects is the concern of its author.
- **The manual path** (FTP) is the site owner's responsibility: a `config/` uploaded whole overwrites the settings of
  the site with the delivery, and without `config/` no new option is added. That is why code always reads a new
  option with a fallback (`$conf['section']['option'] ?? …`): a site without it works as it did before.
- After an installation `config/local.php`, the compiled cache of `getConfig()`, is deleted and built again.

## Database Steps Between Releases

A step of a version is a file in `storage/update/step/` (`8.0.1.sql`, when needed `8.0.1.php` for data). `Update`
runs the steps in the order of versions; the mark of a done step is `update.steps` in `config/update.php`. Every version
gets its mark, one without a step file too, otherwise "Применить шаги базы" would hang forever. `setup.php` marks every
step of its own version done, so a step that changes the schema changes `storage/update/sql/table.sql` in the same
release — the rehearsal compares both schemas. Database and PHP steps run before the merge of `config/`: a PHP step
that renames an option must run before the merge adds it under the new name with the delivered value.

**The light mode `UPDATE_FILE`.** A new core on the old schema can fail at boot when it reads a new column, so steps
never run under a full boot. The mode is built like `API_FILE`: the core without theme, visitor session, modules,
blocks, `Point`, `Rating` and every other boot query on tables a step may change. The administrator is checked by one
query on the table of administrators, whose schema changes only compatibly; an expired cookie gets the own login form
of the mode, which checks against the same table, counts failures like the panel form and sets the cookie the normal
login sets — as WordPress does with "Database Update Required". The mode lives in `admin/update.php` on the card of
the theme `admin` (`setCardPage()`, `getCardLinks()` in `core/admin.php`).

**Who runs the steps.** An installation from the panel runs them itself, in this mode, on step 6. The manual path
(files uploaded by FTP or pulled from git): while `SL_VERSION` is above the last mark, `checkSiteGate()` closes every
entry with a static 503 before the core — `/api/` and the pseudo-cron included, so the scheduler is silent — and only
the light mode of `public/admin.php` passes. It shows "Применить шаги базы" with a backup taken on the closed site by the
same portion dump of the tables of the site that an installation takes on step 4, into `storage/backup/steps/<version>/`
(after the steps, renamed with the time); a backup that did not fit into one request continues with the next click, and
a backup is not taken again after a failed step. After the steps the mode clears `config/local.php`, the caches of pages
and assets and OPcache, as step 7 of an installation does, and the site opens by itself. `public/update.php` takes no
part and does not ship.

## Storage

```
storage/update/
├ sql/              shipped: the schema table.sql for setup.php
├ step/             shipped: the database steps 8.x
├ files.json        shipped in the archive: the list of files with hashes
├ client/           on every site; gitignored; written by the core only
│ ├ keys.json       trusted keys, kseq, revoked
│ ├ state.json      identifier, hash of homeurl, last check, freshest time per channel,
│ │                 version found, unpacked package, last fault, result of the integrity check
│ ├ notes.json      accepted and hidden notes
│ ├ work.lock       the lock of download and unpacking
│ ├ tmp/            the downloaded archive; deleted right after unpacking
│ ├ stage/8.0.1/    unpacked and verified
│ ├ run/apply.php   the executor of the new package while an installation runs
│ ├ apply.run       the flock queue of executor and rescuer
│ └ apply.lock      an unfinished installation
└ master/           on slaed.net only; gitignored; written by the module master and by SFTP
  ├ inbox/          sent by the owner, not accepted yet (releases, notes/, keys/)
  └ 8.0.1/          slaed-8.0.1.zip, slaed-8.0.1-from-8.0.0.zip, manifest-beta.json,
                    manifest-stable.json, signatures
storage/backup/update/8.0.0-8.0.1/   the backup of an installation: files, config/ before the merge, database dump
storage/backup/steps/<version>/      the dump before the steps of the light mode
storage/release/                     the owner's machine only; gitignored: builds, rehearsal.json, notes/, keys/
```

An archive that holds anything under `storage/update/client/` or `storage/update/master/` is refused whole.

## Installation

Every phase is its own short request: the database backup in portions, the files in batches, the steps one by one. The
course is written into `apply.lock`, the page shows progress, and a request cut by the time limit of a host continues
with the next batch. `apply.lock` is created atomically (`fopen(…, 'x')`): a second installation and a double click
are refused. While it lies, the scheduler starts no job, neither by cron nor by the pseudo-cron (`addSchedulerRun()`).
An installation never starts by itself.

1. **Pre-check, the site still open** (`Update::getApplyCheck()`, its first refusal is its error): signature and hash
   were already verified; PHP not below `php` of the manifest; the version of the site not below `min`; write access to
   every target file and folder; space by the calculation below; the files changed on the site — the administrator
   ticks "перезаписать" explicitly, judged by the files as they are now, not by the list of unpacking; OPcache: when it
   is on with `validate_timestamps` = 0 and `opcache.restrict_api` forbids `opcache_reset()`, the installation from the
   panel is closed with an explanation, otherwise step 6 would run old bytecode (`getOpcacheState()`); the rights of the
   database (`Backup::checkDumpAccess()`) and the fixed list of the tables of the site, found now and not on step 4,
   when the site is closed.
2. **Rollback code**, 20 hex shown once in five groups of four, before the start.
3. **Close:** `apply.lock` is created with the hashes of the token and the code (phase `prep`), the executor of the
   package is copied into `client/run/apply.php` and held against the hash of the new `files.json`, and only then is
   the backup of the previous installation deleted, so a second window cannot erase the backup of another. From now on
   the gate answers 503 to everything but the requests of the installation and of the rollback.
4. **Database backup** by the portion mode of `Backup`, by the old core while it is still whole. A failure here touched
   neither files nor database: the incomplete dump is deleted, `apply.lock` removed, the site opens, the package stays
   for another attempt.
5. **Files** (`run/apply.php`, without the core): in batches by renaming — the old file into the backup, the new one from
   `stage/`; deleted files into the backup; the entries `public/*.php`, then `core/gate.php` and `core/apply.php` last,
   `core/rescue.php` untouched; then `opcache_reset()` and, with `validate_timestamps` = 1, a pause of
   `revalidate_freq` seconds. The `public/admin.php` of the package lands on the panel entry `public/<afile>.php` of the
   site, a `public/setup.php` the installer deleted does not return, an identical file is not touched.
6. **The new core in the light mode:** `config/` without `local.php` is copied into the backup; the database and PHP
   steps in order, then the merge of `config/`.
7. **Cleanup:** `config/local.php` and the caches; `stage/` stays until step 8.
8. **Open:** when the package holds `core/rescue.php` (a delta lacks it when it did not change), it takes its place by
   renaming and the previous one goes into the backup; then `stage/` and `run/` are deleted, `apply.lock` removed, the
   result written. The backup stays until the next installation.
9. **Self-check** at the first opening of "Обновления" after the installation: the site asks itself for `/` and
   `/api/v1/system/ping`. A 5xx or no answer — "Сайт не отвечает" and the offer "Вернуть предыдущую версию"; the
   version is not rolled back by itself, the administrator decides. A host that does not let the site reach itself —
   the page asks to open the site and press "Всё в порядке" or "Вернуть предыдущую версию".

**"Проверить установку"** runs the pre-check of step 1 over the unpacked `stage/` and shows which files are replaced,
added and deleted, which were changed on the site, which options of `config/` are added, which steps run and how much
space is needed; it closes, writes and moves nothing, and names the same list the installation then applies.

**Space.** All amounts are known beforehand: `size` and `usize` from the signed manifest, the rest from disk and
database.

| Symbol | What | Source |
| --- | --- | --- |
| Z | archive | `size` of the manifest |
| U | unpacked package in `stage/` | `usize` of the manifest |
| D | database dump | `data_length` of the tables of the site from `information_schema` × 1.2 |
| B | old files that move into the backup | `filesize()` of the overwritten and deleted files |
| N | new files | the files in `stage/` |
| P | backup of the previous installation | the size of its folder; deleted at the start of the new one |
| R | reserve | 10 % of the need, at least 50 MB |

- "Скачать": free ≥ Z + U + R. "Установить": free + P ≥ D + R on one disk; on different disks (`stat()['dev']` of
  `stage/`, `storage/backup/` and the tree) the files are copied instead of renamed, and free + P ≥ D + B + N + R.
- The page says it in words: "Нужно 140 МБ, свободно 900 МБ; бэкап прошлой установки 60 МБ будет удалён". When it does
  not fit, the button is closed and the page says how much to free.
- The calculation can be wrong (a quota below what `disk_free_space()` sees, a dump above the estimate), so the space is
  checked again before every batch and every portion, and any failed or incomplete write is an error: the installation
  stops and rolls back. A rollback renames and needs no space; restoring the database needs space on the database
  server only.
- `disk_free_space()` disabled on the host: the calculation shows without "free", the installation requires the tick
  "место проверено вручную", and only the handling of write errors guards the course.

**The gate of every entry.** `public/index.php` (with the branch `/api/`), `public/admin.php` and `public/setup.php`
each call `getSiteGate()` of `core/gate.php` first, right after the folder constants, and `require` the file it names at
the top level, so the new core that the executor loads on step 6 keeps its globals. While `apply.lock` lies:
`rescue.now`, the field `rescue` of the form, `?rescue` or a started rollback with the right token -> the installed
`core/rescue.php`, past the new executor, so a rollback works with a bad package; the field `apply` whose SHA-256 equals
`token` of the lock and the copy `run/apply.php` with the hash `run` -> the executor; everything else and any `/api/` ->
the static 503 of `public/error.html` with `Retry-After`, without the core. `$conf['close']` is not used: it needs the
core, and an administrator in `admin.php` over a mixed core would end in a fatal error or in mixed code. Without
`apply.lock` the same gate, `checkSiteGate()`, closes the site while the code is newer than its steps. Both calls stay
first in every entry of every version (`.rules/architecture.md`).

**Contract version 1** (`Update::APPLY` = 1, `Backup::FORMAT` = 1), frozen in 8.0.0. The executor comes from the new
package, while the old version stays in two places: on step 4 the executor calls `Backup` of the old core, and a failure
on steps 5–8 is rolled back by the installed, that is old, `core/rescue.php`, which from step 5 on is called by the new
gate. So these are version 1:

- **The call.** The gate calls the executor and the rescuer by one `require` without arguments; both read everything
  else from `apply.lock` and share one `flock` queue on `client/apply.run`. The token is 64 hex in the POST field
  `apply`, the code 20 hex; the server holds only their hashes.
- **`apply.lock`:** `ver`, `from`, `to`, `kind`, `time`, `token`, `code`, `entry` (name of the panel entry), `run`
  (SHA-256 of the executor copy), `back` and `stage` (paths from the project root), `prefix`, `tabs` (the fixed list of
  the tables of the site), `phase` (`prep`, `dump`, `files`, `core`, `open`, `roll`), `dump` (the state of the portion
  dump), `at` and `upto` (moves of the plan done and the end of the current batch), `conf` (the copy of `config/` is
  taken), `base` (steps touched the database), `roll` — `null` or `{why, step, error, part, rest, at, busy}`: the
  reason (`files`, `space`, `config`, `steps`, `merge`, `fatal`, `open`, `code`, `now`, `revert`), the step, the text,
  the part of the rollback (`stop`, `base`, `conf`, `files`, `clean`, `done`), the state of the `Backup` restore, the
  moves left to undo, the flag of a part at work.
- **The backup of an installation** `storage/backup/update/<from>-<to>/`: `plan.json` `{ver, from, to, moves, dirs}`, a
  move `{op: put|drop, src, path, old}` (for `put` the path in the package and the path on the site, which differ for
  the panel entry), `dirs` the folders the installation created; `files/<path>` the old files that moved out; `config/`
  the `config/*.php` without `local.php` before the merge; `dump/` the portion dump. The move of the rescuer on step 8
  is appended as the last move.
- **The entry of the portion dump** — static methods of `Backup` on a `PDO` in `ERRMODE_EXCEPTION`, calling neither
  `Database` nor any function of the core; the class file loads by itself under `FUNC_FILE`. One call is one part; the
  state is an array the caller keeps between requests. `getSiteTables($pdo, $prefix)` lists the tables of the site:
  `{prefix}_%` without those of a longer prefix that has its own `<prefix>_admins` (a prefix may contain `_`).
  `checkDumpAccess($pdo, $tabs)` names the missing rights — `SELECT`, `INSERT`, `CREATE`, `DROP` on the schema,
  `REFERENCES` on MySQL with foreign keys — from `SHOW GRANTS`, reading the database name as a LIKE pattern
  (`user\_db`, `user\_%`), without writing. `getDumpState($dir, $prefix, $tabs, $pack)` opens a dump in an empty folder
  (`gz`, `none` without `zlib`); `addDumpPart($pdo, $state)` writes the next part `000001.sql.gz`: one table at most and
  about 4 MB of SQL per part, the first part of a table starts with `DROP TABLE IF EXISTS` and `SHOW CREATE TABLE`,
  `INSERT`s up to 1 MB, a table with one integer key read by key (`last`), the others by offset (`skip`) ordered by the
  key or by all columns; a part is written under a temporary name and renamed, the space checked before each. The last
  part writes the index `dump.json` — format, prefix, packing, tables, parts with table, rows, size and SHA-256, the
  total of rows; with it the dump is complete, without it not. `getRestoreState($dir)` checks the index;
  `addRestorePart($pdo, $state)` checks the hash of a part, unpacks and runs it. Part 0 first drops every table of the
  prefix by the same rule: the tables of the list come back with their parts, the others were created after the list
  was fixed and do not return — which also frees constraint names a step may have moved to a new table. `head` in the
  state is the part where the current table starts: a caller that does not know whether its last call landed continues
  from `head`, and the table is created again without duplicates. A state or index of a foreign format, a table of the
  list without parts or with parts out of order, a damaged part, a finished dump in the folder, a prefix outside the
  grammar, a connection without exceptions — each is a refusal with its reason. The mode needs no InnoDB: the site is
  closed, so the closing keeps consistency, not a snapshot. `FLOAT` and `DOUBLE` are written in their shortest exact
  form (`var_export()`).

A new executor writes `apply.lock` and the backup only so that the rescuer of 8.0.0 reads them: new fields are only
added, and the old rescuer skips them. The dump is understood by the old rescuer by itself, since the `Backup` of the
old core writes it. The reverse demand binds the new rescuer: after step 8 it is in place, and "Вернуть предыдущую
версию" restores a dump the old `Backup` took, so it reads the dumps of every earlier version of the format. To change
the contract is to release version 2 and read version 1 forever. This is the part of 8.0.0 that no later release can
fix; tests and review are strictest here.

**Rollback.** A failure on steps 5–8 rolls back by itself: the database (only with `base`), `config/` (only with `conf`;
files created after the copy are deleted) together with the marks `update.steps` — otherwise a repeated installation
would skip a step on the returned old schema —, the moves of the plan in reverse order, the folders `dirs`, then the
caches. A move is undone without knowing whether it landed: an old file in `files/` returns, no old file and the file was
new — the new one is deleted. The version and the step of the failure go into `state.json` and to the master with the
next check. Every rollback — automatic, emergency and "Вернуть предыдущую версию" — ends by `opcache_reset()`, deleting
`config/local.php` (the new core may have built it with the asset lists of the new theme) and the caches of pages and
assets.

**Emergency rollback.** When the core does not boot after a fault or the executor is broken, the gate hands the request
to `core/rescue.php`: one page with the field "Код отката". The right code, or an empty file
`storage/update/client/rescue.now` put by FTP — access to FTP already proves the owner — returns files, `config/` and
the database from the backup; the code is single use, and `rescue.now` is deleted after the rollback. The rescuer reads
`config/db.php` directly and restores the database in portions, one request per portion. The code also continues a
broken installation under a new token, so a closed browser window never leaves the site closed. The pages of the
executor and the rescuer are English, without the core, in the shell of `public/error.html`.

**"Вернуть предыдущую версию"** is offered while the backup of the last installation lies (`keepbak` = 0 deletes it
right after a successful installation): the same rollback of the whole plan returns files, `config/` and the database,
after warning that changes of the database and the settings since the installation are lost. The next installation
replaces the backup with its own; after a successful installation the backup takes B + D, shown beside `keepbak`.

## What a Site Refuses

Before unpacking: a wrong signature of the manifest or of the archive; a release signed by the recovery key; a foreign
channel; a version not above the installed one; a `time` older than one already seen; a size or SHA-256 that differs; an
archive above its signed size (the download stops). On unpacking, by its own reading of the central directory before
the reader (`ZipArchive` or `PharData`) opens a single entry: an absolute path, `..`, a backslash, a drive letter, a
symlink (by the Unix mode), encryption, ZIP64, a twin path that differs by case only, a path outside the shipped top
folders (`Update::DIRS`), a path under `storage/update/client/` or `master/`, an unpacked size above the signed `usize`;
and on the way an entry that yields more bytes than its directory entry declares. The directory is read by `Update` for
both readers because `PharData` cannot be checked by its names: it silently skips `../x` and `/x`, loses the mark of a
symlink and throws in the middle of a walk on a backslash. Files get 0644, folders 0755.

## Rehearsal

`tools/rehearse.php v8.0.1 [--keep]` gates every publication: `release.php publish` refuses without a green
`storage/release/<version>/rehearsal.json` holding the SHA-256 of the same archives (`checkRehearsal()`), and
`sign.php` points to it after signing.

- **Clean:** the new full archive is installed through its own `setup.php` into a disposable folder and database, as a
  new site takes it: the site answers, its integrity is clean, and its schema is the one every update must reach.
- **Full and delta:** each archive gets its own run on a fresh site — the full archive of the previous release installed
  clean by its own `setup.php`; the new archive and its signed manifest go to `Update` of that site the way "Загрузить
  архив" gives them, so the site verifies the signature itself; the panel of the site runs "Проверить установку",
  installs, runs the self-check and "Проверить целостность", compares the schema with the clean run (columns, keys,
  engine, regardless of column order), and goes back by "Вернуть предыдущую версию" to the byte in files and schema.
  Non-empty journals of PHP or database errors make a run red. A database step without the same change in `table.sql`
  shows here.
- It runs on the built-in PHP server in a disposable folder and database on the server of `config/db.php` of the tree,
  every child process without OPcache; the site of the tree and its database are not touched, the scheduler of the copy
  is off, so the master never sees it. The previous full archive is `storage/release/<previous>/` when its hash matches
  the signed manifest, otherwise the one of the GitHub release (`gh release download`). The first release with an
  updater has no previous one and ends after the clean run.
- The rehearsal goes past the master on purpose. The path through a real master was proven once by the general rehearsal
  of 2026-10-17: a clone under fresh keys with `Update::HOST` on a second OSPanel project `master.loc`, the site copy on
  `rehearse.loc`, each with its own domain on nginx and php-cgi with OPcache and the HTTPS of the stand, releases 8.0.0
  -> 8.0.1 -> 8.0.2 with a key change, a pause, the rescuer of 8.0.0 under the executor of 8.0.1, a failure on step 6, a
  foreign prefix in the same database, the manual path, forgeries, a broken download, missing rights, the fallbacks
  without `sodium` and without `zip`, a delta and a full archive, a personal note and the return after the next
  installation. What it did not cover: running out of space on a real disk; the same rollback path was proven by a
  failed move in the middle of a batch.

## Moving slaed.net to 8.0

`slaed.net` runs a development build of early October 2026, not a release, and holds data newer than any copy, so it
is neither installed anew nor updated by the updater: its own database is brought forward by `public/update.php`
(`docs/NODE.md`, "A site of an early 6.3 build") and restored on the host. The rehearsal of 2026-10-26 proved the
whole path on the production dump of 2026-10-25; the move repeats it on a dump taken with the site closed, so no row
written before the closing is lost.

**The copy.** The run happens in a copy of the tree over a disposable database, never on the stand: `update.php`
writes the settings of the site into `config/`, whose files the stand tracks.

1. A local `git clone` of the committed tree: `git archive` drops `public/update.php` and `table_update6_3.sql` by
   `export-ignore`. `modules/master/` and `modules/changelog/` are taken out, as the release archive leaves them.
2. The shipped settings in `storage/backup/release/config/` in the form of the release archive, `config/modules.php`
   without `master` and `changelog`: the settings step of a 6.3 site merges from that folder.
3. The production `config/` laid over `config/` without `local.php` and without the code files `system.php`,
   `header.php` and `chmod.php`; its `db.php` names the host, user, password and database of the disposable base;
   `storage/logs/` exists, or the run logs nothing.
4. The dump loaded with the prefix it carries (`sport`), then the accounts that share an address with an older one
   deleted as the owner decided for each.
5. The production `uploads/` copied in without `presentation/`, since a rewrite touches only a file the folder holds.
6. Served by `php -S` or a host of its own: a POST `op=update` with `dry=1`, then one with `op=update` alone, then the
   second stage as the administrator of the copy; a repeat of the first stage changes nothing.

The script of these steps (`build`, `run`, `deliver`, `prove`) lies with the hand-over, outside the repository, since
it names the paths and the databases of the owner's machine.

**What the run must show.** The first stage ends "The update finished" without a failed row, points, ratings and fields
done by their marks, the archive and text units with the counts of their dry run, the tables of the gone modules and
the nine source tables dropped; the second stage ends "Готово" with the addresses of every type, category, material,
topic, account and poll. The copy holds the 36 tables of `table.sql`, equal in columns, defaults, keys, foreign keys,
checks and collations (`/schema` inside the copy) and without a routine; `config/modules.php` names the modules of
the release, `global.php` has `close = 1`, and the panel file carries the `afile` of the production `security.php`.

**The hand-over** is a folder outside git, deleted after the move, since its `config/` holds the production secrets:

- the dump of the copy made by `Backup`, in the format the restore of the core reads, proven by a restore into an
  empty database: every table equal to the copy by definition, rows and `CHECKSUM TABLE … EXTENDED`. Production runs
  MariaDB 12.3; a stand without 12.x proves the restore on its own version, and the report names the gap;
- the `config/` of the copy without `local.php`, its `db.php` the production one with the collation of the release;
- the folder moves of `docs/NODE.md`, "A site of an early 6.3 build", checked file by file on the stand;
- the reports of both stages, the order below and a README in Russian.

**The order on production:**

1. Close the site.
2. Take a fresh dump and `config/` of production and put them through the copy and the run above.
3. Upload the release files; the `admin.php` of the release lands under the name of the panel file of the site, and
   no `admin.php` stays.
4. The document root on `public/`, with `uploads/`, `config/` and `storage/` at the project level (`UPGRADING.md`,
   "The Document Root Is `public/`").
5. Move the folders of `uploads/` by the list.
6. Lay the delivered `config/` over the old one.
7. Restore the dump.
8. Delete `config/local.php` and the caches.
9. Read the journals, then open the site (`close = 0` in the settings).

**The stand** takes the database of the copy, never its `config/`, which stays the development one: the stand is dumped
first and the dump read back, then dropped and loaded; `storage/cache/` and `config/local.php` are cleared afterwards. A
type whose version in the development `config/node.php` is ahead of its row is invalid and answers 404, so the row is
raised on the stand alone and the deliverable keeps the one of production.

**The live check** on the stand passes before the hand-over leaves, through real HTTP with the session of the stand:
every Node type that holds materials and every module that stays (account, contact, forum, recommend, search, sitemap,
users, voting, rss), the blocks, the comments, the private messages, the signatures and the own blocks, on the site and
in the panel. It passes when no page shows a BB tag, a visible tag or a leaked escape, the editor of every form holds
the stored text and a save without a change keeps every byte, a new comment, forum post and private message renders
like the converted ones, and the console, the failed requests and the three error journals stay empty. A change of
`update.php` or of the parser before the move repeats it.

## Statistics and Privacy

**What leaves a site.** Only the parameters of `master/check`: `sid`, channel, `kseq`, version, PHP version, database
kind and version, three bits of extensions, two error counts, the domain unless switched off, and once the version and
step of a failed installation. No error text, no path, no content, no data of a user. The request goes to `slaed.net`
only. "Обновления" lists all of it, and the check and the domain each have their own switch.

**What the master keeps.** The last state of every site and daily totals (retention 180 and 730 days by default, the
settings of the module). The master does not write an IP into its database; `checkApiFlood` counts the frequency of an
address as a keyed hash in the file cache with a short lifetime, IPv6 by its /64, sized for hundreds of sites behind
one address of a host that arrive at different minutes. `master/check` carries its data in the query string, which a web
server would log beside the IP, so the access log of slaed.net for `/api/v1/master/` is written without the query
string — the sample `log_format` with the path in place of `$request_uri` is in the help of the master. A domain is a
claim of the site, not a proof: anybody can send another domain under a random `sid`. The tab "Сайты" serves support,
not accounting, and is never published or exported.

**For the owner of a site (DSGVO / GDPR).** The check sends no personal data of a visitor or a user. The domain of the
site and the technical figures above are data of the operator's own installation; the legitimate interest is security
updates and support (Art. 6(1)(f) GDPR). An operator who does not want it switches off "Сообщать домен" (the master
erases the domain with the next check) or the check itself (only "Проверить" then asks). The IP address of the server
reaches slaed.net as with any HTTPS request, but is neither stored with the data nor linked to the domain. Nothing is
sent to a third party; GitHub is not contacted by a site. A site that wants no contact at all switches the check off and
updates by "Загрузить архив" and the manual path.

**The privacy note of slaed.net** (the text for the page of slaed.net; the owner publishes it in Russian and English):

> **Update service of SLAED CMS.** Once a day a SLAED site asks `slaed.net` whether a new release exists. With that
> request it sends: a random installation identifier, the update channel, the installed version, the versions of PHP and
> of the database, whether three PHP extensions are present, the number of PHP and database errors since the previous
> request (numbers only, never their text), the domain of the site unless the administrator switched it off, and, once,
> the version and the step of a failed installation. We use this to offer the right release, to stop a release that
> fails, to answer support requests and to send a site a personal notice. We do not store IP addresses with these data
> and do not link them to the domain; the web server log of this service is written without the request parameters. The
> state of a site is kept until it has been silent for 180 days, daily totals without identifiers for 730 days. Nothing
> is passed to third parties. To stop sending the domain, switch off "Report the domain to the master" on the page
> "Updates" of your panel: the domain is erased with the next check. To stop all contact, switch off the check; to
> have the stored state of your site deleted at once, write to us with the identifier shown on that page.

**Protecting the list.** A list of domains with their versions is a target: the panel of the master stays behind a
strong password and, until the second factor of the core plan exists, `admin.php` of slaed.net is restricted by IP
on the host.

## Security

- A broken slaed.net can only hold back or switch between what is already signed. A broken GitHub account touches only
  people who download an archive by hand: "Загрузить архив" holds it against the signed manifest, and the manual path by
  FTP is the site owner's responsibility. Two-factor login on both is mandatory.
- The external requests of a client go to `slaed.net` only, HTTPS only, without redirects, the answer limited in size
  (`.rules/api.md`, `ApiHttp`). GitHub is for people: the release page and the manual download for "Загрузить архив".
- Panel actions pass `checkAdminPost()`; installation, rollback, pause and channel moves are written into `admin.log`.
- A release with test factory keys is impossible: `release.php publish` refuses it.

## Tests

| Test | Holds |
| --- | --- |
| `UpdateSignTest` | the signature and its fallback `Signature` (RFC 8032 vectors, the refusals of libsodium; `tests/Support/sign_probe.php` under `php -n`), key statements, `keys.json`, notes, the grammar of versions and package paths, the real factory keys published in README.md |
| `UpdateClientTest` | the check, `state.json`, the identifier, the domain, the error counts, the pause, notes |
| `UpdateFetchTest` | download and resume, the upload by hand, the central directory and its refusals for both readers, `stage/`, the integrity check |
| `UpdateMergeTest` | the merge of `config/` (`tests/Support/config_probe.php` mode `merge`) |
| `UpdateGateTest` | the gate of the three entries, the manual path, the light mode and its backup (`route_probe.php gate`) |
| `UpdateApplyTest` | the installation, every rollback, the rescuer, the executor of the package (`tests/Support/apply_probe.php`) |
| `BackupPartTest` | the portion dump and restore without the core (`tests/Support/dump_probe.php`) |
| `ReleaseToolTest` | build, sign, publish, notes and key statements in a scratch repository; the tools run without OPcache, since CLI processes of one user on Windows share one cache |
| `MasterTest` | the master: panel, `master/check`, `master/file`, acceptance, cohort, pause, the two jobs (`route_probe.php master`) |
| `ApiEntryTest`, `ApiRouterTest` | the branch `/api/`, the guards in their order, the envelope, the codes, the mode `file` |

`tools/api.http` holds a request for every action. Contract version 1 is the strictest part: a new rescuer must restore
a dump of an old `Backup`, and an old rescuer must roll back the `apply.lock` of a newer executor.

## Why It Is Built This Way

- **Before 8.0, not after.** A site learns of updates only by code it already has; an 8.0 without the check would stay
  blind forever, and a security fix 8.0.1 would reach only those who visit slaed.net themselves.
- **The signature is the "go".** Signed offline on the owner's machine, nothing unsigned is accepted wherever it came
  from; the master and GitHub are only couriers.
- **The executor of the new package** leads the installation, not the installed one (the opposite of `update-core.php`
  of WordPress): a fault of the installer is fixed by the next release and not by the same broken code. The rescuer is
  the installed one, so it works when the new package is bad.
- **Files move, they are not copied:** a rename on one disk takes no space and cannot stop in the middle of a file; the
  rollback renames back.
- **Two archives** — the delta for a site exactly on the previous release, the full archive for new installations and
  older sites.
- **Database steps never run under a full boot**, and the dump is the site's own tables only: on a shared host one
  database often holds several programs, and a foreign MyISAM table must neither be dumped nor block the site.
- **The light mode of the manual path takes the portion dump of the site,** not the full dump of the job `dbbackup`: that
  one took the whole database, a foreign MyISAM table left the site closed, and `dbbackup` cannot be changed from a
  closed panel.
- **The rehearsal installs the previous release clean** and never takes the database of the stand, so it is
  self-sufficient on any machine and for a fork.
- **The delivered `config/` carries no value of the stand:** no test ban in `config/security.php`, no row `master` in
  `config/modules.php`, error level 0 — they would reach every new site.
