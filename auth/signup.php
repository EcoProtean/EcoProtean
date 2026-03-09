<?php
session_start();
require_once '../config.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!$terms) {
        $error = 'You must agree to the Terms of Service and Privacy Policy.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            // Combine first and last name
            $fullName = $firstName . ' ' . $lastName;
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user with default role 'user'
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'user')");
            $stmt->bind_param('sss', $fullName, $email, $hashedPassword);
            
            if ($stmt->execute()) {
                $success = 'Account created successfully! Redirecting to login...';
                
                // Redirect to login page after 2 seconds
                header("refresh:2;url=login.php");
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoProtean - Sign Up</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="signup-container">
        <div class="logo-container">
            <!-- Insert your EPP logo here -->
            <img src="../image.png" alt="EPP Logo" id="logoImage">
        </div>
        
        <div class="signup-header">
            <h1>Join EcoProtean</h1>
            <p>Start your journey to a sustainable future</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form id="signupForm" method="POST" action="signup.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name</label>
                    <input 
                        type="text" 
                        id="firstName" 
                        name="firstName" 
                        placeholder="John"
                        value="<?php echo htmlspecialchars($_POST['firstName'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="lastName">Last Name</label>
                    <input 
                        type="text" 
                        id="lastName" 
                        name="lastName" 
                        placeholder="Doe"
                        value="<?php echo htmlspecialchars($_POST['lastName'] ?? ''); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="john.doe@example.com"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Create a strong password"
                        required
                    >
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg id="eyeOffIcon" viewBox="0 0 24 24" class="hidden">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strengthBar"></div>
                </div>
            </div>

            <label class="terms-checkbox">
                <input type="checkbox" id="terms" name="terms" required>
                <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
            </label>

            <button type="submit" class="signup-button">Create Account</button>
        </form>

        <div class="divider">or</div>

        <div class="login-link">
            Already have an account? <a href="login.php">Log in</a>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>