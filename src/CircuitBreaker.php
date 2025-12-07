<?php
/**
 * Enhanced Circuit Breaker Pattern Implementation
 * 
 * @package CARMEN
 * @version 0.1.4
 * 
 * WHO: Circuit Breaker
 * WHAT: Prevents cascading failures with three-state pattern (Closed/Open/Half-Open)
 * WHEN: 2025-12-06
 * WHY: Protect system from repeated failures with graceful recovery
 * 
 * Enhanced Implementation provided by: WOLFITH-GROK (Agent 101)
 * Based on: DeepSeek Critical Review + Ganesha/leocarmo Circuit Breaker patterns
 * 
 * Features:
 * - Three states: Closed (normal), Open (blocking), Half-Open (probation)
 * - Success threshold for closing circuit
 * - Configurable failure/success thresholds
 * - Callback hooks for state changes
 * - Request timeout per operation
 * - Extensible for persistence (Redis/APCu)
 */

namespace Carmen;

use Exception;

class CircuitBreaker
{
    // Circuit States
    const STATE_CLOSED = 'closed';
    const STATE_OPEN = 'open';
    const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $successCount = 0;
    private int $lastFailureTime = 0;

    // Configurable thresholds
    private int $failureThreshold;
    private int $successThreshold;
    private int $retryTimeout; // seconds
    private int $requestTimeout; // seconds for individual calls

    // Optional callbacks for events
    private $onStateChangeCallback = null;
    private $onFailureCallback = null;
    private $onSuccessCallback = null;

    /**
     * Constructor
     * 
     * @param int $failureThreshold Number of failures before opening circuit (default: 3)
     * @param int $successThreshold Number of successes in half-open to close circuit (default: 1)
     * @param int $retryTimeout Seconds to wait before trying half-open state (default: 300)
     * @param int $requestTimeout Seconds timeout per individual request (default: 30)
     */
    public function __construct(
        int $failureThreshold = 3,
        int $successThreshold = 1,
        int $retryTimeout = 300, // 5 minutes
        int $requestTimeout = 30 // per request
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->successThreshold = $successThreshold;
        $this->retryTimeout = $retryTimeout;
        $this->requestTimeout = $requestTimeout;
    }

    /**
     * Set callback for state change events
     * 
     * @param callable $callback Function(oldState, newState)
     */
    public function setOnStateChange(callable $callback): void
    {
        $this->onStateChangeCallback = $callback;
    }

    /**
     * Set callback for failure events
     * 
     * @param callable $callback Function(Exception $e)
     */
    public function setOnFailure(callable $callback): void
    {
        $this->onFailureCallback = $callback;
    }

    /**
     * Set callback for success events
     * 
     * @param callable $callback Function()
     */
    public function setOnSuccess(callable $callback): void
    {
        $this->onSuccessCallback = $callback;
    }

    /**
     * Execute operation with circuit breaker protection
     * 
     * @param callable $operation Operation to execute
     * @return mixed Operation result
     * @throws Exception If circuit is open or operation fails
     */
    public function execute(callable $operation)
    {
        $this->beforeExecute();

        try {
            // Wrap with timeout (using stream context or similar; here simulated with time limit)
            set_time_limit($this->requestTimeout);
            $result = $operation();
            $this->handleSuccess();
            return $result;
        } catch (Exception $e) {
            $this->handleFailure($e);
            throw $e;
        } finally {
            set_time_limit(0); // Reset
        }
    }

    /**
     * Check circuit state before execution
     * 
     * @throws Exception If circuit is open and timeout hasn't passed
     */
    private function beforeExecute(): void
    {
        $now = time();

        if ($this->state === self::STATE_OPEN) {
            if ($now - $this->lastFailureTime > $this->retryTimeout) {
                // Timeout passed - transition to half-open for probation
                $this->transitionTo(self::STATE_HALF_OPEN);
                $this->resetCounts();
            } else {
                $timeRemaining = $this->retryTimeout - ($now - $this->lastFailureTime);
                throw new Exception('Circuit open - retry after ' . $timeRemaining . ' seconds');
            }
        }
    }

    /**
     * Handle successful operation
     */
    private function handleSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            // In half-open state - count successes
            $this->successCount++;
            if ($this->successCount >= $this->successThreshold) {
                // Enough successes - close the circuit
                $this->transitionTo(self::STATE_CLOSED);
                $this->resetCounts();
            }
        } else {
            // In closed state - reset counts on success
            $this->resetCounts();
        }

        if ($this->onSuccessCallback) {
            call_user_func($this->onSuccessCallback);
        }
    }

    /**
     * Handle failed operation
     * 
     * @param Exception $e The exception that occurred
     */
    private function handleFailure(Exception $e): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        // If in half-open state, or failure threshold reached, open circuit
        if ($this->state === self::STATE_HALF_OPEN || $this->failureCount >= $this->failureThreshold) {
            $this->transitionTo(self::STATE_OPEN);
        }

        if ($this->onFailureCallback) {
            call_user_func($this->onFailureCallback, $e);
        }
    }

    /**
     * Transition to new state
     * 
     * @param string $newState New circuit state
     */
    private function transitionTo(string $newState): void
    {
        if ($this->state !== $newState) {
            $oldState = $this->state;
            $this->state = $newState;
            
            if ($this->onStateChangeCallback) {
                call_user_func($this->onStateChangeCallback, $oldState, $newState);
            }
        }
    }

    /**
     * Reset failure and success counts
     */
    private function resetCounts(): void
    {
        $this->failureCount = 0;
        $this->successCount = 0;
    }

    /**
     * Get current circuit state
     * 
     * @return string Current state (closed/open/half_open)
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get current failure count
     * 
     * @return int Number of consecutive failures
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get current success count (in half-open state)
     * 
     * @return int Number of consecutive successes
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get status string for debugging
     * 
     * @return string Human-readable status
     */
    public function getStatus(): string
    {
        switch ($this->state) {
            case self::STATE_OPEN:
                $timeRemaining = $this->retryTimeout - (time() - $this->lastFailureTime);
                return "OPEN (retry in {$timeRemaining}s, {$this->failureCount}/{$this->failureThreshold} failures)";
            
            case self::STATE_HALF_OPEN:
                return "HALF_OPEN (probation: {$this->successCount}/{$this->successThreshold} successes needed)";
            
            case self::STATE_CLOSED:
            default:
                return "CLOSED ({$this->failureCount}/{$this->failureThreshold} failures)";
        }
    }

    /**
     * Manually reset circuit breaker to closed state
     */
    public function reset(): void
    {
        $this->transitionTo(self::STATE_CLOSED);
        $this->resetCounts();
        $this->lastFailureTime = 0;
    }

    /**
     * Check if circuit is open (blocking requests)
     * 
     * @return bool True if circuit is open
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN && 
               (time() - $this->lastFailureTime) < $this->retryTimeout;
    }

    /**
     * Check if circuit is closed (normal operation)
     * 
     * @return bool True if circuit is closed
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Check if circuit is in half-open state (probation)
     * 
     * @return bool True if circuit is half-open
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    // TODO: For persistence (extendable, e.g., to Redis/APCu for distributed systems)
    // public function loadStateFromStorage(string $key): void { ... }
    // public function saveStateToStorage(string $key): void { ... }
}

