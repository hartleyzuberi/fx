<?php

namespace App\AI\Providers;

class OpenRouterProvider extends AbstractOpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'openrouter';
    }
}
