<?php
/**
 * LLM Client Interface
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: LLM Client Interface
 * WHAT: Standardized contract for LLM providers (OpenAI, Grok, etc.)
 * WHEN: 2025-12-02
 * WHY: Enable switching between LLM providers for A/B testing and flexibility
 */

interface LlmClientInterface {
    
    /**
     * Generate completion from prompt
     * 
     * @param string $prompt The prompt text
     * @param array $options Generation options (temperature, max_tokens, etc.)
     * @return LlmResponse Response object with text, tokens used, metadata
     */
    public function generate(string $prompt, array $options = []): LlmResponse;
    
    /**
     * Generate completion from system/user message pair
     * 
     * @param string $systemMessage System/instruction message
     * @param string $userMessage User input message
     * @param array $options Generation options
     * @return LlmResponse
     */
    public function generateChat(string $systemMessage, string $userMessage, array $options = []): LlmResponse;
    
    /**
     * Count tokens in text (estimation)
     * 
     * @param string $text Text to count
     * @return int Estimated token count
     */
    public function countTokens(string $text): int;
    
    /**
     * Get provider name
     * 
     * @return string Provider identifier (e.g., "openai", "grok")
     */
    public function getProviderName(): string;
    
    /**
     * Check if provider is available/configured
     * 
     * @return bool TRUE if provider is ready to use
     */
    public function isAvailable(): bool;
}

/**
 * LLM Response Object
 */
class LlmResponse {
    public string $text;
    public int $tokensUsed;
    public ?int $tokensPrompt = null;
    public ?int $tokensCompletion = null;
    public string $provider;
    public string $model;
    public array $metadata;
    public ?string $error = null;
    public bool $success;
    
    public function __construct(
        string $provider,
        string $model,
        bool $success = true,
        string $text = '',
        int $tokensUsed = 0,
        ?int $tokensPrompt = null,
        ?int $tokensCompletion = null,
        array $metadata = [],
        ?string $error = null
    ) {
        $this->provider = $provider;
        $this->model = $model;
        $this->success = $success;
        $this->text = $text;
        $this->tokensUsed = $tokensUsed;
        $this->tokensPrompt = $tokensPrompt;
        $this->tokensCompletion = $tokensCompletion;
        $this->metadata = $metadata;
        $this->error = $error;
    }
    
    public static function failure(string $provider, string $model, string $error): self {
        return new self($provider, $model, false, '', 0, null, null, [], $error);
    }
    
    public static function success(string $provider, string $model, string $text, int $tokensUsed, array $metadata = []): self {
        return new self($provider, $model, true, $text, $tokensUsed, null, null, $metadata);
    }
    
    public function toArray(): array {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'success' => $this->success,
            'text' => $this->text,
            'tokens_used' => $this->tokensUsed,
            'tokens_prompt' => $this->tokensPrompt,
            'tokens_completion' => $this->tokensCompletion,
            'metadata' => $this->metadata,
            'error' => $this->error
        ];
    }
}

