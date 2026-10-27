#!/usr/bin/env bash
#
# Lists open pull requests from low risk to critical, for a maintainer to run with their own gh sign-in.
# It never runs in a workflow, and it only reads: one GraphQL request per 50 open pull requests.
#
# Drafts, conflicts, failing CI and requested changes wait on their authors. The rest is split by
# what it touches, then each 👍 on a description (not the author's) lifts it one size step.

set -euo pipefail

gh api graphql --paginate --slurp -f query='
  query ($endCursor: String) {
    repository(owner: "dev-null-sh", name: "dev-null.sh") {
      pullRequests(states: OPEN, first: 50, after: $endCursor) {
        pageInfo { hasNextPage endCursor }
        nodes {
          number title createdAt isDraft additions deletions changedFiles mergeable reviewDecision
          author { login }
          files(first: 100) { nodes { path } }
          reactions(content: THUMBS_UP, first: 100) { nodes { user { login } } }
          commits(last: 1) { nodes { commit { checkSuites(last: 20) { nodes { id conclusion app { slug } workflowRun { workflow { name } } } } } } }
        }
      }
    }
  }' | jq -r '
  [.[].data.repository.pullRequests.nodes[]]
  | map(
      (.additions + .deletions) as $lines
      | [.files.nodes[].path] as $paths
      | .size = ([10, 50, 250, 1000] | map(select($lines >= .)) | length)
      | .votes = ([.reactions.nodes[].user.login // empty | select(endswith("[bot]") | not)] - [.author.login] | length)
      | ([.commits.nodes[].commit.checkSuites.nodes[]] | group_by(.workflowRun.workflow.name // .app.slug // .id) | map(last.conclusion)) as $suites
      | .approval = any($suites[]; . == "ACTION_REQUIRED")
      | .waiting = if .isDraft then "draft"
          elif .mergeable == "CONFLICTING" then "merge conflicts"
          elif any($suites[]; . == "FAILURE" or . == "TIMED_OUT" or . == "STARTUP_FAILURE") then "failing CI"
          elif .reviewDecision == "CHANGES_REQUESTED" then "changes requested"
          else null end
      | .tier = if $lines >= 1000 or .changedFiles > 100 or any($paths[]; test("^(database/migrations|routes|bootstrap|config|patches|\\.github|app/Http/(Middleware|Requests))/|^app/Services/(SshSignatures|SignedRequests)\\.php$|^(composer\\.json|package\\.json|\\.npmrc|bunfig\\.toml|vet\\.json)$|\\.lock$")) then 2
          elif $lines < 250 and all($paths[]; test("^(resources/(js|css)|tests)/|\\.md$")) then 0
          else 1 end
    )
  | (map(select(.waiting == null)) | sort_by(.tier, .size - .votes, .createdAt)) as $ready
  | (
      ["🟢 Low risk: only UI, docs or tests, under 250 lines", "🟡 Review: everything else", "🔴 Critical: data, routes, auth, config, CI or dependencies, or 1000+ lines or over 100 files"] as $titles
      | range(0; 3) as $tier
      | [$ready[] | select(.tier == $tier)]
      | "\n\($titles[$tier]) (\(length))",
        (.[] | "  #\(.number)  \(["XS", "S", "M", "L", "XL"][.size])  👍\(.votes)\(if .approval then "  needs CI approval" else "" end)  \(.title)")
    ),
    (map(select(.waiting != null)) | "\n⏸  Waiting on the author (\(length))", (group_by(.waiting)[] | "  \(.[0].waiting): \(map("#\(.number)") | join(" "))"))
'
