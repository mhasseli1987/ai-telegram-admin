<?php
namespace ATA\Contracts\AI;

defined('ABSPATH') || exit;

interface AIProviderInterface
{
    /**
     * @param AIRequest $request
     * @return AIResult
     */
    public function generate(AIRequest $request): AIResult;

    /**
     * @param array $config (baseUrl, model, etc. — no secret)
     * @return array List of model identifiers.
     */
    public function listModels(array $config = []): array;

    /**
     * @param array $config
     * @return array{success:bool, message:string, latency_ms:?int}
     */
    public function testConnection(array $config = []): array;
}