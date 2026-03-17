// ═══════════════════════════════════════════════════════════
//   VC COIN EARNER — ADMIN PANEL JS
//   Programmed by SUBHAN
// ═══════════════════════════════════════════════════════════

// ─── SIDEBAR TOGGLE ──────────────────────────────────────
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
}

// ─── TOAST NOTIFICATIONS ─────────────────────────────────
function showToast(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  const icons = { success: '✅', error: '❌', info: 'ℹ️', warn: '⚠️' };
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('fadeout');
    setTimeout(() => toast.remove(), 350);
  }, duration);
}

// ─── MODAL ───────────────────────────────────────────────
let _modalCallback = null;
function openModal(title, body, confirmLabel = 'Confirm', danger = true, callback = null) {
  document.getElementById('modalTitle').textContent = title;
  document.getElementById('modalBody').innerHTML = body;
  const confirmBtn = document.getElementById('modalConfirmBtn');
  confirmBtn.textContent = confirmLabel;
  confirmBtn.className = 'btn ' + (danger ? 'btn-danger' : 'btn-primary');
  _modalCallback = callback;
  document.getElementById('modalOverlay').classList.add('open');
}
function closeModal() {
  document.getElementById('modalOverlay').classList.remove('open');
  _modalCallback = null;
}
document.getElementById('modalConfirmBtn').addEventListener('click', () => {
  if (_modalCallback) _modalCallback();
  closeModal();
});
document.getElementById('modalOverlay').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) closeModal();
});

// ─── COPY TO CLIPBOARD ───────────────────────────────────
function copyText(text, label = 'Copied!') {
  navigator.clipboard.writeText(text).then(() => {
    showToast(label, 'success', 2000);
  }).catch(() => {
    // Fallback
    const el = document.createElement('textarea');
    el.value = text; document.body.appendChild(el);
    el.select(); document.execCommand('copy');
    document.body.removeChild(el);
    showToast(label, 'success', 2000);
  });
}

// ─── FETCH WRAPPER ───────────────────────────────────────
async function apiPost(url, data = {}) {
  try {
    const resp = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data)
    });
    return await resp.json();
  } catch (e) {
    return { success: false, error: 'Network error' };
  }
}

async function apiGet(url) {
  try {
    const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return await resp.json();
  } catch (e) {
    return { success: false, error: 'Network error' };
  }
}

// ─── SEARCH/FILTER TABLES ────────────────────────────────
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  input.addEventListener('input', () => {
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
  });
}

// ─── TOGGLE SWITCH ───────────────────────────────────────
function initToggle(id, callback) {
  const el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('click', () => {
    el.classList.toggle('on');
    if (callback) callback(el.classList.contains('on'));
  });
}

// ─── AUTO-REFRESH ─────────────────────────────────────────
function autoRefresh(intervalMs, callback) {
  callback();
  return setInterval(callback, intervalMs);
}

// ─── CONFIRM DELETE ───────────────────────────────────────
function confirmDelete(itemName, callback) {
  openModal(
    '⚠️ Confirm Delete',
    `<p>Are you sure you want to delete <strong>${itemName}</strong>? This action cannot be undone.</p>`,
    'Delete', true, callback
  );
}

// ─── FORMAT NUMBERS ───────────────────────────────────────
function formatNum(n) {
  if (n >= 1e6) return (n/1e6).toFixed(1) + 'M';
  if (n >= 1e3) return (n/1e3).toFixed(1) + 'K';
  return n.toLocaleString();
}

// ─── SIMPLE BAR CHART ────────────────────────────────────
function drawBarChart(canvasId, labels, values, color = '#5865f2') {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = canvas.width, H = canvas.height;
  const max = Math.max(...values, 1);
  const barW = W / values.length;
  const pad = 4;
  ctx.clearRect(0,0,W,H);
  values.forEach((v, i) => {
    const barH = (v / max) * (H - 20);
    const x = i * barW + pad;
    const y = H - barH;
    const grad = ctx.createLinearGradient(0, y, 0, H);
    grad.addColorStop(0, color);
    grad.addColorStop(1, color + '44');
    ctx.fillStyle = grad;
    ctx.beginPath();
    ctx.roundRect(x, y, barW - pad*2, barH, [3,3,0,0]);
    ctx.fill();
  });
}

// ─── COUNTDOWN TIMER ─────────────────────────────────────
function startCountdown(elementId, seconds) {
  const el = document.getElementById(elementId);
  if (!el) return;
  const tick = () => {
    el.textContent = seconds + 's';
    if (seconds <= 0) { location.reload(); return; }
    seconds--;
    setTimeout(tick, 1000);
  };
  tick();
}

// ─── PAGE LOAD ANIMATION ─────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.stat-card, .card').forEach((el, i) => {
    el.style.animationDelay = (i * 0.05) + 's';
    el.classList.add('fade-in');
  });
  // Init table searches
  filterTable('coinSearch', 'coinTable');
  filterTable('keySearch', 'keyTable');
  filterTable('blSearch', 'blTable');
  filterTable('memberSearch', 'memberTable');
});
