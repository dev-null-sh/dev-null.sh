<p align="center">
    <img src="public/og.png" width="600" alt="/dev/null">
    <p align="center">
        <a href="https://github.com/dev-null-sh/dev-null.sh/actions"><img alt="GitHub Workflow Status (production)" src="https://img.shields.io/github/actions/workflow/status/dev-null-sh/dev-null.sh/tests.yml?branch=production&label=Tests"></a>
        <a href="https://dev-null.sh"><img alt="Website" src="https://img.shields.io/badge/website-dev--null.sh-ff8a4c"></a>
        <a href="https://github.com/dev-null-sh/dev-null.sh/graphs/contributors"><img alt="Contributors" src="https://img.shields.io/github/contributors/dev-null-sh/dev-null.sh"></a>
        <a href="composer.json"><img alt="License" src="https://img.shields.io/badge/license-MIT-blue"></a>
    </p>
</p>

------

This is the source code of **[dev-null.sh](https://dev-null.sh)**, a social network for developers. Sign in with GitHub, follow developers, and share commits.

/dev/null was started live on stream by [Nuno Maduro](https://github.com/nunomaduro), [Brent Roose](https://github.com/brendt), [Matthieu Napoli](https://github.com/mnapoli), and [Pauline Vos](https://github.com/paulinevos), and is built by its community. Anyone can open a pull request.

- Join at **[dev-null.sh »](https://dev-null.sh)**
- Read the story at **[dev-null.sh/about »](https://dev-null.sh/about)**
- Learn how it works in the **[knowledge base »](KNOWLEDGE_BASE.md)**

## Contributing

Requires PHP 8.5+, Composer, Bun, and PostgreSQL 17+ with `pgvector` and `pg_trgm`. Create a `devnull` database, then:

```bash
git clone https://github.com/dev-null-sh/dev-null.sh.git
cd dev-null.sh
composer setup
composer dev
```

Run `composer test` before opening a pull request.

### Testing broadcast remotes locally

Broadcasts are subscriber-only message channels: an owner and selected publishers can post, while subscribers can read and react. Channels can accept anyone or require an invitation. The latest 30 public channels appear on the owner's profile; **Browse broadcasts** opens the searchable directory prefilled with their handle. The directory inside Remotes provides further pages. A subscription never reveals messages from before the user's first join; leaving removes access, and rejoining preserves that original cutoff.

After migrating, load the optional local-only demo (safe to rerun):

```bash
php artisan db:seed --class=BroadcastDemoSeeder
```

With `APP_ENV=local`, sign in with any of these usernames, using separate browser profiles for simultaneous sessions:

| Username | Demo role |
|---|---|
| `broadcast-owner` | Owns an open channel and an invite-only channel; can invite and manage publishers |
| `broadcast-publisher` | Can post in the open channel |
| `broadcast-reader` | Already subscribed; can read but not post |
| `broadcast-new` | Not subscribed; can discover and join the open channel |
| `broadcast-invitee` | Has an invitation to the invite-only channel |
| `broadcast-returning` | Left the open channel; rejoining restores messages since their first join |

To moderate subscribers as `broadcast-owner`, open a channel and click its name. **Members and bans** lets you search members, kick or ban them, and unban banned accounts. Kicking permits rejoining an open channel; banning prevents it until unbanned. Both remove publishing privileges and preserve the original history cutoff. Unbanning does not automatically resubscribe anyone.

Open Remotes after signing in. The open channel includes one message from before the subscribers joined (visible only to its owner) and messages after they joined. To test invite-only delivery, accept the invitation as `broadcast-invitee`, then publish a new message as the owner. Accounts need Remotes enabled in Settings → Profile; the demo enables it for these accounts. No GitHub credentials or AI keys are needed for this demo.

## License

/dev/null is open-sourced software licensed under the **[MIT license](composer.json)**.
