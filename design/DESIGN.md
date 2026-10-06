# MusicBox — Style Reference
> Midnight catalog console — violet telemetry on layered charcoal, traced with cool-white borders.

**Theme:** dark

Source measurements are normalized; roles and recommendations are interpreted. Font summary lists are independent, not paired by position.

MusicBox is an internal catalog console for a music catalog: a near-black violet-tinted canvas, cool-white typography, and a single violet (violet-600 `#7c3aed`) that marks anything the operator can act on — the active nav item, the primary button, the running sync, the selected tab. The system is derived from the same editorial-scientific DNA as a marketing surface (monospaced micro-labels with wide tracking, single-weight display type, concentric charcoal surfaces, 1px hairline borders) but is re-tuned for density: an operator reads this panel for hours, so type is smaller, radii are tighter, and contrast does the work that oversized headlines did on a landing page. Surfaces layer as concentric violet-tinted charcoals (`#08070c` → `#0e0d13` → `#15141c` → `#1c1a25`) with no shadows anywhere. Violet-600 is the only filled color in the system; everything else is an outline, a hairline, or a text weight.

## Tokens — Colors

| Name | Value | Token | Role |
|------|-------|-------|------|
| Ice | `#ece9f5` | `--color-ice` | Primary text — near-white with a faint violet cast. 17.4:1 on Void, so it is safe at every size including 10px captions |
| Fog | `#8b8799` | `--color-fog` | Muted and secondary text — helper copy, timestamps, disabled states, table metadata. 5.97:1 on Void, passes AA at body size |
| Linen | `#6f6b80` | `--color-linen` | Meaningful hairline borders — inputs, focusable edges, anything a border that carries state. 4.01:1, clears the 3:1 non-text minimum |
| Steel | `#4a4759` | `--color-steel` | Decorative hairlines — table row rules, card outlines that do not convey state. 2.32:1, never load-bearing |
| Iron | `#2a2835` | `--color-iron` | Surface stepping and inset dividers — the line between Slate and Slate-raised. Not for borders against Void |
| Slate | `#1c1a25` | `--color-slate` | Elevated panel surface — table headers, dropdown menus, hovered rows, the default card |
| Graphite | `#15141c` | `--color-graphite` | Card surface — resource tables, form panels, widget bodies, one step above the shell |
| Carbon | `#0e0d13` | `--color-carbon` | App shell — Filament sidebar and topbar, one step above the canvas |
| Void | `#08070c` | `--color-void` | Page canvas — the deepest background layer, the default body color |
| Violet 600 | `#7c3aed` | `--color-violet-600` | Primary action and active state — the only filled color in the system. 5.70:1 under Ice for button labels, but only 3.65:1 as text on Void, so it is never body copy |
| Violet 400 | `#a78bfa` | `--color-violet-400` | Violet as *text* — links, active nav labels, inline status emphasis, focus rings. 7.64:1 on Void, AA at any size |
| Violet 300 | `#c4b5fd` | `--color-violet-300` | Violet on violet — pressed states and hover text inside a filled violet surface. Never on Void |
| Signal Amber | `#fbbf24` | `--color-signal-amber` | The single warm exception — queued and stale states only. Never decoration, never a button |
| Alert Rose | `#fb7185` | `--color-alert-rose` | Failed and destructive — sync failures, validation errors, destructive actions. Outline only, never filled |

## Tokens — Typography

### Instrument Sans — UI, tables, and display type — the geometric grotesque already installed as `--font-sans`. Headings are set at weight 400 and command through size and tracking, never through bold; at panel scale the ceiling is 32px rather than a marketing 96px, but the single-weight rule holds — a table column header at 500 exists only where it is a label, never to shout. Body runs 13-14px. · `--font-sans`
- **Weights:** 400 (display, body), 500 (field labels, table column headers), 600 (stat numerals only)
- **Sizes:** 10px, 12px, 13px, 14px, 18px, 24px, 32px
- **Line height:** 1.00-1.60
- **Letter spacing:** -0.02em at 32px, -0.01em at 24px and below
- **Role:** Display and heading type — Instrument Sans weight 400. Page titles reach 32px with line-height 1.10, section titles 24px. Body-level type at 13-14px handles table cells, form inputs, and descriptions.

