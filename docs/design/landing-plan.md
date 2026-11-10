# Design System Tokens & Marketing Landing Plan: "Teri" (ጠሪ)

**Document:** `docs/design/landing-plan.md`  
**Application:** Teri (ጠሪ) · Slogan: "ጠሪ አክባሪ" (Invite with honor)  
**Target Audience:** Ethiopian hosts locally and across the diaspora (Weddings, Tazkar/Funerals, Birthdays, Eid, Meskel, Graduations).  
**Primary Job:** Showcase real product vector and typographic output within 5 seconds, allowing visitors to test guest personalization and transition straight into the editor with zero registration barrier.

---

## 1. Compact Token System

### 1.1 Color Tokens (6 Primary Hex Swatches)
Selected to echo traditional Ethiopian ceremonial palettes—deep illuminated inks, golden Meskel florals (*Adey Abeba*), highland cedar greens, and ceremonial woven silk accents—without falling into clichéd neon or generic SaaS schemes.

| Token Name | Hex Code | Role & Usage |
| :--- | :--- | :--- |
| **`ink`** (Ink Indigo) | `#1C2140` | Primary brand ink, high-contrast headline text, deep structural panels |
| **`meskel`** (Meskel Yellow) | `#F4B400` | Golden accent, Demera warmth, primary action highlight, live interaction cues |
| **`highland`** (Highland Green)| `#1E5B47` | Deep Ethiopian highland spruce green, verified scan indicator, calm stability |
| **`orchid`** (Ceremonial Orchid)| `#8E3B6E` | Refined festive accent, decorative contrast, celebratory tags |
| **`paper`** (Invitation Paper)| `#F6F5FA` | Clean, subtle cool-tinted paper background; high legibility, zero glare |
| **`mist`** (Muted Slate / Mist) | `#E4E3EE` | Hairline borders, structural card outlines, subtle divider rules |

#### Semantic Text & Background Roles (CSS Custom Properties)
- `--color-bg-base`: `#F6F5FA` (Paper)
- `--color-bg-surface`: `#FFFFFF` (Pure White)
- `--color-text-main`: `#1C2140` (Ink Indigo)
- `--color-text-muted`: `#525875` (Subtle Indigo Slate)
- `--color-accent`: `#F4B400` (Meskel Yellow)
- `--color-accent-hover`: `#D99F00`
- `--color-accent-green`: `#1E5B47` (Highland Green)
- `--color-accent-orchid`: `#8E3B6E` (Orchid)
- `--color-border`: `#E4E3EE` (Mist)
- `--color-border-subtle`: `#ECEBF4`

### 1.2 Typography Hierarchy & Script Roles
Bilingual typography designed for Latin and Ge'ez (Ethiopic) parity. The display serif pairs classic ceremonial dignity with rhythmic modern geometry.

| Role | Latin Font | Ethiopic (Amharic) Font | Weight & Scale | Leading & Tracking |
| :--- | :--- | :--- | :--- | :--- |
| **Display / Hero** | Young Serif | Noto Serif Ethiopic | 400 / 700 (48px–60px desktop, 32px–40px mobile) | `leading-[1.15]`, normal tracking |
| **Section Headings**| Young Serif | Noto Serif Ethiopic | 400 / 700 (28px–36px) | `leading-[1.25]`, normal tracking |
| **Subheadings** | Instrument Sans | Noto Sans Ethiopic | 600 (18px–22px) | `leading-snug`, normal tracking |
| **Body Text** | Instrument Sans | Noto Sans Ethiopic | 400 (16px–17px) | `leading-relaxed`, line width < 68ch |
| **UI Badges & Cues** | Instrument Sans | Noto Sans Ethiopic | 500 / 600 (12px–14px) | `leading-tight`, sentence case |

*Rule:* Sentence case everywhere. No tracked-out all-caps labels. Line lengths strictly contained under 70 characters for effortless scanning on wide viewports.

### 1.3 Radius Scale (3 Distinct Radii)
- `rounded-sm`: `4px` — Small badges, inline tags, input fields, and canvas action buttons.
- `rounded-md`: `12px` — Interactive dialogs, phone frame enclosures, template modal preview container.
- `rounded-full`: `9999px` — Pill chips (occasion filters, language switcher, round scan action buttons).

### 1.4 Spacing & Rhythm
- Baseline unit: `4px` (8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128).
- Section vertical cadence: `py-20` (80px) on mobile, `py-28` (112px) on desktop to provide generous negative space.

