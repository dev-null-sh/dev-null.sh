---
name: devnull
description: A social network for developers, drawn as a git log.
colors:
  shell: "#f6f7f8"
  surface: "#ffffff"
  ink: "#121417"
  ink-muted: "#5b616b"
  hairline: "#e1e4e8"
  rail: "#d3d7dd"
  wash: "#eef0f2"
  primary: "#3b6fe0"
  primary-hover: "#2e5ccb"
  primary-soft: "#e8effc"
  primary-ink: "#ffffff"
  destructive: "#c92a2a"
  lane-0: "#d9480f"
  lane-1: "#2f9e44"
  lane-2: "#9c36b5"
  lane-3: "#0c8599"
  lane-4: "#c2255c"
  lane-5: "#b7791f"
  shell-dark: "#111112"
  surface-dark: "#18181a"
  popover-dark: "#1f1f22"
  ink-dark: "#ececec"
  ink-muted-dark: "#a1a1a6"
  hairline-dark: "#2a2a2d"
  rail-dark: "#36363a"
  wash-dark: "#232326"
  primary-dark: "#7aa2f7"
  primary-hover-dark: "#94b5f9"
  primary-soft-dark: "#18233a"
  destructive-dark: "#ff6b6b"
  lane-0-dark: "#ff8a4c"
  lane-1-dark: "#51cf66"
  lane-2-dark: "#da77f2"
  lane-3-dark: "#3bc9db"
  lane-4-dark: "#f06595"
  lane-5-dark: "#fcc419"
typography:
  display:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(3.5rem, 12vw, 6rem)"
    fontWeight: 600
    lineHeight: 1
    letterSpacing: "-0.04em"
  headline:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "clamp(1.75rem, 4vw, 2.5rem)"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: 1.33
    letterSpacing: "-0.025em"
  section:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.55
    letterSpacing: "-0.025em"
  composer:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "17px"
    fontWeight: 400
    lineHeight: 1.375
  body:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 400
    lineHeight: 1.625
    fontFeature: "'ss01', 'cv11'"
  nav:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "15px"
    fontWeight: 500
  small:
    fontFamily: "Geist, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  mono:
    fontFamily: "Geist Mono, ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
    fontSize: "13px"
    fontWeight: 400
    fontFeature: "'tnum'"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  full: "9999px"
spacing:
  row-y: "12px"
  gutter: "16px"
  gutter-wide: "24px"
  rail: "44px"
  rail-wide: "56px"
  feed-column: "620px"
  nav-column: "256px"
  aside-column: "320px"
  shell-max: "1264px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-ink}"
    typography: "{typography.small}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "36px"
  button-primary-hover:
    backgroundColor: "{colors.primary-hover}"
  button-outline:
    backgroundColor: "{colors.shell}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "36px"
  button-outline-hover:
    backgroundColor: "{colors.wash}"
  button-ghost-hover:
    backgroundColor: "{colors.wash}"
    textColor: "{colors.ink}"
  button-destructive:
    backgroundColor: "{colors.destructive}"
    textColor: "{colors.primary-ink}"
    rounded: "{rounded.md}"
    padding: "0 12px"
    height: "32px"
  nav-item:
    textColor: "{colors.ink-muted}"
    typography: "{typography.nav}"
    rounded: "{rounded.lg}"
    padding: "12px"
  nav-item-hover:
    backgroundColor: "{colors.wash}"
    textColor: "{colors.ink}"
  nav-item-active:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.primary}"
  page-tab:
    textColor: "{colors.ink-muted}"
    typography: "{typography.nav}"
    padding: "14px 0"
  page-tab-active:
    textColor: "{colors.ink}"
  commit-row:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    typography: "{typography.body}"
    padding: "12px 16px 12px 0"
  graph-node:
    backgroundColor: "{colors.lane-0}"
    rounded: "{rounded.full}"
    size: "14px"
  avatar:
    backgroundColor: "{colors.wash}"
    rounded: "{rounded.full}"
    size: "40px"
---

# Design System: devnull

## Overview

**Creative North Star: "The Commit Graph"**

