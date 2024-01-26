<?php
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
$registrationSuccessful = false;
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

function disconnectFromDB($conn) {
    oci_close($conn);
}

function fetchFaculties($conn) {
    $sql = "SELECT FacultyID, Program FROM Faculty";
    $result = oci_parse($conn, $sql);
    oci_execute($result);
    $faculties = [];
    while (($row = oci_fetch_array($result, OCI_ASSOC+OCI_RETURN_NULLS)) != false) {
        $faculties[htmlspecialchars($row['FACULTYID'])] = htmlspecialchars($row['PROGRAM']);
    }
    return $faculties;
}

function validateFaculty($conn, $facultyID) {
    $sql = "SELECT COUNT(*) FROM Faculty WHERE FacultyID = :facultyID";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":facultyID", $facultyID);
    oci_execute($stid);
    $row = oci_fetch_array($stid);
    return ($row[0] > 0);
}

function isEmailUnique($conn, $email) {
    $sql = "SELECT COUNT(*) AS email_count FROM userAccount WHERE Email = :email";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ":email", $email);
    oci_execute($stid);
    $row = oci_fetch_array($stid);
    return htmlspecialchars($row['EMAIL_COUNT']) == 0; // returns true if email doesn't exist
}

function handleRegistrationRequest($conn) {
    // Collecting POST data
    $firstName = trim($_POST['inputFirstName']);
    $lastName = trim($_POST['inputLastName']);
    $username = trim($_POST['inputUsername']);
    $faculty = trim($_POST['inputFaculty']);
    $major = trim($_POST['inputMajor']);
    $password = password_hash(trim($_POST['inputPassword']), PASSWORD_DEFAULT);

    // Generating unique IDs
    $userID = trim(uniqid());
    $profileID = trim(uniqid());

    // Validate FacultyID
    if (!validateFaculty($conn, $faculty)) {
        throw new Exception('Faculty ID does not exist in the database');
    }

    if (!isEmailUnique($conn, $username)) {
        throw new Exception("Email already registered. Please use a different email.");
    }

    // Handle file upload
    if (isset($_FILES['inputProfilePicture']) && $_FILES['inputProfilePicture']['error'] == UPLOAD_ERR_OK) {
        $fileData = file_get_contents($_FILES['inputProfilePicture']['tmp_name']);
        $lob = oci_new_descriptor($conn, OCI_D_LOB);

        // Insert into userProfile table with ProfilePicture
        $sqlProfile = "INSERT INTO userProfile (ProfileID, FirstName, LastName, Faculty, Major, ProfilePicture) VALUES (:profileID, :firstName, :lastName, :faculty, :major, EMPTY_BLOB()) RETURNING ProfilePicture INTO :image";
        $stid = oci_parse($conn, $sqlProfile);
        oci_bind_by_name($stid, ":profileID", $profileID);
        oci_bind_by_name($stid, ":firstName", $firstName);
        oci_bind_by_name($stid, ":lastName", $lastName);
        oci_bind_by_name($stid, ":faculty", $faculty);
        oci_bind_by_name($stid, ":major", $major);
        oci_bind_by_name($stid, ":image", $lob, -1, OCI_B_BLOB);

        oci_execute($stid, OCI_NO_AUTO_COMMIT);
        $lob->save($fileData);
        oci_commit($conn);
        $lob->free();
    }

    // Insert into userAccount table
    $sqlAccount = "INSERT INTO userAccount (UserID, Email, ProfileID, Password) VALUES (:userID, :email, :profileID, :password)";
    $stid = oci_parse($conn, $sqlAccount);
    oci_bind_by_name($stid, ":userID", $userID);
    oci_bind_by_name($stid, ":email", $username);
    oci_bind_by_name($stid, ":profileID", $profileID);
    oci_bind_by_name($stid, ":password", $password);
    oci_execute($stid);
    oci_commit($conn);
}

// Handling POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = connectToDB();
        handleRegistrationRequest($conn);
        $registrationSuccessful = true;
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    } finally {
        disconnectFromDB($conn);
    }
}

$conn = connectToDB();
$faculties = fetchFaculties($conn);
disconnectFromDB($conn);
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - FoodTalk</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles/register.css" rel="stylesheet">
  </head>

  <body>
    <div class="container">
      <div class="registration-area">
        <h2 class="text-center">Registration</h2>
            <?php if ($registrationSuccessful): ?>
                <p>Registration successful!</p>
            <?php else: ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
            <form class="form-register" action="register.php" method="post" enctype="multipart/form-data">
              <div class="form-row">
                <label for="inputFirstName">First Name</label>
                <input type="text" id="inputFirstName" name="inputFirstName" class="form-control" required>
              </div>

              <div class="form-row">
                <label for="inputLastName">Last Name</label>
                <input type="text" id="inputLastName" name="inputLastName" class="form-control" required>
              </div>

              <div class="form-row">
                <label for="inputUsername">Username (Email)</label>
                <input type="email" id="inputUsername" name="inputUsername" class="form-control" required>
              </div>

              <div class="form-row">
                    <label for="inputFaculty">Faculty</label>
                    <select id="inputFaculty" name="inputFaculty" class="form-control" required>
                        <?php foreach ($faculties as $facultyID => $programName): ?>
                            <option value="<?php echo ($facultyID); ?>">
                                <?php echo ($programName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label for="inputProfilePicture">Profile Picture</label>
                    <input type="file" id="inputProfilePicture" name="inputProfilePicture" class="form-control" accept="image/*">
                </div>

              <div class="form-row">
                <label for="inputMajor">Major</label>
                <input type="text" id="inputMajor" name="inputMajor" class="form-control" required>
              </div>

              <div class="form-row">
                <label for="inputPassword">Password</label>
                <input type="password" id="inputPassword" name="inputPassword" class="form-control" required>
              </div>

              <button type="submit" class="btn btn-signup">Sign Up</button>
            </form>
        <?php endif; ?>
        <p class="mt-5 mb-3 text-muted text-center">Already have an account? <a href="login.php">Login</a></p>
      </div>
    </div>
  </body>
</html>