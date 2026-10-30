<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$errors = [];
$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| ADD SUBJECT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_subject"])) {

    $course_id = (int) ($_POST["course_id"] ?? 0);
    $subject_name = trim($_POST["subject_name"] ?? "");
    $subject_code = trim($_POST["subject_code"] ?? "");
    $semester = (int) ($_POST["semester"] ?? 0);
    $max_marks = (int) ($_POST["max_marks"] ?? 100);

    if ($course_id <= 0) {
        $errors[] = "Please select a course.";
    }

    if ($subject_name === "") {
        $errors[] = "Subject name is required.";
    }

    if ($subject_code === "") {
        $errors[] = "Subject code is required.";
    }

    if ($semester <= 0) {
        $errors[] = "Please enter a valid semester.";
    }

    if ($max_marks <= 0) {
        $errors[] = "Maximum marks must be greater than 0.";
    }

    /*
     * Teacher can manage subjects only for courses created by them.
     * Admin can manage all courses.
     */
    if (empty($errors)) {
        if ($role === "admin") {
            $course_verify_sql = "SELECT id FROM courses WHERE id = ?";
            $course_verify_stmt = mysqli_prepare($conn, $course_verify_sql);
            mysqli_stmt_bind_param($course_verify_stmt, "i", $course_id);
        } else {
            $course_verify_sql = "
                SELECT id
                FROM courses
                WHERE id = ?
                  AND created_by = ?
            ";
            $course_verify_stmt = mysqli_prepare($conn, $course_verify_sql);
            mysqli_stmt_bind_param($course_verify_stmt, "ii", $course_id, $user_id);
        }

        mysqli_stmt_execute($course_verify_stmt);
        $course_verify_result = mysqli_stmt_get_result($course_verify_stmt);

        if (mysqli_num_rows($course_verify_result) === 0) {
            $errors[] = "The selected course is not available to you.";
        }
    }

    /*
     * Subject code must be unique inside the selected course.
     */
    if (empty($errors)) {
        $duplicate_sql = "
            SELECT id
            FROM subjects
            WHERE course_id = ?
              AND code = ?
            LIMIT 1
        ";

        $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);
        mysqli_stmt_bind_param(
            $duplicate_stmt,
            "is",
            $course_id,
            $subject_code
        );
        mysqli_stmt_execute($duplicate_stmt);
        $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);

        if (mysqli_num_rows($duplicate_result) > 0) {
            $errors[] = "This subject code already exists for the selected course.";
        }
    }

    if (empty($errors)) {
        $insert_sql = "
            INSERT INTO subjects
            (course_id, name, code, semester, max_marks)
            VALUES (?, ?, ?, ?, ?)
        ";

        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        mysqli_stmt_bind_param(
            $insert_stmt,
            "issii",
            $course_id,
            $subject_name,
            $subject_code,
            $semester,
            $max_marks
        );

        if (mysqli_stmt_execute($insert_stmt)) {
            header("Location: subjects.php?added=1");
            exit();
        }

        $errors[] = "Unable to add the subject. Please try again.";
    }
}

/*
|--------------------------------------------------------------------------
| COURSES FOR ADD SUBJECT FORM
|--------------------------------------------------------------------------
*/
if ($role === "admin") {
    $course_sql = "
        SELECT
            c.id,
            c.name,
            c.code,
            d.name AS department_name,
            d.code AS department_code
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        ORDER BY d.name ASC, c.name ASC
    ";
    $course_stmt = mysqli_prepare($conn, $course_sql);
} else {
    $course_sql = "
        SELECT
            c.id,
            c.name,
            c.code,
            d.name AS department_name,
            d.code AS department_code
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        WHERE c.created_by = ?
        ORDER BY c.name ASC
    ";
    $course_stmt = mysqli_prepare($conn, $course_sql);
    mysqli_stmt_bind_param($course_stmt, "i", $user_id);
}

mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);

