<?php

namespace App\AI\Providers;

class GroqProvider extends AbstractOpenAiCompatibleProvider
{
    public function key(): string
    {
        return 'groq';
    }
}
