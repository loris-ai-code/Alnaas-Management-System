<?php
session_start();
include 'alnaasDB.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | ALNAAS System</title>
<style>

body {
    font-family: 'Poppins', sans-serif;
    display: flex;
    height: 100vh;
    justify-content: center;
    align-items: center;
    margin: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    animation: bgChange 25s infinite alternate linear; 
}

@keyframes bgChange {
    0%   { background-image: url('assets/pic1.jpg'); }
    25%  { background-image: url('assets/pic2.jpg'); }
    50%  { background-image: url('assets/pic3.jpg'); }
    75%  { background-image: url('assets/pic4.jpg'); }
    100% { background-image: url('assets/pic5.jpg'); }
}

/*  Frosted Glass Login Box */
.login-container {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 45px 40px 35px;
    border-radius: 15px;
    box-shadow: 0 0 25px rgba(0,0,0,0.4);
    width: 330px;
    text-align: center;
    position: relative;
    z-index: 1;
    color: #fff;
    animation: fadeIn 5s ease;
}

/*  Logo Style */
.login-logo {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 50%;
    background: transparent;
    margin-bottom: 15px;
    overflow: hidden;
    animation: glow 3s infinite ease-in-out;
}
@keyframes glow {
    0%, 100% { box-shadow: 0 0 10px rgba(255,215,0,0.3); }
    50% { box-shadow: 0 0 25px rgba(255,215,0,0.6); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/*  Text and Form Styling */
h1 { 
    margin-bottom: 25px; 
    font-size: 22px; 
    font-weight: 600; 
    color: #fff; 
    letter-spacing: 1px; 
}

input {
    width: 90%;
    padding: 10px;
    margin: 8px 0;
    border: none;
    border-radius: 8px;
    text-align: center;
    font-size: 14px;
    outline: none;
}

input[type="submit"] {
    background: #004AAD;
    color: white;
    cursor: pointer;
    transition: 0.3s;
    font-weight: 600;
}
input[type="submit"]:hover { background: #00317A; }

a.forgot { 
    display: block; 
    margin-top: 10px; 
    color: #fff; 
    font-size: 0.9em; 
    text-decoration: underline; 
}
a.forgot:hover { color: #ffdd99; }

.error { 
    color: #ff9999; 
    margin-top: 10px; 
    font-weight: 500; 
}
</style>
</head>
<body>
<div class="login-container">
  <img src="assets/PTPlogo.jpg" alt="PTP Logo" class="login-logo">
  <h1>ALNAAS LOGIN PAGE</h1>

  <form method="POST">
      <input type="text" name="username" placeholder="Enter your name" required><br>
      <input type="password" name="password" placeholder="Password please" required><br>
      <input type="submit" value="Login" name="login">
  </form>

  <a href="create_password.php" class="forgot">Forgot Password?</a>

  <?php
  if (isset($_POST['login'])) {
      $username = trim($_POST['username']);
      $password = $_POST['password'];

      $stmt = $conn->prepare("SELECT ptpID, name, password, role, status FROM users WHERE name = ?");
      $stmt->bind_param("s", $username);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows === 1) {
          $row = $result->fetch_assoc();
          $storedPass = $row['password'];

          if ($password === $storedPass || password_verify($password, $storedPass)) {
              if ($row['status'] === 'Inactive') {
                  echo "<p class='error'> Account inactive. Contact admin.</p>";
              } else {
                  $_SESSION['ptpID'] = $row['ptpID'];
                  $_SESSION['username'] = $row['name'];
                  $_SESSION['role'] = $row['role'];
                  $conn->query("UPDATE users SET last_login = NOW() WHERE ptpID = {$row['ptpID']}");
                  header("Location: alnaasDash.php");
                  exit();
              }
          } else {
              echo "<p class='error'> Wrong password. Try again!</p>";
          }
      } else {
          echo "<p class='error'> Username not found!</p>";
      }

      $stmt->close();
      $conn->close();
  }
  ?>
</div>
</body>
</html>
