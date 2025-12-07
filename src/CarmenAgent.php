<?php
/**
 * CARMEN Unified Emotional Intelligence Agent
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: CARMEN Main Agent Class
 * WHAT: Orchestrates 5-stage unified emotional intelligence processing
 * WHEN: 2025-12-02
 * WHY: Single-pass processing instead of chaining 6-7 agents
 */

require_once __DIR__ . '/stages/StageInterface.php';
require_once __DIR__ . '/stages/AgapeStage.php';
require_once __DIR__ . '/stages/ErisStage.php';
require_once __DIR__ . '/stages/MetisStage.php';
require_once __DIR__ . '/llm/LlmClientInterface.php';
require_once __DIR__ . '/llm/LlmClientFactory.php';
require_once __DIR__ . '/synthesis/ResponseSynthesizer.php';
require_once __DIR__ . '/fallback/FallbackDataProvider.php';
// TODO: Add other stage requires as they're implemented

class CarmenAgent {
    private array $config;
    private array $stages = [];
    private ?\PDO $db = null;
    private array $processingLog = [];
    private ?LlmClientFactory $llmFactory = null;
    private ?ResponseSynthesizer $synthesizer = null;
    
    public function __construct(array $config = []) {
        $this->config = $this->loadConfig($config);
        $this->llmFactory = new LlmClientFactory($this->config['llm'] ?? []);
        $this->synthesizer = new ResponseSynthesizer($this->config['output'] ?? []);
        $this->initializeStages();
    }
    
