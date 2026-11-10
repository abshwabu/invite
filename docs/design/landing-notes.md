# Landing Page Design System & Self-Critique Notes

**Document:** `docs/design/landing-notes.md`  
**Step:** 15 of 15 (Design System Tokens and Marketing Landing Page)  
**Status:** Completed and Verified  
**Screenshots Captured:**
- `docs/design/screenshots/desktop-en.png` (1280px viewport, English)
- `docs/design/screenshots/desktop-am.png` (1280px viewport, Amharic)
- `docs/design/screenshots/mobile-en.png` (375px viewport, English)
- `docs/design/screenshots/mobile-am.png` (375px viewport, Amharic)

---

## 1. Visual Observations Across Viewports and Locales

### 1.1 Desktop English (`desktop-en.png` - 1280px)
- **Hero Staging:** The dual-stage layout (left: editorial copy + live name input + occasion chips; right: live rendered invitation card + CSS phone SMS preview) immediately proves product capability within the first 3 seconds of viewing.
- **Card Renderer:** The canvas rendering operates with high vector fidelity at natural `1080:1528` card proportions without distortion.
- **The Shelf:** Real template previews arranged in a single-row scrollable shelf. The occasion tabs clearly communicate the breadth of the library (84 templates).
- **How It Works & At The Door:** Alternating CSS-drawn mobile mockups break repetitive visual rhythm. The interactive door scanner simulation clearly illustrates the single-use QR mechanic.
- **Pricing & Hierarchy:** The 5 tiers accurately reflect the SQLite database (`free`, `bronze`, `silver`, `gold`, `platinum`). The Gold tier (6,999 ETB / 40,000 cards) stands out cleanly with Meskel yellow bordering without being garish.

### 1.2 Desktop Amharic (`desktop-am.png` - 1280px)
- **Ethiopic Typography:** Noto Serif Ethiopic displays with authentic typographic dignity for major headlines ("ካርዱን ይንደፉ። ለሁሉም ይላኩ። በበሩ ላይ ፈትሸው ያስገቡ።"), while Noto Sans Ethiopic maintains crisp UI legibility across buttons, badges, and body copy.
- **Cultural Tone:** Memorial and funeral wording ("ተዝካር / የቀብር ሥነ-ሥርዓት") treats solemn events with sober respect alongside joyous celebrations (ሠርግ, ዒድ, መስቀል, ልደት).
- **Layout Parity:** Zero line overflows or awkward wrapping in Amharic. The two-column typographic layout in "Built for Ethiopia" scales harmoniously.

### 1.3 Mobile English & Amharic (`mobile-en.png`, `mobile-am.png` - 375px)
- **Above-the-Fold Delivery:** On small screens (360px–375px), the headline, visitor input field, live card preview, and primary CTA sit above the fold with zero horizontal scrolling.
- **Touch Targets:** Occasion selector chips, language toggle pills, and CTA buttons meet minimum 44px touch targets.
- **Native Details/Summary:** The FAQ accordion operates smoothly on touch without layout jank or script dependencies.

---

## 2. Critique Against the Design Plan & Anti-Pattern Elimination

We reviewed the live rendered pages against our initial plan (`docs/design/landing-plan.md`) to verify that all anti-patterns were avoided:

| Target Anti-Pattern | Status | Verification in Live Output |
| :--- | :--- | :--- |
| **No cream + terracotta cliché** | Pass | Built on crisp **Paper (`#F6F5FA`)** with deep **Ink Indigo (`#1C2140`)** and authentic **Meskel Yellow (`#F4B400`)**. |
| **No dark-mode neon glows** | Pass | Light ceremonial paper background with crisp typography; zero synthetic drop shadows or neon blurs. |
| **No repetitive 3-column card grids** | Pass | Every section has a different structure: Hero = Split live stage; Shelf = Horizontal carousel; How it works = Alternating device frames; Built for Ethiopia = 2-column typographic list; Also included = Definition list `<dl>`; Pricing = Structured tier table. |
| **No tracked-out all-caps labels** | Pass | Sentence case used everywhere in both Latin and Ethiopic scripts. |
| **No trailing arrows on buttons** | Pass | Buttons use clean, direct labels ("Design an invitation", "Use this design", "Try free in editor") with zero appended arrows. |
| **No non-sequential 01/02/03 badges**| Pass | Numbers 1 through 4 appear exclusively on the chronological sequence of "How it works". |

---

## 3. Decorative Element Removal & Refinements

### Decorative Element Removed
- **Floating Eyebrow Pill Badge in Hero:**  
  *Initial state:* A small floating pill tag (`[• Ethiopian Event Invitations & Door Check-in]`) was positioned above the main headline in the Hero.  
  *Critique:* Because the occasion filter chips (`Wedding`, `Funeral`, `Birthday`, `Eid`, `Meskel`, `Graduation`) already explicitly communicate the platform's multi-occasion Ethiopian capabilities, the extra eyebrow badge added visual clutter and pushed the primary headline down by 40px on mobile screens.  
  *Action Taken:* Removed the decorative eyebrow pill badge. This immediately allowed the Young Serif display headline to command instant focus and brought the interactive card higher above the fold on mobile viewports.

### Additional Micro-Adjustments Made
1. **Ethiopic Script Line-Height:** Enhanced line-height on Amharic body paragraphs (`leading-relaxed` / 1.625) to prevent ascender/descender collision in complex Ge'ez consonant-vowel combinations.
2. **Scanner Interaction Flow:** Set the interactive scanner demo to alternate naturally: First click triggers verified green screen ("Welcome, Almaz Bekele • Party of 2"), second click simulates the duplicate re-scan rejection ("Already used at 6:42 pm • Entry Denied"), and added a clear "Reset scanner demo" button.
3. **CardRenderer Code Splitting:** Verified that `CardRenderer` is lazy-loaded via `defineAsyncComponent`, maintaining the core landing bundle at just **44.7 kB (12.0 kB gzipped)**.

---

## 4. Verification Checklist

- [x] Design tokens configured in Tailwind and exposed as CSS custom properties in `:root`.
- [x] Real template previews rendered at natural 1080x1528 card proportions.
- [x] Interactive live card updates when visitor types guest name.
- [x] Occasion selector chips switch templates between Wedding, Funeral, Birthday, Eid, Meskel, and Graduation.
- [x] Tiers dynamically loaded from SQLite database with Gold highlighted and ETB prices.
- [x] Single-use QR door scanner interactive simulation with success and duplicate rejection states.
- [x] All copy available in both English and Amharic with `vue-i18n`.
- [x] Amharic translation strings documented for native review in `resources/js/locales/landing-needs-review.json`.
- [x] Sitemap at `/sitemap.xml` with zero admin or private URLs.
- [x] Zero stock photos; all visuals are authentic canvas/vector renders and CSS device frames.
- [x] All Pest feature tests pass.
