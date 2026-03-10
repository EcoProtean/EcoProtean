// Wait until the page is loaded
document.addEventListener('DOMContentLoaded', function() {
    const msg = document.getElementById('successMessage');
    const err = document.getElementById('errorMessage');

    if (msg) {
      // Hide after 4 seconds (4000ms)
      setTimeout(() => {
        msg.style.transition = 'opacity 0.5s ease';
        msg.style.opacity = '0';
        // Remove from DOM after fade-out
        setTimeout(() => msg.remove(), 500);
      }, 4000);
    }
    if (err) {
      setTimeout(() => {
        err.style.transition = 'opacity 0.5s ease';
        err.style.opacity = '0';
        setTimeout(() => err.remove(), 500);
      }, 4000);
    }
});


// Open View Modal
function openViewModal(user) {
    document.getElementById('viewUserId').innerText = user.user_id;
    document.getElementById('viewName').innerText = user.name;
    document.getElementById('viewEmail').innerText = user.email;
    document.getElementById('viewRole').innerText = capitalize(user.role);
    document.getElementById('viewCreated').innerText = user.created_at;

    document.getElementById('viewUserModal').style.display = 'flex';
    window.currentViewUser = user;
}

// Close View Modal
function closeViewModal() {
    document.getElementById('viewUserModal').style.display = 'none';
}

// Open Edit Modal from View
function openEditFromView() {
    const user = window.currentViewUser;
    if (!user) return;

    document.getElementById('editUserId').value = user.user_id;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editRole').value = user.role;

    closeViewModal();
    document.getElementById('editUserModal').style.display = 'flex';
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
