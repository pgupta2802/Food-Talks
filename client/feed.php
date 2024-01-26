<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$hostname = 'dbhost.students.cs.ubc.ca:1522/stu';
$username = 'ora_samuelk2';
$password = 'a71186696';
$conn = oci_connect($username, $password, $hostname);

// Fetch user's profile ID for navbar link
$userEmail = $_SESSION['userEmail'] ?? '';
$userAccountSql = "SELECT userProfile.ProfileID FROM userAccount JOIN userProfile ON userAccount.ProfileID = userProfile.ProfileID WHERE userAccount.Email = :email";
$userAccountStid = oci_parse($conn, $userAccountSql);
oci_bind_by_name($userAccountStid, ":email", $userEmail);
oci_execute($userAccountStid);
$userInfo = oci_fetch_array($userAccountStid, OCI_ASSOC);

$isSearch = $_SERVER["REQUEST_METHOD"] == "POST";
$firstname = $isSearch && isset($_POST['firstname']) ? strtoupper($_POST['firstname']) : ''; // Convert input to uppercase
$rating = $isSearch && isset($_POST['rating']) ? $_POST['rating'] : null;

// Columns to display based on checkboxes or default
$displayColumns = [
    'show_title' => !$isSearch || isset($_POST['show_title']),
    'show_rating' => !$isSearch || isset($_POST['show_rating']),
    'show_review' => !$isSearch || isset($_POST['show_review']),
    'show_time' => !$isSearch || isset($_POST['show_time']),
    'show_city' => !$isSearch || isset($_POST['show_city'])
];

// Build the SQL Query based on search parameters
$selectClause = "SELECT p.POSTID, p.TITLE, p.RATING, p.TEXTREVIEW, p.POSTTIME, c.CITY, c.PROVINCE FROM POST p LEFT JOIN USERPROFILE u ON p.PROFILEID = u.PROFILEID LEFT JOIN CITY c ON p.CITY = c.CITYID";

$whereClauses = [];
if ($firstname) {
    $whereClauses[] = "UPPER(u.FIRSTNAME) = :firstname";
}
if (!is_null($rating) && $rating <= 5) {
    $whereClauses[] = "p.RATING >= :rating";
}

if (!empty($whereClauses)) {
    $selectClause .= " WHERE " . implode(' OR ', $whereClauses);
}
$selectClause .= " ORDER BY p.POSTTIME DESC";

$stid = oci_parse($conn, $selectClause);

if ($firstname) {
    oci_bind_by_name($stid, ':firstname', $firstname);
}
if (!is_null($rating) && $rating <= 5) {
    oci_bind_by_name($stid, ':rating', $rating);
}

oci_execute($stid);

// Check for Division Query request
$isDivisionQuery = $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['divisionQuery']);
$divisionResults = [];

if ($isDivisionQuery) {
    $divisionSql = "
        SELECT up.ProfileID, up.FirstName, up.LastName
        FROM userProfile up
        WHERE NOT EXISTS (
            SELECT c.CityID
            FROM City c
            WHERE NOT EXISTS (
                SELECT p.ProfileID
                FROM Post p
                WHERE p.City = c.CityID
                AND p.ProfileID = up.ProfileID
            )
        )
    ";
    $divisionStid = oci_parse($conn, $divisionSql);
    oci_execute($divisionStid);
    while ($row = oci_fetch_array($divisionStid, OCI_ASSOC)) {
        $divisionResults[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - foodtalk</title>
    <!-- ============ bootstrap & neuton font ============  -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Neuton:400,700&display=swap">
    <link href="./styles/feed.css" rel="stylesheet">
</head>

<body>
    <!-- ============ navbar ============  -->
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

    <form action="feed.php" method="POST">
        <p>
            <label for="firstname">Search by First Name:</label>
            <input type="text" name="firstname">
        </p>
        <p>
            <label for="rating">Reviews with Rating (Out of 5):</label>
            <input type="number" name="rating" placeholder="Rating Number" min="1" max="5">
        </p>

        <p>
            <input type="checkbox" name="show_title" id="show_title" <?php if($displayColumns['show_title']) echo 'checked'; ?>>
            <label for="show_title">Show Title</label>
            
            <input type="checkbox" name="show_rating" id="show_rating" <?php if($displayColumns['show_rating']) echo 'checked'; ?>>
            <label for="show_rating">Show Rating</label>
            
            <input type="checkbox" name="show_review" id="show_review" <?php if($displayColumns['show_review']) echo 'checked'; ?>>
            <label for="show_review">Show Review</label>
            
            <input type="checkbox" name="show_time" id="show_time" <?php if($displayColumns['show_time']) echo 'checked'; ?>>
            <label for="show_time">Show Time</label>
            
            <input type="checkbox" name="show_city" id="show_city" <?php if($displayColumns['show_city']) echo 'checked'; ?>>
            <label for="show_city">Show City</label>
        </p>

        <p>
            <input type="submit" value="Search">
        </p>
    </form>
    <!-- Division Query Button Form -->
    <form action="feed.php" method="POST" style="text-align: center; margin-top: 20px;">
        <button type="submit" name="divisionQuery" class="btn-search">Find Users Posting in All Cities</button>
    </form>

    <table class="table table-hover">
        <thead>
            <tr>
                <?php
                if ($displayColumns['show_title']) echo '<th>Title</th>';
                if ($displayColumns['show_rating']) echo '<th>Rating</th>';
                if ($displayColumns['show_review']) echo '<th>Review</th>';
                if ($displayColumns['show_time']) echo '<th>Time Posted</th>';
                if ($displayColumns['show_city']) echo '<th>City</th>';
                ?>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
                echo "<tr onclick=\"window.location='post.php?postID=" . htmlspecialchars($row['POSTID']) . "';\">";
                if ($displayColumns['show_title']) echo "<td>" . htmlspecialchars($row['TITLE']) . "</td>";
                if ($displayColumns['show_rating']) echo "<td>" . htmlspecialchars($row['RATING']) . "</td>";
                if ($displayColumns['show_review']) echo "<td>" . htmlspecialchars($row['TEXTREVIEW']) . "</td>";
                if ($displayColumns['show_time']) echo "<td>" . htmlspecialchars($row['POSTTIME']) . "</td>";
                if ($displayColumns['show_city']) echo "<td>" . htmlspecialchars($row['CITY']) . ", " . htmlspecialchars($row['PROVINCE']) . "</td>";
                echo "</tr>";
            }
            oci_free_statement($stid);
            oci_close($conn);
            ?>
        </tbody>
    </table>

    <!-- Division Query Results -->
    <?php if ($isDivisionQuery && !empty($divisionResults)): ?>
        <h2>Users Who Posted in All Cities</h2>
        <ul>
            <?php foreach ($divisionResults as $user): ?>
                <li>
                    <a href="profile.php?profileID=<?= htmlspecialchars($user['PROFILEID']) ?>">
                        <?= htmlspecialchars($user['FIRSTNAME']) . ' ' . htmlspecialchars($user['LASTNAME']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</body>
</html>

<?php
oci_close($conn);
?>