### JetBrains Mono — Micro-labels, IDs, timestamps, and enum values — monospaced at 10-12px with wide tracking (0.08-0.16em). Every ULID, every enum badge value, every timestamp, and every uppercase field label is set here. The wide tracking on uppercase labels creates an instrument-panel read: a row of records that looks like telemetry, not a spreadsheet. · `--font-mono`
- **Weights:** 400, 500
- **Sizes:** 10px, 11px, 12px
- **Line height:** 1.00-1.50
- **Letter spacing:** 0.08em at 12px, 0.16em at 10-11px
- **Role:** Micro-labels, ULIDs, ISO timestamps, enum badge text, unit suffixes, and small-caps column headers.

### Type Scale

| Role | Family | Weight | Size | Line Height | Letter Spacing | Token |
|------|--------|--------|------|-------------|----------------|-------|
| caption | JetBrains Mono | 500 | 10px | 1 | 1.6px | `--text-caption` |
| label | JetBrains Mono | 500 | 12px | 1.2 | 0.96px | `--text-label` |
| body-sm | Instrument Sans | 400 | 13px | 1.45 | 0.13px | `--text-body-sm` |
| body | Instrument Sans | 400 | 14px | 1.6 | 0.14px | `--text-body` |
| title | Instrument Sans | 400 | 18px | 1.30 | -0.18px | `--text-title` |
| heading | Instrument Sans | 400 | 24px | 1.20 | -0.24px | `--text-heading` |
| display | Instrument Sans | 400 | 32px | 1.10 | -0.64px | `--text-display` |

## Tokens — Spacing & Shapes

**Base unit:** 4px

**Density:** compact — this is a data console, not a marketing page. Table rows sit at 44px, form fields at 40px, and card padding at 20px.

### Spacing Scale

| Name | Value | Token |
|------|-------|-------|
| 4 | 4px | `--spacing-4` |
| 8 | 8px | `--spacing-8` |
| 12 | 12px | `--spacing-12` |
| 16 | 16px | `--spacing-16` |
| 20 | 20px | `--spacing-20` |
| 24 | 24px | `--spacing-24` |
| 32 | 32px | `--spacing-32` |
| 48 | 48px | `--spacing-48` |
| 64 | 64px | `--spacing-64` |

### Border Radius

| Element | Value |
|---------|-------|
| tags | 6px |
| buttons | 8px |
| inputs | 8px |
| cards | 12px |
| largeCards | 16px |
| pills | 999px |

### Layout

- **Page max-width:** 1400px
- **Sidebar width:** 256px
- **Section gap:** 48px
- **Card padding:** 20px
- **Table row height:** 44px
- **Element gap:** 12px

## Components

### Primary Action Button
**Role:** The one filled element in the system — 'Save', 'Sync catalog', 'Create artist'

Solid violet-600 background with Ice label at weight 500, 8px radius, 36-40px height, 12-16px horizontal padding. Label in Instrument Sans 14px. Filled violet is a deliberate exception: it marks the single action the operator is expected to take on a screen. Everything else is an outline. Pressed state shifts to violet-700; hover keeps violet-600 and adds nothing — no shadow, no scale.

### Outlined Action Button
**Role:** Secondary actions — 'Cancel', 'Force sync', 'Preview', anything adjacent to the primary

Transparent background with a 1px Linen border, 8px radius, matching 36-40px height. Label in Instrument Sans 14px weight 400 at Fog. Hover lifts the border to violet-400 and the label to Ice. The outlined treatment means a screen with six available actions still shows exactly one thing to press.

### Destructive Action Button
**Role:** Delete and irreversible actions — 'Delete release', 'Clear catalog'

Transparent background, 1px Alert Rose border, 8px radius, Alert Rose label at 14px. Never filled. Confirm dialogs keep the same rose outline; the confirm button inverts to a rose fill only at the moment of commitment, so a misread confirm dialog still requires one more deliberate press.

### Sidebar Navigation Item
**Role:** Filament sidebar entries — Artists, Releases, My Library, plus resource sub-items

Instrument Sans 14px weight 400 at Fog when inactive, Ice at 400 when hovered, and violet-400 with a 2px violet-600 left bar when active. 40px row height, 20px horizontal padding, 8px radius. The left bar is the active signal — never a filled pill behind the label, which would put a second violet block on screen next to the primary button.

### Resource Table
**Role:** The Artists, Releases, and library tables — the densest surface in the product

Graphite card surface, 12px radius, 1px Iron hairline outline, no shadow. Column headers are uppercase JetBrains Mono 10px at Fog with 0.16em tracking, sitting on a Slate header row. Row height 44px, cell padding 12px/20px, and a 1px Iron rule between rows that disappears on hover when the row lifts to Slate. Identifiers — ULIDs, `youtube_music_id`, ISO timestamps — are JetBrains Mono 12px at Fog so they scan vertically as a data column, never competing with the primary label.

