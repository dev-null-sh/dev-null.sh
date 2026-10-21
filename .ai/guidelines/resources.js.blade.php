# Frontend UI stability

Every UI change keeps the layout still. Nothing may flash, jump, clip or reflow on first load, navigation, polling, or after an action.

## Shapes and insets

- Before changing corners, read `DESIGN.md` → Shapes and reuse the existing radius tokens and shared UI primitives. Apply the same scale to custom controls, badges, overlays and sheets.
- Preserve the semantic circles and functional geometry listed there when standardizing corners.
- Balance logo insets inside badges, accounting for the border. Derive the inner clipping radius from the outer radius minus the inset; inspect the nested image as well as its container.

## Loading

- A skeleton occupies exactly the loaded content's box: same height, line count and wrapping at every breakpoint, including the narrow aside. It is visible in light and dark mode, popovers included.
- Delay skeletons by about 300ms so fast responses show nothing. Show one only while data is genuinely missing, never while refetching data you already have.
- Shared props that persist across pages (aside, nav counts) use `Inertia::defer(...)->once()`, so client visits don't refetch them and flash.
- Polling and refresh buttons use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Changing content

- Numbers, counts and countdowns use `tabular-nums` in fixed-width slots.
- Compact unread and request badges display `99+` above 99, with the exact count in a screen-reader label and hover title. Keep the badge from shrinking and let adjacent text truncate.
- Size buttons to their content. When labels change ("Following", "Committing…"), reserve only the width the longest state needs; prefer a same-size spinner beside a stable label when that keeps a compact action still. Disabled, loading and enabled states are the same size.
- Conditional UI (banners, "@x is reviewing", inline confirms, errors) reserves its space or overlays. An inline confirm fits in the slot of the control it replaces.
- Removed list rows collapse with the existing commit-deletion animation, keeping scroll position and focus.
- Right after an action, the control under the cursor must not flip to a destructive state, e.g. Follow → "Unfollow" before the pointer leaves.
- Sidebar transitions preserve the visible row's scroll position, including notification groups and their dividers. Repeated toggles keep the same anchor; intentional user scrolling releases it.

## Menus and dismissal

- Keep a closing picker's contents and anchor stable until its exit animation finishes. Defer updates that would reorder its options or move its anchor until it has unmounted.
- Multi-select filter menus stay open while options are toggled. Reflect active hidden filters on the overflow trigger, and reset the open menu when crossing into the desktop layout.
- Fit filter rows to their container with centered, aligned columns. Keep primary filters visible on mobile and put additional filters in an accessible overflow menu.
- Render settings explanations as visually separated, non-interactive help text associated with the relevant controls, outside selectable menu items.

## Overflow

- Dialogs and popovers are capped to the viewport (`dvh`) and scroll inside, with a sticky header and footer when actions would scroll away. No double scrollbars and no page scroll through the overlay. Scroll lock must not shift the page: keep `scrollbar-gutter: stable`.
- Long usernames, URLs, reasons, queue names and messages use `min-w-0` with `truncate` or `line-clamp`, and the full text in `title`.
- Auto-growing textareas are `overflow-x-hidden`. Inputs use at least 16px text below `md` so iOS doesn't zoom.
- On mobile, toasts sit above the bottom nav.

## Verify before calling it done

- Check in a browser at 1440×900, 1366×650 and 390×844, in light and dark.
- Measure layout shift (`PerformanceObserver` with `layout-shift`) for first load, navigation, a polling cycle and every action you added. The target is 0.
- Compare side by side with `production` using the same demo data. Record before/after clips for any fix to motion or loading.
- Exercise open, selection, dismissal and reopen states, plus repeated sidebar toggles while scrolled. Check responsive menus across their breakpoint and with keyboard and touch input.
- Check compact badges at 0, 1, 99, 100 and a large count; verify the exact accessible count and truncation beside long names.
