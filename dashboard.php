
<?php
session_start();
require_once __DIR__ . "/db.php";

/* 1. Check whether the user is logged in */
if (!isset($_SESSION["user_id"], $_SESSION["role_id"])) {
    header("Location: login.php");
    exit;
}

/* 2. Validate the user's role */
$roleId = (int) $_SESSION["role_id"];

if (!in_array($roleId, [1, 2], true)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

/* 3. Prepare safe user information */
$userName = htmlspecialchars(
    $_SESSION["name"] ?? "User",
    ENT_QUOTES,
    "UTF-8"
);

$profilePicture = "";
$userId = (int) $_SESSION["user_id"];

/* 4. Retrieve the user's profile picture */
$stmt = $conn->prepare(
    "SELECT profile_picture FROM users WHERE id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($savedPicture);
    $stmt->fetch();
    $stmt->close();

    if (
        !empty($savedPicture) &&
        basename($savedPicture) === $savedPicture
    ) {
        $picturePath = __DIR__ . "/uploads/profiles/" . $savedPicture;

        if (is_file($picturePath)) {
            $profilePicture =
                "uploads/profiles/" . rawurlencode($savedPicture);
        }
    }
}

/* 5. Set role-specific information */
$isAdmin = ($roleId === 1);
$pageTitle = "Home";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | ApexPlanet User Management</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header class="site-header">
        <div class="site-header__inner">
            <h1>User Management System</h1>
            <p>Welcome back, <?= $userName ?>!</p>
        </div>
    </header>

    <nav class="site-nav" aria-label="Main navigation">
        <a class="site-nav__link site-nav__link--active"
           href="dashboard.php" aria-current="page">Home</a>

        <a class="site-nav__link" href="profile.php">My Profile</a>

        <?php if ($isAdmin): ?>
            <a class="site-nav__link" href="admin.php">Manage Users</a>
        <?php endif; ?>

        <a class="site-nav__link" href="logout.php">Logout</a>
    </nav>

    <main class="page-container">

        <!-- One unified dashboard card -->
        <section class="content-card" aria-labelledby="homeHeading">

            <div class="content-card__header">
                <h2 id="homeHeading">Welcome, <?= $userName ?>!</h2>
                <p>Your account dashboard</p>
            </div>

            <div class="content-card__body">

                <div class="home-welcome">
                    <div class="home-welcome__icon" aria-hidden="true">
                        <?php if ($profilePicture !== ""): ?>
                            <img
                                class="home-welcome__photo"
                                src="<?= htmlspecialchars(
                                    $profilePicture,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                alt="Your profile"
                            >
                        <?php else: ?>
                            <span>
                                <?= htmlspecialchars(
                                    strtoupper(substr(
                                        $_SESSION["name"] ?? "U",
                                        0,
                                        1
                                    )),
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="home-welcome__details">
                        <h3>Your account is ready</h3>

                        <p>
                            We're glad to have you here. Use your dashboard
                            to access your account and manage your profile.
                        </p>

                        <span class="role-badge">
                            <?= $isAdmin ? "Administrator" : "Regular User" ?>
                        </span>
                    </div>
                </div>

                <!-- Access information is now inside the same card -->
                <div class="home-information">
                    <h3>
                        <?= $isAdmin ? "Administrator Access" : "Your Account" ?>
                    </h3>

                    <p>
                        <?= $isAdmin
                            ? "You can manage user accounts from the Manage Users section."
                            : "You are signed in as a regular user. You can view and update your profile." ?>
                    </p>

                   
                </div>

            </div>
        </section>

    </main>

    <footer class="site-footer">
        <p>&copy; <?= date("Y") ?> ApexPlanet User Management System</p>
    </footer>

</body>
</html>