/*
|--------------------------------------------------------------------------
| SUBJECT LIST
|--------------------------------------------------------------------------
*/
if ($role === "admin") {

    $subject_sql = "
        SELECT
            s.id,
            s.name AS subject_name,
            s.code AS subject_code,
            s.semester,
            s.max_marks,
            s.created_at,
            c.id AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code,
            COUNT(m.id) AS marks_count
        FROM subjects s
        INNER JOIN courses c ON s.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        LEFT JOIN marks m ON m.subject_id = s.id
    ";

    if ($search !== "") {
        $subject_sql .= "
            WHERE
                s.name LIKE ?
                OR s.code LIKE ?
                OR c.name LIKE ?
                OR c.code LIKE ?
                OR d.name LIKE ?
                OR d.code LIKE ?
        ";
    }

    $subject_sql .= "
        GROUP BY
            s.id,
            s.name,
            s.code,
            s.semester,
            s.max_marks,
            s.created_at,
            c.id,
            c.name,
            c.code,
            d.name,
            d.code
        ORDER BY d.name ASC, c.name ASC, s.semester ASC, s.name ASC
    ";

    $subject_stmt = mysqli_prepare($conn, $subject_sql);

    if ($search !== "") {
        $value = "%" . $search . "%";
        mysqli_stmt_bind_param(
            $subject_stmt,
            "ssssss",
            $value,
            $value,
            $value,
            $value,
            $value,
            $value
        );
    }

} else {

    $subject_sql = "
        SELECT
            s.id,
            s.name AS subject_name,
            s.code AS subject_code,
            s.semester,
            s.max_marks,
            s.created_at,
            c.id AS course_id,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code,
            COUNT(m.id) AS marks_count
        FROM subjects s
        INNER JOIN courses c ON s.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        LEFT JOIN marks m ON m.subject_id = s.id
        WHERE c.created_by = ?
    ";

    if ($search !== "") {
        $subject_sql .= "
            AND (
                s.name LIKE ?
                OR s.code LIKE ?
                OR c.name LIKE ?
                OR c.code LIKE ?
            )
        ";
    }

    $subject_sql .= "
        GROUP BY
            s.id,
            s.name,
            s.code,
            s.semester,
            s.max_marks,
            s.created_at,
            c.id,
            c.name,
            c.code,
            d.name,
            d.code
        ORDER BY c.name ASC, s.semester ASC, s.name ASC
    ";

    $subject_stmt = mysqli_prepare($conn, $subject_sql);

    if ($search !== "") {
        $value = "%" . $search . "%";
        mysqli_stmt_bind_param(
            $subject_stmt,
            "issss",
            $user_id,
            $value,
            $value,
            $value,
            $value
        );
    } else {
        mysqli_stmt_bind_param($subject_stmt, "i", $user_id);
    }
}

