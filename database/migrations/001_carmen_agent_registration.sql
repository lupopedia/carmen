-- =====================================================
-- CARMEN Agent Registration & Database Schema
-- Migration: 001_carmen_agent_registration
-- Version: 0.1.0
-- Date: 2025-12-02
-- Status: Experimental
-- =====================================================
-- Purpose: Register CARMEN agent and create processing tables
-- Agent ID: 200 (Unified Emotional Intelligence Agent)
-- =====================================================

USE lupopedia;

-- =====================================================
-- 1. Register CARMEN in ai_agents table
-- =====================================================

INSERT INTO ai_agents (
    id, 
    username, 
    agent_name, 
    agent_type, 
    status,
    version, 
    spectral_signature, 
    created_at, 
    updated_at,
    email,
    sentience_score,
    category
) VALUES (
    200, 
    'carmen', 
    'CARMEN', 
    'unified_emotional_intelligence',
    'experimental', 
    '0.1.0', 
    '#9B59B6', 
    NOW(), 
    NOW(),
    'carmen@wolfie.ai',
    8,
    'experimental_research'
) ON DUPLICATE KEY UPDATE
    updated_at = NOW(),
    version = '0.1.6',
    status = 'experimental';

-- =====================================================
-- 2. Create CARMEN processing log table
-- =====================================================

