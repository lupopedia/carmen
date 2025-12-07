<?php
/**
 * CARMEN Processor - Main Processing Logic
 * 
 * @package CARMEN
 * @version 0.1.1
 * 
 * WHO: CARMEN Processor
 * WHAT: Processes messages through CARMEN's unified workflow
 * WHEN: 2025-12-06
 * WHY: Handle actual message processing via LLM API calls
 */

// Set up paths
$carmenRoot = dirname(dirname(dirname(dirname(__FILE__))));
$configPath = $carmenRoot . '/config/carmen.yaml';
$srcPath = $carmenRoot . '/src';

// Load YAML config (simple parsing for now)
function loadCARMENConfig($path) {
    if (!file_exists($path)) {
        return getDefaultConfig();
    }
    
    // Simple YAML parsing (for production, use proper YAML parser)
    $content = file_get_contents($path);
    
    // Extract LLM provider from config
    $config = getDefaultConfig();
    if (preg_match('/default_provider:\s*"([^"]+)"/', $content, $matches)) {
        $config['llm']['default_provider'] = $matches[1];
    }
    
    // Extract API keys (from environment or config)
    $config['llm']['api_keys']['openai'] = getenv('OPENAI_API_KEY') ?: '';
    $config['llm']['api_keys']['grok'] = getenv('GROK_API_KEY') ?: '';
    
    return $config;
}

function getDefaultConfig() {
    return [
        'llm' => [
            'default_provider' => 'grok',
            'api_keys' => [
                'openai' => getenv('OPENAI_API_KEY') ?: '',
                'grok' => getenv('GROK_API_KEY') ?: ''
            ],
            'models' => [
                'openai' => 'gpt-4',
                'grok' => 'grok-beta'
            ],
            'defaults' => [
                'temperature' => 0.3,
                'max_tokens' => 1000,
                'timeout' => 30
            ]
        ],
        'stages' => [
            'AGAPE' => [
                'enabled' => true,
                'required' => true,
                'llm_model' => 'grok-beta',
                'temperature' => 0.3,
                'max_tokens' => 1000
            ],
            'ERIS' => [
                'enabled' => true,
                'required' => true,
                'llm_model' => 'grok-beta',
                'temperature' => 0.4,
                'max_tokens' => 1000
            ],
            'METIS' => [
                'enabled' => true,
                'required' => true,
                'llm_model' => 'grok-beta',
                'temperature' => 0.3,
                'max_tokens' => 1000
            ]
        ]
    ];
}

// Load CARMEN agent classes
require_once $srcPath . '/stages/StageInterface.php'; // Also loads StageResult
require_once $srcPath . '/llm/LlmClientInterface.php';
require_once $srcPath . '/llm/LlmClientFactory.php';
require_once $srcPath . '/stages/AgapeStage.php';
require_once $srcPath . '/synthesis/ResponseSynthesizer.php';
require_once $srcPath . '/CarmenAgent.php';

/**
 * Process message through CARMEN
 */
function processCARMENMessage($message, $context = []) {
    global $configPath;
    
    try {
        $config = loadCARMENConfig($configPath);
        
        // Initialize CARMEN agent
        $carmen = new CarmenAgent($config);
        
        // Process message
        $result = $carmen->process($message, $context);
        
        return $result;
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

/**
 * Check LLM provider status
 */
function checkLLMStatus() {
    global $configPath;
    
    try {
        $config = loadCARMENConfig($configPath);
        $factory = new LlmClientFactory($config['llm']);
        
        $provider = $factory->getDefaultProvider();
        $availableProviders = $factory->getAvailableProviders();
        
        $grokAvailable = in_array('grok', $availableProviders);
        
        return [
            'llm_provider' => $provider,
            'available_providers' => $availableProviders,
            'grok_available' => $grokAvailable
        ];
        
    } catch (\Exception $e) {
        return [
            'llm_provider' => 'unknown',
            'error' => $e->getMessage()
        ];
    }
}