mysqli_stmt_execute($subject_stmt);
$subject_result = mysqli_stmt_get_result($subject_stmt);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/
if ($role === "admin") {
    $count_sql = "SELECT COUNT(*) AS total FROM subjects";
    $count_stmt = mysqli_prepare($conn, $count_sql);
} else {
    $count_sql = "
        SELECT COUNT(*) AS total
        FROM subjects s
        INNER JOIN courses c ON s.course_id = c.id
        WHERE c.created_by = ?
    ";
    $count_stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($count_stmt, "i", $user_id);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_data = mysqli_fetch_assoc($count_result);
$total_subjects = (int) $count_data["total"];

if ($role === "admin") {
    $course_count_sql = "SELECT COUNT(*) AS total FROM courses";
    $course_count_stmt = mysqli_prepare($conn, $course_count_sql);
} else {
    $course_count_sql = "
        SELECT COUNT(*) AS total
        FROM courses
        WHERE created_by = ?
    ";
    $course_count_stmt = mysqli_prepare($conn, $course_count_sql);
    mysqli_stmt_bind_param($course_count_stmt, "i", $user_id);
}

mysqli_stmt_execute($course_count_stmt);
$course_count_result = mysqli_stmt_get_result($course_count_stmt);
$course_count_data = mysqli_fetch_assoc($course_count_result);
$total_courses = (int) $course_count_data["total"];

if ($role === "admin") {
    $marks_count_sql = "SELECT COUNT(*) AS total FROM marks";
    $marks_count_stmt = mysqli_prepare($conn, $marks_count_sql);
} else {
    $marks_count_sql = "
        SELECT COUNT(m.id) AS total
        FROM marks m
        INNER JOIN subjects s ON m.subject_id = s.id
        INNER JOIN courses c ON s.course_id = c.id
        WHERE c.created_by = ?
    ";
    $marks_count_stmt = mysqli_prepare($conn, $marks_count_sql);
    mysqli_stmt_bind_param($marks_count_stmt, "i", $user_id);
}

mysqli_stmt_execute($marks_count_stmt);
$marks_count_result = mysqli_stmt_get_result($marks_count_stmt);
$marks_count_data = mysqli_fetch_assoc($marks_count_result);
$total_marks = (int) $marks_count_data["total"];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Subjects | Student Management System</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* =====================================================
           SUBJECTS PAGE
        ===================================================== */

    .subjects-page {
        min-height: 100vh;
        background: #f5f7fb;
    }

    .subjects-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh;
    }

    .subjects-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .subjects-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        padding: 30px;
        box-sizing: border-box;
    }

    .subjects-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .subjects-topbar h1 {
        margin: 0;
        color: #182033;
        font-size: 28px;
    }

    .subjects-topbar p {
        margin: 7px 0 0;
        color: #7d8798;
        font-size: 14px;
    }

    .subjects-profile {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 14px 9px 9px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 17px;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .subjects-avatar {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #4f46e5;
        color: #fff;
        font-weight: 800;
    }

    .subjects-profile strong {
        display: block;
        color: #182033;
        font-size: 13px;
    }

    .subjects-profile span {
        display: block;
        margin-top: 2px;
        color: #7d8798;
        font-size: 12px;
    }

    .subjects-hero {
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 24px;
        background: linear-gradient(135deg, #4338ca 0%, #6366f1 48%, #7c3aed 100%);
        color: #fff;
        box-shadow: 0 18px 45px rgba(67, 56, 202, .20);
    }

    .subjects-hero::before,
    .subjects-hero::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .subjects-hero::before {
        width: 210px;
        height: 210px;
        right: -60px;
        top: -80px;
    }

    .subjects-hero::after {
        width: 150px;
        height: 150px;
        right: 130px;
        bottom: -100px;
    }

    .subjects-hero-content {
        position: relative;
        z-index: 1;
        max-width: 760px;
    }

    .subjects-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 13px;
        border-radius: 999px;
        background: rgba(255, 255, 255, .15);
        border: 1px solid rgba(255, 255, 255, .18);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .4px;
        margin-bottom: 14px;
    }

    .subjects-hero h2 {
        margin: 0 0 8px;
        font-size: clamp(25px, 3vw, 36px);
    }

    .subjects-hero p {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, .84);
        line-height: 1.7;
        font-size: 14px;
    }

    .subjects-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .subject-stat {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        border-radius: 18px;
        background: #fff;
        border: 1px solid #e8ebf2;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .05);
    }

    .subject-stat-icon {
        width: 52px;
        height: 52px;
        flex: 0 0 52px;
        display: grid;
        place-items: center;
        border-radius: 15px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 20px;
    }

    .subject-stat strong {
        display: block;
        color: #172033;
        font-size: 25px;
    }

    .subject-stat span {
        display: block;
        margin-top: 5px;
        color: #7a8497;
        font-size: 13px;
    }

    .subjects-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 22px;
        align-items: start;
    }

    .subjects-list-card,
    .add-subject-card {
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
    }

    .subjects-list-card {
        overflow: hidden;
    }

    .subject-list-header {
        padding: 21px 24px;
        border-bottom: 1px solid #edf0f5;
    }

    .subject-list-header h3,
    .add-subject-card h3 {
        margin: 0;
        color: #182033;
        font-size: 18px;
    }

    .subject-list-header p,
    .add-subject-card>p {
        margin: 5px 0 0;
        color: #8992a3;
        font-size: 12px;
        line-height: 1.6;
    }

    .subject-search {
        position: relative;
        margin-top: 17px;
    }

    .subject-search i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #8a94a7;
    }

    .subject-search input {
        width: 100%;
        height: 47px;
        padding: 0 15px 0 43px;
        box-sizing: border-box;
        border: 1px solid #dfe4ed;
        border-radius: 13px;
        outline: none;
        font-size: 13px;
    }

    .subject-search input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .subjects-table-wrap {
        width: 100%;
        overflow-x: auto;
    }

    .subjects-table {
        width: 100%;
        min-width: 930px;
        border-collapse: collapse;
    }

    .subjects-table th {
        padding: 13px 17px;
        background: #fafbfc;
        color: #7d8798;
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .6px;
        white-space: nowrap;
    }

    .subjects-table td {
        padding: 16px 17px;
        border-top: 1px solid #eef1f5;
        vertical-align: middle;
    }

    .subject-title {
        display: flex;
        align-items: center;
        gap: 11px;
        min-width: 180px;
    }

    .subject-icon {
        width: 40px;
        height: 40px;
        flex: 0 0 40px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: #eef2ff;
        color: #4f46e5;
    }

    .subject-title strong {
        display: block;
        color: #273044;
        font-size: 13px;
    }

    .subject-title small {
        display: block;
        margin-top: 3px;
        color: #8b95a7;
        font-size: 10px;
    }

    .course-cell strong {
        display: block;
        color: #273044;
        font-size: 12px;
    }

    .course-cell small {
        display: block;
        margin-top: 3px;
        color: #8b95a7;
        font-size: 10px;
    }

    .semester-badge,
    .marks-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 9px;
        border-radius: 9px;
        font-size: 11px;
        font-weight: 700;
    }

    .semester-badge {
        background: #f3f4f6;
        color: #596579;
    }

    .marks-badge {
        background: #ecfdf5;
        color: #047857;
    }

    .max-marks {
        color: #596579;
        font-size: 12px;
        white-space: nowrap;
    }

    .add-subject-card {
        padding: 23px;
    }

    .add-subject-icon {
        width: 52px;
        height: 52px;
        display: grid;
        place-items: center;
        margin-bottom: 16px;
        border-radius: 15px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 20px;
    }

    .add-subject-card h3 {
        font-size: 18px;
    }

    .subject-form {
        margin-top: 20px;
    }

    .subject-form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 15px;
    }

    .subject-form-group label {
        margin-bottom: 7px;
        color: #374151;
        font-size: 12px;
        font-weight: 700;
    }

    .subject-input,
    .subject-select {
        width: 100%;
        height: 47px;
        box-sizing: border-box;
        padding: 0 13px;
        border: 1px solid #dfe4ed;
        border-radius: 12px;
        outline: none;
        background: #fff;
        color: #20283a;
        font-size: 13px;
    }

    .subject-input:focus,
    .subject-select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .subject-help {
        margin-top: 5px;
        color: #98a1b1;
        font-size: 10px;
        line-height: 1.5;
    }

    .subject-alert {
        display: flex;
        gap: 9px;
        padding: 12px;
        margin-bottom: 16px;
        border-radius: 12px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 11px;
        line-height: 1.5;
    }

    .subject-submit {
        width: 100%;
        min-height: 47px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        border-radius: 12px;
        background: #4f46e5;
        color: #fff;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        box-shadow: 0 8px 20px rgba(79, 70, 229, .18);
        transition: .2s ease;
    }

    .subject-submit:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .no-subjects {
        padding: 55px 20px;
        text-align: center;
    }

    .no-subjects i {
        width: 60px;
        height: 60px;
        display: grid;
        place-items: center;
        margin: 0 auto 13px;
        border-radius: 18px;
        background: #eef2ff;
        color: #6366f1;
        font-size: 23px;
    }

    .no-subjects h4 {
        margin: 0 0 6px;
        color: #273044;
        font-size: 15px;
    }

    .no-subjects p {
        margin: 0;
        color: #8b95a7;
        font-size: 12px;
    }

    .no-courses {
        padding: 14px;
        border-radius: 12px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #c2410c;
        font-size: 11px;
        line-height: 1.6;
    }

    .no-courses a {
        color: #9a3412;
        font-weight: 700;
    }


    /* SUBJECT ACTION BUTTONS */
    .subject-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        white-space: nowrap;
    }

    .subject-action {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none;
        font-size: 14px;
        line-height: 1;
        transition: all .2s ease;
    }

    .subject-action i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        line-height: 1;
        margin: 0;
        padding: 0;
    }

    .subject-action.edit {
        background: #eff6ff;
        color: #2563eb;
        border: 1px solid #bfdbfe;
    }

    .subject-action.edit:hover {
        background: #2563eb;
        color: #fff;
        border-color: #2563eb;
        transform: translateY(-1px);
    }

    .subject-action.delete {
        background: #fff5f5;
        color: #ef4444;
        border: 1px solid #fecaca;
    }

    .subject-action.delete:hover {
        background: #ef4444;
        color: #fff;
        border-color: #ef4444;
        transform: translateY(-1px);
    }

    .subject-message {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 12px 15px;
        margin-bottom: 18px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .subject-message.success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
    }

    .subject-message.error {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
    }

    @media (max-width: 1100px) {
        .subjects-grid {
            grid-template-columns: 1fr;
        }

        .add-subject-card {
            order: -1;
        }
    }

    @media (max-width: 900px) {
        .subjects-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px;
        }

        .subjects-page .sidebar {
            width: 260px;
        }

        .subjects-stats {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 650px) {
        .subjects-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .subjects-profile {
            align-self: stretch;
        }

        .subjects-hero {
            padding: 24px 20px;
            border-radius: 20px;
        }

        .subject-list-header,
        .add-subject-card {
            padding: 19px;
        }
    }
    </style>
