
<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ((int)($_SESSION["role_id"] ?? 0) !== 1) {
    http_response_code(403);
    exit("Access denied. Admins only.");
}

function editUserEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

$message = "";
$messageType = "";
$userId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    http_response_code(400);
    exit("Invalid user ID.");
}

$uploadDirectory = __DIR__ . "/uploads/profiles/";

function fetchEditedUser($conn, $userId)
{
    $stmt = $conn->prepare(
        "SELECT id, name, email, role_id, profile_picture
         FROM users WHERE id = ?"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user;
}

$user = fetchEditedUser($conn, $userId);

if (!$user) {
    http_response_code(404);
    exit("User not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $removePicture = isset($_POST["remove_picture"])
        && $_POST["remove_picture"] === "1";

    $name = trim($_POST["name"] ?? $user["name"]);
    $email = trim($_POST["email"] ?? $user["email"]);
    $roleId = filter_input(INPUT_POST, "role_id", FILTER_VALIDATE_INT);

    if (!$removePicture) {
        $user["name"] = $name;
        $user["email"] = $email;

        if (in_array($roleId, [1, 2], true)) {
            $user["role_id"] = $roleId;
        }
    }

    $oldPicture = basename((string)($user["profile_picture"] ?? ""));

    // Remove the selected user's photo without changing account details.
    if ($removePicture) {
        $stmt = $conn->prepare(
            "UPDATE users SET profile_picture = NULL WHERE id = ?"
        );
        $stmt->bind_param("i", $userId);

        if ($stmt->execute()) {
            $stmt->close();

            if (
                $oldPicture !== "" &&
                is_file($uploadDirectory . $oldPicture)
            ) {
                unlink($uploadDirectory . $oldPicture);
            }

            $user["profile_picture"] = null;
            $message = "Profile photo removed successfully.";
            $messageType = "success";
        } else {
            $stmt->close();
            $message = "Unable to remove the profile photo.";
            $messageType = "error";
        }
    } else {
        if (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 100 ||
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !in_array($roleId, [1, 2], true)
        ) {
            $message = "Enter a valid name, email address, and role.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare(
                "SELECT id FROM users WHERE email = ? AND id <> ?"
            );
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $emailExists = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($emailExists) {
                $message = "That email address is already in use.";
                $messageType = "error";
            } else {
                $newPicture = null;
                $uploadError = "";

                if (
                    isset($_FILES["profile_picture"]) &&
                    $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {
                    $file = $_FILES["profile_picture"];

                    if ($file["error"] !== UPLOAD_ERR_OK) {
                        $uploadError = "The photo upload failed. Please try again.";
                    } elseif ($file["size"] > 2 * 1024 * 1024) {
                        $uploadError = "The photo must be 2 MB or smaller.";
                    } elseif (!is_uploaded_file($file["tmp_name"])) {
                        $uploadError = "The uploaded file is invalid.";
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
                            $uploadError =
                                "Choose a valid JPG, PNG, or WebP image.";
                        } else {
                            if (
                                !is_dir($uploadDirectory) &&
                                !mkdir($uploadDirectory, 0755, true) &&
                                !is_dir($uploadDirectory)
                            ) {
                                $uploadError =
                                    "The profile photo folder could not be created.";
                            } else {
                                $newPicture =
                                    bin2hex(random_bytes(16)) .
                                    "." . $allowedTypes[$mimeType];

                                if (!move_uploaded_file(
                                    $file["tmp_name"],
                                    $uploadDirectory . $newPicture
                                )) {
                                    $uploadError =
                                        "Unable to save the uploaded photo.";
                                    $newPicture = null;
                                }
                            }
                        }
                    }
                }

                if ($uploadError !== "") {
                    $message = $uploadError;
                    $messageType = "error";
                } else {
                    if ($newPicture !== null) {
                        $stmt = $conn->prepare(
                            "UPDATE users
                             SET name = ?, email = ?, role_id = ?,
                                 profile_picture = ?
                             WHERE id = ?"
                        );
                        $stmt->bind_param(
                            "ssisi",
                            $name,
                            $email,
                            $roleId,
                            $newPicture,
                            $userId
                        );
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE users
                             SET name = ?, email = ?, role_id = ?
                             WHERE id = ?"
                        );
                        $stmt->bind_param(
                            "ssii",
                            $name,
                            $email,
                            $roleId,
                            $userId
                        );
                    }

                    if ($stmt->execute()) {
                        $message = "User details updated successfully.";
                        $messageType = "success";

                        if (
                            $newPicture !== null &&
                            $oldPicture !== "" &&
                            is_file($uploadDirectory . $oldPicture)
                        ) {
                            unlink($uploadDirectory . $oldPicture);
                        }
                    } else {
                        if (
                            $newPicture !== null &&
                            is_file($uploadDirectory . $newPicture)
                        ) {
                            unlink($uploadDirectory . $newPicture);
                        }

                        $message = $stmt->errno === 1062
                            ? "That email address is already in use."
                            : "Unable to update the user. Please try again.";

                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }

    // Reload the latest account details after processing the request.
    $user = fetchEditedUser($conn, $userId);

    if (!$user) {
        http_response_code(404);
        exit("User not found.");
    }
}

$pictureName = basename((string)($user["profile_picture"] ?? ""));
$picturePath = $uploadDirectory . $pictureName;
$pictureUrl = "uploads/profiles/" . rawurlencode($pictureName);

$hasPicture = $pictureName !== "" && is_file($picturePath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | ApexPlanet</title>
    <link rel="stylesheet" href="style.css">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f3f6fc;
            color: #243047;
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.6;
        }

        .topbar {
    padding: 24px 20px;
    background: #fff;
    border-bottom: 1px solid #e4eaf3;
}

.topbar-inner {
    max-width: 1050px;
    margin: auto;
}

.topbar h1 {
    margin: 0;
    color: #20345d;
    font-size: 27px;
}

.topbar p {
    margin: 4px 0 0;
    color: #718096;
    font-size: 14px;
}
        .page {
            width: calc(100% - 32px);
            max-width: 760px;
            margin: 36px auto;
        }

        .profile-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e5eaf2;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(32, 52, 93, .07);
        }

        .card-heading {
            padding: 25px 30px 20px;
            border-bottom: 1px solid #edf0f6;
        }

        .card-heading h2 {
            margin: 0;
            color: #20345d;
            font-size: 22px;
        }

        .card-heading p {
            margin: 6px 0 0;
            color: #718096;
            font-size: 14px;
        }

        .form-content { padding: 28px 30px 30px; }

        .photo-section {
            display: flex;
            align-items: center;
            gap: 22px;
            padding-bottom: 25px;
            margin-bottom: 25px;
            border-bottom: 1px solid #edf0f6;
        }

        .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 112px;
            width: 112px;
            height: 112px;
            overflow: hidden;
            border: 4px solid #e8eefb;
            border-radius: 50%;
            background: #edf2ff;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar svg {
            width: 58px;
            height: 58px;
            fill: none;
            stroke: #8b9bc0;
            stroke-width: 1.5;
        }

        .photo-details h3 {
            margin: 0 0 4px;
            color: #263858;
            font-size: 16px;
        }

        .photo-details p {
            margin: 0 0 12px;
            color: #718096;
            font-size: 13px;
        }

        .photo-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .upload-label,
        .remove-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 9px 14px;
            border-radius: 8px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .upload-label {
            border: 1px solid #cdd8f0;
            background: #f5f7ff;
            color: #3156a5;
        }

        .upload-label:hover { background: #eaf0ff; }

        .upload-label input {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip-path: inset(50%);
        }

        .remove-button {
            border: 1px solid #fecdd3;
            background: #fff1f2;
            color: #be123c;
        }

        .remove-button:hover { background: #ffe4e6; }

        .field { margin-bottom: 20px; }

        .field label {
            display: block;
            margin-bottom: 7px;
            color: #34445f;
            font-size: 14px;
            font-weight: 600;
        }

        .field input,
        .field select {
            display: block;
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #d5ddeb;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: #243047;
            font: inherit;
            font-size: 14px;
        }

        .field input:focus,
        .field select:focus {
            border-color: #5579d6;
            box-shadow: 0 0 0 3px rgba(85, 121, 214, .12);
        }

        .field small {
            display: block;
            margin-top: 6px;
            color: #7b879b;
            font-size: 12px;
        }

        .message {
            margin: 0 0 22px;
            padding: 12px 14px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 14px;
        }

        .message.success {
            border-color: #b8e4ca;
            background: #effaf3;
            color: #216c40;
        }

        .message.error {
            border-color: #f0c5c8;
            background: #fff2f2;
            color: #a52d39;
        }

        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding-top: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 11px 19px;
            border: 1px solid transparent;
            border-radius: 9px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary { background: #3156a5; color: #fff; }
        .btn-primary:hover { background: #254583; }

        .btn-secondary {
            border-color: #d5ddeb;
            background: #fff;
            color: #34445f;
        }

        .btn-secondary:hover { background: #f6f8fc; }

        @media (max-width: 540px) {
            .topbar { padding: 20px 16px; }
            .topbar h1 { font-size: 23px; }
            .page { margin: 22px auto; }
            .card-heading, .form-content { padding: 21px 18px; }

            .photo-section {
                align-items: flex-start;
                gap: 15px;
            }

            .avatar {
                flex-basis: 84px;
                width: 84px;
                height: 84px;
            }

            .avatar svg { width: 45px; height: 45px; }

            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="site-header__inner">
        <h1>Edit User</h1>
        <p>Manage user account information and permissions</p>
    </div>
</header>

<main class="page">
    <section class="profile-card">
        <div class="card-heading">
            <h2>Edit User Details</h2>
            <p>Update this user's profile information and account role.</p>
        </div>

        <div class="form-content">
            <?php if ($message !== ""): ?>
                <div class="message <?= editUserEscape($messageType) ?>"
                     role="status">
                    <?= editUserEscape($message) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="editUserForm">
                <div class="photo-section">
                    <div class="avatar">
                        <?php if ($hasPicture): ?>
                            <img
                                src="<?= editUserEscape($pictureUrl) ?>"
                                alt="User profile photo"
                                id="photoPreview"
                            >
                        <?php else: ?>
                            <svg id="defaultAvatar" viewBox="0 0 24 24"
                                 aria-label="Default profile icon" role="img">
                                <circle cx="12" cy="8" r="4"></circle>
                                <path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path>
                            </svg>
                            <img id="photoPreview" src=""
                                 alt="Selected profile photo preview"
                                 style="display:none">
                        <?php endif; ?>
                    </div>

                    <div class="photo-details">
                        <h3>Profile Photo</h3>
                        <p>JPG, PNG, or WebP. Maximum size: 2 MB.</p>

                        <div class="photo-actions">
                            <label class="upload-label">
                                <span id="uploadLabelText">
                                    <?= $hasPicture ? "Change Photo" : "Add Photo" ?>
                                </span>
                                <input type="file" name="profile_picture"
                                       id="profilePictureInput"
                                       accept="image/jpeg,image/png,image/webp">
                            </label>

                            <?php if ($hasPicture): ?>
                                <button type="submit" name="remove_picture"
                                        value="1" class="remove-button"
                                        formnovalidate
                                        onclick="return confirm('Remove this user\\'s profile photo?');">
                                    Remove Photo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name"
                           maxlength="100"
                           value="<?= editUserEscape($user["name"]) ?>"
                           autocomplete="name" required>
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           maxlength="150"
                           value="<?= editUserEscape($user["email"]) ?>"
                           autocomplete="email" required>
                </div>

                <div class="field">
                    <label for="role_id">Account Role</label>
                    <select id="role_id" name="role_id" required>
                        <option value="1"
                            <?= (int)$user["role_id"] === 1 ? "selected" : "" ?>>
                            Admin
                        </option>
                        <option value="2"
                            <?= (int)$user["role_id"] === 2 ? "selected" : "" ?>>
                            User
                        </option>
                    </select>
                    <small>Admins can access the user management dashboard.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>
                    
                    <a href="admin.php" class="btn btn-primary">
Back to Manage users              </a>
                </div>
            </form>
        </div>
    </section>
</main>

<script>
const pictureInput = document.getElementById("profilePictureInput");
const photoPreview = document.getElementById("photoPreview");
const defaultAvatar = document.getElementById("defaultAvatar");
const uploadLabelText = document.getElementById("uploadLabelText");

pictureInput.addEventListener("change", function () {
    const file = this.files && this.files[0];

    if (!file) return;

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, or WebP image.");
        this.value = "";
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert("The photo must be 2 MB or smaller.");
        this.value = "";
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {
        photoPreview.src = event.target.result;
        photoPreview.style.display = "block";

        if (defaultAvatar) {
            defaultAvatar.style.display = "none";
        }

        uploadLabelText.textContent = "Change Photo";
    };

    reader.readAsDataURL(file);
});
</script>
</body>
</html>
