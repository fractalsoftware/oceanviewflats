---
target: the homepage
total_score: 34
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 1
target_identity: "file:/Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx"
target_fingerprint: "sha256:92782ce9aaeba7183658ab87e53463fcbb5a821e335828b820545d1248496e8f"
target_path: /Users/manuel.herrera/Projects/17071606/src/pages/Home.tsx
timestamp: 2026-11-06T19-26-06Z
slug: src-pages-home-tsx
---
Method: dual-agent (A: ses_e4db3fbf9ffeKTezqNvaSCo9RH · B: ses_e4db3f05cffeg122c4B47cYL0I)

#### Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Scrolled navbar (`DOMContentLoaded` + `scroll`), `<details open>` chevron rotation (`#faq-3` open by default), and starting `"From 300,000 COP / night"` rate badges work cleanly, though international visitors have no approximate USD/EUR reference alongside `COP`. |
| 2 | Match System / Real World | 4 | Authentic domain language across all 6 locales (`Edificio Salguero Sunset`, `RNT #268781` & `#270413`, `17th`/`16th` floors, `300,000 COP`, `PSE`, `Efecty`, `20,000 COP` reception fee). |
| 3 | User Control and Freedom | 4 | Native `<details>/<summary>` accordions plus `Escape`, outside-click, and keyboard `focusout` dismissal on `#lang-toggle-group` provide complete user control. |
| 4 | Consistency and Standards | 3 | `Inter` is now properly bound via `--font-sans`, theme tokens are deduplicated, and `DESIGN.md` matches the unified homepage card palette; however, `#why-book-direct` Card 4 ("Secure Booking" under RNT regulations) uses Rose (`directIconRose`) instead of Clearance Emerald per `DESIGN.md`'s Semantic Trust Rule, and `cardSpecHighlight` is identical to `cardSpecPill`. |
| 5 | Error Prevention | 3 | `#home-multi-property-note` proactively guides 7–12 guest groups to book both adjacent floors, though `faq-2` ("What amenities are included?") mentions pool access without noting the mandatory `20,000 COP` reception fee or Tuesday pool closure (which sit inside collapsed `faq-6`). |
| 6 | Recognition Rather Than Recall | 4 | Both `#properties` and `#experiences` cards explicitly badge their target residence (`Oceanview Flat 1707 • 17th Floor • RNT #268781` and `Oceanview Flat 1606 • 16th Floor • RNT #270413`) with starting `300,000 COP` rates, `900 Mbps` Wi-Fi, and private parking visible at a glance. |
| 7 | Flexibility and Efficiency | 3 | Ready-to-book desktop visitors have `#nav-book` and `#properties`, and bottom CTA buttons now have a clear primary/secondary hierarchy; however, `#nav-book` is `hidden md:inline-flex` on mobile (`<768px`), and the bottom CTA's primary button (`href="#properties"`) still scrolls users back up the page rather than linking directly to 1707/1606. |
| 8 | Aesthetic and Minimalist Design | 3 | Balanced 2-item `#experiences` cards, visible `#properties` heading, and primary/secondary bottom CTA pills create a clean visual rhythm, though `#experiences` repeats a second 2-column property card grid without surfacing the verified guest reviews already available in `src/i18n/dict.ts`. |
| 9 | Error Recovery | 4 | Zero dead ends; provides immediate Contact and pre-configured WhatsApp escalation channels for custom or multi-property inquiries. |
| 10 | Help and Documentation | 3 | Six native `<details>/<summary>` FAQ accordions (`#faq-3` open by default, synced with `FAQPage` JSON-LD) and localized Law 1581 `/privacy` links across all 6 languages; `faqA6` remains a dense single paragraph combining four operational topics. |
| **Total** | | **34/40** | **Good–Excellent (85.0%, up from 21/40 → 30/40 → 34/40) — Cohesive typography, balanced layout, and clear COP pricing cues, with high-leverage opportunities in verified guest social proof, keyboard `focus-visible` rings, and mobile sticky booking access.** |

#### Design Specificity Verdict

