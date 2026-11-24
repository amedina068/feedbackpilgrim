# Feedback Pilgrim — Design System

> The visual and UI-copy system of Feedback Pilgrim. This is the reference page. Keep it current.

## Design thesis

Feedback Pilgrim looks like a fast, plain tool that a developer built: dense, dark and calm. Think Linear, Basecamp or a good CLI tool. Do not use gradients, stock photos, blob illustrations or enterprise polish.

**Principles:** dense but calm · monospace as a signature, not a gimmick · dark-mode-native · motion is fast and functional, never decorative · zero corporate stock imagery · a real feedback board is the hero visual.

## Tokens

### Color — dark-first, light as equal partner

```
/* Surfaces (dark, default) */
--bg-base:      #0B0C0E   /* near-black, slight warmth */
--bg-surface:   #131519   /* cards, panels */
--bg-elevated:  #1B1E24   /* popovers, modals */
--border:       #262A31   /* hairline dividers */
--border-strong:#363B44

/* Text */
--text-hi:      #F2F4F7   /* primary */
--text-mid:     #A9B0BC   /* secondary */
--text-low:     #6B7280   /* muted, meta */

/* Brand accent — one confident color, used sparingly */
--accent:       #4F8CFF   /* "pilgrim blue" — links, primary action, vote-active */
--accent-hover: #6BA0FF
--accent-quiet: #4F8CFF1A /* 10% tint for backgrounds */

/* Semantic */
--success: #3FB68B
--warning: #E0A23C
--danger:  #E5564B
--vote:    #4F8CFF   /* upvote active = accent */
```

```
/* Light mode — same hues, inverted lightness; never a separate palette */
--bg-base:      #FFFFFF
--bg-surface:   #F7F8FA   /* wells / recessed only — content prefers the base */
--bg-elevated:  #FFFFFF   /* popovers, modals (lift via border, not fill) */
--border:       #1116200D /* gray-950 @ ~5%, opacity-based */
--border-strong:#11162014 /* gray-950 @ ~8% */
--text-hi:      #0B0C0E
--text-mid:     #4A5160
--text-low:     #6B7280
--accent:       #2C6BF0   /* slightly darker blue for AA contrast on white */
--accent-hover: #1E5AD8
```

**Rules**
- **One accent.** Color earns its place — primary actions, active votes, links. Everything else is greyscale. Multi-color UI reads as consumer/toy; greyscale + one accent reads as tool.
- The neutral ramp is custom (warm near-black), not Tailwind `gray-*`/`slate-*`. If you must reach for a Tailwind default neutral, use `zinc-*` or `neutral-*`, never `gray`/`slate`.
- The accent is blue, never indigo (indigo is the generic SaaS default we are avoiding).
- **Dividers use opacity, not solid hex** — `border-white/5` (dark) / `border-gray-950/5` (light), not a flat `--border` line, so dividers sit correctly on any surface.
- Verify contrast: body text and meta must hit WCAG AA (4.5:1) against their surface; `--text-low` is for non-essential meta only. The light-mode `--accent` is darkened so accent-colored text/links pass AA on white.

### Type

```
--font-sans: "Inter", system-ui, sans-serif;            /* UI + body */
--font-mono: "JetBrains Mono", ui-monospace, monospace; /* the signature */
```

- **Mono is the brand signature** — use it for: the wordmark, numbers (vote counts, **MRR/LTV figures**), labels/tags, status chips, code-ish metadata, board slugs. It says "developer tool" instantly. Don't set body paragraphs in mono.
- Use the **Inter variable font** (`InterVariable`) with `font-display: swap`, and enable OpenType features for the sharper, more technical look: `font-feature-settings: "cv02","cv03","cv04","cv11"`. Tabular figures (`"tnum"`) on any column of numbers that should align (vote counts, MRR).
- Scale (1.25 ratio): `12 / 14 / 16 / 20 / 25 / 31 / 39`. Body 16, UI default 14, meta 12. Never use 12 for body/paragraph copy — 12 is for mono meta/labels only.
- Weights: 400 body, 500 UI labels, 600 headings. **Never 700+** — heavy weight reads marketing-y.
- Tight but readable: line-height 1.5 body, 1.2 headings. `tracking-tight` (≈ -0.01em) on headings above 20px. Use `text-balance` on headings, `text-pretty` on paragraphs.
- **Mono uppercase (status chips, eyebrows) must add `tracking-wide`** — uppercase mono is only legible with widened tracking.

