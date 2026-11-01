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

## Navigation and requests

- Every Inertia `<Link>` to a GET page prefetches on hover (`prefetch`). A GET page never writes; the only exceptions are `log/{log}` (remembers the log), `notifications` (marks read, remembers the filter), `remotes/@{partner}` and `remotes/orgs/{org}` (mark read), and every link to them keeps `prefetch={false}`. `starred` also keeps it, because starring changes the list. A new GET page that must write joins this list.
- Never prefetch a non-GET link, an external URL, a guest's sign-in link, or a URL that is not an Inertia page (images, feeds, downloads, JSON).
- A click target that is not a link (the commit card surface) prefetches on `mousedown`, never on hover: scrolling a feed hovers every card.
- After an action that changes what a prefetched page shows, flush that page (`router.flush(url)`, `router.flushAll()`, or `invalidateCacheTags`), so the next click does not show the old state.
- One user intent is one request. When the server would only echo the URL back (a search query, a filter), change it on the client with `router.replace({ url, props })`. Data the first paint needs comes with the page as a prop or a deferred prop, never from a fetch in a mount effect.
- Shared props never recompute per visit what changes slowly: use `Inertia::once()`, with `->until()` when it must refresh, and `Inertia::defer(...)->once()` for the aside. Leave the key out on pages that do not show it, so no page caches an empty value. A partial reload that names the prop still refreshes it.
- Polling, refresh buttons and filters use partial reloads (`only: [...]`) of the page's own props. Never reload the whole page, and keep showing the old data until the new data arrives.

## Actions

- Every action updates the UI at once and rolls back on failure: `router.optimistic(...)`, `useHttp().optimistic(...)`, `<Form optimistic>`, `usePicked` for switches, `useSharedState` for commit counters, and `resources/js/lib/chat.ts` for remotes.
- Wait for the server only when the UI needs a value that only the server has (the id of a new commit or message), or when a validation error must show inline in a form that the optimistic change would unmount.

## Rendering

- The React Compiler is on: write no `useMemo`, `useCallback` or `memo` for performance, and never write a ref or module state during render.
- Derive values from props during render, or reset a subtree with a `key`. Never copy props into state with an effect.
- Values that differ between the server and the browser (`window`, `matchMedia`, `localStorage`, the time) come from `useSyncExternalStore` with a server snapshot (`useMediaQuery`, `useNow`). Never read them during render, and never set them in a mount effect: each one makes hydration render twice.
- The SSR process renders pages for many users. A module store that a page seeds during render resets on the server first, so no render starts with another user's data.
- Declare no component inside another component, and use no `key={index}` on a list that inserts, removes or reorders rows.

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