### Sync Status Badge
**Role:** The observable state of a catalog sync, from `SyncStatus`

Pill radius 999px, 6px vertical / 10px horizontal padding, transparent background with a 1px border and JetBrains Mono 10px uppercase label at 0.16em tracking. Four states, four colors, no exceptions: **idle** — Fog border, Fog text. **queued** — Signal Amber border, Signal Amber text. **running** — violet-600 border, violet-400 text. **failed** — Alert Rose border, Alert Rose text. The badge is the only place status color appears, and running is the only state that shares the primary violet, because it is the only state where work is actively happening.

### Release Thumbnail Tile
**Role:** Cover art in tables, cards, and the artist discography

Square `thumbnail_url` from the provider, 40px in tables with 6px radius, 120px on discography cards with 12px radius and rounded bottom corners. Rendered desaturated and darkened to 80% opacity against the charcoal shell so a grid of covers reads as one grey mass with the artist name carrying the row; hover restores full color and full opacity in 120ms. Missing art falls back to a Graphite cell with a JetBrains Mono 10px `NO_ART` label in Fog — never a broken-image icon.

### Stat Tile
**Role:** Dashboard counters — artists cataloged, releases synced, failures

Carbon surface, 12px radius, 1px Iron hairline, 20px padding. Numeral in Instrument Sans 600 at 32px with -0.02em tracking in Ice, label beneath in JetBrains Mono 10px uppercase Fog at 0.16em. The numeral is the only 600-weight text in the product; the label is the only place uppercase mono appears without a leading border. A tile in an alert state keeps its Carbon surface and turns only the numeral Alert Rose — the tile never fills.

### Form Field
**Role:** Create/edit forms for Artist, Release, and library entries

Label in JetBrains Mono 10px uppercase Fog at 0.16em tracking, sitting 8px above the input. Input is Carbon background with a 1px Linen border, 8px radius, 40px height, Instrument Sans 14px in Ice. Focus is a 1px violet-400 border plus a 3px `rgba(167,139,250,0.18)` ring — the ring is a tint of the same violet, never a second hue. Validation errors swap the border to Alert Rose and place the message below in Instrument Sans 13px at Alert Rose; the field never uses a red fill.

### Hairline Panel Frame
**Role:** The wireframe-style outlined boxes — the empty-state container, the detail-view wrapper, the sync-progress panel

Carbon or Graphite surface with a 1px Linen border, 12px radius, transparent padding. Centered content in mono. This is the same wireframe-in-light idea as the reference surface, minus the corner dots — at console density the dots are noise, and the 1px frame is already the strongest signal available.

## Do's and Don'ts

### Do
- Fill exactly one thing per screen — the primary action gets violet-600, every other button is an outline in Linen, Fog, or Alert Rose
- Use violet-400 for violet *text* and violet-600 for violet *fills and borders* — violet-600 on Void is only 3.65:1 and fails as body copy
- Set every ULID, ISO timestamp, enum badge value, and uppercase field label in JetBrains Mono at 10-12px with 0.16em tracking — the telemetry read is the signature, not optional
- Set page titles and section titles at weight 400 and command through size — add 32px, never 700
- Layer surfaces in concentric violet-tinted charcoals (`#08070c` → `#0e0d13` → `#15141c` → `#1c1a25`) and never with box-shadow
- Keep every border at 1px, using Linen when it carries state and Steel when it is purely decorative
- Map status color to the `SyncStatus` enum and nothing else — idle is grey, queued is amber, running is violet, failed is rose

### Don't
- Do not fill more than one element with violet on a screen — two filled violets read as a mistake, not a hierarchy
- Do not use violet-600 as body text or as a link color on dark surfaces; use violet-400
- Do not use Signal Amber or Alert Rose for anything decorative, and never as a background fill — they are semantic status colors only
- Do not add shadows or drop-shadow elevation — depth comes from surface stepping and hairlines. The one exception is a true overlay (dropdown, modal, command palette), which must float above the panel to be readable
- Do not set Instrument Sans above weight 600, and never use 600 outside stat numerals
- Do not raise card radii past 16px or leave inputs and buttons above 8px — the 999px pill radius belongs to status badges and tag chips only
- Do not add gradients. The only texture in the product is the release artwork
- Do not introduce a second accent hue. The system is violet + one semantic amber + one semantic rose, and that is the whole palette