### Space, radius, depth

```
--space: 4 8 12 16 24 32 48 64           /* 4px base grid */
--radius-sm: 6px   --radius-md: 10px   --radius-lg: 14px   /* never pill-round on containers */
--shadow-sm: 0 1px 2px #0006
--shadow-md: 0 8px 24px #0008            /* modals only; dark UI leans on borders > shadows */
```

- Borders do the structural work in dark mode, not big shadows. Hairline border everywhere; reserve shadow for true overlays (modals, popovers, dropdowns).
- **Shadows never pair with a solid border** — when an element has a shadow, define its edge with `ring-1 ring-black/5` (light) instead of a gray border line.
- **Remove shadows in dark mode** (`dark:shadow-none`); define elevation with a slightly lighter fill + a `ring-1 ring-white/5` inset hairline instead.
- Use **concentric radii** on nested rounded elements (a chip inside a card): inner radius = outer radius − padding, so corners stay parallel. For screenshots/images use a fluid radius like `min(1vw, 12px)`.

## Components (the board is the product)

- **Feedback card:** title (16/500) · one-line excerpt (14/`--text-mid`) · upvote control left (mono count, accent when voted) · status chip + author + **MRR badge** + meta (12/mono/`--text-low`) right. Dense list, calm spacing, hover = `--bg-surface` lift. This is the unit users live in — fast and quiet. The card is a true card (independently clickable) — so it earns a border; lists of sub-items inside it use whitespace/dividers, not nested cards.
- **Upvote control:** caret + mono number. Active = `--accent`. The single most-used interaction → instant, optimistic, no spinner. It is a button: real `<button>`, visible `focus-visible` ring, and a 48×48 minimum touch target even when the visual is smaller.
- **Status chip:** mono uppercase 12 with `tracking-wide`, tinted bg (semantic color @ ~10%). `OPEN / PLANNED / IN PROGRESS / SHIPPED`. Honest, plain words — not "Under Consideration."
- **Customer-value badge (the differentiator — make it prominent):** a mono `$MRR` / `LTV` tag on every feedback author, tinted by tier. This is the headline feature of FP. A reader sees "$500/mo" next to one request and "$0" next to the loudest one. Make it legible and ever-present, not a flashy flourish. A roadmap **sort-by-revenue** control is the signature interaction. Badges with an icon use asymmetric padding (`pl-1 pr-2` for a leading icon), never symmetric.
- **Roadmap/WIP board:** three calm columns, same cards, drag = fast. No celebratory confetti.
- **Buttons:** primary = solid `--accent`, 14/500, `--radius-sm`, no gradient. Secondary = `--border` outline, transparent bg. Ghost for tertiary. **One primary per view** (treat a modal as its own view). Secondary buttons must never out-contrast the primary. App-UI buttons are compact (`text-sm`, ~28–38px tall); max two button heights across the app, ≥6px apart.
- **Destructive actions ("Delete", "Remove"):** use a *secondary/muted* style by default — only promote to a solid danger-colored primary inside a dedicated confirm dialog where deleting is the primary action.
- **Changelog/release notes:** dated entries, mono dates, plain prose. The changelog shows that the product is active.
- **Data table (admin users):** `ring-1 ring-white/5` container, `bg-fp-surface` header row with mono uppercase 12 labels and `tracking-wide`, `divide-white/5` rows. Status is a word in a mono chip (`ACTIVE`, `DEACTIVATED`), never only a color. A row action that removes access uses the secondary outline style and asks `confirm()` first.
- **Empty states:** plain, useful, slightly dry-funny in the operator voice. Never an illustration of a person looking confused. On the dashboard, use `x-empty-state`: a title, an optional line of text and an optional secondary link, in a dashed hairline box. No icon by default.
- **Side panel (`x-drawer`):** the mobile menu and the filter panel open in a native `<dialog>` that slides in from the left or the right edge. `--bg-surface` fill, hairline border on the inner edge, `backdrop:bg-black/70`. The slide takes 150ms and stops under `prefers-reduced-motion`. The panel has a title and a close button with a 48x48 touch target.
- **Sidebar link (`x-sidebar-link`):** 14/500, `--text-mid`. The current link gets `--accent` text on a 10% accent tint and `aria-current="page"`. The weight never changes between states.
- **Widget dialog (`WidgetDialog.vue`):** the embeddable widgets run on the customer site, so they use a light surface: white fill, slate text, `ring-1 ring-black/5`. The release notes open as a full-height panel on the right. The testimonial form opens as a centered dialog. The title names the dialog.
- **Dashboard dialog (`Modal.vue`):** a centered native `<dialog>` on `--bg-elevated` with `ring-1 ring-white/10`, `shadow-md` and `backdrop:bg-black/70`. The button that opens it goes in the `trigger` slot. The dialog title has an ID, and `labelledby` names the dialog with it. Escape and a click on the backdrop close it.