Every post is a commit and the timeline is the log. The system draws a literal `git log --graph` down the left edge of the feed: a vertical rail, one ringed node per commit, the segment below each node colored by its author's lane. Everything else stays out of the way so the graph reads. The topology is the familiar three-column social layout (nav, a 620px feed, an aside), stripped of the default social skin: no engagement row, no trend panels, no pill-shaped blue chrome.

The register is a well-lit terminal rather than a dark hacker cosplay. A cool near-white shell holds a white feed column cut by hairline cool-gray rules; ink is near-black. Geist carries prose, Geist Mono carries everything a machine would print: handles, short hashes, relative times, counts, character budgets, `fatal:` lines, and `null` values. Dark mode is a faithful mirror on graphite, with the lanes and primary lifted for luminance rather than redesigned.

Color is rationed. Six author lane colors exist only inside the graph and its direct echoes (profile ring, handle dot). One blue does the work of action and location: the primary button, the current nav item, the active tab underline, links. Motion is the graph's own: a node pops onto the rail when you commit, rail segments draw downward on the landing page, and a deleted commit prints `rm {hash} → /dev/null` before its row collapses.

**Key Characteristics:**
- A vertical graph rail with lane-colored nodes and segments is the signature and the only decorative device.
- Lane color is identity, blue is action; they never trade jobs.
- Flat surfaces separated by hairlines, not shadows or cards.
- Mono for machine output, sans for human prose, both from the Geist family.
- Dry, literal copy; empty and terminal states speak in git's voice.

## Colors

A cool, nearly colorless shell with one working blue and six author lanes reserved for the graph.

### Primary
- **Commit Blue** (light `primary`, dark `primary-dark`): the primary button ("Commit", "Follow", "Sign in with GitHub", "Edit on GitHub"), links, focus rings, caret color, the active tab underline, and the active nav item's text.
- **Pressed Commit Blue** (`primary-hover`): hover state of the primary button only.
- **Blue Wash** (`primary-soft`): fill behind the active nav item and the selected appearance option; also the text selection color.

### Secondary
- **Author Lanes** (`lane-0` through `lane-5`, burnt orange, green, violet, teal, rose, ochre; each with a `-dark` lift): chosen by the user in Settings → Profile, otherwise assigned by hashing the user id (`User::automaticLane()`); every serialized user carries the effective `lane` (0–5) and the frontend paints it with `laneColor(user.lane)`. Used only for graph nodes, rail segments, the staged composer node, the 8px dot before a handle in profile contexts, and the 2px ring around a profile avatar. Never text, never fills behind text, never buttons.

### Tertiary
- **Rm Red** (`destructive`): delete affordances (trash icon on hover, inline confirm wash at 8% opacity, destructive button), "Unfollow" on hover, the composer counter at 0.

### Neutral
- **Cool Shell** (`shell`): page background around the feed column and side columns; also the hole punched in each node.
- **Feed White** (`surface`): the feed column, sticky page headers, and the mobile bottom nav.
- **Near-Black Ink** (`ink`): all primary text.
- **Muted Ink** (`ink-muted`): handles, times, hashes, secondary copy, inactive nav and tabs, the rail terminator line.
- **Hairline** (`hairline`): every divider; column borders, row separators, tab bar, aside section rule.
- **Rail Gray** (`rail`): the rail segment above the first commit, the staged composer's dashed tail, aside list bullets, the scrollbar thumb, and input strokes.
- **Hover Wash** (`wash`): hover background for nav items, tabs, ghost and outline buttons; avatar placeholder.

### Named Rules
**The Lanes Are Identity Rule.** A lane color appears only where it marks an author inside or beside the graph. If a lane color is carrying meaning that isn't "this person", it is wrong.

**The One Blue Rule.** Blue means "you can act here" or "you are here". It is never decorative and never assigned to an author lane.

## Typography

**Display Font:** Geist (with ui-sans-serif, system-ui)
**Body Font:** Geist (with ui-sans-serif, system-ui), stylistic sets `ss01` and `cv11` on
**Label/Mono Font:** Geist Mono (with ui-monospace, SFMono-Regular, Menlo), tabular numerals