## Surfaces

| Level | Name | Value | Purpose |
|-------|------|-------|---------|
| 0 | Void | `#08070c` | Page canvas — the deepest background layer and default body color |
| 1 | Carbon | `#0e0d13` | App shell — sidebar, topbar, inputs, empty-state frames |
| 2 | Graphite | `#15141c` | Card surface — resource tables, form panels, stat tiles, widget bodies |
| 3 | Slate | `#1c1a25` | Most common elevated surface — table headers, dropdown menus, hovered rows |

## Elevation

No shadows are used on any in-page element. Depth is achieved exclusively through stepped violet-tinted surface colors (`#08070c` → `#0e0d13` → `#15141c` → `#1c1a25`) plus 1px hairlines, Linen when the border carries state and Iron or Steel when it does not. This keeps the console diagrammatic — a catalog is a ledger, and ledgers are ruled, not cast in relief. The single sanctioned exception is a true overlay (dropdown menu, modal dialog, command palette): an overlay leaves the page plane, so it gets Filament's default shadow to communicate that fact.

## Imagery

Artwork is the product's only imagery, and it is never decorative. Release covers are square `thumbnail_url` images from the provider, shown at 40px in tables and 120px on discography cards, always desaturated to 80% opacity against the charcoal shell and restored on hover — a grid of twenty covers must read as one surface, not a wall of noise. Artist identity is a single monogram: the operator's avatar comes from Blobatar, artist rows use the provider thumbnail when present and fall back to a Graphite cell with a mono `NO_ART` label. No illustration, no iconography beyond Filament's built-in outline set, no 3D, no marketing photography. The visual vocabulary is restricted to: release artwork, hairlines, and status badges.

## Layout

The shell is a fixed 256px sidebar against a Void canvas, with the content column capped at 1400px and padded 32px. The sidebar is Carbon and the content area is Void, so the shell boundary is carried by the surface step rather than a border. Each resource is a Graphite card holding a table; the page title sits 32px above it in Ice at 32px, with a mono breadcrumb and the primary action right-aligned on the same baseline. Forms use a single column capped at 640px with 20px field gaps — an operator correcting a record does not need a two-column grid. Section gaps are 48px, tight enough that a dashboard, a table, and its action row read as one screen rather than three. Density wins over air: the whole point is that an operator can see a decade of records and their sync state without scrolling.

## Agent Prompt Guide

primary action: violet-600 fill, everything else outlined
**Quick Color Reference**
- Text (primary): #ece9f5
- Text (secondary): #8b8799
- Violet (links, active, status): #a78bfa
- Violet (fill, primary action): #7c3aed
- Background (page): #08070c
- Background (card): #15141c
- Border (state-carrying): #6f6b80
- Border (decorative): #4a4759
- Status queued: #fbbf24 · failed: #fb7185

**3-5 Example Component Prompts**

1. **Primary button**: 40px height, 8px radius, violet-600 fill, Instrument Sans 14px weight 500 label in Ice, 16px horizontal padding. Hover holds violet-600 with no shadow; pressed drops to violet-700. No outline, no icon.

2. **Secondary button**: identical metrics, transparent background, 1px Linen border, Fog label. Hover lifts border to violet-400 and label to Ice. Used for Cancel, Force sync, and Preview — never for the action the operator came to take.

3. **Sync status badge**: pill (999px), transparent background, 1px border in the state's color, JetBrains Mono 10px uppercase at 0.16em tracking, 6px/10px padding. Running is violet-600 border with violet-400 text; queued is amber; failed is rose; idle is Fog. The only status-colored element on screen.

4. **Resource table**: Graphite card, 12px radius, 1px Iron outline. Header row on Slate with uppercase JetBrains Mono 10px Fog labels at 0.16em. 44px rows, 12px/20px cell padding, 1px Iron row rules, hover lifts the row to Slate. ULIDs, external IDs, and timestamps in JetBrains Mono 12px Fog; titles in Instrument Sans 14px Ice.

5. **Stat tile**: Carbon surface, 12px radius, 20px padding, 1px Iron hairline. Numeral in Instrument Sans 600 at 32px Ice — the only 600 weight in the product — with a JetBrains Mono 10px uppercase Fog label beneath. Alert state recolors the numeral to Alert Rose; the tile surface never changes.

