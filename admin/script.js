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


