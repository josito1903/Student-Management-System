<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$student_id = (int) ($_GET["id"] ?? $_POST["student_id"] ?? 0);

if ($student_id <= 0) {
    header("Location: students.php");
    exit();
}

$errors = [];

/*
|--------------------------------------------------------------------------
| LOAD STUDENT
|--------------------------------------------------------------------------
| Admin -> any student
| Teacher -> only students in their own sections
*/
if ($role === "admin") {
    $student_sql = "
        SELECT
            s.id,
            s.section_id,
            s.roll_no,
            s.name,
            s.email,
            s.phone,
            s.gender,
            s.date_of_birth,
            s.address,
            sec.name AS section_name,
            sec.semester,
            sec.academic_year,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code
        FROM students s
        INNER JOIN sections sec ON s.section_id = sec.id
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        WHERE s.id = ?
        LIMIT 1
    ";
    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt, "i", $student_id);
} else {
    $student_sql = "
        SELECT
            s.id,
            s.section_id,
            s.roll_no,
            s.name,
            s.email,
            s.phone,
            s.gender,
            s.date_of_birth,
            s.address,
            sec.name AS section_name,
            sec.semester,
            sec.academic_year,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code
        FROM students s
        INNER JOIN sections sec ON s.section_id = sec.id
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        WHERE s.id = ?
          AND sec.teacher_id = ?
        LIMIT 1
    ";
    $student_stmt = mysqli_prepare($conn, $student_sql);
    mysqli_stmt_bind_param($student_stmt, "ii", $student_id, $user_id);
}

mysqli_stmt_execute($student_stmt);
$student_result = mysqli_stmt_get_result($student_stmt);

if (mysqli_num_rows($student_result) === 0) {
    header("Location: students.php");
    exit();
}

$student = mysqli_fetch_assoc($student_result);

/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/
$section_id = (int) $student["section_id"];
$roll_no = $student["roll_no"];
$name = $student["name"];
$email = $student["email"];
$phone = $student["phone"];
$gender = $student["gender"] ?? "";
$date_of_birth = $student["date_of_birth"] ?? "";
$address = $student["address"] ?? "";

/*
|--------------------------------------------------------------------------
| LOAD AVAILABLE SECTIONS
|--------------------------------------------------------------------------
*/
if ($role === "admin") {
    $section_sql = "
        SELECT
            sec.id,
            sec.name AS section_name,
            sec.semester,
            sec.academic_year,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code
        FROM sections sec
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        ORDER BY d.name ASC, c.name ASC, sec.semester ASC, sec.name ASC
    ";
    $section_stmt = mysqli_prepare($conn, $section_sql);
} else {
    $section_sql = "
        SELECT
            sec.id,
            sec.name AS section_name,
            sec.semester,
            sec.academic_year,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code
        FROM sections sec
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        WHERE sec.teacher_id = ?
        ORDER BY c.name ASC, sec.semester ASC, sec.name ASC
    ";
    $section_stmt = mysqli_prepare($conn, $section_sql);
    mysqli_stmt_bind_param($section_stmt, "i", $user_id);
}

mysqli_stmt_execute($section_stmt);
$section_result = mysqli_stmt_get_result($section_stmt);

