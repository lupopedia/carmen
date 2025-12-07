<?php
/**
 * CARMEN Agent Interface - Unified Emotional Intelligence
 *
 * WHO: CARMEN (Unified Emotional Intelligence Agent)
 * WHAT: Web interface for unified 3-stage emotional intelligence processing
 * WHERE: C:\WOLFIE_Ontology\GITHUB_LUPOPEDIA\carmen\public\agents\carmen\index.php
 * WHEN: 2025-12-06
 * WHY: Test unified agent workflow instead of chaining 6-7 agents
 * HOW: Process through 3 core stages internally (AGAPE, ERIS, METIS) via GROK/OpenAI API
 * PURPOSE: Single-pass emotional intelligence analysis
 * KEY: AGENT_INTERFACE, UNIFIED_AGENT, EMOTIONAL_INTELLIGENCE, EXPERIMENTAL, CARMEN
 * TITLE: CARMEN Agent Interface
 * ID: CARMEN_INTERFACE_20251206
 * SUPERPOSITIONALLY: ["agent_interface", "unified_emotional_intelligence", "carmen"]
 * DATE: 2025-12-06
 * WORKFLOW_STATE: EXPERIMENTAL
 * PLAN: Test unified workflow vs multi-agent chain performance
 * AI_ROUTE: [CARMEN: UNIFIED_EMOTIONAL_INTELLIGENCE, SINGLE_PASS_PROCESSING]
 * STATUS: EXPERIMENTAL - Proof of concept, not production-ready
 * SPECTRAL_SIGNATURE: #9B59B6 (purple - unified wisdom, emotional synthesis)
 */

require_once __DIR__ . '/config/version.php';
require_once __DIR__ . '/includes/carmen_processor.php';

