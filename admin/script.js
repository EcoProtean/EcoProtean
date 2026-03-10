// Wait until the page is loaded
document.addEventListener('DOMContentLoaded', function() {
    const msg = document.getElementById('successMessage');
    if (msg) {
      // Hide after 4 seconds (4000ms)
      setTimeout(() => {
        msg.style.transition = 'opacity 0.5s ease';
        msg.style.opacity = '0';
        // Remove from DOM after fade-out
        setTimeout(() => msg.remove(), 500);
      }, 4000);
    }
});