---

## 2. Core Principles

1. **Spend the boldness in one place:** The live interactive card and companion SMS mockup in the hero command immediate attention. All other sections are quietly confident, functional, and uncluttered.
2. **Every section has a distinct layout:** No repetitive 3-column card grids. Structure varies intentionally:
   - Hero = Split interactive stage + live canvas preview
   - Shelf = Horizontal dragging carousel with occasion selector tabs
   - How it works = Alternating numbered sequence with CSS-rendered mobile viewports
   - Door check-in = Single focal interactive scanner demo with visitor-triggered feedback
   - Built for Ethiopia = Two-column typographic editorial list (no cards)
   - Also included = Clean semantic definition list (`<dl>`)
   - Pricing = Structured tier comparison table highlighting Gold
   - FAQ = Native HTML `<details>` and `<summary>` disclosure list
3. **Real vector output, zero stock photos:** All visual proof comes from genuine template canvas renders, authentic Amharic/English typography, and CSS-crafted device viewports.
4. **Immediate utility:** Visitors can type a real name, see it instantly reflected on an invitation, select an occasion, and launch the guest editor with one click.

---

## 3. Anti-Pattern Review & Design Revisions

Before writing code, the proposed design was examined against current SaaS landing page tropes to eliminate clichéd, generic patterns:

| Cliché / Anti-Pattern | Proposed Approach & Why It Was Rejected | Final Implemented Decision |
| :--- | :--- | :--- |
| **Cream + Terracotta palette** | Trendy warm cream `#FDFBF7` with rust/terracotta buttons looks like a generic US boutique SaaS. | Used crisp, ceremonial **Paper (`#F6F5FA`)** paired with **Ink Indigo (`#1C2140`)** and authentic **Meskel Yellow (`#F4B400`)**. |
| **Near-black with neon green/purple glow** | Dark mode with neon blur washes looks like a crypto/developer tool, inappropriate for weddings and memorial funerals. | Clean, respectful light paper background with rich ink typography that respects solemn and joyous occasions alike. |
| **Repeated 3-column rounded card grids with soft grey shadows** | "Features", "Benefits", and "Use Cases" all using identical cards makes pages look like a standard template theme. | Banned repeated card cards. Built for Ethiopia uses a **2-column typographic list**; Also Included uses a **`<dl>` definition list**; How it works uses **alternating device frames**. |
| **Gradient washes & blurry background blobs** | Radial CSS blur blobs under headlines feel dated and reduce contrast. | Crisp white surfaces against soft paper backdrops, framed by delicate `1px` mist borders (`#E4E3EE`). Zero blurry backdrop blobs. |
| **Tracked-out all-caps eyebrows (`S O L U T I O N`)** | Artificial uppercase tracking degrades readability and Amharic doesn't have capital letters. | **Sentence case everywhere** in standard weight and proportional size. |
| **"01 / 02 / 03" markers on non-sequential items** | Slapping numbers on unordered benefit lists confuses sequence with priority. | Sequential numbers are restricted **only** to the chronological "How it works" sequence (1: Pick & edit, 2: Add guests, 3: Send, 4: Scan). |
| **Middle-dot meta strings (`Fast • Simple • Reliable`)** | Overused micro-copy filler that says nothing concrete. | Specific, descriptive phrases grounded in actual user jobs (e.g., "Invitations that work as short SMS links for guests without mobile data"). |
| **Trailing arrows (`→`) appended to every button** | Cluttered visual noise on buttons. | Clean, definitive button labels without trailing arrows (e.g., "Design an invitation", "Open in editor", "Check duplicate scan"). |

---

## 4. ASCII Wireframes

### 4.1 Desktop Wireframe (1280px / 1920px)

