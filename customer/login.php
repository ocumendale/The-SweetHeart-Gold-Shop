<?php 
session_start();
$conn = new mysqli("localhost", "root", "", "practicegoldshop");

if (isset($_POST['login'])) {
  $username = $_POST['username'];
  $password = $_POST['password'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['role'] = $user['role'];

      // 🔒 Redirect based on role
      if ($user['role'] === 'admin') {
        header("Location: ../AdminDashboard/smpAdminwithBE/dashboard.php");
        exit;
      } else {
        header("Location: index.php");
        exit;
      }

    } else {
      $error = "Wrong password!";
    }
  } else {
    $error = "User not found!";
    echo "Entered username: " . $username;
  }

}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - SweetHeart Gold Shop</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="CSS/Login.css">
</head>
<body>

<!-- Login Container -->
<div class="login-container fade-in">
  <div class="login-header">
    <h1 class="login-title">Welcome!</h1>
    <div class="luxury-divider"></div>
    <p class="login-subtitle">Sign in to your account</p>
  </div>

  <form action="" method="post">
    <?php if (!empty($error)): ?>
      <div class="error-message">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="form-group">
      <label for="username">Email</label>
      <input type="text" name="username" id="username" required>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <div class="password-container">
        <input type="password" name="password" id="password" required>
        <i class="fas fa-eye password-toggle" id="password-toggle" onclick="togglePassword()"></i>
      </div>
    </div>

    <button type="submit" name="login" class="login-button">
      <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
      Sign In
    </button>
  </form>

  <div class="register-link">
    <p>Don't have an account yet?</p>
    <a href="register.php">Create your account</a>
  </div>
</div>

<script>
  function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-toggle');

    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      passwordToggle.classList.remove('fa-eye-slash');
      passwordToggle.classList.add('fa-eye');
    } else {
      passwordInput.type = 'password';
      passwordToggle.classList.remove('fa-eye');
      passwordToggle.classList.add('fa-eye-slash');
    }
  }

  // Smooth scrolling for navigation links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Add fade-in animation on page load
  document.addEventListener('DOMContentLoaded', function() {
    const loginContainer = document.querySelector('.login-container');
    loginContainer.classList.add('fade-in');
  });
</script>

</body>
</html>