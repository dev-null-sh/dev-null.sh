<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\UpdateCommitHotScores;
use App\Enums\CommitStatus;
use App\Services\TheAlgorithm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Repeatable local Explore fixtures. Every announcement and developer is fictional.
 * Seed stored classifications and summaries directly, without AI calls or queue work.
 * Stable IDs preserve unrelated content, personal feedback and global suppression.
 */
final class ExploreSeeder extends Seeder
{
    /** @var array<string, array{title: string, categories: list<string>, overview: string, stories: list<array{string, string, string, string}>}> */
    private const array SUBJECTS = [
        'laravel' => [
            'title' => 'Laravel',
            'categories' => ['web-development', 'open-source'],
            'overview' => 'Queues, deployment, Eloquent, testing and developer tools for Laravel teams.',
            'stories' => [
                ['Tenant-aware queue dashboard enters public preview', 'The fictional Dockyard team added per-tenant queue depth, failed-job replay and worker saturation charts.', "A noisy tenant can now be spotted without exposing another customer's job payloads.", 'Retry permissions and redacted exception messages still need an application-level review.'],
                ['Invoice imports become safe to retry', 'The fictional Ledgerleaf app published its Laravel recipe for idempotent invoice imports.', 'A unique import key prevents a timed-out request from charging the same invoice twice.', 'The idempotency key must survive a client retry and expire only after the reconciliation window.'],
                ['Community package isolates flaky third-party API tests', 'The fictional Lantern HTTP kit can replay sanitized responses in Laravel feature tests.', 'A suite can exercise pagination and rate-limit handling without reaching a real API.', 'Recordings must remove credentials and include an explicit expiry date.'],
                ['Multi-tenant applications compare database isolation strategies', 'The fictional Atlas Desk team shared a migration from request-scoped filters to tenant-aware repositories.', 'Background jobs now carry an explicit tenant identifier instead of depending on request state.', 'Every query and queued job still needs a test that crosses the tenant boundary.'],
                ['Large Eloquent exports move to cursor-based streaming', 'The fictional Copper CRM team rebuilt its customer export as a streamed download.', 'Export memory stays bounded while rows are read in a stable primary-key order.', 'Concurrent edits require a documented snapshot policy and resumable export checkpoints.'],
                ['Laravel teams compare zero-downtime migration checklists', 'The fictional Orchard deployment guide separates nullable columns, backfills and constraint enforcement.', 'Application deploys no longer wait for a large table rewrite during the release window.', 'Old and new application versions must both understand the intermediate schema.'],
                ['A payment webhook inbox ships with replay protection', 'The fictional Parcel Pay demo stores webhook identifiers before dispatching Laravel jobs.', 'Duplicate deliveries return successfully while the original event is processed once.', 'A signature check and a consistent transaction boundary are still required.'],
                ['Feature tests expose a cached authorization decision', 'The fictional Ember support portal removed a permission cache shared between tenant contexts.', "Switching accounts now rechecks resource access instead of reusing another tenant's result.", 'Long-running workers need a separate regression case because their containers survive requests.'],
                ['Community benchmarks compare route-cache warm-up strategies', 'The fictional Meadow team published a repeatable Laravel deployment warm-up experiment.', 'Cold-route latency is measured separately from database connections and view compilation.', 'A warm-up request must not trigger user-visible writes or send notifications.'],
                ['Open-source form toolkit adds accessible validation summaries', 'The fictional Finch Forms package now links a Laravel validation summary to each invalid field.', 'Keyboard and screen-reader users can move directly from the summary to the affected input.', 'Dynamic field names and repeated form sections need unique stable identifiers.'],
            ],
        ],
        'typescript' => [
            'title' => 'TypeScript',
            'categories' => ['web-development', 'open-source'],
            'overview' => 'Typed APIs, frontend tooling, accessibility and practical TypeScript migrations.',
            'stories' => [
                ['Generated API client catches contract drift before deployment', 'The fictional Meridian SDK now checks generated TypeScript clients against response fixtures.', 'A renamed response property fails CI before it reaches the account settings screen.', 'Runtime validation is still needed when a server returns data that bypasses the schema.'],
                ['A dashboard removes its last unsafe type assertion', 'The fictional Nimbus analytics team replaced broad casts with discriminated unions.', 'Loading, empty, failed and ready states now have separate render paths.', 'An exhaustive switch only helps when the API boundary validates the discriminant.'],
                ['Community experiment measures smaller frontend bundles', 'The fictional Cedar UI project split its chart editor from the public dashboard bundle.', 'Visitors reading a dashboard no longer download the entire editing toolchain.', 'A smaller initial bundle can still hide a slow follow-up request after the first click.'],
                ['A shared component library tests keyboard behavior', 'The fictional Elmway design system added focus-order tests to its TypeScript components.', 'Dialogs return focus to the triggering button and menus support arrow-key navigation.', 'Portal-based overlays still need manual checks with real assistive technology.'],
                ['Package maintainers publish an ESM migration checklist', 'The fictional Pebble utility library documented its move away from dual package output.', 'Consumers can test import paths and declaration files before adopting the new build.', 'Older test runners and CommonJS-only integrations need an explicit compatibility decision.'],
                ['Monorepo teams compare incremental type-check pipelines', 'The fictional Northstar workspace caches TypeScript project-reference builds per package.', 'Unchanged packages no longer repeat type analysis on every pull request.', 'Cache keys must include compiler settings and the transitive declaration dependencies.'],
                ['A router prototype preserves unsaved form state', 'The fictional Tide Console UI now keeps a pending draft when users cancel navigation.', 'A back-button action no longer silently discards a long settings form.', 'Server-side navigation and multiple tabs need their own draft-conflict behavior.'],
                ['Typed feature flags gain expiry dates', 'The fictional Switchboard TS package associates each flag with an owner and removal deadline.', 'Stale rollout branches can be reported in CI without contacting the production flag service.', 'An expired flag still needs a deliberate product decision before either branch is removed.'],
                ['A documentation site rebuilds search in a worker', "The fictional Fieldnotes docs team moved local search indexing off the browser's main thread.", 'Typing in the search box stays responsive while a larger manual is indexed.', 'The worker message protocol needs runtime checks as well as TypeScript types.'],
                ['Frontend teams share a gradual strict-null migration', 'The fictional Prism board app enabled strict null checks package by package.', 'Missing values are handled at boundaries instead of being hidden behind non-null assertions.', 'A migration dashboard should track actual unsafe boundaries rather than just error counts.'],
            ],
        ],
        'postgresql' => [
            'title' => 'PostgreSQL',
            'categories' => ['web-development', 'open-source'],
            'overview' => 'Query plans, schema changes, indexing, backups and reliable PostgreSQL operations.',
            'stories' => [
                ['An online index rollout cuts the events endpoint tail latency', 'The fictional Maproom service tested a concurrent tenant-and-date index on its events table.', "The busiest customer's recent-events query avoids scanning unrelated tenants.", 'The team checks write amplification and disk headroom before keeping the new index.'],
                ['Backup drills uncover an untested restore dependency', 'The fictional Fern database team restored a backup into an isolated PostgreSQL instance.', 'A missing extension and ownership mismatch were caught before the next incident.', 'A successful backup job is not proof that an application can start after restore.'],
                ['A booking app replaces offset pagination with stable cursors', 'The fictional Seabird booking service now paginates with a timestamp and unique ID.', 'Rows stop jumping between pages when new reservations arrive during browsing.', 'The cursor needs a deterministic tie-breaker when several rows share a timestamp.'],
                ['Query-plan clinic compares skewed tenant workloads', 'The fictional Glasshouse workshop published plans for both tiny and very large tenants.', 'An index that helps a small fixture is no longer assumed to help the largest account.', 'Prepared statements and changing statistics can alter the chosen execution plan.'],
                ['A queue prototype tests skip-locked row claims', 'The fictional Waypoint workers claim pending PostgreSQL rows inside short transactions.', 'Two workers can take separate jobs without waiting on the same locked record.', 'A crashed worker needs a lease expiry and an idempotent operation before retrying.'],
                ['A reporting pipeline retires a nightly table lock', 'The fictional Lantern Metrics team replaced a full-table refresh with incremental batches.', 'Interactive reads remain available while the latest reporting period is assembled.', 'Partial refreshes need a visible freshness marker and a recovery checkpoint.'],
                ['Connection-pool sizing becomes a repeatable load test', 'The fictional Holloway API team tested worker counts against a fixed PostgreSQL connection budget.', 'Adding application workers no longer unexpectedly exhausts the database connection limit.', 'Transaction pooling changes the assumptions around session-local database settings.'],
                ['A text-search experiment combines keywords and vectors', 'The fictional Index Meadow search prototype compares exact terms with semantic candidates.', 'Rare identifiers remain findable while natural-language questions get related matches.', 'Ranking evaluation needs labeled queries instead of a few convincing screenshots.'],
                ['A schema cleanup moves foreign-key checks out of the release window', 'The fictional Portage platform staged and validated new PostgreSQL constraints separately.', 'The application can introduce a relationship without a long validation step during deployment.', 'Existing invalid rows need an explicit remediation policy before validation can succeed.'],
                ['Database teams share a bloat monitoring playbook', 'The fictional Larch operations group linked table growth to vacuum history and long transactions.', 'A long-running report was identified before its dead rows consumed the remaining disk space.', 'Reclaiming disk space can require locks and must be scheduled rather than improvised.'],
            ],
        ],
        'rust' => [
            'title' => 'Rust',
            'categories' => ['open-source'],
            'overview' => 'Rust services, parsers, async execution, tooling and maintainable systems code.',
            'stories' => [
                ['A streaming parser prototype reduces copying', 'The fictional Bramble protocol crate now borrows packet headers from an input buffer.', 'The parser avoids allocating a second buffer for every small incoming frame.', 'Borrowed data cannot outlive the receive buffer that owns it.'],
                ['A CLI project publishes its first stable plugin protocol', 'The fictional Cairn command runner now exchanges versioned messages with plugins.', 'A plugin can fail independently without crashing the command-line interface.', 'The host still needs message-size limits and explicit protocol-version negotiation.'],
                ['An async service fixes cancellation during shutdown', 'The fictional Alder gateway now stops accepting work before draining active Rust tasks.', 'Requests already in progress receive a bounded grace period instead of disappearing.', 'Every task that owns a resource needs a clear cancellation and cleanup path.'],
                ['Maintainers compare error types across crate boundaries', 'The fictional Moss workspace distinguishes recoverable domain errors from operational context.', 'Callers can match a missing record without parsing a human-readable error string.', 'Public error variants become part of the compatibility promise of a library.'],
                ['A terminal dashboard adds a reduced-motion mode', 'The fictional Ridge TUI project replaced continuous transitions with explicit state changes.', 'Terminal navigation stays readable over slow remote sessions and for motion-sensitive users.', 'The alternative rendering path needs the same keyboard coverage as the default view.'],
                ['A storage experiment tests crash-safe manifest writes', 'The fictional Stonepath engine writes a new manifest before atomically replacing the active file.', 'A simulated process crash leaves either the old or the new manifest readable.', 'Atomic rename alone does not guarantee that bytes have reached durable storage.'],
                ['Rust teams compare smaller container build stages', 'The fictional Sorrel service separated dependency compilation from the application build.', 'A small source change can reuse compiled dependencies without rebuilding the entire image.', 'The runtime image still needs the certificates and shared libraries the binary actually uses.'],
                ['A networking crate adds bounded backpressure', 'The fictional Brook transport library now caps queued outbound messages per peer.', 'A slow consumer cannot keep growing an unbounded in-memory message queue.', 'The application must choose whether overload means waiting, rejecting or dropping work.'],
                ['A fuzzing campaign finds a malformed header edge case', 'The fictional Hazel codec team added minimized regression inputs to its Rust test corpus.', 'An invalid length prefix is rejected before the parser reserves a large allocation.', 'The demo reproducer is synthetic and does not claim a vulnerability in another project.'],
                ['An observability crate separates trace context from business data', 'The fictional Wren telemetry adapter passes trace identifiers without recording request bodies.', 'Services can correlate a failed operation while avoiding sensitive payloads in spans.', 'Sampling and redaction rules need review when new attributes are introduced.'],
            ],
        ],
        'python' => [
            'title' => 'Python',
            'categories' => ['ai', 'open-source'],
            'overview' => 'Python services, data pipelines, packaging, async jobs and dependable automation.',
            'stories' => [
                ['A job service adopts structured cancellation', 'The fictional Juniper pipeline now groups related Python tasks under one lifecycle.', 'A failed child task cancels its sibling work before the job reports a clean failure.', 'Cancellation handlers still need bounded cleanup and must not swallow the cancellation signal.'],
                ['A data import toolkit adds reproducible input manifests', 'The fictional Finch Data runner records source checksums and parser settings for each import.', 'A failed customer import can be reproduced against the exact same synthetic fixture.', 'The manifest must reference approved storage without copying private source rows into logs.'],
                ['Package maintainers trial isolated command-line tools', 'The fictional Pebble Tasks guide tests automation tools in isolated Python environments.', 'Installing a lint tool no longer changes the dependencies of the application being checked.', 'Pinned tools still need a planned update process and an auditable package source.'],
                ['A pandas workflow replaces row-by-row processing', 'The fictional Reed Analytics demo batches transformations over a typed tabular input.', 'Processing time falls without introducing a separate distributed compute service.', 'Batch size needs to be measured against peak memory rather than chosen from row count alone.'],
                ['A FastAPI demo clarifies request versus job validation', 'The fictional Willow API validates request shape before handing durable work to a queue.', 'Workers receive a stable job envelope rather than an entire web request object.', 'Business invariants must still be checked when the queued job eventually runs.'],
                ['A Python library publishes a compatibility test matrix', 'The fictional Fern Schema project checks supported interpreters against the same saved fixtures.', 'A dependency update can be reviewed with reproducible contract differences.', 'An installation success does not prove that optional native extensions work on every platform.'],
                ['A notebook project makes experiments repeatable', 'The fictional Acorn Lab demo moves configuration and data selection into versioned inputs.', 'Two developers can reproduce an experiment without relying on notebook cell execution order.', 'Random seeds do not remove nondeterminism from every numerical backend.'],
                ['An async client adds retry budgets', 'The fictional Harborleaf HTTP adapter bounds total retry time across all attempts.', 'A slow dependency no longer makes a request wait through several independent timeouts.', 'Retrying a write is only safe when the remote operation has an idempotency contract.'],
                ['A community benchmark compares typed validation boundaries', 'The fictional Clover ingestion team measures validation separately from JSON decoding.', 'The team can choose a boundary implementation using representative payload sizes.', 'Performance numbers should include rejected and deeply nested inputs as well as the happy path.'],
                ['A scheduler demo handles daylight-saving transitions', 'The fictional Sunroom scheduling app distinguishes a local calendar time from a fixed interval.', "A daily report can follow the customer's timezone without pretending every day has 24 hours.", 'Ambiguous and missing local times need a documented policy before deployment.'],
            ],
        ],
        'kubernetes' => [
            'title' => 'Kubernetes',
            'categories' => ['open-source'],
            'overview' => 'Container rollouts, workload isolation, cluster capacity and production operations.',
            'stories' => [
                ['A rollout controller pauses on application readiness failures', 'The fictional Estuary platform now holds a Kubernetes deployment when synthetic checks fail.', 'A process being alive no longer counts as proof that the application can serve requests.', 'Readiness checks must not overload the same dependency during an incident.'],
                ['Platform teams publish a resource-request tuning exercise', 'The fictional Fern Fleet workshop compares requested CPU and memory with observed workloads.', "A cluster can schedule reliably without treating every application's peak as its normal demand.", 'Memory limits need a separate failure-mode review because an exceeded limit can kill a process.'],
                ['A GitOps demo previews drift before reconciliation', 'The fictional Lumen Ops dashboard shows the difference between desired and live resources.', 'An operator can see why a controller wants to replace an object before approving a rollout.', 'Emergency changes still need to be reconciled back into the declared configuration.'],
                ['An internal developer platform adds short-lived preview namespaces', 'The fictional Tern Preview service creates isolated namespaces for review environments.', "Reviewers can test an application revision without sharing another branch's database.", 'Namespace deletion needs a reliable cleanup policy for external resources and volumes.'],
                ['A service team tests graceful connection draining', 'The fictional Briar API stops taking traffic before its pods terminate.', 'Long-lived requests can finish during a rollout instead of being cut off immediately.', 'The ingress timeout and termination grace period must agree on the intended behavior.'],
                ['A cluster workshop explores network-policy defaults', 'The fictional Sandbar Lab starts demo namespaces with denied traffic and opens specific paths.', 'An unexpected pod cannot reach every service just because it runs in the same cluster.', 'DNS, monitoring and health-check traffic need explicit allowances in the exercise.'],
                ['Platform engineers rehearse a failed node evacuation', 'The fictional Ash Fleet exercise checks disruption budgets and replacement capacity together.', 'A drain failure becomes a capacity decision rather than a surprise during node maintenance.', 'An overly strict disruption budget can prevent safe maintenance indefinitely.'],
                ['An image pipeline introduces digest-based deployment records', 'The fictional Copper Registry demo records the exact container digest used by each rollout.', 'A rollback can select the same image bytes instead of relying on a mutable tag.', 'A digest identifies content but does not replace provenance or vulnerability review.'],
                ['A batch-processing team adds workload priorities', 'The fictional Osprey Jobs platform keeps interactive services ahead of expendable batch work.', 'A temporary import surge does not crowd out the customer-facing API.', 'Priority needs quotas so one team cannot label every workload as critical.'],
                ['A cost dashboard links idle capacity to ownership', 'The fictional Fieldstone cluster demo groups resource allocations by team and service.', 'Teams can see unused reservations without exposing application payloads or customer data.', 'Removing headroom solely from an average utilization chart can damage incident resilience.'],
            ],
        ],
        'ai-engineering' => [
            'title' => 'AI Engineering',
            'categories' => ['ai', 'open-source'],
            'overview' => 'Grounded assistants, evaluation, retrieval, model serving and safe AI integrations.',
            'stories' => [
                ['A retrieval assistant starts returning inspectable citations', 'The fictional Lattice Notes demo links each generated answer to the source passages it used.', 'Reviewers can check whether a statement is supported without rerunning the model call.', 'A citation can still be irrelevant; evaluation must check support rather than just link presence.'],
                ['An evaluation harness adds deliberately unanswerable questions', 'The fictional Orchard Eval suite includes prompts whose answer is absent from the supplied documents.', 'A model is rewarded for acknowledging missing evidence rather than inventing a plausible answer.', 'The evaluation set needs held-out examples so prompt tuning does not simply memorize it.'],
                ['A support assistant moves tool permissions into application code', 'The fictional Harbor Desk demo validates every tool argument against the requesting account.', "A model-generated customer identifier cannot override the application's authorization boundary.", 'Prompt instructions are useful guidance but are not an authorization mechanism.'],
                ['A local model-serving experiment measures time to first token', 'The fictional Spruce Serve team separates queue delay, prompt processing and generated-token speed.', 'Developers can compare interactive responsiveness instead of quoting a single throughput number.', 'A benchmark with one short prompt does not represent concurrent long-context requests.'],
                ['A document pipeline compares chunking strategies', 'The fictional Moss Search experiment evaluates section boundaries against fixed-size text windows.', 'Retrieved passages can retain the heading that explains an otherwise ambiguous paragraph.', 'Larger chunks can improve context while reducing the number that fits in a model request.'],
                ['An assistant gateway introduces per-request token budgets', 'The fictional Brook AI proxy reserves room for instructions, tools and the final answer.', 'A long conversation fails predictably or is reduced before the provider rejects the request.', 'Trimming must preserve the evidence and tool results required for the current question.'],
                ['A red-team exercise tests instructions hidden in retrieved text', 'The fictional Wren Guard fixtures put malicious instructions inside ordinary-looking documents.', 'The application checks whether an assistant treats retrieved content as evidence rather than authority.', 'A passing prompt-injection example is not a general guarantee against all adversarial content.'],
                ['A model comparison focuses on structured output validity', 'The fictional Pebble Bench runner checks required fields and known references before accepting output.', 'Malformed results can be rejected without partially inventing database relationships.', 'A valid JSON document can still contain unsupported claims or incorrect classifications.'],
                ['A community project evaluates accessible image descriptions', 'The fictional Open Lens demo asks reviewers to score concise descriptions against a shared rubric.', 'Evaluation checks useful context rather than rewarding an exhaustive list of visible objects.', 'Descriptions should avoid guessing identity, private circumstances or unsupported intent.'],
                ['An AI operations dashboard separates freshness from popularity', 'The fictional Meadow Signals prototype stores classifications and recomputes rankings from current activity.', 'A new reaction can change the ranking without paying to classify the same text again.', 'Derived summaries must be invalidated when their supporting sources become unavailable.'],
            ],
        ],
    ];

