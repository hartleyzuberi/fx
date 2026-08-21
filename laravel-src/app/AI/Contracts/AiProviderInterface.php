<?php

namespace App\AI\Contracts;

use App\AI\Data\AiRequest;
use App\AI\Data\AiResponse;

interface AiProviderInterface
{
    public function key(): string;

    public function generate(AiRequest $request, string $model): AiResponse;

    /** @return array{ok: bool, latency_ms: int, model: string|null, error_code: string|null, message: string} */
    public function test(string $model): array;
}
