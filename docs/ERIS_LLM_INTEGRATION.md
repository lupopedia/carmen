# ERIS LLM Integration

**Version**: 0.1.6  
**Date**: 2025-12-06  
**Status**: ✅ Implemented

## Overview

The ERIS stage now uses real LLM calls (GROK or OpenAI) to identify ROOT CAUSES of conflicts, discord, and frustration. It gracefully falls back to rule-based analysis if the LLM is unavailable, and uses soft dependency on AGAPE output when available.

## Implementation Details

### 1. LLM Client Integration

**Location**: `src/stages/ErisStage.php`

- **Constructor**: Accepts optional `LlmClientInterface` parameter
- **Auto-detection**: Checks if LLM client is available before processing
- **Fallback**: Uses rule-based analysis if LLM unavailable
- **Soft Dependency**: Uses AGAPE output from context when available

### 2. Prompt Template

**Location**: `config/prompts/eris_prompt.txt`

The prompt instructs the LLM to:
- Find ROOT CAUSES (not just detect anger)
- Look for: knowledge gaps, misunderstandings, ambiguity, constraints, communication failures
- Return structured JSON with:
  - `discord_detected`: Boolean
  - `root_cause`: String describing what's actually causing the problem
  - `root_cause_type`: One of: knowledge_gap, misunderstanding, ambiguity, constraint, communication
  - `severity`: One of: critical, high, medium, low
  - `contributing_factors`: Array of additional factors
  - `pattern_identified`: Recurring pattern if any
  - `prevention_strategy`: How to prevent this in the future

### 3. Processing Flow

```
1. Check if LLM client available
   ├─ YES → processWithLLM()
   │   ├─ Extract AGAPE output from context (soft dependency)
   │   ├─ Build prompt with message + AGAPE context
   │   ├─ Call LLM with system/user messages
   │   ├─ Parse JSON response
   │   ├─ Validate output structure
   │   └─ Return StageResult with LLM output
   └─ NO → processWithFallback()
       ├─ Extract AGAPE output if available
       ├─ Use rule-based root cause analysis
       ├─ Detect discord from keywords
       └─ Return StageResult with fallback output
```

### 4. Soft Dependency on AGAPE

ERIS has a **soft dependency** on AGAPE:
- **If AGAPE succeeded**: Uses AGAPE output to inform root cause analysis
- **If AGAPE failed**: Uses fallback context extracted from message
- **If AGAPE missing**: Proceeds with message-only analysis

This ensures ERIS can always provide root cause analysis, even if AGAPE fails.

### 5. JSON Parsing

**Robust parsing** handles:
- Markdown code blocks (```json ... ```)
- Extra text before/after JSON
- Nested JSON objects
- Malformed responses (throws exception, falls back)

### 6. Output Validation

Validates:
- Required fields present (`discord_detected`, `root_cause`, `root_cause_type`, `severity`)
- Correct data types (boolean, string, array)
- `root_cause_type` in valid enum
- `severity` in valid enum
- `contributing_factors` is array

### 7. Fallback Analysis

When LLM unavailable, uses rule-based heuristics:
- **Discord Detection**: Keyword matching (frustrat, angry, problem, etc.)
- **Root Cause Identification**: Pattern matching for common causes
- **Root Cause Type**: Keyword-based classification
- **Severity Assessment**: Keyword-based severity levels
- **Contributing Factors**: Extracts constraints, time, technical issues
- **Pattern Identification**: Detects recurring issues
- **Prevention Strategy**: Suggests based on root cause type

## Configuration

### YAML Config (`config/carmen.yaml`)

```yaml
stages:
  ERIS:
    enabled: true
    required: true
    depends_on: ["AGAPE"]  # Soft dependency
    llm_provider: "openai"  # or "grok"
    llm_model: "gpt-4"      # or "grok-beta"
    temperature: 0.4        # Slightly higher for analysis
    max_tokens: 1000
    config:
      max_root_causes: 2
      severity_threshold: "medium"
      focus: ["knowledge_gaps", "misunderstandings", "ambiguity", "constraints", "communication"]
```

