# SLAED Engineering Principles

These principles summarize the stable project direction already reflected in the repository rules and current codebase.

## 1. Fast
- Keep hot paths simple
- Avoid unnecessary abstractions
- Prefer low-overhead solutions

## 2. Stable
- Preserve behavior unless the task explicitly changes it
- Prefer incremental changes
- Verify touched areas after each meaningful step

## 3. Effective
- Reuse semantics, not accidental duplication
- Fix root causes when they are clearly identified
- Keep data flow understandable

## 4. Productive
- Keep structure predictable for contributors
- Prefer direct contracts over temporary compatibility layers
- Keep documentation aligned with the code

## 5. Secure
- Read input through `getVar()`
- Use prepared statements
- Validate CSRF tokens on state-changing POST handlers
- Escape output at the correct boundary

## 6. Self-contained
- The CMS must work as soon as its files are copied to a server
- No runtime Composer dependency, package manager step, SDK or framework
- `composer.json require` stays limited to the PHP version itself; tooling lives in `require-dev` and never ships
- Front-end dependencies are vendored under `plugins/`, never loaded from a CDN
- Implement protocols against their specification rather than pulling in a library
- Bundled PHP extensions may be used, but a missing one degrades a feature with a clear message instead of breaking the site