**Character:** One family, two voices. The sans speaks for people; the mono speaks for the machine. Both are self-hosted (Geist 400/500/600/700, Geist Mono 400/500).

### Hierarchy
- **Display** (600, clamp(3.5rem, 12vw, 6rem), line-height 1, -0.04em): the landing-page wordmark only.
- **Headline** (600, clamp(1.75rem, 4vw, 2.5rem), 1.25): the landing-page statement.
- **Title** (600, 1.5rem, tight tracking): a profile's name.
- **Section** (600, 1.125rem, tight tracking): settings section headings; sticky page headers use 600 at 1rem.
- **Composer** (400, 17px, 1.375): the commit textarea, one step larger than the commits it produces.
- **Body** (400, 15px, 1.625): commit bodies, author names (600), bios; pre-wrapped, max line set by the 620px column.
- **Nav** (500, 15px): side nav items and page tabs.
- **Small** (400, 0.875rem): aside copy, metadata lists, helper text, button labels.
- **Mono** (400, 13px; 500 for counts): handles, short hashes, relative times, character counter, `fatal:` lines, field keys and `null` values in settings.

### Named Rules
**The Machine Speaks Mono Rule.** If git or a shell would print it (a hash, a handle, a count, a timestamp, an error line, `null`), it is set in Geist Mono at 13px with tabular figures. Human sentences never are.

**The Lowercase Wordmark Rule.** "devnull" is always lowercase, set in the sans beside the null-sign mark, bold with tight negative tracking.

## Layout

A centered shell (max 1264px) holds three columns: a sticky left nav (256px at lg, 80px icon rail at md), the feed column (white, hairline-bordered left and right, max 620px), and a sticky aside (320px, xl and up only). Below md the nav becomes a fixed four-item bottom bar and a 56px mobile header carries the wordmark and account menu; the aside disappears.

Inside the feed every row is `[rail | content]`. The rail column is 44px (56px from sm) and spans the full row height; content rows pad 12px vertically and 16px on the right, and the row's bottom hairline starts after the rail so the graph runs uninterrupted. Page headers are sticky, 56px tall, with 16/24px horizontal gutters. The landing page reuses the same grammar at large scale: an ordered list of three commits on a rail with 40-48px of air between nodes.

**The Unbroken Rail Rule.** Dividers never cross the rail column. Rows are separated to the right of the graph so the line reads as one continuous history from the staged composer to the root commit.

## Elevation & Depth

Flat. Depth comes from the shell/feed tone step and hairline rules, not shadows. The only shadows in the build are functional geometry: the 2px zero-offset ring that outlines each graph node in its lane color, the 3px inset underline on the active tab, and the near-invisible `shadow-xs` on primitive buttons. Popovers and the delete-account dialog use the component library's default overlay treatment.

**The No Cards Rule.** Commits are rows, not cards. Containment is a hairline; there are no floating panels in the feed.

## Shapes

Soft but small. Interactive controls use 6px corners (buttons, confirm strip, icon hit areas), nav items and bordered groups use 8px, and anything that represents a person or a commit is a perfect circle: avatars, graph nodes, handle dots, aside bullets. Lines are 2px for rail segments and node rings, 1px for hairlines. The node is a ring with a 3px punched gap in the surface color, filled with the lane color for a commit and left hollow for staged and root states.

## Components

### Buttons
Quiet, rectangular, decisive.
- **Shape:** gently rounded (6px); default height 36px, small 32px, large 40px (landing CTA 44px).
- **Primary:** Commit Blue fill, white label, 14px medium. Disabled at 50% opacity (the empty composer).
- **Hover / Focus:** fill steps to Pressed Commit Blue; focus shows a 3px ring at 50% of the ring color.
- **Outline:** hairline input stroke on the shell color; hover to the wash. Used for "Following", "Edit on GitHub" on your own profile, "View profile".
- **Ghost:** no fill until hover. Used for "Cancel".
- **Destructive:** Rm Red fill, white label, small size. Appears only after an explicit confirm step.

