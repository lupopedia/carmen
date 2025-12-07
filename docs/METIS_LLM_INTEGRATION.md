# METIS LLM Integration

**Version**: 0.1.6  
**Date**: 2025-12-06  
**Status**: ✅ Implemented

## Overview

The METIS stage now uses real LLM calls (GROK or OpenAI) to provide empathy through comparison - understanding the gap between "what SHOULD be" and "what IS". It gracefully falls back to rule-based analysis if the LLM is unavailable, and uses soft dependencies on ERIS and AGAPE outputs when available.

## Implementation Details

### 1. LLM Client Integration

**Location**: `src/stages/MetisStage.php`

- **Constructor**: Accepts optional `LlmClientInterface` parameter
- **Auto-detection**: Checks if LLM client is available before processing
- **Fallback**: Uses rule-based analysis if LLM unavailable
- **Soft Dependencies**: Uses ERIS and AGAPE outputs from context when available

### 2. Prompt Template

**Location**: `config/prompts/metis_prompt.txt`

The prompt instructs the LLM to:
- Run EMPATHY through comparison (what SHOULD be vs what IS)
- **CRITICAL**: Not sympathy ("I feel bad for you") but empathy ("I understand the gap")
- Analyze: ideal state, current state, gap, what's causing gap, what would bridge gap
- Return structured JSON with:
  - `ideal_state`: Description of ideal/expected state
  - `current_state`: Description of actual current state
  - `gap_identified`: Object with knowledge_gap, communication_gap, solution_gap
  - `empathy_analysis`: Object with user_experience, why_experiencing, user_needs, bridge
  - `understanding_score`: Float 0.0-1.0

### 3. Processing Flow

```
1. Check if LLM client available
   ├─ YES → processWithLLM()
   │   ├─ Extract ERIS and AGAPE outputs from context (soft dependencies)
   │   ├─ Build prompt with message + ERIS/AGAPE context
   │   ├─ Call LLM with system/user messages
   │   ├─ Parse JSON response
   │   ├─ Validate output structure
   │   └─ Return StageResult with LLM output
   └─ NO → processWithFallback()
       ├─ Extract ERIS and AGAPE outputs if available
       ├─ Identify ideal vs current state
       ├─ Analyze gaps using ERIS root cause
       ├─ Provide empathy analysis
       └─ Return StageResult with fallback output
```

### 4. Soft Dependencies on ERIS and AGAPE

METIS has **soft dependencies** on both ERIS and AGAPE:
- **If ERIS succeeded**: Uses root cause analysis to identify gaps
- **If AGAPE succeeded**: Uses loving actions to inform bridge strategy
- **If either failed**: Uses fallback context extracted from message
- **If both missing**: Proceeds with message-only analysis

This ensures METIS can always provide empathy analysis, even if earlier stages fail.

### 5. JSON Parsing

**Robust parsing** handles:
- Markdown code blocks (```json ... ```)
- Extra text before/after JSON
- Nested JSON objects
- Malformed responses (throws exception, falls back)

### 6. Output Validation

Validates:
- Required fields present (`ideal_state`, `current_state`, `gap_identified`, `empathy_analysis`, `understanding_score`)
- Correct data types (strings, objects/arrays, numeric)
- Nested structures validated (`gap_identified` must have knowledge_gap, communication_gap, solution_gap)
- Nested structures validated (`empathy_analysis` must have user_experience, why_experiencing, user_needs, bridge)
- `understanding_score` in range 0.0-1.0

### 7. Fallback Analysis

When LLM unavailable, uses rule-based heuristics:
- **Ideal State**: Inferred from ERIS root cause or message expectations
- **Current State**: Extracted from ERIS root cause or message
- **Gap Identification**: Uses ERIS root_cause_type to categorize gaps
- **Empathy Analysis**: Identifies user experience, why, needs, and bridge strategy
- **Understanding Score**: Calculated based on available context (ERIS + AGAPE)

## Configuration

### YAML Config (`config/carmen.yaml`)

```yaml
stages:
  METIS:
    enabled: true
    required: true
    depends_on: ["ERIS"]  # Soft dependency (also uses AGAPE if available)
    llm_provider: "openai"  # or "grok"
    llm_model: "gpt-4"      # or "grok-beta"
    temperature: 0.3        # Lower for empathetic accuracy
    max_tokens: 1000
    config:
      gap_analysis_depth: "detailed"
      include_bridging: true
      focus: ["ideal_vs_reality", "knowledge_gaps", "constraint_understanding"]
```

## Example Output

### Successful LLM Call

