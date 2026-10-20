# GitHub workflows guidelines

Workflows in this repository only ever read the code and report whether it passes. They never write to the repository, GitHub, or production. A workflow that can write is a workflow an attacker can use, through a dependency, a fork, or a pull request.

## Read-only, always

- Every workflow declares exactly this at the top level, and nothing else:

@boostsnippet('Workflow permissions', 'yaml')
permissions:
  contents: read
@endboostsnippet

- No job or step raises permissions. Never `contents: write`, `pull-requests: write`, `issues: write`, `packages: write`, `actions: write`, `deployments: write`, `id-token: write`, `security-events: write`, or `permissions: write-all`. If a feature needs one of these, it does not run in GitHub Actions.
- Workflows never push commits or tags, create releases, comment on pull requests or issues, add labels, open issues, publish packages, call the GitHub API with a token, or deploy. Deploys happen from the hosting platform, never from a workflow.
- The only output a workflow may produce is a build artifact uploaded with `actions/upload-artifact`, such as the `pest-tia-baseline` artifact, and the job status itself.

## No secrets

- Workflows and composite actions in `.github/` never use `secrets.*`, never pass `GITHUB_TOKEN` to a step, an `env:`, or an action input, and never contain deploy keys, SSH keys, API keys, or credentials of any kind. The database password in `tests.yml` belongs to a throwaway container that only exists for the job, which is the only acceptable kind of "secret".
- Repository secrets stay empty. There is nothing for a workflow to deploy to, publish to, or sign with.
- `.github/actions/commit` is published for other people's repositories and takes an SSH key from their secrets. Our own workflows never use it.

## Safe triggers and inputs

- Only `push`, `pull_request`, `schedule`, and `workflow_dispatch` trigger workflows. Never `pull_request_target`, `workflow_run`, `issue_comment`, `issues`, `discussion`, or `repository_dispatch`: those run with a privileged token or on input from strangers.
- Never interpolate `$@{{ github.event.* }}`, `$@{{ github.head_ref }}`, or any other user-controlled value directly into `run:`. Pass it through `env:` and read it as a shell variable, as `lint-pr-title.yml` does with `PR_TITLE`.
- Pull requests from forks run with the read-only token and without secrets, and the repository requires approval before they run at all. Keep those repository settings; a workflow must stay safe even if they were turned off.
- Jobs run on GitHub-hosted `ubuntu-latest` runners only, never self-hosted, and always with a `timeout-minutes`.

## Trusted actions only

- Third-party actions are pinned to a full commit SHA with the version in a comment, and Dependabot keeps them current. A tag can be moved to malicious code; a SHA cannot.
- Only actions from GitHub (`actions/*`) and from the vendor of the tool being installed (`shivammathur/setup-php`, `oven-sh/setup-bun`, `voidzero-dev/setup-vp`). No unknown marketplace actions, however convenient.
- `actions/checkout` runs with `persist-credentials: false`, so the token never lingers in `.git/config` for later steps to find.
- Dependencies are installed from lockfiles only (`composer install`, `bun install --frozen-lockfile`) and never with scripts the lockfile did not already run locally.

## Review

- Any change under `.github/` is reviewed for the four questions above before anything else: which permissions, which triggers, which secrets, which interpolations. If the answer to any of them changed, the change is rejected.
- A new workflow is only added when it reads the code and reports a result. If it needs to write anywhere, the answer is no.
