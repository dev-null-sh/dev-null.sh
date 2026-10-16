<?php

declare(strict_types=1);

/**
 * Connects to `ssh:serve` as a real SSH client: `php client.php <port> <private key file>...`.
 *
 * It offers the keys in order on one connection, as an SSH agent does. With an accepted key, it opens a shell and runs
 * a command. Then it connects again without a key, using keyboard-interactive without answers, as OpenSSH does when
 * the server accepts none of its keys.
 */
use Illuminate\Support\Sleep;
use phpseclib4\Common\Functions\Strings;
use phpseclib4\Crypt\Common\PrivateKey;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Net\SSH2;
use phpseclib4\Net\SSH2\MessageType;

require __DIR__.'/../../../vendor/autoload.php';

[, $port] = $argv;

$client = fn (): SSH2 => new class('127.0.0.1', (int) $port) extends SSH2
{
    /**
     * Sends a "window-change" request, as a terminal does when it is resized.
     */
    public function resize(int $columns, int $rows): void
    {
        $this->send_binary_packet(Strings::packSSH2(
            'CNsbNNNN',
            MessageType::CHANNEL_REQUEST,
            $this->server_channels[self::CHANNEL_SHELL],
            'window-change',
            false,
            $columns,
            $rows,
            0,
            0,
        ));
    }
};

$keys = array_map(fn (string $keyFile): PrivateKey => PublicKeyLoader::loadPrivateKey((string) file_get_contents($keyFile)), array_slice($argv, 2));

for ($attempt = 0; ; $attempt++) {
    try {
        $ssh = $client();
        $ssh->getServerPublicHostKey();

        break;
    } catch (Throwable $exception) {
        throw_if($attempt === 100, $exception);

        Sleep::usleep(50_000);
    }
}

$acceptedKey = null;

try {
    foreach ($keys as $key) {
        if ($ssh->login('dev', $key)) {
            $acceptedKey = $key;

            break;
        }

        echo "[key rejected]\n";
    }
} catch (Throwable) {
    echo "[disconnected]\n";
}

if ($acceptedKey !== null) {
    $ssh->setTimeout(5);
    $ssh->enablePTY();
    $ssh->setWindowSize(100, 30);
    echo $ssh->read('Press q to quit.');
    $ssh->resize(120, 40);
    echo $ssh->read('Press q to quit.');
    $ssh->write('q');
    echo $ssh->read();
    echo "\n[shell closed with {$ssh->getExitStatus()}]\n";

    $ssh = $client();
    $ssh->login('dev', $acceptedKey);
    echo $ssh->exec('ls');
    echo "[exec closed with {$ssh->getExitStatus()}]\n";
}

$ssh = $client();
echo $ssh->login('stranger', []) ? '' : "[stranger rejected]\n";
$ssh->setTimeout(5);
$ssh->enablePTY();
echo $ssh->read('Press q to quit.');
