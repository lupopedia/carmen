<?php
/**
 * Mock LLM Client (for testing/development)
 * 
 * @package CARMEN
 * @version 0.1.0
 */

require_once __DIR__ . '/../LlmClientInterface.php';

class MockLlmClient implements LlmClientInterface {
    private string $provider;
    private string $model;
    private bool $available;
    
    public function __construct(string $provider, string $model, bool $available = true) {
        $this->provider = $provider;
        $this->model = $model;
        $this->available = $available;
    }
    
    public function generate(string $prompt, array $options = []): LlmResponse {
        if (!$this->available) {
            return LlmResponse::failure($this->provider, $this->model, "Provider not available");
        }
        
        // Mock response - returns formatted JSON structure
        $mockResponse = $this->generateMockResponse($prompt, $options);
        $tokensUsed = $this->countTokens($prompt . $mockResponse);
        
        return LlmResponse::success(
            $this->provider,
            $this->model,
            $mockResponse,
            $tokensUsed,
            ['mocked' => true, 'temperature' => $options['temperature'] ?? 0.3]
        );
    }
    
    public function generateChat(string $systemMessage, string $userMessage, array $options = []): LlmResponse {
        $fullPrompt = $systemMessage . "\n\nUser: " . $userMessage;
        return $this->generate($fullPrompt, $options);
    }
    
    public function countTokens(string $text): int {
        // Rough estimation: ~4 characters per token
        return (int)ceil(strlen($text) / 4);
    }
    
    public function getProviderName(): string {
        return $this->provider;
    }
    
    public function isAvailable(): bool {
        return $this->available;
    }
    
    /**
     * Generate mock response based on prompt content
     */
    private function generateMockResponse(string $prompt, array $options): string {
        // Detect stage type from prompt
        if (stripos($prompt, 'AGAPE') !== false || stripos($prompt, 'loving action') !== false) {
            return json_encode([
                'loving_actions' => [
                    'Provide working solution NOW',
                    'Show upgrade path without condemning current approach',
                    'Work WITH constraints, not against them'
                ],
                'behavioral_scores' => [
                    'did_it_teach' => true,
                    'did_it_help' => true,
                    'did_it_encourage' => true,
                    'patience_shown' => true,
                    'kindness_shown' => true,
                    'hope_shown' => true,
                    'flexibility_shown' => true
                ],
                'love_score' => 0.95,
                'why_loving' => 'Provides immediate help, teaches upgrade path, respects constraints'
            ], JSON_PRETTY_PRINT);
        }
        
        if (stripos($prompt, 'ERIS') !== false || stripos($prompt, 'root cause') !== false) {
            return json_encode([
                'discord_detected' => true,
                'root_cause' => 'AI lacks knowledge of user constraints before suggesting solutions',
                'root_cause_type' => 'knowledge_gap',
                'severity' => 'medium',
                'prevention_strategy' => 'Detect constraints BEFORE suggesting architecture'
            ], JSON_PRETTY_PRINT);
        }
        
        // Default mock JSON response
        return json_encode([
            'mock_response' => true,
            'prompt_length' => strlen($prompt),
            'note' => 'This is a mock response. Implement real LLM client for production.'
        ], JSON_PRETTY_PRINT);
    }
}

