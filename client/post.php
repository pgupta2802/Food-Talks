<?php
session_start(); // Start the session at the beginning

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
$postID = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST['postID'] : $_GET['postID'];

$userEmail = $_SESSION['userEmail'] ?? '';
$userAccountSql = "SELECT userProfile.ProfileID FROM userAccount JOIN userProfile ON userAccount.ProfileID = userProfile.ProfileID WHERE userAccount.Email = :email";
$userAccountStid = oci_parse($conn, $userAccountSql);
oci_bind_by_name($userAccountStid, ":email", $userEmail);
oci_execute($userAccountStid);
$userInfo = oci_fetch_array($userAccountStid, OCI_ASSOC);

// Initialize $showNames based on session variable
$showNames = $_SESSION['showNames'] ?? false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggleNames'])) {
    $_SESSION['showNames'] = !$showNames;
    header("Location: post.php?postID=".$postID);
    exit();
}

// Modified SQL query
$sqlComments = "SELECT c.CommentContent, c.ProfileID, u.ProfilePicture" . 
               ($showNames ? ", u.FirstName, u.LastName " : " ") .
               "FROM Comments c JOIN userProfile u ON c.ProfileID = u.ProfileID WHERE c.PostID = :postID ORDER BY c.CommentTime DESC";

$stidComments = oci_parse($conn, $sqlComments);
oci_bind_by_name($stidComments, ":postID", $postID);
oci_execute($stidComments);

// Handling comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitComment'])) {
    $email = $_SESSION['userEmail'];
    $commentContent = $_POST['commentContent'];

    // Prepare and execute query to get UserID and ProfileID
    $userSql = "SELECT UserID, ProfileID FROM userAccount WHERE Email = :email";
    $userStid = oci_parse($conn, $userSql);
    oci_bind_by_name($userStid, ":email", $email);
    oci_execute($userStid);

    if ($row = oci_fetch_array($userStid, OCI_ASSOC)) {
        $userID = trim(htmlspecialchars($row['USERID']));
        $profileID = trim(htmlspecialchars($row['PROFILEID']));

        // Insert comment into database
        $commentsID = uniqid();
        $insertSql = "INSERT INTO Comments (CommentsID, PostID, UserID, Email, ProfileID, CommentContent, CommentTime)
                      VALUES (:commentsID, :postID, :userID, :email, :profileID, :commentContent, CURRENT_TIMESTAMP)";
        $insertStid = oci_parse($conn, $insertSql);
        oci_bind_by_name($insertStid, ":commentsID", $commentsID);
        oci_bind_by_name($insertStid, ":postID", $postID);
        oci_bind_by_name($insertStid, ":userID", $userID);
        oci_bind_by_name($insertStid, ":email", $email);
        oci_bind_by_name($insertStid, ":profileID", $profileID);
        oci_bind_by_name($insertStid, ":commentContent", $commentContent);

        if (!oci_execute($insertStid)) {
            $error = oci_error($insertStid);
            echo "Error: " . $error['message'];
        }
    } else {
        echo "User details not found for email: $email";
    }

    header("Location: post.php?postID=".$postID);
    exit();
}

