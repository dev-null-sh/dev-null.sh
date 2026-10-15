# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Any developer with a GitHub account. They come to post short text updates, follow other developers, and read a timeline of what those people are saying. Every user is identified by their GitHub account; there is no other way in.

## Product Purpose

devnull is a social network for developers: sign in with GitHub, get a profile mirrored from GitHub, follow people, post short text, and read a timeline. Success is developers choosing to post and read here because the people on it are verifiably developers.

## Positioning

Identity is GitHub. Every account is a real GitHub user, and the profile (avatar, name, username, bio, company, location, website) is mirrored from GitHub on every login and cannot be edited on devnull. A general-purpose network cannot truthfully claim that every account is a developer with a public GitHub identity.

## Operating Context

- Sign-in is GitHub OAuth only. Profile changes happen on GitHub ("Edit on GitHub"), then sync at next login.
- Everything except the landing page requires login.
- Profile links (`/@{username}`) and links to approved commits (`/@{username}/commits/{id}`) unfurl with a link preview (the author and their bio or counts, or the commit body and reply count) for guests and crawlers; a guest opening one sees the landing page, and the profile or commit itself stays behind login. Pending and rejected commits are a 404 for guests.
- Two logs: **Following** (my commits, people I follow, and commits tagged with tags I follow) and **Everyone** (all commits), newest first, infinite scroll.
- Tags live at `/tags/{name}`: every visible commit and reply tagged with it, newest first, infinite scroll, with commit and follower counts and a Follow button.
- Profiles live at `/@{username}` with commit, following, and follower counts, a commit activity graph with longest and current streaks, and Commits and Replies tabs. Commits is the default and lists the user's top-level commits; `?tab=replies` lists their authored replies with visible parent context. Both feeds use infinite scroll.
- Settings: read-only profile, color (pick one of the six graph lanes or keep the automatic one; the only devnull-owned profile setting), appearance (light/dark/system), delete account.

## Capabilities and Constraints

- Commits (posts): plain text, 3–280 characters; create and delete only (no editing). Rate limited to 10 commits per minute per user.
- Follow/unfollow, idempotent; self-follow rejected.
- Replies: any visible commit can be replied to; a reply is itself a commit (same 280 limit, moderation, and rate limit). Threads expand inline under a commit; logs and the profile's Commits tab list top-level commits only, while the profile's Replies tab lists that user's replies. Every commit has its own page with its ancestors above it and its replies expanded; it is behind login like everything else. Deleting a commit removes it from logs, profiles, counts, and its own page, but never takes other people's replies with it: while it still has visible replies it stays in its thread as a `[deleted]` tombstone with no author or body, and it can no longer be replied to. Deleting an account erases that user's commits for good, along with the replies under them.
- Stars: anyone who can see a commit can star and unstar it. Starring is idempotent. The count sits on the commit. A star is the engagement action, named the way GitHub names it, and it is not a git ref.
- Tags: `#laravel` in a commit or reply tags it. A tag starts with a letter, then up to 49 ASCII letters, digits, `-` or `_`, and is case-insensitive (stored lowercase), so `#123` stays an issue number and URL fragments, `C#` and `a#b` are never tags. Tags in a body link to their page. Anyone can follow a tag, idempotently; the Following log then includes top-level commits tagged with it. Followed tags are part of the personal data export.
- Notifications: in-app only, for a new follower and for an approved reply to one of your commits (only the direct parent's author; never for replying to yourself). A notification points to the follow or reply, it never copies it: it disappears when the reply, its parent, or either user is deleted, and is hidden while the follow is undone or the reply is rejected. A follower notifies once, ever: unfollowing and following again never pings twice. Pending replies notify nobody until approved. No notification for moderation outcomes. Unread count in the nav; a page is marked as read once shown.
- People search: find developers by name or GitHub username (⌘K). Commits are not searchable.
- Deliberately excluded for now: likes/reactions, reposts, images/media, mentions, searching commits, DMs, other OAuth providers, public API, moderation tools, blocking/muting, header/banner images. Future work must not design for these as if they exist.
- Open: handling GitHub username renames that collide with a stale username.

## Brand Commitments

- Name: **devnull** (lowercase), a nod to `/dev/null`.
- Voice: dry, terminal-literate humor. Understated wit; copy may wink at Unix and `/dev/null`, but stays clear and never obscures what an action does.
- Current tagline in use: "A social network for developers."
- Vocabulary: a post is a **commit** and a timeline is a **log**, in the UI and in code (`Commit` model, `LogController`, `/log/{log}`). The git metaphor stops at those two nouns: no branches, forks, pushes, diffs, or PRs. **Star** is the engagement verb, not a git ref. A `#hashtag` is a **tag**, the plain word for it; it is not a git ref either, and git jokes about it stay in secondary copy such as empty states.

## Evidence on Hand

- No users, testimonials, metrics, or press yet. Do not fabricate user counts, quotes, activity, or endorsements.
- Seeded/factory data exists for development only and is not real evidence.

## Product Principles

1. **GitHub is the source of truth for identity.** Never add devnull-side profile editing or alternate sign-in.
2. **Keep it stupidly simple.** Text posts, follows, stars, timelines. Every addition must justify itself against the "not now" list.
3. **Words over mechanics.** The post body is the content; chrome and metadata stay subordinate to it.
4. **Wit in the copy, clarity in the action.** Humor lives in tone, never in labels a user needs to understand.