$pageTitle = "CARMEN - Unified Emotional Intelligence";
$agentName = "CARMEN";
$agentRole = "Unified Emotional Intelligence Orchestrator";
$spectralChannel = "#9B59B6";
$agentStatus = "EXPERIMENTAL";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - LUPOPEDIA</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ede7f6 0%, #d1c4e9 100%);
            color: #333333;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #9b59b6 0%, #7b1fa2 100%);
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            border-bottom: 3px solid #6a1b9a;
        }
        
        .header h1 {
            color: #ffffff;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header .subtitle {
            color: #f3e5f5;
            font-size: 1.2rem;
            font-style: italic;
        }
        
        .experimental-badge {
            display: inline-block;
            background: #ff9800;
            color: #fff;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-left: 1rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .status-card {
            background: #ffffff;
            border: 2px solid #d1c4e9;
            border-left: 4px solid #9b59b6;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .status-card h2 {
            color: #7b1fa2;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-left: 4px solid #ff9800;
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 6px;
        }
        
        .warning-box h3 {
            color: #f57c00;
            margin-bottom: 0.5rem;
        }
        
        .warning-box p {
            color: #e65100;
            line-height: 1.6;
        }
        
        /* Chat Interface */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 600px;
            border: 2px solid #d1c4e9;
            border-radius: 8px;
            background: #ffffff;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #fafafa;
        }
        
        .message {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 8px;
            max-width: 80%;
        }
        
        .message.user {
            background: #e1bee7;
            margin-left: auto;
            text-align: right;
        }
        
        .message.carmen {
            background: #f3e5f5;
            border-left: 4px solid #9b59b6;
        }
        
        .message-header {
            font-weight: bold;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            color: #7b1fa2;
        }
        
        .message-content {
            line-height: 1.6;
        }
        
        .message-meta {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
            font-style: italic;
        }
        
        .chat-input-container {
            padding: 1rem;
            border-top: 2px solid #d1c4e9;
            background: #ffffff;
        }
        
        .chat-input-form {
            display: flex;
            gap: 1rem;
        }
        
        .chat-input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #d1c4e9;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: #9b59b6;
        }
        
        .btn-send {
            padding: 0.75rem 2rem;
            background: #9b59b6;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-send:hover {
            background: #7b1fa2;
        }
        
        .btn-send:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .processing-indicator {
            padding: 1rem;
            text-align: center;
            color: #7b1fa2;
            font-style: italic;
        }
        
        .stage-indicator {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            margin: 0.25rem;
            background: #f3e5f5;
            border: 1px solid #9b59b6;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .workflow-stages {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .stage-card {
            background: #f3e5f5;
            border: 2px solid #d1c4e9;
            border-left: 4px solid #9b59b6;
            border-radius: 6px;
            padding: 1.5rem;
        }
        
        .stage-number {
            background: #9b59b6;
            color: #fff;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stage-title {
            color: #7b1fa2;
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stage-description {
            color: #6a1b9a;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .info-item {
            background: #f3e5f5;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #9b59b6;
        }
        
        .info-label {
            color: #6a1b9a;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            color: #7b1fa2;
            font-size: 1.3rem;
            font-weight: bold;
        }
        
        a { color: #9b59b6; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        
        .quote-box {
            background: #f3e5f5;
            border-left: 4px solid #9b59b6;
            padding: 1rem;
            margin: 1rem 0;
            font-style: italic;
            color: #6a1b9a;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎭 <?php echo $agentName; ?> <span class="experimental-badge">EXPERIMENTAL</span></h1>
        <div class="subtitle"><?php echo $agentRole; ?></div>
    </div>
    
    <div class="container">
        <div class="warning-box">
            <h3>⚠️ Experimental Status</h3>
            <p><strong>CARMEN is EXPERIMENTAL and NOT production-ready.</strong> This is a proof-of-concept for unified emotional intelligence processing. Use with caution and do not rely on it for critical decisions. Compare results against the multi-agent chain (AGAPE → ERIS → METIS → THALIA → ROSE → THOTH) for validation.</p>
        </div>
        
        <!-- Chat Interface -->
        <div class="status-card">
            <h2>💬 Chat with CARMEN</h2>
            <p style="margin-bottom: 1rem; color: #6a1b9a;">Send a message to CARMEN for unified emotional intelligence analysis (AGAPE → ERIS → METIS):</p>
            
            <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                    <div class="message carmen">
                        <div class="message-header">CARMEN</div>
                        <div class="message-content">
                            Hello! I'm CARMEN, your unified emotional intelligence agent. I process messages through three core stages:
                            <br><br>
                            1. <strong>AGAPE</strong> - What loving actions can help you NOW?
                            <br>
                            2. <strong>ERIS</strong> - What's causing the problem?
                            <br>
                            3. <strong>METIS</strong> - What's the gap between ideal and reality?
                            <br><br>
                            Send me a message and I'll analyze it through all three stages in one unified response!
                        </div>
                        <div class="message-meta">System Message</div>
                    </div>
                </div>
                
                <div class="chat-input-container">
                    <form class="chat-input-form" id="chatForm" action="api.php" method="POST">
                        <input 
                            type="text" 
                            class="chat-input" 
                            id="messageInput" 
                            name="message" 
                            placeholder="Type your message here..." 
                            required
                            autocomplete="off"
                        >
                        <button type="submit" class="btn-send" id="sendBtn">Send</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Processing Status -->
        <div class="status-card" id="processingStatus" style="display: none;">
            <h2>⏳ Processing...</h2>
            <div class="processing-indicator">
                <div id="stageProgress">Initializing CARMEN...</div>
                <div style="margin-top: 1rem;">
                    <span class="stage-indicator" id="stageAGAPE">AGAPE</span>
                    <span class="stage-indicator" id="stageERIS">ERIS</span>
                    <span class="stage-indicator" id="stageMETIS">METIS</span>
                </div>
            </div>
        </div>
        
        <!-- Unified Workflow Display -->
        <div class="status-card">
            <h2>Unified 3-Stage Processing Workflow (MVP)</h2>
            <p style="margin-bottom: 1.5rem; color: #6a1b9a;">CARMEN processes messages through 3 core stages internally, then returns one unified response:</p>
            
            <div class="workflow-stages">
                <div class="stage-card">
                    <div class="stage-number">1</div>
                    <div class="stage-title">AGAPE</div>
                    <div class="stage-description">Analyzes love as ACTION. What loving actions can be taken? What helps RIGHT NOW? (Not "I love you" but "I'm helping you NOW in this loving way")</div>
                </div>
                
                <div class="stage-card">
                    <div class="stage-number">2</div>
                    <div class="stage-title">ERIS</div>
                    <div class="stage-description">Identifies what makes "us" mad—finds ROOT CAUSES of conflicts, discord, frustration. (Not "you're angry" but "THIS is causing the anger")</div>
                </div>
                
                <div class="stage-card">
                    <div class="stage-number">3</div>
                    <div class="stage-title">METIS</div>
                    <div class="stage-description">Runs emotional EMPATHY through comparison—what SHOULD be vs what IS. (Not sympathy, but understanding the gap between reality and ideal)</div>
                </div>
            </div>
            
            <div class="quote-box" style="margin-top: 1.5rem;">
                <p><strong>Optional Stages (Disabled by Default):</strong></p>
                <p>4. <strong>THALIA + ROSE</strong> - Cultural humor detection (can be enabled)</p>
                <p>5. <strong>THOTH</strong> - Truth verification (can be enabled)</p>
            </div>
        </div>
        
        <!-- Agent Information -->
        <div class="status-card">
            <h2>Agent Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Version</div>
                    <div class="info-value"><?php echo CARMEN_VERSION; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Agent ID</div>
                    <div class="info-value"><?php echo CARMEN_AGENT_ID; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><?php echo CARMEN_STATUS; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Spectral Channel</div>
                    <div class="info-value" style="color: <?php echo CARMEN_SPECTRAL_CHANNEL; ?>;"><?php echo CARMEN_SPECTRAL_CHANNEL; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?php echo CARMEN_CREATED_DATE; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Core Stages</div>
                    <div class="info-value">3 Stages</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">LLM Provider</div>
                    <div class="info-value" id="llmProvider">Loading...</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">GROK Client</div>
                    <div class="info-value" id="grokStatus">Checking...</div>
                </div>
            </div>
        </div>
        
        <!-- Documentation Links -->
        <div class="status-card">
            <h2>Documentation</h2>
            <ul style="color: #6a1b9a; line-height: 1.8; margin-left: 2rem;">
                <li><a href="../../README.md" target="_blank">README.md</a> - Complete agent documentation</li>
                <li><a href="../../docs/PROCESSING_LOGIC.md" target="_blank">PROCESSING_LOGIC.md</a> - Technical workflow design</li>
                <li><a href="../../docs/MVP_APPROACH.md" target="_blank">MVP_APPROACH.md</a> - MVP strategy explanation</li>
                <li><a href="../../docs/IMPLEMENTATION_ROADMAP.md" target="_blank">IMPLEMENTATION_ROADMAP.md</a> - Implementation phases</li>
                <li><a href="../../CHANGELOG.md" target="_blank">CHANGELOG.md</a> - Version history</li>
                <li><a href="../../HANDSHAKE_GROK_EXPLAIN_CARMEN.md" target="_blank">HANDSHAKE_GROK_EXPLAIN_CARMEN.md</a> - WOLFITH-GROK handshake</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Handle form submission via AJAX
        const chatForm = document.getElementById('chatForm');
        const chatMessages = document.getElementById('chatMessages');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const processingStatus = document.getElementById('processingStatus');
        
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Add user message to chat
            addMessage('user', message);
            messageInput.value = '';
            sendBtn.disabled = true;
            
            // Show processing status
            processingStatus.style.display = 'block';
            updateStageProgress('Processing through AGAPE...', ['AGAPE']);
            
            try {
                const formData = new FormData();
                formData.append('message', message);
                formData.append('action', 'process');
                
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Add CARMEN response
                    addMessage('carmen', data.unified_response, {
                        processing_time: data.processing_time_ms + 'ms',
                        stages: data.stages_executed.join(', '),
                        tokens: data.token_usage
                    });
                    
                    // Update stage indicators
                    data.stages_executed.forEach(stage => {
                        const indicator = document.getElementById('stage' + stage);
                        if (indicator) {
                            indicator.style.background = '#c8e6c9';
                            indicator.style.borderColor = '#4caf50';
                        }
                    });
                } else {
                    addMessage('carmen', 'Error: ' + (data.error || 'Processing failed'), null, true);
                }
                
            } catch (error) {
                addMessage('carmen', 'Error: ' + error.message, null, true);
            } finally {
                sendBtn.disabled = false;
                processingStatus.style.display = 'none';
                resetStageIndicators();
            }
        });
        
        function addMessage(type, content, meta = null, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + type;
            
            let html = '<div class="message-header">' + (type === 'user' ? 'You' : 'CARMEN') + '</div>';
            html += '<div class="message-content">' + escapeHtml(content) + '</div>';
            
            if (meta) {
                html += '<div class="message-meta">';
                if (meta.processing_time) html += 'Processing time: ' + meta.processing_time + ' | ';
                if (meta.stages) html += 'Stages: ' + meta.stages + ' | ';
                if (meta.tokens) html += 'Tokens: ' + meta.tokens;
                html += '</div>';
            }
            
            messageDiv.innerHTML = html;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        function updateStageProgress(text, activeStages = []) {
            const progressDiv = document.getElementById('stageProgress');
            if (progressDiv) {
                progressDiv.textContent = text;
            }
            
            // Update stage indicators
            ['AGAPE', 'ERIS', 'METIS'].forEach(stage => {
                const indicator = document.getElementById('stage' + stage);
                if (indicator) {
                    if (activeStages.includes(stage)) {
                        indicator.style.background = '#fff3cd';
                        indicator.style.borderColor = '#ff9800';
                    } else {
                        indicator.style.background = '#f3e5f5';
                        indicator.style.borderColor = '#9b59b6';
                    }
                }
            });
        }
        
        function resetStageIndicators() {
            ['AGAPE', 'ERIS', 'METIS'].forEach(stage => {
                const indicator = document.getElementById('stage' + stage);
                if (indicator) {
                    indicator.style.background = '#f3e5f5';
                    indicator.style.borderColor = '#9b59b6';
                }
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/\n/g, '<br>');
        }
        
        // Check LLM provider status on load
        async function checkLLMStatus() {
            try {
                const response = await fetch('api.php?action=status');
                const data = await response.json();
                
                if (data.llm_provider) {
                    document.getElementById('llmProvider').textContent = data.llm_provider.toUpperCase();
                }
                
                if (data.grok_available !== undefined) {
                    document.getElementById('grokStatus').textContent = data.grok_available ? 'Available' : 'Not Available';
                    document.getElementById('grokStatus').style.color = data.grok_available ? '#4caf50' : '#f44336';
                }
            } catch (error) {
                console.error('Error checking LLM status:', error);
            }
        }
        
        // Check status on page load
        checkLLMStatus();
    </script>
</body>
</html>
