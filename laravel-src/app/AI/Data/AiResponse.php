<?php

namespace App\AI\Data;

final readonly class AiResponse
{
    /**
     * @param  array<string, int>  $usage
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $text,
        public string $provider,
        public string $model,
        public ?string $responseId = null,
        public array $usage = ['input_tokens' => 0, 'output_tokens' => 0],
        public array $metadata = [],
    ) {}
}
