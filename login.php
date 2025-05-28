<?php
// login.php
require_once 'includes/db.php'; // Connects and starts session
require_once 'includes/functions.php';

// If user is already logged in, redirect to index
if (is_logged_in()) {
    redirect("index.php");
}

$login_identifier = $password = ""; // Can be username or email
$login_err = $password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate identifier (username or email)
    if (empty(trim($_POST["login_identifier"]))) {
        $login_err = "Please enter username or email.";
    } else {
        $login_identifier = trim($_POST["login_identifier"]);
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // If no validation errors
    if (empty($login_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT user_id, username, email, password_hash FROM users WHERE username = :login_identifier OR email = :login_identifier";

        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":login_identifier", $login_identifier, PDO::PARAM_STR);

            if ($stmt->execute()) {
                // Check if identifier exists
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["user_id"];
                        $username = $row["username"];
                        $hashed_password = $row["password_hash"];
                        $email = $row["email"];

                        // Verify password
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, start a new session
                            // session_start(); // Already started in db.php

                            // Store data in session variables
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["email"] = $email;
                            $_SESSION["loggedin"] = true; // Optional convenience flag

                            // Redirect user to home page
                            redirect("index.php");
                        } else {
                            // Display an error message if password is not valid
                            $password_err = "The password you entered was not valid.";
                        }
                    }
                } else {
                    // Display an error message if username/email doesn't exist
                    $login_err = "No account found with that username or email.";
                }
            } else {
                 $_SESSION['flash_message'] = "Oops! Something went wrong. Please try again later.";
                 $_SESSION['flash_type'] = "error";
            }
            unset($stmt);
        }
    }
     // No need to close connection
}
?>

<?php include 'includes/header.php'; ?>

<h2>Login</h2>
<p>Please fill in your credentials to login.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group <?php echo (!empty($login_err)) ? 'has-error' : ''; ?>">
        <label>Username or Email</label>
        <input type="text" name="login_identifier" value="<?php echo escape($login_identifier); ?>">
        <span class="help-block error-text"><?php echo $login_err; ?></span>
    </div>
    <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
        <label>Password</label>
        <input type="password" name="password">
        <span class="help-block error-text"><?php echo $password_err; ?></span>
    </div>
    <div class="form-group">
        <button type="submit">Login</button>
    </div>
    <p>Don't have an account? <a href="register.php">Sign up now</a>.</p>
</form>
<style>.error-text { color: red; font-size: 0.9em; }</style>


<?php include 'includes/footer.php'; ?>