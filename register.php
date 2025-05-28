<?php
// register.php
require_once 'includes/db.php'; // Connects and starts session
require_once 'includes/functions.php';

$username = $email = $password = $confirm_password = "";
$username_err = $email_err = $password_err = $confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["username"]))) {
        $username_err = "Username can only contain letters, numbers, and underscores.";
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE username = :username";
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $username_err = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            unset($stmt); // Close statement
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
         // Prepare a select statement
         $sql = "SELECT user_id FROM users WHERE email = :email";
         if ($stmt = $pdo->prepare($sql)) {
             $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
             $param_email = trim($_POST["email"]);
             if ($stmt->execute()) {
                 if ($stmt->rowCount() == 1) {
                     $email_err = "This email is already registered.";
                 } else {
                     $email = trim($_POST["email"]);
                 }
             } else {
                 echo "Oops! Something went wrong. Please try again later.";
             }
             unset($stmt); // Close statement
         }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Check input errors before inserting in database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)";
        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);
            $stmt->bindParam(":email", $param_email, PDO::PARAM_STR);
            $stmt->bindParam(":password_hash", $param_password_hash, PDO::PARAM_STR);

            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password_hash = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Redirect to login page after successful registration
                $_SESSION['flash_message'] = "Registration successful! Please login.";
                $_SESSION['flash_type'] = "success";
                redirect("login.php");
            } else {
                 $_SESSION['flash_message'] = "Something went wrong. Please try again.";
                 $_SESSION['flash_type'] = "error";
            }
            unset($stmt); // Close statement
        }
    }
    // No need to close connection, footer does it (or PHP auto-closes)
}
?>

<?php include 'includes/header.php'; ?>

<h2>Register</h2>
<p>Please fill this form to create an account.</p>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
    <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
        <label>Username</label>
        <input type="text" name="username" value="<?php echo escape($username); ?>">
        <span class="help-block error-text"><?php echo $username_err; ?></span>
    </div>
     <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
        <label>Email</label>
        <input type="email" name="email" value="<?php echo escape($email); ?>">
        <span class="help-block error-text"><?php echo $email_err; ?></span>
    </div>
    <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
        <label>Password</label>
        <input type="password" name="password">
        <span class="help-block error-text"><?php echo $password_err; ?></span>
    </div>
    <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password">
        <span class="help-block error-text"><?php echo $confirm_password_err; ?></span>
    </div>
    <div class="form-group">
        <button type="submit">Register</button>
        <button type="reset" class="button-secondary">Reset</button>
    </div>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</form>
<style>.error-text { color: red; font-size: 0.9em; }</style>


<?php include 'includes/footer.php'; ?>