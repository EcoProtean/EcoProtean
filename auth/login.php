<?php
session_start();
require_once '../config/config.php';

// --- GOOGLE/AUTH0 LOGIC REMOVED FROM HERE ---

$error = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($password, $user['password'])) {
            // Password is correct - create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Update last_login in the database
            $stmt_update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt_update->bind_param("i", $user['user_id']);
            $stmt_update->execute();
            $stmt_update->close();
      
            // Handle "Remember me"
            if ($remember) {
                setcookie('user_email', $email, time() + (30 * 24 * 60 * 60), '/');
            }
            
            // Log the login activity
            logActivity($conn, $user['user_id'], 'login', 'User logged in successfully');
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: ../admin/index.php');
            } elseif ($user['role'] === 'manager') {
                header('Location: ../management/index.php');
            } else {
                header('Location: ../webapp/riskmap/index.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}

// Pre-fill email if cookie exists
$savedEmail = $_COOKIE['user_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoProtean - Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../assets/images/logo.png" alt="EcoProtean Logo">
        </div>
        
        <div class="login-header">
            <h1>EcoProtean</h1>
            <p>Sustainable solutions for a greener future</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($savedEmail); ?>"
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
                        placeholder="Enter your password"
                        required
                    >
                    <button type="button" class="toggle-password" id="togglePassword" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" fill="none" stroke-width="2"></path>
                            <circle cx="12" cy="12" r="3" stroke="currentColor" fill="none" stroke-width="2"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" id="remember" name="remember" <?php echo $savedEmail ? 'checked' : ''; ?>>
                    <span>Remember me</span>
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" class="login-button">Login</button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>
    </div>

    <script src="../assets/js/login.js"></script>
</body>
</html>