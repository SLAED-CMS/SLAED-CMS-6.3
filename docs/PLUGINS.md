# Plugin Architecture Analysis

Analysis of how SLAED extends itself today and how an admin-managed plugin layer
could be built — without turning the admin panel into a remote-code installer.

## Summary

SLAED already has working extension mechanisms; they are just not unified. The
strongest one (the editor plugins) should be generalized rather than replaced. An
admin **management** page (enable / disable / configure) is worthwhile and
low-risk. Admin-side **installation** of arbitrary PHP is not — it is the classic
CMS compromise vector and must stay out.

## Current state

| Mechanism | Location | Discovery | Quality |
|-----------|----------|-----------|---------|
| Feature modules | `modules/` + `admin/modules/`, managed via `admin.php?name=modules` | directory scan, flags persisted in `config/modules.php` (active/menu/side/top/type) | solid |
| Editor plugins | `plugins/editors/*/` with `manifest.json` + `driver.php`, registry in `core/classes/editor.php` | manifest scan + driver class load | **best pattern** |
| Captcha provider | `CaptchaProvider` interface + `AltchaCaptchaProvider` / `NullCaptchaProvider` | config-selected provider | clean strategy pattern |
| Vendored libraries | `plugins/highlightjs`, `plugins/htmx`, `plugins/tablesort`, `plugins/system` | hardcoded references | no plugin contract |

So there is no single "plugin" concept — there are modules (features), driver-based
editor plugins, a captcha strategy, and a set of hardcoded vendor libraries.

## The pattern worth generalizing

The editor system already defines a clean plugin descriptor
(`plugins/editors/tinymce/manifest.json`):

```json
{
  "id": "tinymce",
  "label": "TinyMCE 8 Community",
  "type": "content",
  "driver": "EditorTinymce",
  "entry": "driver.php",
  "enabled": true,
  "priority": 70,
  "roles": ["admin"],
  "profiles": ["simple", "full"],
  "formats": ["html"]
}
```

A registry scans these manifests, loads the named driver from `entry`, and selects
by `type` / `priority` / `roles`. This is exactly what a generic plugin needs:
self-describing metadata + a typed entry point + a registry.

## Recommendation

1. **Generalize the manifest + driver + registry pattern.** A single registry scans
   `plugins/*/manifest.json`; the admin page renders from those manifests, the same
   way `admin.php?name=modules` renders from a directory scan. Editors, captcha and
   future capabilities then share one contract.

2. **Keep modules and plugins distinct.** Modules are content/feature subsystems;
   plugins are capability providers (editors, captcha, syntax highlighting). Do not
   merge them into one list — it blurs the architecture.

3. **Manage, do not install.** An admin "Plugins" page should enable / disable /
   configure plugins (status, version, roles from the manifest) and persist flags to
   config — exactly like the module manager. It must not upload or execute new PHP.

## Security boundary (critical for a widely deployed CMS)

Do **not** add "upload a zip → run PHP" plugin installation in the admin. For a CMS
with many installations this is the primary compromise vector. Plugins are installed
at the filesystem / deploy level (Git, FTP, package); the admin only **manages** the
already-present code by reading manifests and writing config flags. This matches the
existing module manager and the project security baseline.

## Effort vs. benefit

- **Low / worthwhile:** a read-only "Plugins" overview built from manifests
  (what is installed, version, active state) plus enable/disable. Surfaces the
  editor and captcha plugins that already exist.
- **Medium:** a `config` schema in the manifest plus a generic settings UI.
- **High / risky:** a hook/event API, dependency resolution and version
  compatibility. Only with a deliberate design — and never with remote install.

## Proposed generalized manifest (direction, not final)

```json
{
  "id": "altcha",
  "label": "ALTCHA captcha",
  "type": "captcha",
  "driver": "AltchaCaptchaProvider",
  "entry": "driver.php",
  "enabled": true,
  "priority": 50,
  "requires": { "core": ">=6.3" },
  "provides": ["captcha"],
  "config": { "active": "bool", "provider": "string" }
}
```

- `type` / `provides` let the registry group capabilities (editor, captcha, …).
- `requires` enables a compatibility check at scan time (warn, do not auto-install).
- `config` drives a generic settings form and maps to the existing config files.

## Conclusion

SLAED does not need a new plugin system — it needs to unify the good one it already
has (editors). The sensible next step is a manifest-driven registry plus a
**managing** (not installing) Plugins admin page modeled on `admin.php?name=modules`.
That keeps SLAED consistent, extensible and safe.

## Next steps

1. Extract the manifest scan/registry from `Editor` into a small reusable resolver
   keyed by manifest `type`.
2. Add manifests to the remaining `plugins/*` that should be manageable.
3. Build a read-only Plugins admin page from the manifests; add enable/disable next.
4. Defer any hook/event API until there is a concrete need and a written design.
