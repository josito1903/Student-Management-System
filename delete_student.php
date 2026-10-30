<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/
if ($role !== "admin") {
    header("Location: teacher_dashboard.php");
    exit();
}

$teacher_id = (int) ($_GET["id"] ?? $_POST["teacher_id"] ?? 0);

if ($teacher_id <= 0) {
    header("Location: teachers.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| LOAD TEACHER
|--------------------------------------------------------------------------
| Only users with role = teacher can be deleted here.
*/
$sql = "
    SELECT
        u.id,
        u.name,
        u.username,
        u.email,
        u.created_at,
        d.name AS department_name,
        d.code AS department_code
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
      AND u.role = 'teacher'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    header("Location: teachers.php");
    exit();
}

$teacher = mysqli_fetch_assoc($result);

/*
|--------------------------------------------------------------------------
| CHECK DEPENDENCIES
|--------------------------------------------------------------------------
| Deleting a teacher can cascade into courses/sections and their
| academic records because of the current database foreign keys.
| Therefore deletion is BLOCKED when academic data exists.
*/

function get_count(mysqli $conn, string $sql, int $teacher_id): int
{
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    return (int) ($row["total"] ?? 0);
}

$course_count = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM courses WHERE created_by = ?",
    $teacher_id
);

$subject_count = get_count(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM subjects s
    INNER JOIN courses c ON s.course_id = c.id
    WHERE c.created_by = ?
    ",
    $teacher_id
);

$section_count = get_count(
    $conn,
    "SELECT COUNT(*) AS total FROM sections WHERE teacher_id = ?",
    $teacher_id
);

$student_count = get_count(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM students s
    INNER JOIN sections sec ON s.section_id = sec.id
    WHERE sec.teacher_id = ?
    ",
    $teacher_id
);

$mark_count = get_count(
    $conn,
    "
    SELECT COUNT(*) AS total
    FROM marks m
    INNER JOIN students s ON m.student_id = s.id
    INNER JOIN sections sec ON s.section_id = sec.id
    WHERE sec.teacher_id = ?
    ",
    $teacher_id
);

$has_dependencies =
    $course_count > 0 ||
    $subject_count > 0 ||
    $section_count > 0 ||
    $student_count > 0 ||
    $mark_count > 0;