**LLM assessment**: The homepage is now ~85% grounded in the authentic physical and statutory reality of OceanViewFlats in Santa Marta. Both `#properties` and `#experiences` clearly identify **Oceanview Flat 1707** (`17th Floor • RNT #268781`) and **Oceanview Flat 1606** (`16th Floor • RNT #270413`), display starting **`300,000 COP / night`** rate badges and **`900 Mbps` Wi-Fi** pills, balance their feature rows to 2 items per card, and pair **Manuel Enrique's** host profile with official RNT credentials (`#home-host-card`). The remaining opportunities to elevate specificity from "well-crafted property template" to "unmistakable coastal concierge" are:
1. **Unused Verified Guest Reviews (`src/i18n/dict.ts:79-88`)**: The translation dictionary already contains localized guest testimonials from **Karol Ximena** (for 1707), **Lina Marcela**, and **Sheyla** (for 1606) across all 6 locales, yet `src/pages/Home.tsx` displays zero guest reviews on the homepage.
2. **Undifferentiated `cardSpecHighlight` vs. `cardSpecPill` (`src/constants/theme.ts:169-172`)**: Now that both apartments share a unified neutral Slate frame and the same `"From 300,000 COP / night"` starting rate, `cardSpecHighlight` (`4 Beds` vs. `3 Beds`) has the exact same styling as `cardSpecPill`, making the key capacity difference between 1707 and 1606 harder to spot in a 3-second scan.
3. **Mobile Sticky Navbar & Bottom CTA Navigation**: `#nav-book` is hidden on mobile (`hidden md:inline-flex`), and the bottom CTA's primary button (`href="#properties"`) scrolls convinced visitors back up to the middle of the page instead of letting them jump directly into 1707 or 1606.

**Deterministic scan**:
- **Source scan** (`impeccable detect --json src/pages/Home.tsx src/components/Navigation.tsx src/components/LanguageDropdown.tsx src/components/Footer.tsx`): Exit code `0`, **0 findings (`[]`)**.
- **Compiled HTML scan** (`impeccable detect --json dist/index.html`): Exit code `2`, **39 warnings** across 4 rules (**0 `design-system-font`, 0 `design-system-color`, 0 `kicker-above-heading`, 0 `dark-glow`, and 0 genuine `low-contrast` failures**):
  - **`design-system-font` is 100% resolved**: Binding `--font-sans` to `"Inter", -apple-system, BlinkMacSystemFont, sans-serif` in `src/style.css` completely eliminated the `apple color emoji` font warning.
  - **False Positives Filtered Out (all 39 compiled HTML warnings)**:
    - `overused-font` (1 warning: `Primary font: inter`): False positive against the project's pinned `DESIGN.md` typography specification, which explicitly mandates `Inter` for zero-FOUT tabular COP legibility and 6-locale glyph coverage.
    - `low-contrast` (3 warnings: `1.0:1 — text #ffffff on #f8fafc`): False positives on `#navbar` (`position: fixed; background: transparent`), which sits over the dark `bg-slate-900` hero at `scrollY === 0` and now synchronizes immediately on `DOMContentLoaded` if loaded pre-scrolled.
    - `cramped-padding` (34 warnings): False positives from static analysis on wrapper elements whose padding is delegated to child containers or responsive Tailwind classes (`p-6 md:p-8`).
    - `gradient-text` (1 warning): False positive from `.bg-clip-text` in the shared `dist/css/style.css` bundle (used only on `src/pages/NotFound.tsx:66`).

**Visual overlays**: No reliable user-visible overlay is available in this session; fallback signal: `no browser automation tool exposed in harness`.

#### Overall Impression

Across two iterations, the OceanViewFlats homepage has climbed from **21/40 → 30/40 → 34/40**, with **0 `P0` issues**, **0 source detector findings**, and **0 genuine compiled detector violations**. Typography (`Inter`), layout symmetry (`#experiences`), starting `300,000 COP` rate anchors, `900 Mbps` Wi-Fi pills, `#faq-3` default expansion, and the bottom CTA primary/secondary button hierarchy all work harmoniously. The single biggest remaining opportunity is surfacing the **verified guest reviews** (`Karol Ximena`, `Lina Marcela`, `Sheyla`—already translated in `src/i18n/dict.ts`) and sharpening the **4 Beds vs. 3 Beds** visual highlight so first-time visitors have peer social proof and instant unit differentiation before clicking through.

#### What's Working

1. **Cohesive `Inter` Typography, Visible `#properties` Framing & `300,000 COP` Price Anchors (`src/style.css:3-5`, `src/pages/Home.tsx:87-155`)**: Binding `--font-sans` to `Inter` and adding dark `#0F172A` `"From 300,000 COP / night"` badges + `"900 Mbps Wi-Fi"` pills to both `#home-card-1707` and `#home-card-1606` makes the hero's *"Authoritative COP Quotes"* promise concrete immediately below the fold.
2. **Badged & Height-Balanced `#experiences` Showcase (`src/pages/Home.tsx:244-315`, `src/constants/config.ts:257-300`)**: Adding explicit localized Property identity badges (`Oceanview Flat 1707 • 17th Floor • RNT #268781` and `Oceanview Flat 1606 • 16th Floor • RNT #270413`) and pairing two lifestyle features inside each card eliminated both the ~80px desktop height mismatch and the ambiguity over which card links to which apartment.
3. **Hardened Navigation, Pre-Opened `faq-3` & Primary/Secondary Bottom CTA Hierarchy (`public/js/main.js:15-57, 178-188`, `src/constants/theme.ts:306-311`)**: Pre-opening `#faq-3` surfaces `Card`, `PSE`, and `Efecty` direct payment instructions immediately; `#navbar` syncs on `DOMContentLoaded` and `#lang-toggle-group` closes cleanly on keyboard `focusout`; and the bottom CTA now leads with one crisp white primary pill flanked by quieter bordered secondary pills.

