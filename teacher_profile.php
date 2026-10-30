<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

/*
|--------------------------------------------------------------------------
| TEACHER ONLY
|--------------------------------------------------------------------------
*/
if ($role !== "teacher") {
    header("Location: admin_dashboard.php");
    exit();
}

$success_message = "";
$error_message = "";

/*
|--------------------------------------------------------------------------
| LOAD LOGGED-IN TEACHER
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        u.id,
        u.name,
        u.username,
        u.email,
        u.department_id,
        u.created_at,
        d.name AS department_name,
        d.code AS department_code
    FROM users u
    LEFT JOIN departments d
        ON u.department_id = d.id
    WHERE u.id = ?
      AND u.role = 'teacher'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$teacher = mysqli_fetch_assoc($result);

/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name              = trim($_POST["name"] ?? "");
    $email             = trim($_POST["email"] ?? "");
    $current_password  = $_POST["current_password"] ?? "";
    $new_password      = $_POST["new_password"] ?? "";
    $confirm_password  = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "") {
        $error_message = "Name and email are required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";

    } else {

        /*
        |------------------------------------------------------------------
        | CHECK DUPLICATE EMAIL
        |------------------------------------------------------------------
        */
        $email_sql = "
            SELECT id
            FROM users
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ";

        $email_stmt = mysqli_prepare($conn, $email_sql);
        mysqli_stmt_bind_param($email_stmt, "si", $email, $user_id);
        mysqli_stmt_execute($email_stmt);
        $email_result = mysqli_stmt_get_result($email_stmt);

        if (mysqli_num_rows($email_result) > 0) {
            $error_message = "This email address is already being used by another account.";

        } elseif (
            $new_password !== "" ||
            $confirm_password !== "" ||
            $current_password !== ""
        ) {

            /*
            |------------------------------------------------------------------
            | PASSWORD CHANGE
            |------------------------------------------------------------------
            */
            if ($current_password === "") {
                $error_message = "Enter your current password to change the password.";

            } elseif (!password_verify($current_password, $teacher["password"] ?? "")) {
                /*
                | Password is loaded below when needed. This branch is replaced
                | by the secure password lookup immediately below.
                */
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SECURE PASSWORD LOOKUP / PASSWORD VALIDATION
        |--------------------------------------------------------------------------
        */
        if ($error_message === "" &&
            (
                $new_password !== "" ||
                $confirm_password !== "" ||
                $current_password !== ""
            )
        ) {

            $password_sql = "
                SELECT password
                FROM users
                WHERE id = ?
                  AND role = 'teacher'
                LIMIT 1
            ";

            $password_stmt = mysqli_prepare($conn, $password_sql);
            mysqli_stmt_bind_param($password_stmt, "i", $user_id);
            mysqli_stmt_execute($password_stmt);
            $password_result = mysqli_stmt_get_result($password_stmt);
            $password_row = mysqli_fetch_assoc($password_result);

            if (!$password_row || !password_verify($current_password, $password_row["password"])) {
                $error_message = "Your current password is incorrect.";

            } elseif ($new_password === "") {
                $error_message = "Enter a new password.";

            } elseif (strlen($new_password) < 6) {
                $error_message = "The new password must contain at least 6 characters.";

            } elseif ($new_password !== $confirm_password) {
                $error_message = "The new password and confirmation password do not match.";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE CHANGES
        |--------------------------------------------------------------------------
        */
        if ($error_message === "") {

            if ($new_password !== "") {

                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $update_sql = "
                    UPDATE users
                    SET name = ?,
                        email = ?,
                        password = ?
                    WHERE id = ?
                      AND role = 'teacher'
                ";

                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $name,
                    $email,
                    $hashed_password,
                    $user_id
                );

            } else {

                $update_sql = "
                    UPDATE users
                    SET name = ?,
                        email = ?
                    WHERE id = ?
                      AND role = 'teacher'
                ";

                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssi",
                    $name,
                    $email,
                    $user_id
                );
            }

            if (mysqli_stmt_execute($update_stmt)) {

                $_SESSION["user_name"] = $name;

                $user_name = $name;
                $teacher["name"] = $name;
                $teacher["email"] = $email;

                $success_message = "Your profile has been updated successfully.";

                // Clear password fields after successful update.
                $_POST["current_password"] = "";
                $_POST["new_password"] = "";
                $_POST["confirm_password"] = "";

            } else {
                $error_message = "Unable to update your profile. Please try again.";
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

    <title>My Profile | Student Management System</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    .teacher-profile-page {
        min-height: 100vh;
        background: #f5f7fb;
    }

    .teacher-profile-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh;
    }

    .teacher-profile-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .teacher-profile-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        padding: 30px;
        box-sizing: border-box;
    }

    .profile-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .profile-topbar h1 {
        margin: 0;
        color: #182033;
        font-size: 28px;
    }

    .profile-topbar p {
        margin: 7px 0 0;
        color: #7d8798;
        font-size: 14px;
    }

    .profile-user {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 14px 9px 9px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 17px;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .profile-avatar {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #4f46e5;
        color: #fff;
        font-weight: 800;
    }

    .profile-user strong {
        display: block;
        color: #182033;
        font-size: 13px;
    }

    .profile-user span {
        display: block;
        margin-top: 2px;
        color: #7d8798;
        font-size: 12px;
    }

    .profile-hero {
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 24px;
        background: linear-gradient(135deg, #312e81 0%, #4f46e5 52%, #6366f1 100%);
        color: #fff;
        box-shadow: 0 18px 45px rgba(79, 70, 229, .18);
    }

    .profile-hero::before,
    .profile-hero::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .profile-hero::before {
        width: 220px;
        height: 220px;
        right: -65px;
        top: -95px;
    }

    .profile-hero::after {
        width: 150px;
        height: 150px;
        right: 150px;
        bottom: -105px;
    }

    .profile-hero-content {
        position: relative;
        z-index: 1;
        max-width: 760px;
    }

    .profile-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .18);
        font-size: 12px;
        font-weight: 700;
        margin-bottom: 14px;
    }

    .profile-hero h2 {
        margin: 0 0 8px;
        font-size: clamp(25px, 3vw, 36px);
    }

    .profile-hero p {
        margin: 0;
        max-width: 690px;
        color: rgba(255, 255, 255, .86);
        line-height: 1.7;
        font-size: 14px;
    }

    .profile-content {
        max-width: 950px;
        margin: 0 auto;
    }

    .profile-card {
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .profile-card-header {
        display: flex;
        align-items: center;
        gap: 17px;
        padding: 25px;
        background: #fafbff;
        border-bottom: 1px solid #edf0f5;
    }

    .profile-big-avatar {
        width: 68px;
        height: 68px;
        flex: 0 0 68px;
        display: grid;
        place-items: center;
        border-radius: 19px;
        background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
        color: #3730a3;
        font-size: 24px;
        font-weight: 800;
    }

    .profile-card-header h3 {
        margin: 0;
        color: #20283a;
        font-size: 20px;
    }

    .profile-card-header p {
        margin: 5px 0 0;
        color: #8992a3;
        font-size: 12px;
    }

    .profile-body {
        padding: 26px;
    }

    .profile-alert {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 15px 17px;
        margin-bottom: 22px;
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.55;
    }

    .profile-alert.success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
    }

    .profile-alert.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    .profile-alert i {
        margin-top: 2px;
        font-size: 17px;
    }

    .profile-section-title {
        margin: 0 0 15px;
        color: #20283a;
        font-size: 15px;
        font-weight: 800;
    }

    .profile-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .profile-field {
        display: flex;
        flex-direction: column;
    }

    .profile-field.full {
        grid-column: 1 / -1;
    }

    .profile-field label {
        margin-bottom: 7px;
        color: #4b5563;
        font-size: 12px;
        font-weight: 700;
    }

    .profile-field input {
        width: 100%;
        min-height: 46px;
        padding: 0 13px;
        box-sizing: border-box;
        border: 1px solid #dfe3eb;
        border-radius: 11px;
        background: #fff;
        color: #20283a;
        outline: none;
        font-size: 13px;
        transition: .2s ease;
    }

    .profile-field input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, .10);
    }

    .profile-field input[readonly] {
        background: #f8fafc;
        color: #7d8798;
        cursor: not-allowed;
    }

    .profile-field small {
        margin-top: 6px;
        color: #98a1b1;
        font-size: 10px;
    }

    .password-section {
        padding-top: 25px;
        border-top: 1px solid #edf0f5;
    }

    .password-help {
        margin: -7px 0 17px;
        color: #8992a3;
        font-size: 11px;
        line-height: 1.6;
    }

    .profile-actions {
        display: flex;
        justify-content: flex-end;
        gap: 11px;
        padding-top: 22px;
        border-top: 1px solid #edf0f5;
    }

    .profile-btn {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 20px;
        border: none;
        border-radius: 13px;
        text-decoration: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        transition: .2s ease;
    }

    .profile-cancel-btn {
        background: #f3f4f6;
        color: #4b5563;
    }

    .profile-cancel-btn:hover {
        background: #e5e7eb;
        transform: translateY(-1px);
    }

    .profile-save-btn {
        background: #4f46e5;
        color: #fff;
        box-shadow: 0 8px 20px rgba(79, 70, 229, .18);
    }

    .profile-save-btn:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .profile-note {
        margin-top: 17px;
        text-align: center;
        color: #98a1b1;
        font-size: 11px;
        line-height: 1.6;
    }

    @media (max-width: 900px) {
        .teacher-profile-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px;
        }

        .teacher-profile-page .sidebar {
            width: 260px;
        }
    }

    @media (max-width: 650px) {
        .profile-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .profile-user {
            align-self: stretch;
        }

        .profile-hero {
            padding: 24px 20px;
            border-radius: 20px;
        }

        .profile-body {
            padding: 20px;
        }

        .profile-card-header {
            padding: 20px;
        }

        .profile-form-grid {
            grid-template-columns: 1fr;
        }

        .profile-field.full {
            grid-column: auto;
        }

        .profile-actions {
            flex-direction: column-reverse;
        }

        .profile-btn {
            width: 100%;
        }
    }
    </style>
