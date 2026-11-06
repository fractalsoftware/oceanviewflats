---
target: the homepage
total_score: 30
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 2
target_identity: "file:/Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx"
target_fingerprint: "sha256:0d0dec4b5b5ad851c97efdd4a1eca2fc693d62ecce22980a969df90a50f57105"
target_path: /Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx
timestamp: 2026-11-05T23-48-33Z
slug: src-pages-home-tsx
---
Method: dual-agent (A: ses_e51e701bdffeFVzrkxI1J6vqKL · B: ses_e51e6f5ceffe6ewhwcoYbpiRHq)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Scrolled navbar, `<details open>` chevron rotation, and card hover lift work cleanly, but property cards still omit starting `COP` nightly rates before click-through. |
| 2 | Match System / Real World | 4 | Authentic domain language across all 6 locales (`Edificio Salguero Sunset`, `RNT #268781` & `#270413`, `17th`/`16th` floors, `COP`, `PSE`, `Efecty`, `20,000 COP` reception fee). |
| 3 | User Control and Freedom | 3 | Native `<details>` accordions and `Escape` / outside-click dismissal work well, but `#lang-dropdown` lacks a `focusout` listener when a keyboard user tabs past the last language option. |
| 4 | Consistency and Standards | 2 | `.font-sans` on `HOME_THEME.pageWrapper` overrides `body { font-family: 'Inter' }` with Tailwind v4's default `system-ui` stack; `src/constants/theme.ts` has duplicate color classes (`text-red-700 text-[#B91C1C]`); and `DESIGN.md` still documents the old Rose/Stone 1606 card split after the cards were unified. |
| 5 | Error Prevention | 3 | `#home-multi-property-note` proactively guides 7–12 guest groups to book both adjacent floors, though the mandatory `20,000 COP` reception fee and Tuesday pool closure sit in collapsed `faqQ6` (`defaultOpen: false`). |
| 6 | Recognition Rather Than Recall | 2 | In `#experiences` (`src/pages/Home.tsx:246-338`), the two lifestyle cards link to `/Oceanview1707/` and `/Oceanview1606/` without naming which apartment is which on the card face. |
| 7 | Flexibility and Efficiency | 2 | Every primary conversion CTA on `Home.tsx` (Navbar, Hero, and Bottom CTA) is a `#properties` hash link that scrolls up/down the same page rather than letting convinced guests jump straight to 1707 or 1606 at the bottom. |
| 8 | Aesthetic and Minimalist Design | 3 | Clean whitespace, zero-hydration static delivery, and eliminated `dark-glow`/`kicker-above-heading` slop, but `#experiences` has a lopsided 1-item vs. 2-item card asymmetry and the bottom CTA stacks 3 equally sized solid pill buttons. |
| 9 | Error Recovery | 4 | Zero dead ends; provides immediate Contact and pre-configured WhatsApp escalation channels for custom or multi-property inquiries. |
| 10 | Help and Documentation | 4 | Six native `<details>/<summary>` FAQ accordions (synced with JSON-LD) and localized Law 1581 `/privacy` links across all 6 languages. |
| **Total** | | **30/40** | **Good (75.0%, up from 21/40) — Strong domain authority, accessibility, and funnel clarity, with clear polish opportunities in `#experiences` symmetry, `Inter` font binding, and CTA hierarchy.** |

#### Design Specificity Verdict

**LLM assessment**: The homepage has taken a major leap forward in product specificity. Naming `Edificio Salguero Sunset • Playa Salguero, Santa Marta`, badging the exact floor levels and tourism registry numbers (`17th Floor • RNT #268781` and `16th Floor • RNT #270413`), surfacing the **7–12 Guest Multi-Property Adjacency Banner** (`#home-multi-property-note`), and pairing **Manuel Enrique's** host portrait with official RNT credentials (`#home-host-card`) anchor the page in physical Colombian beachfront reality. The remaining specificity gaps are structural:
1. **Unlabelled & Asymmetric `#experiences` Grid (`src/pages/Home.tsx:246-338`)**: The two dark lifestyle cards link to `property1707Href` and `property1606Href` with `"Explore & Book Direct"`, but omit the apartment names (`Oceanview Flat 1707` vs. `Oceanview Flat 1606`), and place 1 feature item (`homeExpNomad`) in the left card against 2 stacked feature items (`homeExpRomantic` + `homeExpFamily`) in the right card.
2. **Missing Starting `COP` Rates & Hidden Section Heading in `#properties` (`src/pages/Home.tsx:90-150`)**: Despite promising *"Authoritative COP Quotes"* in the hero, neither Property card displays a starting nightly rate in `COP` (e.g., `From $450,000 COP / night` vs. `From $350,000 COP / night`) or the `900 Mbps` Wi-Fi spec pill, and the section heading is `sr-only`.
3. **Three Equally Weighted Pill Buttons in the Bottom CTA (`src/pages/Home.tsx:409-436`)**: Rendering three full-size solid pill buttons (`White`, `Rose #BE123C`, `Emerald #047857`) side-by-side creates competing focal points, and the primary button (`href="#properties"`) scrolls a bottom-of-page visitor back up to the middle of the page.

