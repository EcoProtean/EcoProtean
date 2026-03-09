<?php
session_start();
require_once 'config.php';

// Already logged in → redirect to home
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $conn->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        // Support both plain-text (legacy sample data) and hashed passwords
        $valid = $user && (
            password_verify($password, $user['password']) ||
            $password === $user['password']
        );

        if ($valid) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            logActivity($conn, $user['user_id'], 'LOGIN', $user['full_name'] . ' logged into the system');

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <title>Login - EcoProtean</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #2c5f5d 0%, #1b9e9b 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-box {
      background: #fff;
      border-radius: 20px;
      padding: 50px 45px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .logo-area {
      text-align: center;
      margin-bottom: 35px;
    }
    .logo-area .logo-text {
      font-size: 2rem;
      font-weight: 700;
      color: #2c5f5d;
    }
    .logo-area .tagline {
      font-size: 0.75rem;
      color: #888;
    }
    h2 {
      font-size: 1.4rem;
      color: #333;
      margin-bottom: 25px;
      text-align: center;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      font-size: 0.9rem;
      font-weight: 500;
      color: #555;
      margin-bottom: 6px;
    }
    input {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.95rem;
      transition: border-color 0.3s;
      outline: none;
    }
    input:focus { border-color: #1b9e9b; }
    .btn {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #2c5f5d, #1b9e9b);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.3s;
      margin-top: 5px;
    }
    .btn:hover { opacity: 0.9; }
    .error {
      background: #fdecea;
      color: #c0392b;
      padding: 12px 15px;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-bottom: 20px;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="logo-area">
      <div class="logo-text">EcoProtean</div>
      <div class="tagline">Guarding the Land, Growing the Future</div>
    </div>
    <h2>Sign In</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn">Login</button>
    </form>
  </div>
</body>
</html>
