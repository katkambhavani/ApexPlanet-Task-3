
<?php
session_start();
require_once __DIR__ . "/db.php";

/* 1. Authentication and role validation */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$roleId = (int) ($_SESSION["role_id"] ?? 0);

if (!in_array($roleId, [1, 2], true)) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$messageType = "";

$uploadDir = __DIR__ . "/uploads/profiles/";
$uploadUrl = "uploads/profiles/";

/* 2. Helper functions */
function profileEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function fetchProfile($conn, $userId)
{
    $stmt = $conn->prepare(
        "SELECT name, email, profile_picture
         FROM users
         WHERE id = ?"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $profile = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    return $profile;
}

/* 3. Load the current profile */
$user = fetchProfile($conn, $userId);

if (!$user) {
    http_response_code(404);
    exit("User account not found.");
}

$name = $user["name"];
$email = $user["email"];

/* 4. Process profile updates */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $removePicture = isset($_POST["remove_picture"])
        && $_POST["remove_picture"] === "1";

    $name = trim($_POST["name"] ?? $user["name"]);
    $email = trim($_POST["email"] ?? $user["email"]);

    $oldPicture = basename((string) ($user["profile_picture"] ?? ""));
    $newPicture = null;

    if (!$removePicture) {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $message = "Name must contain 2–100 characters.";
        } elseif (
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $message = "Please enter a valid email address.";
        }
    }

    /* Check for duplicate email addresses */
    if ($message === "" && !$removePicture) {
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ? AND id <> ?"
        );

        if (!$stmt) {
            $message = "Unable to validate your email address.";
        } else {
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();

            $emailExists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($emailExists) {
                $message = "That email address is already in use.";
            }
        }
    }

    /* Validate an optional profile photo */
    if (
        $message === "" &&
        !$removePicture &&
        isset($_FILES["profile_picture"]) &&
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {
        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $message = "The upload failed. Please try again.";
        } elseif ($file["size"] > 2 * 1024 * 1024) {
            $message = "The image must be 2 MB or smaller.";
        } elseif (!is_uploaded_file($file["tmp_name"])) {
            $message = "Invalid uploaded file.";
        } else {
            $imageInfo = @getimagesize($file["tmp_name"]);
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file["tmp_name"]);

            $allowedTypes = [
                "image/jpeg" => "jpg",
                "image/png" => "png",
                "image/webp" => "webp"
            ];

            if (
                $imageInfo === false ||
                !isset($allowedTypes[$mimeType])
            ) {
                $message = "Choose a valid JPG, PNG, or WebP image.";
            } elseif (
                !is_dir($uploadDir) &&
                !mkdir($uploadDir, 0755, true) &&
                !is_dir($uploadDir)
            ) {
                $message = "The profile upload folder could not be created.";
            } else {
                $newPicture = bin2hex(random_bytes(16))
                    . "." . $allowedTypes[$mimeType];

                if (!move_uploaded_file(
                    $file["tmp_name"],
                    $uploadDir . $newPicture
                )) {
                    $message = "Unable to save the uploaded image.";
                    $newPicture = null;
                }
            }
        }
    }

    /* Update the database */
    if ($message === "") {
        if ($removePicture) {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET profile_picture = NULL
                 WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("i", $userId);
            }
        } elseif ($newPicture !== null) {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?, email = ?, profile_picture = ?
                 WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param(
                    "sssi",
                    $name,
                    $email,
                    $newPicture,
                    $userId
                );
            }
        } else {
            $stmt = $conn->prepare(
                "UPDATE users
                 SET name = ?, email = ?
                 WHERE id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("ssi", $name, $email, $userId);
            }
        }

        if (!isset($stmt) || !$stmt) {
            $message = "Unable to prepare the profile update.";
            $messageType = "error";

            if (
                $newPicture !== null &&
                is_file($uploadDir . $newPicture)
            ) {
                unlink($uploadDir . $newPicture);
            }
        } elseif ($stmt->execute()) {
            $message = $removePicture
                ? "Profile photo removed successfully."
                : "Profile updated successfully.";

            $messageType = "success";

            if (!$removePicture) {
                $_SESSION["name"] = $name;
                $_SESSION["email"] = $email;
            }

            /* Delete the old image after the database update */
            if (
                ($newPicture !== null || $removePicture) &&
                $oldPicture !== "" &&
                is_file($uploadDir . $oldPicture)
            ) {
                unlink($uploadDir . $oldPicture);
            }

            $user = fetchProfile($conn, $userId);

            if ($user) {
                $name = $user["name"];
                $email = $user["email"];
            }
        } else {
            $message = "Unable to update your profile. Please try again.";
            $messageType = "error";

            if (
                $newPicture !== null &&
                is_file($uploadDir . $newPicture)
            ) {
                unlink($uploadDir . $newPicture);
            }
        }

        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    } else {
        $messageType = "error";
    }
}

/* 5. Prepare the profile photo URL */
$currentPicture = basename(
    (string) ($user["profile_picture"] ?? "")
);

$pictureExists = $currentPicture !== ""
    && is_file($uploadDir . $currentPicture);

$pictureUrl = $pictureExists
    ? $uploadUrl . rawurlencode($currentPicture)
    : "";