**Deterministic scan**:
- **Source scan** (`impeccable detect --json src/pages/Home.tsx src/components/Navigation.tsx src/components/LanguageDropdown.tsx src/components/Footer.tsx`): Exit code `0`, **0 findings (`[]`)** — down from 2 advisories in the baseline run.
- **Compiled HTML scan** (`impeccable detect --json dist/index.html`): Exit code `2`, **37 warnings** (down from 44 findings; **all `kicker-above-heading`, `dark-glow`, `design-system-color`, `design-system-font-size`, and all 10 genuine `low-contrast` text failures from the baseline run are 100% resolved**):
  - **1 Genuine Technical Finding Caught by Detector — `design-system-font` (#36)**:
    - `span "OceanViewFlats" uses apple color emoji; not declared in DESIGN.md typography`.
    - **Why this matters**: `HOME_THEME.pageWrapper` (`src/constants/theme.ts:208`) applies Tailwind v4's `font-sans` utility, which sets `font-family: var(--font-sans)` (`ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji"...`). Because `src/style.css` does not declare `@theme { --font-sans: "Inter", -apple-system, BlinkMacSystemFont, sans-serif; }`, `.font-sans` overrides `body { font-family: 'Inter', sans-serif; }` in `src/templates/base.ts:110`—silently resetting the homepage typography from `Inter` back to `system-ui`!
  - **False Positives Filtered Out (36 of 37 compiled HTML warnings)**:
    - `low-contrast` #1–#3 (`1.0:1 — text #ffffff on #f8fafc`): False positives on `#navbar` (`position: fixed; background: transparent`), where static DOM parent traversal resolves against `<div class="bg-slate-50">` instead of the dark `<header class="bg-slate-900">` hero painted underneath it at `scrollY === 0`. *(Note: calling the scroll-state sync function once on `DOMContentLoaded` in `public/js/main.js` is still a good defense if a user reloads while scrolled down at `/#faq`.)*
    - `cramped-padding` #4–#35 (32 warnings): False positives from static analysis against Tailwind v4 spacing variables and `<details>`/`<summary>` child padding containers (all containers have `16px–96px` actual padding).
    - `gradient-text` #37: False positive from `.bg-clip-text` in the shared `dist/css/style.css` bundle (used only on `src/pages/NotFound.tsx:66`, not on `Home.tsx`).

**Visual overlays**: No reliable user-visible overlay is available in this session; fallback signal: `no browser automation tool exposed in harness`.

#### Overall Impression

The homepage has progressed from a **21/40** template with broken funnel routing and 19 genuine detector warnings to a **30/40** cohesive, accessible, zero-hydration direct-booking gateway with **0 source detector findings**. The remaining opportunities are focused craft refinements: fixing the Tailwind v4 `--font-sans` override so `Inter` actually renders, balancing and badging the two `#experiences` cards so each clearly identifies its apartment (`1707` vs. `1606`), surfacing starting `COP` rates and visible section framing on `#properties`, and establishing a clear visual hierarchy among the three bottom CTA actions.

#### What's Working

1. **7–12 Guest Multi-Property Adjacency Banner (`src/pages/Home.tsx:154-170`, `HOME_THEME.multiPropertyBanner`)**: Turning the physical adjacency of Property 1707 (17th floor) and Property 1606 (16th floor) at Edificio Salguero Sunset into an immediate callout below the 2-column property grid solves a real multi-generational family pain point right where group planners look for capacity.
2. **Editorial Host & Statutory RNT Trust Card (`src/pages/Home.tsx:341-368`, `#home-host-card`)**: Pairing Manuel Enrique's portrait and hospitality bio with the high-contrast emerald `RNT #268781 • RNT #270413` badge bridges personal host warmth with Colombian statutory legitimacy without triggering `kicker-above-heading`.
3. **Zero-Hydration `<details>/<summary>` FAQ & WCAG AA Chrome (`src/pages/Home.tsx:373-395`, `src/components/LanguageDropdown.tsx`)**: All 10 genuine `low-contrast` failures, `dark-glow` halos, and `kicker-above-heading` anti-patterns are gone; `#lang-toggle` is a semantic `<button>` with `Escape` focus restoration and `≥ 40px × 40px` tap targets; and the 6 FAQ items use native `<details>` accordions synced with `FAQPage` JSON-LD.

#### Priority Issues

1. **[P1] Tailwind v4 `.font-sans` Overrides `Inter` Typography (`design-system-font`)**
   - **What**: `HOME_THEME.pageWrapper` (`src/constants/theme.ts:208`) applies `font-sans`, which in Tailwind v4 resolves to `--font-sans: ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji"...` (`dist/index.html` detector warning #36), overriding the `<style>body { font-family: 'Inter', sans-serif; }</style>` rule in `src/templates/base.ts:110`.
   - **Why it matters**: Every heading, body paragraph, and button inside `.font-sans` on the homepage is rendering in the OS system font instead of the loaded Google Font `Inter` specified in `DESIGN.md`.
   - **Fix**: Define `@theme { --font-sans: "Inter", -apple-system, BlinkMacSystemFont, sans-serif; }` in `src/style.css` so Tailwind v4's `font-sans` utility binds directly to `Inter` and matches `DESIGN.md` without undocumented emoji font warnings.
   - **Suggested command**: `$impeccable typeset`

2. **[P1] Asymmetric & Unlabelled `#experiences` Lifestyle Cards ("Tailored Experiences")**
   - **What**: In `src/pages/Home.tsx:246-338`, `#experiences` renders a second 2-column photo card grid linking to `property1707Href` and `property1606Href`, but:
     - Neither card displays a badge or title indicating which apartment (`Oceanview Flat 1707` vs. `Oceanview Flat 1606`) it links to.
     - The left card (`1707`, lines 257–274) contains only **1** feature block (`homeExpNomad`), while the right card (`1606`, lines 295–328) stacks **2** feature blocks (`homeExpRomantic` + `homeExpFamily`), creating an ~80px height imbalance on desktop and implying only 1606 is family-friendly (even though 1707 has 4 beds vs. 1606's 3 beds).
   - **Why it matters**: Forces visitors to guess which link goes to which apartment (violating Recognition Rather Than Recall) and visually unbalances the two-column dark showcase.
   - **Fix**: Add explicit property identity badges (`Oceanview Flat 1707 • 17th Floor` and `Oceanview Flat 1606 • 16th Floor`) to the two `#experiences` cards and balance their content height (e.g., pairing `homeExpNomad` + `900 Mbps` / 4-Bed Family flexibility on 1707 and `homeExpRomantic` + `homeExpFamily` on 1606, or rendering the 3 experience pillars in a balanced 3-column row above `#home-host-card`).
   - **Suggested command**: `$impeccable layout`

3. **[P2] Hidden Section Heading & Missing Starting `COP` Rates / `900 Mbps` Pill on `#properties` Cards**
   - **What**: In `src/pages/Home.tsx:90-150`, `<h2 className={HOME_THEME.propertiesSrHeading}>` is hidden with `sr-only` (`src/constants/theme.ts:235`), and the two Property cards show beds, baths, guests, and private parking, but omit starting `COP` nightly rates (`From 450,000 COP / night` / `From 350,000 COP / night` from `prices.csv`) and the `900 Mbps Wi-Fi` spec pill.
   - **Why it matters**: The Hero promises *"Authoritative COP Quotes"*, yet visitors comparing 1707 and 1606 on the homepage have zero price anchor until they click into each property page and select calendar dates.
   - **Fix**: Make the `#properties` section heading visible (`t.homeFeaturedPropertiesTitle`) and add a starting `COP` rate indicator and `900 Mbps Wi-Fi` spec pill to the property cards across all 6 locales.
   - **Suggested command**: `$impeccable clarify`

4. **[P2] Three Competing Solid Pill Buttons & Circular `#properties` Anchor at Bottom CTA**
   - **What**: In `src/pages/Home.tsx:409-436` and `src/constants/theme.ts:323-330`, the bottom CTA renders three identically sized solid pill buttons (`White`, `Rose #BE123C`, `Emerald #047857`), where the primary button (`href="#properties"`) scrolls the user back up the page, and `ctaCard` (`bg-slate-900`) sits directly above the `bg-slate-900` `Footer`.
   - **Why it matters**: Three solid high-chroma/high-contrast pills compete for visual priority, and scrolling a convinced visitor back up to `#properties` adds an extra step compared to letting them pick 1707/1606 or clearly separating primary vs. secondary button styles.
   - **Fix**: Establish a clear primary/secondary button hierarchy in `HOME_THEME` (1 primary solid CTA, with Contact Inquiry and WhatsApp styled as refined secondary/ghost pills), and clean up duplicate Tailwind color classes (`text-red-700 text-[#B91C1C]`, `bg-rose-700 bg-[#BE123C]`, `bg-emerald-700 bg-[#047857]`) in `src/constants/theme.ts`.
   - **Suggested command**: `$impeccable polish`

5. **[P2] Trivial Default-Open FAQ Item (`faqQ1`), Unchunked `faqA6` Copy, & Navbar Scroll/Focusout Edge Cases**
   - **What**:
     - `src/pages/Home.tsx:35` pre-opens `faqQ1` (*"Where is OceanViewFlats located?"*—already answered in the Hero badge) instead of `faqQ3` (Direct `COP` booking) or `faqQ6` (Check-in, Guest Registry & `20,000 COP` building fee).
     - `public/js/main.js:15-53` only runs the navbar scroll class swap on `scroll` events (not on initial `DOMContentLoaded` if the page loads pre-scrolled at `/#faq`), and `#lang-dropdown` (`main.js:144-173`) lacks a `focusout` listener when tabbing past the final language option.
   - **Why it matters**: Pre-opening `faqQ3` or `faqQ6` surfaces high-value booking and fee transparency immediately, and running the navbar scroll check on `DOMContentLoaded` + closing `#lang-dropdown` on `focusout` hardens keyboard and anchor-link navigation.
   - **Fix**: Set `defaultOpen: true` on `faqQ3` (or both `faqQ3` and `faqQ6`), invoke the navbar scroll state sync on `DOMContentLoaded` in `public/js/main.js`, and close `#lang-dropdown` on `focusout` when `e.relatedTarget` leaves `#lang-toggle-group`.
   - **Suggested command**: `$impeccable harden`

#### Persona Red Flags

**Jordan (First-Timer)**: Scrolls to `#experiences` (`src/pages/Home.tsx:246-338`) and sees two cards labeled *"Digital Nomad Heaven"* and *"Romantic Retreats / Family Escapes"* with `"Explore & Book Direct"`, but neither card says whether it leads to **Flat 1707** or **Flat 1606**. Also wonders what the starting nightly price in `COP` is before clicking into each property page.

**Riley (Deliberate Stress Tester)**: Opens `#lang-toggle` with the keyboard and presses `Tab` through all 5 language links into the Hero CTA; because `public/js/main.js` has no `focusout` listener on `#lang-toggle-group`, `#lang-dropdown` stays open over the hero. Also reloads the page at `http://localhost:8080/#faq` and notices `#navbar` stays transparent (`text-white` over `bg-slate-50`) until the user scrolls 1px, and spots `.font-sans` overriding `Inter` with `system-ui` in computed styles.

**Casey (Distracted Mobile User)**: Scrolls to the bottom CTA on a 375px phone (`src/pages/Home.tsx:409-436`) and faces three full-width, identically sized solid pill buttons stacked vertically (White, Rose, Emerald), and tapping the first button scrolls back up the page instead of opening a property.

**Elena & Hiroshi (International Remote Workers & Family Planners in `de` / `ja`)**: Elena views `de.html` and sees `"Familienausflüge"` grouped only inside the right `#experiences` card (1606), making the left card (1707) look shorter and solo-only even though 1707 has 4 beds. Hiroshi views `ja.html` and opens `faqQ6` (`src/i18n/dict.ts:3101`), encountering a dense 280-character CJK paragraph covering Law 1581, Law 679, `0XXXXXX#` PINs, and the `20,000 COP` reception fee.

#### Minor Observations

- **Redundant Dual Color Classes in `src/constants/theme.ts`**: Several theme tokens emit both a named Tailwind class and a hex arbitrary class (`text-red-700 text-[#B91C1C]`, `bg-rose-700 bg-[#BE123C]`, `bg-emerald-700 bg-[#047857]`, `text-sky-400 text-[#38BDF8]`).
- **`DESIGN.md` Documentation Drift on Property 1606 Card**: Now that `#home-card-1606` was intentionally unified with `#home-card-1707`'s clean Slate + `#B91C1C` palette per user review, `DESIGN.md` should note that homepage comparison cards share a unified neutral Slate frame while individual property pages (`/Oceanview1707` vs. `/Oceanview1606`) express the full Property Temperature split.
- **Duplicate `homeCard1707Parking` / `homeCard1606Parking` Dictionary Values**: Both keys now hold identical `"Private Parking Included"` strings across all 6 locales.

#### Questions to Consider

- **Should each `#experiences` lifestyle card explicitly badge its target residence (`Oceanview Flat 1707` vs. `Oceanview Flat 1606`) and match feature counts so both cards align in height?**
- **Would showing a starting nightly rate (e.g., `From 350,000 COP / night` and `From 450,000 COP / night`) right on the homepage property cards make the "Authoritative COP Quotes" promise immediately tangible?**
- **In the bottom CTA, how much clearer would the decision feel if one primary action led the eye while Contact and WhatsApp used a quieter secondary button treatment?**
