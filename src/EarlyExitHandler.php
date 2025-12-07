<?php
/**
 * Early Exit Handler
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: Early Exit Handler
 * WHAT: Decides when to skip stages for efficiency
 * WHEN: 2025-12-02
 * WHY: Optimize processing by skipping unnecessary stages
 */

class EarlyExitHandler {
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'enable_early_exit' => true,
            'skip_eris_if_no_discord' => true,
            'skip_humor_if_not_applicable' => true
        ], $config);
    }
    
    /**
     * Check if a stage should be skipped based on previous results
     * 
     * @param string $stageName Stage to check
     * @param array $previousResults Results from previous stages
     * @param string $originalMessage Original user message
     * @return bool TRUE if stage should be skipped
     */
    public function shouldSkipStage(string $stageName, array $previousResults, string $originalMessage): bool {
        if (!$this->config['enable_early_exit']) {
            return false;
        }
        
        switch ($stageName) {
            case 'ERIS':
                return $this->shouldSkipEris($previousResults, $originalMessage);
                
            case 'THALIA_ROSE':
                return $this->shouldSkipHumor($previousResults, $originalMessage);
                
            default:
                return false;
        }
    }
    
    /**
     * Check if ERIS should be skipped (no discord detected)
     */
    private function shouldSkipEris(array $previousResults, string $originalMessage): bool {
        if (!$this->config['skip_eris_if_no_discord']) {
            return false;
        }
        
        // Quick heuristic: Check for conflict indicators in message
        $conflictKeywords = ['frustrated', 'angry', 'upset', 'mad', 'hate', 'conflict', 'problem', 'issue', 'wrong', 'broken', 'failed'];
        $hasConflictIndicators = false;
        
        foreach ($conflictKeywords as $keyword) {
            if (stripos($originalMessage, $keyword) !== false) {
                $hasConflictIndicators = true;
                break;
            }
        }
        
        // If no conflict indicators, skip ERIS (assume no discord)
        return !$hasConflictIndicators;
    }
    
    /**
     * Check if THALIA_ROSE should be skipped (not applicable)
     */
    private function shouldSkipHumor(array $previousResults, string $originalMessage): bool {
        if (!$this->config['skip_humor_if_not_applicable']) {
            return false;
        }
        
        // Skip humor for serious topics
        $seriousKeywords = ['death', 'crisis', 'emergency', 'urgent', 'critical', 'failed', 'error', 'broken'];
        foreach ($seriousKeywords as $keyword) {
            if (stripos($originalMessage, $keyword) !== false) {
                return true; // Skip humor for serious topics
            }
        }
        
        // If previous stages indicate serious issue, skip humor
        if (isset($previousResults['ERIS']) && 
            $previousResults['ERIS']->success && 
            isset($previousResults['ERIS']->output['severity']) &&
            in_array($previousResults['ERIS']->output['severity'], ['critical', 'high'])) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get list of stages to skip for given context
     * 
     * @param array $stageNames Ordered list of stage names
     * @param string $originalMessage Original user message
     * @return array Stages to skip
     */
    public function getStagesToSkip(array $stageNames, string $originalMessage): array {
        $toSkip = [];
        $previousResults = [];
        
        foreach ($stageNames as $stageName) {
            if ($this->shouldSkipStage($stageName, $previousResults, $originalMessage)) {
                $toSkip[] = $stageName;
            }
            // Simulate successful result for next iteration (since we're pre-checking)
            // In actual implementation, previousResults would be real results
        }
        
        return $toSkip;
    }
}

