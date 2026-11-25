<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\MediaType;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;

/**
 * A request that takes files takes its fields as JSON, and its files as a signed upload: the JSON as a `payload` file
 * part beside them, so the signature covers the JSON and the files' hashes (see SignedRequests). Scramble reads the
 * rules as one multipart form, where the fields would be form fields the API refuses.
 */
final readonly class SignedUploads implements DocumentTransformer
{
    private const string Multipart = 'multipart/form-data';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $body = $operation->requestBodyObject;
                $media = $body instanceof RequestBodyObject && isset($body->content[self::Multipart]) ? $body->getContent(self::Multipart) : null;
                $json = $media instanceof MediaType ? $media->schema : null;
                $schema = $json instanceof Reference ? $json->resolve() : $json;
                $fields = $schema instanceof Schema ? $schema->type : null;

                if (! $body instanceof RequestBodyObject || ! $json instanceof Schema && ! $json instanceof Reference || ! $fields instanceof ObjectType) {
                    continue;
                }

                $files = $this->takeFiles($fields);

                $body->content = [];
                $body->setContent('application/json', $json);
                $body->setContent(self::Multipart, $this->upload($files));
            }
        }
    }

    /**
     * @return array<string, Type>
     */
    private function takeFiles(ObjectType $fields): array
    {
        $files = [];

        foreach ($fields->properties as $name => $type) {
            if ($type !== null && $this->isFile($type)) {
                $files[$name] = $type;
                unset($fields->properties[$name]);
            }
        }

        $fields->required = array_values(array_diff($fields->required, array_keys($files)));

        return $files;
    }

    private function isFile(Type $type): bool
    {
        $item = $type instanceof ArrayType ? $type->items : $type;

        return $item instanceof StringType && $item->format === 'binary';
    }

    /**
     * The JSON and its files, all required: a request without files sends the JSON alone, and the API refuses an upload
     * that has none (see SignedRequests::payload()).
     *
     * @param  array<string, Type>  $files
     */
    private function upload(array $files): Schema
    {
        $payload = new StringType;
        $payload->format = 'binary';
        $payload->contentMediaType = 'application/json';
        $payload->setDescription("The request's fields, as the JSON body would send them, in a file part.");

        $upload = new ObjectType;
        $upload->addProperty('payload', $payload);

        foreach ($files as $name => $file) {
            $upload->addProperty($name, $file->nullable(false));
        }

        $upload->setRequired(['payload', ...array_keys($files)]);

        $schema = new Schema;
        $schema->type = $upload;

        return $schema;
    }
}
