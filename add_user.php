
<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["user_id"], $_SESSION["role_id"])) {
    header("Location: login.php");
    exit;
}

if ((int) $_SESSION["role_id"] !== 1) {
    http_response_code(403);
    exit("Access denied. Admins only.");
}

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $roleId = filter_input(INPUT_POST, "role_id", FILTER_VALIDATE_INT);

    if ($name === "" || strlen($name) > 100) {
        $message = "Enter a name up to 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $message = "Enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";
    } elseif (!in_array($roleId, [1, 2], true)) {
        $message = "Select a valid role.";
    } else {
        $profilePicture = null;

        if (
            isset($_FILES["profile_picture"]) &&
            $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["profile_picture"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $message = "The profile picture upload failed.";
            } elseif ($file["size"] > 2 * 1024 * 1024) {
                $message = "Profile picture must be 2 MB or smaller.";
            } elseif (!is_uploaded_file($file["tmp_name"])) {
                $message = "Invalid uploaded file.";
            } else {
                $imageInfo = @getimagesize($file["tmp_name"]);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!$imageInfo || !isset($allowedTypes[$mime])) {
                    $message = "Choose a valid JPG, PNG, or WebP image.";
                } else {
                    $uploadDir = __DIR__ . "/uploads/profiles/";

                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    if (!is_writable($uploadDir)) {
                        $message = "The profile picture folder is not writable.";
                    } else {
                        $filename = bin2hex(random_bytes(16)) . "." . $allowedTypes[$mime];

                        if (move_uploaded_file($file["tmp_name"], $uploadDir . $filename)) {
                            $profilePicture = "uploads/profiles/" . $filename;
                        } else {
                            $message = "Could not save the profile picture.";
                        }
                    }
                }
            }
        }

        if ($message === "") {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $message = "That email address is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password, role_id, profile_picture)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "sssis",
                    $name,
                    $email,
                    $hashedPassword,
                    $roleId,
                    $profilePicture
                );

                if ($stmt->execute()) {
                    $success = true;
                    $message = "User account created successfully.";
                    if ($profilePicture !== null) {
                        // Keep the uploaded file; the database now references it.
                        $profilePicture = null;
                    }
                } else {
                    $message = $conn->errno === 1062
                        ? "That email address is already registered."
                        : "Unable to create the account.";
                }

                $stmt->close();
            }
        }

        // Remove an uploaded file if account creation did not succeed.
        if (!$success && isset($filename) && isset($uploadDir)) {
            $savedFile = $uploadDir . $filename;
            if (is_file($savedFile)) {
                unlink($savedFile);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User | ApexPlanet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Add User</h1>
    <p>Create an account from the Admin dashboard</p>
</header>



<main class="container">
    <h2>Create Account</h2>

    <?php if ($message !== ""): ?>
        <div class="alert <?php echo $success ? "alert-success" : "alert-error"; ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="addUserForm">
    <div class="form-group">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" maxlength="100"
               required aria-describedby="name-error">
        <small class="field-error" id="name-error" aria-live="polite"></small>
    </div>

    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" maxlength="150"
               required aria-describedby="email-error">
        <small class="field-error" id="email-error" aria-live="polite"></small>
    </div>

   <div class="form-group">
    <label for="password">Password (minimum 8 characters)</label>
    <div class="password-input-row">
        <input type="password" id="password" name="password" minlength="8"
               required aria-describedby="password-error">
        <button type="button" class="password-toggle"
                data-target="password" aria-pressed="false">Show</button>
    </div>
    <small class="field-error" id="password-error" aria-live="polite"></small>
</div>

   <div class="form-group">
    <label for="confirm_password">Confirm Password</label>
    <div class="password-input-row">
        <input type="password" id="confirm_password" name="confirm_password"
               required aria-describedby="confirm-password-error">
        <button type="button" class="password-toggle"
                data-target="confirm_password" aria-pressed="false">Show</button>
    </div>
    <small class="field-error" id="confirm-password-error"
           aria-live="polite"></small>
</div>

    <div class="form-group">
        <label for="profile_picture">Profile Picture (optional, JPG/PNG/WebP, max 2 MB)</label>
        <input type="file" id="profile_picture" name="profile_picture"
               accept="image/jpeg,image/png,image/webp"
               aria-describedby="picture-error">
        <small class="field-error" id="picture-error" aria-live="polite"></small>
    </div>

    <div class="form-group">
        <label for="role_id">Role</label>
        <select id="role_id" name="role_id" required>
            <option value="2" selected>User</option>
            <option value="1">Admin</option>
        </select>
        <br>
   <div class="add-user-actions">
    <button type="submit" class="btn">Create Account</button>
    <a href="admin.php" class="btn">Back to Manage Users</a>
    <br>
</div>
</main>

<footer>
    <p>ApexPlanet User Management System</p>
</footer>

<script>
    document.querySelectorAll(".password-toggle").forEach(function (button) {
    button.addEventListener("click", function () {
        const input = document.getElementById(button.dataset.target);

        if (!input) return;

        const showPassword = input.type === "password";
        input.type = showPassword ? "text" : "password";
        button.textContent = showPassword ? "Hide" : "Show";
        button.setAttribute("aria-pressed", String(showPassword));
    });
});
const addUserForm = document.getElementById("addUserForm");

const nameInput = document.getElementById("name");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirm_password");
const pictureInput = document.getElementById("profile_picture");

function showFieldError(input, message, errorId) {
    document.getElementById(errorId).textContent = message;
    input.setAttribute("aria-invalid", message ? "true" : "false");
}

function validateName() {
    const value = nameInput.value.trim();
    let error = "";

    if (!value) {
        error = "Please enter the user's full name.";
    } else if (value.length < 2) {
        error = "Name must contain at least 2 characters.";
    }

    showFieldError(nameInput, error, "name-error");
    return !error;
}

let emailCheckTimer;
let emailCheckRequest = 0;
let emailIsAvailable = null;

async function validateEmail() {
    const value = emailInput.value.trim();
    let error = "";

    clearTimeout(emailCheckTimer);
    emailCheckRequest++;
    emailIsAvailable = null;

    if (!value) {
        error = "Email address is required.";
    } else if (/\s/.test(value)) {
        error = "Email must not contain spaces.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
        error = "Enter a valid email, e.g. name@example.com.";
    }

    showFieldError(emailInput, error, "email-error");

    if (error) return false;

    const requestNumber = emailCheckRequest;

    try {
        const response = await fetch(
            "check_email.php?email=" + encodeURIComponent(value),
            { headers: { "Accept": "application/json" } }
        );

        if (!response.ok) {
            throw new Error("Email check failed");
        }

        const data = await response.json();

        if (
            requestNumber !== emailCheckRequest ||
            value !== emailInput.value.trim()
        ) {
            return false;
        }

        emailIsAvailable = data.available === true;

        if (!emailIsAvailable) {
            showFieldError(
                emailInput,
                data.message || "This email is already registered. Please use another email address.",
                "email-error"
            );
        } else {
            showFieldError(emailInput, "", "email-error");
        }

        return emailIsAvailable;
    } catch (error) {
        emailIsAvailable = null;
        showFieldError(
            emailInput,
            "Could not check email availability. Please try again.",
            "email-error"
        );
        return false;
    }
}

function validatePassword() {
    let error = "";

    if (!passwordInput.value) {
        error = "Please enter a password.";
    } else if (passwordInput.value.length < 8) {
        error = "Password must contain at least 8 characters.";
    }

    showFieldError(passwordInput, error, "password-error");

    if (confirmPasswordInput.value) {
        validateConfirmPassword();
    }

    return !error;
}

function validateConfirmPassword() {
    let error = "";

    if (!confirmPasswordInput.value) {
        error = "Please confirm the password.";
    } else if (confirmPasswordInput.value !== passwordInput.value) {
        error = "Passwords do not match.";
    }

    showFieldError(
        confirmPasswordInput,
        error,
        "confirm-password-error"
    );

    return !error;
}

function validatePicture() {
    let error = "";
    const file = pictureInput.files[0];

    if (file) {
        const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

        if (!allowedTypes.includes(file.type)) {
            error = "Choose a JPG, PNG, or WebP image.";
        } else if (file.size > 2 * 1024 * 1024) {
            error = "Image must be 2 MB or smaller.";
        }
    }

    showFieldError(pictureInput, error, "picture-error");
    return !error;
}

nameInput.addEventListener("input", validateName);

emailInput.addEventListener("input", function () {
    clearTimeout(emailCheckTimer);

    // Check the format immediately, then check the database after typing pauses.
    const value = emailInput.value.trim();

    if (
        !value ||
        /\s/.test(value) ||
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
    ) {
        emailCheckRequest++;
        emailIsAvailable = null;
        validateEmail();
        return;
    }

    emailCheckRequest++;
    emailIsAvailable = null;
    showFieldError(emailInput, "Checking email availability...", "email-error");

    emailCheckTimer = setTimeout(validateEmail, 500);
});

passwordInput.addEventListener("input", validatePassword);
confirmPasswordInput.addEventListener("input", validateConfirmPassword);
pictureInput.addEventListener("change", validatePicture);


addUserForm.addEventListener("submit", async function (event) {
    event.preventDefault();

    const nameValid = validateName();
    const emailValid = await validateEmail();
    const passwordValid = validatePassword();
    const confirmPasswordValid = validateConfirmPassword();
    const pictureValid = validatePicture();

    if (
        !nameValid ||
        !emailValid ||
        !passwordValid ||
        !confirmPasswordValid ||
        !pictureValid
    ) {
        return;
    }

    addUserForm.submit();
});

</script>
</body>
</html>

</body>
</html>
