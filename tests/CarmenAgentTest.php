<?php
/**
 * CARMEN Agent Test Suite
 * 
 * @package CARMEN
 * @version 0.1.0
 */

require_once __DIR__ . '/../src/CarmenAgent.php';
require_once __DIR__ . '/../src/stages/StageInterface.php';
require_once __DIR__ . '/../src/stages/AgapeStage.php';

/**
 * Basic test structure for CARMEN agent
 * 
 * TODO: Expand with full PHPUnit test suite
 */
class CarmenAgentTest {
    
    public function testBasicProcessing() {
        echo "Test: Basic Processing\n";
        
        $config = [
            'processing' => [
                'mode' => 'sequential',
                'enable_fallback' => false
            ],
            'stages' => [
                'AGAPE' => ['config' => []]
            ],
            'logging' => ['log_to_database' => false]
        ];
        
        $agent = new CarmenAgent($config);
        $result = $agent->process("I'm frustrated with the database suggestions.");
        
        assert(isset($result['success']), "Result should have 'success' key");
        assert(isset($result['unified_response']), "Result should have 'unified_response' key");
        
        echo "✓ Basic processing test passed\n";
    }
    
    public function testStageDependencies() {
        echo "Test: Stage Dependencies\n";
        
        // Test that stages respect dependencies
        // This would require full stage implementations
        echo "⚠ Stage dependency test requires full implementations\n";
    }
    
    public function testEarlyExit() {
        echo "Test: Early Exit Logic\n";
        
        require_once __DIR__ . '/../src/EarlyExitHandler.php';
        
        $handler = new EarlyExitHandler([
            'enable_early_exit' => true,
            'skip_eris_if_no_discord' => true
        ]);
        
        // Test message with no conflict indicators
        $shouldSkip = $handler->shouldSkipStage('ERIS', [], "Hello, how are you?");
        assert($shouldSkip === true, "Should skip ERIS for non-conflict messages");
        
        // Test message with conflict indicators
        $shouldSkip = $handler->shouldSkipStage('ERIS', [], "I'm frustrated and angry!");
        assert($shouldSkip === false, "Should NOT skip ERIS for conflict messages");
        
        echo "✓ Early exit test passed\n";
    }
    
    public function testResponseSynthesis() {
        echo "Test: Response Synthesis\n";
        
        require_once __DIR__ . '/../src/synthesis/ResponseSynthesizer.php';
        
        $synthesizer = new ResponseSynthesizer([
            'tone' => 'balanced',
            'include_humor' => true,
            'include_truth_markers' => true
        ]);
        
        // Mock stage results
        $stageResults = [
            'AGAPE' => StageResult::success('AGAPE', [
                'loving_actions' => ['Help with solution'],
                'why_loving' => 'Provides immediate help'
            ])
        ];
        
        $response = $synthesizer->synthesize($stageResults, "Test message", []);
        assert(!empty($response), "Synthesis should produce non-empty response");
        
        echo "✓ Response synthesis test passed\n";
    }
    
    public function runAll() {
        echo "=== CARMEN Agent Test Suite ===\n\n";
        
        try {
            $this->testBasicProcessing();
            $this->testStageDependencies();
            $this->testEarlyExit();
            $this->testResponseSynthesis();
            
            echo "\n✓ All tests passed!\n";
        } catch (AssertionError $e) {
            echo "\n✗ Test failed: " . $e->getMessage() . "\n";
        } catch (Exception $e) {
            echo "\n✗ Error: " . $e->getMessage() . "\n";
        }
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli') {
    $test = new CarmenAgentTest();
    $test->runAll();
}

