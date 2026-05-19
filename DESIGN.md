---
name: Mission Token
description: Internal gamification platform for JOURNAL — dark operative aesthetic, JOURNAL brand colors, Prompt typeface.
colors:
  dark: "#091113"
  panel: "#0d1618"
  surface: "#111b1e"
  gold: "#dab937"
  gold-dk: "#c9a830"
  gold-l: "#f8e769"
  teal: "#4f8b98"
  green: "#518e5c"
  orange: "#d2592a"
  white: "#eeebe1"
  ivory: "#fdfcdf"
  silver: "#cecdcd"
  slate: "#6b6e77"
  charcoal: "#3a3e43"
  border: "#1e2628"
  border-subtle: "#162022"
typography:
  display:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "2rem"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "normal"
  title:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "1.125rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "normal"
  body:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.65
    letterSpacing: "normal"
  label:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.4
    letterSpacing: "0.02em"
  caption:
    fontFamily: "'Prompt', sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "0.01em"
rounded:
  sm: "6px"
  md: "10px"
  lg: "12px"
  xl: "16px"
  pill: "999px"
  circle: "50%"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "40px"
  2xl: "64px"
components:
  button-primary:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.dark}"
    rounded: "{rounded.md}"
    padding: "10px 24px"
  button-primary-hover:
    backgroundColor: "{colors.gold-dk}"
    textColor: "{colors.dark}"
  button-outline:
    backgroundColor: "transparent"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "10px 24px"
  button-danger:
    backgroundColor: "{colors.orange}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "10px 24px"
  card-dark:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.white}"
    rounded: "{rounded.lg}"
    padding: "{spacing.lg}"
  input-dark:
    backgroundColor: "{colors.panel}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "10px 14px"
  badge-pill:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.slate}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  nav-link:
    textColor: "{colors.slate}"
    rounded: "{rounded.sm}"
    padding: "6px 12px"
---

## Overview

Mission Token ใช้ **dark operative aesthetic** — พื้นผิวสีเข้มชั้น tonal แบบ j-dark → j-panel → surface glass ไม่ใช้ glassmorphism เป็น default แต่ใช้ tonal layering เป็นระบบ elevation หลัก
j-gold (รงทอง) สงวนไว้สำหรับ primary action, token metric, milestone highlight เท่านั้น ไม่กระจายทั่วหน้า
j-teal (ขนคอหางนกยูง) ใช้เป็น secondary accent สำหรับ info state และ secondary data
Prompt เป็น typeface เดียวสำหรับทุก scale — ออกแบบมารองรับภาษาไทยและอังกฤษ

**Color strategy**: Committed — j-dark carries 60%+ ของพื้นที่, j-gold เป็น committed accent ≈20-30%

**Theme rationale**: พนักงานเปิดในที่ทำงาน ทั้งบนจอ 24" และมือถือระหว่างวัน ambient light หลากหลาย dark theme ลดความล้าของตาในระยะยาวและเสริมความรู้สึก "เข้าระบบลับ" ที่สอดกับ brand personality

## Colors

**Primary — ดำเขม่า (j-dark)**
พื้นผิวหลัก `#091113` ไม่ใช่ pure black เจือสีฟ้าเย็นจาก brand book
Panel layer: `#0d1618` สำหรับ card, sidebar, header
Surface layer: `#111b1e` สำหรับ hover state, input bg, nested container

**Primary Accent — รงทอง (j-gold)**
`#dab937` — primary action buttons, token balance, reward price, active nav
`#c9a830` (gold-dk) — hover/pressed state
`#f8e769` (gold-l) — highlight glow, ambient background orb
ใช้ gold เฉพาะจุดที่ผู้ใช้ต้องตัดสินใจหรือสังเกต ไม่ใช้ตกแต่ง

**Secondary Accent — ขนคอหางนกยูง (j-teal)**
`#4f8b98` — info badge, secondary stat, Strava integration indicator
ให้ warmth โดยไม่แข่งกับ gold

**Semantic**
- Success / approved: j-green `#518e5c`
- Error / rejected / danger: j-orange `#d2592a`
- Muted text: j-slate `#6b6e77`
- Secondary text: j-charcoal `#3a3e43`
- Light text / heading on dark: j-white `#eeebe1`

**Borders**
`#1e2628` (border) — card edge, divider
`#162022` (border-subtle) — inner nested border

**Light surfaces** (profile page, admin light-zone)
j-ivory `#fdfcdf` — card bg, j-white `#eeebe1` — body bg

ห้ามใช้ pure `#000000` หรือ `#ffffff` — ทุก neutral ต้องเจือสี dark teal หรือ gold ตาม layer

## Typography

Font family: **Prompt** (Google Fonts) เพียง family เดียว ครอบคลุม Thai + Latin
Load weights: 400, 500, 600, 700

| Role | Size | Weight | Use |
|---|---|---|---|
| display | 2rem | 700 | Page hero number, token balance large |
| headline | 1.5rem | 600 | Section title, modal header |
| title | 1.125rem | 500 | Card heading, nav label |
| body | 1rem | 400 | Paragraph, description, feed item |
| label | 0.875rem | 500 | Button text, badge, form label |
| caption | 0.75rem | 400 | Timestamp, helper text, footnote |

