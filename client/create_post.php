<?php
$target_dir = "uploads/";
$file_name_no_space = str_replace(' ', '_', basename($_FILES["image"]["name"]));
$target_file = $target_dir . $file_name_no_space;
$uploadOk = 1;
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) {
  $check = getimagesize($_FILES["image"]["tmp_name"]);
  if($check !== false) {
    // echo "File is an image - " . $check["mime"] . ".";
    $uploadOk = 1;
  } else {
    // echo "File is not an image.";
    $uploadOk = 0;
  }
}

// Check if file already exists
if (file_exists($target_file)) {
//   echo "Sorry, file already exists.";
  $uploadOk = 0;
}

// Check file size
if ($_FILES["image"]["size"] > 500000) {
//   echo "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
&& $imageFileType != "gif" ) {
//   echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
  $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
//   echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
    // echo "The file ". htmlspecialchars( basename( $_FILES["image"]["name"])). " has been uploaded.";
  } else {
    // echo "Sorry, there was an error uploading your file.";
  }
}
?>

<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$config = [
    "dbuser" => "ora_samuelk2",
    "dbpassword" => "a71186696",
    "dbserver" => "dbhost.students.cs.ubc.ca:1522/stu"
];

function connectToDB() {
    global $config;
    $conn = oci_connect($config["dbuser"], $config["dbpassword"], $config["dbserver"]);
    if (!$conn) {
        $m = oci_error();
        throw new Exception('Cannot connect to database: ' . $m['message']);
    }
    return $conn;
}

$conn = connectToDB();

// Fetch Cities for dropdown
$citiesSql = "SELECT CityID, City FROM City ORDER BY City";
$citiesStid = oci_parse($conn, $citiesSql);
oci_execute($citiesStid);

// Fetch the logged-in user's profile ID
$userEmail = $_SESSION['userEmail'] ?? '';
$userAccountSql = "SELECT userProfile.ProfileID FROM userAccount JOIN userProfile ON userAccount.ProfileID = userProfile.ProfileID WHERE userAccount.Email = :email";
$userAccountStid = oci_parse($conn, $userAccountSql);
oci_bind_by_name($userAccountStid, ":email", $userEmail);
oci_execute($userAccountStid);
$userInfo = oci_fetch_array($userAccountStid, OCI_ASSOC);

// echo $target_file;

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitPost'])) {
    // Collecting POST data
    $title = $_POST['title'];
    $review = $_POST['review'];
    $rating = $_POST['rating'];
    $city = $_POST['city'];
    $profileID = $userInfo['PROFILEID'] ?? '';
    $postID = uniqid();
    $currentTime = date('Y-m-d H:i:s'); // CURRENT_TIMESTAMP equivalent in PHP
    $photo = trim($file_name_no_space);
    

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
        // Insert post with PostPhoto
        $insertPostSql = "INSERT INTO Post (PostID, Title, Rating, TextReview, City, PostTime, ProfileID, PostPhotoTemp) 
                          VALUES (:postID, :title, :rating, :review, :city, :postTime, :profileID, :photo)";

        $insertPostStid = oci_parse($conn, $insertPostSql);

        oci_bind_by_name($insertPostStid, ":postID", $postID);
        oci_bind_by_name($insertPostStid, ":title", $title);
        oci_bind_by_name($insertPostStid, ":rating", $rating);
        oci_bind_by_name($insertPostStid, ":review", $review);
        oci_bind_by_name($insertPostStid, ":city", $city);
        oci_bind_by_name($insertPostStid, ":postTime", $currentTime);
        oci_bind_by_name($insertPostStid, ":profileID", $profileID);
        oci_bind_by_name($insertPostStid, ":photo", $photo);

        oci_execute($insertPostStid, OCI_NO_AUTO_COMMIT);
        oci_commit($conn);
    }

    // Redirect after successful post creation
    header("Location: post.php?postID=" . $postID);
    exit();
}

oci_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Post - foodTalk</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles/create_post.css" rel="stylesheet">
</head>
<body>
    <!-- [Navigation Bar] -->
    <nav class="navbar navbar-expand bg-body-tertiary my-3">
        <div class="container-fluid">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <h2 class="nav-logo" style="font-weight: 600; margin: 8px;">foodtalk.</h2>
                </li>
            </ul>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav" style="font-weight: 600">
                <ul class="navbar-nav">
                    <li class="nav-item ml-2 mr-2">
                        <a class="nav-link" href="feed.php">Feed</a>
                    </li>
                    <li class="nav-item ml-2 mr-2">
                        <a class="nav-link" href="profile.php?profileID=<?= $userInfo['PROFILEID'] ?>">Profile</a>
                    </li>
                    <li class="nav-item ml-2 mr-2">
                        <a class="nav-link" href="create_post.php">Create Post</a>
                    </li>
                    <li class="nav-item ml-2">
                        <a class="nav-link" href="logout.php">Log Out</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container my-4">
        <h2>Create a New Post</h2>
        <form action="create_post.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" class="form-control" name="title" required>
            </div>

            <div class="form-group">
                <label for="review">Review</label>
                <textarea class="form-control" name="review" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label for="image">Image</label>
                <input type="file" class="form-control" name="image" id="image" required>
            </div>

            <div class="form-group">
                <label for="rating">Rating (1-5)</label>
                <input type="number" class="form-control" name="rating" min="1" max="5" required>
            </div>

            <div class="form-group">
                <label for="city">City</label>
                <select class="form-control" name="city">
                    <?php while ($row = oci_fetch_array($citiesStid, OCI_ASSOC)): ?>
                        <option value="<?= $row['CITYID'] ?>"><?= htmlspecialchars($row['CITY']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" name="submitPost" class="btn btn-primary">Submit Post</button>
        </form>
    </div>

</body>
</html>