### Navigation
- **Side nav:** 15px medium, 20px line icons, 12px padding, 8px corners. Inactive is Muted Ink; hover adds the wash; active is Commit Blue text on Blue Wash. Labels collapse to icons at md.
- **Page tabs:** full-width split under a sticky header, 15px medium. Active text goes to ink with a 3px Commit Blue underline hugging the label; inactive is muted.
- **Mobile:** fixed bottom bar, four equal cells, icon over 12px label, active in Commit Blue.
- **Vocabulary:** a post is a **commit** and a timeline is a **log**, in the UI and in code. No other git nouns.

### Inputs / Fields
- **Composer:** borderless, no visible field chrome: a 17px textarea ("What did you ship?", max 280) that grows with content. Its state is carried by the graph: the staged node is hollow until the body reaches 3 characters, then fills with the author's lane; the tail below it is a dashed Rail Gray line. A mono countdown sits left of the "Commit" button; ⌘/Ctrl+Enter commits.
- **Stash:** unsent text in the composer and in each reply composer is kept in `localStorage` per signed-in user (cleared on commit, when emptied, and on log out). When a draft comes back after a reload or navigation, a quiet mono `stash@{0}` (title "Restored your unsent draft") sits at the left of the counter row with a muted `drop` text button that discards it. Merely typing never shows it. A thread with a stashed reply prints a muted 11px mono `stash@{0}` after its "Reply" action; Cancel and Escape close the reply composer but keep the stash. Neither element is blue.
- **Read-only fields (settings):** a hairline-bordered 8px-radius list, mono keys in a 6.5rem column, values in sans; missing values print a mono `null`.
- **Color picker (settings):** a live preview row (rail node, 48px avatar with the lane ring, lane-dotted handle) above a radio group of seven 28px swatches with sans captions: a dashed "Auto" swatch showing the hashed lane, then the six lanes. The checked swatch gets the 2px lane ring offset from the surface; focus shows the standard focus ring on the whole option. Saved with a primary "Save color" button, disabled until the choice changes. Only the six lanes are offered so light/dark lifts and contrast keep working.
- **Theme switch:** a bordered segmented group (8px outer, 6px inner) whose selected option uses the active-nav treatment.

### Commit Row (signature)
The graph rail, then avatar (40px circle), then a header line: author name (600), mono handle, a middle dot, mono relative time, and the 7-character short hash pushed right (from sm). The body follows at 15px, pre-wrapped. Your own rows reveal a trash icon on hover/focus (always visible on touch). Deleting is inline: a soft red strip asks "Delete this commit? There's no undo." with Cancel and Delete; confirming swaps the body for a mono `rm {hash} → /dev/null` status, then the row collapses (height and opacity, 300ms after a 400ms hold).

### Graph Rail (signature)
A 14px circle (16px on the landing page) centered on a 2px vertical line. The segment above a node takes the previous author's lane; the segment below takes this author's. The first commit hangs from a Rail Gray segment beneath the staged composer. The end of history is a hollow **root** node with no tail and a muted line ("That's the root commit. There's nothing before it."). A commit you made in the last 15 seconds animates in with `node-in` (scale 0 to 1.25 to 1 with a lane-colored pulse, 700ms, expo-out); landing-page segments draw downward with `rail-draw`, staggered 140ms. Both respect reduced motion.

### Empty States
Centered in the feed: a mono `fatal: …` line in Muted Ink, then one or two plain sentences (15px, max 24rem) with a single Commit Blue link when there is somewhere to go.

### Error Pages
403, 404, 429, 500 (outside debug), and 503 render one standalone `error` page, with no app shell, for guests and signed-in users alike. A broken rail: the wordmark sits on a node in the viewer's lane (Rail Gray for guests) whose segment fades out before reaching a hollow, dangling node. Beside it, a mono git-style line (`fatal: path '/x' does not exist in 'HEAD'`), a Headline sentence saying what happened, one muted sentence saying what to do, and a single primary button ("Back to your log", "Go to the home page", or "Try again" during maintenance). A 419 is not a page: it redirects back with an error toast.

