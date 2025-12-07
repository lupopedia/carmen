<?php
/**
 * AGAPE Stage - Love as Action Analysis
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: AGAPE Stage (Stage 1 of CARMEN)
 * WHAT: Analyzes love as ACTION (verb, not feeling)
 * WHEN: 2025-12-02
 * WHY: First stage - identify loving actions that help NOW
 */

require_once __DIR__ . '/StageInterface.php';
require_once __DIR__ . '/../llm/LlmClientInterface.php';

class AgapeStage implements StageInterface {
    private string $name = 'AGAPE';
    private int $priority = 1;
    private bool $enabled = true;
    private ?int $fallbackAgentId = 100;
    private array $dependencies = [];
    private array $config = [];
    private ?LlmClientInterface $llmClient = null;
    private string $promptTemplate = '';
    
    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null) {
        $this->config = array_merge([
            'max_actions' => 3,
            'behavioral_threshold' => 0.7,
            'focus' => ['teaching', 'helping', 'encouraging', 'patience', 'kindness', 'hope'],
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4',
            'temperature' => 0.3,
            'max_tokens' => 1000
        ], $config);
        $this->validateConfig($this->config);
        $this->llmClient = $llmClient;
        $this->loadPromptTemplate();
    }
    
    /**
     * Load prompt template from file
     */
    private function loadPromptTemplate(): void {
        $promptPath = __DIR__ . '/../../config/prompts/agape_prompt.txt';
        if (file_exists($promptPath)) {
            $this->promptTemplate = file_get_contents($promptPath);
        } else {
            // Fallback to inline template if file not found
            $this->promptTemplate = $this->getDefaultPromptTemplate();
        }
    }
    
    /**
     * Get default prompt template (fallback if file not found)
     */
    private function getDefaultPromptTemplate(): string {
        return <<<'PROMPT'
You are the AGAPE stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Analyze the user's message for LOVING ACTIONS (verbs, not feelings).

Focus on ACTIONABLE ways to help RIGHT NOW:
- What can we TEACH (not condemn)?
- What can we HELP with (working solution NOW)?
- What can we ENCOURAGE (show upgrade path)?
- Are we PATIENT (work WITH constraints, not demand perfection)?
- Are we KIND (calibrated for learning, not humiliation)?
- Are we HOPEFUL (improvement possible when ready)?

CRITICAL: Love is measured by what it DOES, not what it feels.

Return ONLY valid JSON matching this exact schema:

{
  "loving_actions": ["action1", "action2", "action3"],
  "behavioral_scores": {
    "did_it_teach": true/false,
    "did_it_help": true/false,
    "did_it_encourage": true/false,
    "patience_shown": true/false,
    "kindness_shown": true/false,
    "hope_shown": true/false,
    "flexibility_shown": true/false
  },
  "love_score": 0.0-1.0,
  "why_loving": "Brief explanation of what makes this loving"
}

User Message: {message}

Context: {context}
PROMPT;
    }
    
    public function process(string $message, array $context = [], array $config = []): StageResult {
        $startTime = microtime(true);
        $stageConfig = array_merge($this->config, $config);
        
        try {
            // If LLM client is available, use it for real analysis
            if ($this->llmClient !== null && $this->llmClient->isAvailable()) {
                return $this->processWithLLM($message, $context, $stageConfig, $startTime);
            } else {
                // Fallback to mock/rule-based analysis if LLM not available
                return $this->processWithFallback($message, $context, $stageConfig, $startTime);
            }
            
        } catch (\Exception $e) {
            $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);
            return StageResult::failure($this->name, $e->getMessage(), $processingTimeMs);
        }
    }
    
    /**
     * Process with real LLM call
     */
    private function processWithLLM(string $message, array $context, array $stageConfig, float $startTime): StageResult {
        try {
            // Build prompt from template
            $prompt = $this->buildPrompt($message, $context);
            
            // Prepare LLM options
            $llmOptions = [
                'temperature' => $stageConfig['temperature'] ?? 0.3,
                'max_tokens' => $stageConfig['max_tokens'] ?? 1000
            ];
            
            // Make LLM call
            $llmResponse = $this->llmClient->generateChat(
                $this->getSystemPrompt(),
                $prompt,
                $llmOptions
            );
            
            if (!$llmResponse->success) {
                throw new \Exception("LLM call failed: " . ($llmResponse->error ?? 'Unknown error'));
            }
            
            // Parse JSON response
            $output = $this->parseLLMResponse($llmResponse->text);
            
            // Validate output structure
            $this->validateOutput($output);
            
            $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);
            $tokensUsed = $llmResponse->tokensUsed ?? ($llmResponse->tokensPrompt ?? 0) + ($llmResponse->tokensCompletion ?? 0);
            if ($tokensUsed === 0) {
                $tokensUsed = $this->estimateTokens($llmResponse->text);
            }
            
            return StageResult::success(
                $this->name,
                $output,
                $processingTimeMs,
                $tokensUsed,
                [
                    'stage_config' => $stageConfig,
                    'llm_provider' => $this->llmClient->getProviderName(),
                    'llm_model' => $llmResponse->model ?? 'unknown',
                    'raw_response' => $llmResponse->text // For debugging
                ]
            );
            
        } catch (\Exception $e) {
            // If LLM processing fails, fall back to rule-based
            error_log("AGAPE LLM processing failed: " . $e->getMessage());
            return $this->processWithFallback($message, $context, $stageConfig, $startTime);
        }
    }
    
    /**
     * Process with fallback (rule-based or mock)
     */
    private function processWithFallback(string $message, array $context, array $stageConfig, float $startTime): StageResult {
        $output = [
            'loving_actions' => $this->identifyLovingActions($message, $context),
            'behavioral_scores' => $this->calculateBehavioralScores($message, $context),
            'love_score' => $this->calculateLoveScore($message, $context),
            'why_loving' => $this->explainLovingNature($message, $context),
            'fallback_used' => true,
            'fallback_reason' => $this->llmClient === null ? 'LLM client not provided' : 'LLM not available'
        ];
        
        $processingTimeMs = (int)((microtime(true) - $startTime) * 1000);
        $tokensUsed = $this->estimateTokens($output);
        
        return StageResult::success(
            $this->name,
            $output,
            $processingTimeMs,
            $tokensUsed,
            ['stage_config' => $stageConfig, 'fallback_mode' => true]
        );
    }
    
    /**
     * Get system prompt (instruction part)
     */
    private function getSystemPrompt(): string {
        return <<<'SYSTEM'
You are the AGAPE stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Analyze the user's message for LOVING ACTIONS (verbs, not feelings).

Focus on ACTIONABLE ways to help RIGHT NOW:
- What can we TEACH (not condemn)?
- What can we HELP with (working solution NOW)?
- What can we ENCOURAGE (show upgrade path)?
- Are we PATIENT (work WITH constraints, not demand perfection)?
- Are we KIND (calibrated for learning, not humiliation)?
- Are we HOPEFUL (improvement possible when ready)?

CRITICAL: Love is measured by what it DOES, not what it feels.

Return ONLY valid JSON matching this exact schema (no markdown, no code blocks, just pure JSON):

{
  "loving_actions": ["action1", "action2", "action3"],
  "behavioral_scores": {
    "did_it_teach": true,
    "did_it_help": true,
    "did_it_encourage": true,
    "patience_shown": true,
    "kindness_shown": true,
    "hope_shown": true,
    "flexibility_shown": true
  },
  "love_score": 0.85,
  "why_loving": "Brief explanation of what makes this loving"
}
SYSTEM;
    }
    
    /**
     * Build user prompt from template
     */
    private function buildPrompt(string $message, array $context): string {
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = json_encode($context, JSON_PRETTY_PRINT);
        }
        
        return "User Message: " . $message . "\n\nContext: " . $contextStr;
    }
    
    /**
     * Parse LLM JSON response
     */
    private function parseLLMResponse(string $responseText): array {
        // Remove markdown code blocks if present
        $cleaned = preg_replace('/```json\s*/', '', $responseText);
        $cleaned = preg_replace('/```\s*/', '', $cleaned);
        $cleaned = trim($cleaned);
        
        // Try to extract JSON object from response
        // Look for JSON object - find first { and matching }
        $startPos = strpos($cleaned, '{');
        if ($startPos !== false) {
            $depth = 0;
            $endPos = $startPos;
            for ($i = $startPos; $i < strlen($cleaned); $i++) {
                if ($cleaned[$i] === '{') {
                    $depth++;
                } elseif ($cleaned[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $endPos = $i + 1;
                        break;
                    }
                }
            }
            $cleaned = substr($cleaned, $startPos, $endPos - $startPos);
        }
        
        $decoded = json_decode($cleaned, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse LLM JSON response: " . json_last_error_msg() . "\nResponse: " . substr($responseText, 0, 500));
        }
        
        return $decoded;
    }
    
    /**
     * Validate output structure
     */
    private function validateOutput(array $output): void {
        $required = ['loving_actions', 'behavioral_scores', 'love_score', 'why_loving'];
        foreach ($required as $field) {
            if (!isset($output[$field])) {
                throw new \Exception("Missing required field in AGAPE output: $field");
            }
        }
        
        // Validate types
        if (!is_array($output['loving_actions'])) {
            throw new \Exception("loving_actions must be an array");
        }
        
        if (!is_array($output['behavioral_scores'])) {
            throw new \Exception("behavioral_scores must be an object/array");
        }
        
        if (!is_numeric($output['love_score']) || $output['love_score'] < 0 || $output['love_score'] > 1) {
            throw new \Exception("love_score must be a number between 0 and 1");
        }
        
        // Limit actions to max_actions
        if (count($output['loving_actions']) > $this->config['max_actions']) {
            $output['loving_actions'] = array_slice($output['loving_actions'], 0, $this->config['max_actions']);
        }
    }
    
    private function identifyLovingActions(string $message, array $context): array {
        // TODO: Implement actual analysis
        // For now, return example structure
        return [
            "Identify constraints before suggesting solutions",
            "Provide working solutions that fit user's situation NOW",
            "Show upgrade path without condemning current approach"
        ];
    }
    
    private function calculateBehavioralScores(string $message, array $context): array {
        // TODO: Implement actual scoring based on AGAPE behavioral markers
        return [
            'did_it_teach' => true,
            'did_it_help' => true,
            'did_it_encourage' => true,
            'patience_shown' => true,
            'kindness_shown' => true,
            'hope_shown' => true,
            'flexibility_shown' => true
        ];
    }
    
    private function calculateLoveScore(string $message, array $context): float {
        // TODO: Calculate based on behavioral scores
        // Average of behavioral markers
        return 1.0; // Placeholder
    }
    
    private function explainLovingNature(string $message, array $context): string {
        // TODO: Generate explanation based on analysis
        return "Provides working solution NOW, teaches upgrade path, works WITH constraints";
    }
    
    private function estimateTokens(array $data): int {
        // Rough estimation: count characters / 4
        return (int)(strlen(json_encode($data)) / 4);
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getPriority(): int {
        return $this->priority;
    }
    
    public function getDependencies(): array {
        return $this->dependencies;
    }
    
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    public function isRequired(): bool {
        return $this->config['required'] ?? true;
    }
    
    public function getFallbackAgentId(): ?int {
        return $this->fallbackAgentId;
    }
    
    public function validateConfig(array $config): bool {
        if (!isset($config['max_actions']) || $config['max_actions'] < 1) {
            throw new \InvalidArgumentException("AGAPE: max_actions must be >= 1");
        }
        if (!isset($config['behavioral_threshold']) || 
            $config['behavioral_threshold'] < 0 || 
            $config['behavioral_threshold'] > 1) {
            throw new \InvalidArgumentException("AGAPE: behavioral_threshold must be 0-1");
        }
        return true;
    }
}

