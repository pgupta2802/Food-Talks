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

// Capture the viewed ProfileID from the URL
$viewedProfileID = $_GET['profileID'] ?? '';

// Fetch user information based on the viewedProfileID
$userProfileSql = "SELECT * FROM userProfile WHERE ProfileID = :profileId";
$userProfileStid = oci_parse($conn, $userProfileSql);
oci_bind_by_name($userProfileStid, ":profileId", $viewedProfileID);
oci_execute($userProfileStid);
$userInfo = oci_fetch_array($userProfileStid, OCI_ASSOC);

// Check for Bio and handle Profile Picture
$bio = isset($userInfo['BIO']) ? htmlspecialchars(trim($userInfo['BIO'])) : "I love foodtalk! (I didn't write a bio yet)";
$profilePic = 'Profile_Pic.JPG'; // Default picture
if (isset($userInfo['PROFILEPICTURE']) && !is_null($userInfo['PROFILEPICTURE'])) {
    $profilePicData = $userInfo['PROFILEPICTURE']->load();
    $profilePic = 'data:image/jpeg;base64,' . base64_encode($profilePicData);
}

// Fetch the logged-in user's profile ID
$loggedInUserProfileId = '';
if (isset($_SESSION['userEmail'])) {
    $loggedInUserSql = "SELECT userProfile.ProfileID FROM userAccount JOIN userProfile ON userAccount.ProfileID = userProfile.ProfileID WHERE userAccount.Email = :email";
    $loggedInUserStid = oci_parse($conn, $loggedInUserSql);
    oci_bind_by_name($loggedInUserStid, ":email", $_SESSION['userEmail']);
    oci_execute($loggedInUserStid);
    if ($row = oci_fetch_array($loggedInUserStid, OCI_ASSOC)) {
        $loggedInUserProfileId = htmlspecialchars($row['PROFILEID']);
    }
}

// Handle bio update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submitBio']) && $viewedProfileID === $userInfo['PROFILEID']) {
    $newBio = $_POST['newBio'];

    $updateSql = "UPDATE userProfile SET Bio = :bio WHERE ProfileID = :profileId";
    $updateStid = oci_parse($conn, $updateSql);
    oci_bind_by_name($updateStid, ":bio", $newBio);
    oci_bind_by_name($updateStid, ":profileId", $viewedProfileID);
    oci_execute($updateStid);

    header("Location: profile.php?profileID=" . $viewedProfileID);
    exit();
}

// Handling post deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletePosts'])) {
    $postsToDelete = $_POST['postsToDelete'] ?? [];
    foreach ($postsToDelete as $postIdToDelete) {
        $deleteSql = "DELETE FROM Post WHERE POSTID = :postId";
        $deleteStid = oci_parse($conn, $deleteSql);
        oci_bind_by_name($deleteStid, ":postId", $postIdToDelete);
        if (!oci_execute($deleteStid)) {
            // Handle error - For debugging only
            $error = oci_error($deleteStid);
            echo "Error: " . $error['message'];
        }
    }

    header("Location: profile.php?profileID=" . $viewedProfileID);
    exit();
}

// Fetch Posts
$postsSql = "SELECT * FROM Post WHERE ProfileID = :profileId ORDER BY PostTime DESC";
$postsStid = oci_parse($conn, $postsSql);
oci_bind_by_name($postsStid, ":profileId", $viewedProfileID);
oci_execute($postsStid);

// Query to get the number of posts for a specific profile
$postCountSql = "SELECT COUNT(*) AS POST_COUNT FROM Post WHERE ProfileID = :profileId";
$postCountStid = oci_parse($conn, $postCountSql);
oci_bind_by_name($postCountStid, ":profileId", $viewedProfileID);
oci_execute($postCountStid);
$postCountRow = oci_fetch_array($postCountStid, OCI_ASSOC);
$postCount = $postCountRow['POST_COUNT'];

// Query to find the user who has commented the most on the profile user's posts
$topCommenterSql = "
    SELECT u.FirstName, u.LastName, COUNT(c.CommentsID) AS COMMENT_COUNT 
    FROM Comments c 
    JOIN Post p ON c.PostID = p.PostID 
    JOIN userProfile u ON c.ProfileID = u.ProfileID 
    WHERE p.ProfileID = :profileId 
    GROUP BY u.FirstName, u.LastName 
    ORDER BY COUNT(c.CommentsID) DESC 
    FETCH FIRST 1 ROWS ONLY";