### Profile Header
A 80px avatar with a 2px lane ring offset 4px from the surface, name in Title, mono handle led by the lane dot, metadata with 16px line icons, and mono counts paired with sans labels ("3 commits", "5 following").

### Commit Activity
Below the profile header: a GitHub-style 53-week grid (Sunday-first columns, Mon/Wed/Fri row labels, month labels on month change). Cells are 2px-rounded squares with 3px gaps, filled in the profile owner's lane: Muted for empty days, then the lane mixed into the surface at 32/56/80/100% for levels 1–4 relative to that user's busiest day. The total count is mono; each cell carries a "N commits on Mon D, YYYY" title. On narrow screens it scrolls horizontally, opening on the most recent week.

### Log Interactions
- **Day markers:** a row that keeps the rail running (no node) with a mono 12px label, "Today", "Yesterday", or "Tue, Oct 13", wherever the local day changes between commits.
- **Last visit marker:** a row like the day marker (rail runs through, no node) with a muted mono `HEAD@{last visit}` and a dashed hairline, between the commits that arrived since your last visit to that log and the first one you had already seen. The newest commit's time you saw is kept in `localStorage` per signed-in user and per log, updated on every load and after the new commits pill; the marker stays where it was at page load. It is skipped on a first visit, when nothing is new, or when the only new commits are your own; when it falls past the loaded page it appears once infinite scroll reaches it. Never blue.
- **Author highlight:** hovering a node, avatar, name, or handle keeps that author's lane at full strength and fades every other node and segment to 30% opacity (200ms).
- **New commits pill:** polling every 30s; when commits arrive, a Commit Blue pill ("↑ 3 new commits") hangs under the tabs and reloads the log from the top.
- **Commit toast:** after committing, a mono toast prints `[log <hash>] <body>`, the way `git commit` reports.
- **Copy link:** a muted link icon ends every commit's and reply's action row (and sits beside the short hash in the commit page header). It copies the absolute commit URL, swaps to a check for 2s, and prints a mono toast `Copied link to <hash>`; if the clipboard is unavailable or denied, an error toast prints `fatal: could not copy link to <hash>`. `y` (yank) copies the focused commit's link; only the top-level commit's own button carries `aria-keyshortcuts`, so replies never answer to it. It copies on every device; there is no share sheet.
- **Loading:** skeleton rows keep the rail running (rail-gray node and line, pulsing bars) while the next page loads.
- **Keyboard:** `j`/`k` move a focus highlight (Accent background) between commits, `c` focuses the composer, `g` then `l`/`e`/`p`/`s` navigates, `?` opens the shortcut sheet; keys render as bordered mono `kbd` caps. In the moderation queue `a`/`x` approve or reject the focused commit and focus moves to the next one; the caps sit inside the Approve/Reject buttons (from sm), and the sheet adds a Moderation section for admins.
- **Starter suggestions:** an empty Log for someone who follows nobody lists three recent approved commits from different authors, each with a Follow button.
- **Thread keys:** on the focused commit, `r` opens its reply composer with the cursor in it and `o` shows or hides its replies; focus stays on the commit (Esc or replying returns it there), so `j`/`k` keep stepping through top-level commits. The commit's own Reply and replies buttons carry `aria-keyshortcuts` and a `title` hint, with no caps in the action row.
- **Activity tooltip:** one dark tooltip (Ink on Background inverted) follows the hovered cell, edge-aligned near the ends; hovering a day tints that day's commits in the profile feed with Primary Soft.

### User Preview
Hovering an avatar, name, or handle (mouse only, 450ms delay, 200ms grace to move into it) opens a 304px Popover preview below the trigger, flipping above near the viewport bottom: a 56px avatar ringed in the user's lane, Follow / Following (Unfollow on hover) top right, name, mono handle with lane dot and a "Follows you" chip, a 3-line bio, and mono counts linking to following and followers. Follow toggles optimistically and rolls back on error. Closes on scroll, Escape, or leaving.

