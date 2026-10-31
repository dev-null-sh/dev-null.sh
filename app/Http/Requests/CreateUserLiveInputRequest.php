<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\CloudflareStream;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserLiveInputRequest extends FormRequest
{
    public function authorize(CloudflareStream $stream): Response
    {
        return $stream->configured() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * @return array{}
     */
    public function rules(): array
    {
        return [];
    }
}
