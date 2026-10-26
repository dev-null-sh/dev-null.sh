<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\UpdateDeviceLastUsedAt;
use App\Dto\SshPublicKey;
use App\Models\Device;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Verifies a request to the API, signed by the mobile app with an SSH key it keeps on the device.
 *
 * The signed message is "<unix timestamp>\n<nonce>\n<METHOD>\n<path and query>\n<body>". The timestamp and nonce
 * are also sent in the X-Devnull-Timestamp and X-Devnull-Nonce headers, and the signature in X-SSH-Signature.
 *
 * A request with images is sent as multipart, like a commit with images from the `devnull commit` script: the JSON
 * body goes in a payload part, the images in images[], and the signed message carries one more line before the body,
 * the comma-separated SHA-256 of each image in the order they are attached. A voice note goes the same way, alone in a
 * voice part, and that line is its SHA-256. It is signed under its own namespace, so the signature states which format
 * it covers.
 *
 * A key signs in as a user once that user approved it as a device on the web. The key doesn't have to be on their
 * GitHub account: the approval is the proof. So a key made for something else, like committing from CI, can't sign in.
 */
final readonly class SignedRequests
{
    public const string Namespace = 'devnull-api';

    public const string ImagesNamespace = 'devnull-api-images';

    private const array Headers = ['X-Devnull-Timestamp', 'X-Devnull-Nonce', 'X-SSH-Signature'];

    public function __construct(private SshSignatures $sshSignatures, private UpdateDeviceLastUsedAt $updateDeviceLastUsedAt)
    {
        //
    }

    /**
     * Whether the request comes with any of the signature headers. It still has to pass verify() to be trusted.
     */
    public function isSigned(Request $request): bool
    {
        return array_any(self::Headers, fn (string $header): bool => $request->headers->has($header));
    }

    /**
     * Checks the signature, its timestamp and its nonce, which it spends, and returns the key that signed the request,
     * whether or not it is approved as a device yet. A request with files reads its fields from its payload from
     * then on.
     */
    public function verifySignature(Request $request): SshPublicKey
    {
        $withFiles = $request->files->count() > 0;
        $body = $withFiles ? $this->payload($request) : $this->body($request);

        throw_if($body === null, InvalidArgumentException::class, 'Send the body of a signed request as JSON.');

        $timestamp = $request->headers->get('X-Devnull-Timestamp', '') ?? '';
        $nonce = $request->headers->get('X-Devnull-Nonce', '') ?? '';

        $publicKey = $this->sshSignatures->verify(
            implode("\n", [$timestamp, $nonce, $request->getMethod(), $request->getRequestUri(), ...($withFiles ? [implode(',', $this->fileHashes($request))] : []), $body]),
            $request->headers->get('X-SSH-Signature', '') ?? '',
            $withFiles ? self::ImagesNamespace : self::Namespace,
        );

        $this->sshSignatures->checkFreshness($timestamp, $nonce);

        if ($withFiles) {
            $this->readPayload($request, $body);
        }

        return $publicKey;
    }

    /**
     * The device is null when nobody approved the key that signed the request. Using a device counts as its last use.
     *
     * @return array{publicKey: SshPublicKey, device: Device|null}
     */
    public function verify(Request $request): array
    {
        $publicKey = $this->verifySignature($request);

        $device = Device::query()->with('user')->where('fingerprint', $publicKey->fingerprint())->first();

        if ($device instanceof Device) {
            $this->updateDeviceLastUsedAt->handle($device);
        }

        return [
            'publicKey' => $publicKey,
            'device' => $device,
        ];
    }

    /**
     * The raw body, which is all that is signed, so it has to be JSON. PHP hands a multipart body over as fields and
     * files and keeps no raw body, and the same signed bytes read as a form would give the endpoint other fields.
     */
    private function body(Request $request): ?string
    {
        $content = $request->getContent();

        if ($content === '') {
            return $request->request->count() > 0 ? null : '';
        }

        return $request->isJson() ? $content : null;
    }

    /**
     * The JSON body of a request with images or a voice note, or null unless it came as a payload part beside a list of
     * images or a single voice note, with no other fields or files.
     */
    private function payload(Request $request): ?string
    {
        $files = $request->files->all();
        $payload = $files['payload'] ?? null;
        $images = $files['images'] ?? null;
        $voice = $files['voice'] ?? null;

        $isMultipart = $request->request->count() === 0
            && count($files) === 2
            && $payload instanceof UploadedFile && $payload->isValid()
            && (is_array($images) && array_is_list($images) && array_all($images, fn (mixed $image): bool => $image instanceof UploadedFile) || $voice instanceof UploadedFile);

        $contents = $isMultipart ? file_get_contents($payload->getPathname()) : false;

        return is_string($contents) && is_array(json_decode($contents, true)) ? $contents : null;
    }

    /**
     * An image or voice note PHP couldn't take, too big for its upload limit for one, can't be hashed, and is a
     * validation error, as on the web, rather than a bad signature.
     *
     * @return list<string>
     */
    private function fileHashes(Request $request): array
    {
        /** @var array{images: list<UploadedFile>}|array{voice: UploadedFile} $files */
        $files = $request->files->all();
        [$field, $attribute, $uploads] = isset($files['images']) ? ['images', 'image', $files['images']] : ['voice', 'voice note', [$files['voice']]];

        return array_map(function (UploadedFile $file) use ($field, $attribute): string {
            throw_unless($file->isValid(), ValidationException::withMessages([$field => __('validation.uploaded', ['attribute' => $attribute])]));

            return (string) hash_file('sha256', $file->getPathname());
        }, $uploads);
    }

    /**
     * The payload becomes the request's fields, cleaned the way every request's fields are before it gets here.
     */
    private function readPayload(Request $request, string $payload): void
    {
        /** @var array<string, mixed> $fields */
        $fields = json_decode($payload, true);

        $request->request->replace($fields);
        resolve(NullBytes::class)->strip($request);

        foreach ([TrimStrings::class, ConvertEmptyStringsToNull::class] as $middleware) {
            resolve($middleware)->handle($request, fn (Request $request): Request => $request);
        }
    }
}
