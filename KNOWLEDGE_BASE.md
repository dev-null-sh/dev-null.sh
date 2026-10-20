# /dev/null knowledge base

/dev/null is a social network for developers, built in public during a livestream. Developers sign in with GitHub, share posts called **commits**, follow one another, and read timelines called **logs**.

This file documents the platform's features, stack, and stories. Feature entries describe the current implementation, including access requirements and limits, and can be retrieved independently by the knowledge-base search. Technology names intentionally have no version numbers.

## Contents

- [Repository](#repository)
- [Features](#features)
  - [Public browsing and GitHub sign-in](#public-browsing-and-github-sign-in)
  - [First-time setup](#first-time-setup)
  - [Profiles and GitHub sync](#profiles-and-github-sync)
  - [Going live](#going-live)
  - [Achievement badges and XP](#achievement-badges-and-xp)
  - [Following developers and suggestions](#following-developers-and-suggestions)
  - [Following, Latest, and Trending logs](#following-latest-and-trending-logs)
  - [Searching developers and commits](#searching-developers-and-commits)
  - [Writing commits](#writing-commits)
  - [Translating commits](#translating-commits)
  - [Code, emoji, and mentions](#code-emoji-and-mentions)
  - [Draft previews and secret warnings](#draft-previews-and-secret-warnings)
  - [Stashed drafts](#stashed-drafts)
  - [Images and descriptions](#images-and-descriptions)
  - [Links, link cards, and embedded players](#links-link-cards-and-embedded-players)
  - [Replies and threads](#replies-and-threads)
  - [Quoting commits](#quoting-commits)
  - [Polls and voting](#polls-and-voting)
  - [Scheduled commits](#scheduled-commits)
  - [Deleting commits and undo](#deleting-commits-and-undo)
  - [Stars](#stars)
  - [Saved commits](#saved-commits)
  - [Pinning a commit](#pinning-a-commit)
  - [Pastéis mode and awards](#pastéis-mode-and-awards)
  - [Notifications](#notifications)
  - [Remotes: direct messages](#remotes-direct-messages)
  - [Orgs: group chats](#orgs-group-chats)
  - [Muting and blocking](#muting-and-blocking)
  - [Reporting commits](#reporting-commits)
  - [Appearance, color, and timezone](#appearance-color-and-timezone)
  - [Installing /dev/null as an app](#installing-devnull-as-an-app)
  - [Activity graphs and README embeds](#activity-graphs-and-readme-embeds)
  - [Keyboard shortcuts](#keyboard-shortcuts)
  - [Terminal commits](#terminal-commits)
  - [GitHub Actions integration](#github-actions-integration)
  - [Public read API and Atom feeds](#public-read-api-and-atom-feeds)
  - [Signed API and devices](#signed-api-and-devices)
  - [Bug reports and feature requests](#bug-reports-and-feature-requests)
  - [Exporting your data](#exporting-your-data)
  - [Deleting your account](#deleting-your-account)
  - [Moderation](#moderation)
  - [Operations dashboard](#operations-dashboard)
  - [Product metrics](#product-metrics)
  - [About, changelog, and policies](#about-changelog-and-policies)
  - [Tom's repository insights and community statistics](#toms-repository-insights-and-community-statistics)
  - [Hello world replies](#hello-world-replies)
- [Stack and hosting](#stack-and-hosting)
- [Lore](#lore)
  - [Product trailer](#product-trailer)
  - [Built on a livestream](#built-on-a-livestream)
  - [Tom](#tom)
  - [Pastéis de nata](#pastéis-de-nata)
  - [The pushup break](#the-pushup-break)
  - [The codename that stuck](#the-codename-that-stuck)
  - [Peach P](#peach-p)
  - [Chat chooses dark mode](#chat-chooses-dark-mode)
  - [The disappearing images](#the-disappearing-images)
  - [The anti-TikTok pull request](#the-anti-tiktok-pull-request)

## Repository

- **Repository:** [nunomaduro/dev-null.sh](https://github.com/nunomaduro/dev-null.sh).
- **Setup and development:** [README](README.md).
- **Product terminology and behavior:** [Product guide](PRODUCT.md).

## Features

These entries cover the app's public, signed-in, and administrator features. Configuration-dependent options are called out explicitly. Each entry includes implementation references so its behavior can be checked when the app changes. Knowledge-base retrieval powers Tom's public question-answering replies, separately from the public commit search.

### Public browsing and GitHub sign-in

Guests land on the Trending log and can browse Latest and Trending, public profiles and their following/follower lists, approved commits and reply threads, photos, and search. Sign in with GitHub to write commits, follow, star, vote, save, report, give pastéis, translate, receive notifications, read the Following log, or use private messages and settings. Clicking one of those actions as a guest goes straight to GitHub sign-in and returns to the profile or commit being read. GitHub is the only sign-in provider; there is no separate /dev/null password.

New GitHub accounts are screened at signup. The repository defaults require an account at least seven days old and a verified email; these requirements are configurable. Returning accounts are not screened again. Public profiles and approved commits are readable by other people and crawlers, and links have social previews. A public commit is not private just because its author signed in to write it.

Sources: [web routes](routes/web.php), [signup configuration](config/signup.php), [sign-in tests](tests/Feature/Controllers/SessionControllerTest.php).

Livestream history: the team agreed on GitHub-only sign-in and importing profile defaults on [day one at 1:06:29](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=3989s), citing simplicity and reducing bot problems. A successful sign-in and imported profile were reported during the walkthrough at [1:44:25](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=6265s).

### First-time setup

The first GitHub sign-in opens a short onboarding flow: review your mirrored profile (after editing it on GitHub, **Sync from GitHub** pulls the changes in without leaving setup), choose a color, follow suggested developers, and learn about the composer, keyboard shortcuts, and terminal commits. Skip setup at any step or finish it to continue to the app. Completed or skipped setup does not appear again. New users also start with Tom as a mutual follow; Tom is the platform's fictional welcome bot. Tag @tom in a public commit to ask about the platform or find posts on a topic when the OpenCode integration is configured.

Sources: [onboarding page](resources/js/pages/onboarding/show.tsx), [sign-in tests](tests/Feature/Controllers/SessionControllerTest.php).

### Profiles and GitHub sync

Every developer has a profile at `/@{username}`, with their GitHub identity, bio and other public profile information, commit/following/follower counts, a **Follows you** badge when they follow you, and an activity graph. Across a block in either direction, the profile is shown as unavailable. The Commits tab lists top-level commits; Replies lists that developer's replies with parent context. Follow and follower counts open the corresponding people lists. The profile links to GitHub and, when available, GitHub Sponsors. Share uses the device's share sheet when supported, or copies the profile link.

Change your name, avatar, bio, company, location, or website on GitHub, not on /dev/null. Settings → Profile offers **Edit on GitHub** and **Sync from GitHub**. Profile details also refresh on sign-in. Manual sync is limited to once a minute. The GitHub Sponsors link is refreshed on sign-in only. A GitHub username rename is picked up on sign-in or sync; if a stale account still holds the new username because its owner also renamed, that account moves to a `_renamed-<github id>` placeholder until its owner signs in or syncs again. Accounts are matched by GitHub id and never merged.

Profiles also show [achievement badges and an XP-based Unix rank](#achievement-badges-and-xp). Click a badge or rank to inspect unlock requirements and progress.

Sources: [profile page](resources/js/pages/user/show.tsx), [profile settings](resources/js/pages/user-profile/edit.tsx), [profile sharing](resources/js/components/profile-share-button.tsx), [identity sync](app/Actions/UpdateOrCreateUser.php).

### Going live

Share a stream you are running from the **…** menu on your own profile: **Go live** takes a link to a single YouTube video (a `/live/` link, a watch link, an embed, or one with a start time all work) and how long to show it, from **1 to 12 hours** (two by default). YouTube is the only supported platform. While you are live, your avatar gets a red ring and a **Live** marker on your profile, in logs, and in hover cards. Hovering it (or tapping it on a touch screen) shows the stream's live thumbnail with a **Watch** button; the stream itself plays on YouTube in a new tab, never embedded. On your profile, the avatar links straight to the stream.

**End stream** in the same menu stops it at once; otherwise it stops showing when the chosen time runs out, and the scheduler clears it within five minutes. Only the platform, the link, and the end time are stored, and they are included in your export.

Sources: [go-live menu](resources/js/components/profile-actions-menu.tsx), [live avatar and card](resources/js/components/live-stream-card.tsx), [validation](app/Http/Requests/UpdateUserLiveRequest.php), [YouTube links](app/Services/LivePlatforms/YouTube.php), [ending streams](app/Jobs/EndLiveStreams.php).

### Achievement badges and XP

Profiles have **13 achievement badges**. Earned badges appear in a single row, with **+N more** when more have been earned than fit. Hover over the row on desktop or tap it on mobile to toggle a popover with every badge, including locked badges and their progress. Profiles with no earned badges show **View achievements** instead. Select a badge in the popover, or the profile's rank or unlocked count, to open the detailed achievements dialog.

| Badge | When it unlocks |
| --- | --- |
| **init** | Publish your first commit. |
| **syntax** | Write a commit containing a triple-backtick code fence. |
| **uptime** | Reach a longest commit streak of **7 consecutive days**. |
| **daemon** | Reach a longest commit streak of **30 consecutive days**. |
| **sysadmin** | Reach a longest commit streak of **100 consecutive days**. |
| **stdout** | Reach **50 commits**. |
| **kernel space** | Reach **100 commits**. |
| **stargazer** | Star **10 commits by other developers**, excluding your own. |
| **superstar** | Receive **25 stars across your commits**, excluding self-stars. The stars need not be on one commit or from 25 distinct people. |
| **pipe** | Write **5 replies to other developers' commits**, excluding self-replies. Replies still count when their parent commit is soft-deleted. |
| **authorized_keys** | Have at least one public SSH key linked from GitHub. |
| **sweet tooth** | Receive **100 pastéis across your account**, not on a single commit. Pastéis mode does not need to be enabled, and the total survives deletion of the awarded commits. |
| **luddite** | Stop following Tom while he still follows you. This deducts **100 XP**; following him again removes the badge and penalty. Accounts Tom has never befriended do not qualify. |

Commit counts include replies and quotes. Commit-based badges and XP use non-deleted commits visible to the person viewing the profile, so an author may see progress from pending or rejected commits that other viewers cannot see. Streak badges use the **longest** streak, not the current streak: missing a day does not by itself remove them. Days follow the activity graph's app-timezone grouping, not the user's selected display timezone.

Badges are calculated from current account data, not permanently recorded awards. Removing qualifying activity can reduce progress or lock a badge again; for example, unstarring a commit can remove **stargazer**. When a commit posted on the web unlocks a badge, a toast names it and confetti drops; clicking an unlocked badge drops confetti again. Unlocking a badge does not normally give bonus XP. XP comes from **10 per commit**, **15 per day in the longest streak**, **5 per star received**, **5 per reply to another developer**, and **2 per star given to another developer**. The **luddite** penalty applies once while the badge is unlocked, not once per unfollow, and total XP cannot fall below zero. A qualifying reply contributes both commit XP and reply XP; pastéis do not add XP.

Sources: [badge definitions](app/Enums/Achievement.php), [unlock conditions and XP calculation](app/Queries/UserAchievementsQuery.php), [streak calculation](app/Queries/UserStreakQuery.php), [achievements dialog](resources/js/components/achievements-dialog.tsx).

### Following developers and suggestions

Follow or unfollow from a developer's profile or a follow control elsewhere in the app. Following puts their commits in your Following log. You cannot follow yourself or follow across a block. GitHub follows are imported once: connections to developers already on /dev/null are added in both directions, with up to 1,000 GitHub connections checked each way. This is not continuous synchronization, so unfollowing someone on /dev/null sticks.

**Who to follow** suggests developers in the side column or within the log on smaller screens, each with the first line of their bio and why they are suggested (how many people you follow follow them, or their follower count). Someone who already follows you gets **Follow back**. Show or hide these suggestions in Settings → Appearance. You can also follow back from the notifications list.

Sources: [follow import](app/Actions/CreateUserGitHubFollows.php), [appearance settings](resources/js/pages/appearance/update.tsx), [notifications page](resources/js/pages/notification/index.tsx).

### Following, Latest, and Trending logs

Logs are the app's timelines. **Following** contains your commits and those of developers you follow, newest first; it requires sign-in. **Latest** contains the newest public commits across the app. **Trending** ranks public commits by a hot score: the distinct other developers who engaged anywhere in the thread (a reply or quote counts three times a star, each person once, the author excluded), weighed against age so engagement must double to keep up with a commit posted three hours later. Pastéis don't affect Trending. Logs list top-level commits, including quotes, rather than every reply, 20 at a time as you scroll, and show your own pending commits to you. Following and Latest check for new commits every 30 seconds and offer an "N new commits" control instead of moving what you are reading, and mark where you left off. On touch screens, swipe sideways to move between logs. An empty Following log suggests three recent commits from people to follow. Muted and blocked people are filtered for signed-in viewers. The last log you opened is remembered.

Browser Back and Forward restore expanded replies and loaded thread continuations. Offscreen commits are measured before scroll restoration so returning to a log or discussion keeps the content in place.

If a background check hits a temporary connection or gateway failure, the page keeps its last received content and tries again on the normal polling schedule.

Sources: [log types](app/Enums/Log.php), [log queries](app/Queries/LogQueries), [log page](resources/js/pages/log/show.tsx), [background polling](resources/js/lib/background-poll.ts).

### Searching developers and commits

Open search with **⌘/Ctrl+K** or **/**. Developer search finds people by name or GitHub username. Commit search, also available at `/search`, finds posts by meaning rather than requiring an exact phrase: describe the topic you want to read about. Commit queries must contain at least three characters and at most 100, and cover replies as well as top-level commits. An AI relevance check filters the nearest matches, so a search can return fewer results than asked. Results respect visibility, mutes, and blocks; semantic matching does not guarantee that every relevant commit will be returned. Guests can search too. Commit search is limited to 15 searches a minute per person (or per IP for guests).

The public search searches developers and commits, not this knowledge-base file. Feature documentation is available to the internal knowledge-base retriever once indexed; there is no public knowledge-base search endpoint yet.

Sources: [search page](resources/js/pages/search/index.tsx), [commit search query](app/Queries/CommitSearchQuery.php), [search validation](app/Http/Requests/ListCommitSearchRequest.php), [knowledge-base query](app/Queries/KnowledgeBaseSearchQuery.php).

Livestream history: the team agreed to build semantic search on [day two at 2:57:59](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=10679s). The discussion connects immutable commits with simpler embeddings: editing would require re-embedding, versioning, and reconsidering notifications. They deferred editing and versioning “for now,” rather than declaring that those features could never exist.

### Writing commits

A **commit** is a post on /dev/null, not a Git commit in a repository. Open the composer or press **c**, write your update, and choose **Commit** or press **⌘/Ctrl+Enter**. Text-only commits accept 3–4,000 characters; as you type, the ring around your avatar in the composer fills toward that limit. In the browser, an image attachment makes the text optional, but the 4,000-character maximum still applies. Lists fold posts longer than 280 characters or 8 lines behind **Show more**, without cutting through code. Commits can be created and deleted, but not edited after publication.

Commits, replies, quotes, scheduling, terminal submissions, and app (signed API) commits share a limit of 10 attempts per minute per user. Posts go through [moderation](#moderation), so a newly submitted commit may be pending rather than public immediately; admins and authors with a good reputation are approved at once.

Sources: [composer](resources/js/components/commit-composer.tsx), [commit model](app/Models/Commit.php), [commit validation](app/Http/Requests/CreateCommitRequest.php), [submission throttle](app/Http/Middleware/ThrottleCommits.php).

Livestream history: the team settled on calling posts **commits** and their list a **log** on [day one at 2:56:00](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=10560s). At [3:02:19](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=10939s), they agreed to rename the post model too, rather than changing only the UI. These are naming decisions, not timestamps proving the rename had finished.

### Translating commits

Public commits and replies offer **Translate** when their detected language differs from the reader's translation language. Choose a translation language in **Settings → Profile**, searching by its English or native name (for example, Hungarian or magyar), independently of the device language, or leave **Browser language (automatic)** selected to follow the browser. This private preference is saved to the account, included in the data export, and erased with the account. Regional variants share a language, such as English for `en-US` and `en-GB`. Language detection runs in the background for posts being read; undetermined posts initially offer Translate rather than guessing. Code-only or indeterminate text is not offered once detected. Generating translations requires sign-in and is limited to ten requests per minute.

Moderators can also translate pending commits in the moderation queue, using the same **Translate** and **Show original** controls without approving the post. Pending translations remain unavailable to other readers, including the author.

DeepSeek through OpenCode translates only the post text. The result replaces its displayed text, with **Translated from [language]** and **Show original** to switch back; the original post is never changed. Cached translations are included on subsequent page loads, including for guests. Exact duplicate text shares translations across authors using a SHA-256 content key, with separate text for each target language. The cache expires seven days after its creation, without extending on reads or additional target languages. The database cache is physically pruned hourly; Redis expires entries itself. Deleting a post or account evicts its shared translations, which surviving duplicates can generate again. No private messages are translated.

Background language detection uses the separate `translations` queue on the configured queue connection and needs a worker listening to that queue. It makes up to three attempts, waiting one minute and then ten minutes between failures. A detection dispatch failure does not prevent reading the original post. Requested translations still run in the HTTP request; failures leave the original unchanged and can be retried with Translate. If the provider returns an unsupported source-language code, the user sees that specific reason rather than a generic failure. Provider response contents are never shown.

Administrators can switch translations off for everyone from the operations dashboard. While off, Translate and cached translations are hidden, the translation endpoint returns not found, no detection jobs are queued, and detection jobs already waiting finish without calling the provider. Cached translations are kept and reappear when translations are switched back on.

Sources: [translation endpoint](app/Http/Controllers/CommitTranslationController.php), [agent](app/Ai/Agents/CommitTranslator.php), [cache](app/Services/CommitTranslations.php), [controls](resources/js/components/commit-translation.tsx).

### Code, emoji, and mentions

Commit and reply text supports inline code in backticks and fenced code blocks with syntax highlighting and a copy control. Text outside code supports emoji shortcodes, clickable URLs, and `@username` mentions. This is developer-oriented text formatting, not a full rich-text editor.

When a commit or reply has a fenced code block, its link preview image shows the first block, highlighted, in a dark editor window named after the block's language, with the first line of any text before it above. Later blocks and the text after the first block are left out of the image, but not the commit. Shorter code is drawn larger. Lines too wide for the window are cut with an ellipsis rather than wrapped, and code too long for it ends in an ellipsis line. Emoji in the code are left out of the image. Inline code and empty blocks keep the ordinary preview image.

Typing **@** in a composer opens developer suggestions. Use ↑/↓ to move, Enter or Tab to select, and Esc to dismiss. A bare @ suggests people you follow; typing more searches names and usernames. In replies, matching suggestions put the direct recipient first, then the root commit author, then other visible participants in that thread, followed by ordinary suggestions. Quotes prioritize only the quoted commit author. These priorities also apply to a bare @; typing a term still filters everyone, and yourself, muted developers, and blocked developers are excluded. In an org's messages, suggestions are limited to the org's members. Mentions link to profiles and can notify the mentioned developer after approval. Email addresses and scoped packages such as `@inertiajs/react` are not treated as mentions.

Typing **#123** in a composer looks up that exact issue or pull request in the configured upstream repository (`nunomaduro/dev-null.sh` by default). The matching title appears beneath the number; click it or use Enter or Tab to insert its GitHub URL. A space or punctuation after the number automatically inserts the match, without treating a pause midway through a longer number as completion. Missing numbers and failed lookups leave the text unchanged. Code, URL fragments, and references qualified with another repository are not expanded.

The upstream is configured with `GITHUB_REPOSITORY=owner/repo` through `services.github.repository`. Issue suggestions, issue filing, the changelog, repository insights and history, and public repository links share this setting. Repository-specific caches are scoped to it.

Sources: [body renderer](resources/js/components/commit-body.tsx), [code blocks](resources/js/components/code-block.tsx), [code preview images](app/Actions/CreateCommitOpenGraphImage.php), [mention suggestions](app/Http/Controllers/MentionSuggestionController.php), [mention parsing](app/Services/Mentions.php).

### Draft previews and secret warnings

Use the composer's eye button or **⌘/Ctrl+Shift+P** to preview a commit or reply before submitting it. Preview shows the rendered code, emoji, mentions, links, and images, and identifies the link selected for a card. Esc returns from preview to writing.

The composer checks text for recognizable secrets, such as credentials, and asks for confirmation before posting a flagged draft. The warning can be minimized to a shield icon while you keep editing, for example after a false positive. Draft text containing detected secrets is not persisted in the local stash. Treat this as a warning, not a guarantee that all secrets will be detected: review text and images yourself before publishing. Public GitHub issue drafts require detected secrets to be removed rather than offering a publish-anyway option.

Sources: [composer](resources/js/components/commit-composer.tsx), [secret warning](resources/js/hooks/use-secret-warning.ts), [sensitive-text handling](resources/js/lib/sensitive-text.ts), [issue commands](resources/js/components/issue-command.tsx).

### Stashed drafts

The app stashes unfinished commit and reply text, attached images, and image descriptions locally in your browser so you can return to them. Drafts belong to the signed-in user on that browser; they are not cloud drafts and do not synchronize between devices. Detected-secret text is excluded from persistent storage. Publishing or dropping a draft clears that stash. Browser storage restrictions or clearing site data can remove it; do not use the stash as your only copy of important writing.

Sources: [stash hook](resources/js/hooks/use-stash.ts), [image storage](resources/js/lib/image-storage.ts).

### Images and descriptions

Attach up to **three JPEG, PNG, or WebP images** to a commit or reply. Each image can be at most **10 MB** and **6,000 pixels in either dimension**. Add an image description of up to **1,000 characters** for accessibility. Add images with the picker, by pasting, or by dropping them on the composer. Every image is re-encoded to WebP, at most 1,600 pixels wide, which strips metadata such as location. Images can be posted without accompanying text. Images on a pending commit are visible only to its author and admins. Image attachments and a poll cannot be combined in the same commit. Open an attached photo to view it in the photo viewer, move through the commit's images, read its description, or download it. Terminal commits can attach images without descriptions; the signed API cannot attach images.

Sources: [image picker](resources/js/components/commit-image-picker.tsx), [image limits](app/Actions/CreateCommitImages.php), [attachment validation](app/Http/Requests/CreateCommitRequest.php), [photo controller](app/Http/Controllers/CommitPhotoController.php).

### Links, link cards, and embedded players

Each link in a commit or reply counts as at most **23 characters** toward the 4,000-character allowance; shorter links count at their actual length. This also applies to quotes, scheduled commits, previews, and terminal and app submissions. The full URL is stored and followed directly, not through a redirect service. Prose URLs longer than **2,048 characters** are rejected, including the inferred `https://` scheme for bare domains. Code still counts literally toward the 4,000-character allowance. Bodies are additionally capped at 40,000 raw characters to bound storage and processing. Private messages and issue drafts keep their existing literal character limits.

URLs and bare domains in commits and replies become clickable links. Long URLs are shortened visually; hover reveals the full destination. GitHub repository links show as `owner/repo`, and issue or pull request links as `owner/repo#123`, with the GitHub mark. Links to /dev/null commits show as the author's username and a short hash. Code keeps its literal text. The first link normally gets a preview card with available title, description, and image. In the commit and reply composers, choose another link from the body or choose no card; quotes, terminal commits, and app commits always use the first link. Missing site metadata may leave a link without a rich preview.

Supported links can also show click-to-play embedded players: YouTube, Vimeo, Loom, Twitch, Spotify, SoundCloud, Apple Music, Suno, CodePen, and asciinema. Only the link that gets the card can show a player, so choosing no card means no player. Embeds depend on server configuration and the linked URL being supported. Third-party player content loads only after you press play; those providers have their own privacy policies. This is embedding a link, not uploading a video or audio file to /dev/null.

Sources: [link rendering](resources/js/components/linkified-text.tsx), [link-card selection](app/Http/Requests/CreateCommitRequest.php), [embed configuration](config/embeds.php), [embedded player](resources/js/components/link-embed.tsx).

### Replies and threads

Reply to a visible commit to continue its thread. Replies use the same text limits, image support, moderation, and submission rate limit as commits. Threads expand inline, a few replies at a time: choose 1, 3, 5, 10, 15, 20, or 25 in Settings → Appearance (3 by default). Opening a commit's own page shows its ancestors and replies. Quotes of a commit are listed among its replies and counted with them. Profiles have a separate Replies tab for authored replies. A reply cannot also be a quote, contain a poll, or be scheduled.

When an author replies to their own commit, their earliest such reply continues it as a thread. The commit's page reads that thread in order, 10 commits at a time with **Show more of this thread**, each marked with its place, such as `3/7`. Elsewhere, a continued commit shows **Show thread**, which opens its page. The thread stops at the first reply you cannot see, and is hidden if you muted the author.

A commit that is deleted or hidden while you are reading it stays on screen with its actions turned off.

Deleting a parent does not automatically delete other people's replies: an authorless `[deleted]` placeholder can keep the thread intact. You cannot add a new reply to a deleted commit. Approved replies notify the direct parent's author, except when replying to yourself.

Sources: [reply validation](app/Http/Requests/CreateCommitReplyRequest.php), [commit page](app/Http/Controllers/CommitController.php), [reply notifications](app/Actions/CreateReplyNotifications.php), [commit deletion](app/Actions/DeleteCommit.php).

### Quoting commits

Quote a visible commit to publish a new top-level commit with your own commentary and an embedded card pointing to the original. Quotes follow normal commit limits, image support, and moderation. The quote composer has no preview, link-card choice, poll, or scheduling. Quoting is different from replying: the quote appears in logs as its own post, and it is also listed among the original's replies and counted in its reply count. The original author is notified after approval, except for self-quotes.

The embedded original only shows content the viewer may see. Deleted originals appear as `[deleted]`; otherwise-inaccessible originals appear as `[unavailable]` without exposing their author or body. There is no comment-free repost action.

Sources: [quote validation](app/Http/Requests/CreateCommitRequest.php), [quoted commit card](resources/js/components/quoted-commit.tsx), [quote notifications](app/Actions/CreateQuoteNotifications.php).

Livestream history: on [day two at 7:31:27](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=27087s), the team explained why plain reposts were unnecessary: quoting already existed and required the person sharing to write something themselves.

### Polls and voting

Add a poll to a top-level commit with **two to four distinct options**, each up to **50 characters** (with the same emoji suggestions and `:shortcode:` conversion as the commit body), and a duration of **one hour, one day, three days, or seven days**. Polls cannot accompany images or be added to replies. Voting opens when the commit is approved, so time spent pending does not consume the voting period.

Signed-in readers can choose one option and vote once while an approved, visible poll is open; a vote cannot be changed or withdrawn. The author cannot vote in their own poll. Results are visible to the author and people who have voted, and to everyone once the poll closes. Other people's individual votes are not revealed.

Sources: [poll editor](resources/js/components/poll-editor.tsx), [poll results](app/Models/Poll.php), [voting rules](app/Rules/OpenPoll.php), [approval and poll opening](app/Actions/UpdateCommitStatuses.php).

### Scheduled commits

Use the composer's scheduling control to submit a top-level commit later. Pick a future time within **30 days**, in five-minute steps; you can have at most **10 outstanding scheduled commits**. Scheduled posts can carry images or a poll under the normal attachment rules, but replies and quotes cannot be scheduled. Scheduling counts against the 10-per-minute submission limit. The scheduled-commits page, linked from the scheduling control, is private to you: it lists queued posts, which you can cancel, and already submitted ones with their outcome.

The scheduler checks due posts every minute. Scheduling submits the post for the normal publishing and moderation flow; it is not a promise that an approved public post will appear at the exact second selected. To change a queued post, cancel it and schedule a replacement.

Sources: [schedule validation](app/Http/Requests/CreateScheduledCommitRequest.php), [outstanding limit](app/Rules/ScheduledCommitLimit.php), [scheduled-commit controller](app/Http/Controllers/ScheduledCommitController.php), [scheduler](routes/console.php).

### Deleting commits and undo

Delete your own commit or reply from its controls. An **Undo** action is available for **30 seconds**, restoring the same commit without sending its notifications again. Deletion removes the post from normal lists, but visible replies can remain under an authorless `[deleted]` placeholder. Deleting a post is different from permanently deleting your account and all its top-level threads.

Sources: [commit deletion](app/Actions/DeleteCommit.php), [restoration validation](app/Http/Requests/CreateCommitRestorationRequest.php), [commit model](app/Models/Commit.php).

Livestream history: on [day one at 5:17:38](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=19058s), the team objected to deleting every reply when a parent commit was deleted. They kept the existing constraint temporarily and agreed to track soft deletion as follow-up work; this discussion is not the moment that reply preservation shipped.

### Stars

Star someone else's visible commit or reply to show appreciation, or unstar to remove that star. You cannot star your own commits. A star contributes to the displayed count and can notify the author; unstar removes that notification. Starring the same commit repeatedly does not add multiple stars. Stars are an engagement action, not a private reading list; use Saved for bookmarks.

Sources: [star authorization](app/Http/Requests/CreateCommitStarRequest.php), [star notifications](app/Actions/CreateStarNotification.php), [unstar notifications](app/Actions/DeleteStarNotification.php).

Livestream history: “Stars instead of likes” received immediate agreement on [day one at 4:30:03](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=16203s), extending the platform's developer-oriented terminology.

### Saved commits

Save a visible commit or reply with the bookmark control and find it later in **Saved** at `/saved`. Saved commits are private to your account and listed newest saved first. Saving does not notify the author. Unsave to remove an item from the list. A saved reference does not preserve access to content that has since been deleted or become unavailable to you.

Sources: [saved page](resources/js/pages/bookmark/index.tsx), [bookmark controller](app/Http/Controllers/BookmarkController.php), [bookmark query](app/Queries/SavedCommitsQuery.php).

### Pinning a commit

Pin one of your own top-level commits to feature it at the top of your profile's Commits tab. Pinning another replaces the previous pin; unpin to return to the normal list. Replies cannot be pinned, and pinning does not override a commit's visibility or moderation state.

Sources: [pin authorization](app/Http/Requests/UpdateCommitPinRequest.php), [pin action](app/Actions/UpdateCommitPin.php), [profile page](resources/js/pages/user/show.tsx).

### Pastéis mode and awards

Enable **Pastéis mode** in Settings → Profile to give pastry awards to other people's commits. You get **10 pastéis per day**, with the allowance resetting at **00:00 UTC**, and each click gives one. You cannot award your own commit. Unlike stars, repeated clicks can give more than one pastel to the same commit, using your daily allowance.

Pastéis do not notify anyone and do not affect Trending. Turning Pastéis mode off hides its controls and counters, including the pastéis count on profiles; people can still give you pastéis. The pastel emoji and mode commemorate Fernando Andrade bringing Portuguese custard pastries to the livestream team, rather than representing a payment or purchasable currency.

Sources: [Pastéis setting](resources/js/components/pasteis-mode-setting.tsx), [award action](app/Actions/CreateCommitPastel.php), [award authorization](app/Http/Requests/CreateCommitPastelRequest.php), [pastry lore](#pastéis-de-nata).

### Notifications

Open **Notifications** to read in-app activity: follows, replies, quotes, mentions, stars, and moderation messages about rejected commits (approvals do not notify). The navigation shows unread activity and checks for more every 30 seconds. Related activity is grouped, with expandable details: stars per commit, follows per day, and replies and mentions per thread. Filters (Replies, Quotes, Stars, Mentions, Follows, Moderation) are remembered, and the unread count follows them. Opening the inbox marks all notifications read. You can follow back or unfollow from follower notifications.

Pending replies, quotes, and mentions wait for approval before notifying others. Self-actions do not notify you; mentioning the parent or quoted author does not duplicate their reply or quote notification. A new follower notifies only once even if they unfollow and follow again. Deleted or unavailable sources, mutes, and blocks affect which notifications remain visible. Rejected commits can produce a moderation message with a reason, without revealing a moderator's identity.

Sources: [notifications page](resources/js/pages/notification/index.tsx), [notification filtering](app/Queries/NotificationBuilder.php), [grouping](app/Queries/NotificationGroupsQuery.php), [moderation notifications](app/Actions/UpdateCommitStatuses.php).

Livestream history: the notification requirements on [day one at 5:28:53](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=19733s) explicitly included removing stale activity after an unfollow or deleted reply. The stated goal was to avoid both spam and notifications that “no longer make sense.”

### Remotes: direct messages

**Remotes** is /dev/null's private messaging inbox at `/remotes`. Open it from navigation or use a developer's **Remote** control. Remotes can be disabled in Settings → Profile; disabling keeps existing conversations but stops new messaging. By default, you can contact someone who follows you, provided both accounts have Remotes enabled and neither has blocked the other. The follow is only needed for the first message: once a conversation exists, both people can keep writing. Administrators have a contact-policy exception. Tom has Remotes turned off, so he cannot be messaged.

Messages support up to **2,000 characters**, replies to messages, link cards, and up to three images with the same file limits and descriptions as commit attachments. An image makes message text optional. Messages cannot be edited or deleted, and mentions in them do not notify anyone. React to a message with any emoji, up to 12 different ones per person per message: three quick reactions sit next to each message, a picker searches the rest, and a double-tap or double-click toggles ❤️. Typing indicators show recent activity. Read receipts, called **pull receipts**, are on by default and shared only when both relevant people enable them. Stashing a conversation archives it for you without deleting it; it pops back when anyone writes in it. On desktop, **Dock** keeps the inbox at the bottom of every page. New messages arrive by polling, not push: every few seconds while a conversation is open. Message bodies are encrypted at rest with the app key, not end to end. Block someone from inside a conversation with its menu.

Some deployments enable message requests and an **everyone** contact option. With requests enabled, a first message from someone outside the accepted contact policy waits in **Fetched**. The recipient can **merge** the request to accept or **drop** it privately. The sender cannot keep sending while waiting, and the recipient must merge before replying. These options are disabled by default in repository configuration; do not assume every installation exposes them.

Sources: [Remotes setting](resources/js/components/remotes-setting.tsx), [contact policy](app/Models/User.php), [push access](app/Queries/PushAccessQuery.php), [reactions](app/Http/Controllers/MessageReactionController.php), [message validation](app/Http/Requests/CreateConversationMessageRequest.php), [remote preferences](resources/js/components/chat/remote-settings-menu.tsx), [configuration](config/remotes.php).

### Orgs: group chats

An **Org** is a private group chat within Remotes, not a synchronized GitHub organization. Create one with other eligible developers; a group can contain up to **100 people including you**. Members need Remotes enabled, must follow the person adding them (unless that person is an admin), and must have no block with anyone in the org. A group's optional name can be up to **60 characters**.

The creator starts as a **Maintainer**, and invited people start as **Contributors**. Maintainers can rename the group, add or remove members, and change roles. Contributors can read and send messages. When adding members, a maintainer can share existing history or only new messages. Members can leave or stash the group for themselves. At least one maintainer remains while the group has members: the last maintainer cannot be demoted, and if they leave, the longest-standing remaining member takes over. An org that everyone has left is deleted.

Group messages support the same text, images, replies, link cards, reactions, and typing indicators as direct messages. A member's read position shows only when both you and that member have pull receipts on. Any new message pops the org back for members who stashed it. Membership and history access determine what a person can read.

Sources: [group creation](app/Http/Requests/CreateOrgRequest.php), [invitation eligibility](app/Queries/RemoteCandidatesQuery.php), [history sharing](app/Actions/CreateOrgMembers.php), [role changes](app/Http/Requests/UpdateOrgMemberRequest.php), [leaving a group](app/Actions/DeleteOrgMember.php).

### Muting and blocking

**Mute** someone to remove their commits and replies from your logs, threads, search, and suggestions and stop their notifications. Muting is silent, leaves follows intact, and still lets you visit their profile, which shows a **Muted** badge. Commits you saved stay in Saved.

**Block** someone to stop messaging and interactions such as following, replies, stars, pastéis, and Tom's replies between the two accounts, and hide each other's commits while signed in. Their profile and follower lists become unavailable to you. Blocking removes follows in both directions. Unblocking does not restore those follows. Manage and reverse either choice in Settings → Safety. Blocking is an account-level interaction control, not a way to make public posts inaccessible to signed-out visitors.

Sources: [safety settings](resources/js/pages/safety/edit.tsx), [mute action](app/Actions/CreateUserMute.php), [block action](app/Actions/CreateUserBlock.php), [public routes](routes/web.php).

### Reporting commits

Report another person's visible, approved commit or reply privately from its controls. Choose a reason (spam or misleading links, harassment or hate, harmful or private information, or something else) and optionally add up to **500 characters** of detail. You can report a particular commit once, and file up to five reports a minute. Reporting does not immediately hide or reject the content, but for seven days after an undismissed report, the author's new commits wait for a moderator instead of being approved automatically. Moderators review reports without the reporter's identity and can dismiss the report or reject the commit, with an optional reason sent to its author. Deleting a reported commit keeps its reports. This is a content-safety report, not a software bug report; use `/bug` for app defects.

Sources: [report control](resources/js/components/commit-report-button.tsx), [report validation](app/Http/Requests/CreateCommitReportRequest.php), [reported-commit controller](app/Http/Controllers/ReportedCommitController.php).

### Appearance, color, and timezone

Settings → Appearance offers:

- **Theme:** Light, Dark, or System.
- **Navigation:** expanded or collapsed, on wide screens. The navigation also has its own collapse button.
- **Lane dimming:** on by default; other commits fade while the pointer rests on a commit or its author.
- **Replies:** how many replies show under a commit before "Show more": 1, 3, 5, 10, 15, 20, or 25 (3 by default).
- **Who to follow:** show or hide the suggestions.

These appearance choices are saved in the browser, not on the account, so each browser keeps its own. Settings → Profile lets you choose one of six lane colors (Orange, Green, Violet, Teal, Rose, Ochre), a custom color, or the automatic color used around your identity and activity graph.

Profile settings also let you share a timezone. Timestamps can show the author's local time as well as the viewer's when a timezone is shared. Leaving it unset uses the browser's timezone for your display without publishing your timezone. These /dev/null preferences are separate from your GitHub-managed profile information.

Sources: [appearance settings](resources/js/pages/appearance/update.tsx), [lane picker](resources/js/components/lane-picker.tsx), [timezone picker](resources/js/components/timezone-picker.tsx), [timestamp tooltip](resources/js/components/timestamp-tooltip.tsx).

### Installing /dev/null as an app

/dev/null is a progressive web app. Browsers that support installation (Chrome, Edge, and Safari's **Add to Home Screen** / **Add to Dock**) offer to install it from the web app manifest; the installed app opens in its own window without browser controls, uses the app icon, and follows the Light, Dark, or System appearance in its title and status bars. The app's icon menu offers shortcuts to the Following log, the Latest log, and Notifications. Opening a /dev/null link while the app is installed focuses the existing window instead of opening another.

A service worker caches the built assets of the current release and shows an offline page when a page cannot be reached; the page reloads on its own once the connection returns. Pages themselves are never cached, so nothing a signed-in user sees is kept after signing out, and there is no offline reading of logs or offline posting. There are no push notifications; the installed app polls for new notifications and messages the same way the browser does.

Sources: [web app manifest](public/site.webmanifest), [service worker](public/sw.js), [offline page](public/offline.html), [service worker registration](resources/js/lib/service-worker.ts), [document head](resources/views/app.blade.php), [manifest tests](tests/Feature/WebAppManifestTest.php).

### Activity graphs and README embeds

Profiles display commit activity with current and longest streaks. The profile graph adapts to how long someone has been committing: the last 24 hours, 7 days, 30 days, or a year. Hovering a cell highlights that period's commits. Settings → Profile includes a live preview and copyable Markdown for embedding your /dev/null activity graph in a GitHub profile README. The graph is public at `/@{username}/graph.svg`, uses your lane color, and supports `?theme=light`, `dark`, or `auto` (light by default). The SVG is cached for an hour, so a new commit may not appear in an external embed immediately.

Sources: [graph embed settings](resources/js/components/activity-graph-embed.tsx), [graph controller](app/Http/Controllers/UserActivityGraphController.php), [graph route and cache](routes/web.php).

### Keyboard shortcuts

Press **?** to open the shortcut reference. Navigation shortcuts work outside text inputs; composer shortcuts apply while writing. **⌘** on macOS corresponds to **Ctrl** elsewhere.

| Shortcut | Action |
| --- | --- |
| `j` / `k` | Focus the next / previous commit. |
| `Enter` | Open the focused commit. |
| `y` | Copy the focused commit's link. |
| `r` / `q` / `o` | Reply, quote, or show/hide replies. |
| `c` | Write a commit. |
| `⌘/Ctrl+K` or `/` | Search developers and commits. |
| `⌘/Ctrl+Enter` | Submit from the composer. |
| `⌘/Ctrl+Shift+P` | Toggle composer preview. |
| `g` then `l` / `e` / `p` / `s` | Go to your log / Trending / profile / settings. |
| `a` / `x` | Approve / reject in the moderation queue, for moderators. |

Source: [shortcut reference](resources/js/components/keyboard-shortcuts-dialog.tsx).

### Terminal commits

Settings → Terminal shows how to post using the `/commit` shell script. It signs the submission locally with an Ed25519 or RSA SSH key whose public half is on your GitHub account, as an authentication or a signing key, and /dev/null verifies that signature. No browser login or API token is required for that submission; your private key is not uploaded. The script can create commits, reply with `--reply` using a commit hash or link, and attach up to three images with `--image`, without descriptions.

Terminal posts follow the same moderation and 10-per-minute submission limit as browser posts, and always need 3–4,000 characters of text, even with images. Their first link supplies the default link card. Review a downloaded script before running it, and never paste a private key into a commit.

Sources: [terminal instructions](resources/js/pages/terminal/show.tsx), [CLI routes](routes/api.php), [signature verification](app/Http/Middleware/AuthenticateSshSignature.php).

Livestream history: terminal posting was reported working on [day one at 6:32:47](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=23567s). The explanation that follows describes signing with an existing SSH private key and checking the public key on GitHub, with a curl command so users would not need to install a dedicated CLI. This was signed authentication, not anonymous posting, despite the conversation's shorthand about not setting up authentication.

### GitHub Actions integration

Use `nunomaduro/dev-null.sh/.github/actions/commit@production` to publish updates from a GitHub workflow, for example when a release is published. Settings → Terminal includes a workflow example. The action accepts a commit body, an SSH private key supplied through a GitHub Actions secret, an optional `reply-to` reference, optional image paths (up to three), and an optional `url` for another /dev/null instance.

Generate a dedicated CI key, add its public half to GitHub as a signing or authentication key, and store the private half only in the repository's encrypted secrets. The action supports Ed25519 or RSA keys without a passphrase. It reports the created commit's link and whether it is pending review as a workflow annotation; it has no step outputs, and a refused submission fails the job. The usual moderation and submission limits still apply.

Sources: [workflow instructions](resources/js/pages/terminal/show.tsx), [GitHub action](.github/actions/commit/action.yml).

### Public read API and Atom feeds

The read-only JSON API at `/api/v1` requires no authentication or token. Read a public profile at `/api/v1/users/{username}`, that user's commits at `/api/v1/users/{username}/commits`, or one commit at `/api/v1/commits/{commit}`. `/api/v1/users/{username}/followers` and `/following` list people the way the profile's people lists do, newest follow first; a signed request also gets each person's lane and whether you follow each other. Add `?replies=1` to the user-commits endpoint to read their replies instead of top-level commits. Lists use cursor pagination: 20 commits or 30 people per page. Only public approved, non-deleted content is returned, not private profile data such as email. Unsigned requests are limited to **60 requests per minute per IP** and cannot create posts, follow, or star; terminal posting and the [signed API](#signed-api-and-devices) are separate signed interfaces.

Subscribe in a feed reader to `/@{username}/feed.atom` for a profile's latest **50 approved top-level commits**, including image enclosures. Feeds are public and can be cached for five minutes.

Sources: [API routes](routes/api.php), [API controllers](app/Http/Controllers/Api), [feed generation](app/Actions/CreateUserFeed.php), [rate limits](app/Providers/AppServiceProvider.php).

### Signed API and devices

An app can use the same `/api/v1` for you with signed requests. It keeps an SSH key on the device and signs every request with it; there are no passwords or API tokens. To sign in, the app opens `/mobile/authorize` on the web: sign in with GitHub if needed, check the key's fingerprint, and approve it. The key then becomes one of your devices, and it does not need to be on your GitHub account. A key that is only on GitHub, such as a GitHub Actions key, cannot use the signed API.

A signed request sees what the web shows you, such as your own pending commits. It can also, with the web's validation, moderation, and rate limits:

- read your account, logs, replies, notifications, and saved commits;
- commit, reply, and quote, and delete your commits;
- follow or unfollow, mute or unmute, and block or unblock, and list who you muted and blocked;
- star or unstar, and save or unsave;
- switch Pastéis mode and give pastéis;
- report commits;
- sync from GitHub and save your lane color;
- delete your account.

Signed request bodies are JSON, so app commits are text only: no images, polls, or link-card choice. Pinning, Remotes, scheduling, search, and translations are web-only for now. Signed requests are limited to **120 requests per minute per user**. Settings → Devices lists your approved devices with when each was last used, and revokes them.

Sources: [signed requests](app/Services/SignedRequests.php), [sign-in page](app/Http/Controllers/MobileAuthorizationController.php), [devices settings](resources/js/pages/device/index.tsx), [API routes](routes/api.php).

### Bug reports and feature requests

Start a fresh commit draft with **`/bug `** or **`/feature `** to switch it into an issue draft. Submitting opens a **public GitHub issue** on `nunomaduro/dev-null.sh`, crediting you, rather than posting a normal commit. The issue includes your browser and screen details (browser, OS, viewport, pixel ratio, language, color scheme, and the current page) in a collapsed Environment section. The composer offers command suggestions and a way to cancel back to a commit. Issue drafts are text-only and accept 3–4,000 characters; remove detected secrets before submission.

Empty issue drafts show a short Markdown template: steps and expected versus actual behavior for bugs, or the problem and proposal for features. The first top-level `# Heading` becomes the issue title and is removed from the body; the remaining Markdown is preserved. Without a heading, the first line is used as the title and the full draft remains in the body.

Successful issue creation is limited to **five issues per hour per user**. If GitHub or the connection fails, the draft stays available. Check upstream before retrying an uncertain submission to avoid duplicates. Use a commit's Report control instead when reporting abusive content.

Sources: [issue command UI](resources/js/components/issue-command.tsx), [issue validation](app/Http/Requests/CreateIssueRequest.php), [issue controller](app/Http/Controllers/IssueController.php).

### Exporting your data

Choose **Download JSON** in Settings → Profile to export your account data. The export includes profile details, your commits and replies (including pending, rejected, and deleted entries still stored), polls and your votes, stars, saved commits, follows, mutes, blocks, messages you sent, notifications, SSH key fingerprints, the devices you approved for an app, the last day you used /dev/null, and active-session metadata. Other people's replies and received messages are not included as your authored data. Message images, message reactions, reports you filed, and scheduled commits are not included either. Image references are URLs, not an archive of the image files.

The export does not expose session IDs, authentication tokens, private SSH keys, or other people's poll choices. Keep the downloaded JSON private because it includes personal account information. Exports are limited to **three per hour**.

Sources: [export settings](resources/js/pages/user-profile/edit.tsx), [export contents](app/Actions/CreateExport.php), [export download](app/Http/Controllers/ExportController.php), [rate limits](app/Providers/AppServiceProvider.php).

### Deleting your account

Settings → Profile → **Delete account** opens a confirmation for permanent deletion of your /dev/null account; an app can also delete it through the signed API. It does not delete your GitHub account. Your top-level commits and every reply under those threads are erased. Your replies are also removed, except that replies from other people beneath them survive under authorless `[deleted]` placeholders. Your devices, the messages and reactions you sent, and all your uploaded images are erased too; other people's messages in your conversations stay with them. Account deletion is not covered by the 30-second commit undo.

You can sign in with GitHub again later, but start from a new account rather than restoring the deleted one. Export your data first if you want a copy. Deleting from /dev/null cannot retract copies of public content held elsewhere.

Sources: [account deletion confirmation](resources/js/components/delete-user.tsx), [account deletion](app/Actions/DeleteUser.php), [privacy policy](resources/js/pages/privacy/show.tsx).

### Moderation

Commits and replies may be pending, approved, or rejected. A commit is approved at once when its author is an admin, or has a good reputation: an account older than an hour, at least one approved commit, at least 10 followers, and no undismissed report filed against any of their commits in the last seven days (deleted and rejected commits included). Other commits start pending. When OpenAI is configured, automated moderation checks the commits of authors without a good reputation: clean commits are approved, near-certain violations are rejected without a reason, and anything else waits for a moderator. While an author has an undismissed recent report, none of their commits is approved automatically: authors with a good reputation skip the automated check, and a clean verdict for anyone else still waits for a moderator. Pending and rejected content is not publicly visible; its author can still see its status. On a pending commit, a badge tells the author whether it is still being checked automatically (usually a few seconds, polled for about half a minute) or waiting for a moderator; when the check approves it while the author is looking, the badge shows a check mark. Approval makes content eligible for public lists and releases reply, quote, and mention notifications, but does not notify the author. Rejection notifies the author, with the moderator's reason when one is given. Moderation applies to browser, scheduled, terminal, and app submissions alike.

Moderators are the site administrators, set by GitHub username in the `ADMINS` environment variable. They have a moderation queue at `/moderation` for reviewing pending commits oldest first, with the automated verdict and a translation: approve or reject each (with an optional reason up to 280 characters), or approve up to 100 at once. A reports queue at `/moderation/reports` lists user-reported content. Moderators can dismiss reports or reject reported commits. These are restricted tools, not controls every account receives.

Automated moderation makes up to three attempts with one-minute and ten-minute retry delays and a three-minute timeout per attempt. Provider failures never approve unreviewed posts: exhausted jobs leave them pending for administrator review or an explicit failed-job retry. Moderation and translation failures report the commit identifier, exception type, and error code without retaining the provider's response in the exception.

Sources: [moderation access](app/Http/Requests/ShowModerationRequest.php), [moderation controller](app/Http/Controllers/ModerationController.php), [status transitions](app/Actions/UpdateCommitStatuses.php), [reports controller](app/Http/Controllers/ReportedCommitController.php).

### Operations dashboard

Administrators can open `/operations`, split into tabs under a sticky header like Settings. The Health tab (`/operations`) inspects database and cache connectivity, the GitHub API rate limit when credentials are configured, queue backlogs, recent failed jobs with retry or forget controls, pending moderation, and link-preview health. The Features tab (`/operations/features`) switches site-wide features, currently translations, off and on for everyone. The Activity tab (`/operations/activity`) opens with today's signups, commits, replies, and active authors (UTC day) next to yesterday's, the same four counts since the first signup, then draws everyone's commits, replies, or signups on the same graph as a profile (hourly cells over the last 24 hours, daily cells over 7 or 30 days, or a year grid), with pickers for the series and the window; the 24h, 7d and 30d windows end now, so the first cell only holds the end of the hour or day the window starts in. A Breakdown section follows, listing the window's commits and replies by status (approved, pending, rejected) and its ten busiest authors, each previewing and linking to their profile. The Traffic tab (`/operations/traffic`) exists when a Fathom API token is configured: visitors on the site now, plus visitors, pageviews, average time on site, bounce rate, and the top pages, referrers, countries, devices, and browsers for a chosen date range (the last 7 days by default). The Metrics tab (`/operations/metrics`) follows the product funnel, described under [Product metrics](#product-metrics). Every tab except Metrics refreshes every 15 seconds, and every tab has a manual refresh button. Non-admin users cannot access any of them.

Sources: [operations controller](app/Http/Controllers/OperationsController.php), [site activity controller](app/Http/Controllers/SiteActivityController.php), [traffic controller](app/Http/Controllers/TrafficController.php), [feature switches](app/Services/Features.php), [access check](app/Http/Requests/ShowOperationsRequest.php), [operations layout](resources/js/layouts/operations/layout.tsx), [health tab](resources/js/pages/operations/show.tsx), [features tab](resources/js/pages/operations/features.tsx), [activity tab](resources/js/pages/operations/activity.tsx), [traffic tab](resources/js/pages/operations/traffic.tsx).

### Product metrics

Administrators follow the product in Operations → **Metrics**, for a range of signup dates (all time by default). The funnel counts visitors who are not signed in, signups, **activation** (getting something from the network within 12 hours of signing up: starring or saving commits by three or more people, or a reply, quote, or star from someone else on your own post), and **retention** (coming back at least once from the day after signing up). Each step shows its rate and how it changed since the period before, and a chart shows the activation rate of each signup day. The tab also shows active users, how often commits get an answer within 24 hours, how many people newcomers follow in their first day, and a chart of daily active users. Bots are left out everywhere.

To count active people, /dev/null remembers the **last day** you were signed in and opened a page, did something, or used the API from the mobile app, the terminal, or the GitHub Action, the day and not the time. Background refreshes do not count. That day is the only thing stored about you for metrics: it is never shown to other people, it is included in your export, and it is deleted with your account. Each day's number of active people is kept as one anonymous count. Visitors and the conversion rate come from Fathom, which counts anonymously: the browser sends a `guest-visit` event once per session when you are not signed in and a `signup` event right after an account is created, with nothing that identifies you.

The definitions, their reasoning, benchmarks, and the metrics left for later are in [PRODUCT_METRICS.md](PRODUCT_METRICS.md).

Sources: [metrics controller](app/Http/Controllers/ProductMetricsController.php), [funnel](app/Queries/ProductFunnelQuery.php), [last seen tracking](app/Http/Middleware/UpdateLastSeenAt.php), [daily count](app/Actions/UpdateUserLastSeenAt.php), [Fathom events](resources/js/lib/analytics.ts), [metrics tab](resources/js/pages/operations/metrics.tsx).

### About, changelog, and policies

The public **About** page describes the livestream project and its contributors. The **Changelog**, opened from the navigation or the side column, lists the pull requests merged into `production` on GitHub, a few days at a time. The **Brand** page at `/brand` offers the logo, wordmark, colors, typography, and wallpapers; right-clicking the logo in the navigation also copies it or links there. **Terms** and **Privacy** explain acceptable use and data handling. Public posts may be indexed or copied outside /dev/null; content and search queries can be processed by automated or AI services. Read those policies before posting sensitive information or relying on deletion to remove every external copy.

Sources: [public page routes](routes/web.php), [About](resources/js/pages/about/show.tsx), [privacy policy](resources/js/pages/privacy/show.tsx), [terms](resources/js/pages/terms/show.tsx).

### Tom's repository insights and community statistics

Tom can read public GitHub data for `nunomaduro/dev-null.sh`: up to ten top contributors by GitHub-reported commit count (excluding bots), recent pull requests merged into `production`, open issues labelled **good first issue** or **help wanted**, stars and forks, separate open-issue and open-PR counts, and repository language bytes. These are read-only tools, not arbitrary repository access. A merge does not prove deployment, and a limited list is not an exhaustive count. GitHub insights are cached for fifteen minutes; contributor data can lag by hours and changelog pages have an additional thirty-minute cache. Public reads reuse the application's existing GitHub OAuth credentials, not a user's token.

For feature attribution, Tom can search all indexed dates of merged `production` PR titles and descriptions and non-merge Git commit messages on the default branch. He translates questions into feature keywords and can retry with synonyms or fewer words. All keywords must match, in any order, rather than as one exact phrase. This is AI-guided keyword search, not a semantic GitHub index. Each search returns up to five best matches of each kind, with source links, dates and GitHub-linked author accounts; descriptions are searched but not returned to the model. Results are cached for fifteen minutes, with at most two searches per reply. This is not a code or diff search: matches may be follow-up fixes, missing matches do not establish absence, and PR or commit authorship does not prove sole implementation or list every co-author. Unknown accounts stay unknown; committers and mergers are not credited as implementers. GitHub failures or incomplete searches are reported as unavailable.

History results include a verified local @mention when the author's stable GitHub account ID matches an onboarded member with a real handle. Internal rename placeholders are excluded until a real handle is restored. Tom uses the verified handle rather than the GitHub login; unmatched accounts remain plain GitHub usernames, without an @. Membership and handles are resolved afresh even when GitHub results are cached, so onboarding, renames and account deletion are reflected immediately. This does not disclose online status or last-seen activity, and account IDs are never sent to the model.

Tom can also count current onboarded members (excluding Tom), members whose accounts were created in the last seven or thirty days, and distinct members who posted approved, non-deleted public commits or replies during those windows. Public-author counts are not online-user counts. These aggregates are cached for five minutes and include their observation time and definition.

Country questions use whole-term matches against public profile location text, with UK and US aliases; they do not establish nationality or residence or infer countries from cities, names, IP addresses or timezones. Programming-language questions count mentions in public bios using a supported language list and explicit aliases. Ambiguous Go and C mentions require programming phrases. These counts describe profile text, not everyone who uses a language; missing or unrecognized text is not counted. The community tool returns aggregates, never lists of people, private messages or moderation data, and does not combine demographic filters. GitHub failures are reported as unavailable, not zero.

Sources: [repository insights](app/Ai/Tools/RepositoryInsights.php), [repository history search](app/Ai/Tools/SearchRepositoryHistory.php), [history queries](app/Queries/RepositoryHistoryQuery.php), [community statistics](app/Ai/Tools/CommunityStats.php), [repository queries](app/Queries/RepositoryInsightsQuery.php), [community queries](app/Queries/CommunityStatsQuery.php).

### Hello world replies

An approved commit or reply whose entire text is **hello world**, including variants such as **Hello, World!**, can receive an automatic **“Hello, {name}.”** reply from **@world**, which notifies its author like any reply. A pending commit waits for approval. The bot replies only once to that commit. If the real GitHub owner of `world` signs in, the bot moves to **@devnull-world** and keeps replying with its existing profile and history. The human gets a separate account. It is a small greeting feature, separate from Tom's question-answering role.

Sources: [greeting matching](app/Services/HelloWorld.php), [world reply](app/Actions/CreateWorldReply.php).

Livestream history: on [day two at 4:08:10](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=14890s), the Easter egg was announced as merged, and viewers were invited to post “hello world” without tagging anyone. The plan was to surprise Nuno with the moderation queue and automatic replies; viewers were told not to spoil it in chat.

## Stack and hosting

| Area | Technology and role |
| --- | --- |
| Hosting | **Laravel Cloud** hosts the application. |
| Backend | **PHP** and **Laravel** handle the application, routes, database access, and background jobs. |
| Frontend | **React** and **TypeScript**, connected to Laravel through **Inertia**. |
| Styling and components | **Tailwind CSS**, **Radix UI**, and **Headless UI**, with **Lucide** icons. |
| Database | **PostgreSQL**, with **pgvector** for vector data and **pg_trgm** for text matching. |
| Authentication | **GitHub OAuth** through **Laravel Socialite**. GitHub is the source of truth for user identity and profile information. |
| Frontend routes | **Laravel Wayfinder** generates typed route helpers. |
| Images, code, and signatures | **Intervention Image** re-encodes uploads, **Tempest Highlight** highlights code on the server, and **phpseclib** verifies SSH signatures from the terminal and the app. |
| Analytics | **Fathom** provides the visitor numbers in Operations → Traffic. |
| Asset tooling | **Vite+ / Vite** builds frontend assets; **Bun** manages frontend dependencies and runs scripts. |
| PHP dependencies | **Composer** manages backend dependencies. |
| Testing and quality | **Pest**, **PHPStan / Larastan**, **TypeScript**, **Laravel Pint**, **Rector**, and frontend formatting and linting through **Vite+**. |
| AI services | **Laravel AI SDK** runs Tom and public-post translation with **DeepSeek Flash** through **OpenCode Zen** and generates and caches semantic search query embeddings through **OpenAI**. The existing OpenAI integration handles batch embeddings, commit relevance checks, and moderation. |

The repository defaults to database-backed queues, cache, and sessions. File storage is configurable, with local and S3-compatible storage supported. These are repository defaults and supported options, not a claim about every production environment setting.

The knowledge base is split into Markdown sections by heading and stored with vector embeddings in PostgreSQL. An hourly scheduled job refreshes new or changed sections in production, so knowledge-base changes are picked up within an hour of being deployed. Unchanged sections reuse their embeddings.

An [internal semantic search query](README.md#internal-semantic-search) retrieves the nearest knowledge-base sections using Laravel AI SDK query embeddings through OpenAI and PostgreSQL cosine distance, following the same embedding approach as commit search. Tom's read-only tools expose bounded knowledge-base excerpts and public commit search results to the model. There is no standalone public knowledge-base search endpoint.

Stack references: [PHP dependencies](composer.json), [frontend dependencies](package.json), [database configuration](config/database.php), [setup requirements](README.md#getting-started), [queue configuration](config/queue.php), [cache configuration](config/cache.php), [session configuration](config/session.php), [storage configuration](config/filesystems.php), and [existing AI integration](app/Services/OpenAI.php). Laravel Cloud hosting is confirmed by the project team.

## Lore

### Product trailer

Watch the [/dev/null product trailer — “after 48 hours, we are proud to introduce: dev-null.sh”](https://www.youtube.com/watch?v=nyFSZ_AkQ0I) on Nuno Maduro's YouTube channel. For the livestreams where the platform was built and launched, see [Built on a livestream](#built-on-a-livestream).

### Built on a livestream

/dev/null began with the challenge **“Building a Social Network in PHP in 48 Hours.”** Building the platform together on the livestream was the point of the project, not a broadcast added after it was built. Viewers could watch it take shape and contribute along the way.

The livestream team included:

- **Nuno Maduro** — [@nunomaduro](https://github.com/nunomaduro).
- **Brent Roose** — [@brendt](https://github.com/brendt).
- **Matthieu Napoli** — [@mnapoli](https://github.com/mnapoli).
- **Pauline Vos** — [@paulinevos](https://github.com/paulinevos).

They built live from the JetBrains office, with community contributions becoming part of the project.

**Márk Magyar** — [@xHeaven](https://github.com/xHeaven) — was later added to the core development team, due to the large amount of valuable contributions he made to the project during the livestream. Most contributions from those two days came from him.

Watch the livestreams:

- [Day one — Building a Social Network in PHP in 48 Hours](https://www.youtube.com/watch?v=6zCNQa3hNm0).
- [Day two — Building a Social Network in PHP in 48 Hours](https://www.youtube.com/watch?v=nfmVdXZfzUE).

Timestamped livestream references in this knowledge base were located using YouTube's automatic English captions. They point to discussions and spoken reports, not independently verified deployment times. Captions can misrecognize names and technical terms; historical proposals are not evidence of current product behavior.

Sources: [Laravel News's livestream announcement](https://laravel-news.com/social-network-php-48-hours) names Nuno, Brent, and Matthieu and links both days; Pauline confirms her participation as part of the livestream team. The repository's [livestream component](resources/js/components/live-stream.tsx) links day two.

### Tom

Tom is a fictional profile inspired by **MySpace Tom**. He is everyone's mutual at the beginning: the familiar face who is already there when you arrive.

The idea to make Tom everyone's first friend appears on [day two at 7:29:26](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=26966s), followed by discussion of a robot avatar and a possible future bot. At [8:03:20](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=29000s), the team reports that Tom is deployed and runs the befriending command; at [8:04:14](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=29054s), they describe the resulting notifications and mutual follows. This is the origin of the friendly account, not evidence that his present question-answering behavior existed then.

Originally, Tom had no particular purpose beyond that nod to MySpace. He now has a narrowly scoped bot implementation powered by **DeepSeek Flash through OpenCode Zen**: simple friendly chat, explaining /dev/null's features, origins, stack and lore using this knowledge base, or finding public commits about a topic using semantic search. Greetings, thanks, jokes and small talk get a friendly reply without a search. He is not a general-purpose assistant: unrelated tasks, coding help and professional advice are outside his role. This requires configured provider credentials and a running queue worker; it is not a claim that a particular deployment has enabled him.

Tag **@tom** in a public commit or reply, or reply directly to one of his posts without tagging him. Once it is approved, Tom can reply under that commit, at most once, addressing its author rather than the thread's root poster. Replies further down the thread still need to tag him unless their direct parent is a Tom post. Mentions in code do not summon him. His replies use the normal commit moderation and notification pipeline. He does not reply to himself, across a block, in private messages, or to deleted or unapproved commits.

If the real GitHub owner of `tom` signs in, the bot moves to **@devnull-tom** with the same profile, posts and friendships. Mention **@devnull-tom** to summon him after that; direct replies and mentions captured before the rename still reach the bot. New mentions of the human **@tom** do not summon him. He remains every newcomer's first friend.

Tom remembers at most twelve public ancestor posts, with a 12,000-character budget shared with the current question. This follows the current reply branch, not sibling branches or other threads. Older context drops out; there is no separate permanent AI memory. Search results are bounded and only public commits are exposed, never pending, rejected or deleted posts. He has no browsing or write tools.

Tom is a simple, friendly robot man of few words. Small talk usually gets one short sentence; platform answers usually get one to three short sentences, with more detail only when asked. His dated personality comes through in occasional, varied text-style emoticons made from keyboard characters like people used in 2007, a few words about rust, or a tiny mechanical sound, not hardware monologues. He uses neither modern pictographic emoji nor em dashes. Near his context limit he may mention his 2012 RAM when he actually needs a recap. He is a fictional AI platform character, not the real MySpace Tom.

Sources: the project team's account of Tom's origin and personality, [agent](app/Ai/Agents/Tom.php), [reply action](app/Actions/CreateTomReply.php), [queued replies](app/Jobs/ReplyAsTom.php).

### Pastéis de nata

During the livestream, **Fernando Andrade — [@fernandofreamunde](https://dev-null.sh/@fernandofreamunde)** — brought **pastéis de nata**, Portuguese custard pastries, for the team.

On [day two at 5:47:37](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=20857s), the team reads the message that he is downstairs with the pastries and arranges to meet him. At [5:58:16](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=21496s), they thank Fernando and describe the Portuguese treats he brought. Earlier, at [5:34:42](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=20082s), they had suggested dedicating a feature to the pastries, floating achievement badges. That was an idea, not the implementation of Pastéis mode.

Another possible precursor appears on [day two at 7:18:53](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=26333s): the team discusses a single custom emoji, a daily allowance, and giving multiple rewards, then defers it with “Not today.”

The **pastel emoji** and **Pastéis mode** are a tribute to Fernando and that delivery. A real-world act of feeding the team became part of the platform's shared lore.

The singular is **pastel de nata**; the plural is **pastéis de nata**. The mode is called **Pastéis mode**.

Sources:

- [Fernando's post arranging delivery to the crew](https://dev-null.sh/@fernandofreamunde/commits/01a144fe-ed29-7356-af93-03b9724e8af6).
- [Matthieu's reply thanking him for the pastéis de nata](https://dev-null.sh/@mnapoli/commits/01a1453f-ac59-7077-b99e-98d72547ae8f).
- [Pastéis mode contribution](https://github.com/nunomaduro/dev-null.sh/pull/286).
- The project team's account confirms that the emoji and mode are a tribute to Fernando.

### The pushup break

The development stream also had an impromptu pushup break. On [day two at 2:16:46](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=8206s), a call for ten pushups interrupts a discussion of image moderation. Counting and laughter follow, then Pauline asks, “Why are we doing push-ups?” at [2:17:14](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=8234s). @nunomaduro and @paulinevos each proceeded to do ten push-ups. It is a livestream moment, not a product feature.

### The codename that stuck

Asked about the name on [day two at 1:16:04](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=4564s), the team explained that “dev null” had started as a codename the day before, but they liked it enough to keep it. Just before publishing the launch announcement at [7:58:23](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=28703s), someone asked whether they should reconsider the domain. The response was “It's too late,” followed by the publishing countdown.

### Peach P

On [day two at 0:28:19](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=1699s), a pronunciation story became a PHP joke: viewers of a video course had apparently heard “Peach P” instead of PHP. The explanation included Peach from Mario and more playful repetitions of the language's name.

### Chat chooses dark mode

On [day one at 5:03:06](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=18186s), the team read out a roughly 60% vote for dark mode, joked that light mode “burns my eyes,” and agreed to switch. This was a livestream appearance choice, not a decision that the product should support only dark mode.

### The disappearing images

On [day one at 8:36:27](https://www.youtube.com/watch?v=6zCNQa3hNm0&t=30987s), the team reported that uploaded images had disappeared after deployment because they were stored on local disk. The explanation came with laughter, “Congratulations everyone,” and a joke about “vibe coding in production.” This records the team's account of a development mishap, not the application's current storage setup.

### The anti-TikTok pull request

On [day two at 6:55:44](https://www.youtube.com/watch?v=nfmVdXZfzUE&t=24944s), the team read a joke contribution promising an endless short-video feed that would “triple the engagement by Thursday.” The twist was a proposed GitHub workflow rejecting pull requests for that kind of feature. They enjoyed the joke and said they did not want to turn the platform into TikTok, but declined the workflow over concerns about token permissions. The conversation does not establish that an automated anti-video rule was installed.