$displayInitial = strtoupper(
    mb_substr((string) ($user["name"] ?? "U"), 0, 1)
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | ApexPlanet User Management</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- Shared header -->
    <header class="site-header">
        <div class="site-header__inner">
            <h1>My Profile</h1>
            <p>Manage your account information and profile photo</p>
        </div>
    </header>

    <!-- Shared navigation -->
    <nav class="site-nav" aria-label="Main navigation">
        <a class="site-nav__link" href="dashboard.php">
            Home
        </a>

        <a
            class="site-nav__link site-nav__link--active"
            href="profile.php"
            aria-current="page"
        >
            My Profile
        </a>

        <?php if ($roleId === 1): ?>
            <a class="site-nav__link" href="admin.php">
                Manage Users
            </a>
        <?php endif; ?>

        <a class="site-nav__link" href="logout.php">
            Logout
        </a>
    </nav>

    <!-- Main profile content -->
    <main class="page-container">

        <section class="content-card" aria-labelledby="profileHeading">

            <div class="content-card__header">
                <h2 id="profileHeading">Edit My Details</h2>
                <p>Keep your personal information up to date.</p>
            </div>

            <div class="content-card__body">

                <?php if ($message !== ""): ?>
                    <div
                        class="status-message status-message--<?= profileEscape($messageType) ?>"
                        role="status"
                        aria-live="polite"
                    >
                        <?= profileEscape($message) ?>
                    </div>
                <?php endif; ?>

                <form
                    method="POST"
                    action="profile.php"
                    enctype="multipart/form-data"
                    id="profileForm"
                >

                    <!-- Profile photo -->
                    <section class="photo-section" aria-labelledby="photoHeading">

                        <div class="profile-photo">
                            <?php if ($pictureExists): ?>
                                <img
                                    src="<?= profileEscape($pictureUrl) ?>"
                                    alt="Your profile photo"
                                    id="photoPreview"
                                >
                            <?php else: ?>
                                <span
                                    class="profile-initial"
                                    id="defaultAvatar"
                                    aria-label="Profile initial"
                                >
                                    <?= profileEscape($displayInitial) ?>
                                </span>

                                <img
                                    src=""
                                    alt="Selected profile photo preview"
                                    id="photoPreview"
                                    hidden
                                >
                            <?php endif; ?>
                        </div>

                        <div class="photo-details">
                            <h3 id="photoHeading">Profile Photo</h3>

                            <p class="photo-help">
                                JPG, PNG, or WebP. Maximum size: 2 MB.
                            </p>

                            <div class="photo-actions">
                                <label class="photo-upload-label">
                                    <span id="uploadLabelText">
                                        <?= $pictureExists
                                            ? "Change Photo"
                                            : "Add Photo" ?>
                                    </span>

                                    <input
                                        class="profile-file-input"
                                        type="file"
                                        name="profile_picture"
                                        id="profilePictureInput"
                                        accept="image/jpeg,image/png,image/webp"
                                    >
                                </label>

                                <?php if ($pictureExists): ?>
                                    <button
                                        type="submit"
                                        name="remove_picture"
                                        value="1"
                                        class="button button--danger"
                                        formnovalidate
                                        onclick="return confirm('Remove your profile photo?');"
                                    >
                                        Remove Photo
                                    </button>
                                <?php endif; ?>
                            </div>

                            <p class="field-error" id="photoError" role="alert"></p>
                        </div>

                    </section>

                    <!-- Name -->
                    <div class="form-field">
                        <label class="form-field__label" for="name">
                            Full Name
                        </label>

                        <input
                            class="form-field__input"
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            value="<?= profileEscape($name) ?>"
                            autocomplete="name"
                            required
                        >
                    </div>

                    <!-- Email -->
                    <div class="form-field">
                        <label class="form-field__label" for="email">
                            Email Address
                        </label>

                        <input
                            class="form-field__input"
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?= profileEscape($email) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <!-- Form actions -->
                    <div class="form-actions">
                        <button type="submit" class="button button--primary">
                            Save Changes
                        </button>

                     <button type="reset" class="button button--primary">
    Cancel Changes
</button>
                    </div>

                </form>

            </div>
        </section>

    </main>

    <!-- Shared footer -->
    <footer class="site-footer">
        <p>
            &copy; <?= date("Y") ?> ApexPlanet User Management System
        </p>
    </footer>

    <!-- Profile photo preview and validation -->
    <script>
    const pictureInput = document.getElementById("profilePictureInput");
    const photoPreview = document.getElementById("photoPreview");
    const defaultAvatar = document.getElementById("defaultAvatar");
    const uploadLabelText = document.getElementById("uploadLabelText");
    const photoError = document.getElementById("photoError");

    pictureInput.addEventListener("change", function () {
        const file = this.files && this.files[0];

        photoError.textContent = "";

        if (!file) {
            return;
        }

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp"
        ];

        if (!allowedTypes.includes(file.type)) {
            photoError.textContent =
                "Please select a JPG, PNG, or WebP image.";
            this.value = "";
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            photoError.textContent =
                "The photo must be 2 MB or smaller.";
            this.value = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            photoPreview.src = event.target.result;
            photoPreview.hidden = false;

            if (defaultAvatar) {
                defaultAvatar.hidden = true;
            }

            uploadLabelText.textContent = "Change Photo";
        };

        reader.readAsDataURL(file);
    });
    </script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const notification = document.querySelector(".status-message");

    if (notification) {
        setTimeout(function () {
            notification.style.transition = "opacity 0.3s ease";
            notification.style.opacity = "0";

            setTimeout(function () {
                notification.style.display = "none";
            }, 300);
        }, 2000);
    }
});
</script>

</body>
</html>
