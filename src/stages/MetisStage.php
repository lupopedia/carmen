<?php
/**
 * METIS Stage - Empathy Through Comparison
 * 
 * @package CARMEN
 * @version 0.1.6
 * 
 * WHO: METIS Stage (Stage 3 of CARMEN)
 * WHAT: Runs EMPATHY through comparison - what SHOULD be vs what IS
 * WHEN: 2025-12-06
 * WHY: Third stage - understand the gap between ideal and reality (empathy, not sympathy)
 */

require_once __DIR__ . '/StageInterface.php';
require_once __DIR__ . '/../llm/LlmClientInterface.php';

class MetisStage implements StageInterface {
    private string $name = 'METIS';
    private int $priority = 3;
    private bool $enabled = true;
    private ?int $fallbackAgentId = 3;
    private array $dependencies = ['ERIS'];
 // Can also use AGAPE for context
    private array $config = [];
    private ?LlmClientInterface $llmClient = null;
    private string $promptTemplate = '';
    
    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null) {
        $this->config = array_merge([
            'gap_analysis_depth' => 'detailed',
            'include_bridging' => true,
            'focus' => ['ideal_vs_reality', 'knowledge_gaps', 'constraint_understanding'],
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
        $promptPath = __DIR__ . '/../../config/prompts/metis_prompt.txt';
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
You are the METIS stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Run EMPATHY through comparison - what SHOULD be vs what IS.

CRITICAL: Not sympathy ("I feel bad for you") but empathy ("I understand the gap between ideal and reality").

Analyze:
- What is the IDEAL state (what SHOULD be)?
- What is the CURRENT state (what IS)?
- What is the GAP between them?
- What is causing the gap (knowledge, resources, constraints)?
- What would BRIDGE the gap?

Return ONLY valid JSON matching this exact schema:

{
  "ideal_state": "Description of ideal/expected state",
  "current_state": "Description of actual current state",
  "gap_identified": {
    "knowledge_gap": "What knowledge is missing?",
    "communication_gap": "Where did communication fail?",
    "solution_gap": "What solution doesn't fit?"
  },
  "empathy_analysis": {
    "user_experience": "What is the user experiencing?",
    "why_experiencing": "Why are they experiencing this?",
    "user_needs": "What does the user actually need?",
    "bridge": "What would bridge the gap?"
  },
  "understanding_score": 0.0-1.0
}

User Message: {message}

Previous Stage Outputs: {previous_stages}

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
                // Fallback to rule-based analysis if LLM not available
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
            error_log("METIS LLM processing failed: " . $e->getMessage());
            return $this->processWithFallback($message, $context, $stageConfig, $startTime);
        }
    }
    
    /**
     * Process with fallback (rule-based or mock)
     */
    private function processWithFallback(string $message, array $context, array $stageConfig, float $startTime): StageResult {
        // Extract ERIS and AGAPE outputs if available
        $erisOutput = $context['ERIS'] ?? null;
        $agapeOutput = $context['AGAPE'] ?? null;
        
        $output = [
            'ideal_state' => $this->identifyIdealState($message, $erisOutput, $agapeOutput),
            'current_state' => $this->identifyCurrentState($message, $erisOutput),
            'gap_identified' => $this->identifyGaps($message, $erisOutput, $agapeOutput),
            'empathy_analysis' => $this->analyzeEmpathy($message, $erisOutput, $agapeOutput),
            'understanding_score' => $this->calculateUnderstandingScore($message, $erisOutput, $agapeOutput),
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
You are the METIS stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Run EMPATHY through comparison - what SHOULD be vs what IS.

CRITICAL: Not sympathy ("I feel bad for you") but empathy ("I understand the gap between ideal and reality").

Analyze:
- What is the IDEAL state (what SHOULD be)?
- What is the CURRENT state (what IS)?
- What is the GAP between them?
- What is causing the gap (knowledge, resources, constraints)?
- What would BRIDGE the gap?

Return ONLY valid JSON matching this exact schema (no markdown, no code blocks, just pure JSON):

{
  "ideal_state": "Description of ideal/expected state",
  "current_state": "Description of actual current state",
  "gap_identified": {
    "knowledge_gap": "What knowledge is missing?",
    "communication_gap": "Where did communication fail?",
    "solution_gap": "What solution doesn't fit?"
  },
  "empathy_analysis": {
    "user_experience": "What is the user experiencing?",
    "why_experiencing": "Why are they experiencing this?",
    "user_needs": "What does the user actually need?",
    "bridge": "What would bridge the gap?"
  },
  "understanding_score": 0.85
}
SYSTEM;
    }
    
    /**
     * Build user prompt from template
     */
    private function buildPrompt(string $message, array $context): string {
        // Extract ERIS and AGAPE outputs if available
        $erisOutput = $context['ERIS'] ?? null;
        $agapeOutput = $context['AGAPE'] ?? null;
        
        $previousStages = [];
        if ($erisOutput) {
            $previousStages['ERIS'] = $erisOutput;
        }
        if ($agapeOutput) {
            $previousStages['AGAPE'] = $agapeOutput;
        }
        
        $contextStr = '';
        if (!empty($context)) {
            // Remove ERIS and AGAPE from context (already in previous_stages)
            $contextWithoutStages = $context;
            unset($contextWithoutStages['ERIS'], $contextWithoutStages['AGAPE']);
            if (!empty($contextWithoutStages)) {
                $contextStr = json_encode($contextWithoutStages, JSON_PRETTY_PRINT);
            }
        }
        
        $previousStagesStr = json_encode($previousStages, JSON_PRETTY_PRINT);
        
        return "User Message: " . $message . "\n\nPrevious Stage Outputs: " . $previousStagesStr . "\n\nContext: " . $contextStr;
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
        $required = ['ideal_state', 'current_state', 'gap_identified', 'empathy_analysis', 'understanding_score'];
        foreach ($required as $field) {
            if (!isset($output[$field])) {
                throw new \Exception("Missing required field in METIS output: $field");
            }
        }
        
        // Validate types
        if (!is_string($output['ideal_state'])) {
            throw new \Exception("ideal_state must be a string");
        }
        
        if (!is_string($output['current_state'])) {
            throw new \Exception("current_state must be a string");
        }
        
        if (!is_array($output['gap_identified'])) {
            throw new \Exception("gap_identified must be an object/array");
        }
        
        if (!is_array($output['empathy_analysis'])) {
            throw new \Exception("empathy_analysis must be an object/array");
        }
        
        if (!is_numeric($output['understanding_score']) || $output['understanding_score'] < 0 || $output['understanding_score'] > 1) {
            throw new \Exception("understanding_score must be a number between 0 and 1");
        }
        
        // Validate nested structures
        $requiredGaps = ['knowledge_gap', 'communication_gap', 'solution_gap'];
        foreach ($requiredGaps as $gap) {
            if (!isset($output['gap_identified'][$gap])) {
                $output['gap_identified'][$gap] = '';
            }
        }
        
        $requiredEmpathy = ['user_experience', 'why_experiencing', 'user_needs', 'bridge'];
        foreach ($requiredEmpathy as $field) {
            if (!isset($output['empathy_analysis'][$field])) {
                $output['empathy_analysis'][$field] = '';
            }
        }
    }
    
    /**
     * Identify ideal state from message and context
     */
    private function identifyIdealState(string $message, ?array $erisOutput, ?array $agapeOutput): string {
        // Use ERIS root cause to infer ideal state
        if ($erisOutput && isset($erisOutput['root_cause'])) {
            $rootCause = $erisOutput['root_cause'];
            if (stripos($rootCause, 'knowledge') !== false) {
                return "Ideal: System has complete knowledge before making suggestions";
            }
            if (stripos($rootCause, 'constraint') !== false) {
                return "Ideal: Constraints identified and respected before proposing solutions";
            }
            if (stripos($rootCause, 'communication') !== false) {
                return "Ideal: Clear communication with mutual understanding";
            }
        }
        
        // Check message for expectations
        if (preg_match('/should|expected|supposed to|ought to|want/i', $message)) {
            return "Ideal: User expectations met with appropriate solutions";
        }
        
        return "Ideal: Situation resolved with user's needs met";
    }
    
    /**
     * Identify current state from message and context
     */
    private function identifyCurrentState(string $message, ?array $erisOutput): string {
        // Use ERIS analysis if available
        if ($erisOutput && isset($erisOutput['root_cause'])) {
            return "Current: " . $erisOutput['root_cause'];
        }
        
        // Extract current state from message
        if (preg_match('/frustrat|angry|upset|problem|issue/i', $message)) {
            return "Current: User experiencing frustration or dissatisfaction";
        }
        
        return "Current: Situation as described by user in message";
    }
    
    /**
     * Identify gaps between ideal and current
     */
    private function identifyGaps(string $message, ?array $erisOutput, ?array $agapeOutput): array {
        $gaps = [
            'knowledge_gap' => '',
            'communication_gap' => '',
            'solution_gap' => ''
        ];
        
        // Use ERIS root cause type to identify gaps
        if ($erisOutput && isset($erisOutput['root_cause_type'])) {
            $rootCauseType = $erisOutput['root_cause_type'];
            
            switch ($rootCauseType) {
                case 'knowledge_gap':
                    $gaps['knowledge_gap'] = $erisOutput['root_cause'] ?? 'Missing information or understanding';
                    break;
                case 'communication':
                case 'misunderstanding':
                    $gaps['communication_gap'] = $erisOutput['root_cause'] ?? 'Communication or interpretation failure';
                    break;
                case 'constraint':
                    $gaps['solution_gap'] = $erisOutput['root_cause'] ?? 'Solution doesn't fit constraints';
                    break;
            }
        }
        
        // Use AGAPE output for additional context
        if ($agapeOutput && isset($agapeOutput['loving_actions'])) {
            foreach ($agapeOutput['loving_actions'] as $action) {
                if (stripos($action, 'constraint') !== false && empty($gaps['solution_gap'])) {
                    $gaps['solution_gap'] = "Solution doesn't account for constraints: " . $action;
                }
                if (stripos($action, 'understand') !== false && empty($gaps['knowledge_gap'])) {
                    $gaps['knowledge_gap'] = "Missing understanding: " . $action;
                }
            }
        }
        
        // Fill in any missing gaps with defaults
        if (empty($gaps['knowledge_gap'])) {
            $gaps['knowledge_gap'] = "Potential knowledge gap requiring investigation";
        }
        if (empty($gaps['communication_gap'])) {
            $gaps['communication_gap'] = "Potential communication gap requiring clarification";
        }
        if (empty($gaps['solution_gap'])) {
            $gaps['solution_gap'] = "Potential mismatch between solution and actual needs";
        }
        
        return $gaps;
    }
    
    /**
     * Analyze empathy - understand user experience
     */
    private function analyzeEmpathy(string $message, ?array $erisOutput, ?array $agapeOutput): array {
        $empathy = [
            'user_experience' => '',
            'why_experiencing' => '',
            'user_needs' => '',
            'bridge' => ''
        ];
        
        // Identify user experience from message
        if (preg_match('/frustrat/i', $message)) {
            $empathy['user_experience'] = "User experiencing frustration";
        } elseif (preg_match('/angry|mad/i', $message)) {
            $empathy['user_experience'] = "User experiencing anger or irritation";
        } elseif (preg_match('/confus/i', $message)) {
            $empathy['user_experience'] = "User experiencing confusion";
        } else {
            $empathy['user_experience'] = "User experiencing dissatisfaction or unmet needs";
        }
        
        // Use ERIS to understand why
        if ($erisOutput && isset($erisOutput['root_cause'])) {
            $empathy['why_experiencing'] = "Because: " . $erisOutput['root_cause'];
        } else {
            $empathy['why_experiencing'] = "Due to gap between expectation and reality";
        }
        
        // Identify user needs
        if (preg_match('/need|want|require/i', $message)) {
            // Extract what they need (simple pattern)
            $needPattern = '/(?:need|want|require)[\s]+(?:a|an|the|to)?[\s]*([^.!?]+)/i';
            if (preg_match($needPattern, $message, $matches)) {
                $empathy['user_needs'] = trim($matches[1]);
            } else {
                $empathy['user_needs'] = "Solution that works within their constraints";
            }
        } else {
            $empathy['user_needs'] = "Solution that addresses root cause and fits their situation";
        }
        
        // Suggest bridge
        if ($erisOutput && isset($erisOutput['prevention_strategy'])) {
            $empathy['bridge'] = $erisOutput['prevention_strategy'];
        } elseif ($agapeOutput && isset($agapeOutput['loving_actions'])) {
            $empathy['bridge'] = "Bridge: " . implode(', ', array_slice($agapeOutput['loving_actions'], 0, 2));
        } else {
            $empathy['bridge'] = "Bridge: Address root cause and provide working solution within constraints";
        }
        
        return $empathy;
    }
    
    /**
     * Calculate understanding score
     */
    private function calculateUnderstandingScore(string $message, ?array $erisOutput, ?array $agapeOutput): float {
        $score = 0.5; // Base score
        
        // Increase score if we have ERIS output (root cause identified)
        if ($erisOutput && isset($erisOutput['root_cause'])) {
            $score += 0.2;
        }
        
        // Increase score if we have AGAPE output (loving actions identified)
        if ($agapeOutput && isset($agapeOutput['love_score'])) {
            $score += min(0.2, $agapeOutput['love_score'] * 0.2);
        }
        
        // Increase score if we have both
        if ($erisOutput && $agapeOutput) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
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
        $validDepths = ['basic', 'detailed', 'comprehensive'];
        if (isset($config['gap_analysis_depth']) && !in_array($config['gap_analysis_depth'], $validDepths)) {
            throw new \InvalidArgumentException("METIS: gap_analysis_depth must be one of: " . implode(', ', $validDepths));
        }
        
        return true;
    }
}

