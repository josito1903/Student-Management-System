<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$errors = [];
$success = "";

$roll_no = trim($_POST["roll_no"] ?? "");
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$gender = $_POST["gender"] ?? "";
$date_of_birth = $_POST["date_of_birth"] ?? "";
$address = trim($_POST["address"] ?? "");
$section_id = (int) ($_POST["section_id"] ?? 0);

/*
|--------------------------------------------------------------------------
| LOAD AVAILABLE SECTIONS
|--------------------------------------------------------------------------
| Admin -> all sections
| Teacher -> only sections assigned to the logged-in teacher
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
| ADD STUDENT
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

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
     * Server-side ownership check.
     * A teacher cannot submit a section belonging to another teacher.
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

    /* Check unique roll number */
    if (empty($errors)) {
        $duplicate_sql = "SELECT id FROM students WHERE roll_no = ?";
        $duplicate_stmt = mysqli_prepare($conn, $duplicate_sql);
        mysqli_stmt_bind_param($duplicate_stmt, "s", $roll_no);
        mysqli_stmt_execute($duplicate_stmt);
        $duplicate_result = mysqli_stmt_get_result($duplicate_stmt);

        if (mysqli_num_rows($duplicate_result) > 0) {
            $errors[] = "This roll number is already registered.";
        }
    }

    /* Insert student */
    if (empty($errors)) {
        $insert_sql = "
            INSERT INTO students
            (section_id, roll_no, name, email, phone, gender, date_of_birth, address)
            VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)
        ";

        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        $gender_value = $gender;
        $dob_value = $date_of_birth;

        mysqli_stmt_bind_param(
            $insert_stmt,
            "isssssss",
            $section_id,
            $roll_no,
            $name,
            $email,
            $phone,
            $gender_value,
            $dob_value,
            $address
        );

        if (mysqli_stmt_execute($insert_stmt)) {
            header("Location: students.php?added=1");
            exit();
        } else {
            $errors[] = "Unable to add the student. Please try again.";
        }
    }

    /*
     * Re-load sections after POST so the form remains usable if validation fails.
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

    <title>Add Student | Student Management System</title>

    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        /* =====================================================
           ADD STUDENT PAGE
        ===================================================== */

        .add-student-page {
            min-height: 100vh;
            background: #f5f7fb;
        }

        .add-student-page .dashboard-layout {
            width: 100%;
            min-width: 0;
            min-height: 100vh;
        }

        .add-student-page .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            z-index: 1000;
            box-sizing: border-box;
        }

        .add-student-page .dashboard-main {
            margin-left: 280px !important;
            width: calc(100% - 280px) !important;
            max-width: none !important;
            min-width: 0;
            min-height: 100vh;
            padding: 30px;
            box-sizing: border-box;
        }

        .add-student-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .add-student-topbar h1 {
            margin: 0;
            color: #182033;
            font-size: 28px;
            line-height: 1.2;
        }

        .add-student-topbar p {
            margin: 7px 0 0;
            color: #7d8798;
            font-size: 14px;
        }

        .add-student-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 9px 14px 9px 9px;
            background: #fff;
            border: 1px solid #e8ebf2;
            border-radius: 17px;
            box-shadow: 0 8px 25px rgba(15, 23, 42, .06);
        }

        .add-student-avatar {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #4f46e5;
            color: #fff;
            font-weight: 800;
        }

        .add-student-profile strong {
            display: block;
            color: #182033;
            font-size: 13px;
        }

        .add-student-profile span {
            display: block;
            margin-top: 2px;
            color: #7d8798;
            font-size: 12px;
        }

        .add-student-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            padding: 30px 34px;
            border-radius: 24px;
            background: linear-gradient(135deg, #4338ca 0%, #6366f1 48%, #7c3aed 100%);
            color: #fff;
            box-shadow: 0 18px 45px rgba(67, 56, 202, .20);
        }

        .add-student-hero::before,
        .add-student-hero::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.10);
            pointer-events: none;
        }

        .add-student-hero::before {
            width: 210px;
            height: 210px;
            right: -60px;
            top: -80px;
        }

        .add-student-hero::after {
            width: 150px;
            height: 150px;
            right: 130px;
            bottom: -100px;
        }

        .add-student-hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .add-student-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.18);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .4px;
            margin-bottom: 14px;
        }

        .add-student-hero h2 {
            margin: 0 0 8px;
            font-size: clamp(25px, 3vw, 36px);
            line-height: 1.15;
        }

        .add-student-hero p {
            margin: 0;
            max-width: 680px;
            color: rgba(255,255,255,.84);
            line-height: 1.7;
            font-size: 14px;
        }

        .add-student-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 22px;
            align-items: start;
        }

        .student-form-card,
        .student-info-card {
            background: #fff;
            border: 1px solid #e8ebf2;
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(15,23,42,.05);
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
            box-shadow: 0 0 0 4px rgba(99,102,241,.10);
        }

        .section-select-wrap {
            position: relative;
        }

        .section-select-wrap .student-select {
            padding-right: 42px;
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
            font-size: 13px;
            line-height: 1.5;
        }

        .form-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
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
            box-shadow: 0 8px 20px rgba(79,70,229,.20);
        }

        .save-btn:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        .student-info-card {
            padding: 23px;
        }

        .info-icon {
            width: 52px;
            height: 52px;
            display: grid;
            place-items: center;
            margin-bottom: 17px;
            border-radius: 15px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 20px;
        }

        .student-info-card h3 {
            margin: 0 0 8px;
            color: #20283a;
            font-size: 17px;
        }

        .student-info-card > p {
            margin: 0 0 19px;
            color: #8992a3;
            font-size: 12px;
            line-height: 1.7;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 13px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            border-radius: 12px;
            background: #fafbfc;
        }

        .info-item i {
            width: 25px;
            color: #6366f1;
            text-align: center;
            margin-top: 2px;
        }

        .info-item strong {
            display: block;
            color: #374151;
            font-size: 12px;
        }

        .info-item span {
            display: block;
            margin-top: 3px;
            color: #8992a3;
            font-size: 11px;
            line-height: 1.5;
        }

        .no-sections {
            padding: 15px;
            border-radius: 13px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #c2410c;
            font-size: 12px;
            line-height: 1.6;
        }

        .no-sections a {
            color: #9a3412;
            font-weight: 700;
        }

        @media (max-width: 1050px) {
            .add-student-layout {
                grid-template-columns: 1fr;
            }

            .student-info-card {
                order: -1;
            }
        }

        @media (max-width: 900px) {
            .add-student-page .dashboard-main {
                margin-left: 0 !important;
                width: 100% !important;
                padding: 20px;
            }

            .add-student-page .sidebar {
                width: 260px;
            }
        }

        @media (max-width: 650px) {
            .add-student-topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .add-student-profile {
                align-self: stretch;
            }

            .add-student-hero {
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

<body class="dashboard-page add-student-page">

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

        <header class="add-student-topbar">

            <div>
                <h1>Add Student</h1>
                <p>Create a new student record and assign the student to a section.</p>
            </div>

            <div class="add-student-profile">
                <div class="add-student-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>

                <div>
                    <strong><?= htmlspecialchars($user_name) ?></strong>
                    <span><?= ucfirst(htmlspecialchars($role)) ?></span>
                </div>
            </div>

        </header>

        <section class="add-student-hero">

            <div class="add-student-hero-content">

                <span class="add-student-badge">
                    <i class="fas fa-user-plus"></i>
                    <?= $role === "teacher" ? "Teacher Portal" : "Academic Management" ?>
                </span>

                <h2>Add a New Student</h2>

                <p>
                    Enter the student's personal information and choose the
                    appropriate course section. The section determines the
                    student's academic structure.
                </p>

            </div>

        </section>

        <div class="add-student-layout">

            <section class="student-form-card">

                <div class="student-card-heading">
                    <h3>Student Information</h3>
                    <p>Fields marked with <span class="required">*</span> are required.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="form-alert error">
                        <i class="fas fa-circle-exclamation"></i>

                        <div>
                            <?php foreach ($errors as $error): ?>
                                <div><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="add_student.php">

                    <div class="student-form-grid">

                        <div class="student-form-group full">
                            <label for="section_id">
                                Section <span class="required">*</span>
                            </label>

                            <?php if (mysqli_num_rows($section_result) > 0): ?>

                                <select
                                    name="section_id"
                                    id="section_id"
                                    class="student-select"
                                    required
                                >
                                    <option value="">Select a section</option>

                                    <?php while ($section = mysqli_fetch_assoc($section_result)): ?>
                                        <option
                                            value="<?= (int) $section["id"] ?>"
                                            <?= $section_id === (int) $section["id"] ? "selected" : "" ?>
                                        >
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
                                        : "Select the section where this student will be enrolled." ?>
                                </div>

                            <?php else: ?>

                                <div class="no-sections">
                                    <i class="fas fa-circle-info"></i>
                                    No sections are available.
                                    <?php if ($role === "teacher"): ?>
                                        Create a section first from
                                        <a href="sections.php">My Sections</a>.
                                    <?php else: ?>
                                        Create a section first from
                                        <a href="sections.php">Sections</a>.
                                    <?php endif; ?>
                                </div>

                            <?php endif; ?>

                        </div>

                        <div class="student-form-group">
                            <label for="roll_no">
                                Roll Number <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="roll_no"
                                id="roll_no"
                                class="student-input"
                                value="<?= htmlspecialchars($roll_no) ?>"
                                placeholder="e.g. CS001"
                                maxlength="20"
                                required
                            >

                            <div class="field-help">Roll number must be unique.</div>
                        </div>

                        <div class="student-form-group">
                            <label for="name">
                                Full Name <span class="required">*</span>
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="name"
                                class="student-input"
                                value="<?= htmlspecialchars($name) ?>"
                                placeholder="Enter student's full name"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="student-form-group">
                            <label for="email">
                                Email Address <span class="required">*</span>
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="student-input"
                                value="<?= htmlspecialchars($email) ?>"
                                placeholder="student@example.com"
                                maxlength="100"
                                required
                            >
                        </div>

                        <div class="student-form-group">
                            <label for="phone">
                                Phone Number <span class="required">*</span>
                            </label>

                            <input
                                type="tel"
                                name="phone"
                                id="phone"
                                class="student-input"
                                value="<?= htmlspecialchars($phone) ?>"
                                placeholder="Enter phone number"
                                maxlength="15"
                                required
                            >
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

                            <input
                                type="date"
                                name="date_of_birth"
                                id="date_of_birth"
                                class="student-input"
                                value="<?= htmlspecialchars($date_of_birth) ?>"
                            >
                        </div>

                        <div class="student-form-group full">
                            <label for="address">Address</label>

                            <textarea
                                name="address"
                                id="address"
                                class="student-textarea"
                                placeholder="Enter student's address"
                            ><?= htmlspecialchars($address) ?></textarea>
                        </div>

                    </div>

                    <div class="form-actions">
                        <a href="students.php" class="form-btn cancel-btn">
                            <i class="fas fa-arrow-left"></i>
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="form-btn save-btn"
                            <?= mysqli_num_rows($section_result) === 0 ? "disabled" : "" ?>
                        >
                            <i class="fas fa-user-plus"></i>
                            Add Student
                        </button>
                    </div>

                </form>

            </section>

            <aside class="student-info-card">

                <div class="info-icon">
                    <i class="fas fa-circle-info"></i>
                </div>

                <h3>Before You Add</h3>

                <p>
                    Make sure the student's details are correct before saving
                    the record.
                </p>

                <div class="info-list">

                    <div class="info-item">
                        <i class="fas fa-layer-group"></i>
                        <div>
                            <strong>Choose the right section</strong>
                            <span>
                                The section connects the student to a course,
                                semester and academic year.
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-id-card"></i>
                        <div>
                            <strong>Use a unique roll number</strong>
                            <span>
                                A roll number cannot be assigned to another
                                student.
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong>Access control</strong>
                            <span>
                                Teachers can add students only to their own
                                assigned sections.
                            </span>
                        </div>
                    </div>

                    <div class="info-item">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <strong>Academic records</strong>
                            <span>
                                Marks and results will be connected to this
                                student's section and course later.
                            </span>
                        </div>
                    </div>

                </div>

            </aside>

        </div>

    </main>

</div>

</body>
</html>
