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
require_once __DIR__ . '/llm/LlmClientInterface.php';
require_once __DIR__ . '/llm/LlmClientFactory.php';
require_once __DIR__ . '/synthesis/ResponseSynthesizer.php';
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
            
            // Process each stage sequentially
            foreach ($stages as $stage) {
                $stageStartTime = microtime(true);
                
                try {
                    // Check dependencies (skip if dependency failed and stage is not required)
                    if (!$this->dependenciesMet($stage, $stageResults)) {
                        $isRequired = $this->config['stages'][$stage->getName()]['required'] ?? false;
                        if ($isRequired) {
                            throw new \Exception("Required stage dependencies not met: " . $stage->getName());
                        } else {
                            // Optional stage - skip it
                            $this->processingLog['skipped_stages'][] = [
                                'stage' => $stage->getName(),
                                'reason' => 'dependencies_not_met'
                            ];
                            continue;
                        }
                    }
                    
                    // Process stage
                    $result = $stage->process($message, array_merge($context, $previousStageOutputs), $this->config['stages'][$stage->getName()]['config'] ?? []);
                    
                    // Track result
                    $stageResults[$stage->getName()] = $result;
                    if ($result->success && $result->output) {
                        $previousStageOutputs[$stage->getName()] = $result->output;
                    }
                    
                    // Log stage timing
                    $this->processingLog['stage_timings'][$stage->getName()] = 
                        (int)((microtime(true) - $stageStartTime) * 1000);
                    
                    // Handle failures
                    if (!$result->success) {
                        $this->handleStageFailure($stage, $result, $options);
                    }
                    
                } catch (\Exception $e) {
                    // Log stage failure
                    $stageResults[$stage->getName()] = StageResult::failure(
                        $stage->getName(),
                        $e->getMessage(),
                        (int)((microtime(true) - $stageStartTime) * 1000)
                    );
                    $this->processingLog['errors'][] = [
                        'stage' => $stage->getName(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Synthesize unified response using intelligent synthesizer
            $unifiedResponse = $this->synthesizer->synthesize($stageResults, $message, $context);
            
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
     * Check if stage dependencies are met
     */
    private function dependenciesMet(StageInterface $stage, array $stageResults): bool {
        $dependencies = $stage->getDependencies();
        foreach ($dependencies as $dep) {
            if (!isset($stageResults[$dep]) || !$stageResults[$dep]->success) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Handle stage failure (fallback logic)
     */
    private function handleStageFailure(StageInterface $stage, StageResult $result, array $options): void {
        if (!$this->config['processing']['enable_fallback']) {
            return;
        }
        
        $fallbackAgentId = $stage->getFallbackAgentId();
        if ($fallbackAgentId) {
            // TODO: Implement fallback to individual agent
            $this->processingLog['fallback_used'] = true;
            $this->processingLog['fallback_stages'][] = [
                'stage' => $stage->getName(),
                'fallback_agent_id' => $fallbackAgentId
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
        // Initialize AGAPE
        $this->stages['AGAPE'] = new AgapeStage($this->config['stages']['AGAPE']['config'] ?? []);
        
        // TODO: Initialize other stages as they're implemented
        // $this->stages['ERIS'] = new ErisStage(...);
        // $this->stages['METIS'] = new MetisStage(...);
        // etc.
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

