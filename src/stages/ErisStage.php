<?php
/**
 * ERIS Stage - Root Cause Identification
 * 
 * @package CARMEN
 * @version 0.1.6
 * 
 * WHO: ERIS Stage (Stage 2 of CARMEN)
 * WHAT: Identifies ROOT CAUSES of conflicts, discord, frustration
 * WHEN: 2025-12-06
 * WHY: Second stage - find what's CAUSING the problem, not just detect anger
 */

require_once __DIR__ . '/StageInterface.php';
require_once __DIR__ . '/../llm/LlmClientInterface.php';

class ErisStage implements StageInterface {
    private string $name = 'ERIS';
    private int $priority = 2;
    private bool $enabled = true;
    private ?int $fallbackAgentId = 82;
    private array $dependencies = ['AGAPE'];
    private array $config = [];
    private ?LlmClientInterface $llmClient = null;
    private string $promptTemplate = '';
    
    public function __construct(array $config = [], ?LlmClientInterface $llmClient = null) {
        $this->config = array_merge([
            'max_root_causes' => 2,
            'severity_threshold' => 'medium',
            'focus' => ['knowledge_gaps', 'misunderstandings', 'ambiguity', 'constraints', 'communication'],
            'llm_provider' => 'openai',
            'llm_model' => 'gpt-4',
            'temperature' => 0.4,
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
        $promptPath = __DIR__ . '/../../config/prompts/eris_prompt.txt';
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
You are the ERIS stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Find ROOT CAUSES of conflicts, discord, frustration.

CRITICAL: Not "you're angry" but "THIS is causing the anger."

Look for:
- Knowledge gaps (what's missing?)
- Misunderstandings (what's confused?)
- Ambiguity (what's unclear?)
- Environmental constraints (what limitation?)
- Communication failures (where did message fail?)

Return ONLY valid JSON matching this exact schema:

{
  "discord_detected": true/false,
  "root_cause": "What is ACTUALLY causing the problem",
  "root_cause_type": "knowledge_gap|misunderstanding|ambiguity|constraint|communication",
  "severity": "critical|high|medium|low",
  "contributing_factors": ["factor1", "factor2"],
  "pattern_identified": "Recurring pattern (if any)",
  "prevention_strategy": "How to prevent this in the future"
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
                'temperature' => $stageConfig['temperature'] ?? 0.4,
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
            error_log("ERIS LLM processing failed: " . $e->getMessage());
            return $this->processWithFallback($message, $context, $stageConfig, $startTime);
        }
    }
    
    /**
     * Process with fallback (rule-based or mock)
     */
    private function processWithFallback(string $message, array $context, array $stageConfig, float $startTime): StageResult {
        // Try to extract root cause from AGAPE output if available
        $agapeOutput = $context['AGAPE'] ?? null;
        
        $output = [
            'discord_detected' => $this->detectDiscord($message, $agapeOutput),
            'root_cause' => $this->identifyRootCause($message, $agapeOutput, $context),
            'root_cause_type' => $this->identifyRootCauseType($message),
            'severity' => $this->assessSeverity($message),
            'contributing_factors' => $this->identifyContributingFactors($message, $agapeOutput),
            'pattern_identified' => $this->identifyPattern($message, $context),
            'prevention_strategy' => $this->suggestPreventionStrategy($message, $context),
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
You are the ERIS stage of CARMEN (Unified Emotional Intelligence Agent).

Your role: Find ROOT CAUSES of conflicts, discord, frustration.

CRITICAL: Not "you're angry" but "THIS is causing the anger."

Look for:
- Knowledge gaps (what's missing?)
- Misunderstandings (what's confused?)
- Ambiguity (what's unclear?)
- Environmental constraints (what limitation?)
- Communication failures (where did message fail?)

Return ONLY valid JSON matching this exact schema (no markdown, no code blocks, just pure JSON):

{
  "discord_detected": true,
  "root_cause": "What is ACTUALLY causing the problem",
  "root_cause_type": "knowledge_gap",
  "severity": "medium",
  "contributing_factors": ["factor1", "factor2"],
  "pattern_identified": "Recurring pattern (if any)",
  "prevention_strategy": "How to prevent this in the future"
}
SYSTEM;
    }
    
    /**
     * Build user prompt from template
     */
    private function buildPrompt(string $message, array $context): string {
        // Extract AGAPE output if available
        $agapeOutput = $context['AGAPE'] ?? null;
        $previousStages = [];
        if ($agapeOutput) {
            $previousStages['AGAPE'] = $agapeOutput;
        }
        
        $contextStr = '';
        if (!empty($context)) {
            // Remove AGAPE from context (already in previous_stages)
            $contextWithoutAgape = $context;
            unset($contextWithoutAgape['AGAPE']);
            if (!empty($contextWithoutAgape)) {
                $contextStr = json_encode($contextWithoutAgape, JSON_PRETTY_PRINT);
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
        $required = ['discord_detected', 'root_cause', 'root_cause_type', 'severity'];
        foreach ($required as $field) {
            if (!isset($output[$field])) {
                throw new \Exception("Missing required field in ERIS output: $field");
            }
        }
        
        // Validate types
        if (!is_bool($output['discord_detected'])) {
            throw new \Exception("discord_detected must be a boolean");
        }
        
        if (!is_string($output['root_cause'])) {
            throw new \Exception("root_cause must be a string");
        }
        
        $validTypes = ['knowledge_gap', 'misunderstanding', 'ambiguity', 'constraint', 'communication'];
        if (!in_array($output['root_cause_type'], $validTypes)) {
            throw new \Exception("root_cause_type must be one of: " . implode(', ', $validTypes));
        }
        
        $validSeverities = ['critical', 'high', 'medium', 'low'];
        if (!in_array($output['severity'], $validSeverities)) {
            throw new \Exception("severity must be one of: " . implode(', ', $validSeverities));
        }
        
        // Ensure contributing_factors is array
        if (isset($output['contributing_factors']) && !is_array($output['contributing_factors'])) {
            $output['contributing_factors'] = [];
        }
    }
    
    /**
     * Detect if discord is present in message
     */
    private function detectDiscord(string $message, ?array $agapeOutput): bool {
        // Check message for conflict indicators
        $conflictKeywords = ['frustrat', 'angry', 'mad', 'upset', 'problem', 'issue', 'error', 'wrong', 'failed', 'broken', 'can\'t', 'cannot', 'hate', 'disappointed'];
        
        foreach ($conflictKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        
        // Check AGAPE output for low love_score (might indicate conflict)
        if ($agapeOutput && isset($agapeOutput['love_score']) && $agapeOutput['love_score'] < 0.5) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Identify root cause from message and context
     */
    private function identifyRootCause(string $message, ?array $agapeOutput, array $context): string {
        // Look for common root cause patterns
        $patterns = [
            '/don\'?t know|not sure|unclear|confus/i' => 'Knowledge gap: Missing information or understanding',
            '/should|expected|supposed to|ought to/i' => 'Expectation mismatch: Gap between expectation and reality',
            '/can\'?t|cannot|unable|no way/i' => 'Environmental constraint: Resource or capability limitation',
            '/misunderstand|confus|unclear|ambiguous/i' => 'Misunderstanding: Communication or interpretation failure',
            '/error|wrong|incorrect|mistake/i' => 'Knowledge gap: Incorrect information or assumption',
            '/frustrat|angry|upset/i' => 'Communication failure: Message not received or understood correctly'
        ];
        
        foreach ($patterns as $pattern => $cause) {
            if (preg_match($pattern, $message)) {
                return $cause;
            }
        }
        
        // Default based on AGAPE output if available
        if ($agapeOutput && isset($agapeOutput['why_loving'])) {
            return "Potential root cause identified from context: " . $agapeOutput['why_loving'];
        }
        
        return "Potential misunderstanding in communication or expectation mismatch";
    }
    
    /**
     * Identify root cause type
     */
    private function identifyRootCauseType(string $message): string {
        $types = [
            'knowledge_gap' => ['don\'t know', 'not sure', 'unclear', 'missing', 'unaware'],
            'misunderstanding' => ['misunderstand', 'confus', 'unclear', 'ambiguous', 'interpret'],
            'ambiguity' => ['unclear', 'ambiguous', 'vague', 'unsure', 'confus'],
            'constraint' => ['can\'t', 'cannot', 'unable', 'no way', 'limited', 'budget', 'constraint'],
            'communication' => ['frustrat', 'angry', 'upset', 'not understand', 'message']
        ];
        
        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($message, $keyword) !== false) {
                    return $type;
                }
            }
        }
        
        return 'knowledge_gap'; // Default
    }
    
    /**
     * Assess severity
     */
    private function assessSeverity(string $message): string {
        $criticalKeywords = ['hate', 'terrible', 'awful', 'disaster', 'broken', 'failed'];
        $highKeywords = ['angry', 'frustrat', 'upset', 'wrong', 'error'];
        $mediumKeywords = ['problem', 'issue', 'concern', 'worry'];
        
        foreach ($criticalKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return 'critical';
            }
        }
        
        foreach ($highKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return 'high';
            }
        }
        
        foreach ($mediumKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return 'medium';
            }
        }
        
        return 'low';
    }
    
    /**
     * Identify contributing factors
     */
    private function identifyContributingFactors(string $message, ?array $agapeOutput): array {
        $factors = [];
        
        // Extract constraints mentioned
        if (preg_match('/\$\d+|budget|cost|price|expensive|cheap/i', $message)) {
            $factors[] = 'Financial constraint';
        }
        
        if (preg_match('/time|deadline|urgent|quick/i', $message)) {
            $factors[] = 'Time constraint';
        }
        
        if (preg_match('/hosting|server|infrastructure|platform/i', $message)) {
            $factors[] = 'Technical constraint';
        }
        
        // Check AGAPE output for contributing factors
        if ($agapeOutput && isset($agapeOutput['loving_actions'])) {
            // If AGAPE identified actions, those might be contributing factors
            foreach ($agapeOutput['loving_actions'] as $action) {
                if (stripos($action, 'constraint') !== false || stripos($action, 'limit') !== false) {
                    $factors[] = 'Constraint identified: ' . $action;
                }
            }
        }
        
        return array_slice($factors, 0, 3); // Limit to 3
    }
    
    /**
     * Identify recurring patterns
     */
    private function identifyPattern(string $message, array $context): string {
        // Check if similar issues mentioned before (would need history)
        // For now, return pattern based on message content
        if (preg_match('/always|never|every time|constantly/i', $message)) {
            return 'Recurring issue pattern detected';
        }
        
        if (preg_match('/again|still|yet|still not/i', $message)) {
            return 'Persistent issue pattern';
        }
        
        return 'No clear recurring pattern identified';
    }
    
    /**
     * Suggest prevention strategy
     */
    private function suggestPreventionStrategy(string $message, array $context): string {
        $rootCauseType = $this->identifyRootCauseType($message);
        
        $strategies = [
            'knowledge_gap' => 'Provide clear documentation and context before making suggestions',
            'misunderstanding' => 'Clarify expectations and confirm understanding before proceeding',
            'ambiguity' => 'Ask clarifying questions to resolve ambiguity early',
            'constraint' => 'Identify constraints and limitations before proposing solutions',
            'communication' => 'Improve communication channels and feedback loops'
        ];
        
        return $strategies[$rootCauseType] ?? 'Address root cause proactively to prevent recurrence';
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
        if (!isset($config['max_root_causes']) || $config['max_root_causes'] < 1) {
            throw new \InvalidArgumentException("ERIS: max_root_causes must be >= 1");
        }
        
        $validSeverities = ['critical', 'high', 'medium', 'low'];
        if (isset($config['severity_threshold']) && !in_array($config['severity_threshold'], $validSeverities)) {
            throw new \InvalidArgumentException("ERIS: severity_threshold must be one of: " . implode(', ', $validSeverities));
        }
        
        return true;
    }
}

