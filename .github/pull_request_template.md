<!-- Summary of changes made in this PR -->
## Description

A clear and concise summary of the changes introduced in this pull request.

Fixes #(issue) <!-- e.g. Fixes #42 -->

---

## Type of Change

- [ ] 🚀 New feature
- [ ] 🐛 Bug fix
- [ ] 💅 UI / Design adjustment
- [ ] ⚡ Performance optimization
- [ ] 🧹 Refactor / Code cleanliness
- [ ] 📝 Documentation / Knowledge base update
- [ ] 🔧 Infrastructure / CI / Dependencies

---

<!-- Does this change introduce breaking changes to existing functionality, API endpoints, or database structures? -->
## ⚠️ Breaking Changes

- [ ] Yes
- [ ] No

_If yes, describe what will break and the migration path for existing deployments or clients:_

---

<!-- If this change affects the frontend UI, please provide screenshots or screen recordings -->
## Screenshots / Media

| Before | After |
| :---: | :---: |
| _None_ | _None_ |

---

## Checklist

- [ ] My code follows the repository's style and conventions (`composer lint`).
- [ ] I have run static analysis and type checks (`composer test:types`).
- [ ] I maintained 100% type coverage (`composer test:type-coverage`).
- [ ] I maintained 100% test suite code coverage with Pest (`composer test:unit`).
- [ ] All CI checks pass locally (`composer test`).
- [ ] If changing database schema, migrations and rollback have been tested.
- [ ] If changing environment variables, `.env.example` has been updated.
- [ ] If changing `/api/v1` or mobile contracts, response schemas and signatures are accounted for.
- [ ] If changing `KNOWLEDGE_BASE.md`, embeddings have been verified (`php artisan knowledge-base:embed`).
