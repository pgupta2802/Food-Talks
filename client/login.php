<?php
// Start the session
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$config = [
    "dbuser" => "ora_samuelk2",
    "dbpassword" => "a71186696",
    "dbserver" => "dbhost.students.cs.ubc.ca:1522/stu"
];

// Global variables
$errorMessage = '';

function connectToDB() {
    global $config;
    $conn = oci_connect($config["dbuser"], $config["dbpassword"], $config["dbserver"]);
    if (!$conn) {
        $m = oci_error();
        throw new Exception('Cannot connect to database: ' . $m['message']);
    }
    return $conn;
}

function checkLogin($conn, $email, $password) {
    $sql = "SELECT Password FROM userAccount WHERE Email = :email";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":email", $email);
    oci_execute($stid);
    if ($row = oci_fetch_array($stid, OCI_ASSOC)) {
        return password_verify($password, htmlspecialchars($row['PASSWORD']));
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = connectToDB();
        $email = trim($_POST['inputEmail']);
        $password = trim($_POST['inputPassword']);
        if (checkLogin($conn, $email, $password)) {
            $_SESSION['userEmail'] = $email;
            header("Location: feed.php");
            exit();
        } else {
            throw new Exception("Invalid login credentials.");
        }
        oci_close($conn);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - foodTalk</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles/register.css" rel="stylesheet">
  </head>
  <body>
    <div class="container">
      <div class="registration-area">
        <h2 class="text-center">Login</h2>
        <?php if (!empty($errorMessage)): ?>
            <p>Error: <?= htmlspecialchars($errorMessage) ?></p>
        <?php endif; ?>

        <form class="form-register" action="login.php" method="post">

          <div class="form-row">
            <label for="inputEmail">Email address</label>
            <input type="email" id="inputEmail" name="inputEmail" class="form-control" required autofocus>
          </div>

          <div class="form-row">
            <label for="inputPassword">Password</label>
            <input type="password" id="inputPassword" name="inputPassword" class="form-control" required>
          </div>

          <button type="submit" class="btn btn-signup">Sign in</button>
          <p class="mt-5 mb-3 text-muted text-center">Don't have an account? <a href="register.php">Register</a></p>
        </form>
      </div>
    </div>
  </body>
</html>
