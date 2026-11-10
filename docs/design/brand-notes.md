# Teri (ጠሪ) Brand Identity & Visual Design Notes

## 1. Brand Concept & Origin

- **Name:** **Teri** (English / Latin UI) / **ጠሪ** (Amharic UI).
  - Meaning: "ጠሪ" is the Ethiopian Amharic noun for *the one who invites or calls guests together*. It carries cultural warmth, hospitality, and communal gathering.
- **Slogan:** **ጠሪ አክባሪ** (Teri Akbari).
  - Meaning: "The one who invites, honors." In traditional Ethiopian hospitality, inviting someone is not merely sending a summons—it is extending deep respect (*kibur*) to family, elders, friends, and neighbors.
  - English Translation / Subtitle: *"Invite with honor"*.
  - Slogan status: Marked with `needs_native_review: true` in `storage/app/copy-review.md`.

---

## 2. Monogram Architecture: The "ጠ" (Te) Mark

The core brand mark is an original hand-drawn vector monogram based on the Amharic letter **ጠ** (Te), the first character of **ጠሪ**.

### Geometric Construction
- **Canvas / Tile:** 100 × 100 rounded squircle with `rx=22` corner curvature.
- **Background Tile:** Ink indigo (`#1C2140`).
- **Primary Glyph:** Paper white (`#F6F5FA`) with uniform stroke thickness of 10.4 units.
- **Structure:**
  - Continuous crown horizontal bar across `y=25` (width 60 units).
  - Three distinct vertical legs extending to `y=75`: left (`x=20`), middle (`x=50`), and right (`x=80`).
  - Dual negative-space apertures between the legs (each 16.4 units wide, 24 units high) crowned with internal top curves (`r=6`).
  - Terminal feet are softly rounded (`r=2.2`), ensuring optical clarity and warmth rather than harsh digital corners.
- **The Voice / Honor Accent:**
  - A radiant Meskel yellow (`#F4B400`) circular beacon (`cx=69, cy=18, r=5.2`).
  - Sits above the right shoulder of the letter, symbolizing the voice of the inviter calling out, the light of celebration, and festive warmth.

---

## 3. Scale & Legibility Critique (16px to 256px)

Refer to the visual test sheet at `docs/design/brand-sheet.png`.

| Size | Usage Target | Optical Performance & Assessment |
| :--- | :--- | :--- |
| **16 × 16 px** | Browser Favicon, Compact Notification Dot | **Pass:** The 16.4-unit aperture width translates to crisp 2.5–3 physical pixel gaps at 16px, preventing the three vertical legs from bleeding together into a solid block. The Meskel yellow voice accent remains discernible as an upper-right golden pixel cluster. |
| **32 × 32 px** | High-DPI Favicon, Mobile App Header Icon | **Pass:** Crisp, well-defined letterform. The rounded terminals (`rx=2.2`) prevent optical harshness, and the rounded squircle tile (`rx=22`) frames the glyph cleanly against both dark browser chrome and light tab bars. |
| **64 × 64 px** | Touch Bar, Mobile App Drawer, Quick Actions | **Pass:** Perfect balance. The yellow dot reads distinctly as an intentional design accent rather than a raster artifact. The apertures create harmonious rhythm. |
| **128 × 128 px** | Mobile Homescreen Icon, PWA Splash Tile | **Pass:** Rich visual hierarchy. The contrast between Paper white (`#F6F5FA`), deep Ink indigo (`#1C2140`), and radiant Meskel yellow (`#F4B400`) creates high-end editorial gravitas. |
| **256 × 256 px** | Social Avatar, Super Admin Header, Card Watermark | **Pass:** Premium vector finish. Smooth cubic Bézier curvatures withstand extreme close-up inspection with zero geometric distortion. |

---

## 4. Minimum Size & Clear-Space Rules

### Minimum Sizes
- **Mark Only (`teri-mark.svg`):**
  - Minimum digital size: **16 × 16 px**.
  - Minimum print size: **5 mm × 5 mm**.