</head>

<body class="dashboard-page teacher-profile-page">

    <div class="dashboard-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>

                <div>
                    <h2>Student MS</h2>
                    <span>Teacher Portal</span>
                </div>
            </div>

            <nav class="sidebar-menu">

                <a href="teacher_dashboard.php">
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="courses.php">
                    <i class="fas fa-book"></i>
                    <span>My Courses</span>
                </a>

                <a href="sections.php">
                    <i class="fas fa-layer-group"></i>
                    <span>My Sections</span>
                </a>

                <a href="students.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>My Students</span>
                </a>

                <a href="subjects.php">
                    <i class="fas fa-book-open"></i>
                    <span>Subjects</span>
                </a>

                <a href="marks.php">
                    <i class="fas fa-pen-to-square"></i>
                    <span>Marks</span>
                </a>

                <a href="result.php">
                    <i class="fas fa-chart-column"></i>
                    <span>Results</span>
                </a>

                <a href="teacher_profile.php" class="active">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>

            </nav>

            <div class="sidebar-bottom">
                <a href="logout.php">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>

        </aside>

        <!-- MAIN -->
        <main class="dashboard-main">

            <header class="profile-topbar">
                <div>
                    <h1>My Profile</h1>
                    <p>Manage your teacher account information and password.</p>
                </div>

                <div class="profile-user">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>

                    <div>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                        <span>Teacher</span>
                    </div>
                </div>
            </header>

            <section class="profile-hero">
                <div class="profile-hero-content">

                    <span class="profile-badge">
                        <i class="fas fa-user-shield"></i>
                        Account Settings
                    </span>

                    <h2>Manage Your Profile</h2>

                    <p>
                        Update your personal information and change your password.
                        Your username and department are controlled by the system
                        and cannot be changed from this page.
                    </p>

                </div>
            </section>

            <div class="profile-content">

                <section class="profile-card">

                    <div class="profile-card-header">

                        <div class="profile-big-avatar">
                            <?= strtoupper(substr($teacher["name"], 0, 1)) ?>
                        </div>

                        <div>
                            <h3><?= htmlspecialchars($teacher["name"]) ?></h3>
                            <p>
                                @<?= htmlspecialchars($teacher["username"]) ?>
                                · Teacher Account
                            </p>
                        </div>

                    </div>

                    <div class="profile-body">

                        <?php if ($success_message !== ""): ?>
                        <div class="profile-alert success">
                            <i class="fas fa-circle-check"></i>
                            <div>
                                <strong>Success</strong>
                                <?= htmlspecialchars($success_message) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($error_message !== ""): ?>
                        <div class="profile-alert error">
                            <i class="fas fa-circle-exclamation"></i>
                            <div>
                                <strong>Unable to update profile</strong>
                                <?= htmlspecialchars($error_message) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="teacher_profile.php">

                            <h3 class="profile-section-title">
                                Personal Information
                            </h3>

                            <div class="profile-form-grid">

                                <div class="profile-field">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" maxlength="100"
                                        value="<?= htmlspecialchars($_POST["name"] ?? $teacher["name"]) ?>" required>
                                </div>

                                <div class="profile-field">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" maxlength="100"
                                        value="<?= htmlspecialchars($_POST["email"] ?? $teacher["email"]) ?>" required>
                                </div>

                                <div class="profile-field">
                                    <label for="username">Username</label>
                                    <input type="text" id="username"
                                        value="<?= htmlspecialchars($teacher["username"]) ?>" readonly>
                                    <small>Username cannot be changed here.</small>
                                </div>

                                <div class="profile-field">
                                    <label for="department">Department</label>
                                    <input type="text" id="department" value="<?= htmlspecialchars(
                                        ($teacher["department_name"] ?? "Not assigned")
                                        . (
                                            !empty($teacher["department_code"])
                                            ? " (" . $teacher["department_code"] . ")"
                                            : ""
                                        )
                                    ) ?>" readonly>
                                    <small>Department is managed by the administrator.</small>
                                </div>

                            </div>

                            <div class="password-section">

                                <h3 class="profile-section-title">
                                    Change Password
                                </h3>

                                <p class="password-help">
                                    Leave all password fields empty if you do not want
                                    to change your current password. A new password
                                    must contain at least 6 characters.
                                </p>

                                <div class="profile-form-grid">

                                    <div class="profile-field">
                                        <label for="current_password">
                                            Current Password
                                        </label>
                                        <input type="password" id="current_password" name="current_password"
                                            autocomplete="current-password">
                                    </div>

                                    <div class="profile-field">
                                        <label for="new_password">
                                            New Password
                                        </label>
                                        <input type="password" id="new_password" name="new_password" minlength="6"
                                            autocomplete="new-password">
                                    </div>

                                    <div class="profile-field">
                                        <label for="confirm_password">
                                            Confirm New Password
                                        </label>
                                        <input type="password" id="confirm_password" name="confirm_password"
                                            minlength="6" autocomplete="new-password">
                                    </div>

                                </div>

                            </div>

                            <div class="profile-actions">

                                <a href="teacher_dashboard.php" class="profile-btn profile-cancel-btn">
                                    <i class="fas fa-arrow-left"></i>
                                    Back to Dashboard
                                </a>

                                <button type="submit" class="profile-btn profile-save-btn">
                                    <i class="fas fa-floppy-disk"></i>
                                    Save Changes
                                </button>

                            </div>

                        </form>

                        <div class="profile-note">
                            Only your own teacher account can be modified from this page.
                        </div>

                    </div>

                </section>

            </div>

        </main>

    </div>

</body>

</html>