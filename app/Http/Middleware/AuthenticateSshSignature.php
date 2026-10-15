<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\VerifySignedCommit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a commit signed with the author's SSH key, as sent by the `devnull commit` script.
 */
final readonly class AuthenticateSshSignature
{
    public function __construct(private VerifySignedCommit $verifySignedCommit)
    {
        //
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $commit = $this->verifySignedCommit->handle(
                $request->getContent(),
                $request->headers->get('X-SSH-Signature', '') ?? '',
            );
        } catch (InvalidArgumentException $invalidArgumentException) {
            abort(401, $invalidArgumentException->getMessage());
        }

        Auth::setUser($commit['user']);

        $request->merge([
            'body' => $commit['body'],
            'reply_to' => $commit['reply_to'],
        ]);

        return $next($request);
    }
}
