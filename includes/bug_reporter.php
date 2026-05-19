<!-- ── Floating Bug Reporter ─────────────────────────────────────────────── -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>

<!-- Floating Button removed, now integrated into sidebar -->

<!-- Modal -->
<div id="bug-modal" class="fixed inset-0 bg-black/80 hidden items-center justify-center p-4 sm:p-10 backdrop-blur-sm"
  style="z-index: 9999;">
  <div
    class="border border-purple-500/30 rounded-2xl w-full max-w-4xl max-h-[90vh] shadow-2xl overflow-hidden grid grid-rows-[auto_minmax(0,1fr)_auto]"
    style="background-color: #0f0f1e;">

    <!-- Header -->
    <div class="px-6 py-4 border-b border-white/10 flex justify-between items-center bg-white/5">
      <h3 class="font-semibold text-white text-lg flex items-center gap-2">
        <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        Report a Bug
      </h3>
      <button onclick="closeBugReporter()" class="text-slate-400 hover:text-white transition-colors">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Body -->
    <div class="overflow-y-auto p-4 sm:p-6 flex flex-col lg:flex-row gap-5 sm:gap-6">

      <!-- Form Side -->
      <div class="flex-1 flex flex-col gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1">Description <span
              class="text-red-400">*</span></label>
          <textarea id="bug-desc" rows="4"
            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 outline-none transition-all"
            placeholder="What went wrong? Please be descriptive..."></textarea>
        </div>


        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1">Your Email <span
              class="text-slate-500 font-normal">(optional)</span></label>
          <input type="email" id="bug-email"
            class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 outline-none"
            placeholder="you@example.com">
        </div>
      </div>

      <!-- Screenshot Side -->
      <div class="flex-1 flex flex-col gap-2">
        <div class="flex justify-between items-center">
          <label class="block text-sm font-medium text-slate-300">Screenshot <span
              class="text-slate-500 font-normal">(optional)</span></label>
          <div id="screenshot-actions" class="flex gap-2 hidden">
            <button onclick="clearCanvas()"
              class="text-xs text-slate-400 hover:text-white px-2 py-1 rounded bg-white/5 hover:bg-white/10 transition-colors">Clear
              Drawing</button>
            <button onclick="takeScreenshot()"
              class="text-xs text-purple-400 hover:text-purple-300 px-2 py-1 rounded bg-purple-500/10 hover:bg-purple-500/20 transition-colors">Retake</button>
            <button onclick="removeScreenshot()"
              class="text-xs text-red-400 hover:text-red-300 px-2 py-1 rounded bg-red-500/10 hover:bg-red-500/20 transition-colors">Remove</button>
          </div>
        </div>
        <div id="canvas-container"
          class="relative w-full min-h-[200px] sm:min-h-[280px] max-h-[50vh] bg-black/40 border border-white/10 rounded-xl overflow-y-auto overflow-x-hidden flex flex-col items-center">

          <!-- Empty State -->
          <div id="screenshot-empty" class="flex flex-col items-center text-center p-6 m-auto">
            <svg class="w-10 h-10 text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p class="text-sm text-slate-400 mb-3">A screenshot helps us understand the issue better.</p>
            <button type="button" onclick="takeScreenshot()"
              class="btn-secondary px-4 py-2 rounded-xl text-sm font-medium transition-colors border border-purple-500/30 text-purple-300 hover:bg-purple-500/10">
              📸 Take a Screenshot
            </button>
          </div>

          <!-- Loading State -->
          <div id="screenshot-loading" class="text-slate-500 text-sm flex-col items-center hidden m-auto">
            <svg class="animate-spin h-6 w-6 text-purple-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none"
              viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
              </path>
            </svg>
            Capturing screen...
          </div>

          <!-- Canvas -->
          <canvas id="bug-canvas" class="cursor-crosshair w-full h-auto hidden"
            style="touch-action: none; max-height: 50vh; "></canvas>
        </div>
      </div>

    </div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-white/10 bg-white/5 flex justify-end gap-3">
      <button onclick="closeBugReporter()"
        class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-white/5 transition-colors">Cancel</button>
      <button onclick="submitBugReport()" id="btn-submit-bug"
        class="px-6 py-2 rounded-xl text-sm font-bold text-white bg-purple-600 hover:bg-purple-500 shadow-lg shadow-purple-900/30 transition-colors flex items-center gap-2">
        Submit Report
      </button>
    </div>

  </div>
