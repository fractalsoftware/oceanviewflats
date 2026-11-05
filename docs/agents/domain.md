# Domain Docs

Single-context domain documentation layout at the repository root:

- **[`CONTEXT.md`](../../CONTEXT.md)**: Canonical domain glossary and ubiquitous language.
- **[`docs/adr/`](../adr/)**: Architecture Decision Records (`0001`–`0013`).

## Rules

1. **Read before exploring**: Consult `CONTEXT.md` and relevant ADRs in `docs/adr/` before designing or modifying domain, API, or admin workflows.
2. **Use canonical vocabulary**: Always use the exact term defined in `CONTEXT.md` (in code, tests, UI labels, issues, and commits) and avoid listed synonyms.
3. **Keep `CONTEXT.md` pure**: `CONTEXT.md` is strictly a domain glossary — never put UI implementation details, CSS classes, or code snippets in it.
4. **Surface ADR conflicts**: If a change contradicts or amends an existing ADR, call it out explicitly and update the ADR when the decision is finalized.
