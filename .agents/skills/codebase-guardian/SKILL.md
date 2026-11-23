---
name: codebase-guardian
description: >-
  Protects an existing codebase while making requested changes. Use before any
  risky edit, refactor, dependency upgrade or AI-generated change when the user
  says preserve design, avoid breaking features, do not touch unrelated code, no
  regressions, or keep current functionality. Reviews scope, git diffs,
  architecture compatibility and before/after tests; can also audit a proposed
  patch without changing code.
---

# Codebase Guardian — Preserve What Already Works

## Mission
Make narrowly scoped, reversible changes to an existing application without accidental loss of behavior, design, data, or uncommitted work. This is a change-control skill, not an invitation to refactor the whole repository.

## Activation decision
- **Inspection only:** collect impact analysis and report risks; do not edit files.
- **Requested targeted change:** baseline → impact analysis → minimal patch → comparative verification.
- **Unknown or destructive request:** stop at safe preparation and request explicit approval for irreversible actions.

## Step 1 — Baseline and ownership
1. Locate repository root and record current branch and `git status --short` where possible.
2. Read `AGENTS.md`, `GEMINI.md`, project rules, architecture notes, manifests and affected test/build config.
3. Identify user-authored/uncommitted changes and protect them. Never run `git reset --hard`, blanket clean, checkout-discard, or broad formatting on unrelated files.
4. Inspect exact files and surrounding consumers: imports, props/interfaces, routes, APIs, schemas, services, auth, styles and tests.
5. Establish known behavior before editing: existing screenshots when available, test results, build state, and documented requirements.
6. Classify protected surfaces: branding, typography, spacing, navigation, existing flows, APIs, persistence shape, permissions, configuration.

## Step 2 — Change impact map
Create a brief table for substantive changes:

| Surface | Intended change | Potential breakage | Verification |
| --- | --- | --- | --- |
| UI/component | Specific requested behavior | Shared layout and responsive states | Visual/interaction check |
| API/service | Endpoint or logic | Other consumers, validation, auth | Contract/integration test |
| DB/schema | New field, query or migration | Existing records and queries | Isolated migration test |
| Build/config | Required dependency or variable | CI and deployment | Existing build/check scripts |

Do not generate a giant dependency graph for a one-line fix. Use the smallest useful level of detail.

## Step 3 — Safe edit protocol
1. Define an explicit allowlist of modules/files likely to change and why.
2. Prefer focused edits over whole-file rewrites. Keep unrelated whitespace and file ordering intact.
3. Preserve names, routes, external API contracts and existing component structure unless explicitly changed.
4. Reuse design tokens and component patterns; do not restyle a page to implement a data-only request.
5. For shared primitives, inspect all meaningful callers and consider backward-compatible defaults.
6. For dependency changes, check current versions, peer compatibility, lockfiles, CVEs and build impact.
7. For schema changes, require forward/backward compatibility and an explicit nonproduction migration test plan.
8. For renames/moves, update affected imports, routes, scripts, tests and links; avoid accidental case-sensitive breakage.
9. Keep diffs small enough to review. Where appropriate, isolate behavior changes from formatting changes.

## Step 4 — Regression checks
- Inspect `git diff --check` and changed files. Look for accidental deletions or credentials.
- Run relevant baseline tests, type checking, linting and project build; distinguish pre-existing failures.
- Compare target behavior **before/after** where actual baseline evidence exists.
- Test unaffected shared navigation, auth, critical pages, role checks, forms, layout breakpoints and API consumers as relevant.
- For React/Next.js, confirm client/server boundaries, hydration, key handling, routing and environment separation.
- For database edits, check constraints, nullability, migrations and legacy rows.
- For UI changes, compare screenshots only if screenshot/browser tools actually ran; otherwise label visual verification as not performed.
- Do not treat an unchanged build exit code as proof that business behavior did not regress.

## Red flags: stop and escalate
- Proposed deletion of data/files, downgrade of access controls, revocation of backups, or overwrite of user changes.
- Unplanned redesign, framework migration, broad dependency upgrade, shared public API removal, mass import rewrite.
- A fix that hides errors, hardcodes fake success, bypasses tests, or needs production credentials.
- An action affecting cloud resources, customer data, repository history, or live deployments without authorization.

## Recovery if a change fails
1. Capture failure output and list precisely which of **your** changes are implicated.
2. Repair the new patch or propose reversing only your own edits.
3. Do not use broad reset commands that could discard user work.
4. If a safe repair is not possible, stop modifying and document the blocker and minimal recovery plan.

## Output contract
Report: original state, change scope, files touched, evidence of preserved behavior, checks run (including failures and skipped checks), residual risk, and any approval needed. Never state 'no regressions' unless the relevant behavior was checked.