```text
+----------------------------------------------------------------------------------------------------+
|  [Logo: Invite / ይጋብዙ]                 [EN | አማርኛ]   [Log in]   [Design an invitation (Button)]   |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  HERO SECTION:                                                                                     |
|  +--------------------------------------------+  +-----------------------------------------------+ |
|  |  Design the card.                          |  |  +---------------------+  +-----------------+  | |
|  |  Send it to everyone.                      |  |  | [Live Canvas Render]|  | [SMS Mockup]    |  | |
|  |  Scan them in at the door.                 |  |  |                     |  |                 |  | |
|  |                                            |  |  | "Tigist & Henok     |  | "You are cord-  |  | |
|  |  Choose a design, add your guests, and     |  |  |  Cordially invite   |  |  ially invited: |  | |
|  |  send by SMS or email. Works in Amharic    |  |  |  Almaz Bekele"      |  |  Almaz Bekele   |  | |
|  |  and English.                              |  |  |                     |  |  invite.et/s/8f"|  | |
|  |                                            |  |  +---------------------+  +-----------------+  | |
|  |  Type guest name:                          |  |                                                | |
|  |  [ Almaz Bekele                       ]    |  |  [ Occasion: Wedding | Funeral | Birthday ... ]| |
|  |                                            |  +-----------------------------------------------+ |
|  |  [Design this invitation]  (Guest Editor)  |                                                    |
|  +--------------------------------------------+                                                    |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  THE SHELF (Template Previews Carousel):                                                           |
|  Occasion Tabs: [All (84)]  [Wedding (10)]  [Funeral (8)]  [Birthday (10)]  [Religious (10)] ...       |
|                                                                                                    |
|  < [Card 1: Telfi]    [Card 2: Garden Arch]    [Card 3: Tazkar]    [Card 4: Eid Emerald] >         |
|  (Click card -> Opens interactive modal with live color-palette swatches + "Use this design")      |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  HOW IT WORKS (Sequential Step alternating layout):                                                |
|  [Step 1: Pick and edit design]  <---------->  [CSS Device Mockup: Editor Canvas]                 |
|  [CSS Device Mockup: CSV/Paste Contacts] <-->  [Step 2: Add guests by paste or CSV]                |
|  [Step 3: Send by SMS or email]  <---------->  [CSS Device Mockup: Real-time Delivery Log]        |
|  [CSS Device Mockup: Guest QR Ticket]   <--->  [Step 4: Guests open RSVP & personalized QR]       |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  AT THE DOOR (Interactive Scanner Demo):                                                           |
|  +-----------------------------------------------------------------------------------------------+ |
|  |  Phone Camera View: [  Blinking Reticle  ]                                                    | |
|  |  [Simulate Scan Button]                                                                       | |
|  |  Result 1 (Click): Green Screen -> "Welcome, Almaz Bekele • Party of 2 • Checked in"          | |
|  |  Result 2 (Click): Red Screen   -> "Already used at 6:42 pm • Entry Denied"                   | |
|  |  Notice: Single-use dynamic QR cryptographic tokens prevent guest pass forwarding.            | |
|  +-----------------------------------------------------------------------------------------------+ |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  BUILT FOR ETHIOPIA (Two-column typographic list - no repetitive cards):                           |
|  Col 1:                                            Col 2:                                          |
|  • Amharic and English typography                  • Telebirr and CBE Birr instant checkout        |
|  • Local +251 phone number deliverability          • Low-bandwidth short SMS links for 2G/3G       |
|  • Ethiopian calendar (Meskerem–Pagume)            • Funeral & Tazkar respectful memorial wording  |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  ALSO INCLUDED (Semantic Definition List <dl>):                                                    |
|  <dt> Thank-you cards      <dd> Send automated thank-you cards to all guests who checked in at door|
|  <dt> Scheduled reminders  <dd> Automated SMS 3 days and morning of event to pending RSVPs        |
|  <dt> Custom SMS & email   <dd> Send broadcast updates, directions, or changes with one click      |
|  <dt> Live counter         <dd> Watch real-time headcount and arrival rates on the organizer host  |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  PRICING TIERS (Live Database Tiers with Gold Highlighted):                                        |
|  +-------------------+  +-------------------+  +-------------------+  +--------------------------+ |
|  | Free              |  | Bronze            |  | Silver            |  | GOLD (Highlighted)       | |
|  | 0 ETB             |  | 499 ETB           |  | 1,999 ETB         |  | 6,999 ETB                | |
|  | 20 Cards          |  | 500 Cards         |  | 5,000 Cards       |  | 40,000 Cards             | |
|  | [Open in Editor]  |  | [Choose Bronze]   |  | [Choose Silver]   |  | [Choose Gold]            | |
|  +-------------------+  +-------------------+  +-------------------+  +--------------------------+ |
|  "1 card = 1 message to 1 guest. No recurring monthly subscriptions; pay per event."              |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  FAQ (Native <details> / <summary> disclosures):                                                   |
|  > What is a card?                                                                                |
|  > Do guests need an app?                                                                          |
|  > What if a guest loses the QR?                                                                   |
|  > Can I use my own design?                                                                        |
|  > Is my guest list private?                                                                       |
|  > How do I pay?                                                                                   |
|                                                                                                    |
+----------------------------------------------------------------------------------------------------+
|                                                                                                    |
|  FINAL CALL TO ACTION & FOOTER:                                                                    |
|  "Ready to create your invitation? Start now without creating an account."                         |
|  [Design an invitation (Button)]                                                                   |
|                                                                                                    |
|  Footer: [EN / አማርኛ]  •  Terms of Service  •  Privacy Policy  •  Contact Support  • (c) 2026 Invite |
+----------------------------------------------------------------------------------------------------+
```

