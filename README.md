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

## License

/dev/null is open-sourced software licensed under the **[MIT license](composer.json)**.