**Type Scale Reference**
- Display (32px / 1.10 / -0.02em) — resource page titles
- Heading (24px / 1.20 / -0.01em) — section titles, card titles
- Title (18px / 1.30 / -0.01em) — entity names in detail views
- Body (14px / 1.60) — table cells, descriptions, buttons
- Body-sm (13px / 1.45) — helper text, validation messages
- Label (12px mono / 0.08em) — mono values, IDs, timestamps
- Caption (10px mono / 1.00 / 0.16em) — uppercase field labels, status badges

## Decorative Motif System

There is no decorative motif in this product, and that is the decision. The reference surface uses a sparkle mark and a particle canvas as section punctuation; a catalog console has no sections to punctuate and no atmosphere to establish, so both are dropped rather than translated. The system's only recurring motif is the **1px hairline in a violet-tinted neutral**, which appears on every card, input, frame, and table rule. It is deliberately repetitive and deliberately unremarkable: the console should feel like ruled ledger paper, and a decorative mark would break that instantly. If a future surface needs a visual signature, the honest option is a status-colored badge, not an ornament.

## Color Temperature Discipline

The system is cool by construction — every neutral is violet-tinted, so nothing on screen is a truly neutral grey and the whole surface feels like one material. Violet-600 is the primary and only fill; violet-400 carries it whenever the violet needs to be read as text. Exactly two warm colors exist and both are semantic: Signal Amber for queued, Alert Rose for failed and destructive. They never appear together, never decorate, and never fill a button. This is why the queue state reads instantly — amber is the one color that cannot be confused with chrome, so a queued row is legible at a glance in a table of forty grey ones. Adding a third warm hue would collapse the signal into decoration.

## Similar Brands

- **Linear** — Same violet-on-near-black console palette and the same refusal to use shadows; closer to this product's density than the reference surface it derives from
- **Vercel** — Dark canvas with hairline borders and a single accent, but its accent is used more freely on surfaces; this system keeps violet locked to fill and active state
- **Supabase** — Dark dashboard with the same stepped-charcoal approach and restrained accents, though it leans on green for success where this system treats green as absent
- **Prism** (macOS client) — Violet accent on graphite with monospaced metadata; similar telemetry read, lighter surfaces
- **Grafana** — Dark data console with dense tables and status badges, but leans on saturated fills and gradients where this system stays flat and outlined

## Quick Start

### CSS Custom Properties

```css
:root {
  /* Colors — surfaces */
  --color-void: #08070c;
  --color-carbon: #0e0d13;
  --color-graphite: #15141c;
  --color-slate: #1c1a25;

  /* Colors — lines */
  --color-iron: #2a2835;
  --color-steel: #4a4759;
  --color-linen: #6f6b80;

  /* Colors — text */
  --color-fog: #8b8799;
  --color-ice: #ece9f5;

  /* Colors — accent and status */
  --color-violet-300: #c4b5fd;
  --color-violet-400: #a78bfa;
  --color-violet-600: #7c3aed;
  --color-violet-700: #6d28d9;
  --color-signal-amber: #fbbf24;
  --color-alert-rose: #fb7185;

  /* Typography — Font Families */
  --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Typography — Scale */
  --text-caption: 10px;
  --leading-caption: 1;
  --tracking-caption: 1.6px;
  --text-label: 12px;
  --leading-label: 1.2;
  --tracking-label: 0.96px;
  --text-body-sm: 13px;
  --leading-body-sm: 1.45;
  --tracking-body-sm: 0.13px;
  --text-body: 14px;
  --leading-body: 1.6;
  --tracking-body: 0.14px;
  --text-title: 18px;
  --leading-title: 1.3;
  --tracking-title: -0.18px;
  --text-heading: 24px;
  --leading-heading: 1.2;
  --tracking-heading: -0.24px;
  --text-display: 32px;
  --leading-display: 1.1;
  --tracking-display: -0.64px;

  /* Typography — Weights */
  --font-weight-regular: 400;
  --font-weight-medium: 500;
  --font-weight-semibold: 600;

  /* Spacing */
  --spacing-unit: 4px;
  --spacing-4: 4px;
  --spacing-8: 8px;
  --spacing-12: 12px;
  --spacing-16: 16px;
  --spacing-20: 20px;
  --spacing-24: 24px;
  --spacing-32: 32px;
  --spacing-48: 48px;
  --spacing-64: 64px;

  /* Layout */
  --page-max-width: 1400px;
  --sidebar-width: 256px;
  --section-gap: 48px;
  --card-padding: 20px;
  --table-row-height: 44px;
  --element-gap: 12px;

  /* Border Radius */
  --radius-lg: 8px;
  --radius-xl: 12px;
  --radius-2xl: 16px;
  --radius-pill: 999px;

  /* Surfaces */
  --surface-void: #08070c;
  --surface-carbon: #0e0d13;
  --surface-graphite: #15141c;
  --surface-slate: #1c1a25;
}
```

