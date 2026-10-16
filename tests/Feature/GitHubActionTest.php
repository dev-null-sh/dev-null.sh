<?php

declare(strict_types=1);

use App\Dto\SshPublicKey;
use App\Models\SshKey;
use App\Models\User;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * These tests run the GitHub Action's script with bash and the real `ssh-agent`, `ssh-add` and `ssh-keygen`,
 * in a sandbox: a fake HOME and TMPDIR, and a `curl` stub that keeps what it sends instead of sending it.
 */
beforeEach(function (): void {
    Process::preventStrayProcesses(false);

    $this->sandbox = sys_get_temp_dir().'/devnull-action-test-'.Str::random(10);

    File::makeDirectory("{$this->sandbox}/bin", recursive: true);
    File::makeDirectory("{$this->sandbox}/home");
    File::makeDirectory("{$this->sandbox}/tmp");

    Process::run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', 'release-bot', '-f', "{$this->sandbox}/key"])->throw();

    $this->privateKey = File::get("{$this->sandbox}/key");
    $this->publicKey = SshPublicKey::fromOpenSsh(File::get("{$this->sandbox}/key.pub"));

    File::put("{$this->sandbox}/keys-response", $this->publicKey->toOpenSsh()."\n");
    File::put("{$this->sandbox}/commit-response", "Committed: https://dev-null.sh/@nunomaduro/commits/0be0130 (pending review, only you can see it until it is approved)\n");

    File::put("{$this->sandbox}/bin/curl", <<<'BASH'
        #!/usr/bin/env bash
        output="" url=""
        while [ $# -gt 0 ]; do
          case "$1" in
            --output) output="$2"; shift 2 ;;
            --header) case "$2" in "X-SSH-Signature: "*) printf '%s' "${2#X-SSH-Signature: }" > "$SANDBOX/sent-signature" ;; esac; shift 2 ;;
            --proto | --max-time | --write-out) shift 2 ;;
            --data-binary) cp "${2#@}" "$SANDBOX/sent-$(basename "${2#@}")"; shift 2 ;;
            -*) shift ;;
            *) url="$1"; shift ;;
          esac
        done
        case "$url" in
          */api/cli/key-lookups) cat "$SANDBOX/keys-response" > "$output"; printf '200' ;;
          */api/cli/commits) cat "$SANDBOX/commit-response" > "$output"; printf '%s' "${COMMIT_STATUS:-201}" ;;
        esac
        BASH);
    File::chmod("{$this->sandbox}/bin/curl", 0755);

    $this->runAction = fn (array $env = []): ProcessResult => Process::path(base_path())
        ->env([
            'PATH' => "{$this->sandbox}/bin:".getenv('PATH'),
            'HOME' => "{$this->sandbox}/home",
            'TMPDIR' => "{$this->sandbox}/tmp",
            'SANDBOX' => $this->sandbox,
            'SSH_AUTH_SOCK' => false,
            'GITHUB_ACTIONS' => 'true',
            'DEVNULL_URL' => 'https://dev-null.sh',
            'DEVNULL_SSH_KEY' => $this->privateKey,
            'DEVNULL_BODY' => "Released v2.0.0.\n\nhttps://github.com/nunomaduro/pest/releases/tag/v2.0.0",
            'DEVNULL_REPLY_TO' => '',
            ...$env,
        ])
        ->timeout(20)
        ->run(['bash', '.github/actions/commit/commit.sh']);
});

afterEach(function (): void {
    File::deleteDirectory($this->sandbox);
});

it('commits with the key from the secret, signed so devnull accepts it', function (): void {
    $result = ($this->runAction)();

    expect($result->exitCode())->toBe(0)
        ->and($result->output())->toBe("::notice title=devnull::Committed: https://dev-null.sh/@nunomaduro/commits/0be0130 (pending review, only you can see it until it is approved)\n")
        ->and($result->output().$result->errorOutput())->not->toContain('PRIVATE KEY')
        ->and(File::get("{$this->sandbox}/sent-candidates"))->toBe($this->publicKey->toOpenSsh()."\n")
        ->and(File::allFiles("{$this->sandbox}/home", hidden: true))->toBeEmpty();

    $user = User::factory()->create();
    SshKey::factory()->for($user)->withPublicKey($this->publicKey)->create();
    fakeGitHubSshKeys($user, signingKeys: [$this->publicKey]);

    $this->call('POST', route('cli-commit.store'), server: [
        'CONTENT_TYPE' => 'text/plain; charset=utf-8',
        'HTTP_X_SSH_SIGNATURE' => File::get("{$this->sandbox}/sent-signature"),
    ], content: File::get("{$this->sandbox}/sent-commit"))->assertCreated();

    expect($user->commits()->sole()->body)->toBe("Released v2.0.0.\n\nhttps://github.com/nunomaduro/pest/releases/tag/v2.0.0");
});

it('replies to a commit', function (): void {
    $result = ($this->runAction)(['DEVNULL_REPLY_TO' => '0be0130', 'DEVNULL_BODY' => 'Agreed!']);

    expect($result->exitCode())->toBe(0)
        ->and(File::get("{$this->sandbox}/sent-commit"))->toMatch('/^\d+\n[0-9a-f]{32}\n0be0130\nAgreed!$/');
});

it('fails with the answer of devnull', function (): void {
    File::put("{$this->sandbox}/commit-response", "Slow down: 10 commits a minute. Even CI takes breaks.\n");

    $result = ($this->runAction)(['COMMIT_STATUS' => '429']);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toBe("::error title=devnull::Slow down: 10 commits a minute. Even CI takes breaks.\n");
});

it('fails when the secret is not a private key without a passphrase', function (string $key): void {
    $result = ($this->runAction)(['DEVNULL_SSH_KEY' => $key]);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toBe("::error title=devnull::Could not load the ssh-key input: it must be a private Ed25519 or RSA key without a passphrase.\n")
        ->and(File::exists("{$this->sandbox}/sent-candidates"))->toBeFalse();
})->with([
    'a public key' => fn (): string => $this->publicKey->toOpenSsh(),
    'garbage' => 'not a key',
]);

it('requires a body', function (): void {
    $result = ($this->runAction)(['DEVNULL_BODY' => '']);

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toBe("::error title=devnull::The body input is empty.\n");
});
