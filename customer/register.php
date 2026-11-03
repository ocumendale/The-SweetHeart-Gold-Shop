<?php
$conn = new mysqli("localhost", "root", "", "practicegoldshop");

$message = "";

if (isset($_POST['register'])) {
  $username = $_POST['username'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
  $check->bind_param("s", $username);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    $message = "Username already taken!";
    $message_type = "error";
  } else {
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $password);
    if ($stmt->execute()) {
      $message = "Registration successful!";
      $message_type = "success";
    } else {
      $message = "Registration failed. Try again.";
      $message_type = "error";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - SweetHeart Gold Shop</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="CSS/Register.css">
</head>
<body>

<!-- Register Container -->
<div class="register-container fade-in">
  <div class="register-header">
    <h1 class="register-title">Create Account</h1>
    <div class="luxury-divider"></div>
    <p class="register-subtitle">Join our exclusive community</p>
  </div>

  <form method="post">
    <?php if (!empty($message)): ?>
      <div class="message <?= $message_type ?>">
        <?php if ($message_type == 'success'): ?>
          <i class="fas fa-check-circle"></i>
        <?php else: ?>
          <i class="fas fa-exclamation-circle"></i>
        <?php endif; ?>
        <?= htmlspecialchars($message) ?>
        <?php if ($message_type == 'success'): ?>
          <a href="login.php" style="margin-left: 0.5rem;">Login now</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="username">Email Address</label>
      <input type="text" name="username" id="username" required>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="password-container">
        <input type="password" name="password" id="password" required>
        <i class="fas fa-eye password-toggle" id="password-toggle" onclick="togglePassword()"></i>
      </div>
      <div class="password-strength">
        <div class="password-strength-bar" id="password-strength-bar"></div>
      </div>
      <div class="password-hint">
        Password should be at least 8 characters long
      </div>
    </div>

    <button type="submit" name="register" class="register-button">
      <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
      Create Account
    </button>
  </form>

  <div class="login-link">
    <p>Already have an account?</p>
    <a href="login.php">Sign in here</a>
  </div>
</div>

<script>
  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-toggle');

    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      passwordToggle.classList.remove('fa-eye');
      passwordToggle.classList.add('fa-eye-slash');
    } else {
      passwordInput.type = 'password';
      passwordToggle.classList.remove('fa-eye-slash');
      passwordToggle.classList.add('fa-eye');
    }
  }

  // Password strength indicator
  document.getElementById('password').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength-bar');
    
    let strength = 0;
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;

    strengthBar.className = 'password-strength-bar';
    
    if (strength <= 2) {
      strengthBar.classList.add('weak');
    } else if (strength <= 4) {
      strengthBar.classList.add('medium');
    } else {
      strengthBar.classList.add('strong');
    }
  });

  // Add fade-in animation on page load
  document.addEventListener('DOMContentLoaded', function() {
    const registerContainer = document.querySelector('.register-container');
    registerContainer.classList.add('fade-in');
  });
</script>

</body>
</html>