### Tailwind v4

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';

@theme {
  /* Surfaces */
  --color-void: #08070c;
  --color-carbon: #0e0d13;
  --color-graphite: #15141c;
  --color-slate: #1c1a25;

  /* Lines */
  --color-iron: #2a2835;
  --color-steel: #4a4759;
  --color-linen: #6f6b80;

  /* Text */
  --color-fog: #8b8799;
  --color-ice: #ece9f5;

  /* Accent and status */
  --color-violet-300: #c4b5fd;
  --color-violet-400: #a78bfa;
  --color-violet-600: #7c3aed;
  --color-violet-700: #6d28d9;
  --color-signal-amber: #fbbf24;
  --color-alert-rose: #fb7185;

  /* Typography */
  --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

  /* Typography — Scale */
  --text-caption: 10px;
  --leading-caption: 1;
  --tracking-caption: 1.6px;
  --text-label: 12px;
  --leading-label: 1.2;
  --tracking-label: 0.96px;
  --text-body-sm: 13px;
  --leading-body-sm: 1.45;
  --tracking-body-sm: 0.13px;
  --text-body: 14px;
  --leading-body: 1.6;
  --tracking-body: 0.14px;
  --text-title: 18px;
  --leading-title: 1.3;
  --tracking-title: -0.18px;
  --text-heading: 24px;
  --leading-heading: 1.2;
  --tracking-heading: -0.24px;
  --text-display: 32px;
  --leading-display: 1.1;
  --tracking-display: -0.64px;

  /* Spacing */
  --spacing-4: 4px;
  --spacing-8: 8px;
  --spacing-12: 12px;
  --spacing-16: 16px;
  --spacing-20: 20px;
  --spacing-24: 24px;
  --spacing-32: 32px;
  --spacing-48: 48px;
  --spacing-64: 64px;

  /* Border Radius */
  --radius-lg: 8px;
  --radius-xl: 12px;
  --radius-2xl: 16px;
  --radius-pill: 999px;
}
```

### Filament Wiring

Filament picks the palette from PHP, not from CSS, and picks accessible shades from it at render time — so violet must be registered on the panel, not overridden in the stylesheet. A raw `--primary-*` override in a `<style>` block repaints without re-running Filament's WCAG shade resolver and can produce mismatched pairs. Register the palette in PHP, then add this system's own tokens via a custom theme file.

`app/Providers/Filament/AdminPanelProvider.php` — pass violet-600 as a hex string; Filament generates the 50-950 ramp itself:

```php
use Filament\Enums\ThemeMode;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->colors([
            'primary' => '#7c3aed',
            'danger' => Color::Rose,
            'gray' => Color::Zinc,
        ])
        ->defaultThemeMode(ThemeMode::Dark)
        ->viteTheme('resources/css/filament/admin/theme.css');
}
```

`ThemeMode::Dark` keeps the switcher available; use `->darkMode(isForced: true)` only if light mode must be removed outright. Note the panel currently registers `Color::Purple` (purple-600, `#9333ea`) — violet-600 is a distinct hue and must replace it, not sit next to it.

Generate the theme file, which imports Filament's compiled CSS so Tailwind v4 scans both:

```sh
php artisan make:filament-theme admin
```

`resources/css/filament/admin/theme.css`:

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/**/*';
@source '../../../../resources/views/filament/**/*';
@source '../views';

@theme {
  --color-void: #08070c;
  --color-carbon: #0e0d13;
  --color-graphite: #15141c;
  --color-slate: #1c1a25;

  --color-iron: #2a2835;
  --color-steel: #4a4759;
  --color-linen: #6f6b80;

  --color-fog: #8b8799;
  --color-ice: #ece9f5;

  --color-violet-300: #c4b5fd;
  --color-violet-400: #a78bfa;
  --color-violet-600: #7c3aed;
  --color-violet-700: #6d28d9;
  --color-signal-amber: #fbbf24;
  --color-alert-rose: #fb7185;

  --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
```

Add the theme file to the Vite input, then `npm run build`. Filament's `->font()` sets only the body family and does not cover the mono family, so fonts come from the `@theme` block above — either drop `->font()` and let CSS own both, or keep the two in sync.