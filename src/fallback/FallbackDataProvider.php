<?php
/**
 * Fallback Data Provider for CARMEN Stages
 * 
 * @package CARMEN
 * @version 0.1.6
 * 
 * WHO: Fallback Data Provider
 * WHAT: Provides default/fallback data when stages fail or dependencies are missing
 * WHEN: 2025-12-06
 * WHY: Enable soft dependencies - stages can proceed with fallback data instead of failing
 */

class FallbackDataProvider {
    
    /**
     * Get fallback data for AGAPE stage
     * 
     * @param string $message User message
     * @param array $context Available context
     * @return array Fallback AGAPE output
     */
    public static function getAgapeFallback(string $message, array $context = []): array {
        // Extract basic keywords for context
        $keywords = self::extractKeywords($message);
        
        return [
            'loving_actions' => [
                "Offer patient guidance based on detected needs",
                "Provide constructive support",
                "Acknowledge user's situation with empathy"
            ],
            'behavioral_scores' => [
                'did_it_teach' => false,
                'did_it_help' => true,
                'did_it_encourage' => true,
                'patience_shown' => true,
                'kindness_shown' => true,
                'hope_shown' => false,
                'flexibility_shown' => true
            ],
            'love_score' => 0.6, // Moderate fallback score
            'why_loving' => "Default loving action: Offering support and guidance based on available context.",
            'fallback_used' => true,
            'extracted_keywords' => $keywords
        ];
    }
    
    /**
     * Get fallback data for ERIS stage
     * 
     * @param string $message User message
     * @param array $context Available context (may include AGAPE output if available)
     * @return array Fallback ERIS output
     */
    public static function getErisFallback(string $message, array $context = []): array {
        // Try to extract root cause from message directly
        $rootCause = self::extractRootCauseFromMessage($message);
        
        return [
            'root_causes' => [
                [
                    'cause' => $rootCause,
                    'severity' => 'medium',
                    'confidence' => 0.5,
                    'why' => "Identified from message analysis (fallback mode)"
                ]
            ],
            'conflict_detected' => self::detectConflictInMessage($message),
            'conflict_score' => 0.5,
            'fallback_used' => true,
            'analysis_method' => 'keyword_extraction'
        ];
    }
    
    /**
     * Get fallback data for METIS stage
     * 
     * @param string $message User message
     * @param array $context Available context (may include AGAPE/ERIS outputs)
     * @return array Fallback METIS output
     */
    public static function getMetisFallback(string $message, array $context = []): array {
        // Extract knowledge gaps from message
        $gaps = self::extractKnowledgeGaps($message);
        
        return [
            'knowledge_gaps' => $gaps,
            'empathy_score' => 0.65,
            'gap_analysis' => [
                'what_is' => "Current situation as described by user",
                'what_should_be' => "Ideal resolution or understanding",
                'bridging_strategy' => "Acknowledge gap and offer understanding"
            ],
            'understanding' => "Acknowledging the gap between expectation and reality.",
            'fallback_used' => true
        ];
    }
    
    /**
     * Get fallback data for THALIA_ROSE stage
     * 
     * @param string $message User message
     * @param array $context Available context
     * @return array Fallback THALIA_ROSE output
     */
    public static function getThaliaRoseFallback(string $message, array $context = []): array {
        return [
            'humor_detected' => false,
            'humor_appropriate' => false,
            'humor_score' => 0.0,
            'cultural_context' => 'neutral',
            'fallback_used' => true,
            'reason' => 'Humor analysis requires full context - skipped in fallback mode'
        ];
    }
    
    /**
     * Get fallback data for THOTH stage
     * 
     * @param string $message User message
     * @param array $context Available context
     * @return array Fallback THOTH output
     */
    public static function getThothFallback(string $message, array $context = []): array {
        return [
            'verified_claims' => 0,
            'unverified_claims' => 0,
            'verification_score' => 0.5,
            'truth_status' => 'unverified',
            'fallback_used' => true,
            'reason' => 'Truth verification requires full analysis - skipped in fallback mode'
        ];
    }
    
    /**
     * Extract basic keywords from message for context
     * 
     * @param string $message User message
     * @return array Array of keywords
     */
    private static function extractKeywords(string $message): array {
        // Simple keyword extraction - remove common words
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'];
        
        $words = str_word_count(strtolower($message), 1);
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        
        return array_values(array_unique(array_slice($keywords, 0, 10)));
    }
    
