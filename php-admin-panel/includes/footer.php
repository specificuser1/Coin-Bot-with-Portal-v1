  </main><!-- end .page-content -->
</div><!-- end .main-wrap -->

<!-- Toast container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Modal container -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box" id="modalBox">
    <div class="modal-header">
      <span class="modal-title" id="modalTitle">Confirm</span>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-danger" id="modalConfirmBtn">Confirm</button>
    </div>
  </div>
</div>

<script src="/assets/js/panel.js"></script>
<script>
// Live clock
function updateClock() {
  const now = new Date();
  document.getElementById('topbarTime').textContent =
    now.toUTCString().slice(17,25) + ' UTC';
}
updateClock(); setInterval(updateClock, 1000);

// Bot status check
async function checkBotStatus() {
  try {
    const r = await fetch('/api/bot_status.php');
    const d = await r.json();
    const pill = document.getElementById('botStatusPill');
    const txt  = document.getElementById('botStatusText');
    if (d.online && !d.paused) {
      pill.className = 'status-pill online';
      txt.textContent = 'Bot Online';
    } else if (d.online && d.paused) {
      pill.className = 'status-pill paused';
      txt.textContent = 'Bot Paused';
    } else {
      pill.className = 'status-pill offline';
      txt.textContent = 'Bot Offline';
    }
  } catch(e) {
    document.getElementById('botStatusPill').className = 'status-pill offline';
    document.getElementById('botStatusText').textContent = 'DB Only';
  }
}
checkBotStatus(); setInterval(checkBotStatus, 15000);
</script>
<?php if(isset($extra_js)) echo $extra_js; ?>
</body>
</html>
