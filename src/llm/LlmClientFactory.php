<?php
/**
 * LLM Client Factory
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: LLM Client Factory
 * WHAT: Creates LLM client instances based on provider configuration
 * WHEN: 2025-12-02
 * WHY: Enable switching between OpenAI, Grok, and other providers
 */

require_once __DIR__ . '/LlmClientInterface.php';

class LlmClientFactory {
    private array $config;
    private array $clients = []; // Cache clients
    
    public function __construct(array $config = []) {
        $this->config = $config;
    }
    
    /**
     * Create LLM client for specified provider
     * 
     * @param string $provider Provider name ('openai', 'grok', etc.)
     * @param array $config Optional override config
     * @return LlmClientInterface
     * @throws \Exception if provider not supported or misconfigured
     */
    public function create(string $provider, array $config = []): LlmClientInterface {
        // Check cache
        $cacheKey = $provider . '_' . md5(json_encode($config));
        if (isset($this->clients[$cacheKey])) {
            return $this->clients[$cacheKey];
        }
        
        // Merge config
        $mergedConfig = array_merge($this->config, $config);
        
        // Create client based on provider
        switch (strtolower($provider)) {
            case 'openai':
                $client = $this->createOpenAiClient($mergedConfig);
                break;
                
            case 'grok':
                $client = $this->createGrokClient($mergedConfig);
                break;
                
            default:
                throw new \Exception("Unsupported LLM provider: $provider");
        }
        
        // Cache client
        $this->clients[$cacheKey] = $client;
        
        return $client;
    }
    
    /**
     * Create OpenAI client
     */
    private function createOpenAiClient(array $config): LlmClientInterface {
        // TODO: Implement OpenAI client
        // For now, return mock client
        require_once __DIR__ . '/clients/MockLlmClient.php';
        return new MockLlmClient('openai', $config['models']['openai'] ?? 'gpt-4');
    }
    
    /**
     * Create Grok client
     */
    private function createGrokClient(array $config): LlmClientInterface {
        require_once __DIR__ . '/clients/GrokClient.php';
        
        $apiKey = $config['api_keys']['grok'] ?? getenv('GROK_API_KEY') ?? '';
        if (empty($apiKey)) {
            throw new \Exception("GROK API key not configured. Set GROK_API_KEY environment variable or configure in carmen.yaml");
        }
        
        $model = $config['models']['grok'] ?? 'grok-beta';
        $defaults = $config['defaults'] ?? [];
        
        return new GrokClient($apiKey, $model, $defaults);
    }
    
    /**
     * Get default provider from config
     */
    public function getDefaultProvider(): string {
        return $this->config['llm']['default_provider'] ?? 'openai';
    }
    
    /**
     * Get list of available providers
     */
    public function getAvailableProviders(): array {
        $providers = [];
        
        // Check OpenAI (config or environment variable)
        $openaiKey = $this->config['api_keys']['openai'] ?? getenv('OPENAI_API_KEY') ?? '';
        if (!empty($openaiKey)) {
            $providers[] = 'openai';
        }
        
        // Check Grok (config or environment variable)
        $grokKey = $this->config['api_keys']['grok'] ?? getenv('GROK_API_KEY') ?? '';
        if (!empty($grokKey)) {
            $providers[] = 'grok';
        }
        
        return $providers;
    }
}

