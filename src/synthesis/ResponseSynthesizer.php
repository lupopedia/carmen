<?php
/**
 * Response Synthesizer
 * 
 * @package CARMEN
 * @version 0.1.0
 * 
 * WHO: Response Synthesizer
 * WHAT: Intelligently combines stage outputs into unified response
 * WHEN: 2025-12-02
 * WHY: Replace placeholder concatenation with intelligent synthesis
 */

class ResponseSynthesizer {
    private array $config;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'tone' => 'balanced',
            'include_humor' => true,
            'include_truth_markers' => true,
            'max_length' => 2000
        ], $config);
    }
    
    /**
     * Synthesize unified response from all stage outputs
     * 
     * @param array $stageResults Array of StageResult objects
     * @param string $originalMessage Original user message
     * @param array $context Additional context
     * @return string Unified response
     */
    public function synthesize(array $stageResults, string $originalMessage, array $context = []): string {
        $parts = [];
        
        // Stage 1: AGAPE - Start with loving actions
        if (isset($stageResults['AGAPE']) && $stageResults['AGAPE']->success) {
            $agape = $stageResults['AGAPE']->output ?? [];
            $parts[] = $this->formatAgapeOutput($agape, $context);
        }
        
        // Stage 2: ERIS - Acknowledge root cause (if discord detected)
        if (isset($stageResults['ERIS']) && $stageResults['ERIS']->success) {
            $eris = $stageResults['ERIS']->output ?? [];
            if (!empty($eris['discord_detected'] ?? false)) {
                $parts[] = $this->formatErisOutput($eris, $context);
            }
            // Early exit opportunity: If no discord, could skip some stages
            // but we'll continue to show empathy and verify truth
        }
        
        // Stage 3: METIS - Show empathy and understanding
        if (isset($stageResults['METIS']) && $stageResults['METIS']->success) {
            $metis = $stageResults['METIS']->output ?? [];
            $parts[] = $this->formatMetisOutput($metis, $context);
        }
        
        // Stage 4: THALIA+ROSE - Add humor (OPTIONAL - conditionally, if appropriate)
        if (isset($stageResults['THALIA_ROSE']) && $stageResults['THALIA_ROSE']->success) {
            $thaliaRose = $stageResults['THALIA_ROSE']->output ?? [];
            if (!empty($thaliaRose['humor_detected']) && !empty($thaliaRose['humor_appropriate'])) {
                $humorPart = $this->formatHumorOutput($thaliaRose, $context);
                if ($humorPart) {
                    $parts[] = $humorPart;
                }
            }
        }
        
        // Combine parts
        $response = $this->combineParts($parts, $originalMessage);
        
        // Stage 5: THOTH - Add truth verification markers (OPTIONAL)
        if (isset($stageResults['THOTH']) && $stageResults['THOTH']->success && $this->config['include_truth_markers']) {
            $thoth = $stageResults['THOTH']->output ?? [];
            $response = $this->addTruthMarkers($response, $thoth);
        }
        
        // If optional stages are disabled, response is still complete with core 3 stages
        
        // Final formatting and length check
        $response = $this->finalFormatting($response);
        
        return $response;
    }
    
    /**
     * Format AGAPE output (loving actions)
     */
    private function formatAgapeOutput(array $agape, array $context): string {
        $actions = $agape['loving_actions'] ?? [];
        $whyLoving = $agape['why_loving'] ?? '';
        
        $output = [];
        
        if (!empty($actions)) {
            $output[] = "Here's what I can do to help RIGHT NOW:";
            foreach ($actions as $i => $action) {
                $output[] = ($i + 1) . ". " . $action;
            }
        }
        
        if ($whyLoving) {
            $output[] = "\n" . $whyLoving;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Format ERIS output (root cause acknowledgment)
     */
    private function formatErisOutput(array $eris, array $context): string {
        $rootCause = $eris['root_cause'] ?? '';
        $prevention = $eris['prevention_strategy'] ?? '';
        
        $output = [];
        
        if ($rootCause) {
            $output[] = "I see the issue: " . $rootCause;
        }
        
        if ($prevention) {
            $output[] = "Going forward: " . $prevention;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Format METIS output (empathy)
     */
    private function formatMetisOutput(array $metis, array $context): string {
        $userNeeds = $metis['empathy_analysis']['user_needs'] ?? '';
        $bridge = $metis['empathy_analysis']['bridge'] ?? '';
        
        $output = [];
        
        if ($userNeeds) {
            $output[] = $userNeeds;
        }
        
        if ($bridge) {
            $output[] = "\n" . $bridge;
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Format humor output (conditional)
     */
    private function formatHumorOutput(array $thaliaRose, array $context): ?string {
        $humorText = $thaliaRose['humor_text'] ?? '';
        $whyFunny = $thaliaRose['why_funny'] ?? '';
        
        if (!$humorText) {
            return null;
        }
        
        // Subtle integration - don't force it
        // Could be a gentle observation or light comment
        return $humorText;
    }
    
    /**
     * Combine parts into coherent response
     */
    private function combineParts(array $parts, string $originalMessage): string {
        // Filter empty parts
        $parts = array_filter($parts, fn($p) => !empty(trim($p)));
        
        if (empty($parts)) {
            return "I understand. Let me help you with that.";
        }
        
        // Join with double newlines for readability
        $response = implode("\n\n", $parts);
        
        // Apply tone
        $response = $this->applyTone($response);
        
        return $response;
    }
    
    /**
     * Apply configured tone
     */
    private function applyTone(string $response): string {
        switch ($this->config['tone']) {
            case 'warm':
                // Could add warm language
                break;
            case 'professional':
                // Keep formal
                break;
            case 'concise':
                // Trim unnecessary words
                break;
            // 'balanced' is default
        }
        return $response;
    }
    
    /**
     * Add truth verification markers from THOTH
     */
    private function addTruthMarkers(string $response, array $thoth): string {
        $verified = $thoth['verified_claims'] ?? 0;
        $unverified = $thoth['unverified_claims'] ?? 0;
        
        if ($unverified > 0) {
            $marker = "\n\n*(Note: Some claims are unverified or theoretical. Verified: {$verified}, Unverified: {$unverified})*";
            $response .= $marker;
        } elseif ($verified > 0) {
            $marker = "\n\n*(All claims verified)*";
            $response .= $marker;
        }
        
        return $response;
    }
    
    /**
     * Final formatting and length check
     */
    private function finalFormatting(string $response): string {
        // Trim whitespace
        $response = trim($response);
        
        // Check length
        if (strlen($response) > $this->config['max_length']) {
            // Truncate at sentence boundary
            $truncated = substr($response, 0, $this->config['max_length']);
            $lastPeriod = strrpos($truncated, '.');
            if ($lastPeriod !== false) {
                $response = substr($truncated, 0, $lastPeriod + 1) . " (Response truncated)";
            } else {
                $response = $truncated . "...";
            }
        }
        
        return $response;
    }
}

