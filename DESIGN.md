---
name: Mission Token — JOURNAL
description: Internal gamification platform where employees complete missions to earn and redeem tokens for real rewards.
colors:
  dark-base: "#091113"
  panel: "#0d1618"
  charcoal: "#3a3e43"
  slate: "#6b6e77"
  silver: "#cecdcd"
  ivory: "#fdfcdf"
  warm-white: "#eeebe1"
  gold: "#dab937"
  gold-light: "#f8e769"
  gold-dark: "#c9a830"
  green: "#518e5c"
  orange: "#d2592a"
  teal: "#4f8b98"
  surface-card: "rgba(255,255,255,0.025)"
  surface-border: "rgba(255,255,255,0.08)"
typography:
  display:
    fontFamily: "Prompt, sans-serif"
    fontSize: "3rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "normal"
  heading:
    fontFamily: "Prompt, sans-serif"
    fontSize: "1.15rem"
    fontWeight: 700
    lineHeight: 1.35
    letterSpacing: "normal"
  body:
    fontFamily: "Prompt, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.55
    letterSpacing: "normal"
  label:
    fontFamily: "Prompt, sans-serif"
    fontSize: "0.63rem"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "0.08em"
  meta:
    fontFamily: "Prompt, sans-serif"
    fontSize: "0.72rem"
    fontWeight: 400
    lineHeight: 1.5
    letterSpacing: "normal"
rounded:
  sm: "6px"
  md: "12px"
  lg: "20px"
  full: "9999px"
spacing:
  xs: "0.5rem"
  sm: "0.75rem"
  md: "1.25rem"
  lg: "2rem"
  xl: "4rem"
components:
  button-primary:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.dark-base}"
    rounded: "{rounded.md}"
    padding: "0.6rem 1.5rem"
  button-primary-hover:
    backgroundColor: "{colors.gold-dark}"
    textColor: "{colors.dark-base}"
  button-ghost:
    backgroundColor: "rgba(255,255,255,0.07)"
    textColor: "{colors.warm-white}"
    rounded: "{rounded.md}"
    padding: "0.6rem 1.5rem"
  card-dark:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.warm-white}"
    rounded: "{rounded.lg}"
  badge-gold:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.dark-base}"
    rounded: "{rounded.full}"
    padding: "0.2rem 0.65rem"
  badge-muted:
    backgroundColor: "rgba(255,255,255,0.12)"
    textColor: "{colors.warm-white}"
    rounded: "{rounded.full}"
    padding: "0.2rem 0.65rem"
  type-badge:
    backgroundColor: "rgba(218,185,55,0.10)"
    textColor: "{colors.gold}"
    rounded: "{rounded.sm}"
    padding: "0.22rem 0.65rem"
---

## Overview

Mission Token ใช้ dark theme เป็นหลัก (`#091113`) เพราะพนักงานเปิดใช้ระหว่างวันบน PC และ dark surface ทำให้สีทอง (`#dab937`) pop อย่างมีความหมาย
ระบบสีทองถูกออกแบบให้เป็น signal ของ "value" — ปรากฏเฉพาะที่มี token, reward, หรือ achievement จริงๆ ไม่ใช้เป็นสีตกแต่งทั่วไป

Visual register: **product** — design serves the workflow (ดูภารกิจ → ส่งงาน → รับ token → แลกรางวัล)
Aesthetic: Operative-Premium dark — คล้าย ops dashboard แต่มี gold warmth

Font เดียวคือ **Prompt** (Google Fonts) ซึ่งรองรับภาษาไทยและ Latin ในตัว ทำให้ระบบ UI เป็นเอกภาพ

## Colors

**Dark Base Palette** — โทนมืดที่ทำจาก warm-tinted neutrals ไม่ใช่ neutral grey ล้วน:

| Token | Hex | บทบาท |
|---|---|---|
| `dark-base` | `#091113` | page background ทุกหน้า dark theme |
| `panel` | `#0d1618` | elevated panels, modals |
| `charcoal` | `#3a3e43` | secondary text บน dark |
| `slate` | `#6b6e77` | muted text, meta info |
| `silver` | `#cecdcd` | borders บน light pages |
| `ivory` | `#fdfcdf` | card bg บน light pages |
| `warm-white` | `#eeebe1` | primary text บน dark, body bg light pages |

**Gold Accent** — ใช้เฉพาะที่มี token value หรือ primary action:

| Token | Hex | บทบาท |
|---|---|---|
| `gold` | `#dab937` | primary accent, token amounts, CTA |
| `gold-light` | `#f8e769` | gold gradient endpoint, highlight |
| `gold-dark` | `#c9a830` | hover states บน gold |

**Semantic Colors:**

| Token | Hex | บทบาท |
|---|---|---|
| `green` | `#518e5c` | success, approved |
| `orange` | `#d2592a` | error, rejected, danger |
| `teal` | `#4f8b98` | info, neutral accent |

**Surface tokens สำหรับ glassmorphism cards** (ใช้อย่างประหยัด ไม่ใช่ default):
- `surface-card`: `rgba(255,255,255,0.025)` + `backdrop-filter: blur(8-12px)`
- `surface-border`: `rgba(255,255,255,0.08)`

