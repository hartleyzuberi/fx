<?php

namespace App\AI\Data;

final readonly class AiRequest
{
    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $task,
        public string $capability,
        public string $systemPrompt,
        public string $prompt,
        public string $promptVersion,
        public ?array $schema = null,
        public bool $sensitive = false,
        public ?int $userId = null,
        public ?string $requestId = null,
        public array $metadata = [],
    ) {}
}