Scale ratio ≥ 1.25 ระหว่าง step (Minor Third)
Body line-length max **65ch** — ห้ามให้ paragraph ยืดเต็ม container บน wide screen
Thai text: line-height 1.65 body, 1.4 title (Prompt ต้องการ leading มากกว่า Latin)

เน้น hierarchy ผ่าน **weight + size contrast** — ห้ามใช้ gradient text (`background-clip: text`) เด็ดขาด

## Elevation

Mission Token ใช้ **tonal elevation** ไม่ใช่ shadow elevation:

| Layer | Color | Use |
|---|---|---|
| Base | `#091113` (j-dark) | Page background |
| Raised | `#0d1618` (j-panel) | Card, sidebar, nav bar |
| Float | `rgba(255,255,255,0.04)` | Hover overlay, input focus bg |
| Ambient glow | `radial-gradient` j-gold-l + blur 48-80px | Background atmosphere หน้า hero/dashboard เท่านั้น |

Box-shadow: **ไม่ใช้ decoratively** — ใช้ได้เฉพาะ focus ring (`0 0 0 4px rgba(9,17,19,0.65)`) และ dropdown overlay
Glassmorphism (`backdrop-filter: blur`): ใช้ได้ **เฉพาะเมื่อมี semantic reason** (เช่น modal บน active content) ไม่ใช้เป็น default card style

Border สร้าง edge definition แทน shadow: `1px solid #1e2628`

## Components

### Button
- **Primary** (`btn-gold`): bg j-gold, text j-dark, radius 10px, padding 10px 24px, font label/600 — hover: bg j-gold-dk
- **Outline** (`btn-outline`): transparent bg, border 1.5px j-gold, text j-white — hover: bg `rgba(218,185,55,0.08)`
- **Dark** (`btn-dark`): bg j-panel, text j-white, border 1px #1e2628 — hover: bg j-surface
- **Danger** (`btn-danger`): bg j-orange, text j-white — hover: opacity 0.88
- Focus ring ทุก button: `outline: 3px solid j-gold; outline-offset: 3px`

### Card
- bg j-panel `#0d1618`, border `1px solid #1e2628`, radius 12px, padding 24px
- ห้าม nested card (card ใน card) เด็ดขาด
- ห้าม side-stripe border (`border-left` > 1px เป็น accent) — ใช้ background tint หรือ leading icon แทน

### Input
- bg j-panel, border `1px solid #1e2628`, radius 10px, padding 10px 14px, text j-white
- Focus: border j-gold + float overlay `rgba(255,255,255,0.04)`
- Placeholder: j-slate

### Badge / Pill
- radius 999px, padding 2px 10px, label/500
- Variants: gold (j-gold bg, j-dark text), teal, green, orange, slate

### Navigation
- bg j-dark, height 60px, border-bottom `1px solid #1e2628`
- Active link: j-gold text + underline 2px j-gold
- Token balance chip: gold badge ข้าง user name

### Quest Card (Flip)
- Front: token reward hero number (display/700 j-gold), title (title/500 j-white)
- Back: full detail + action form
- Transition: CSS 3D perspective flip — `transform-style: preserve-3d`, ease-out-quart 0.45s
- ห้าม animate layout properties (width/height/padding)

## Do's and Don'ts

**Do**
- ใช้ tonal layering (j-dark → j-panel → float) สร้าง depth แทน shadow
- ใช้ j-gold เฉพาะ primary action, token number, active state, milestone
- ใช้ j-teal สำหรับ info/secondary metric ที่ไม่ต้องการ action
- ใช้ weight + size contrast สร้าง typography hierarchy
- ใช้ `ease-out-quart` / `ease-out-expo` สำหรับ transition ทุกประเภท
- Border `1px solid #1e2628` สร้าง card edge บน dark surface
- ให้ body text max 65ch บน wide layout

**Don't**
- ❌ Gradient text (`background-clip: text` + gradient background) — ใช้ solid j-gold แทน
- ❌ Side-stripe border accent (`border-left/right > 1px colored`) — rewrite ด้วย tint หรือ icon
- ❌ Glassmorphism เป็น default card style — ใช้ได้เฉพาะ modal บน active layer
- ❌ Hero-metric template (big number + small label + supporting stats grid + gradient) = SaaS cliché
- ❌ Identical card grid (icon + heading + body text ซ้ำทุก card) — vary density หรือ hierarchy
- ❌ Modal เป็นทางเลือกแรก — หา inline / progressive disclosure ก่อนเสมอ
- ❌ Em dash (`—` หรือ `--`) ใน UX copy — ใช้ colon, semicolon, หรือ parentheses
- ❌ Pure `#000` / `#fff` — ทุก neutral ต้องเจือ brand hue
- ❌ Bounce / elastic easing ใน animation
- ❌ Animate CSS layout properties (width, height, padding, margin)
- ❌ Neon glow หรือ heavy drop-shadow สี gold — ambient radial glow (blur ≥48px, opacity ≤0.12) เท่านั้น