/*
|--------------------------------------------------------------------------
| UPDATE STUDENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $section_id = (int) ($_POST["section_id"] ?? 0);
    $roll_no = trim($_POST["roll_no"] ?? "");
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $date_of_birth = $_POST["date_of_birth"] ?? "";
    $address = trim($_POST["address"] ?? "");

    if ($section_id <= 0) {
        $errors[] = "Please select a section.";
    }

    if ($roll_no === "") {
        $errors[] = "Roll number is required.";
    }

    if ($name === "") {
        $errors[] = "Student name is required.";
    }

    if ($email === "") {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($phone === "") {
        $errors[] = "Phone number is required.";
    }

    if ($gender !== "" && !in_array($gender, ["Male", "Female", "Other"], true)) {
        $errors[] = "Invalid gender selected.";
    }

    /*
     * Verify section ownership.
     */
    if (empty($errors)) {
        if ($role === "admin") {
            $verify_sql = "SELECT id FROM sections WHERE id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, "i", $section_id);
        } else {
            $verify_sql = "SELECT id FROM sections WHERE id = ? AND teacher_id = ?";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, "ii", $section_id, $user_id);
        }

        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);

        if (mysqli_num_rows($verify_result) === 0) {
            $errors[] = "The selected section is not available to you.";
        }
    }

    /*
     * Roll number must remain unique, except for this student.
     */
    if (empty($errors)) {
        $duplicate_sql = "
            SELECT id
            FROM students
            WHERE roll_no = ?
              AND id <> ?
            LIMIT 1
        ";

        $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);
        mysqli_stmt_bind_param($duplicate_stmt, "si", $roll_no, $student_id);
        mysqli_stmt_execute($duplicate_stmt);
        $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);

        if (mysqli_num_rows($duplicate_result) > 0) {
            $errors[] = "This roll number is already registered to another student.";
        }
    }

    /*
     * Update.
     */
    if (empty($errors)) {
        $update_sql = "
            UPDATE students
            SET
                section_id = ?,
                roll_no = ?,
                name = ?,
                email = ?,
                phone = ?,
                gender = NULLIF(?, ''),
                date_of_birth = NULLIF(?, ''),
                address = ?
            WHERE id = ?
        ";

        $update_stmt = mysqli_prepare($conn, $update_sql);

        mysqli_stmt_bind_param(
            $update_stmt,
            "isssssssi",
            $section_id,
            $roll_no,
            $name,
            $email,
            $phone,
            $gender,
            $date_of_birth,
            $address,
            $student_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            header("Location: students.php?updated=1");
            exit();
        }

        $errors[] = "Unable to update the student. Please try again.";
    }

    /*
     * Reload the section list after a validation error.
     */
    mysqli_free_result($section_result);

    if ($role === "admin") {
        $section_stmt = mysqli_prepare($conn, $section_sql);
    } else {
        $section_stmt = mysqli_prepare($conn, $section_sql);
        mysqli_stmt_bind_param($section_stmt, "i", $user_id);
    }

    mysqli_stmt_execute($section_stmt);
    $section_result = mysqli_stmt_get_result($section_stmt);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Student | Student Management System</title>

    <link rel="stylesheet" href="style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    /* =====================================================
           EDIT STUDENT PAGE
        ===================================================== */

    .edit-student-page {
        min-height: 100vh;
        background: #f5f7fb;
    }

    .edit-student-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh;
    }

    .edit-student-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .edit-student-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        padding: 30px;
        box-sizing: border-box;
    }

    .edit-student-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 24px;
    }

    .edit-student-topbar h1 {
        margin: 0;
        color: #182033;
        font-size: 28px;
        line-height: 1.2;
    }

    .edit-student-topbar p {
        margin: 7px 0 0;
        color: #7d8798;
        font-size: 14px;
    }

    .edit-student-profile {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 14px 9px 9px;
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 17px;
        box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
    }

    .edit-student-avatar {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 50%;
        background: #4f46e5;
        color: #fff;
        font-weight: 800;
    }

    .edit-student-profile strong {
        display: block;
        color: #182033;
        font-size: 13px;
    }

    .edit-student-profile span {
        display: block;
        margin-top: 2px;
        color: #7d8798;
        font-size: 12px;
    }

    .edit-student-hero {
        position: relative;
        overflow: hidden;
        margin-bottom: 24px;
        padding: 30px 34px;
        border-radius: 24px;
        background: linear-gradient(135deg, #4338ca 0%, #6366f1 48%, #7c3aed 100%);
        color: #fff;
        box-shadow: 0 18px 45px rgba(67, 56, 202, .20);
    }

    .edit-student-hero::before,
    .edit-student-hero::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .edit-student-hero::before {
        width: 210px;
        height: 210px;
        right: -60px;
        top: -80px;
    }

    .edit-student-hero::after {
        width: 150px;
        height: 150px;
        right: 130px;
        bottom: -100px;
    }

    .edit-student-hero-content {
        position: relative;
        z-index: 1;
        max-width: 760px;
    }

    .edit-student-badge {
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

    .edit-student-hero h2 {
        margin: 0 0 8px;
        font-size: clamp(25px, 3vw, 36px);
        line-height: 1.15;
    }

    .edit-student-hero p {
        margin: 0;
        max-width: 680px;
        color: rgba(255, 255, 255, .84);
        line-height: 1.7;
        font-size: 14px;
    }

    .edit-student-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 22px;
        align-items: start;
    }

    .student-form-card,
    .student-summary-card {
        background: #fff;
        border: 1px solid #e8ebf2;
        border-radius: 22px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
    }

    .student-form-card {
        padding: 27px;
    }

    .student-card-heading {
        margin-bottom: 24px;
    }

    .student-card-heading h3 {
        margin: 0;
        color: #182033;
        font-size: 19px;
    }

    .student-card-heading p {
        margin: 6px 0 0;
        color: #8992a3;
        font-size: 13px;
        line-height: 1.6;
    }

    .student-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 19px;
    }

    .student-form-group {
        display: flex;
        flex-direction: column;
    }

    .student-form-group.full {
        grid-column: 1 / -1;
    }

    .student-form-group label {
        margin-bottom: 8px;
        color: #374151;
        font-size: 13px;
        font-weight: 700;
    }

    .required {
        color: #dc2626;
    }

    .student-input,
    .student-select,
    .student-textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #dfe4ed;
        border-radius: 13px;
        outline: none;
        background: #fff;
        color: #20283a;
        font-size: 14px;
        transition: .2s ease;
    }

    .student-input,
    .student-select {
        height: 50px;
        padding: 0 15px;
    }

    .student-textarea {
        min-height: 110px;
        padding: 13px 15px;
        resize: vertical;
        font-family: inherit;
    }

    .student-input:focus,
    .student-select:focus,
    .student-textarea:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .10);
    }

    .field-help {
        margin-top: 6px;
        color: #98a1b1;
        font-size: 11px;
        line-height: 1.5;
    }

    .form-alert {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 21px;
        padding: 13px 15px;
        border-radius: 13px;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        font-size: 13px;
        line-height: 1.5;
    }

    .form-alert i {
        margin-top: 2px;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 11px;
        margin-top: 25px;
        padding-top: 22px;
        border-top: 1px solid #edf0f5;
    }

    .form-btn {
        min-height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 0 19px;
        border-radius: 13px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        transition: .2s ease;
    }

    .cancel-btn {
        background: #f3f4f6;
        color: #4b5563;
    }

    .cancel-btn:hover {
        background: #e5e7eb;
        transform: translateY(-1px);
    }

    .save-btn {
        background: #4f46e5;
        color: #fff;
        box-shadow: 0 8px 20px rgba(79, 70, 229, .20);
    }

    .save-btn:hover {
        background: #4338ca;
        transform: translateY(-1px);
    }

    .student-summary-card {
        overflow: hidden;
    }

    .summary-header {
        padding: 23px;
        background: #fafbff;
        border-bottom: 1px solid #edf0f5;
    }

    .summary-avatar {
        width: 66px;
        height: 66px;
        display: grid;
        place-items: center;
        margin-bottom: 14px;
        border-radius: 19px;
        background: linear-gradient(135deg, #e0e7ff, #ede9fe);
        color: #4338ca;
        font-size: 23px;
        font-weight: 800;
    }

    .summary-header h3 {
        margin: 0;
        color: #20283a;
        font-size: 18px;
    }

    .summary-header p {
        margin: 5px 0 0;
        color: #8992a3;
        font-size: 12px;
    }

    .summary-body {
        padding: 20px 23px 23px;
    }

    .summary-item {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        margin-bottom: 16px;
    }

    .summary-item:last-child {
        margin-bottom: 0;
    }

    .summary-item i {
        width: 25px;
        color: #6366f1;
        text-align: center;
        margin-top: 2px;
    }

    .summary-item strong {
        display: block;
        color: #374151;
        font-size: 12px;
    }

    .summary-item span {
        display: block;
        margin-top: 3px;
        color: #8992a3;
        font-size: 11px;
        line-height: 1.5;
    }

    .section-current {
        margin-top: 18px;
        padding: 13px;
        border-radius: 13px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 12px;
        line-height: 1.6;
    }

    @media (max-width: 1050px) {
        .edit-student-layout {
            grid-template-columns: 1fr;
        }

        .student-summary-card {
            order: -1;
        }
    }

    @media (max-width: 900px) {
        .edit-student-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px;
        }

        .edit-student-page .sidebar {
            width: 260px;
        }
    }

    @media (max-width: 650px) {
        .edit-student-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .edit-student-profile {
            align-self: stretch;
        }

        .edit-student-hero {
            padding: 24px 20px;
            border-radius: 20px;
        }

        .student-form-card {
            padding: 20px;
        }

        .student-form-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .student-form-group.full {
            grid-column: auto;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .form-btn {
            width: 100%;
        }
    }
    </style>
</head>

<body class="dashboard-page edit-student-page">

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

                <a href="students.php" class="active">
                    <i class="fas fa-user-graduate"></i>
                    <span><?= $role === "teacher" ? "My Students" : "Students" ?></span>
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

            <header class="edit-student-topbar">

                <div>
                    <h1>Edit Student</h1>
                    <p>Update the student's personal and academic information.</p>
                </div>

                <div class="edit-student-profile">
                    <div class="edit-student-avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>

                    <div>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                        <span><?= ucfirst(htmlspecialchars($role)) ?></span>
                    </div>
                </div>

            </header>

            <section class="edit-student-hero">

                <div class="edit-student-hero-content">

                    <span class="edit-student-badge">
                        <i class="fas fa-user-pen"></i>
                        <?= $role === "teacher" ? "Teacher Portal" : "Academic Management" ?>
                    </span>

                    <h2>Edit <?= htmlspecialchars($student["name"]) ?></h2>

                    <p>
                        Update the student's details or move the student to another
                        section. The selected section controls the student's
                        course, semester and academic year.
                    </p>

                </div>

            </section>

            <div class="edit-student-layout">

                <section class="student-form-card">

                    <div class="student-card-heading">
                        <h3>Student Information</h3>
                        <p>Fields marked with <span class="required">*</span> are required.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="form-alert">
                        <i class="fas fa-circle-exclamation"></i>

                        <div>
                            <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="edit_student.php?id=<?= $student_id ?>">

                        <input type="hidden" name="student_id" value="<?= $student_id ?>">

                        <div class="student-form-grid">

                            <div class="student-form-group full">
                                <label for="section_id">
                                    Section <span class="required">*</span>
                                </label>

                                <select name="section_id" id="section_id" class="student-select" required>
                                    <option value="">Select a section</option>

                                    <?php while ($section = mysqli_fetch_assoc($section_result)): ?>
                                    <option value="<?= (int) $section["id"] ?>"
                                        <?= $section_id === (int) $section["id"] ? "selected" : "" ?>>
                                        <?= htmlspecialchars(
                                            $section["course_name"]
                                            . " (" . $section["course_code"] . ")"
                                            . " — Section " . $section["section_name"]
                                            . " — Semester " . $section["semester"]
                                            . " — " . $section["academic_year"]
                                            . ($role === "admin"
                                                ? " — " . $section["department_code"]
                                                : "")
                                        ) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>

                                <div class="field-help">
                                    <?= $role === "teacher"
                                    ? "Only sections assigned to you are available."
                                    : "Select the student's academic section." ?>
                                </div>
                            </div>

                            <div class="student-form-group">
                                <label for="roll_no">
                                    Roll Number <span class="required">*</span>
                                </label>

                                <input type="text" name="roll_no" id="roll_no" class="student-input"
                                    value="<?= htmlspecialchars($roll_no) ?>" maxlength="20" required>

                                <div class="field-help">Must be unique.</div>
                            </div>

                            <div class="student-form-group">
                                <label for="name">
                                    Full Name <span class="required">*</span>
                                </label>

                                <input type="text" name="name" id="name" class="student-input"
                                    value="<?= htmlspecialchars($name) ?>" maxlength="100" required>
                            </div>

                            <div class="student-form-group">
                                <label for="email">
                                    Email Address <span class="required">*</span>
                                </label>

                                <input type="email" name="email" id="email" class="student-input"
                                    value="<?= htmlspecialchars($email) ?>" maxlength="100" required>
                            </div>

                            <div class="student-form-group">
                                <label for="phone">
                                    Phone Number <span class="required">*</span>
                                </label>

                                <input type="tel" name="phone" id="phone" class="student-input"
                                    value="<?= htmlspecialchars($phone) ?>" maxlength="15" required>
                            </div>

                            <div class="student-form-group">
                                <label for="gender">Gender</label>

                                <select name="gender" id="gender" class="student-select">
                                    <option value="">Select gender</option>
                                    <option value="Male" <?= $gender === "Male" ? "selected" : "" ?>>Male</option>
                                    <option value="Female" <?= $gender === "Female" ? "selected" : "" ?>>Female</option>
                                    <option value="Other" <?= $gender === "Other" ? "selected" : "" ?>>Other</option>
                                </select>
                            </div>

                            <div class="student-form-group">
                                <label for="date_of_birth">Date of Birth</label>

                                <input type="date" name="date_of_birth" id="date_of_birth" class="student-input"
                                    value="<?= htmlspecialchars($date_of_birth) ?>">
                            </div>

                            <div class="student-form-group full">
                                <label for="address">Address</label>

                                <textarea name="address" id="address" class="student-textarea"
                                    placeholder="Enter student's address"><?= htmlspecialchars($address) ?></textarea>
                            </div>

                        </div>

                        <div class="form-actions">

                            <a href="students.php" class="form-btn cancel-btn">
                                <i class="fas fa-arrow-left"></i>
                                Cancel
                            </a>

                            <button type="submit" class="form-btn save-btn">
                                <i class="fas fa-floppy-disk"></i>
                                Save Changes
                            </button>

                        </div>

                    </form>

                </section>

                <aside class="student-summary-card">

                    <div class="summary-header">

                        <div class="summary-avatar">
                            <?= strtoupper(substr($student["name"], 0, 1)) ?>
                        </div>

                        <h3><?= htmlspecialchars($student["name"]) ?></h3>

                        <p>
                            Roll No. #<?= htmlspecialchars($student["roll_no"]) ?>
                        </p>

                    </div>

                    <div class="summary-body">

                        <div class="summary-item">
                            <i class="fas fa-book"></i>

                            <div>
                                <strong>Current Course</strong>
                                <span>
                                    <?= htmlspecialchars($student["course_name"]) ?>
                                    (<?= htmlspecialchars($student["course_code"]) ?>)
                                </span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <i class="fas fa-layer-group"></i>

                            <div>
                                <strong>Current Section</strong>
                                <span>
                                    Section <?= htmlspecialchars($student["section_name"]) ?>
                                    · Semester <?= htmlspecialchars($student["semester"]) ?>
                                </span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <i class="fas fa-calendar"></i>

                            <div>
                                <strong>Academic Year</strong>
                                <span><?= htmlspecialchars($student["academic_year"]) ?></span>
                            </div>
                        </div>

                        <div class="summary-item">
                            <i class="fas fa-building-columns"></i>

                            <div>
                                <strong>Department</strong>
                                <span>
                                    <?= htmlspecialchars($student["department_name"]) ?>
                                    (<?= htmlspecialchars($student["department_code"]) ?>)
                                </span>
                            </div>
                        </div>

                        <div class="section-current">
                            <i class="fas fa-circle-info"></i>
                            Changing the section may change the student's course,
                            semester and academic year.
                        </div>

                    </div>

                </aside>

            </div>

        </main>

    </div>

</body>

</html>