
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

function adminEscape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$result = $conn->query(
    "SELECT id, name, email, role_id, created_at
     FROM users
     ORDER BY id ASC"
);

if (!$result) {
    die("Unable to load users.");
}

$users = [];
$totalUsers = 0;
$totalAdmins = 0;
$totalRegularUsers = 0;

while ($user = $result->fetch_assoc()) {
    $users[] = $user;
    $totalUsers++;

    if ((int) $user["role_id"] === 1) {
        $totalAdmins++;
    } else {
        $totalRegularUsers++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | ApexPlanet</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header__inner">
        <div>
            <h1>Manage Users</h1>
            <p>Welcome, <?= adminEscape($_SESSION["name"] ?? "Admin") ?>!</p>
        </div>
    </div>
</header>

<nav class="site-nav" aria-label="Main navigation">
    <a class="site-nav__link" href="dashboard.php">Home</a>
    <a class="site-nav__link" href="profile.php">My Profile</a>
    <a class="site-nav__link site-nav__link--active"
       href="admin.php" aria-current="page">Manage Users</a>
    <a class="site-nav__link" href="logout.php">Logout</a>
</nav>

<main class="page-container page-container--wide">

    <section class="content-card">
        <div class="content-card__header">
            <div>
                <h2>User Management</h2>
                <p>View, add, edit, and manage user accounts.</p>
            </div>
<br>
            <a href="add_user.php" class="button button--primary">
                + Add User
            </a>
        </div>

        <div class="content-card__body">

            <div class="stats-grid">
                <article class="stat-card">
                    <p class="stat-card__label">Total Accounts</p>
                    <p class="stat-card__value"><?= $totalUsers ?></p>
                </article>

                <article class="stat-card">
                    <p class="stat-card__label">Administrators</p>
                    <p class="stat-card__value"><?= $totalAdmins ?></p>
                </article>

                <article class="stat-card">
                    <p class="stat-card__label">Regular Users</p>
                    <p class="stat-card__value"><?= $totalRegularUsers ?></p>
                </article>
            </div>

            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="status-message status-message--success"
                     role="status">
                    <?= adminEscape($_SESSION["admin_message"]) ?>
                </div>
                <?php unset($_SESSION["admin_message"]); ?>
            <?php endif; ?>

            <?php if (count($users) === 0): ?>
                <div class="empty-state">
                    <h3>No accounts yet</h3>
                    <p>Use Add User to create an account.</p>
                </div>
            <?php else: ?>

                <div class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th scope="col">S.No</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Registered</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php $serialNumber = 1; ?>

                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $serialNumber++ ?></td>

                                <td><?= adminEscape($user["name"]) ?></td>

                                <td><?= adminEscape($user["email"]) ?></td>

                                <td>
                                    <span class="role-badge">
                                        <?= (int) $user["role_id"] === 1
                                            ? "Admin"
                                            : "User" ?>
                                    </span>
                                </td>

                                <td>
                                    <?= adminEscape($user["created_at"]) ?>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a
                                            class="button button--secondary"
                                            href="edit_user.php?id=<?= (int) $user["id"] ?>">
                                            Edit
                                        </a>

                                        <?php if (
                                            (int) $user["id"] !==
                                            (int) $_SESSION["user_id"]
                                        ): ?>
                                            <form
                                                class="delete-user-form"
                                                method="POST"
                                                action="delete_user.php">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $user["id"] ?>">

                                                <button
                                                    type="submit"
                                                    class="button button--danger">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </section>

</main>

<footer class="site-footer">
    <p>© 2026 ApexPlanet User Management System</p>
</footer>

<!-- Delete confirmation modal -->
<div id="deleteModal" class="delete-modal-overlay" hidden>
    <div class="delete-modal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="deleteModalTitle"
         aria-describedby="deleteModalDescription">

        <div class="delete-modal-icon" aria-hidden="true">!</div>

        <h2 id="deleteModalTitle">Delete Account?</h2>

        <p id="deleteModalDescription">
            Are you sure you want to delete this account?
            This action cannot be undone.
        </p>

        <div class="delete-modal-actions">
            <button type="button"
                    id="cancelDelete"
                    class="button button--secondary">
                Cancel
            </button>

            <button type="button"
                    id="confirmDelete"
                    class="button button--danger">
                Delete Account
            </button>
        </div>
    </div>
</div>

<script>
let deleteFormToSubmit = null;
let previouslyFocusedElement = null;

const deleteModal = document.getElementById("deleteModal");
const cancelDeleteButton = document.getElementById("cancelDelete");
const confirmDeleteButton = document.getElementById("confirmDelete");

function confirmDelete(event) {
    event.preventDefault();

    deleteFormToSubmit = event.currentTarget;
    previouslyFocusedElement = document.activeElement;

    deleteModal.hidden = false;
    cancelDeleteButton.focus();
}

function closeDeleteModal() {
    deleteModal.hidden = true;
    deleteFormToSubmit = null;

    if (previouslyFocusedElement) {
        previouslyFocusedElement.focus();
        previouslyFocusedElement = null;
    }
}

document.querySelectorAll(".delete-user-form").forEach(function (form) {
    form.addEventListener("submit", confirmDelete);
});

cancelDeleteButton.addEventListener("click", closeDeleteModal);

confirmDeleteButton.addEventListener("click", function () {
    if (deleteFormToSubmit) {
        deleteFormToSubmit.submit();
    }
});

deleteModal.addEventListener("click", function (event) {
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !deleteModal.hidden) {
        closeDeleteModal();
    }
});
</script>

</body>
</html>
