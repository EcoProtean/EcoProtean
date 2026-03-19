document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const eyeSlash = document.getElementById('eyeSlash');

    if (togglePassword && password && eyeSlash) {
        togglePassword.addEventListener('click', function () {
            const isVisible = password.getAttribute('type') === 'text';
            password.setAttribute('type', isVisible ? 'password' : 'text');
            eyeSlash.style.display = isVisible ? 'none' : 'block';
        });
    }
});