</head>

<body class="dashboard-page subjects-page">

    <div class="dashboard-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>

                <div>
                    <h2>Student MS</h2>
                    <span>
                        <?= $role === "admin" ? "Management System" : "Teacher Portal" ?>
                    </span>
                </div>
            </div>

            <nav class="sidebar-menu">

                <?php if ($role === "admin"): ?>
                <a href="admin_dashboard.php">
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <?php else: ?>
                <a href="teacher_dashboard.php">
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <?php endif; ?>

                <a href="courses.php">
                    <i class="fas fa-book"></i>
                    <span><?= $role === "teacher" ? "My Courses" : "Courses" ?></span>
                </a>

                <a href="sections.php">
                    <i class="fas fa-layer-group"></i>
                    <span><?= $role === "teacher" ? "My Sections" : "Sections" ?></span>
                </a>

                <a href="students.php">
                    <i class="fas fa-user-graduate"></i>
                    <span><?= $role === "teacher" ? "My Students" : "Students" ?></span>
                </a>

                <a href="subjects.php" class="active">
                    <i class="fas fa-book-open"></i>
                    <span>Subjects</span>
                </a>

                <a href="marks.php">
                    <i class="fas fa-pen-to-square"></i>
                    <span><?= $role === "teacher" ? "Marks" : "Marks & Results" ?></span>
                </a>

                <a href="result.php">
                    <i class="fas fa-chart-column"></i>
                    <span>Results</span>
                </a>

                <?php if ($role === "admin"): ?>
                <a href="departments.php">
                    <i class="fas fa-building-columns"></i>
                    <span>Departments</span>
                </a>

                <a href="teachers.php">
                    <i class="fas fa-chalkboard-user"></i>
                    <span>Teachers</span>
                </a>
                <?php endif; ?>

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

            <header class="subjects-topbar">

                <div>
                    <h1>Subjects</h1>
                    <p>
                        <?= $role === "teacher"
                        ? "Create and manage subjects for your courses."
                        : "Manage subjects across all courses and departments." ?>
                    </p>
                </div>

                <div class="subjects-profile">
                    <div class="subjects-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>

                    <div>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                        <span><?= ucfirst(htmlspecialchars($role)) ?></span>
                    </div>
                </div>

            </header>

            <section class="subjects-hero">

                <div class="subjects-hero-content">

                    <span class="subjects-badge">
                        <i class="fas fa-book-open"></i>
                        <?= $role === "teacher" ? "Teacher Portal" : "Academic Management" ?>
                    </span>

                    <h2>Course Subjects</h2>

                    <p>
                        Organize subjects by course and semester. Each subject
                        becomes available for recording marks and generating
                        student results.
                    </p>

                </div>

            </section>

            <section class="subjects-stats">

                <div class="subject-stat">
                    <div class="subject-stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div>
                        <strong><?= $total_subjects ?></strong>
                        <span><?= $role === "teacher" ? "My Subjects" : "Total Subjects" ?></span>
                    </div>
                </div>

                <div class="subject-stat">
                    <div class="subject-stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <strong><?= $total_courses ?></strong>
                        <span><?= $role === "teacher" ? "My Courses" : "Total Courses" ?></span>
                    </div>
                </div>

                <div class="subject-stat">
                    <div class="subject-stat-icon">
                        <i class="fas fa-pen-to-square"></i>
                    </div>
                    <div>
                        <strong><?= $total_marks ?></strong>
                        <span><?= $role === "teacher" ? "Marks Entered" : "Total Marks Records" ?></span>
                    </div>
                </div>

            </section>

            <div class="subjects-grid">

                <!-- SUBJECT LIST -->
                <section class="subjects-list-card">

                    <div class="subject-list-header">

                        <?php if (isset($_GET["added"])): ?>
                        <div class="subject-message success">
                            <i class="fas fa-circle-check"></i>
                            Subject added successfully.
                        </div>
                        <?php elseif (isset($_GET["updated"])): ?>
                        <div class="subject-message success">
                            <i class="fas fa-circle-check"></i>
                            Subject updated successfully.
                        </div>
                        <?php elseif (isset($_GET["deleted"])): ?>
                        <div class="subject-message success">
                            <i class="fas fa-circle-check"></i>
                            Subject deleted successfully.
                        </div>
                        <?php elseif (isset($_GET["delete_error"])): ?>
                        <div class="subject-message error">
                            <i class="fas fa-circle-exclamation"></i>
                            The subject could not be deleted.
                        </div>
                        <?php endif; ?>

                        <h3>Subject Directory</h3>

                        <p>
                            Subjects are organized by course and semester.
                        </p>

                        <form method="GET" action="subjects.php" class="subject-search">
                            <i class="fas fa-search"></i>

                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                placeholder="Search subject, code, course or department...">
                        </form>

                    </div>

                    <?php if (mysqli_num_rows($subject_result) > 0): ?>

                    <div class="subjects-table-wrap">

                        <table class="subjects-table">

                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Course</th>
                                    <th>Semester</th>
                                    <th>Max Marks</th>
                                    <th>Marks Records</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php while ($subject = mysqli_fetch_assoc($subject_result)): ?>

                                <tr>

                                    <td>
                                        <div class="subject-title">

                                            <div class="subject-icon">
                                                <i class="fas fa-book"></i>
                                            </div>

                                            <div>
                                                <strong>
                                                    <?= htmlspecialchars($subject["subject_name"]) ?>
                                                </strong>

                                                <small>
                                                    <?= htmlspecialchars($subject["subject_code"]) ?>
                                                </small>
                                            </div>

                                        </div>
                                    </td>

                                    <td>
                                        <div class="course-cell">
                                            <strong>
                                                <?= htmlspecialchars($subject["course_name"]) ?>
                                            </strong>

                                            <small>
                                                <?= htmlspecialchars($subject["course_code"]) ?>
                                                <?php if ($role === "admin"): ?>
                                                · <?= htmlspecialchars($subject["department_code"]) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="semester-badge">
                                            <i class="fas fa-layer-group"></i>
                                            Semester <?= (int) $subject["semester"] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="max-marks">
                                            <?= htmlspecialchars($subject["max_marks"]) ?> marks
                                        </span>
                                    </td>

                                    <td>
                                        <span class="marks-badge">
                                            <i class="fas fa-chart-column"></i>
                                            <?= (int) $subject["marks_count"] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="subject-actions">
                                            <a href="edit_subject.php?id=<?= (int) $subject["id"] ?>"
                                                class="subject-action edit" title="Edit Subject">
                                                <i class="fas fa-pen"></i>
                                            </a>

                                            <a href="delete_subject.php?id=<?= (int) $subject["id"] ?>"
                                                class="subject-action delete" title="Delete Subject">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>

                                </tr>

                                <?php endwhile; ?>

                            </tbody>

                        </table>

                    </div>

                    <?php else: ?>

                    <div class="no-subjects">

                        <i class="fas fa-book-open"></i>

                        <h4>
                            <?= $search !== "" ? "No subjects found" : "No subjects yet" ?>
                        </h4>

                        <p>
                            <?= $search !== ""
                                ? "Try another subject, code or course."
                                : "Create a subject using the form to get started." ?>
                        </p>

                    </div>

                    <?php endif; ?>

                </section>

                <!-- ADD SUBJECT -->
                <aside class="add-subject-card">

                    <div class="add-subject-icon">
                        <i class="fas fa-plus"></i>
                    </div>

                    <h3>Add Subject</h3>

                    <p>
                        Add a subject to one of your courses.
                    </p>

                    <?php if (!empty($errors)): ?>

                    <div class="subject-alert">
                        <i class="fas fa-circle-exclamation"></i>

                        <div>
                            <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php endif; ?>

                    <?php if (mysqli_num_rows($course_result) > 0): ?>

                    <form method="POST" action="subjects.php" class="subject-form">

                        <div class="subject-form-group">

                            <label for="course_id">
                                Course
                            </label>

                            <select name="course_id" id="course_id" class="subject-select" required>
                                <option value="">Select course</option>

                                <?php while ($course = mysqli_fetch_assoc($course_result)): ?>

                                <option value="<?= (int) $course["id"] ?>">
                                    <?= htmlspecialchars(
                                            $course["name"]
                                            . " (" . $course["code"] . ")"
                                            . " — " . $course["department_code"]
                                        ) ?>
                                </option>

                                <?php endwhile; ?>

                            </select>

                            <div class="subject-help">
                                <?= $role === "teacher"
                                    ? "Only courses created by you are shown."
                                    : "Choose the course this subject belongs to." ?>
                            </div>

                        </div>

                        <div class="subject-form-group">

                            <label for="subject_name">
                                Subject Name
                            </label>

                            <input type="text" name="subject_name" id="subject_name" class="subject-input"
                                placeholder="e.g. Data Structures" maxlength="100" required>

                        </div>

                        <div class="subject-form-group">

                            <label for="subject_code">
                                Subject Code
                            </label>

                            <input type="text" name="subject_code" id="subject_code" class="subject-input"
                                placeholder="e.g. CS201" maxlength="20" required>

                            <div class="subject-help">
                                Code must be unique within the selected course.
                            </div>

                        </div>

                        <div class="subject-form-group">

                            <label for="semester">
                                Semester
                            </label>

                            <select name="semester" id="semester" class="subject-select" required>
                                <option value="">Select semester</option>

                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>">
                                    Semester <?= $i ?>
                                </option>
                                <?php endfor; ?>

                            </select>

                        </div>

                        <div class="subject-form-group">

                            <label for="max_marks">
                                Maximum Marks
                            </label>

                            <input type="number" name="max_marks" id="max_marks" class="subject-input" value="100"
                                min="1" max="99999" required>

                        </div>

                        <button type="submit" name="add_subject" class="subject-submit">
                            <i class="fas fa-plus"></i>
                            Add Subject
                        </button>

                    </form>

                    <?php else: ?>

                    <div class="no-courses">
                        <i class="fas fa-circle-info"></i>

                        No courses are available.
                        <?php if ($role === "teacher"): ?>
                        Create a course first from
                        <a href="courses.php">My Courses</a>.
                        <?php else: ?>
                        Create a course first from
                        <a href="courses.php">Courses</a>.
                        <?php endif; ?>
                    </div>

                    <?php endif; ?>

                </aside>

            </div>

        </main>

    </div>

</body>

</html>