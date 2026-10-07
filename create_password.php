<?php
include 'alnaasDB.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT ptpID FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 1) {
        $token = bin2hex(random_bytes(50)); // secure token

        // Update token and expiry in DB
        $update = $conn->prepare("UPDATE users SET reset_token = ?, token_expire = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
        $update->bind_param("ss", $token, $email);
        if($update->execute()) {
            // Display link for testing (replace with email in production)
            echo "<p>Password reset link</p>";
            echo "<a href='create_password.php?token=$token'>Click here to reset password</a>";
        } else {
            echo "<p>Failed to generate reset link. Try again later.</p>";
        }

        $update->close();
    } else {
        echo "<p>No account found</p>";
    }

    $stmt->close();
    $conn->close();
    exit();
}

// handling password change
if(isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT ptpID FROM users WHERE reset_token = ? AND token_expire > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows == 1) {
        // Token is valid, show form to change password
        if(isset($_POST['change'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

            $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expire = NULL WHERE reset_token = ?");
            $update->bind_param("ss", $password, $token);
            if($update->execute()) {
                echo "<p>Password changed successfully<a href='alnaasLogin.php'>Login Now</a></p>";
            } else {
                echo "<p>Failed to change password</p>";
            }
            $update->close();
            $stmt->close();
            $conn->close();
            exit();
        }
        ?>
        <h2>Change Your Password</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="New Password" required>
            <button type="submit" name="change">Change Password</button>
        </form>
        <?php
    } else {
        echo "<p>Invalid or expired token.</p>";
    }

    $stmt->close();
    $conn->close();
    exit();
}

// show forgot password
?>
<h2>Forgot Password?</h2>
<form method="POST">
    <input type="email" name="email" placeholder="Enter your registered email" required>
    <button type="submit" name="request_reset">Send Reset Link</button>
</form>