- **Horizontal Lockup (`teri-lockup-horizontal.svg`):**
  - Minimum digital height: **28 px** (mark = 28px, text cap height = 15px).
  - Minimum print height: **8 mm**.
- **Stacked Lockup (`teri-lockup-stacked.svg`):**
  - Minimum digital height: **64 px**.
- **Lockup with Slogan (`teri-lockup-with-slogan.svg`):**
  - Minimum digital height: **48 px** (to ensure the Amharic slogan "ጠሪ አክባሪ" and English translation "Invite with honor" remain legible).

### Clear-Space Rule
- Maintain a minimum exclusion zone of **0.5X** around all edges of the mark or lockup, where **X** is the width/height of the **ጠ** monogram tile.
- No typography, decorative flourishes, borders, or busy imagery should intrude into this boundary.

```
       +---------------------------------------------+
       |                  [ 0.5 X ]                  |
       |         +-------+                           |
[0.5X] |  [0.5X] |   ጠ   |  [0.5X]  Teri  ጠሪ  [0.5X] | [0.5X]
       |         +-------+                           |
       |                  [ 0.5 X ]                  |
       +---------------------------------------------+
```

---

## 5. Color Palette & Usage Rules

The brand palette directly inherits the design tokens established in Step 15:

| Token Name | Hex Code | Role in Brand System |
| :--- | :--- | :--- |
| **Ink Indigo** | `#1C2140` | Primary brand ground, primary wordmark text, dark mode surfaces. |
| **Paper White** | `#F6F5FA` | Light mode surface ground, monogram glyph on dark ground, card background. |
| **Meskel Yellow** | `#F4B400` | Slogan accent, voice beacon dot, CTA highlight, festive warmth. |
| **Highland Green** | `#1E5B47` | Trust, verification badges, confirmed status, eco-friendly invites. |
| **Orchid** | `#8E3B6E` | Celebratory accent, floral tones, wedding & anniversary highlights. |
| **Mist** | `#E4E3EE` | Hairlines, borders, subtle card container dividers. |

### Color Permutations
1. **Full Color on Light (`teri-*.svg`):**
   - Ink indigo squircle tile `#1C2140`, Paper white glyph `#F6F5FA`, Meskel yellow dot `#F4B400`.
   - Wordmark in Ink indigo `#1C2140`.
2. **Reversed on Dark (`teri-*-reversed.svg`):**
   - Wordmark in Paper white `#F6F5FA`, slogan subtitle in Mist `#E4E3EE`, Meskel yellow accent `#F4B400`.
3. **Monochrome Ink (`teri-*-mono-ink.svg`):**
   - Single `#1C2140` color for single-color print, receipts, and stamp impressions.
4. **Monochrome White (`teri-*-mono-white.svg`):**
   - Single `#FFFFFF` color for dark photographic overlays and video watermarks.

---

## 6. Typography Specs

- **Latin Wordmark ("Teri"):** Outlined paths derived from **Young Serif 400**. Classical, warm, editorial serif with generous counters and organic teardrop terminals.
- **Amharic Wordmark ("ጠሪ") & Slogan ("ጠሪ አክባሪ"):** Outlined paths derived from **Noto Serif Ethiopic 700**. Elegant, high-formality calligraphic weight matching traditional Ethiopian parchment manuscripts.
- **English Translation Subtitle ("Invite with honor"):** Outlined paths derived from **Instrument Sans 500 Medium**. Clean, open geometric sans-serif supporting high legibility at micro scales (10px - 14px equivalent).
- **Outlined Independence:** All delivered brand SVG assets contain **100% vector paths (`<path d="...">`)** with zero `<text>` nodes and zero webfont runtime dependencies. They render identically in all browsers, PDF print drivers, and native mobile apps without FOUT (Flash of Unstyled Text) or font rendering discrepancies.