## Typography

Font เดียว: **Prompt** (Thai + Latin) โหลดจาก Google Fonts `weights=400;600;700`

| Role | Size | Weight | ใช้ที่ |
|---|---|---|---|
| `display` | 3rem (responsive: 1.75rem มือถือ) | 700 | Page hero titles |
| `heading` | 1.15rem | 700 | Section headings, card titles |
| `body` | 0.875rem | 400 | Paragraphs, descriptions |
| `label` | 0.63rem | 700 + uppercase + tracking-wide | Type badges, status labels |
| `meta` | 0.72rem | 400 | Dates, counts, secondary info |

Body line length: สูงสุด 65ch บน wide layouts
สีหลักบน dark: `warm-white` (`#eeebe1`) สำหรับ heading; `slate` (`#6b6e77`) สำหรับ body muted

## Elevation

4 ระดับ ทั้งหมดบน `dark-base` background:

| Level | Treatment | ใช้ที่ |
|---|---|---|
| 0 (base) | `#091113` flat | page background |
| 1 (card) | `rgba(255,255,255,0.025)` + border `rgba(255,255,255,0.08)` + `blur(8px)` | quest cards, stat cards |
| 2 (panel) | `#0d1618` solid + border | admin tables, modals |
| 3 (overlay) | `rgba(9,17,19,0.85)` + `blur(20px)` | dropdown, tooltip |

Shadow: `0 4px 24px rgba(9,17,19,0.30)` level 1; `0 28px 60px rgba(9,17,19,0.55)` hover state
Glassmorphism (`backdrop-filter: blur`) ใช้ได้ที่ level 1 เท่านั้น ไม่ nest glass ใน glass

## Components

### Button

- **Primary** (`btn-gold`): gold bg + dark text — CTA หลัก, submit, ยืนยัน
- **Ghost** (`btn-dark`): `rgba(255,255,255,0.07)` bg + white text — actions รอง
- **Danger** (`btn-danger`): orange-tinted — delete, reject
- **Outline** (`btn-outline`): transparent + gold border — secondary on dark

Radius: `12px` (`rounded.md`) สำหรับทุก button
Padding: `0.6rem 1.5rem`; ไม่ใช้ padding เกิน 3rem horizontal เพราะ button จะดูกว้างเกิน

### Card

Cards ทุกอันบน dark theme ใช้ `surface-card` pattern:
```css
background: rgba(255,255,255,0.025);
border: 1px solid rgba(255,255,255,0.08);
border-radius: 20px;
backdrop-filter: blur(8px);
```
Hover: `translateY(-6px)` + gold border glow `rgba(218,185,55,0.35)` — ไม่ใช้ `scale` เกิน 1.02

### Badge / Pill

- **Gold** (`badge-gold`): token counts, primary status — `#091113` text บน `#dab937`
- **Muted** (`badge-muted`): secondary counts — `#eeebe1` text บน `rgba(255,255,255,0.12)` + border
- **Type badge** (`type-badge`): "Quiz" / "Photo" labels — gold-tinted transparent

### Progress Bar

Track: `rgba(255,255,255,0.07)`, height `8px`, radius `99px`
Fill: `linear-gradient(90deg, #dab937, #f8e769)` + glow `0 0 10px rgba(218,185,55,0.45)`

### Form Input (dark)

```css
background: rgba(255,255,255,0.05);
border: 1px solid rgba(255,255,255,0.12);
border-radius: 10px;
color: #eeebe1;
```
Focus: border `rgba(218,185,55,0.50)` + shadow glow gold

### Navigation

Sticky top nav: `#091113` bg + bottom border `rgba(255,255,255,0.08)`
Active link: gold underline 2px + gold text
Font weight active: 600; inactive: 400

## Do's and Don'ts

**Do:**
- ใช้ gold เฉพาะที่มี token value หรือ primary action — ให้มันหมายความว่า "รางวัล" เสมอ
- ใช้ glassmorphism อย่างประหยัด เฉพาะ level 1 cards บน dark hero/background
- ให้ Typography มี hierarchy ชัด — display → heading → body ต้องต่างกันอย่างน้อย 1.25×
- ใช้ภาษาไทยเป็นหลัก copy ทุกอย่างที่ user-facing ยกเว้น label ที่เป็น technical เช่น "Token", "Quest"
- Cards hover ต้องมี gold glow — ทำให้ interactive elements รู้สึก responsive

**Don't:**
- ❌ Side-stripe borders (`border-left` หนาๆ บน cards) — ห้ามใช้
- ❌ Gradient text (`background-clip: text`) — ใช้ solid gold แทน
- ❌ Nest glass cards ใน glass cards
- ❌ ใช้ gold เป็น background color ทั่วไปหรือ decorative — มันต้องหมายถึง "มีมูลค่า"
- ❌ Bounce / elastic animation — ใช้ ease-out-quart เท่านั้น
- ❌ White หรือ off-white cards บน dark pages — ทำลาย atmosphere
- ❌ Cards grids ที่ทุก card เหมือนกันทุก pixel — ให้มี visual hierarchy ระหว่าง available vs completed
