# Frontend UI stability

Every UI change keeps the layout still. Nothing may flash, jump, clip or reflow on first load, navigation, polling, or after an action.

## Shapes and insets

- Before changing visual styles, read the relevant sections of `DESIGN.md` and reuse existing tokens and shared UI primitives.
- Use the radius scale in `DESIGN.md` for controls, badges and overlays. Reserve `rounded-full` for intentional circles and functional geometry; do not use it as the default for UI chrome.
- Check custom components and local overrides when changing a shared primitive, including hover, focus, selected and loading states.
- Balance nested image and surface insets, accounting for borders. Derive inner clipping radii from the outer radius minus the inset; inspect the contents as well as the container.

## Loading

- A skeleton occupies exactly the loaded content's box: same height, line count and wrapping at every breakpoint, including the narrow aside. It is visible in light and dark mode, popovers included.
- Delay skeletons by about 300ms so fast responses show nothing. Show one only while data is genuinely missing, never while refetching data you already have.
- Shared props that persist across pages (aside, nav counts) use `Inertia::defer(...)->once()`, so client visits don't refetch them and flash.
- Polling and refresh buttons use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Changing content

- Numbers, counts and countdowns use `tabular-nums` in fixed-width slots.
- Bound compact count badges using the established display cap for that surface, while preserving the exact count for screen readers and on hover. Keep the badge from shrinking and let adjacent text truncate.
- For compact actions, size buttons to their content while preserving minimum touch targets. Keep deliberate full-width controls. When labels change ("Following", "Committing…"), reserve only the width the longest state needs; prefer a same-size spinner beside a stable label when that keeps a compact action still. Disabled, loading and enabled states are the same size.
- Conditional UI (banners, "@x is reviewing", inline confirms, errors) reserves its space or overlays. An inline confirm fits in the slot of the control it replaces.
- Removed list rows collapse with the existing commit-deletion animation, keeping scroll position and focus.
- Right after an action, the control under the cursor must not flip to a destructive state, e.g. Follow → "Unfollow" before the pointer leaves.
- Layout transitions preserve the visible reading position. Repeated transitions keep a stable anchor; intentional user scrolling releases it.
- Measured or height-animated containers include their children's spacing in the measured box. Contain collapsing margins where needed so text, code blocks and dividers stay aligned throughout reflow.

## Menus and dismissal

- Keep exiting overlays' contents and anchors stable through dismissal. Sequence updates that would reorder visible options or move the anchor after the exit completes, using the component's lifecycle.
- Menus intended for several selections stay open while choices are toggled. When controls move into overflow, keep their active state discoverable on the trigger and close hidden overlays when the layout changes.
- Render explanatory text separately from selectable menu items, visually distinguish it from actions, and associate it with the relevant controls for screen readers.

## Overflow

- Dialogs and popovers are capped to the viewport (`dvh`) and scroll inside, with a sticky header and footer when actions would scroll away. No double scrollbars and no page scroll through the overlay. Scroll lock must not shift the page: keep `scrollbar-gutter: stable`.
- Long usernames, URLs, reasons, queue names and messages use `min-w-0` with `truncate` or `line-clamp`, and the full text in `title`.
- Fit control groups to the available content width, including when sidebars change it. Preserve deliberate alignment across wrapped rows; use an accessible overflow menu when the controls cannot fit comfortably.
- Auto-growing textareas are `overflow-x-hidden`. Inputs use at least 16px text below `md` so iOS doesn't zoom.
- On mobile, toasts sit above the bottom nav.

## Verify before calling it done

- Check in a browser at 1440×900, 1366×650 and 390×844, in light and dark.
- Measure layout shift (`PerformanceObserver` with `layout-shift`) for first load, navigation, a polling cycle and every action you added. The target is 0.
- Compare side by side with `production` using the same demo data. Record before/after clips for any fix to motion or loading.
- Exercise open, selection, dismissal and reopen states, repeated layout transitions while scrolled, and responsive changes in both directions with keyboard and touch input.
- Check compact counts at zero, one, the display cap, just above it and a large value; verify the exact accessible count and layout beside long text.
