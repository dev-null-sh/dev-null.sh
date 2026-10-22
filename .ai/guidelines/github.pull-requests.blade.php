# Pull request guidelines

Apply these rules when an agent prepares, opens, or updates a pull request.

## Choose the evidence

- Use `.github/PULL_REQUEST_TEMPLATE/without-media.md` for text-only PRs. Keep inspection captures out of the PR.
- Use `.github/PULL_REQUEST_TEMPLATE/with-media.md` for PRs with screenshots or recordings.
- Select either template explicitly: in GitHub's compare view, use `template=without-media.md` or `template=with-media.md`. When using another client, use the chosen template as the PR body. There is no default template.
- Honor the requested format. Text-only PRs still need applicable visual and device verification. Explain when visual evidence or device testing is inapplicable.
- Templates collect evidence; these guidelines define the workflow. Keep shared instructions independent of a particular workstation, assistant, or tool.

## Prepare and implement

1. Confirm the task, applicable repository instructions, existing work, and intended base and head branches. Reuse an existing PR for its scope rather than creating a duplicate.
2. Isolate the task in a branch or worktree, preserving unrelated changes. Keep temporary setup and generated artifacts out of the implementation diff.
3. Follow the repository's architecture, staging, commit, and verification rules. Reproduce bugs before fixing them and add meaningful behavior coverage where practical. Use existing lockfiles and an isolated test environment that cannot modify shared or production data.
4. Run the required checks from the general guidelines and checks specific to the change. Record exact results, separating pre-existing failures and environment blockers from regressions. A check that failed or could not run is not a pass.
5. For user-visible changes, follow the frontend stability guidelines and perform applicable device verification. For media PRs, also prepare the evidence below.

## Device verification

- Test changed mobile interactions on a representative supported device or simulator. Viewport emulation alone does not verify the on-screen keyboard, browser controls, safe areas, or touch. Record the device, operating system, browser, and whether testing used hardware, a simulator, or emulation.
- Check that inputs and primary actions remain usable with the keyboard open, fixed controls clear navigation and browser controls, and scrolling works beyond the first screen. Exercise client-side transitions as well as fresh loads.
- Verify actual results and fix or report findings. Disclose unavailable device coverage. Include relevant device evidence in media PRs.

## Media evidence

- Capture before/after evidence against the PR's base branch using comparable, realistic test data. Cover each changed UI and relevant success, failure, focus, loading, and content edge state at the viewports and themes required by the frontend guidelines.
- Use screenshots for static changes and recordings when behavior, loading, or motion matters. Label the state, viewport, theme, and test environment. Clearly identify simulated failures and emulation.
- Inspect the captures for clipping, overflow, errors, missed states, and layout stability. Verify the behavior shown, including relevant device results and required layout-shift measurements. Fix or disclose findings before review.
- Use stable, accessible media links and verify they resolve. Keep raw captures, capture scripts, and temporary authentication support out of the implementation diff; follow repository policy for published assets.

## Independent review before publication

1. Remove temporary routes, debug instrumentation, and unrelated artifacts. Prepare the final revision according to the applicable staging and commit instructions.
2. Obtain review from someone other than the implementer. Keep the review read-only and separate from implementation. If independent review is unavailable, report the blocker and keep publication on hold.
3. Supply the original task, applicable instructions, access to the change, exact base/head revisions, full diff scope, validation results, and limitations. Require an explicit `PASS` or `FAIL` covering correctness, regressions, architecture, and meaningful tests, with file/line evidence for actionable findings.
4. For media PRs, also supply the before/after captures, device results, and a runnable preview when available. Require inspection of the actual evidence and interactions where needed, with separate code and visual verdicts. The reviewer must explain when visual review is inapplicable; missing required evidence blocks PASS.
5. Address each FAIL finding or provide evidence for a disagreement, rerun affected checks, and update affected captures. Obtain a fresh independent review of the updated revision, carrying forward the original brief, prior findings, responses, and unresolved objections.
6. Obtain explicit PASS with no unresolved blockers before opening a draft or ready PR or publishing updates. Tie it to the exact base/head, diff, and applicable evidence. Changes to the implementation, base revision, or visual evidence require renewed review. The implementer's own judgment cannot substitute for independent PASS.

## Publish and report

- Honor the user's current publishing authorization and applicable approval requirements. A request for local review keeps pushes and PR creation on hold until explicitly authorized. PR work does not authorize deployment or production changes. Never merge or enable auto-merge without explicit user approval to merge in the current conversation.
- Use the repository's title convention. Fill the chosen template, remove prompts, and lead with the problem and resulting behavior. Include relevant implementation details, exact validation, the independent verdict and reviewed revision, and limitations. Keep conversation history and unsupported claims out of the body.
- Once publication is authorized and review passes, publish the branch to the intended repository. For media PRs, prepare and verify the reviewed media links before creating or updating the PR.
- Create or update the PR against the intended base using the completed template. If creation fails or its response is lost, check for an existing PR before retrying.
- Remove task-owned temporary setup when no longer needed. Preserve requested deliverables, retained working copies, and pre-existing work.
- Report the PR URL, final revision, what changed and why, verification evidence and limits, and where any retained local work can be found.
