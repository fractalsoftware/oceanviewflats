---
name: OceanViewFlats
description: Direct booking platform and self-service coastal concierge for beachfront residences in Santa Marta, Colombia.
colors:
  coral-horizon: "#FF5A5F"
  coral-horizon-deep: "#FF424D"
  coral-horizon-accessible: "#C43E29"
  coral-horizon-ink: "#A8321E"
  coral-horizon-ink-deep: "#8F2716"
  coral-wash: "#FEF2F2"
  salguero-sunset-rose: "#E11D48"
  salguero-sunset-rose-deep: "#BE123C"
  salguero-sunset-rose-light: "#FB7185"
  rose-wash: "#FFF1F2"
  clearance-emerald: "#059669"
  clearance-emerald-deep: "#047857"
  clearance-emerald-light: "#34D399"
  clearance-emerald-tint: "#6EE7B7"
  clearance-emerald-night: "#022C22"
  emerald-wash: "#ECFDF5"
  keycard-amber: "#F59E0B"
  amber-wash: "#FFFBEB"
  nomad-sky: "#0EA5E9"
  nomad-sky-light: "#38BDF8"
  sky-wash: "#F0F9FF"
  pure-white: "#FFFFFF"
  coastal-mist: "#F8FAFC"
  slate-surface: "#F1F5F9"
  slate-border: "#E2E8F0"
  slate-muted: "#94A3B8"
  slate-body: "#475569"
  slate-ink: "#1E293B"
  deep-caribbean-slate: "#0F172A"
  travertine-mist: "#FAFAF9"
  travertine-surface: "#F5F5F4"
  travertine-border: "#E7E5E4"
  travertine-ink: "#292524"
  warm-travertine-stone: "#1C1917"
typography:
  display:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "3.75rem"
    fontWeight: 800
    lineHeight: 1.15
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 800
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.35
    letterSpacing: "-0.015em"
  body:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.625
    letterSpacing: "0em"
  label:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.06em"
  mono:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
    fontSize: "1.5rem"
    fontWeight: 900
    lineHeight: 1.2
    letterSpacing: "0.08em"
rounded:
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  2xl: "48px"
  3xl: "80px"
  4xl: "96px"
components:
  button-primary:
    backgroundColor: "{colors.coral-horizon}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.full}"
    padding: "16px 32px"
  button-primary-hover:
    backgroundColor: "{colors.coral-horizon-deep}"
    textColor: "{colors.pure-white}"
  button-property-1606:
    backgroundColor: "{colors.salguero-sunset-rose}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "16px 24px"
  button-property-1606-hover:
    backgroundColor: "{colors.salguero-sunset-rose-deep}"
    textColor: "{colors.pure-white}"
  button-settlement:
    backgroundColor: "{colors.clearance-emerald}"
    textColor: "{colors.pure-white}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "16px 24px"
  button-settlement-hover:
    backgroundColor: "{colors.clearance-emerald-deep}"
    textColor: "{colors.pure-white}"
  input-default:
    backgroundColor: "{colors.pure-white}"
    textColor: "{colors.slate-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 14px"
  card-surface:
    backgroundColor: "{colors.pure-white}"
    textColor: "{colors.slate-ink}"
    rounded: "{rounded.xl}"
    padding: "32px"
---

# Design System: OceanViewFlats

<!-- impeccable:design-map -->
Name: OceanViewFlats
Colors: primary: coral-horizon #FF5A5F; secondary: salguero-sunset-rose #E11D48; tertiary: clearance-emerald #059669; neutral: pure-white #FFFFFF; primary: coral-horizon-deep #FF424D (+27 more in the sidecar)
Fonts: Inter (display, headline, title, body, label); ui-monospace (mono)
Spacing: xs 4px, sm 8px, md 16px, lg 24px, xl 32px, 2xl 48px, 3xl 80px, 4xl 96px
Radius: sm 8px, md 12px, lg 16px, xl 24px, full 9999px
North Star: "The Sunlit Coastal Concierge"
Rules:
- The Property Temperature Rule. On dedicated property pages and homepage showcase cards, Property 1707 pairs Coral Horizon / Nomad Sky cues with cool Slate neutrals, while Property 1606 pairs Salguero Sunset Rose cues with warm Travertine Stone neutrals...
- (+5 more)
Elevation:
- Subtle Input & Inner-Tile Rest: shadow-xs
- Standard Card Elevation: shadow-md
- Interactive & Transaction Lift: shadow-xl
- Hero & Floating Showcase Depth: shadow-2xl
- Coral Brand Glow: 0 10px 25px -5px rgba(255, 90, 95, 0.28)
Don't:
- Don't embed property-conditional layout or style ternaries (e.g., `propertyId === "1707" ? "bg-red-50" : "bg-rose-50"`) directly inside React component JSX; resolve them through `THEMES[propertyId]`.
- Don't hardcode UI copy or inline translation maps in components or Vanilla JS (`public/js/`); define all strings across all 6 locales (`en`, `es`, `fr`, `it`, `de`, `ja`) in `src/i18n/dict.ts` and read them on the client exclusively via `window.t()`.
- Don't use Clearance Emerald (`#059669`) or Keycard Amber (`#F59E0B`) as generic decorative colors; preserve their strict semantic association with payment settlement/legal trust and gated physical access credentials/warnings.
- Don't load heavy third-party scripts (such as the Mercado Pago SDK) in the global HTML head or introduce client-side React hydration; preserve zero-hydration static HTML delivery for low-bandwidth roaming connections.
- Don't use fixed-height text containers or single-line clipping (`truncate` / `whitespace-nowrap`) on localized headings, legal attestations, or CTA buttons that must expand across German, French, Spanish, Italian, and Japanese.
Map: src/style.css
Map: src/constants/theme.ts
Map: src/components/
Map: src/pages/
Map: public/js/
Map: src/i18n/dict.ts
Full: .impeccable/design-full.md (read a section when you need its detail)
