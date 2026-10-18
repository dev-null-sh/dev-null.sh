# /dev/null knowledge base

/dev/null is a social network for developers, built in public during a livestream. Developers sign in with GitHub, share posts called **commits**, follow one another, and read timelines called **logs**.

This file documents the platform's features, stack, and stories. Feature entries describe the current implementation, including access requirements and limits, and can be retrieved independently by the knowledge-base search. Technology names intentionally have no version numbers.

## Contents

- [Repository](#repository)
- [Features](#features)
  - [Public browsing and GitHub sign-in](#public-browsing-and-github-sign-in)
  - [First-time setup](#first-time-setup)
  - [Profiles and GitHub sync](#profiles-and-github-sync)
  - [Following developers and suggestions](#following-developers-and-suggestions)
  - [Following, Latest, and Trending logs](#following-latest-and-trending-logs)
  - [Searching developers and commits](#searching-developers-and-commits)
  - [Writing commits](#writing-commits)
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
  - [Activity graphs and README embeds](#activity-graphs-and-readme-embeds)
  - [Keyboard shortcuts](#keyboard-shortcuts)
  - [Terminal commits](#terminal-commits)
  - [GitHub Actions integration](#github-actions-integration)
  - [Public read API and Atom feeds](#public-read-api-and-atom-feeds)
  - [Bug reports and feature requests](#bug-reports-and-feature-requests)
  - [Exporting your data](#exporting-your-data)
  - [Deleting your account](#deleting-your-account)
  - [Moderation](#moderation)
  - [Operations dashboard](#operations-dashboard)
  - [About, changelog, and policies](#about-changelog-and-policies)
  - [Hello world replies](#hello-world-replies)
- [Stack and hosting](#stack-and-hosting)
- [Lore](#lore)
  - [Built on a livestream](#built-on-a-livestream)
  - [Tom](#tom)
  - [Pastéis de nata](#pastéis-de-nata)

## Repository

- **Root / upstream repository:** [nunomaduro/dev-null.sh](https://github.com/nunomaduro/dev-null.sh).
- **This fork:** [paulinevos/dev-null.sh](https://github.com/paulinevos/dev-null.sh).
- **Setup and development:** [README](README.md).
- **Product terminology and behavior:** [Product guide](PRODUCT.md).

## Features

These entries cover the app's public, signed-in, and administrator features. Configuration-dependent options are called out explicitly. Each entry includes implementation references so its behavior can be checked when the app changes. Knowledge-base retrieval is internal infrastructure, not the public commit search; Tom's question-answering bot is still planned.

### Public browsing and GitHub sign-in

Guests can browse Latest and Trending logs, public profiles and their following/follower lists, approved commits and reply threads, photos, and search. Sign in with GitHub to write commits, follow, star, vote, save, receive notifications, or use private messages and settings. GitHub is the only sign-in provider; there is no separate /dev/null password.

New GitHub accounts are screened at signup. The repository defaults require an account at least seven days old and a verified email; these requirements are configurable. Returning accounts are not screened again. Public profiles and approved commits are readable by other people and crawlers, and links have social previews. A public commit is not private just because its author signed in to write it.

Sources: [web routes](routes/web.php), [signup configuration](config/signup.php), [sign-in tests](tests/Feature/Controllers/SessionControllerTest.php).

### First-time setup

The first GitHub sign-in opens a short onboarding flow: review your mirrored profile, choose a color, follow suggested developers, and learn about the composer, keyboard shortcuts, and terminal commits. Skip setup at any step or finish it to continue to the app. Completed or skipped setup does not appear again. New users also start with Tom as a mutual follow; Tom is the platform's fictional welcome profile, not an implemented AI assistant.

Sources: [onboarding page](resources/js/pages/onboarding/show.tsx), [sign-in tests](tests/Feature/Controllers/SessionControllerTest.php).

### Profiles and GitHub sync

Every developer has a profile at `/@{username}`, with their GitHub identity, bio and other public profile information, commit/following/follower counts, and an activity graph. The Commits tab lists top-level commits; Replies lists that developer's replies with parent context. Follow and follower counts open the corresponding people lists. The profile links to GitHub and, when available, GitHub Sponsors. Share uses the device's share sheet when supported, or copies the profile link.

Change your name, avatar, bio, company, location, or website on GitHub, not on /dev/null. Settings → Profile offers **Edit on GitHub** and **Sync from GitHub**. Profile details also refresh on sign-in. Manual sync is limited to once a minute. A GitHub username rename is picked up on sign-in; accounts are not merged with another user's account.

Sources: [profile page](resources/js/pages/user/show.tsx), [profile settings](resources/js/pages/user-profile/edit.tsx), [profile sharing](resources/js/components/profile-share-button.tsx), [identity sync](app/Actions/UpdateOrCreateUser.php).

### Following developers and suggestions

Follow or unfollow from a developer's profile or a follow control elsewhere in the app. Following puts their commits in your Following log. You cannot follow yourself or follow across a block. GitHub follows are imported once: connections to developers already on /dev/null are added in both directions, with up to 1,000 GitHub connections checked each way. This is not continuous synchronization, so unfollowing someone on /dev/null sticks.

**Who to follow** suggests developers in the side column or within the log on smaller screens. Show or hide these suggestions in Settings → Appearance. You can also follow back from the notifications list.

Sources: [follow import](app/Actions/CreateUserGitHubFollows.php), [appearance settings](resources/js/pages/appearance/update.tsx), [notifications page](resources/js/pages/notification/index.tsx).

### Following, Latest, and Trending logs

Logs are the app's timelines. **Following** contains your commits and those of developers you follow, newest first. **Latest** contains the newest public commits across the app. **Trending** ranks public commits by activity rather than strictly by posting time. Logs list top-level commits, including quotes, rather than every reply, and load more as you scroll. New-commit controls let you bring in newer entries without automatically moving what you are reading. Muted and blocked people are filtered for signed-in viewers.

Sources: [log types](app/Enums/Log.php), [log queries](app/Queries/LogQueries), [log page](resources/js/pages/log/show.tsx).

### Searching developers and commits

Open search with **⌘/Ctrl+K** or **/**. Developer search finds people by name or GitHub username. Commit search, also available at `/search`, finds posts by meaning rather than requiring an exact phrase: describe the topic you want to read about. Commit queries must contain at least three characters and at most 100. Results respect visibility, mutes, and blocks; semantic matching does not guarantee that every relevant commit will be returned.

The public search searches developers and commits, not this knowledge-base file. Feature documentation is available to the internal knowledge-base retriever once indexed; there is no public knowledge-base search endpoint yet.

Sources: [search page](resources/js/pages/search/index.tsx), [commit search query](app/Queries/CommitSearchQuery.php), [search validation](app/Http/Requests/ListCommitSearchRequest.php), [knowledge-base query](app/Queries/KnowledgeBaseSearchQuery.php).

### Writing commits

A **commit** is a post on /dev/null, not a Git commit in a repository. Open the composer or press **c**, write your update, and choose **Commit** or press **⌘/Ctrl+Enter**. Text-only commits accept 3–4,000 characters. An image attachment makes the text optional, but the 4,000-character maximum still applies. Long posts offer **Show more** in lists. Commits can be created and deleted, but not edited after publication.

Commits, replies, and terminal submissions share a limit of 10 submissions per minute per user. Posts go through moderation, so a newly submitted commit may be pending rather than public immediately.

Sources: [composer](resources/js/components/commit-composer.tsx), [commit model](app/Models/Commit.php), [commit validation](app/Http/Requests/CreateCommitRequest.php), [submission throttle](app/Http/Middleware/ThrottleCommits.php).

### Code, emoji, and mentions

Commit and reply text supports inline code in backticks and fenced code blocks with syntax highlighting and a copy control. Text outside code supports emoji shortcodes, clickable URLs, and `@username` mentions. This is developer-oriented text formatting, not a full rich-text editor.

Typing **@** in a composer opens developer suggestions. Use ↑/↓ to move, Enter or Tab to select, and Esc to dismiss. A bare @ suggests people you follow; typing more searches names and usernames. Mentions link to profiles and can notify the mentioned developer after approval. Email addresses and scoped packages such as `@inertiajs/react` are not treated as mentions.

Sources: [body renderer](resources/js/components/commit-body.tsx), [code blocks](resources/js/components/code-block.tsx), [mention suggestions](app/Http/Controllers/MentionSuggestionController.php), [mention parsing](app/Services/Mentions.php).

### Draft previews and secret warnings

Use the composer's eye button or **⌘/Ctrl+Shift+P** to preview a commit or reply before submitting it. Preview shows the rendered code, emoji, mentions, links, and images, and identifies the link selected for a card. Esc returns from preview to writing.

The composer checks text for recognizable secrets, such as credentials, and asks for confirmation before posting a flagged draft. Draft text containing detected secrets is not persisted in the local stash. Treat this as a warning, not a guarantee that all secrets will be detected: review text and images yourself before publishing. Public GitHub issue drafts require detected secrets to be removed rather than offering a publish-anyway option.

Sources: [composer](resources/js/components/commit-composer.tsx), [secret warning](resources/js/hooks/use-secret-warning.ts), [sensitive-text handling](resources/js/lib/sensitive-text.ts), [issue commands](resources/js/components/issue-command.tsx).

### Stashed drafts

The app stashes unfinished commit and reply text, attached images, and image descriptions locally in your browser so you can return to them. Drafts belong to the signed-in user on that browser; they are not cloud drafts and do not synchronize between devices. Detected-secret text is excluded from persistent storage. Publishing or dropping a draft clears that stash. Browser storage restrictions or clearing site data can remove it; do not use the stash as your only copy of important writing.

Sources: [stash hook](resources/js/hooks/use-stash.ts), [image storage](resources/js/lib/image-storage.ts).

### Images and descriptions

Attach up to **three JPEG, PNG, or WebP images** to a commit or reply. Each image can be at most **10 MB** and **6,000 pixels in either dimension**. Add an image description of up to **1,000 characters** for accessibility. Images can be posted without accompanying text. Image attachments and a poll cannot be combined in the same commit. Open an attached photo to view it in the photo viewer and move through the commit's images.

Sources: [image picker](resources/js/components/commit-image-picker.tsx), [image limits](app/Actions/CreateCommitImages.php), [attachment validation](app/Http/Requests/CreateCommitRequest.php), [photo controller](app/Http/Controllers/CommitPhotoController.php).

### Links, link cards, and embedded players

URLs and bare domains in commits and replies become clickable links. Long URLs are shortened visually; hover reveals the full destination. Code keeps its literal text. The first link normally gets a preview card with available title, description, and image. In the composer, choose another link from the body or choose no card. Missing site metadata may leave a link without a rich preview.

Supported links can also show click-to-play embedded players: YouTube, Vimeo, Loom, Twitch, Spotify, SoundCloud, Apple Music, Suno, CodePen, and asciinema. Embeds depend on server configuration and the linked URL being supported. Third-party player content loads only after you press play; those providers have their own privacy policies. This is embedding a link, not uploading a video or audio file to /dev/null.

Sources: [link rendering](resources/js/components/linkified-text.tsx), [link-card selection](app/Http/Requests/CreateCommitRequest.php), [embed configuration](config/embeds.php), [embedded player](resources/js/components/link-embed.tsx).

### Replies and threads

Reply to a visible commit to continue its thread. Replies use the same text limits, image support, moderation, and submission rate limit as commits. Threads expand inline, and opening a commit's own page shows its ancestors and replies. Profiles have a separate Replies tab for authored replies. A reply cannot also be a quote or contain a poll.

Deleting a parent does not automatically delete other people's replies: an authorless `[deleted]` placeholder can keep the thread intact. You cannot add a new reply to a deleted commit. Approved replies notify the direct parent's author, except when replying to yourself.

Sources: [reply validation](app/Http/Requests/CreateCommitReplyRequest.php), [commit page](app/Http/Controllers/CommitController.php), [reply notifications](app/Actions/CreateReplyNotifications.php), [commit deletion](app/Actions/DeleteCommit.php).

### Quoting commits

Quote a visible commit to publish a new top-level commit with your own commentary and an embedded card pointing to the original. Quotes follow normal commit limits and moderation. Quoting is different from replying: the quote appears in logs as its own post. The original author is notified after approval, except for self-quotes.

The embedded original only shows content the viewer may see. Deleted originals appear as `[deleted]`; otherwise-inaccessible originals appear as `[unavailable]` without exposing their author or body. There is no comment-free repost action.

Sources: [quote validation](app/Http/Requests/CreateCommitRequest.php), [quoted commit card](resources/js/components/quoted-commit.tsx), [quote notifications](app/Actions/CreateQuoteNotifications.php).

### Polls and voting

Add a poll to a top-level commit with **two to four distinct options**, each up to **50 characters**, and a duration of **one hour, one day, three days, or seven days**. Polls cannot accompany images or be added to replies. Voting opens when the commit is approved, so time spent pending does not consume the voting period.

Signed-in readers can choose one option and vote once while an approved, visible poll is open. The author cannot vote in their own poll. Results are visible to the author and people who have voted, and to everyone once the poll closes. Other people's individual votes are not revealed.

Sources: [poll editor](resources/js/components/poll-editor.tsx), [poll results](app/Models/Poll.php), [voting rules](app/Rules/OpenPoll.php), [approval and poll opening](app/Actions/UpdateCommitStatuses.php).

### Scheduled commits

Use the composer's scheduling control to submit a top-level commit later. Pick a future time within **30 days**; you can have at most **10 outstanding scheduled commits**. Scheduled posts can carry images or a poll under the normal attachment rules, but replies cannot be scheduled. The scheduled-commits page is private to you and lets you inspect or cancel queued posts.

The scheduler checks due posts every minute. Scheduling submits the post for the normal publishing and moderation flow; it is not a promise that an approved public post will appear at the exact second selected. To change a queued post, cancel it and schedule a replacement.

Sources: [schedule validation](app/Http/Requests/CreateScheduledCommitRequest.php), [outstanding limit](app/Rules/ScheduledCommitLimit.php), [scheduled-commit controller](app/Http/Controllers/ScheduledCommitController.php), [scheduler](routes/console.php).

### Deleting commits and undo

Delete your own commit or reply from its controls. An **Undo** action is available for **30 seconds**, restoring the same commit without sending its notifications again. Deletion removes the post from normal lists, but visible replies can remain under an authorless `[deleted]` placeholder. Deleting a post is different from permanently deleting your account and all its top-level threads.

Sources: [commit deletion](app/Actions/DeleteCommit.php), [restoration validation](app/Http/Requests/CreateCommitRestorationRequest.php), [commit model](app/Models/Commit.php).

### Stars

Star someone else's visible commit or reply to show appreciation, or unstar to remove that star. You cannot star your own commits. A star contributes to the displayed count and can notify the author; unstar removes that notification. Starring the same commit repeatedly does not add multiple stars. Stars are an engagement action, not a private reading list; use Saved for bookmarks.

Sources: [star authorization](app/Http/Requests/CreateCommitStarRequest.php), [star notifications](app/Actions/CreateStarNotification.php), [unstar notifications](app/Actions/DeleteStarNotification.php).

### Saved commits

Save a visible commit or reply with the bookmark control and find it later in **Saved** at `/saved`. Saved commits are private to your account and listed newest saved first. Saving does not notify the author. Unsave to remove an item from the list. A saved reference does not preserve access to content that has since been deleted or become unavailable to you.

Sources: [saved page](resources/js/pages/bookmark/index.tsx), [bookmark controller](app/Http/Controllers/BookmarkController.php), [bookmark query](app/Queries/SavedCommitsQuery.php).

### Pinning a commit

Pin one of your own top-level commits to feature it at the top of your profile's Commits tab. Pinning another replaces the previous pin; unpin to return to the normal list. Replies cannot be pinned, and pinning does not override a commit's visibility or moderation state.

Sources: [pin authorization](app/Http/Requests/UpdateCommitPinRequest.php), [pin action](app/Actions/UpdateCommitPin.php), [profile page](resources/js/pages/user/show.tsx).

### Pastéis mode and awards

Enable **Pastéis mode** in Settings → Profile to give pastry awards to other people's commits. You get **10 pastéis per day**, with the allowance resetting at **00:00 UTC**, and each click gives one. You cannot award your own commit. Unlike stars, repeated clicks can give more than one pastel to the same commit, using your daily allowance.

Turning Pastéis mode off hides its controls and counters; people can still give you pastéis. The pastel emoji and mode commemorate Fernando Andrade bringing Portuguese custard pastries to the livestream team, rather than representing a payment or purchasable currency.

Sources: [Pastéis setting](resources/js/components/pasteis-mode-setting.tsx), [award action](app/Actions/CreateCommitPastel.php), [award authorization](app/Http/Requests/CreateCommitPastelRequest.php), [pastry lore](#pastéis-de-nata).

### Notifications

Open **Notifications** to read in-app activity: follows, replies, quotes, mentions, stars, and moderation messages. The navigation shows unread activity. Related activity is grouped, with expandable details, and filters let you choose categories or all/none. Opening the inbox marks relevant notifications read. You can follow back or unfollow from follower notifications.

Pending replies, quotes, and mentions wait for approval before notifying others. Self-actions do not notify you; mentioning the parent or quoted author does not duplicate their reply or quote notification. A new follower notifies only once even if they unfollow and follow again. Deleted or unavailable sources, mutes, and blocks affect which notifications remain visible. Rejected commits can produce a moderation message with a reason, without revealing a moderator's identity.

Sources: [notifications page](resources/js/pages/notification/index.tsx), [notification filtering](app/Queries/NotificationBuilder.php), [grouping](app/Queries/NotificationGroupsQuery.php), [moderation notifications](app/Actions/UpdateCommitStatuses.php).

### Remotes: direct messages

**Remotes** is /dev/null's private messaging inbox at `/remotes`. Open it from navigation or use a developer's **Remote** control. Remotes can be disabled in Settings → Profile; disabling keeps existing conversations but stops new messaging. By default, you can contact someone who follows you, provided both accounts have Remotes enabled and neither has blocked the other. Administrators have a contact-policy exception.

Messages support up to **2,000 characters**, replies to messages, link cards, and up to three images with the same file limits and descriptions as commit attachments. An image makes message text optional. Typing indicators show recent activity. Read receipts, called **pull receipts**, are shared only when both relevant people enable them. Stashing a conversation archives it for you without deleting it; unstash to bring it back.

Some deployments enable message requests and an **everyone** contact option. With requests enabled, a first message from someone outside the accepted contact policy waits in **Fetched**. The recipient can **merge** the request to accept or **drop** it privately. The sender cannot keep sending while waiting, and the recipient must merge before replying. These options are disabled by default in repository configuration; do not assume every installation exposes them.

Sources: [Remotes setting](resources/js/components/remotes-setting.tsx), [contact policy](app/Models/User.php), [message validation](app/Http/Requests/CreateConversationMessageRequest.php), [remote preferences](resources/js/components/chat/remote-settings-menu.tsx), [configuration](config/remotes.php).

### Orgs: group chats

An **Org** is a private group chat within Remotes, not a synchronized GitHub organization. Create one with other eligible developers; a group can contain up to **15 people including you**. Members need Remotes enabled, and invitation eligibility respects follows and blocks. A group's optional name can be up to **60 characters**.

The creator starts as a **Maintainer**, and invited people start as **Contributors**. Maintainers can rename the group, add or remove members, and change roles. Contributors can read and send messages. When adding members, a maintainer can share existing history or only new messages. Members can leave or stash the group for themselves. At least one maintainer remains while the group has members; if the last maintainer leaves, the longest-standing remaining member takes over.

Group messages support the same text, images, replies, link cards, typing indicators, and receipt preferences as direct messages. Membership and history access determine what a person can read.

Sources: [group creation](app/Http/Requests/CreateOrgRequest.php), [invitation eligibility](app/Queries/RemoteCandidatesQuery.php), [history sharing](app/Actions/CreateOrgMembers.php), [role changes](app/Http/Requests/UpdateOrgMemberRequest.php), [leaving a group](app/Actions/DeleteOrgMember.php).

### Muting and blocking

**Mute** someone to remove their commits and replies from your logs, threads, search, and suggestions and stop their notifications. Muting is silent, leaves follows intact, and still lets you visit their profile.

**Block** someone to stop messaging and interactions such as following, replies, and stars between the two accounts, and hide each other's commits while signed in. Blocking removes follows in both directions. Unblocking does not restore those follows. Manage and reverse either choice in Settings → Safety. Blocking is an account-level interaction control, not a way to make public posts inaccessible to signed-out visitors.

Sources: [safety settings](resources/js/pages/safety/edit.tsx), [mute action](app/Actions/CreateUserMute.php), [block action](app/Actions/CreateUserBlock.php), [public routes](routes/web.php).

### Reporting commits

Report another person's visible, approved commit or reply privately from its controls. Choose a reason and optionally add up to **500 characters** of detail. You can report a particular commit once. Reporting does not immediately hide or reject the content. Moderators review reports without the reporter's identity and can dismiss the report or reject the commit. This is a content-safety report, not a software bug report; use `/bug` for app defects.

Sources: [report control](resources/js/components/commit-report-button.tsx), [report validation](app/Http/Requests/CreateCommitReportRequest.php), [reported-commit controller](app/Http/Controllers/ReportedCommitController.php).

### Appearance, color, and timezone

Settings → Appearance offers **Light**, **Dark**, or **System** theme and a show/hide preference for Who to follow suggestions. Settings → Profile lets you choose one of six lane colors or keep the automatic color used around your identity and activity graph.

Profile settings also let you share a timezone. Timestamps can show the author's local time as well as the viewer's when a timezone is shared. Leaving it unset uses the browser's timezone for your display without publishing your timezone. These /dev/null preferences are separate from your GitHub-managed profile information.

Sources: [appearance settings](resources/js/pages/appearance/update.tsx), [lane picker](resources/js/components/lane-picker.tsx), [timezone picker](resources/js/components/timezone-picker.tsx), [timestamp tooltip](resources/js/components/timestamp-tooltip.tsx).

### Activity graphs and README embeds

Profiles display a year of commit activity with current and longest streaks. Settings → Profile includes a live preview and copyable Markdown for embedding your /dev/null activity graph in a GitHub profile README. The graph is public at `/@{username}/graph.svg`, uses your lane color, and supports `?theme=light`, `dark`, or `auto` (light by default). The SVG is cached for an hour, so a new commit may not appear in an external embed immediately.

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

Settings → Terminal shows how to post using the `/commit` shell script. It signs the submission locally with an SSH key published on your GitHub account, and /dev/null verifies that signature. No browser login or API token is required for that submission; your private key is not uploaded. The script can create commits, reply with `--reply` using a commit reference, and attach images with `--image`.

Terminal posts follow the same content limits, moderation, and 10-per-minute submission limit as browser posts. Their first link supplies the default link card. Review a downloaded script before running it, and never paste a private key into a commit.

Sources: [terminal instructions](resources/js/pages/terminal/show.tsx), [CLI routes](routes/api.php), [signature verification](app/Http/Middleware/AuthenticateSshSignature.php).

### GitHub Actions integration

Use `nunomaduro/dev-null.sh/.github/actions/commit@production` to publish updates from a GitHub workflow, for example when a release is published. Settings → Terminal includes a workflow example. The action accepts a commit body, an SSH private key supplied through a GitHub Actions secret, an optional `reply-to` reference, and optional image paths.

Generate a dedicated CI key, add its public half to GitHub as a signing key, and store the private half only in the repository's encrypted secrets. The action supports Ed25519 or RSA keys without a passphrase. It prints the created commit's link and whether it is pending review; a refused submission fails the job. The usual moderation and submission limits still apply.

Sources: [workflow instructions](resources/js/pages/terminal/show.tsx), [GitHub action](.github/actions/commit/action.yml).

### Public read API and Atom feeds

The read-only JSON API at `/api/v1` requires no authentication or token. Read a public profile at `/api/v1/users/{username}`, that user's commits at `/api/v1/users/{username}/commits`, or one commit at `/api/v1/commits/{commit}`. Add `?replies=1` to the user-commits endpoint to read their replies instead of top-level commits. Lists use cursor pagination, 20 items per page. Only public approved, non-deleted content is returned, not private profile data such as email. The API is limited to **60 requests per minute per IP** and cannot create posts, follow, or star; terminal posting is a separate signed interface.

Subscribe in a feed reader to `/@{username}/feed.atom` for a profile's latest **50 approved top-level commits**, including image enclosures. Feeds are public and can be cached for five minutes.

Sources: [API routes](routes/api.php), [API controllers](app/Http/Controllers/Api), [feed generation](app/Actions/CreateUserFeed.php), [rate limits](app/Providers/AppServiceProvider.php).

### Bug reports and feature requests

Start a fresh commit draft with **`/bug `** or **`/feature `** to switch it into an issue draft. Submitting opens a **public GitHub issue** on `nunomaduro/dev-null.sh`, crediting you, rather than posting a normal commit. The composer offers command suggestions and a way to cancel back to a commit. Issue drafts are text-only and accept 3–4,000 characters; remove detected secrets before submission.

Successful issue creation is limited to **five issues per hour per user**. If GitHub or the connection fails, the draft stays available. Check upstream before retrying an uncertain submission to avoid duplicates. Use a commit's Report control instead when reporting abusive content.

Sources: [issue command UI](resources/js/components/issue-command.tsx), [issue validation](app/Http/Requests/CreateIssueRequest.php), [issue controller](app/Http/Controllers/IssueController.php).

### Exporting your data

Choose **Download JSON** in Settings → Profile to export your account data. The export includes profile details, your commits and replies (including pending, rejected, and deleted entries still stored), polls and your votes, stars, bookmarks, follows, mutes, blocks, messages you sent, notifications, SSH key fingerprints, and active-session metadata. Other people's replies and received messages are not included as your authored data. Image references are URLs, not an archive of the image files.

The export does not expose session IDs, authentication tokens, private SSH keys, or other people's poll choices. Keep the downloaded JSON private because it includes personal account information. Exports are limited to **three per hour**.

Sources: [export settings](resources/js/pages/user-profile/edit.tsx), [export contents](app/Actions/CreateExport.php), [export download](app/Http/Controllers/ExportController.php), [rate limits](app/Providers/AppServiceProvider.php).

### Deleting your account

Settings → Profile → **Delete account** opens a confirmation for permanent deletion of your /dev/null account. It does not delete your GitHub account. Your top-level commits and every reply under those threads are erased. Your replies are also removed, except that replies from other people beneath them survive under authorless `[deleted]` placeholders. Account deletion is not covered by the 30-second commit undo.

You can sign in with GitHub again later, but start from a new account rather than restoring the deleted one. Export your data first if you want a copy. Deleting from /dev/null cannot retract copies of public content held elsewhere.

Sources: [account deletion confirmation](resources/js/components/delete-user.tsx), [account deletion](app/Actions/DeleteUser.php), [privacy policy](resources/js/pages/privacy/show.tsx).

### Moderation

Commits and replies undergo automated moderation and may be pending, approved, or rejected. Pending and rejected content is not publicly visible; its author can still see its status. Approval makes content eligible for public lists and releases reply, quote, and mention notifications. Rejection can notify the author with a reason. Moderation applies to browser, scheduled, and terminal submissions alike.

Administrators have a moderation queue for reviewing and approving or rejecting commits, including bulk decisions, plus a reports queue for user-reported content. Moderators can dismiss reports or reject reported commits. These are restricted tools, not controls every account receives.

Sources: [moderation access](app/Http/Requests/ShowModerationRequest.php), [moderation controller](app/Http/Controllers/ModerationController.php), [status transitions](app/Actions/UpdateCommitStatuses.php), [reports controller](app/Http/Controllers/ReportedCommitController.php).

### Operations dashboard

Administrators can open `/operations` to inspect database and cache connectivity, queue backlogs, recent failed jobs, pending moderation, link-preview health, and recent signup/commit/reply volume. The GitHub API rate limit is shown when credentials are configured. The dashboard refreshes periodically and offers retry or forget controls for failed jobs. Non-admin users cannot access it.

Sources: [operations controller](app/Http/Controllers/OperationsController.php), [access check](app/Http/Requests/ShowOperationsRequest.php), [operations page](resources/js/pages/operations/show.tsx).

### About, changelog, and policies

The public **About** page describes the livestream project and its contributors. **Changelog** records shipped changes. **Terms** and **Privacy** explain acceptable use and data handling. Public posts may be indexed or copied outside /dev/null; content and search queries can be processed by automated or AI services. Read those policies before posting sensitive information or relying on deletion to remove every external copy.

Sources: [public page routes](routes/web.php), [About](resources/js/pages/about/show.tsx), [privacy policy](resources/js/pages/privacy/show.tsx), [terms](resources/js/pages/terms/show.tsx).

### Hello world replies

An approved commit whose entire text is **hello world**, including variants such as **Hello, World!**, can receive an automatic **“Hello, {name}.”** reply from **@world**. A pending commit waits for approval. The bot replies only once to that commit. It is a small greeting feature, separate from Tom and the planned knowledge-base assistant.

Sources: [greeting matching](app/Services/HelloWorld.php), [world reply](app/Actions/CreateWorldReply.php).

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
| Asset tooling | **Vite+ / Vite** builds frontend assets; **Bun** manages frontend dependencies and runs scripts. |
| PHP dependencies | **Composer** manages backend dependencies. |
| Testing and quality | **Pest**, **PHPStan / Larastan**, **TypeScript**, **Laravel Pint**, **Rector**, and frontend formatting and linting through **Vite+**. |
| Existing AI services | **Laravel AI SDK** generates and caches semantic search query embeddings through **OpenAI**. The existing OpenAI integration handles batch embeddings, commit relevance checks, and moderation. This is separate from Tom's planned DeepSeek role. |

The repository defaults to database-backed queues, cache, and sessions. File storage is configurable, with local and S3-compatible storage supported. These are repository defaults and supported options, not a claim about every production environment setting.

The knowledge base is split into Markdown sections by heading and stored with vector embeddings in PostgreSQL. An hourly scheduled job refreshes new or changed sections in production, so knowledge-base changes are picked up within an hour of being deployed. Unchanged sections reuse their embeddings.

An [internal semantic search query](README.md#internal-semantic-search) retrieves the nearest knowledge-base sections using Laravel AI SDK query embeddings through OpenAI and PostgreSQL cosine distance, following the same embedding approach as commit search. Results preserve source paths, heading context, and Markdown content for a future agent to use. This is retrieval infrastructure only: there is no public search endpoint, and Tom's DeepSeek bot remains planned.

Stack references: [PHP dependencies](composer.json), [frontend dependencies](package.json), [database configuration](config/database.php), [setup requirements](README.md#getting-started), [queue configuration](config/queue.php), [cache configuration](config/cache.php), [session configuration](config/session.php), [storage configuration](config/filesystems.php), and [existing AI integration](app/Services/OpenAI.php). Laravel Cloud hosting is confirmed by the project team.

## Lore

### Built on a livestream

/dev/null began with the challenge **“Building a Social Network in PHP in 48 Hours.”** Building the platform together on the livestream was the point of the project, not a broadcast added after it was built. Viewers could watch it take shape and contribute along the way.

The livestream team included:

- **Nuno Maduro** — [@nunomaduro](https://github.com/nunomaduro).
- **Brent Roose** — [@brendt](https://github.com/brendt).
- **Matthieu Napoli** — [@mnapoli](https://github.com/mnapoli).
- **Pauline Vos** — [@paulinevos](https://github.com/paulinevos).

They built live from the JetBrains office, with community contributions becoming part of the project.

Watch the livestreams:

- [Day one — Building a Social Network in PHP in 48 Hours](https://www.youtube.com/watch?v=6zCNQa3hNm0).
- [Day two — Building a Social Network in PHP in 48 Hours](https://www.youtube.com/watch?v=nfmVdXZfzUE).

Sources: [Laravel News's livestream announcement](https://laravel-news.com/social-network-php-48-hours) names Nuno, Brent, and Matthieu and links both days; Pauline confirms her participation as part of the livestream team. The repository's [livestream component](resources/js/components/live-stream.tsx) links day two.

### Tom

Tom is a fictional profile inspired by **MySpace Tom**. He is everyone's mutual at the beginning: the familiar face who is already there when you arrive.

Originally, Tom had no particular purpose beyond that nod to MySpace. The plan now is to give him one: **Tom will become a bot powered by DeepSeek, using this knowledge base to answer questions about /dev/null, its stack, and its lore.**

That bot role is planned, not a claim that the DeepSeek integration is already implemented. Tom is a fictional platform character, not the real MySpace Tom.

Source: the project team's account of Tom's origin and intended role.

### Pastéis de nata

During the livestream, **Fernando Andrade — [@fernandofreamunde](https://dev-null.sh/@fernandofreamunde)** — brought **pastéis de nata**, Portuguese custard pastries, for the team.

The **pastel emoji** and **Pastéis mode** are a tribute to Fernando and that delivery. A real-world act of feeding the team became part of the platform's shared lore.

The singular is **pastel de nata**; the plural is **pastéis de nata**. The mode is called **Pastéis mode**.

Sources:

- [Fernando's post arranging delivery to the crew](https://dev-null.sh/@fernandofreamunde/commits/01a144fe-ed29-7356-af93-03b9724e8af6).
- [Matthieu's reply thanking him for the pastéis de nata](https://dev-null.sh/@mnapoli/commits/01a1453f-ac59-7077-b99e-98d72547ae8f).
- [Pastéis mode contribution](https://github.com/nunomaduro/dev-null.sh/pull/286).
- The project team's account confirms that the emoji and mode are a tribute to Fernando.