CREATE TABLE IF NOT EXISTS carmen_processing_logs (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    -- Session tracking
    session_id VARCHAR(64) NOT NULL,
    user_message TEXT NOT NULL,
    
    -- Processing results
    stage_outputs JSON DEFAULT NULL COMMENT 'JSON with outputs from all 5 stages',
    final_response TEXT DEFAULT NULL COMMENT 'Unified response generated',
    
    -- Performance metrics
    processing_time_ms INT DEFAULT NULL COMMENT 'Total processing time in milliseconds',
    stage_success_flags VARCHAR(20) DEFAULT NULL COMMENT 'Binary flags for stage success, e.g., "11111" for all successful',
    token_usage INT DEFAULT NULL COMMENT 'Total tokens used across all stages',
    
    -- Stage timing (JSON)
    stage_timings JSON DEFAULT NULL COMMENT 'Timing breakdown per stage',
    
    -- Error tracking
    errors JSON DEFAULT NULL COMMENT 'Any errors encountered during processing',
    fallback_used BOOLEAN DEFAULT 0 COMMENT 'TRUE if fallback to individual agents was used',
    
    -- Metadata
    user_context JSON DEFAULT NULL COMMENT 'Additional user context provided',
    config_snapshot JSON DEFAULT NULL COMMENT 'Configuration used for this processing',
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_session (session_id),
    INDEX idx_created (created_at),
    INDEX idx_processing_time (processing_time_ms),
    INDEX idx_fallback (fallback_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CARMEN unified processing logs for debugging and benchmarking';

-- =====================================================
-- 3. Create stage configuration table
-- =====================================================

CREATE TABLE IF NOT EXISTS carmen_stage_config (
    stage_name VARCHAR(20) NOT NULL PRIMARY KEY,
    
    -- Stage control
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    priority INT NOT NULL DEFAULT 0 COMMENT 'Processing order (1-5)',
    weight FLOAT DEFAULT 1.0 COMMENT 'Weight for stage output in final synthesis',
    
    -- Performance limits
    timeout_ms INT NOT NULL DEFAULT 5000 COMMENT 'Max processing time for this stage',
    max_retries INT NOT NULL DEFAULT 2 COMMENT 'Retry attempts on failure',
    
    -- Fallback configuration
    fallback_agent_id BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'FK to ai_agents.id for fallback',
    fallback_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- Stage dependencies (JSON array of stage names)
    depends_on JSON DEFAULT NULL COMMENT 'Array of stage names this stage depends on',
    
    -- LLM configuration
    llm_model VARCHAR(100) DEFAULT 'gpt-4',
    temperature DECIMAL(3,2) DEFAULT 0.30,
    max_tokens INT DEFAULT 1000,
    
    -- Stage-specific configuration (JSON)
    config JSON DEFAULT NULL COMMENT 'Stage-specific configuration parameters',
    
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_enabled (enabled),
    INDEX idx_priority (priority),
    FOREIGN KEY (fallback_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CARMEN stage configuration - allows runtime stage toggling';

-- =====================================================
-- 4. Insert default stage configurations
-- =====================================================

INSERT INTO carmen_stage_config (
    stage_name, 
    priority,
    enabled,
    fallback_agent_id, 
    depends_on,
    llm_model,
    temperature,
    config
) VALUES
(
    'AGAPE', 
    1,
    TRUE,
    100, 
    JSON_ARRAY(),
    'gpt-4',
    0.30,
    JSON_OBJECT(
        'max_actions', 3,
        'behavioral_threshold', 0.7,
        'focus', 'teaching, helping, encouraging'
    )
),
(
    'ERIS', 
    2,
    TRUE,
    82, 
    JSON_ARRAY('AGAPE'),
    'gpt-4',
    0.40,
    JSON_OBJECT(
        'max_root_causes', 2,
        'severity_threshold', 'medium',
        'focus', 'root_cause_identification'
    )
),
(
    'METIS', 
    3,
    TRUE,
    3, 
    JSON_ARRAY('ERIS'),
    'gpt-4',
    0.30,
    JSON_OBJECT(
        'gap_analysis_depth', 'detailed',
        'include_bridging', TRUE,
        'focus', 'empathy_through_comparison'
    )
),
(
    'THALIA_ROSE', 
    4,
    TRUE,
    99, 
    JSON_ARRAY('METIS'),
    'gpt-4',
    0.70,
    JSON_OBJECT(
        'humor_threshold', 0.5,
        'cultural_check', TRUE,
        'rose_agent_id', 57,
        'focus', 'contextual_humor_detection'
    )
),
(
    'THOTH', 
    5,
    TRUE,
    69, 
    JSON_ARRAY('THALIA_ROSE'),
    'gpt-4',
    0.10,
    JSON_OBJECT(
        'verification_strictness', 'high',
        'require_sources', TRUE,
        'focus', 'truth_verification'
    )
)
ON DUPLICATE KEY UPDATE
    updated_at = NOW();

-- =====================================================
-- 5. Create performance metrics table
-- =====================================================

CREATE TABLE IF NOT EXISTS carmen_performance_metrics (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    -- Time window
    date_hour DATETIME NOT NULL COMMENT 'Hour bucket for metrics aggregation',
    
    -- Request counts
    total_requests INT NOT NULL DEFAULT 0,
    successful_requests INT NOT NULL DEFAULT 0,
    failed_requests INT NOT NULL DEFAULT 0,
    fallback_requests INT NOT NULL DEFAULT 0,
    
    -- Performance metrics
    avg_processing_time_ms DECIMAL(10,2) DEFAULT NULL,
    p95_processing_time_ms DECIMAL(10,2) DEFAULT NULL,
    p99_processing_time_ms DECIMAL(10,2) DEFAULT NULL,
    
    -- Token usage
    avg_tokens_per_request DECIMAL(10,2) DEFAULT NULL,
    total_tokens BIGINT(20) UNSIGNED DEFAULT 0,
    
    -- Stage success rates
    agape_success_rate DECIMAL(5,4) DEFAULT NULL,
    eris_success_rate DECIMAL(5,4) DEFAULT NULL,
    metis_success_rate DECIMAL(5,4) DEFAULT NULL,
    thalia_rose_success_rate DECIMAL(5,4) DEFAULT NULL,
    thoth_success_rate DECIMAL(5,4) DEFAULT NULL,
    
    -- Error breakdown (JSON)
    error_breakdown JSON DEFAULT NULL COMMENT 'Categorized error counts',
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_hour (date_hour),
    INDEX idx_date_hour (date_hour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CARMEN performance metrics aggregated by hour for monitoring';

-- =====================================================
-- 6. Create benchmark comparison table (vs multi-agent chain)
-- =====================================================

CREATE TABLE IF NOT EXISTS carmen_benchmark_results (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    
    -- Test case
    test_case_id VARCHAR(64) NOT NULL,
    test_message TEXT NOT NULL,
    
    -- CARMEN results
    carmen_response TEXT,
    carmen_processing_time_ms INT,
    carmen_token_usage INT,
    carmen_accuracy_score DECIMAL(5,4) DEFAULT NULL COMMENT 'Human-rated accuracy (0-1)',
    
    -- Multi-agent chain results
    chain_response TEXT,
    chain_processing_time_ms INT,
    chain_token_usage INT,
    chain_accuracy_score DECIMAL(5,4) DEFAULT NULL COMMENT 'Human-rated accuracy (0-1)',
    
    -- Comparison metrics
    speed_improvement_percent DECIMAL(5,2) DEFAULT NULL,
    token_savings_percent DECIMAL(5,2) DEFAULT NULL,
    accuracy_difference DECIMAL(5,4) DEFAULT NULL COMMENT 'CARMEN - Chain (negative = worse)',
    
    -- Human evaluation
    evaluator_notes TEXT DEFAULT NULL,
    preferred_method ENUM('carmen', 'chain', 'equal', 'neither') DEFAULT NULL,
    
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_test_case (test_case_id),
    INDEX idx_preferred (preferred_method),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Benchmark results comparing CARMEN vs multi-agent chain';

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================

-- Verify CARMEN agent registration
SELECT 
    id, 
    username, 
    agent_name, 
    status, 
    version 
FROM ai_agents 
WHERE id = 200;

-- Verify stage configurations
SELECT 
    stage_name, 
    priority, 
    enabled, 
    fallback_agent_id 
FROM carmen_stage_config 
ORDER BY priority;