## Example Output

### Successful LLM Call

```json
{
  "discord_detected": true,
  "root_cause": "AI lacks knowledge of budget constraint before suggesting architecture",
  "root_cause_type": "knowledge_gap",
  "severity": "medium",
  "contributing_factors": [
    "Financial constraint ($3/month budget)",
    "Technical constraint (shared hosting limitations)"
  ],
  "pattern_identified": "Suggesting solutions without checking constraints first",
  "prevention_strategy": "Identify constraints and limitations before proposing solutions"
}
```

### Fallback Output

```json
{
  "discord_detected": true,
  "root_cause": "Frustration with current approach or limitations",
  "root_cause_type": "constraint",
  "severity": "medium",
  "contributing_factors": [
    "Financial constraint"
  ],
  "pattern_identified": "No clear recurring pattern identified",
  "prevention_strategy": "Identify constraints and limitations before proposing solutions",
  "fallback_used": true,
  "fallback_reason": "LLM client not provided"
}
```

## Error Handling

1. **LLM Call Fails**: Falls back to rule-based analysis
2. **JSON Parse Fails**: Throws exception, caught by fallback
3. **Validation Fails**: Throws exception, caught by fallback
4. **LLM Client Unavailable**: Uses fallback immediately
5. **AGAPE Missing**: Proceeds with message-only analysis (soft dependency)

All errors are logged for debugging.

## Token Tracking

- Tracks `tokensUsed` from LLM response
- Falls back to estimation if not provided
- Includes in StageResult metadata

## Integration with AGAPE

ERIS receives AGAPE output in context:
- Uses `love_score` to detect potential discord
- Uses `loving_actions` to identify contributing factors
- Uses `why_loving` to inform root cause analysis

If AGAPE failed, ERIS still works with fallback context.

## Testing

### Manual Testing

1. **With LLM Available**:
   ```php
   $llmClient = $factory->create('grok');
   $stage = new ErisStage($config, $llmClient);
   $context = ['AGAPE' => $agapeOutput]; // Soft dependency
   $result = $stage->process("I'm frustrated...", $context);
   ```

2. **Without LLM** (fallback):
   ```php
   $stage = new ErisStage($config, null);
   $result = $stage->process("I'm frustrated...");
   ```

3. **With AGAPE Context**:
   ```php
   $context = ['AGAPE' => ['love_score' => 0.3, 'loving_actions' => [...]]];
   $result = $stage->process("I'm frustrated...", $context);
   ```

### Expected Behavior

- ✅ LLM available → Uses real analysis
- ✅ LLM unavailable → Uses fallback
- ✅ LLM fails → Falls back gracefully
- ✅ AGAPE available → Uses AGAPE context
- ✅ AGAPE missing → Proceeds with message-only
- ✅ Invalid JSON → Falls back gracefully
- ✅ Validation fails → Falls back gracefully

## Next Steps

- [ ] Add unit tests for LLM integration
- [ ] Add integration tests with mock LLM
- [ ] Test soft dependency scenarios
- [ ] Benchmark LLM vs fallback quality
- [ ] Add retry logic for transient LLM failures
- [ ] Add circuit breaker integration

## Related Files

- `src/stages/ErisStage.php` - Main implementation
- `src/CarmenAgent.php` - LLM client initialization
- `src/llm/LlmClientFactory.php` - Client creation
- `src/llm/clients/GrokClient.php` - GROK implementation
- `config/prompts/eris_prompt.txt` - Prompt template
- `config/carmen.yaml` - Configuration
- `src/fallback/FallbackDataProvider.php` - Fallback data

---

**Implementation Status**: ✅ Complete  
**Testing Status**: ⏳ Pending  
**Documentation Status**: ✅ Complete

