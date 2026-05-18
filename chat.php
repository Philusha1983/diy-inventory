<?php
/**
 * chat.php — Interactive Lab Assistant (Phase 8)
 * Full-page chat UI. Single-file: HTML + CSS + JS.
 */
session_start();
if (!isset($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lab Assistant — DIY Lab</title>
  <meta name="description" content="AI-powered inventory-aware brainstorming assistant for your DIY lab.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    html, body { height:100%; margin:0; }
    /* Chat layout */
    #chat-panel { position:fixed; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; transition:left .3s ease; }
    @media(min-width:1024px){ #chat-panel { left:256px; } }
    #chat-window { flex:1; overflow-y:auto; padding:2rem; display:flex; flex-direction:column; gap:1rem; }
    #chat-window::-webkit-scrollbar { width:6px; }
    #chat-window::-webkit-scrollbar-track { background:transparent; }
    #chat-window::-webkit-scrollbar-thumb { background:rgba(124,58,237,.3); border-radius:3px; }
    /* Bubbles */
    .msg-user {
      align-self:flex-end; max-width:70%;
      background:linear-gradient(135deg,#7c3aed,#5b21b6);
      color:white; padding:.75rem 1rem; border-radius:18px 18px 4px 18px;
      font-size:.9rem; line-height:1.5; box-shadow:0 4px 20px rgba(124,58,237,.3);
    }
    .msg-ai {
      align-self:flex-start; max-width:80%;
      background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07);
      color:#cbd5e1; padding:.75rem 1rem; border-radius:4px 18px 18px 18px;
      font-size:.9rem; line-height:1.6;
    }
    .msg-ai p { margin:.4rem 0; }
    .msg-ai p:first-child { margin-top:0; }
    .msg-ai p:last-child { margin-bottom:0; }
    .msg-ai code { font-family:'JetBrains Mono',monospace; font-size:.78rem; background:rgba(124,58,237,.2); border:1px solid rgba(124,58,237,.25); color:#c4b5fd; padding:.15rem .4rem; border-radius:.3rem; }
    .msg-ai pre { background:#0d0d1f; border:1px solid rgba(124,58,237,.2); border-left:3px solid #7c3aed; border-radius:8px; padding:1rem; margin:.75rem 0; overflow-x:auto; }
    .msg-ai pre code { background:none; border:none; color:#a5f3fc; }
    .msg-ai strong { color:#f1f5f9; }
    .msg-ai ul { margin:.5rem 0 .5rem 1.25rem; list-style:disc; }
    .msg-ai li { margin:.2rem 0; }
    .msg-ai h3 { color:#e2e8f0; font-weight:600; margin:.75rem 0 .3rem; }
    .msg-system {
      align-self:center; font-size:.75rem; color:#475569;
      background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.05);
      border-radius:999px; padding:.25rem .75rem;
    }
    /* Input area */
    #input-area {
      padding:1rem; border-top:1px solid rgba(255,255,255,.06);
      background:rgba(10,10,26,.9); backdrop-filter:blur(12px);
    }
    @media(min-width:640px){ #input-area { padding:1.25rem 2rem; } }
    .input-field {
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      color:#e2e8f0; resize:none; font-family:'Inter',sans-serif;
      transition:border-color .2s, box-shadow .2s;
    }
    .btn-send { background:linear-gradient(135deg,#7c3aed,#06b6d4); transition:all .2s; }
    .btn-send:hover:not(:disabled) { opacity:.9; transform:translateY(-1px); }
    .btn-send:disabled { opacity:.4; cursor:not-allowed; }
    .typing-indicator { display:flex; gap:5px; align-items:center; padding:.5rem; }
    .typing-dot { width:8px; height:8px; background:#7c3aed; border-radius:50%; animation:typingBounce .9s infinite ease-in-out; }
    .typing-dot:nth-child(2) { animation-delay:.2s; }
    .typing-dot:nth-child(3) { animation-delay:.4s; }
    @keyframes typingBounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-8px)} }
    /* Suggestion chips */
    .chip {
      font-size:.75rem; padding:.35rem .85rem; border-radius:999px;
      border:1px solid rgba(124,58,237,.3); color:#c4b5fd;
      background:rgba(124,58,237,.1); cursor:pointer; white-space:nowrap;
      transition:all .15s;
    }
    .chip:hover { background:rgba(124,58,237,.25); border-color:rgba(124,58,237,.5); }
  </style>
</head>
<body class="bg-grid">
  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
  <!-- Sidebar -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
        </div>
        <div><p class="font-semibold text-white text-sm" data-i18n-text="nav.brand_name">DIY Lab</p><p class="text-xs text-slate-500" data-i18n-text="nav.brand_sub">Inventory System</p></div>
      </div>
    </div>
    <nav class="flex-1 p-4 space-y-1">
      <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard</a>
      <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component</a>
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707"/></svg> Creative Engine</a>
      <a href="chat.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings</a>
    </nav>
    <div class="p-4 border-t border-white/5">
      <div class="theme-toggle-wrap mb-2" onclick="toggleTheme()" role="button" aria-label="Toggle light mode" title="Toggle light/dark mode">
        <span class="theme-toggle-label">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <span data-i18n-text="nav.light_mode">Light Mode</span>
        </span>
        <span class="toggle-pill"></span>
      </div>
      <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Logout
      </a>
    </div>
  </div>

  <!-- Chat Panel -->
  <div id="chat-panel">
    <!-- Header -->
    <div class="glass border-b border-white/5 px-4 py-3.5 flex items-center justify-between flex-shrink-0">
      <div class="flex items-center gap-3">
        <button onclick="openSidebar()" class="lg:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="relative">
          <div class="w-8 h-8 rounded-full bg-gradient-to-br from-purple-500 to-cyan-400 flex items-center justify-center">
            <span class="text-sm">🤖</span>
          </div>
          <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-400 rounded-full border-2 border-slate-900"></span>
        </div>
        <div>
          <p class="font-semibold text-white text-sm" data-i18n-text="chat.title">Lab Planning Assistant</p>
          <p class="text-xs text-emerald-400"><span data-i18n-text="chat.online">Online</span> · Inventory-aware</p>
        </div>
      </div>
        <button onclick="clearChat()" class="text-xs text-slate-500 hover:text-slate-300 transition-colors border border-white/10 hover:border-white/20 px-3 py-1.5 rounded-lg" data-i18n-text="chat.clear">Clear Chat</button>
    </div>

    <!-- Messages -->
    <div id="chat-window">
      <div class="msg-system">Start of conversation</div>
      <div class="msg-ai">
        👋 Hey! I'm your DIY Lab Planning Assistant.<br><br>
        I have full access to your current inventory. Ask me anything — project ideas, component questions, wiring help, code snippets, or troubleshooting!
      </div>

      <!-- Suggestion chips -->
      <div class="flex flex-wrap gap-2 pl-1 mt-1">
        <button class="chip" onclick="sendChip(this)">What can I build with ESP32?</button>
        <button class="chip" onclick="sendChip(this)">Suggest a beginner IoT project</button>
        <button class="chip" onclick="sendChip(this)">What's the best sensor for temperature?</button>
        <button class="chip" onclick="sendChip(this)">Show me all my microcontrollers</button>
        <button class="chip" onclick="sendChip(this)">What components am I missing for a robot?</button>
      </div>
    </div>

    <!-- Input -->
    <div id="input-area">
      <div class="flex gap-3 items-end">
        <textarea
          id="user-input"
          placeholder="Ask anything about your lab inventory…"
          rows="1"
          class="input-field flex-1 rounded-2xl px-4 py-3 text-sm"
          onkeydown="handleKey(event)"
          oninput="autoResize(this)"
        ></textarea>
        <button id="send-btn" onclick="sendMessage()" class="btn-send w-11 h-11 rounded-2xl flex items-center justify-center text-white flex-shrink-0" aria-label="Send message">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
        </button>
      </div>
      <p class="text-xs text-slate-600 mt-2 text-center">Shift+Enter for new line · Enter to send</p>
    </div>
  </div>

  <script>
  const chatWindow = document.getElementById('chat-window');
  const userInput  = document.getElementById('user-input');
  const sendBtn    = document.getElementById('send-btn');

  function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  }

  function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
  }

  function sendChip(btn) {
    userInput.value = btn.textContent;
    sendMessage();
  }

  function clearChat() {
    if (!confirm('Clear the conversation history?')) return;
    chatWindow.innerHTML = '<div class="msg-system">Conversation cleared</div>';
    const ai = document.createElement('div');
    ai.className = 'msg-ai';
    ai.textContent = "Fresh start! What are you building today?";
    chatWindow.appendChild(ai);
  }

  function scrollBottom() {
    chatWindow.scrollTop = chatWindow.scrollHeight;
  }

  async function sendMessage() {
    const text = userInput.value.trim();
    if (!text) return;

    // Add user bubble
    const userBubble = document.createElement('div');
    userBubble.className = 'msg-user';
    userBubble.textContent = text;
    chatWindow.appendChild(userBubble);

    userInput.value = '';
    userInput.style.height = 'auto';
    sendBtn.disabled = true;
    scrollBottom();

    // Remove suggestion chips
    document.querySelectorAll('.chip').forEach(c => c.parentElement?.remove());

    // Typing indicator
    const typingEl = document.createElement('div');
    typingEl.className = 'msg-ai';
    typingEl.innerHTML = '<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>';
    chatWindow.appendChild(typingEl);
    scrollBottom();

    try {
      const res  = await fetch('chat_api.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ message: text }),
      });
      const data = await res.json();

      typingEl.remove();

      const aiBubble = document.createElement('div');
      aiBubble.className = 'msg-ai';
      aiBubble.innerHTML  = marked.parse(data.reply || 'Sorry, I had trouble responding. Try again.');
      chatWindow.appendChild(aiBubble);
    } catch (err) {
      typingEl.remove();
      const errBubble = document.createElement('div');
      errBubble.className = 'msg-ai';
      errBubble.innerHTML = '❌ Connection error. Check your API settings.';
      chatWindow.appendChild(errBubble);
      console.error(err);
    } finally {
      sendBtn.disabled = false;
      userInput.focus();
      scrollBottom();
    }
  }
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  localizationController.init();
  </script>
</body>
</html>
