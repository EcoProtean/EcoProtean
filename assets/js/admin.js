// ── Sidebar mobile toggle ──────────────────────────────
(function () {
  const toggle  = document.getElementById('menuToggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!toggle || !sidebar || !overlay) return;
  toggle.addEventListener('click', function () {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
  });
  overlay.addEventListener('click', function () {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  });
})();

// ── Auto-dismiss alert messages ───────────────────────
document.addEventListener('DOMContentLoaded', function () {
  ['successMessage','errorMessage'].forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});

// ── Modal: View User ──────────────────────────────────
function openViewModal(user) {
  document.getElementById('viewUserId').innerText    = user.user_id;
  document.getElementById('viewName').innerText      = user.name;
  document.getElementById('viewEmail').innerText     = user.email;
  document.getElementById('viewRole').innerText      = capitalize(user.role);
  document.getElementById('viewCreated').innerText   = user.created_at;
  document.getElementById('viewUserModal').style.display = 'flex';
  window.currentViewUser = user;
}

function closeViewModal() {
  document.getElementById('viewUserModal').style.display = 'none';
}

// ── Modal: Edit User (from View) ──────────────────────
function openEditFromView() {
  const user = window.currentViewUser;
  if (!user) return;
  document.getElementById('editUserId').value = user.user_id;
  document.getElementById('editEmail').value  = user.email;
  document.getElementById('editRole').value   = user.role;
  closeViewModal();
  document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editUserModal').style.display = 'none';
}

// ── Modal: Add User ───────────────────────────────────
function openAddUserModal() {
  document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
  document.getElementById('addUserModal').style.display = 'none';
}

// ── Modal: Add Location ───────────────────────────────
function openAddLocationModal() {
  document.getElementById('addLocationModal').style.display = 'flex';
}

function closeAddLocationModal() {
  document.getElementById('addLocationModal').style.display = 'none';
}

// ── Utility ───────────────────────────────────────────
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// ── Close modals on backdrop click ────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const modals = ['addUserModal','viewUserModal','editUserModal','addLocationModal'];
  modals.forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function(e) {
      if (e.target === el) {
        el.style.display = 'none';
      }
    });
  });
});