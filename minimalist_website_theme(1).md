# Minimalist Website Theme Guide

A clean, modern visual system for a minimalist website. Use a neutral foundation, one accent color, consistent typography, and generous whitespace.

## 1. Recommended Color Palette

| UI Element | Color | Hex |
|---|---|---|
| Page background | Warm off-white | `#FAFAF9` |
| Card / surface | White | `#FFFFFF` |
| Primary text | Near black | `#171717` |
| Secondary text | Neutral gray | `#737373` |
| Borders / dividers | Light gray | `#E5E5E5` |
| Primary accent | Blue | `#2563EB` |
| Accent hover | Dark blue | `#1D4ED8` |

**Rule:** Keep the neutrals dominant and use blue sparingly for calls to action, links, and selected states.

## 2. Typography

**Recommended:** Inter for headings, body text, and UI elements.

**Alternative premium pairing:** Manrope for headings + Inter for body and UI text.

| Style | Size | Weight | Suggested line height |
|---|---|---|---|
| H1 | 48–64px desktop | 700 | 1.1–1.2 |
| H2 | 32–40px | 600 | 1.2 |
| H3 | 24–28px | 600 | 1.3 |
| Body | 16–18px | 400 | 1.5–1.7 |
| Small / caption | 14px | 400 | 1.5 |
| Button | 15–16px | 600 | 1.2 |

For mobile screens, scale H1 down to approximately **36–42px**. Use a consistent typographic scale and avoid mixing many font families or weights.

## 3. Components and Layout

- **Buttons:** Solid accent blue with white text for primary actions; outlined or text-only for secondary actions.
- **Cards:** White surfaces, thin `#E5E5E5` borders, and restrained shadows when necessary.
- **Border radius:** 8–16px for most buttons, inputs, and cards.
- **Spacing:** Use an 8px-based spacing system; leave generous space between sections.
- **Navigation:** Simple labels, clear active state, minimal decoration.
- **Icons:** Consistent stroke width and a single icon family.
- **Effects:** Avoid strong gradients, oversized shadows, and excessive animation.

### Example Page Structure

1. Minimal navigation: logo, a few links, one primary CTA.
2. Hero: large concise heading, short supporting copy, one primary action.
3. Features: spacious grid of simple cards.
4. Final CTA: clear message and one action.
5. Footer: understated links and secondary information.

## 4. Alternative Premium Palette

For a warmer, more editorial or premium aesthetic, replace the blue-accent palette with:

| Role | Hex |
|---|---|
| Background | `#F8F7F4` |
| Primary text | `#18181B` |
| Secondary text | `#A3A3A3` |
| Accent | `#C67C4E` |

Pair **Manrope headings** with **Inter body text**. Keep cards white and use subtle neutral borders. Check text contrast before using the lighter gray for small text.

## 5. Starter CSS Tokens

```css
:root {
  --color-background: #FAFAF9;
  --color-surface: #FFFFFF;
  --color-text: #171717;
  --color-text-muted: #737373;
  --color-border: #E5E5E5;
  --color-primary: #2563EB;
  --color-primary-hover: #1D4ED8;

  --font-body: 'Inter', sans-serif;
  --font-heading: 'Inter', sans-serif;

  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
}

body {
  background: var(--color-background);
  color: var(--color-text);
  font-family: var(--font-body);
  line-height: 1.6;
}

h1, h2, h3 {
  font-family: var(--font-heading);
  line-height: 1.2;
}

.button-primary {
  background: var(--color-primary);
  color: #fff;
  border: 0;
  border-radius: var(--radius-md);
  padding: 12px 20px;
  font-weight: 600;
}

.button-primary:hover {
  background: var(--color-primary-hover);
}
```

**Recommendation:** Start with the blue palette and Inter. Choose the terracotta alternative if the brand calls for a warmer, premium look. Do not mix both accent palettes without a specific brand reason.