$sqlImage = "SELECT PostPhoto FROM Post WHERE PostID = :postID";
$stidImage = oci_parse($conn, $sqlImage);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Posts - foodtalk</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/post.css">
</head>
<body>
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
                        <a class="nav-link" href="profile.php?profileID=<?= $userInfo['PROFILEID'] ?? '' ?>">Profile</a>
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

    <!-- Post Content Area -->
    <div class="container my-4">
        <!-- Post Details -->
        <div class="post-details">
            <?php
            $sql = "SELECT p.Title, p.Rating, p.TextReview, p.PostTime, c.City, p.ProfileID, p.PostPhotoTemp
                    FROM Post p 
                    JOIN City c ON p.City = c.CityID 
                    WHERE p.PostID = :postID";
            $stid = oci_parse($conn, $sql);
            oci_bind_by_name($stid, ":postID", $postID);
            oci_execute($stid);
            $postDetails = oci_fetch_assoc($stid);

            if ($postDetails) {
                echo '<div class="post-header">';
                echo '<span class="location">' . htmlspecialchars($postDetails['CITY']) . '</span>';
                echo '<h1 class="post-title">' . htmlspecialchars($postDetails['TITLE']) . '</h1>';
                echo '<div class="star-rating">';

                $rating = floatval($postDetails['RATING']);
                for ($i = 1; $i <= 5; $i++) {
                    if ($rating >= $i) {
                        echo '<i class="bi bi-star-fill"></i>';
                    } elseif ($rating > $i - 1 && $rating < $i) {
                        echo '<i class="bi bi-star-half"></i>';
                    } else {
                        echo '<i class="bi bi-star"></i>';
                    }
                }

                echo '</div>';
                echo '<div class="post-meta">';
                echo '<a href="profile.php?profileID=' . htmlspecialchars($postDetails['PROFILEID']) . '">' . htmlspecialchars($postDetails['PROFILEID']) . '</a>';
                echo '<span class="post-date">' . htmlspecialchars($postDetails['POSTTIME']) . '</span>';
                echo '</div></div>';
                echo '<div class="post-body">';
                echo '<p>' . htmlspecialchars($postDetails['TEXTREVIEW']) . '</p>';
                echo '</div>';

                echo ' <img src="uploads/' . htmlspecialchars($postDetails['POSTPHOTOTEMP']) . '" class="post-image" alt="Post Image">';

            } else {
                echo 'Post details not available.';
            }
            ?>
        </div>

        <!-- Comment Submission Form -->
        <div class="add-comment-section mt-4">
            <form action="post.php?postID=<?php echo $postID; ?>" method="POST">
                <input type="hidden" name="postID" value="<?php echo $postID; ?>">
                <textarea class="form-control" name="commentContent" rows="3" placeholder="Write your comment here..."></textarea>
                <div class="text-right mt-2">
                    <button type="submit" name="submitComment" class="btn btn-success" style="color: white;">Comment</button>
                </div>
            </form>
        </div>

        <!-- Toggle Button for Commenter Names -->
        <form action="post.php?postID=<?= $postID ?>" method="POST">
            <input type="hidden" name="postID" value="<?= $postID ?>">
            <button type="submit" name="toggleNames" class="btn btn-success"><?= $showNames ? 'Show Profile IDs' : 'Show Names' ?></button>
        </form>

        <!-- Comments Section -->
        <div class="comments-section">
            <h3>Comments</h3>
            <?php
            // Fetch Comments
            $sqlComments = "SELECT c.CommentContent, c.ProfileID, u.ProfilePicture, u.FirstName, u.LastName 
                FROM Comments c 
                JOIN userProfile u ON c.ProfileID = u.ProfileID 
                WHERE c.PostID = :postID 
                ORDER BY c.CommentTime DESC";

            $stidComments = oci_parse($conn, $sqlComments);
            oci_bind_by_name($stidComments, ":postID", $postID);
            oci_execute($stidComments);

            while ($comment = oci_fetch_assoc($stidComments)) {
                // Handle profile picture
                $profilePic = $comment['PROFILEPICTURE'] ? 'data:image/jpeg;base64,'.base64_encode($comment['PROFILEPICTURE']->load()) : 'default-pic.jpg';
            
                // Decide what to display based on $showNames
                $displayName = $showNames ? 
                               htmlspecialchars($comment['FIRSTNAME']) . ' ' . htmlspecialchars($comment['LASTNAME']) :
                               htmlspecialchars($comment['PROFILEID']);
            
                // Create a link to the user's profile
                $profileLink = "profile.php?profileID=" . htmlspecialchars($comment['PROFILEID']);

                // Display comment and profile picture
                echo '<div class="comment">';
                echo '<img class="commenter-pic" src="'. htmlspecialchars($profilePic) .'" alt="User Profile Picture">';
                echo '<div class="commenter-details">';
                echo '<span class="commenter-name"><a href="' . htmlspecialchars($profileLink) . '">' . htmlspecialchars($displayName) . '</a></span>';
                echo '<p class="comment-text">'.htmlspecialchars($comment['COMMENTCONTENT']).'</p>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
</body>
</html>

<?php
oci_close($conn);
?>