const password = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const togglePassword = document.getElementById('togglePassword');
const eyeIcon = document.getElementById('eyeIcon');
const eyeOffIcon = document.getElementById('eyeOffIcon');

// Toggle password visibility
togglePassword.addEventListener('click', function() {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);
    
    // Toggle eye icons using class
    eyeIcon.classList.toggle('hidden');
    eyeOffIcon.classList.toggle('hidden');
});

// Password strength checker
password.addEventListener('input', function() {
    const value = this.value;
    let strength = 0;

    if (value.length >= 8) strength++;
    if (value.match(/[a-z]/) && value.match(/[A-Z]/)) strength++;
    if (value.match(/[0-9]/)) strength++;
    if (value.match(/[^a-zA-Z0-9]/)) strength++;

    strengthBar.className = 'password-strength-bar';
    
    if (strength === 0 || value.length === 0) {
        strengthBar.className = 'password-strength-bar';
    } else if (strength <= 2) {
        strengthBar.classList.add('weak');
    } else if (strength === 3) {
        strengthBar.classList.add('medium');
    } else {
        strengthBar.classList.add('strong');
    }
});