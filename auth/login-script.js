const password = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');
const eyeIcon = document.getElementById('eyeIcon');
const eyeOffIcon = document.getElementById('eyeOffIcon');

// Toggle password visibility
if (togglePassword) {
    togglePassword.addEventListener('click', function() {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        // Toggle eye icons using class
        eyeIcon.classList.toggle('hidden');
        eyeOffIcon.classList.toggle('hidden');
    });
}