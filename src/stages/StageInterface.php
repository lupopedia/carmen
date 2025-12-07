<?php
/**
 * Stage Interface for CARMEN Unified Agent
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: CARMEN Stage Interface
 * WHAT: Defines contract for all CARMEN processing stages
 * WHEN: 2025-12-02
 * WHY: Standardize stage processing for unified workflow
 */

interface StageInterface {
    
    /**
     * Process the message through this stage
     * 
     * @param string $message User message to process
     * @param array $context Additional context (previous stage outputs, user data, etc.)
     * @param array $config Stage-specific configuration
     * @return StageResult Result object containing output, success status, metadata
     */
    public function process(string $message, array $context = [], array $config = []): StageResult;
    
    /**
     * Get stage name
     * 
     * @return string Stage name (e.g., "AGAPE", "ERIS")
     */
    public function getName(): string;
    
    /**
     * Get stage priority (processing order)
     * 
     * @return int Priority (1-5)
     */
    public function getPriority(): int;
    
    /**
     * Get dependencies (stage names this stage depends on)
     * 
     * @return array Array of stage names (e.g., ["AGAPE", "ERIS"])
     */
    public function getDependencies(): array;
    
    /**
     * Check if stage is enabled
     * 
     * @return bool TRUE if enabled
     */
    public function isEnabled(): bool;
    
    /**
     * Get fallback agent ID if stage fails
     * 
     * @return int|null Agent ID or NULL if no fallback
     */
    public function getFallbackAgentId(): ?int;
    
    /**
     * Validate stage configuration
     * 
     * @param array $config Configuration to validate
     * @return bool TRUE if valid
     * @throws InvalidArgumentException if invalid
     */
    public function validateConfig(array $config): bool;
}

/**
 * Stage Result Object
 */
class StageResult {
    public string $stageName;
    public bool $success;
    public ?array $output;
    public ?string $error;
    public int $processingTimeMs;
    public int $tokensUsed;
    public array $metadata;
    
    public function __construct(
        string $stageName,
        bool $success,
        ?array $output = null,
        ?string $error = null,
        int $processingTimeMs = 0,
        int $tokensUsed = 0,
        array $metadata = []
    ) {
        $this->stageName = $stageName;
        $this->success = $success;
        $this->output = $output;
        $this->error = $error;
        $this->processingTimeMs = $processingTimeMs;
        $this->tokensUsed = $tokensUsed;
        $this->metadata = $metadata;
    }
    
    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array {
        return [
            'stage_name' => $this->stageName,
            'success' => $this->success,
            'output' => $this->output,
            'error' => $this->error,
            'processing_time_ms' => $this->processingTimeMs,
            'tokens_used' => $this->tokensUsed,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Create failure result
     */
    public static function failure(string $stageName, string $error, int $processingTimeMs = 0): self {
        return new self($stageName, false, null, $error, $processingTimeMs);
    }
    
    /**
     * Create success result
     */
    public static function success(
        string $stageName, 
        array $output, 
        int $processingTimeMs = 0,
        int $tokensUsed = 0,
        array $metadata = []
    ): self {
        return new self($stageName, true, $output, null, $processingTimeMs, $tokensUsed, $metadata);
    }
}