    public function run(UpdateCommitHotScores $scores, TheAlgorithm $algorithm): void
    {
        throw_unless(app()->environment(['local', 'testing']), RuntimeException::class, 'Explore demo data is only available in local and testing environments.');

        $now = now()->toImmutable();
        $fixtureId = fn (string $key): string => Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://dev-null.sh/demo/explore/'.$key)->toString();
        $developers = collect([
            'Maya Chen', 'Leo Bennett', 'Amara Okafor', 'Noah Silva', 'Priya Shah', 'Felix Berg', 'Sofia Costa', 'Owen Reed',
            'Zara Malik', 'Elias Moreau', 'Nina Park', 'Theo Anders', 'Ava Morgan', 'Luca Rossi', 'Iris Tan', 'Samir Patel',
            'Clara Weiss', 'Hugo Martins', 'Elena Novak', 'Arjun Rao', 'Freya Olsen', 'Daniel Kim', 'Mila Petrov', 'Oscar Lane',
            'Ada Brooks', 'Ravi Mehta', 'Ines Duarte', 'Ben Foster', 'Yara Hassan', 'Finn Wallace', 'Eva Lind', 'Mateo Cruz',
            'Lena Fischer', 'Kai Wilson', 'Anika Das', 'Jules Laurent', 'Ruby Evans', 'Tomas Varga', 'Layla Aziz', 'Alex Rivers',
        ])->map(fn (string $name, int $index): array => [
            'id' => $fixtureId('developer/'.$index), 'github_id' => 'explore-demo:'.$index,
            'username' => 'explore-demo-'.Str::before(Str::slug($name), '-'), 'name' => $name,
            'bio' => 'Fictional Explore demo developer. '.['Backend systems and reliable queues.', 'Accessible interfaces and TypeScript.', 'Databases and infrastructure.', 'Open-source tooling and Rust.', 'Python, retrieval and AI evaluation.'][$index % 5],
            'timezone' => 'UTC', 'onboarded_at' => $now->subDays(30), 'commit_rules_accepted_at' => $now->subDays(30),
            'created_at' => $now->subDays(30), 'updated_at' => $now,
        ]);
        $developerIds = $developers->pluck('id')->all();
        $subjectIds = collect(self::SUBJECTS)->mapWithKeys(fn (array $subject, string $key): array => [$key => $fixtureId('subject/'.$key)]);
        $topics = [];
        $commits = [];
        $memberships = [];
        $relationships = [];
        $sources = [];
        $work = [];
        $stars = [];
        $aliases = [];
        $follows = [];
        $rootIds = [];

        foreach (self::SUBJECTS as $subjectKey => $subject) {
            $subjectId = $subjectIds[$subjectKey];
            $topics[] = ['id' => $subjectId, 'kind' => 'subject', 'key' => 'demo-'.$subjectKey, 'title' => $subject['title'],
                'categories' => json_encode($subject['categories'], JSON_THROW_ON_ERROR),
                'summary' => 'Demo overview: '.$subject['overview'].' These fictional discussions are supplied for local Explore testing.',
                'summary_generated_at' => $now, 'summary_dirty_at' => null, 'created_at' => $now->subDay(), 'updated_at' => $now];
            foreach ([$subjectKey, Str::slug($subject['title'])] as $alias) {
                $aliases[$alias] = ['alias' => $alias, 'topic_id' => $subjectId];
            }

            foreach ($subject['stories'] as $storyIndex => [$title, $change, $finding, $caution]) {
                $storyKey = $subjectKey.'-'.$storyIndex;
                $storyNumber = intdiv(count($rootIds), 24);
                $storyId = $fixtureId('story/'.$storyKey);
                $storySubjects = [$subjectKey, ...(match ($storyKey) {
                    'laravel-5' => ['postgresql'], 'postgresql-7' => ['ai-engineering'], 'rust-6' => ['kubernetes'],
                    'ai-engineering-4' => ['python'], 'ai-engineering-3' => ['kubernetes'], default => [],
                })];
                $topics[] = ['id' => $storyId, 'kind' => 'story', 'key' => 'demo-'.Str::slug($title), 'title' => $title,
                    'categories' => json_encode(array_values(array_unique(['news', ...$subject['categories']])), JSON_THROW_ON_ERROR),
                    'summary' => 'Demo story: '.$change.' '.$finding.' Community discussion focuses on this trade-off: '.$caution,
                    'summary_generated_at' => $now, 'summary_dirty_at' => null, 'created_at' => $now->subHours(12), 'updated_at' => $now];
                foreach ($storySubjects as $key) {
                    $relationships[] = ['story_id' => $storyId, 'subject_id' => $subjectIds[$key]];
                }

                $perspectives = [
                    $title.".\n\n".$change,
                    'First impression of '.$title.":\n".$finding.' I would still keep a rollback path for the first deployment.',
                    'We have been discussing '.$title." in our platform review.\n".$caution,
                    'The useful part of '.$title.' is the operational detail. '.$finding.' A release announcement alone would not have answered our questions.',
                    'Before adopting '.$title.' I would put the failure case in CI. '.$caution,
                    'Our small-team take on '.$title.":\n".$change.' The maintenance cost matters more to us than adding another dashboard.',
                    'Has anyone tried '.$title.' against a large customer workload? '.$finding.' I want to see both the typical case and the slowest tenant.',
                    'The migration plan is what interests me in '.$title.'. '.$caution.' A feature flag would make the first rollout easier to compare.',
                    'I would document the recovery procedure alongside '.$title.'. '.$change.' The person on call should not have to infer it from the source.',
                    'A useful distinction in '.$title.":\n".$finding.' That is a concrete behavior we can measure in an integration test.',
                    'For a new project, '.$title.' looks approachable. '.$caution.' Existing deployments probably need a slower migration.',
                    'My checklist for '.$title.': representative data, a rollback rehearsal, and a failure injection test. '.$finding,
                    'I appreciate the explicit trade-off in '.$title.'. '.$caution.' Those details usually decide whether a tool fits our team.',
                    'We would test '.$title.' in a preview environment first. '.$change.' Production traffic should not be the first realistic input.',
                    'An observability question about '.$title.': what would tell us the new path is failing? '.$finding.' An average alone would miss the outliers.',
                    'The support angle on '.$title.' is worth a look. '.$caution.' A useful error should explain the next action without leaking a customer payload.',
                    'There is a documentation opportunity around '.$title.'. '.$change.' I would like one small example and a complete rollback example.',
                    'Comparing '.$title.' with our current approach this afternoon. '.$finding.' We will keep the same input fixtures for both measurements.',
                    'The biggest adoption question for '.$title.' is ownership. '.$caution.' Somebody needs to maintain the compatibility checks after the launch.',
                    'A good follow-up to '.$title.' would be a reproducible benchmark. '.$finding.' Please include setup time and the workload shape.',
                    'For teams considering '.$title.":\n".$change.' Start with one bounded workload and write down what success looks like.',
                    'I would keep the first release of '.$title.' deliberately small. '.$caution.' A clear escape hatch is more valuable than covering every edge case immediately.',
                    'What convinced me to investigate '.$title.' was this detail: '.$finding.' I am adding a test for the boundary before trying it in our app.',
                    'Notes after reading the discussion on '.$title.":\n".$finding.' The remaining review item for us is: '.$caution,
                ];
                foreach ($perspectives as $postIndex => $body) {
                    $commitId = $fixtureId('commit/'.$storyKey.'/'.$postIndex);
                    $rootIds[] = $commitId;
                    $authorIndex = ($storyNumber * 3 + $postIndex) % count($developerIds);
                    $ageMinutes = 45 + $storyNumber * 8;
                    $createdAt = $now->subMinutes($ageMinutes)->addMinutes((int) floor($postIndex * ($ageMinutes - 2) / 23));
                    $commits[] = ['id' => $commitId, 'user_id' => $developerIds[$authorIndex], 'parent_id' => null, 'root_id' => null,
                        'body' => $body, 'status' => CommitStatus::Approved->value, 'created_at' => $createdAt, 'updated_at' => $createdAt,
                        'hot_score' => $algorithm->score($createdAt, 0, 0)];
                    $memberships[] = ['topic_id' => $storyId, 'commit_id' => $commitId];
                    if ($postIndex < 3) {
                        $sources[] = ['topic_id' => $storyId, 'commit_id' => $commitId];
                    }

                    if ($postIndex === 0) {
                        foreach ($storySubjects as $key) {
                            $sources[] = ['topic_id' => $subjectIds[$key], 'commit_id' => $commitId];
                        }
                    }

                    $work[] = ['commit_id' => $commitId, 'eligible_at' => $createdAt, 'claimed_at' => null, 'completed_at' => $now];
                    $starCount = 1 + (($storyIndex * 3 + $postIndex) % 16);
                    for ($starIndex = 1; $starIndex <= $starCount; $starIndex++) {
                        $stars[] = ['commit_id' => $commitId, 'user_id' => $developerIds[($authorIndex + $starIndex) % count($developerIds)],
                            'created_at' => $now->subSeconds($starIndex), 'updated_at' => $now];
                    }

                    if (in_array($postIndex, [0, 8, 15], true)) {
                        $replyId = $fixtureId('reply/'.$storyKey.'/'.$postIndex);
                        $commits[] = ['id' => $replyId, 'user_id' => $developerIds[($authorIndex + 25) % count($developerIds)],
                            'parent_id' => $commitId, 'root_id' => $commitId,
                            'body' => match ($postIndex) {
                                0 => 'On '.$title.': '.$caution.' Have you written down how you would detect this in the first rollout?',
                                8 => $finding.' How would you measure that during a staged rollout of '.$title.'?',
                                default => $change.' Could you share the smallest reproducible example for '.$title.'?',
                            },
                            'status' => CommitStatus::Approved->value, 'created_at' => $createdAt->addSeconds(30), 'updated_at' => $now,
                            'hot_score' => $algorithm->score($createdAt->addSeconds(30), 0, 0)];
                        if ($postIndex === 0) {
                            $commits[] = ['id' => $fixtureId('nested-reply/'.$storyKey), 'user_id' => $developerIds[$authorIndex],
                                'parent_id' => $replyId, 'root_id' => $commitId,
                                'body' => 'Yes. We would exercise the failure with synthetic inputs, record the expected alert, and rehearse the rollback. '.$finding,
                                'status' => CommitStatus::Approved->value, 'created_at' => $createdAt->addMinute(), 'updated_at' => $now,
                                'hot_score' => $algorithm->score($createdAt->addMinute(), 0, 0)];
                        }
                    }
                }
            }

            foreach (['What is the most useful '.$subject['title'].' lesson you learned on a small project this week?', 'Looking for a thoughtful code-review checklist for '.$subject['title'].'. I care about maintainability, clear boundaries and failure behavior.'] as $index => $body) {
                $id = $fixtureId('general/'.$subjectKey.'/'.$index);
                $commits[] = ['id' => $id, 'user_id' => $developerIds[$index], 'parent_id' => null, 'root_id' => null,
                    'body' => $body, 'status' => CommitStatus::Approved->value, 'created_at' => $now->subMinutes(10 + $index), 'updated_at' => $now,
                    'hot_score' => $algorithm->score($now->subMinutes(10 + $index), 0, 0)];
                $memberships[] = ['topic_id' => $subjectId, 'commit_id' => $id];
                $work[] = ['commit_id' => $id, 'eligible_at' => $now->subMinutes(10 + $index), 'claimed_at' => null, 'completed_at' => $now];
            }
        }

        foreach ($developerIds as $index => $developerId) {
            foreach ([1, 4, 11] as $offset) {
                $follows[] = ['follower_id' => $developerId, 'followed_id' => $developerIds[($index + $offset) % count($developerIds)],
                    'created_at' => $now->subDay(), 'updated_at' => $now];
            }
        }

        DB::transaction(function () use ($developers, $topics, $commits, $relationships, $memberships, $sources, $aliases, $work, $stars, $follows): void {
            DB::table('users')->upsert($developers->all(), ['id'], ['name', 'bio', 'timezone', 'onboarded_at', 'commit_rules_accepted_at', 'updated_at']);
            DB::table('explore_topics')->upsert($topics, ['id'], ['title', 'categories', 'summary', 'summary_generated_at', 'summary_dirty_at', 'updated_at']);
            foreach (array_chunk($commits, 200) as $chunk) {
                DB::table('commits')->upsert($chunk, ['id'], ['body', 'created_at', 'updated_at', 'hot_score']);
            }

            DB::table('explore_topic_subject')->insertOrIgnore($relationships);
            DB::table('explore_commit_topic')->insertOrIgnore($memberships);
            DB::table('explore_summary_sources')->insertOrIgnore($sources);
            DB::table('explore_topic_aliases')->insertOrIgnore(array_values($aliases));
            DB::table('explore_classification_work')->upsert($work, ['commit_id'], ['eligible_at', 'claimed_at', 'completed_at']);
            foreach (array_chunk($stars, 500) as $chunk) {
                DB::table('stars')->insertOrIgnore($chunk);
            }

            DB::table('follows')->insertOrIgnore($follows);
        });
        $scores->handle($rootIds);
        $this->command->info('Explore demo ready: 7 subjects, 70 stories, 1,680 story commits, 14 general commits, 280 replies and 40 fictional developers.');
    }
}