```json
{
  "ideal_state": "AI suggests solutions that fit user's constraints from the start",
  "current_state": "AI suggests PostgreSQL without checking $3/month shared hosting budget",
  "gap_identified": {
    "knowledge_gap": "AI lacks knowledge of budget constraint before suggesting architecture",
    "communication_gap": "Budget constraint not communicated or checked",
    "solution_gap": "PostgreSQL solution doesn't fit shared hosting constraints"
  },
  "empathy_analysis": {
    "user_experience": "User experiencing frustration",
    "why_experiencing": "Because: AI lacks knowledge of budget constraint before suggesting",
    "user_needs": "Solution that works within their constraints",
    "bridge": "Identify constraints and limitations before proposing solutions"
  },
  "understanding_score": 0.85
}
```

### Fallback Output

```json
{
  "ideal_state": "Ideal: Constraints identified and respected before proposing solutions",
  "current_state": "Current: User experiencing frustration or dissatisfaction",
  "gap_identified": {
    "knowledge_gap": "AI lacks knowledge of budget constraint before suggesting architecture",
    "communication_gap": "Potential communication gap requiring clarification",
    "solution_gap": "PostgreSQL solution doesn't fit shared hosting constraints"
  },
  "empathy_analysis": {
    "user_experience": "User experiencing frustration",
    "why_experiencing": "Because: AI lacks knowledge of budget constraint before suggesting",
    "user_needs": "Solution that addresses root cause and fits their situation",
    "bridge": "Bridge: Identify constraints before suggesting solutions, Provide working solutions that fit user's situation NOW"
  },
  "understanding_score": 0.8,
  "fallback_used": true,
  "fallback_reason": "LLM client not provided"
}
```

## Error Handling

1. **LLM Call Fails**: Falls back to rule-based analysis
2. **JSON Parse Fails**: Throws exception, caught by fallback
3. **Validation Fails**: Throws exception, caught by fallback
4. **LLM Client Unavailable**: Uses fallback immediately
5. **ERIS Missing**: Proceeds with message-only analysis (soft dependency)
6. **AGAPE Missing**: Proceeds with ERIS-only analysis (soft dependency)

All errors are logged for debugging.

## Token Tracking

- Tracks `tokensUsed` from LLM response
- Falls back to estimation if not provided
- Includes in StageResult metadata

## Integration with ERIS and AGAPE

METIS receives both ERIS and AGAPE outputs in context:
- **From ERIS**: Uses `root_cause` to identify gaps, `root_cause_type` to categorize, `prevention_strategy` for bridge
- **From AGAPE**: Uses `loving_actions` to inform bridge strategy, `love_score` for understanding quality

If ERIS or AGAPE failed, METIS still works with fallback context.

## Understanding Score Calculation

The `understanding_score` is calculated based on:
- Base score: 0.5
- +0.2 if ERIS output available (root cause identified)
- +0.2 (scaled) if AGAPE output available (love score)
- +0.1 if both ERIS and AGAPE available
- Capped at 1.0

## Testing

### Manual Testing

1. **With LLM Available**:
   ```php
   $llmClient = $factory->create('grok');
   $stage = new MetisStage($config, $llmClient);
   $context = [
       'ERIS' => $erisOutput,
       'AGAPE' => $agapeOutput
   ];
   $result = $stage->process("I'm frustrated...", $context);
   ```

2. **Without LLM** (fallback):
   ```php
   $stage = new MetisStage($config, null);
   $result = $stage->process("I'm frustrated...");
   ```

3. **With Partial Context**:
   ```php
   $context = ['ERIS' => $erisOutput]; // AGAPE missing
   $result = $stage->process("I'm frustrated...", $context);
   ```

### Expected Behavior

- ✅ LLM available → Uses real analysis
- ✅ LLM unavailable → Uses fallback
- ✅ LLM fails → Falls back gracefully
- ✅ ERIS + AGAPE available → Uses both for context
- ✅ ERIS only → Uses ERIS context
- ✅ AGAPE only → Uses AGAPE context
- ✅ Both missing → Proceeds with message-only
- ✅ Invalid JSON → Falls back gracefully
- ✅ Validation fails → Falls back gracefully

## Next Steps

- [ ] Add unit tests for LLM integration
- [ ] Add integration tests with mock LLM
- [ ] Test soft dependency scenarios (various combinations)
- [ ] Benchmark LLM vs fallback quality
- [ ] Add retry logic for transient LLM failures
- [ ] Add circuit breaker integration
- [ ] Test full 3-stage workflow (AGAPE → ERIS → METIS)

## Related Files

- `src/stages/MetisStage.php` - Main implementation
- `src/CarmenAgent.php` - LLM client initialization
- `src/llm/LlmClientFactory.php` - Client creation
- `src/llm/clients/GrokClient.php` - GROK implementation
- `config/prompts/metis_prompt.txt` - Prompt template
- `config/carmen.yaml` - Configuration
- `src/fallback/FallbackDataProvider.php` - Fallback data

---

**Implementation Status**: ✅ Complete  
**Testing Status**: ⏳ Pending  
**Documentation Status**: ✅ Complete

