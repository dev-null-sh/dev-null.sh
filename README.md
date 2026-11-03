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

### Live updates

Pages hear about changes through any server speaking the Pusher protocol, such as Laravel Cloud's WebSockets or a self-hosted Reverb. By default `BROADCAST_CONNECTION=log` sends nothing anywhere and every page polls as before. To try them locally, run a Pusher-protocol server on your machine, set `BROADCAST_CONNECTION=reverb` with the `REVERB_*` variables pointing at it, set the matching `VITE_REVERB_*` variables, and rebuild the assets: the `VITE_` ones are read at build time. Tests always use the `null` broadcaster.

In production, the server needs `BROADCAST_CONNECTION=reverb` and the `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT` and `REVERB_SCHEME` of the WebSocket server, and the build needs `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT` and `VITE_REVERB_SCHEME`. A queue worker must be running, since updates for more than a hundred people at once go through the queue.

Commits are attributed to people only: never add Amp, Claude Code, or any other AI tool as a co-author, and keep their names out of commit attribution (author, committer, and trailers).

### Amp orbs

Amp runs `.agents/setup` to prepare a reusable snapshot with PHP 8.5, Composer, Node 26, the Bun version from `package.json`, locked dependencies, generated routes, and built assets. Setup also prepares isolated PostgreSQL 18 development and test databases with `vector` and `pg_trgm`, including extensions inherited by parallel test databases. It is safe to rerun and preserves an existing `.env` and application key.

Run `amp orb services ensure` to start the supervised database and app and print the app's portal URL. The app uses the existing local sign-in flow (any handle, no GitHub credentials required). Frontend assets are prebuilt; rerun `bun run build` after frontend edits. No resume hook is needed: Amp supervises the services, and dependency installation belongs only in setup.

The database is loopback-only and uses trust authentication and relaxed durability for disposable development data. Do not use this configuration for production.

## License

/dev/null is open-sourced software licensed under the **[MIT license](composer.json)**.