## Motion

- Transitions 120–180ms, `ease-out`. Hovers instant. Optimistic UI on votes/status.
- Reserve `transition-*` for things that **move or transform** — not for plain hover color/background swaps (those should be instant).
- No parallax, no scroll-jacking, no decorative loops. Motion only confirms an action or reveals structure.
- Honor `prefers-reduced-motion: reduce` — drop non-essential transitions/transforms for users who ask for it.

## Accessibility (non-negotiable, part of "honest tool")

- Every interactive element is a real `<button>`/`<a>` with a visible `focus-visible` ring (accent outline, `outline-offset-2`). No div-as-button.
- Small/icon controls (upvote, kebab menus) keep a 48×48 minimum touch target even when the glyph is smaller.
- Color is never the only signal: status uses a word + tint, votes use caret + count, not hue alone.
- Contrast hits WCAG AA against the actual surface in both themes; re-check tinted chips and accent-on-color.

## UI copy voice

- Plain and dry. Buttons say what they do: `Create board`, `Add feedback`, `Mark shipped` — not `Get started ✨`.
- Error/empty copy is honest and human: "No feedback yet. Drop your board link where your users already are." Not "Oops! Something went wrong 😢".
- Punctuation: standalone descriptive lines (taglines, tier descriptions, single-line empty-state copy) get a period; list items (feature bullets) do not. Be consistent per page. **Never use emojis** anywhere.
- The landing hero shows a feedback board, not an abstract illustration.

## Tailwind v4 implementation notes

This app runs **Tailwind CSS v4** through the `@tailwindcss/vite` plugin. There is no `tailwind.config.js`. Map the tokens, don't hand-roll hex per component.

- The palette and the fonts are CSS variables in the `@theme` block of `resources/css/app.css` (`--color-fp-base`, `--color-fp-surface`, `--color-fp-hi`, `--color-fp-accent`, and more). Write classes like `bg-fp-surface`, `text-fp-mid`, `text-fp-accent`, `font-mono`.
- Dark mode: the app is dark-native, so make dark the default surface; expose light as a peer theme. Use opacity-based dividers (`border-white/5`, `border-gray-950/5`) so a single class works on any surface.
- v4 utilities such as `inset-ring`, `scheme-only-dark`, `pointer-fine:*` and `size-*` are available.
- `app.css` keeps three v3 defaults in the base layer: the `gray-200` border color, the pointer cursor on buttons, and the `gray-400` placeholder color.
- Load Inter + JetBrains Mono with `font-display: swap` and apply `font-feature-settings` in the base layer.

## Anti-patterns (kill on sight)

Gradients on buttons/heroes · stock photos of smiling teams · 3D blob illustrations · "Trusted by 10,000+ teams" badges · emoji-stuffed CTAs · multi-color category chips · confetti/celebration animation · carousels · pill-shaped containers · drop-shadow-heavy "floating card" hero · indigo as the accent · `gray-*`/`slate-*` neutrals · `font-bold` headings · shadows kept in dark mode · div-as-button without a focus ring · anything that looks like a Series-B landing page. · markup copied from Tailwind UI (Tailwind Plus): its license does not allow it in this open source repository

## The one line
**Make it look like a tool that a developer built for daily use — fast, dark, mono-accented, one confident blue, the MRR badge always visible, a real board as the only hero image.**
