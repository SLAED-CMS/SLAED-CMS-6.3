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
