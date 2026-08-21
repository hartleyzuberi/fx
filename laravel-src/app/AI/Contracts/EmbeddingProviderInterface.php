<?php

namespace App\AI\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, string $model): array;
}