### 4.2 Mobile Wireframe (360px / 768px)

```text
+-----------------------------------+
| [Logo]        [EN|አማ]   [Design]  |
+-----------------------------------+
| HERO:                             |
| Design the card.                  |
| Send it to everyone.              |
| Scan them in at the door.         |
|                                   |
| Type guest name:                  |
| [ Almaz Bekele                  ] |
|                                   |
| +-------------------------------+ |
| | LIVE CARD PREVIEW             | |
| | (Rendered on Canvas)          | |
| | "Almaz Bekele" appears live   | |
| +-------------------------------+ |
|                                   |
| +-------------------------------+ |
| | SMS Preview bubble:           | |
| | "Hi Almaz, you are invited..."| |
| +-------------------------------+ |
|                                   |
| Occasions: (Scrollable pills)     |
| [Wedding] [Funeral] [Birthday]... |
|                                   |
| [ Design this invitation (Btn) ]  |
+-----------------------------------+
| THE SHELF:                        |
| Occasion tabs: [All] [Wedding]... |
| [ [Card 1] [Card 2] [Card 3] ]    |
| (Tap to open full modal preview)  |
+-----------------------------------+
| HOW IT WORKS:                     |
| 1. Pick and edit a design         |
|    [Mini phone preview]           |
| 2. Add guests by paste or CSV     |
|    [Mini phone preview]           |
| 3. Send by SMS or email           |
|    [Mini phone preview]           |
| 4. Guests open RSVP & QR          |
|    [Mini phone preview]           |
+-----------------------------------+
| AT THE DOOR:                      |
| [Camera reticle mockup]           |
| [ Tap to Simulate Scan ]          |
| Status badge: [Success / Reused]  |
+-----------------------------------+
| BUILT FOR ETHIOPIA:               |
| 1-col stacked typographic items   |
+-----------------------------------+
| ALSO INCLUDED:                    |
| Definition list items             |
+-----------------------------------+
| PRICING:                          |
| Stacked tiers with Gold marked    |
| "Most popular for weddings"       |
+-----------------------------------+
| FAQ:                              |
| Native tap-to-expand items        |
+-----------------------------------+
| FOOTER:                           |
| Language switch, Legal, Contact   |
+-----------------------------------+
```

---

## 5. SEO, Performance, and Accessibility Plan

1. **SEO:**
   - Meta title and description in both languages (`en` and `am`).
   - `rel="alternate" hreflang="en"` and `hreflang="am"`.
   - OpenGraph image (`/images/og-invite.png`) featuring composite previews of authentic templates.
   - JSON-LD structured data schema `WebApplication` with Ethiopian localization parameters.
   - Sitemap entry at `/sitemap.xml` with zero admin paths.
2. **Performance:**
   - Self-hosted fonts in `public/fonts/` with `font-display: swap` and preloads in `resources/views/app.blade.php`.
   - CardRenderer lazy loaded or initialized cleanly after first paint; static placeholder during hydration.
   - Shelf card images lazy-loaded as WebP with explicit width and height dimensions to prevent cumulative layout shift (CLS = 0).
3. **Accessibility:**
   - WCAG AA compliant contrast ratios across Ink Indigo (`#1C2140`) on Paper (`#F6F5FA`) and White.
   - Visible keyboard `:focus-visible` rings using Meskel Yellow (`#F4B400`) and Ink outline.
   - CSS `@media (prefers-reduced-motion: reduce)` disables all animations and transitions.
   - Aria labels on tabs, carousel navigation buttons, modal dialogues, and door scanner controls.