    /**
     * Extract root cause from message using simple heuristics
     * 
     * @param string $message User message
     * @return string Extracted root cause
     */
    private static function extractRootCauseFromMessage(string $message): string {
        // Look for common conflict indicators
        $conflictPatterns = [
            '/frustrat(ed|ing|ion)/i' => 'Frustration with current approach or limitations',
            '/angry|mad|upset/i' => 'Emotional distress or dissatisfaction',
            '/confus(ed|ing|ion)/i' => 'Lack of clarity or understanding',
            '/problem|issue|error/i' => 'Technical or practical problem',
            '/can\'t|cannot|unable/i' => 'Capability or resource constraint',
            '/wrong|incorrect|mistake/i' => 'Perceived error or misunderstanding',
            '/not working|broken|failed/i' => 'System or process failure'
        ];
        
        foreach ($conflictPatterns as $pattern => $cause) {
            if (preg_match($pattern, $message)) {
                return $cause;
            }
        }
        
        return "Potential misunderstanding in communication or expectation mismatch";
    }
    
    /**
     * Detect if message contains conflict indicators
     * 
     * @param string $message User message
     * @return bool TRUE if conflict detected
     */
    private static function detectConflictInMessage(string $message): bool {
        $conflictKeywords = ['frustrat', 'angry', 'mad', 'upset', 'problem', 'issue', 'error', 'wrong', 'failed', 'broken', 'can\'t', 'cannot'];
        
        foreach ($conflictKeywords as $keyword) {
            if (stripos($message, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract knowledge gaps from message
     * 
     * @param string $message User message
     * @return array Array of identified knowledge gaps
     */
    private static function extractKnowledgeGaps(string $message): array {
        $gaps = [];
        
        // Look for questions or uncertainty indicators
        if (preg_match('/\?/', $message)) {
            $gaps[] = "User seeking clarification or information";
        }
        
        // Look for "don't know" or "not sure" patterns
        if (preg_match('/don\'?t know|not sure|unclear|confus/i', $message)) {
            $gaps[] = "Missing information or understanding";
        }
        
        // Look for "should" or "expected" patterns (gap between expectation and reality)
        if (preg_match('/should|expected|supposed to|ought to/i', $message)) {
            $gaps[] = "Gap between expectation and current reality";
        }
        
        if (empty($gaps)) {
            $gaps[] = "Potential gap in understanding or information";
        }
        
        return $gaps;
    }
    
    /**
     * Summarize previous stage output for context
     * 
     * @param array $stageOutput Previous stage output
     * @param int $maxLength Maximum summary length
     * @return string Summarized context
     */
    public static function summarizeStageOutput(array $stageOutput, int $maxLength = 200): string {
        $summary = json_encode($stageOutput);
        
        if (strlen($summary) <= $maxLength) {
            return $summary;
        }
        
        // Extract key fields for summary
        $keyFields = [];
        
        if (isset($stageOutput['loving_actions'])) {
            $keyFields[] = "Actions: " . implode(', ', array_slice($stageOutput['loving_actions'], 0, 2));
        }
        
        if (isset($stageOutput['root_causes'])) {
            $causes = array_map(fn($c) => $c['cause'] ?? '', $stageOutput['root_causes']);
            $keyFields[] = "Root causes: " . implode(', ', array_slice($causes, 0, 1));
        }
        
        if (isset($stageOutput['knowledge_gaps'])) {
            $keyFields[] = "Gaps: " . implode(', ', array_slice($stageOutput['knowledge_gaps'], 0, 1));
        }
        
        return implode(' | ', $keyFields);
    }
    
    /**
     * Extract basic context from raw message when dependencies are missing
     * 
     * @param string $message User message
     * @return array Basic context array
     */
    public static function extractBasicContext(string $message): array {
        return [
            'keywords' => self::extractKeywords($message),
            'has_question' => strpos($message, '?') !== false,
            'has_conflict' => self::detectConflictInMessage($message),
            'estimated_sentiment' => self::estimateSentiment($message),
            'message_length' => strlen($message),
            'word_count' => str_word_count($message)
        ];
    }
    
    /**
     * Estimate sentiment from message (simple heuristic)
     * 
     * @param string $message User message
     * @return string Sentiment label
     */
    private static function estimateSentiment(string $message): string {
        $positiveWords = ['happy', 'glad', 'thanks', 'great', 'good', 'excellent', 'love', 'appreciate'];
        $negativeWords = ['frustrat', 'angry', 'mad', 'upset', 'hate', 'terrible', 'awful', 'bad'];
        
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            if (stripos($message, $word) !== false) {
                $positiveCount++;
            }
        }
        
        foreach ($negativeWords as $word) {
            if (stripos($message, $word) !== false) {
                $negativeCount++;
            }
        }
        
        if ($positiveCount > $negativeCount) {
            return 'positive';
        } elseif ($negativeCount > $positiveCount) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
}

