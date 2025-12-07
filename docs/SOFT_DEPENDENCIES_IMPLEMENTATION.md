# Soft Dependencies Implementation

**Version**: 0.1.6  
**Date**: 2025-12-06  
**Status**: ✅ Implemented

## Overview

CARMEN now implements **soft dependencies** - stages can proceed with fallback data even if their dependencies fail. This prevents cascading failures and ensures partial results are always available.

## Problem Solved

**Before**: If AGAPE stage failed, ERIS (which depends on AGAPE) would also fail, causing METIS to fail, and so on. This created a single point of failure.

**After**: If AGAPE fails, ERIS receives fallback context extracted from the raw message and can still produce useful analysis. Each stage gracefully degrades rather than failing completely.

## Implementation

### 1. FallbackDataProvider Class

**Location**: `src/fallback/FallbackDataProvider.php`

Provides default/fallback data for each stage when:
- The stage itself fails
- Dependencies are missing or failed
- Context is insufficient

**Methods**:
- `getAgapeFallback()` - Default loving actions and behavioral scores
- `getErisFallback()` - Root cause extraction from message keywords
- `getMetisFallback()` - Knowledge gap identification
- `getThaliaRoseFallback()` - Neutral humor analysis
- `getThothFallback()` - Unverified truth status
- `extractBasicContext()` - Keyword extraction and sentiment analysis
- `summarizeStageOutput()` - Condense previous stage outputs for context

### 2. Updated CarmenAgent::process()

**Key Changes**:

1. **Soft Context Building**: `getContextForStage()` method:
   - Checks if dependencies succeeded → uses their output
   - If dependency failed → extracts basic context from message
   - If dependency missing → provides fallback context
   - Always provides some context, never fails completely

2. **Graceful Failure Handling**:
   - When stage fails → automatically uses `getFallbackForStage()`
   - Fallback result is marked as success (with `fallback_used: true` metadata)
   - Next stages receive fallback data as context
   - Processing continues even if multiple stages fail

3. **Exception Handling**:
   - Catches exceptions at stage level
   - Uses fallback data instead of propagating errors
   - Logs errors but continues processing

### 3. Context Flow Example

```
User Message: "I'm frustrated because the AI keeps suggesting PostgreSQL but my budget is $3/month shared hosting."

Stage 1: AGAPE
- Success → Output: {loving_actions: [...], love_score: 0.9}
- Context for ERIS: AGAPE output available ✅

Stage 2: ERIS (depends on AGAPE)
- If AGAPE succeeded: Uses AGAPE output as context
- If AGAPE failed: Uses fallback context extracted from message
  → Fallback: {root_causes: ["Frustration with current approach"], conflict_detected: true}
- ERIS can still identify root causes even if AGAPE failed

Stage 3: METIS (depends on ERIS, AGAPE)
- If ERIS succeeded: Uses ERIS + AGAPE outputs
- If ERIS failed but AGAPE succeeded: Uses AGAPE + ERIS fallback
- If both failed: Uses message-based fallback context
- METIS can still provide empathy analysis
```

## Benefits

1. **Resilience**: No single point of failure
2. **Partial Results**: Always get some analysis, even if stages fail
3. **Graceful Degradation**: Quality degrades smoothly, doesn't break completely
4. **User Experience**: Users get responses even during system issues
5. **Debugging**: Errors are logged but don't stop processing

## Fallback Quality

Fallback data is intentionally **moderate quality** (scores ~0.5-0.6) to:
- Clearly indicate fallback was used
- Provide basic analysis without false confidence
- Encourage fixing underlying issues rather than relying on fallbacks

## Metadata Tracking

All fallback results include:
```php
[
    'fallback_used' => true,
    'original_error' => '...', // If stage failed
    'extracted_keywords' => [...], // If using message extraction
    'analysis_method' => 'keyword_extraction' // How fallback was generated
]
```

## Testing Recommendations

1. **Simulate AGAPE failure** → Verify ERIS still runs with fallback
2. **Simulate ERIS failure** → Verify METIS still runs with AGAPE + ERIS fallback
3. **Simulate all stages failing** → Verify final response still generated
4. **Check logs** → Verify fallback_used flags are set correctly
5. **Compare outputs** → Verify fallback quality is acceptable but clearly marked

## Next Steps

- [ ] Add unit tests for fallback scenarios
- [ ] Benchmark fallback quality vs. full processing
- [ ] Consider improving fallback heuristics based on real message patterns
- [ ] Add fallback quality metrics to performance tracking

## Related Files

- `src/CarmenAgent.php` - Main processing logic with soft dependencies
- `src/fallback/FallbackDataProvider.php` - Fallback data generation
- `docs/DEEPSEEK_CRITICAL_REVIEW_RESPONSE.md` - Original requirements
- `docs/WOLFITH_GROK_INTEGRATION_SUMMARY.md` - Implementation guidance

---

**Implementation Status**: ✅ Complete  
**Testing Status**: ⏳ Pending  
**Documentation Status**: ✅ Complete