$error_message = "";

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
| Re-check dependencies immediately before deletion.
| This prevents accidental cascading deletion of academic records.
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if ($has_dependencies) {
        $error_message = "This teacher cannot be deleted because academic records are still associated with this account.";
    } else {

        // Re-check once more in case another record was created after page load.
        $course_count = get_count(
            $conn,
            "SELECT COUNT(*) AS total FROM courses WHERE created_by = ?",
            $teacher_id
        );

        $section_count = get_count(
            $conn,
            "SELECT COUNT(*) AS total FROM sections WHERE teacher_id = ?",
            $teacher_id
        );

        if ($course_count > 0 || $section_count > 0) {
            $error_message = "Deletion was stopped because this teacher now has associated academic records.";
        } else {

            $delete_sql = "
                DELETE FROM users
                WHERE id = ?
                  AND role = 'teacher'
            ";

            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $teacher_id);

            if (
                mysqli_stmt_execute($delete_stmt) &&
                mysqli_stmt_affected_rows($delete_stmt) > 0
            ) {
                header("Location: teachers.php?deleted=1");
                exit();
            }

            $error_message = "The teacher could not be deleted. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delete Teacher | Student Management System</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    .delete-teacher-page {
        min-height: 100vh;
        background: #f5f7fb;
    }

    .delete-teacher-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh;
    }

    .delete-teacher-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .delete-teacher-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        padding: 30px;
        box-sizing: border-box;
    }

    .teacher-delete-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .teacher-delete-topbar h1 {
        margin: 0;
        color: #182033;
        font-size: 28px;
    }

    .teacher-delete-topbar p {
        margin: 7px 0 0;
        color: #7d8798;
        font-size: 14px;
    }

    .teacher-delete-profile {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 14px 9px 9px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 17px;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .teacher-delete-avatar {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #4f46e5;
        color: #fff;
        font-weight: 800;
    }

    .teacher-delete-profile strong {
        display: block;
        color: #182033;
        font-size: 13px;
    }

    .teacher-delete-profile span {
        display: block;
        margin-top: 2px;
        color: #7d8798;
        font-size: 12px;
    }

    .teacher-delete-hero {
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 24px;
        background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 52%, #ef4444 100%);
        color: #fff;
        box-shadow: 0 18px 45px rgba(220, 38, 38, .18);
    }

    .teacher-delete-hero::before,
    .teacher-delete-hero::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .teacher-delete-hero::before {
        width: 210px;
        height: 210px;
        right: -60px;
        top: -80px;
    }

    .teacher-delete-hero::after {
        width: 150px;
        height: 150px;
        right: 130px;
        bottom: -100px;
    }

    .teacher-delete-hero-content {
        position: relative;
        z-index: 1;
        max-width: 780px;
    }

    .teacher-delete-badge {
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

    .teacher-delete-hero h2 {
        margin: 0 0 8px;
        font-size: clamp(25px, 3vw, 36px);
    }

    .teacher-delete-hero p {
        margin: 0;
        max-width: 700px;
        color: rgba(255, 255, 255, .86);
        line-height: 1.7;
        font-size: 14px;
    }

    .teacher-delete-content {
        max-width: 950px;
        margin: 0 auto;
    }

    .teacher-delete-card {
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .teacher-delete-header {
        display: flex;
        align-items: center;
        gap: 17px;
        padding: 25px;
        background: #fafbff;
        border-bottom: 1px solid #edf0f5;
    }

    .teacher-big-avatar {
        width: 68px;
        height: 68px;
        flex: 0 0 68px;
        display: grid;
        place-items: center;
        border-radius: 19px;
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #b91c1c;
        font-size: 24px;
        font-weight: 800;
    }

    .teacher-delete-header h3 {
        margin: 0;
        color: #20283a;
        font-size: 20px;
    }

    .teacher-delete-header p {
        margin: 5px 0 0;
        color: #8992a3;
        font-size: 12px;
    }

    .teacher-delete-body {
        padding: 26px;
    }

    .danger-box,
    .blocked-box {
        display: flex;
        align-items: flex-start;
        gap: 13px;
        padding: 17px;
        margin-bottom: 23px;
        border-radius: 15px;
    }

    .danger-box {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #9a3412;
    }

    .blocked-box {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .danger-box i,
    .blocked-box i {
        font-size: 20px;
        margin-top: 1px;
    }

    .danger-box strong,
    .blocked-box strong {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
    }

    .danger-box p,
    .blocked-box p {
        margin: 0;
        font-size: 12px;
        line-height: 1.6;
    }

    .teacher-details {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 13px;
        margin-bottom: 24px;
    }

    .teacher-detail-item {
        padding: 15px;
        border: 1px solid #edf0f5;
        border-radius: 13px;
        background: #fff;
    }

    .teacher-detail-item label {
        display: block;
        margin-bottom: 6px;
        color: #98a1b1;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .teacher-detail-item strong {
        display: block;
        color: #374151;
        font-size: 13px;
    }

    .teacher-detail-item span {
        display: block;
        margin-top: 3px;
        color: #8992a3;
        font-size: 11px;
    }

    .dependency-title {
        margin: 8px 0 12px;
        color: #374151;
        font-size: 14px;
        font-weight: 800;
    }

    .dependency-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 25px;
    }

    .dependency-item {
        padding: 15px 10px;
        text-align: center;
        border: 1px solid #edf0f5;
        border-radius: 13px;
        background: #fafbff;
    }

    .dependency-item strong {
        display: block;
        color: #20283a;
        font-size: 21px;
    }

    .dependency-item span {
        display: block;
        margin-top: 4px;
        color: #8992a3;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .teacher-delete-actions {
        display: flex;
        justify-content: flex-end;
        gap: 11px;
        padding-top: 22px;
        border-top: 1px solid #edf0f5;
    }

    .teacher-delete-btn {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 19px;
        border: none;
        border-radius: 13px;
        text-decoration: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        transition: .2s ease;
    }

    .teacher-cancel-btn {
        background: #f3f4f6;
        color: #4b5563;
    }

    .teacher-cancel-btn:hover {
        background: #e5e7eb;
        transform: translateY(-1px);
    }

    .teacher-confirm-btn {
        background: #dc2626;
        color: #fff;
        box-shadow: 0 8px 20px rgba(220, 38, 38, .18);
    }

    .teacher-confirm-btn:hover {
        background: #b91c1c;
        transform: translateY(-1px);
    }

    .teacher-confirm-btn:disabled {
        background: #d1d5db;
        color: #6b7280;
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }

    .teacher-delete-note {
        margin-top: 17px;
        text-align: center;
        color: #98a1b1;
        font-size: 11px;
        line-height: 1.6;
    }

    @media (max-width: 1000px) {
        .dependency-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .delete-teacher-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px;
        }

        .delete-teacher-page .sidebar {
            width: 260px;
        }
    }

    @media (max-width: 650px) {
        .teacher-delete-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .teacher-delete-profile {
            align-self: stretch;
        }

        .teacher-delete-hero {
            padding: 24px 20px;
            border-radius: 20px;
        }

        .teacher-delete-body {
            padding: 20px;
        }

        .teacher-delete-header {
            padding: 20px;
        }

        .teacher-details {
            grid-template-columns: 1fr;
        }

        .dependency-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .teacher-delete-actions {
            flex-direction: column-reverse;
        }

        .teacher-delete-btn {
            width: 100%;
        }
    }
    </style>
</head>

<body class="dashboard-page delete-teacher-page">

    <div class="dashboard-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>

                <div>
                    <h2>Student MS</h2>
                    <span>Management System</span>
                </div>
            </div>

            <nav class="sidebar-menu">

                <a href="admin_dashboard.php">
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="departments.php">
                    <i class="fas fa-building-columns"></i>
                    <span>Departments</span>
                </a>

                <a href="teachers.php" class="active">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>

                <a href="courses.php">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>

                <a href="sections.php">
                    <i class="fas fa-layer-group"></i>
                    <span>Sections</span>
                </a>

                <a href="students.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>

                <a href="marks.php">
                    <i class="fas fa-pen-to-square"></i>
                    <span>Marks &amp; Results</span>
                </a>

                <a href="result.php">
                    <i class="fas fa-chart-column"></i>
                    <span>Results</span>
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

            <header class="teacher-delete-topbar">
                <div>
                    <h1>Delete Teacher</h1>
                    <p>Review the teacher account before permanently removing it.</p>
                </div>

                <div class="teacher-delete-profile">
                    <div class="teacher-delete-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>

                    <div>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                        <span>Administrator</span>
                    </div>
                </div>
            </header>

            <section class="teacher-delete-hero">
                <div class="teacher-delete-hero-content">

                    <span class="teacher-delete-badge">
                        <i class="fas fa-triangle-exclamation"></i>
                        Confirmation Required
                    </span>

                    <h2>Remove Teacher Account</h2>

                    <p>
                        Teacher deletion is protected because the current database
                        relationships can cascade into academic records. The system
                        will allow deletion only when this teacher has no courses,
                        subjects, sections, students, or marks associated with the account.
                    </p>

                </div>
            </section>

            <div class="teacher-delete-content">

                <section class="teacher-delete-card">

                    <div class="teacher-delete-header">

                        <div class="teacher-big-avatar">
                            <?= strtoupper(substr($teacher["name"], 0, 1)) ?>
                        </div>

                        <div>
                            <h3><?= htmlspecialchars($teacher["name"]) ?></h3>
                            <p>
                                @<?= htmlspecialchars($teacher["username"]) ?>
                                · <?= htmlspecialchars($teacher["email"]) ?>
                            </p>
                        </div>

                    </div>

                    <div class="teacher-delete-body">

                        <?php if ($error_message !== ""): ?>
                        <div class="blocked-box">
                            <i class="fas fa-shield-halved"></i>
                            <div>
                                <strong>Deletion blocked</strong>
                                <p><?= htmlspecialchars($error_message) ?></p>
                            </div>
                        </div>
                        <?php elseif ($has_dependencies): ?>
                        <div class="blocked-box">
                            <i class="fas fa-database"></i>
                            <div>
                                <strong>This teacher has academic records</strong>
                                <p>
                                    The account cannot be deleted while these
                                    records exist. This protects courses, sections,
                                    students, subjects, marks, and results from
                                    accidental cascading deletion.
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="danger-box">
                            <i class="fas fa-triangle-exclamation"></i>
                            <div>
                                <strong>This action cannot be undone.</strong>
                                <p>
                                    The teacher account will be permanently removed.
                                    Please confirm that you no longer need this account.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="teacher-details">

                            <div class="teacher-detail-item">
                                <label>Teacher Name</label>
                                <strong><?= htmlspecialchars($teacher["name"]) ?></strong>
                            </div>

                            <div class="teacher-detail-item">
                                <label>Username</label>
                                <strong>@<?= htmlspecialchars($teacher["username"]) ?></strong>
                            </div>

                            <div class="teacher-detail-item">
                                <label>Email</label>
                                <strong><?= htmlspecialchars($teacher["email"]) ?></strong>
                            </div>

                            <div class="teacher-detail-item">
                                <label>Department</label>
                                <strong>
                                    <?= htmlspecialchars($teacher["department_name"] ?? "Not assigned") ?>
                                </strong>
                                <?php if (!empty($teacher["department_code"])): ?>
                                <span><?= htmlspecialchars($teacher["department_code"]) ?></span>
                                <?php endif; ?>
                            </div>

                        </div>

                        <div class="dependency-title">
                            Academic records connected to this teacher
                        </div>

                        <div class="dependency-grid">

                            <div class="dependency-item">
                                <strong><?= $course_count ?></strong>
                                <span>Courses</span>
                            </div>

                            <div class="dependency-item">
                                <strong><?= $subject_count ?></strong>
                                <span>Subjects</span>
                            </div>

                            <div class="dependency-item">
                                <strong><?= $section_count ?></strong>
                                <span>Sections</span>
                            </div>

                            <div class="dependency-item">
                                <strong><?= $student_count ?></strong>
                                <span>Students</span>
                            </div>

                            <div class="dependency-item">
                                <strong><?= $mark_count ?></strong>
                                <span>Marks</span>
                            </div>

                        </div>

                        <form method="POST" action="delete_teacher.php?id=<?= $teacher_id ?>">

                            <input type="hidden" name="teacher_id" value="<?= $teacher_id ?>">

                            <div class="teacher-delete-actions">

                                <a href="teachers.php" class="teacher-delete-btn teacher-cancel-btn">
                                    <i class="fas fa-arrow-left"></i>
                                    Cancel
                                </a>

                                <button type="submit" class="teacher-delete-btn teacher-confirm-btn"
                                    <?= $has_dependencies || $error_message !== "" ? "disabled" : "" ?>
                                    onclick="return confirm('This will permanently delete the teacher account. Continue?');">
                                    <i class="fas fa-trash"></i>
                                    Yes, Delete Teacher
                                </button>

                            </div>

                        </form>

                        <div class="teacher-delete-note">
                            For safety, deletion is available only when no academic
                            records are linked to this teacher.
                        </div>

                    </div>

                </section>

            </div>

        </main>

    </div>

</body>

</html>