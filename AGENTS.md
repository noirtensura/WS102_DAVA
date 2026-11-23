# Engineering Skills & Standards (`AGENTS.md`)

Every file created or modified in this workspace MUST follow the specialist engineering skills installed under `.agents/skills/` (and globally under `~/.gemini/config/skills/`):

| Skill | File Location | When Applied |
| --- | --- | --- |
| `fullstack-engineer` | `.agents/skills/fullstack-engineer/SKILL.md` | Multi-layer orchestration, vertical slices, cross-layer acceptance & completion reporting |
| `fullstack-web-engineer` | `.agents/skills/fullstack-web-engineer/SKILL.md` | Any web feature, route, form, controller, DB integration, or end-to-end flow |
| `codebase-guardian` | `.agents/skills/codebase-guardian/SKILL.md` | Every edit to existing files: baseline inspection, impact map, minimal diff, zero regressions |
| `frontend-engineer` | `.agents/skills/frontend-engineer/SKILL.md` | HTML/CSS/JS views, semantic markup, accessibility, responsive layout, glassmorphic design tokens |
| `backend-engineer` | `.agents/skills/backend-engineer/SKILL.md` | PHP actions/APIs, server-side input validation, RBAC/IDOR checks, transactions, safe uploads |
| `database-architect` | `.agents/skills/database-architect/SKILL.md` | MariaDB/MySQL schema changes, additive idempotent migrations, FK/check constraints, query indexing |
| `security-auditor` | `.agents/skills/security-auditor/SKILL.md` | CSRF enforcement, output escaping (`e()`), `finfo` upload verification, access-controlled `media.php`, zero secret leaks |
| `qa-debugger` | `.agents/skills/qa-debugger/SKILL.md` | Root-cause diagnosis, `php -l` syntax checks, and automated HTTP/DB verification gates |
| `devops-engineer` | `.agents/skills/devops-engineer/SKILL.md` | `.env.example` hygiene, reproducible local XAMPP setup, and explicit user approval before `git push` or live changes |
| `mobile-engineer` | `.agents/skills/mobile-engineer/SKILL.md` | Mobile viewports, touch targets, geolocation permissions (`navigator.geolocation` fallback), and future native clients |

## Mandatory Per-File Checklist
1. **Reconnaissance & Guardian Baseline**: Check `git status --short` and callers before editing; preserve unrelated code and design tokens.
2. **Security & Backend Contracts**:
   - Declare `strict_types=1` in every PHP file.
   - Escape all HTML output via `e()`.
   - Use parameterized PDO queries (`query()`, `row()`, `rows()`, `scalar()`).
   - Enforce `require_post()` (CSRF validation) and role/ownership checks (`require_role()`, `owned_property()`, `owned_room()`) on all mutations.
   - Validate uploads via `finfo` magic bytes and serve private files through `media.php`.
3. **Database Changes**: Author additive, idempotent SQL migrations under `database/` and keep `database/dayon_db.sql` synchronized.
4. **Verification Gate**: Run `php -l` on every modified PHP file and execute end-to-end HTTP/DB assertions before reporting completion.
5. **Safety Boundary**: Never run `git push`, destructive SQL, or production deployments without explicit user approval in the current turn.
6. **Completion Report Format**: Always report **Implemented**, **Changed files**, **Verified** (exact commands & pass/fail/not run), and **Risks / follow-up**.
