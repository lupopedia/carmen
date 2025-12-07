<?php
/**
 * GROK API Client Implementation
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: GROK Client Implementation
 * WHAT: PHP client for xAI GROK API
 * WHEN: 2025-12-06
 * WHY: Enable CARMEN to use GROK as LLM provider
 * 
 * Implementation provided by: WOLFITH-GROK (Agent 101)
 * Based on: xAI API Reference (https://docs.x.ai/docs/api-reference)
 */

require_once __DIR__ . '/../LlmClientInterface.php';

class GrokClient implements LlmClientInterface {
    private string $apiKey;
    private string $baseUrl = 'https://api.x.ai/v1';
    private string $model;
    private array $defaults;
    
    public function __construct(string $apiKey, string $model = 'grok-beta', array $defaults = []) {
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->defaults = array_merge([
            'temperature' => 0.3,
            'max_tokens' => 1000,
            'timeout' => 30 // seconds
        ], $defaults);
    }
    
    /**
     * Generate completion from prompt
     */
    public function generate(string $prompt, array $options = []): LlmResponse {
        $messages = [['role' => 'user', 'content' => $prompt]];
        return $this->callChatCompletions($messages, $options);
    }
    
    /**
     * Generate completion from system/user message pair
     */
    public function generateChat(string $systemMessage, string $userMessage, array $options = []): LlmResponse {
        $messages = [
            ['role' => 'system', 'content' => $systemMessage],
            ['role' => 'user', 'content' => $userMessage],
        ];
        return $this->callChatCompletions($messages, $options);
    }
    
    /**
     * Call GROK chat completions endpoint
     */
    private function callChatCompletions(array $messages, array $options): LlmResponse {
        $payload = array_merge($this->defaults, $options, [
            'model' => $this->model,
            'messages' => $messages,
        ]);
        
        $response = $this->makeRequest('/chat/completions', $payload);
        
        if (isset($response['error'])) {
            return LlmResponse::failure('grok', $this->model, $response['error']);
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        $promptTokens = $response['usage']['prompt_tokens'] ?? 0;
        $completionTokens = $response['usage']['completion_tokens'] ?? 0;
        $totalTokens = $response['usage']['total_tokens'] ?? ($promptTokens + $completionTokens);
        
        // Create response with all token information
        $llmResponse = new LlmResponse(
            'grok',
            $this->model,
            true,
            $content,
            $totalTokens,
            $promptTokens,
            $completionTokens,
            [
                'model' => $response['model'] ?? $this->model,
                'finish_reason' => $response['choices'][0]['finish_reason'] ?? null
            ]
        );
        
        return $llmResponse;
    }
    
    /**
     * Count tokens in text
     * Uses GROK tokenize endpoint with fallback estimation
     */
    public function countTokens(string $text): int {
        $payload = [
            'model' => $this->model,
            'text' => $text,
        ];
        
        $response = $this->makeRequest('/tokenize-text', $payload);
        
        if (isset($response['error'])) {
            // Fallback estimation: ~4 chars per token (Grok average)
            return (int)(strlen($text) / 4) + 1;
        }
        
        return count($response['token_ids'] ?? []);
    }
    
    /**
     * Make HTTP request to GROK API with retry logic
     */
    private function makeRequest(string $endpoint, array $payload, string $method = 'POST'): array {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->defaults['timeout']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        
        $retries = 3;
        $backoff = 1; // seconds
        $result = null;
        
        while ($retries > 0) {
            $rawResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $result = json_decode($rawResponse, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $result = ['error' => 'Invalid JSON response: ' . json_last_error_msg()];
                }
                break;
            } elseif ($httpCode === 429) {
                // Rate limit - exponential backoff
                sleep($backoff);
                $backoff *= 2;
                $retries--;
            } elseif ($httpCode === 401) {
                // Authentication error - no retry
                $result = ['error' => 'Authentication failed. Check API key.'];
                break;
            } else {
                // Other errors - try once more after delay
                $error = curl_error($ch) ?: ($rawResponse ?? 'Unknown error');
                if ($retries > 1) {
                    sleep(2);
                    $retries--;
                } else {
                    $result = ['error' => "HTTP $httpCode: $error"];
                    break;
                }
            }
        }
        
        curl_close($ch);
        
        return $result ?? ['error' => 'Max retries exceeded or connection failed'];
    }
    
    /**
     * Get provider name
     */
    public function getProviderName(): string {
        return 'grok';
    }
    
    /**
     * Check if provider is available
     */
    public function isAvailable(): bool {
        if (empty($this->apiKey)) {
            return false;
        }
        
        // Simple check: Try tokenizing a small string
        try {
            $test = $this->countTokens('test');
            return $test > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