### Reply Threads
Under a commit's body, a quiet action row: "Reply" (muted, opens an inline bordered composer with its own counter, Cancel, and Reply; ⌘/Ctrl+Enter submits, Escape cancels) "N replies" in Commit Blue, which expands the thread in place, and a muted copy-link icon. Replies are compact rows: 24px (phone) / 28px avatar, name, mono handle (hidden on phones), time, status badge, then the body. Children render below their parent at the parent's left edge, not inside its text column, behind a 1px Rail Gray guide line; each level costs 19px (phone) / 27px. Indentation stops at three levels: deeper replies stay at that depth and carry a mono "↳ replying to @handle" label. Bodies wrap anywhere (`overflow-wrap: anywhere`) so long URLs and tokens never overflow.

### Commit Page
Every commit has a page at `/@username/commits/{id}`, reached from its relative time or short hash (both links), the "↳ replying to @handle" label, or Enter on a focused commit. A sticky header reads "Commit" with the mono short hash and a copy-link icon on the right. Visible ancestors (up to 20) stack above as regular commit rows on one continuous rail, without delete; the focused commit sits last with a 17px body, a mono "time · date · commit hash" line, and its replies already expanded.

### People Search
A command palette opened by ⌘K / Ctrl+K or `/` anywhere, the "Search developers ⌘K" pill at the top of the right column, a Search nav item where that column is hidden, or the search icon in the mobile header. Popover surface, 576px max, 12vh from the top: a borderless 17px input with a search icon and an `esc` cap, results as rows (36px avatar, name in 600, mono handle with lane dot, truncated bio) with the active row on Accent and a `↵` hint, and a footer of key hints (hidden on phones). Combobox semantics: arrow keys move, Enter opens the profile, Escape closes. Empty: "Type a name or @handle."; pending: "Searching…"; no match: mono `fatal: no developers match '…'`.

### Link Previews
A profile or commit link shared to X, Slack, Discord, or iMessage unfurls as a 1200×630 PNG drawn server-side with GD in the light theme: the Cool Shell background, a 4px rail down the left in the author's lane (Rail Gray above the first node), ringed lane nodes beside the avatar and the bio, a 152px avatar with the lane ring offset from the shell (a lane disc with a white initial when GitHub's avatar can't be fetched), the name in Geist 600, the mono handle led by the lane dot, up to three lines of bio ending in an ellipsis, and below a hairline the mono counts with sans labels and the `/dev/null` wordmark on the right. A commit link uses the same card (both drawn by `App\Services\OpenGraphCanvas`): an 80px avatar with the name and mono handle beside it and one lane node on the rail, a mono "↳ replying to @handle" line when the parent is approved, the body as the hero in Geist Regular (56px for one-liners, stepping down to 32px so up to six lines fit, then an ellipsis; line breaks are kept and long URLs wrap by character), and a footer reading mono `commit <short hash> · <date>` plus the approved reply count. Fonts are the Geist TTFs in `resources/fonts`; emoji are dropped because Geist has none.

## Do's and Don'ts

### Do:
- **Do** put every list of commits on the graph rail, with node and lower segment in the author's lane color and the upper segment in the previous author's.
- **Do** set handles, hashes, times, counts, and `fatal:` lines in Geist Mono at 13px.
- **Do** keep Commit Blue for the primary action, links, focus, and current location only.
- **Do** separate rows with 1px hairlines that start to the right of the rail.
- **Do** end a finished feed with a hollow root node and a dry, literal sentence.
- **Do** mirror dark mode token-for-token on graphite (`shell-dark`, `surface-dark`) using the lifted `-dark` lanes and primary.
- **Do** gate graph motion behind `prefers-reduced-motion`.

### Don't:
- **Don't** use lane colors for text, buttons, badges, or backgrounds; they mark authors on the graph and nowhere else.
- **Don't** give a lane to blue; blue belongs to action.
- **Don't** wrap commits in cards or raise them with drop shadows.
- **Don't** add an engagement row, counts beside commits, trend panels, or who-to-follow modules; the product has none of these.
- **Don't** call posts "posts" or the following timeline "Home" in the UI; they are commits and the Log.
- **Don't** set human sentences in mono to look technical.