#### Priority Issues

1. **[P1] Zero Verified Guest Reviews Surfaced on the Homepage (`src/pages/Home.tsx:244-347`, `src/i18n/dict.ts:79-88`)**
   - **What**: `src/i18n/dict.ts` already contains verified, localized guest testimonials across all 6 languages (`review1707Text` by **Karol Ximena** for Flat 1707; `review1Text` by **Lina Marcela** and `review2Text` by **Sheyla** for Flat 1606), and `PRODUCT.md` highlights them as core evidence, yet `src/pages/Home.tsx` renders none of them.
   - **Why it matters**: First-time guests being asked to book direct in `COP` outside Airbnb/Booking.com look for peer validation alongside host credentials.
   - **Fix**: Surface the verified guest testimonials from `src/i18n/dict.ts` (`Karol Ximena`, `Lina Marcela`, `Sheyla`) on the homepage—either integrated into the `#experiences` cards (giving each property's lifestyle card a verified guest quote) or as a dedicated guest voices row above `#home-host-card`.
   - **Suggested command**: `$impeccable delight`

2. **[P2] `cardSpecHighlight` Is Visually Identical to `cardSpecPill` & Mobile Navbar Hides `#nav-book` (`src/constants/theme.ts:111, 169-172`)**
   - **What**:
     - In `SHARED_HOME_CARD_THEME` (`src/constants/theme.ts:169-172`), `cardSpecHighlight` (`2 Bedrooms • 4 Beds` on 1707 vs. `2 Bedrooms • 3 Beds` on 1606) has the exact same class string as `cardSpecPill` (`bg-white border border-slate-200 text-slate-700`). Now that both cards share the same `"From 300,000 COP / night"` starting rate and neutral Slate palette, the primary capacity difference (`4 Beds` vs. `3 Beds`) blends into the 5 white pills.
     - In `CHROME_THEME.navBookBtn` (`src/constants/theme.ts:111`), `#nav-book` uses `hidden md:inline-flex`, leaving mobile users (`<768px`) without a "Book Direct" shortcut in the sticky top bar after scrolling past the hero.
   - **Why it matters**: Emphasizing `cardSpecHighlight` helps families and groups distinguish 1707 (`4 Beds`) from 1606 (`3 Beds`) in 3 seconds, and keeping `#nav-book` accessible on mobile preserves one-tap booking access down the page.
   - **Fix**: Give `cardSpecHighlight` a distinct warm accent border/tint (`bg-rose-50/70 border-rose-200 text-slate-900 font-bold`) in `src/constants/theme.ts`, and make `#nav-book` visible on mobile (or as a compact pill on small viewports).
   - **Suggested command**: `$impeccable adapt`

3. **[P2] Missing Explicit `focus-visible` Rings & `prefers-reduced-motion` Safeguards (`src/constants/theme.ts`, `src/style.css`)**
   - **What**: Interactive homepage elements (`SHARED_HOME_CARD_THEME.cardWrapper`, `HOME_THEME.heroCtaBtn`, `HOME_THEME.multiPropertyCta`, `HOME_THEME.faqSummary`, `HOME_THEME.ctaPrimaryBtn`, `HOME_THEME.ctaSecondaryBtn`, `HOME_THEME.ctaWhatsAppBtn`) rely on browser-default focus outlines rather than the `4px` focus ring specified in `DESIGN.md`, and `src/style.css` lacks a `@media (prefers-reduced-motion: reduce)` rule for hover scale/translate and smooth-scroll transitions.
   - **Why it matters**: Explicit `focus-visible:ring-4` states and reduced-motion rules ensure keyboard travelers and motion-sensitive users experience the WCAG 2.1 AA+ quality floor promised in `PRODUCT.md` and `DESIGN.md`.
   - **Fix**: Add `focus-visible:outline-none focus-visible:ring-4` tokens to interactive controls in `src/constants/theme.ts` and add a `@media (prefers-reduced-motion: reduce)` block in `src/style.css`.
   - **Suggested command**: `$impeccable harden`

4. **[P3] Semantic Trust Rule Drift on `#why-book-direct` Card 4 & Unchunked `faqA6` / `faqA2` Pool Fee Context (`src/pages/Home.tsx:225-238`, `src/i18n/dict.ts:129-131, 159-161`)**
   - **What**:
     - In `#why-book-direct`, Card 4 ("Secure Booking" under Colombian RNT regulations, `src/pages/Home.tsx:225-238`) uses `directIconRose` (`bg-rose-50 text-rose-700`) instead of **Clearance Emerald** (`directIconEmerald`), which `DESIGN.md` reserves for statutory/financial trust.
     - `faqA2` ("What amenities are included?") mentions the swimming pool without cross-referencing the `20,000 COP`/person reception wristband fee or Tuesday pool maintenance (which live inside collapsed `faqA6`).
   - **Why it matters**: Aligning Card 4 with Clearance Emerald reinforces the Semantic Trust Rule, and briefly noting the reception wristband fee/Tuesday pool schedule in `faqA2` prevents amenity surprises for visitors who don't open `faq-6`.
   - **Fix**: Switch Card 4's icon token to `directIconEmerald` (or `directIconSky`) and add a brief parenthetical note on Tuesday pool maintenance / reception registration in `faqA2`.
   - **Suggested command**: `$impeccable clarify`

#### Persona Red Flags

**Jordan (First-Timer)**: Sees `"From 300,000 COP / night"` on both `#home-card-1707` and `#home-card-1606` and appreciates the clear price anchor, but because all 5 spec pills look identical (`cardSpecHighlight` === `cardSpecPill`) and there are no guest reviews on the homepage, Jordan has to read the full paragraph text to realize 1707 has **4 beds** while 1606 has **3 beds**.

**Riley (Deliberate Stress Tester)**: Verifies that `DOMContentLoaded` scroll sync and `#lang-toggle-group` `focusout` dismissal now work seamlessly! Next, Riley tabs through `#home-card-1707`, `<summary>` FAQ headers, and the bottom CTA buttons, noticing they lack explicit `focus-visible:ring-4` rings from `DESIGN.md`, and checks `prefers-reduced-motion: reduce` in DevTools, noticing `.scroll-smooth` and `group-hover:scale-105` remain active.

**Casey (Distracted Mobile User)**: Scrolls past the hero on a 375px phone screen and notices `#nav-book` ("Book Direct") is hidden on mobile (`hidden md:inline-flex`), leaving only the envelope icon and language selector in the sticky navbar until scrolling back to `#properties` or down to the bottom CTA.

**Elena & Hiroshi (International Remote Workers & Family Planners in `de` / `ja`)**: Both see the new `900 Mbps` Wi-Fi pill and balanced 2-item `#experiences` cards in German and Japanese. Elena notices `"Ab 300,000 COP / Nacht"` uses an English comma in `de` (`300,000` vs `300.000`), and Hiroshi checks `faq-2` ("What amenities are included?") in `ja.html`, which mentions the pool without noting the `20,000 COP` reception wristband fee or Tuesday maintenance unless he also opens `faq-6`.

#### Minor Observations

- **`HOME_EXPERIENCE_CARDS` Image `alt` Keys (`src/constants/config.ts:261, 281`)**: `imageAltKey` currently points to `homeExpNomad` ("Digital Nomad Heaven") and `homeExpRomantic` ("Romantic Retreats"), repeating the feature heading text rather than using descriptive photo alt text (`nomadImgAlt` / `romanticImgAlt`).
- **Hero CTA Icon Color (`src/constants/theme.ts:206`)**: `HOME_THEME.heroCtaIcon` uses `text-[#B91C1C]` on a solid white pill, whereas `DESIGN.md` (*Hero Inverted Pill*) and `THEMES["1707"].heroIconColor` specify `text-[#FF5A5F]`.
- **Bottom CTA Primary Action Target (`src/pages/Home.tsx:388-394`)**: Clicking `t.ctaBtn` (`href="#properties"`) at the bottom of the page scrolls the visitor back up to `#properties`; offering direct links or keeping the scroll smooth with reduced-motion fallback is worth considering.

#### Questions to Consider

- **Would pairing each `#experiences` card (or a strip above `#home-host-card`) with the verified guest quotes from Karol Ximena (1707) and Lina Marcela / Sheyla (1606) give first-time direct bookers the peer social proof they need?**
- **Now that both apartments start at `300,000 COP / night`, should `cardSpecHighlight` (`2 Bedrooms • 4 Beds` vs. `2 Bedrooms • 3 Beds`) have a distinct visual accent so families can spot the bed-count difference in under 3 seconds?**
- **Should `#nav-book` ("Book Direct") remain visible in the sticky mobile navbar so phone users always have a one-tap jump to the properties?**
