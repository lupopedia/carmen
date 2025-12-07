# AGAPE LLM Integration

**Version**: 0.1.6  
**Date**: 2025-12-06  
**Status**: ✅ Implemented

## Overview

The AGAPE stage now uses real LLM calls (GROK or OpenAI) to analyze messages for loving actions. It gracefully falls back to rule-based analysis if the LLM is unavailable.

## Implementation Details

### 1. LLM Client Integration

**Location**: `src/stages/AgapeStage.php`

- **Constructor**: Accepts optional `LlmClientInterface` parameter
- **Auto-detection**: Checks if LLM client is available before processing
- **Fallback**: Uses rule-based analysis if LLM unavailable

### 2. Prompt Template

**Location**: `config/prompts/agape_prompt.txt`

The prompt instructs the LLM to:
- Analyze for LOVING ACTIONS (verbs, not feelings)
- Focus on actionable ways to help RIGHT NOW
- Return structured JSON with:
  - `loving_actions`: Array of actionable verbs
  - `behavioral_scores`: Boolean flags for 7 behavioral markers
  - `love_score`: Float 0.0-1.0
  - `why_loving`: Explanation string

### 3. Processing Flow

```
1. Check if LLM client available
   ├─ YES → processWithLLM()
   │   ├─ Build prompt from template
   │   ├─ Call LLM with system/user messages
   │   ├─ Parse JSON response
   │   ├─ Validate output structure
   │   └─ Return StageResult with LLM output
   └─ NO → processWithFallback()
       ├─ Use rule-based analysis
       ├─ Extract keywords from message
       └─ Return StageResult with fallback output
```

### 4. JSON Parsing

**Robust parsing** handles:
- Markdown code blocks (```json ... ```)
- Extra text before/after JSON
- Nested JSON objects
- Malformed responses (throws exception, falls back)

### 5. Output Validation

Validates:
- Required fields present
- Correct data types
- `love_score` in range 0.0-1.0
- `loving_actions` array length ≤ `max_actions`

### 6. CarmenAgent Integration

**Location**: `src/CarmenAgent.php`

- Creates LLM client from factory
- Passes client to AgapeStage constructor
- Supports stage-specific LLM providers/models
- Falls back gracefully if LLM creation fails

## Configuration

### YAML Config (`config/carmen.yaml`)

```yaml
stages:
  AGAPE:
    enabled: true
    required: true
    llm_provider: "openai"  # or "grok"
    llm_model: "gpt-4"       # or "grok-beta"
    temperature: 0.3
    max_tokens: 1000
    config:
      max_actions: 3
      behavioral_threshold: 0.7
```

### Environment Variables

- `OPENAI_API_KEY` - For OpenAI provider
- `GROK_API_KEY` - For GROK provider

## Example Output

### Successful LLM Call

```json
{
  "loving_actions": [
    "Identify constraints before suggesting solutions",
    "Provide working solutions that fit user's situation NOW",
    "Show upgrade path without condemning current approach"
  ],
  "behavioral_scores": {
    "did_it_teach": true,
    "did_it_help": true,
    "did_it_encourage": true,
    "patience_shown": true,
    "kindness_shown": true,
    "hope_shown": true,
    "flexibility_shown": true
  },
  "love_score": 0.95,
  "why_loving": "Provides working solution NOW, teaches upgrade path, works WITH constraints"
}
```

### Fallback Output

```json
{
  "loving_actions": [
    "Offer patient guidance based on detected needs",
    "Provide constructive support"
  ],
  "behavioral_scores": {
    "did_it_teach": false,
    "did_it_help": true,
    "did_it_encourage": true,
    "patience_shown": true,
    "kindness_shown": true,
    "hope_shown": false,
    "flexibility_shown": true
  },
  "love_score": 0.6,
  "why_loving": "Default loving action: Offering support and guidance based on available context.",
  "fallback_used": true,
  "fallback_reason": "LLM client not provided"
}
```

## Error Handling

1. **LLM Call Fails**: Falls back to rule-based analysis
2. **JSON Parse Fails**: Throws exception, caught by fallback
3. **Validation Fails**: Throws exception, caught by fallback
4. **LLM Client Unavailable**: Uses fallback immediately

All errors are logged for debugging.

## Token Tracking

- Tracks `tokensUsed` from LLM response
- Falls back to estimation if not provided
- Includes in StageResult metadata

## Testing

### Manual Testing

1. **With LLM Available**:
   ```php
   $llmClient = $factory->create('grok');
   $stage = new AgapeStage($config, $llmClient);
   $result = $stage->process("I'm frustrated...");
   ```

2. **Without LLM** (fallback):
   ```php
   $stage = new AgapeStage($config, null);
   $result = $stage->process("I'm frustrated...");
   ```

### Expected Behavior

- ✅ LLM available → Uses real analysis
- ✅ LLM unavailable → Uses fallback
- ✅ LLM fails → Falls back gracefully
- ✅ Invalid JSON → Falls back gracefully
- ✅ Validation fails → Falls back gracefully

## Next Steps

- [ ] Add unit tests for LLM integration
- [ ] Add integration tests with mock LLM
- [ ] Benchmark LLM vs fallback quality
- [ ] Add retry logic for transient LLM failures
- [ ] Add circuit breaker integration

## Related Files

- `src/stages/AgapeStage.php` - Main implementation
- `src/CarmenAgent.php` - LLM client initialization
- `src/llm/LlmClientFactory.php` - Client creation
- `src/llm/clients/GrokClient.php` - GROK implementation
- `config/prompts/agape_prompt.txt` - Prompt template
- `config/carmen.yaml` - Configuration

---

**Implementation Status**: ✅ Complete  
**Testing Status**: ⏳ Pending  
**Documentation Status**: ✅ Complete