    /**
     * Process message through all enabled stages
     * 
     * @param string $message User message
     * @param array $context Additional context
     * @param array $options Processing options
     * @return array Unified response with stage outputs
     */
    public function process(string $message, array $context = [], array $options = []): array {
        $startTime = microtime(true);
        $sessionId = $options['session_id'] ?? uniqid('carmen_', true);
        
        try {
            // Sort stages by priority
            $stages = $this->getEnabledStagesSorted();
            $stageResults = [];
            $previousStageOutputs = [];
            
            // Process each stage sequentially with soft dependencies
            foreach ($stages as $stage) {
                $stageStartTime = microtime(true);
                $stageName = $stage->getName();
                
                try {
                    // Get context for this stage (with soft dependency fallback)
                    $stageContext = $this->getContextForStage($stage, $message, $context, $previousStageOutputs, $stageResults);
                    
                    // Process stage with context (may include fallback data)
                    $result = $stage->process($message, $stageContext, $this->config['stages'][$stageName]['config'] ?? []);
                    
                    // If stage failed, use fallback data (soft dependency approach)
                    if (!$result->success) {
                        $this->processingLog['errors'][] = [
                            'stage' => $stageName,
                            'error' => $result->error ?? 'Stage processing failed',
                            'fallback_used' => true
                        ];
                        
                        // Get fallback data for this stage
                        $fallbackOutput = $this->getFallbackForStage($stageName, $message, $stageContext);
                        
                        // Create success result with fallback data
                        $result = StageResult::success(
                            $stageName,
                            $fallbackOutput,
                            (int)((microtime(true) - $stageStartTime) * 1000),
                            0, // No tokens used for fallback
                            ['fallback_used' => true, 'original_error' => $result->error ?? 'Unknown error']
                        );
                    }
                    
                    // Track result (success or fallback)
                    $stageResults[$stageName] = $result;
                    if ($result->output) {
                        $previousStageOutputs[$stageName] = $result->output;
                    }
                    
                    // Log stage timing
                    $this->processingLog['stage_timings'][$stageName] = 
                        (int)((microtime(true) - $stageStartTime) * 1000);
                    
                } catch (\Exception $e) {
                    // Critical exception - use fallback
                    $this->processingLog['errors'][] = [
                        'stage' => $stageName,
                        'error' => $e->getMessage(),
                        'exception' => true,
                        'fallback_used' => true
                    ];
                    
                    // Get fallback context
                    $fallbackContext = $this->getContextForStage($stage, $message, $context, $previousStageOutputs, $stageResults);
                    
                    // Create fallback result
                    $fallbackOutput = $this->getFallbackForStage($stageName, $message, $fallbackContext);
                    $stageResults[$stageName] = StageResult::success(
                        $stageName,
                        $fallbackOutput,
                        (int)((microtime(true) - $stageStartTime) * 1000),
                        0,
                        ['fallback_used' => true, 'exception' => $e->getMessage()]
                    );
                    
                    // Add to previous outputs for next stages
                    if ($fallbackOutput) {
                        $previousStageOutputs[$stageName] = $fallbackOutput;
                    }
                }
            }
            
            // Synthesize unified response using intelligent synthesizer
            // Convert StageResult objects to array format for synthesis
            $stageResultsArray = [];
            foreach ($stageResults as $stageName => $result) {
                $stageResultsArray[$stageName] = $result;
            }
            $unifiedResponse = $this->synthesizer->synthesize($stageResultsArray, $message, $context);
            
            // Calculate metrics
            $totalTimeMs = (int)((microtime(true) - $startTime) * 1000);
            $totalTokens = array_sum(array_column(array_map(fn($r) => $r->toArray(), $stageResults), 'tokens_used'));
            $successFlags = $this->generateSuccessFlags($stageResults);
            
            // Log to database if enabled
            if ($this->config['logging']['log_to_database']) {
                $this->logToDatabase($sessionId, $message, $stageResults, $unifiedResponse, $totalTimeMs, $totalTokens, $successFlags, $context);
            }
            
            // Prepare return value
            return [
                'success' => true,
                'unified_response' => $unifiedResponse,
                'processing_time_ms' => $totalTimeMs,
                'token_usage' => $totalTokens,
                'stages_executed' => array_keys($stageResults),
                'stage_results' => array_map(fn($r) => $r->toArray(), $stageResults),
                'session_id' => $sessionId,
                'metadata' => [
                    'config_used' => $this->config,
                    'stage_timings' => $this->processingLog['stage_timings'] ?? [],
                    'errors' => $this->processingLog['errors'] ?? []
                ]
            ];
            
        } catch (\Exception $e) {
            // Critical failure - log and return error
            $totalTimeMs = (int)((microtime(true) - $startTime) * 1000);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time_ms' => $totalTimeMs,
                'session_id' => $sessionId
            ];
        }
    }
    
    /**
     * Get context for stage with soft dependency support
     * If dependencies failed, provides fallback context extracted from message
     * 
     * @param StageInterface $stage Stage to get context for
     * @param string $message User message
     * @param array $baseContext Base context
     * @param array $previousStageOutputs Previous stage outputs
     * @param array $stageResults All stage results (including failures)
     * @return array Context array for stage processing
     */
    private function getContextForStage(
        StageInterface $stage, 
        string $message, 
        array $baseContext, 
        array $previousStageOutputs, 
        array $stageResults
    ): array {
        $stageName = $stage->getName();
        $dependencies = $stage->getDependencies();
        $context = array_merge($baseContext, $previousStageOutputs);
        
        // For each dependency, check if we have valid output
        foreach ($dependencies as $depName) {
            if (isset($stageResults[$depName]) && $stageResults[$depName]->success && $stageResults[$depName]->output) {
                // Dependency succeeded - use its output
                $context[$depName] = $stageResults[$depName]->output;
            } elseif (isset($previousStageOutputs[$depName])) {
                // Previous output exists (may be fallback) - use it
                $context[$depName] = $previousStageOutputs[$depName];
            } else {
                // Dependency missing or failed - provide fallback context
                // Summarize what we can from the message
                $context['_fallback_' . $depName] = FallbackDataProvider::extractBasicContext($message);
                
                // If we have partial data from failed dependency, include it
                if (isset($stageResults[$depName]) && $stageResults[$depName]->output) {
                    $context['_partial_' . $depName] = $stageResults[$depName]->output;
                }
            }
        }
        
        // Add basic context extraction if no dependencies
        if (empty($dependencies)) {
            $context['_basic_context'] = FallbackDataProvider::extractBasicContext($message);
        }
        
        return $context;
    }
    
    /**
     * Get fallback data for a stage when it fails
     * 
     * @param string $stageName Stage name
     * @param string $message User message
     * @param array $context Available context
     * @return array Fallback output data
     */
    private function getFallbackForStage(string $stageName, string $message, array $context): array {
        // Extract any previous stage outputs from context
        $previousOutputs = [];
        foreach (['AGAPE', 'ERIS', 'METIS', 'THALIA_ROSE', 'THOTH'] as $prevStage) {
            if (isset($context[$prevStage]) && is_array($context[$prevStage])) {
                $previousOutputs[$prevStage] = $context[$prevStage];
            }
        }
        
        // Get stage-specific fallback
        switch ($stageName) {
            case 'AGAPE':
                return FallbackDataProvider::getAgapeFallback($message, $context);
                
            case 'ERIS':
                return FallbackDataProvider::getErisFallback($message, $context);
                
            case 'METIS':
                return FallbackDataProvider::getMetisFallback($message, $context);
                
            case 'THALIA_ROSE':
                return FallbackDataProvider::getThaliaRoseFallback($message, $context);
                
            case 'THOTH':
                return FallbackDataProvider::getThothFallback($message, $context);
                
            default:
                // Generic fallback
                return [
                    'fallback_used' => true,
                    'message' => 'Stage processing unavailable - using fallback data',
                    'extracted_context' => FallbackDataProvider::extractBasicContext($message)
                ];
        }
    }
    
    /**
     * Handle stage failure (legacy method - now handled in process loop)
     * Kept for backward compatibility and external fallback agent calls
     */
    private function handleStageFailure(StageInterface $stage, StageResult $result, array $options): void {
        if (!$this->config['processing']['enable_fallback']) {
            return;
        }
        
        $fallbackAgentId = $stage->getFallbackAgentId();
        if ($fallbackAgentId) {
            // Log that fallback agent is available (but we're using internal fallback first)
            $this->processingLog['fallback_agent_available'][] = [
                'stage' => $stage->getName(),
                'fallback_agent_id' => $fallbackAgentId,
                'note' => 'Internal fallback used instead of external agent call'
            ];
        }
    }
    
    /**
     * Get LLM factory for stage use
     */
    public function getLlmFactory(): LlmClientFactory {
        return $this->llmFactory;
    }
    
    /**
     * Generate success flags string (e.g., "11111" for all successful)
     */
    private function generateSuccessFlags(array $stageResults): string {
        $flags = '';
        $orderedStages = ['AGAPE', 'ERIS', 'METIS', 'THALIA_ROSE', 'THOTH'];
        foreach ($orderedStages as $stageName) {
            if (isset($stageResults[$stageName])) {
                $flags .= $stageResults[$stageName]->success ? '1' : '0';
            } else {
                $flags .= '0';
            }
        }
        return $flags;
    }
    
    /**
     * Log processing to database
     */
    private function logToDatabase(
        string $sessionId,
        string $message,
        array $stageResults,
        string $unifiedResponse,
        int $processingTimeMs,
        int $totalTokens,
        string $successFlags,
        array $context
    ): void {
        if (!$this->db) {
            return; // Database not configured
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO carmen_processing_logs (
                    session_id, user_message, stage_outputs, final_response,
                    processing_time_ms, stage_success_flags, token_usage,
                    stage_timings, errors, fallback_used, user_context
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stageOutputsJson = json_encode(array_map(fn($r) => $r->toArray(), $stageResults));
            $stageTimingsJson = json_encode($this->processingLog['stage_timings'] ?? []);
            $errorsJson = json_encode($this->processingLog['errors'] ?? []);
            $contextJson = json_encode($context);
            $fallbackUsed = isset($this->processingLog['fallback_used']) && $this->processingLog['fallback_used'] ? 1 : 0;
            
            $stmt->execute([
                $sessionId,
                $message,
                $stageOutputsJson,
                $unifiedResponse,
                $processingTimeMs,
                $successFlags,
                $totalTokens,
                $stageTimingsJson,
                $errorsJson,
                $fallbackUsed,
                $contextJson
            ]);
        } catch (\Exception $e) {
            error_log("CARMEN: Failed to log to database: " . $e->getMessage());
        }
    }
    
    /**
     * Load configuration (from YAML or array)
     */
    private function loadConfig(array $config): array {
        // TODO: Load from YAML file if path provided
        // For now, return default config structure
        return array_merge([
            'processing' => [
                'mode' => 'sequential',
                'max_processing_time' => 30000,
                'enable_fallback' => true,
                'fallback_mode' => 'hybrid'
            ],
            'stages' => [],
            'logging' => [
                'log_to_database' => false,
                'level' => 'info'
            ]
        ], $config);
    }
    
    /**
     * Initialize stage objects
     */
    private function initializeStages(): void {
        // Get LLM client for stages
        // Check both nested and flat config structure
        $defaultProvider = $this->config['llm']['default_provider'] ?? 
                          ($this->config['default_provider'] ?? 'openai');
        $llmClient = null;
        
        try {
            $llmClient = $this->llmFactory->create($defaultProvider);
        } catch (\Exception $e) {
            error_log("CARMEN: Failed to create LLM client: " . $e->getMessage() . " - Stages will use fallback mode");
        }
        
        // Initialize AGAPE with LLM client
        $agapeStageConfig = $this->config['stages']['AGAPE'] ?? [];
        $agapeConfig = $agapeStageConfig['config'] ?? [];
        
        // Get LLM settings (stage-specific or default)
        $stageProvider = $agapeStageConfig['llm_provider'] ?? $defaultProvider;
        $stageModel = $agapeStageConfig['llm_model'] ?? ($this->config['llm']['models'][$defaultProvider] ?? 'gpt-4');
        $agapeConfig['llm_provider'] = $stageProvider;
        $agapeConfig['llm_model'] = $stageModel;
        $agapeConfig['temperature'] = $agapeStageConfig['temperature'] ?? 0.3;
        $agapeConfig['max_tokens'] = $agapeStageConfig['max_tokens'] ?? 1000;
        
        // Create stage-specific LLM client
        $stageLlmClient = $llmClient;
        if ($llmClient !== null && $stageProvider !== $defaultProvider) {
            try {
                $stageLlmClient = $this->llmFactory->create($stageProvider);
            } catch (\Exception $e) {
                error_log("CARMEN: Failed to create stage-specific LLM client for AGAPE: " . $e->getMessage() . " - Using default client");
            }
        }
        
        $this->stages['AGAPE'] = new AgapeStage($agapeConfig, $stageLlmClient);
        
        // Initialize ERIS with LLM client
        $erisStageConfig = $this->config['stages']['ERIS'] ?? [];
        $erisConfig = $erisStageConfig['config'] ?? [];
        
        // Get LLM settings (stage-specific or default)
        $erisProvider = $erisStageConfig['llm_provider'] ?? $defaultProvider;
        $erisModel = $erisStageConfig['llm_model'] ?? ($this->config['llm']['models'][$defaultProvider] ?? 'gpt-4');
        $erisConfig['llm_provider'] = $erisProvider;
        $erisConfig['llm_model'] = $erisModel;
        $erisConfig['temperature'] = $erisStageConfig['temperature'] ?? 0.4;
        $erisConfig['max_tokens'] = $erisStageConfig['max_tokens'] ?? 1000;
        
        // Create stage-specific LLM client
        $erisLlmClient = $llmClient;
        if ($llmClient !== null && $erisProvider !== $defaultProvider) {
            try {
                $erisLlmClient = $this->llmFactory->create($erisProvider);
            } catch (\Exception $e) {
                error_log("CARMEN: Failed to create stage-specific LLM client for ERIS: " . $e->getMessage() . " - Using default client");
            }
        }
        
        $this->stages['ERIS'] = new ErisStage($erisConfig, $erisLlmClient);
        
        // Initialize METIS with LLM client
        $metisStageConfig = $this->config['stages']['METIS'] ?? [];
        $metisConfig = $metisStageConfig['config'] ?? [];
        
        // Get LLM settings (stage-specific or default)
        $metisProvider = $metisStageConfig['llm_provider'] ?? $defaultProvider;
        $metisModel = $metisStageConfig['llm_model'] ?? ($this->config['llm']['models'][$defaultProvider] ?? 'gpt-4');
        $metisConfig['llm_provider'] = $metisProvider;
        $metisConfig['llm_model'] = $metisModel;
        $metisConfig['temperature'] = $metisStageConfig['temperature'] ?? 0.3;
        $metisConfig['max_tokens'] = $metisStageConfig['max_tokens'] ?? 1000;
        
        // Create stage-specific LLM client
        $metisLlmClient = $llmClient;
        if ($llmClient !== null && $metisProvider !== $defaultProvider) {
            try {
                $metisLlmClient = $this->llmFactory->create($metisProvider);
            } catch (\Exception $e) {
                error_log("CARMEN: Failed to create stage-specific LLM client for METIS: " . $e->getMessage() . " - Using default client");
            }
        }
        
        $this->stages['METIS'] = new MetisStage($metisConfig, $metisLlmClient);
        
        // TODO: Initialize optional stages as they're implemented
        // $this->stages['THALIA_ROSE'] = new ThaliaRoseStage(...);
        // $this->stages['THOTH'] = new ThothStage(...);
    }
    
    /**
     * Get enabled stages sorted by priority
     */
    private function getEnabledStagesSorted(): array {
        $enabled = array_filter($this->stages, fn($stage) => $stage->isEnabled());
        usort($enabled, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
        return $enabled;
    }
    
    /**
     * Check if stage is required (cannot be skipped)
     */
    private function isStageRequired(string $stageName): bool {
        return $this->config['stages'][$stageName]['required'] ?? false;
    }
    
    /**
     * Set database connection for logging
     */
    public function setDatabase(\PDO $db): void {
        $this->db = $db;
    }
}

