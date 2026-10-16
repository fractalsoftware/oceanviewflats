## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done
- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests — then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First**: Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan**: Check in before starting implementation
3. **Track Progress**: Mark items complete as you go
4. **Explain Changes**: High-level summary at each step
5. **Document Results**: Add review section to `tasks/todo.md`
6. **Capture Lessons**: Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimal Impact**: Changes should only touch what's necessary. Avoid introducing bugs.

## 🛠️ Development Rules (From Lessons Learned)

### 🎨 Theme Abstractions & Component Separation
- Completely centralize style classes, custom layout flags, imagery arrays, and target endpoints within central config sheets (e.g. `src/constants/theme.ts` or `src/constants/config.ts`).
- **Rule**: React components must never contain localized dynamic layout ternary operators or hardcoded property-conditional paths. Keep components 100% style-agnostic.

### 🌐 Multilingual Page Hydration Seam (SSG-to-Client Config)
- Client-side Vanilla JS scripts must never embed hardcoded text literals, duplicate inline translation dictionaries, or scrape ad-hoc `data-msg-*` attributes from DOM elements.
- **Rule**: All client translations must be defined in `src/i18n/dict.ts`, scoped in `src/config/pages.ts`, serialized at build time into `<script id="ovf-page-config" type="application/json">`, and accessed at runtime exclusively via `window.t(key, params, fallback)` provided by `public/js/page-config.js`. Authoritative paths and locale metadata (`lang`, `assetPrefix`, `apiBase`) must be accessed via `window.getPageConfig()`.

### ⚡ Async Lazyloading of Weighty Assets
- Banish globally loaded third-party scripts/SDKs from the base templates (e.g. `base.ts`) to preserve pristine PageSpeed / Core Web Vitals (FCP, LCP) on static content views.
- **Rule**: Load external SDK scripts dynamically via custom Promises triggered only on active user intent events (such as calendar unhiding or form field focusing).

### 🗄️ Banned Inline Database Schema Mutations
- Banish inline, dynamic `ALTER TABLE` or `CREATE TABLE IF NOT EXISTS` commands inside runtime client-facing transactional REST scripts (e.g. `payment.php`).
- **Rule**: Centralize SQL schema definitions under `scripts/schema.sql` and run additions exclusively using CLI migration routines (`scripts/migrate.php`) during deploy time.

### 🔗 Absolute Asset Prefix Propagation
- Since generated outputs map to both root and nested folder structures, compile-time relative links will break if absolute paths are utilized.
- **Rule**: Always pass down the calculated `assetPrefix` prop through all React modules and prepend it to every relative link to ensure local preview and subdirectory durability.

## Agent skills

### Issue tracker

Issues and specs live in GitHub Issues for `fractalsoftware/oceanviewflats` using the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical five-role triage vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context documentation layout (`CONTEXT.md` and `docs/adr/` at repo root). See `docs/agents/domain.md`.