</div>

<script>
  let bugCanvas, bugCtx;
  let drawing = false;
  let originalImage = null; // Store base screenshot to allow clearing drawings

  function openBugReporter() {
    document.getElementById('bug-modal').classList.remove('hidden');
    document.getElementById('bug-modal').classList.add('flex');
    // Clear previous form data
    document.getElementById('bug-desc').value = '';
    // Reset screenshot state if none taken
    if (!originalImage) {
      document.getElementById('screenshot-empty').classList.remove('hidden');
      document.getElementById('screenshot-empty').classList.add('flex');
      document.getElementById('bug-canvas').classList.add('hidden');
      document.getElementById('screenshot-actions').classList.add('hidden');
    }
  }

  function closeBugReporter() {
    document.getElementById('bug-modal').classList.add('hidden');
    document.getElementById('bug-modal').classList.remove('flex');
  }

  function removeScreenshot() {
    originalImage = null;
    bugCanvas = null;
    document.getElementById('bug-canvas').classList.add('hidden');
    document.getElementById('screenshot-actions').classList.add('hidden');
    document.getElementById('screenshot-empty').classList.remove('hidden');
    document.getElementById('screenshot-empty').classList.add('flex');
  }

  async function takeScreenshot() {
    const emptyState = document.getElementById('screenshot-empty');
    const loading = document.getElementById('screenshot-loading');
    const canvasEl = document.getElementById('bug-canvas');
    const actions = document.getElementById('screenshot-actions');

    emptyState.classList.add('hidden');
    emptyState.classList.remove('flex');
    loading.classList.remove('hidden');
    loading.classList.add('flex');
    canvasEl.classList.add('hidden');
    actions.classList.add('hidden');

    // Hide the modal while capturing
    document.getElementById('bug-modal').style.opacity = '0';

    try {
      // Small delay to ensure modal is hidden
      await new Promise(r => setTimeout(r, 100));

      const isLightMode = document.documentElement.classList.contains('light');
      const bgColor = isLightMode ? '#f8fafc' : '#0f0f1e';

      const renderedCanvas = await htmlToImage.toCanvas(document.body, {
        backgroundColor: bgColor,
        pixelRatio: window.devicePixelRatio || 1,
        skipFonts: true, // Prevents CORS failures on Google Fonts
        filter: (node) => {
          // Exclude the modal itself to prevent rendering loops or sizing issues
          if (node.id === 'bug-modal') return false;

          // Exclude external images that might trigger CORS canvas tainting
          if (node.tagName === 'IMG') {
            try {
              const src = node.getAttribute('src');
              if (src && src.startsWith('http') && !src.includes(window.location.host)) {
                return false; // Skip external images to prevent security errors
              }
            } catch (e) { }
          }
          return true;
        }
      });

      // Restore UI
      document.getElementById('bug-modal').style.opacity = '1';

      originalImage = renderedCanvas;
      initCanvas(renderedCanvas);

      loading.classList.add('hidden');
      loading.classList.remove('flex');
      canvasEl.classList.remove('hidden');
      actions.classList.remove('hidden');
      actions.classList.add('flex');

    } catch (err) {
      console.error("Screenshot failed", err);
      loading.classList.add('hidden');
      loading.classList.remove('flex');
      emptyState.classList.remove('hidden');
      emptyState.classList.add('flex');
      document.getElementById('bug-modal').style.opacity = '1';
      alert("Failed to capture screenshot: " + (err.message || err));
    }
  }

  function initCanvas(sourceCanvas) {
    bugCanvas = document.getElementById('bug-canvas');
    bugCtx = bugCanvas.getContext('2d');

    // Set logical size to match intrinsic image size
    bugCanvas.width = sourceCanvas.width;
    bugCanvas.height = sourceCanvas.height;

    // Draw base image
    bugCtx.drawImage(sourceCanvas, 0, 0);

    // Setup drawing events
    bugCtx.strokeStyle = '#ef4444'; // Red pen
    bugCtx.lineWidth = 4 * (sourceCanvas.width / 800); // Scale pen size
    bugCtx.lineCap = 'round';
    bugCtx.lineJoin = 'round';

    // Remove existing listeners to prevent duplicates if retaking
    bugCanvas.onmousedown = null;
    bugCanvas.onmousemove = null;
    bugCanvas.onmouseup = null;
    bugCanvas.onmouseout = null;

    bugCanvas.addEventListener('mousedown', startDrawing);
    bugCanvas.addEventListener('mousemove', draw);
    bugCanvas.addEventListener('mouseup', stopDrawing);
    bugCanvas.addEventListener('mouseout', stopDrawing);

    // Touch support
    bugCanvas.addEventListener('touchstart', handleTouchStart, { passive: false });
    bugCanvas.addEventListener('touchmove', handleTouchMove, { passive: false });
    bugCanvas.addEventListener('touchend', stopDrawing);
  }

  function getMousePos(evt) {
    const rect = bugCanvas.getBoundingClientRect();
    // Calculate scale since canvas is scaled down via CSS object-contain
    const scaleX = bugCanvas.width / rect.width;
    const scaleY = bugCanvas.height / rect.height;

    // If object-fit: contain is used, we need to calculate the actual image boundaries within the element.
    // A simpler approximation for standard width-constrained elements:
    return {
      x: (evt.clientX - rect.left) * scaleX,
      y: (evt.clientY - rect.top) * scaleY
    };
  }

  function startDrawing(e) {
    drawing = true;
    const pos = getMousePos(e);
    bugCtx.beginPath();
    bugCtx.moveTo(pos.x, pos.y);
  }

  function draw(e) {
    if (!drawing) return;
    const pos = getMousePos(e);
    bugCtx.lineTo(pos.x, pos.y);
    bugCtx.stroke();
  }

  function stopDrawing() {
    if (drawing) {
      bugCtx.stroke();
      bugCtx.closePath();
      drawing = false;
    }
  }

  // Touch handlers map touch to mouse
  function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent("mousedown", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    bugCanvas.dispatchEvent(mouseEvent);
  }

  function handleTouchMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent("mousemove", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    bugCanvas.dispatchEvent(mouseEvent);
  }

  function clearCanvas() {
    if (originalImage && bugCtx) {
      bugCtx.clearRect(0, 0, bugCanvas.width, bugCanvas.height);
      bugCtx.drawImage(originalImage, 0, 0);
    }
  }

  async function submitBugReport() {
    const desc = document.getElementById('bug-desc').value.trim();
    const email = document.getElementById('bug-email').value.trim();

    if (!desc) {
      alert("Please provide a description.");
      return;
    }

    const btn = document.getElementById('btn-submit-bug');
    btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Submitting...';
    btn.disabled = true;

    // Get base64 from canvas
    let base64Image = '';
    if (bugCanvas) {
      base64Image = bugCanvas.toDataURL('image/jpeg', 0.7); // Compress to jpeg to save bandwidth
    }

    try {
      const res = await fetch('bug_report_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          description: desc,
          email: email,
          image: base64Image
        })
      });

      const data = await res.json();
      if (res.ok) {
        alert(data.message + (data.ticket_url ? "\n\nTicket URL: " + data.ticket_url : ""));
        closeBugReporter();
      } else {
        alert("Error: " + data.error);
      }
    } catch (err) {
      alert("An error occurred submitting the bug.");
    } finally {
      btn.innerHTML = 'Submit Report';
      btn.disabled = false;
    }
  }
</script>