$topCommenterStid = oci_parse($conn, $topCommenterSql);
oci_bind_by_name($topCommenterStid, ":profileId", $viewedProfileID);
// HTML and PHP for displaying the top commenter on button click

$topCommenterButtonPressed = False;
$topCommenterName = '';
$topCommenterCount = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['findTopCommenter'])) {
    $topCommenterButtonPressed = true;
    oci_execute($topCommenterStid); // Re-execute as it might have been executed earlier
    $topCommenterRow = oci_fetch_array($topCommenterStid, OCI_ASSOC);
    
    if ($topCommenterRow) {
        $topCommenterName = $topCommenterRow['FIRSTNAME'] . ' ' . $topCommenterRow['LASTNAME'];
        $topCommenterCount = $topCommenterRow['COMMENT_COUNT'];
    } else {
        // Handle the case where no top commenter is found
        $topCommenterName = "No top commenter found";
        $topCommenterCount = 0;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - foodTalk</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/profile.css">
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
    
    <div class="profile-container">
        <div class="profile-header">
            <img src="<?= $profilePic ?>" alt="Profile Picture" class="profile-image">
            <h1><?= htmlspecialchars($userInfo['FIRSTNAME'] . ' ' . $userInfo['LASTNAME']) ?></h1>
            <p><strong>Faculty:</strong> <?= htmlspecialchars($userInfo['FACULTY']) ?></p>
            <p><strong>Major:</strong> <?= htmlspecialchars($userInfo['MAJOR']) ?></p>
        </div>
        <div class="profile-info">
            <h2>About Me</h2>
            <p><?= $bio ?></p>
        </div>
        
        <!-- Aggregation Data Display -->
        <div class="aggregation-data">
            <h3>Profile Statistics</h3>
            <p>Number of Posts: <?= $postCount ?></p>
            <form method="POST" action="profile.php?profileID=<?= $viewedProfileID ?>">
                <button type="submit" name="findTopCommenter" class="btn-delete">Find Top Commenter</button>
            </form>
            <?php if ($topCommenterButtonPressed): ?>
            <?php if ($topCommenterName != "No top commenter found"): ?>
                <p>Top Commenter: <?= $topCommenterName ?> (Comments: <?= $topCommenterCount ?>)</p>
            <?php else: ?>
                <p><?= $topCommenterName ?></p>
            <?php endif; ?>
        <?php endif; ?>
        </div>

        <?php if ($viewedProfileID === $loggedInUserProfileId): ?>
            <!-- Edit Bio Form -->
            <div class="edit-bio-form">
                <h2>Edit Bio</h2>
                <form action="profile.php?profileID=<?= $viewedProfileID ?>" method="POST">
                    <textarea name="newBio" rows="4" placeholder="Your new bio..."><?= $bio ?></textarea>
                    <button type="submit" name="submitBio">Update Bio</button>
                </form>
            </div>
            <!-- Delete Posts Form -->
            <div class="user-posts">
                <form action="profile.php?profileID=<?= $viewedProfileID ?>" method="POST">
                    <h2>My Posts</h2>
                    <?php while ($post = oci_fetch_assoc($postsStid)): ?>
                        <div class="user-post">
                            <input type="checkbox" name="postsToDelete[]" value="<?= $post['POSTID'] ?>">
                            <h3><?= htmlspecialchars($post['TITLE']) ?></h3>
                            <p><?= htmlspecialchars($post['TEXTREVIEW']) ?></p>
                            <span class="post-date"><?= htmlspecialchars($post['POSTTIME']) ?></span>
                        </div>
                    <?php endwhile; ?>
                    <button type="submit" name="deletePosts" class="btn-delete">Delete Selected Posts</button>
                </form>
            </div>
            <?php else: ?>
                <!-- Display message or handle as required -->
                <p>You do not have permission to edit this profile.</p>
        <?php endif; ?>
    </div>

    
</body>
</html>

<?php
oci_close($conn);
?>
