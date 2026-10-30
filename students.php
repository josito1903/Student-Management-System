<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

/* =========================================================
   SEARCH
========================================================= */
$search = trim($_GET["search"] ?? "");

/* =========================================================
   STUDENTS
   Admin  -> sees all students
   Teacher -> sees only students in their own sections
========================================================= */

if ($role === "admin") {

    if ($search !== "") {
        $sql = "SELECT
                    s.id,
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
                INNER JOIN sections sec
                    ON s.section_id = sec.id
                INNER JOIN courses c
                    ON sec.course_id = c.id
                INNER JOIN departments d
                    ON c.department_id = d.id
                WHERE s.roll_no LIKE ?
                   OR s.name LIKE ?
                   OR s.email LIKE ?
                   OR c.name LIKE ?
                   OR c.code LIKE ?
                   OR sec.name LIKE ?
                ORDER BY s.id DESC";

        $stmt = mysqli_prepare($conn, $sql);
        $value = "%" . $search . "%";

        mysqli_stmt_bind_param(
            $stmt,
            "ssssss",
            $value,
            $value,
            $value,
            $value,
            $value,
            $value
        );

    } else {

        $sql = "SELECT
                    s.id,
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
                INNER JOIN sections sec
                    ON s.section_id = sec.id
                INNER JOIN courses c
                    ON sec.course_id = c.id
                INNER JOIN departments d
                    ON c.department_id = d.id
                ORDER BY s.id DESC";

        $stmt = mysqli_prepare($conn, $sql);
    }

} else {

    if ($search !== "") {

        $sql = "SELECT
                    s.id,
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
                INNER JOIN sections sec
                    ON s.section_id = sec.id
                INNER JOIN courses c
                    ON sec.course_id = c.id
                INNER JOIN departments d
                    ON c.department_id = d.id
                WHERE sec.teacher_id = ?
                  AND (
                       s.roll_no LIKE ?
                    OR s.name LIKE ?
                    OR s.email LIKE ?
                    OR c.name LIKE ?
                    OR c.code LIKE ?
                    OR sec.name LIKE ?
                  )
                ORDER BY s.id DESC";

        $stmt = mysqli_prepare($conn, $sql);
        $value = "%" . $search . "%";

        mysqli_stmt_bind_param(
            $stmt,
            "issssss",
            $user_id,
            $value,
            $value,
            $value,
            $value,
            $value,
            $value
        );

    } else {

        $sql = "SELECT
                    s.id,
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
                INNER JOIN sections sec
                    ON s.section_id = sec.id
                INNER JOIN courses c
                    ON sec.course_id = c.id
                INNER JOIN departments d
                    ON c.department_id = d.id
                WHERE sec.teacher_id = ?
                ORDER BY s.id DESC";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
    }
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

/* =========================================================
   COUNTS
========================================================= */

if ($role === "admin") {

    $count_sql = "SELECT COUNT(*) AS total FROM students";

    $section_count_sql = "SELECT COUNT(*) AS total FROM sections";

} else {

    $count_sql = "SELECT COUNT(*) AS total
                  FROM students s
                  INNER JOIN sections sec
                      ON s.section_id = sec.id
                  WHERE sec.teacher_id = ?";

    $section_count_sql = "SELECT COUNT(*) AS total
                          FROM sections
                          WHERE teacher_id = ?";
}

$count_stmt = mysqli_prepare($conn, $count_sql);

if ($role === "teacher") {
    mysqli_stmt_bind_param($count_stmt, "i", $user_id);
}

mysqli_stmt_execute($count_stmt);
$count_result = mysqli_stmt_get_result($count_stmt);
$count_data = mysqli_fetch_assoc($count_result);
$total_students = (int) $count_data["total"];

$section_stmt = mysqli_prepare($conn, $section_count_sql);

if ($role === "teacher") {
    mysqli_stmt_bind_param($section_stmt, "i", $user_id);
}

mysqli_stmt_execute($section_stmt);
$section_result = mysqli_stmt_get_result($section_stmt);
$section_data = mysqli_fetch_assoc($section_result);
$total_sections = (int) $section_data["total"];

/* =========================================================
   TOTAL COURSES
========================================================= */

if ($role === "admin") {

    $course_count_sql = "SELECT COUNT(*) AS total FROM courses";
    $course_count_stmt = mysqli_prepare($conn, $course_count_sql);

} else {

    $course_count_sql = "SELECT COUNT(DISTINCT sec.course_id) AS total
                         FROM sections sec
                         WHERE sec.teacher_id = ?";

    $course_count_stmt = mysqli_prepare($conn, $course_count_sql);
    mysqli_stmt_bind_param($course_count_stmt, "i", $user_id);
}

mysqli_stmt_execute($course_count_stmt);
$course_count_result = mysqli_stmt_get_result($course_count_stmt);
$course_count_data = mysqli_fetch_assoc($course_count_result);
$total_courses = (int) $course_count_data["total"];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= $role === "teacher" ? "My Students" : "Students" ?>
        | Student Management System
    </title>

    <link rel="stylesheet" href="style.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        /* =====================================================
           STUDENTS PAGE
        ===================================================== */

        .main-content {
    margin-left: 280px;
    width: calc(100% - 280px);
    min-height: 100vh;
    padding: 30px;
    box-sizing: border-box;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    z-index: 1000;
    box-sizing: border-box;
}

        .students-page {
            min-height: 100vh;
            background: #f5f7fb;
        }

        .students-page .dashboard-layout {
            min-height: 100vh;
        }

       .students-page .dashboard-main {
    margin-left: 280px !important;
    width: calc(100% - 280px) !important;
    max-width: none !important;
    min-width: 0;
    min-height: 100vh;
    box-sizing: border-box;
    padding: 30px;
}

        .students-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            padding: 30px 34px;
            border-radius: 24px;
            background:
                linear-gradient(135deg, #4338ca 0%, #6366f1 48%, #7c3aed 100%);
            color: #fff;
            box-shadow: 0 18px 45px rgba(67, 56, 202, .20);
        }

        .students-hero::before,
        .students-hero::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.10);
            pointer-events: none;
        }

        .students-hero::before {
            width: 210px;
            height: 210px;
            right: -60px;
            top: -80px;
        }

        .students-hero::after {
            width: 150px;
            height: 150px;
            right: 130px;
            bottom: -100px;
        }

        .students-hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .students-badge {
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

        .students-hero h2 {
            margin: 0 0 8px;
            font-size: clamp(26px, 3vw, 38px);
            line-height: 1.15;
        }

        .students-hero p {
            margin: 0;
            max-width: 650px;
            color: rgba(255,255,255,.84);
            line-height: 1.7;
        }

        .student-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .student-stat {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid #e8ebf2;
            box-shadow: 0 8px 25px rgba(15,23,42,.05);
        }

        .student-stat-icon {
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

        .student-stat strong {
            display: block;
            font-size: 25px;
            line-height: 1;
            color: #172033;
        }

        .student-stat span {
            display: block;
            margin-top: 6px;
            color: #7a8497;
            font-size: 13px;
        }

        .students-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .student-search {
            flex: 1;
            max-width: 620px;
            position: relative;
        }

        .student-search i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8a94a7;
        }

        .student-search input {
            width: 100%;
            height: 50px;
            padding: 0 18px 0 45px;
            border: 1px solid #dfe4ed;
            border-radius: 14px;
            outline: none;
            background: #fff;
            font-size: 14px;
            transition: .2s ease;
            box-sizing: border-box;
        }

        .student-search input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99,102,241,.10);
        }

        .add-student-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 50px;
            padding: 0 18px;
            border-radius: 14px;
            background: #4f46e5;
            color: #fff !important;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(79,70,229,.20);
            transition: .2s ease;
            white-space: nowrap;
        }

        .add-student-btn:hover {
            transform: translateY(-2px);
            background: #4338ca;
        }

        .students-card {
            background: #fff;
            border: 1px solid #e8ebf2;
            border-radius: 22px;
            box-shadow: 0 10px 30px rgba(15,23,42,.05);
            overflow: hidden;
        }

        .students-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 21px 24px;
            border-bottom: 1px solid #edf0f5;
        }

        .students-card-header h3 {
            margin: 0;
            color: #182033;
            font-size: 18px;
        }

        .students-card-header p {
            margin: 5px 0 0;
            color: #8992a3;
            font-size: 13px;
        }

        .student-count-badge {
            padding: 7px 11px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 12px;
            font-weight: 700;
        }

        .students-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .students-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        .students-table th {
            padding: 14px 18px;
            background: #fafbfc;
            color: #7d8798;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .65px;
            white-space: nowrap;
        }

        .students-table td {
            padding: 16px 18px;
            border-top: 1px solid #eef1f5;
            vertical-align: middle;
        }

        .students-table tbody tr {
            transition: background .18s ease;
        }

        .students-table tbody tr:hover {
            background: #fafbff;
        }

        .roll-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 9px;
            background: #f3f4f6;
            color: #4b5563;
            font-weight: 700;
            font-size: 12px;
        }

        .student-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 190px;
        }

        .table-student-avatar {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            background: linear-gradient(135deg, #e0e7ff, #ede9fe);
            color: #4338ca;
            font-weight: 800;
            font-size: 15px;
        }

        .student-name-cell strong {
            display: block;
            color: #20283a;
            font-size: 14px;
        }

        .student-name-cell small {
            display: block;
            margin-top: 3px;
            color: #9aa2b1;
            font-size: 11px;
        }

        .email-cell {
            color: #667085;
            font-size: 13px;
        }

        .course-info strong {
            display: block;
            color: #273044;
            font-size: 13px;
        }

        .course-info small {
            display: block;
            margin-top: 4px;
            color: #8b95a7;
            font-size: 11px;
        }

        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 9px;
            background: #ecfdf5;
            color: #047857;
            font-size: 12px;
            font-weight: 700;
        }

        .semester-text {
            color: #596579;
            font-size: 12px;
            white-space: nowrap;
        }

        .phone-cell {
            color: #667085;
            font-size: 12px;
            white-space: nowrap;
        }

        .student-actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .student-action {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            text-decoration: none;
            transition: .18s ease;
            font-size: 13px;
        }

        .edit-action {
            background: #eef2ff;
            color: #4f46e5;
        }

        .result-action {
            background: #ecfdf5;
            color: #059669;
        }

        .delete-action {
            background: #fef2f2;
            color: #dc2626;
        }

        .student-action:hover {
            transform: translateY(-2px);
        }

        .empty-students {
            padding: 60px 25px;
            text-align: center;
        }

        .empty-icon {
            width: 68px;
            height: 68px;
            margin: 0 auto 15px;
            display: grid;
            place-items: center;
            border-radius: 20px;
            background: #eef2ff;
            color: #6366f1;
            font-size: 27px;
        }

        .empty-students h3 {
            margin: 0 0 7px;
            color: #273044;
        }

        .empty-students p {
            margin: 0;
            color: #8b95a7;
            font-size: 13px;
        }

        @media (max-width: 900px) {
    .students-page .dashboard-main {
        margin-left: 0 !important;
        width: 100% !important;
        padding: 20px;
    }
}

        @media (max-width: 900px) {
    .main-content {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .sidebar {
        width: 260px;
    }
}

        @media (max-width: 900px) {
            .student-stats {
                grid-template-columns: 1fr;
            }

            .students-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .student-search {
                max-width: none;
            }
        }

        @media (max-width: 600px) {
            .students-hero {
                padding: 24px 20px;
                border-radius: 20px;
            }

            .students-card-header {
                padding: 18px;
            }

            .student-stat {
                padding: 16px;
            }

            .add-student-btn {
                width: 100%;
            }
        }
    </style>
</head>

<body class="dashboard-page students-page">

<div class="dashboard-layout">

    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <aside class="sidebar">

        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>

            <div>
                <h2>Student MS</h2>
                <span>
                    <?= $role === "admin"
                        ? "Management System"
                        : "Teacher Portal" ?>
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

            <?php if ($role === "teacher"): ?>

                <a href="result.php">
                    <i class="fas fa-chart-column"></i>
                    <span>Results</span>
                </a>

            <?php endif; ?>

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


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="dashboard-main">

        <!-- TOPBAR -->

        <header class="dashboard-topbar">

            <div>
                <h1>
                    <?= $role === "teacher" ? "My Students" : "Students" ?>
                </h1>

                <p>
                    <?= $role === "teacher"
                        ? "View and manage students assigned to your sections."
                        : "Manage students across all departments, courses and sections." ?>
                </p>
            </div>

            <div class="admin-profile">

                <div class="admin-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>

                <div>
                    <strong><?= htmlspecialchars($user_name) ?></strong>
                    <span><?= ucfirst(htmlspecialchars($role)) ?></span>
                </div>

            </div>

        </header>


        <!-- HERO -->

        <section class="students-hero">

            <div class="students-hero-content">

                <span class="students-badge">
                    <i class="fas fa-user-graduate"></i>
                    <?= $role === "teacher" ? "Teacher Portal" : "Academic Management" ?>
                </span>

                <h2>
                    <?= $role === "teacher"
                        ? "Your Students"
                        : "Student Directory" ?>
                </h2>

                <p>
                    <?= $role === "teacher"
                        ? "Keep track of the students belonging to your assigned sections and quickly access their academic information."
                        : "A complete overview of students organized by department, course and section." ?>
                </p>

            </div>

        </section>


        <!-- STATS -->

        <section class="student-stats">

            <div class="student-stat">

                <div class="student-stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>

                <div>
                    <strong><?= $total_students ?></strong>
                    <span><?= $role === "teacher" ? "My Students" : "Total Students" ?></span>
                </div>

            </div>


            <div class="student-stat">

                <div class="student-stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>

                <div>
                    <strong><?= $total_sections ?></strong>
                    <span><?= $role === "teacher" ? "My Sections" : "Total Sections" ?></span>
                </div>

            </div>


            <div class="student-stat">

                <div class="student-stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>

                <div>
                    <strong><?= $total_courses ?></strong>
                    <span><?= $role === "teacher" ? "My Courses" : "Total Courses" ?></span>
                </div>

            </div>

        </section>


        <!-- TOOLBAR -->

        <div class="students-toolbar">

            <form method="GET" action="students.php" class="student-search">

                <i class="fas fa-search"></i>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search by name, roll number, email, course or section..."
                >

            </form>


            <a href="add_student.php" class="add-student-btn">
                <i class="fas fa-plus"></i>
                Add Student
            </a>

        </div>


        <!-- STUDENT TABLE -->

        <section class="students-card">

            <div class="students-card-header">

                <div>
                    <h3>
                        <?= $role === "teacher"
                            ? "Students in My Sections"
                            : "All Students" ?>
                    </h3>

                    <p>
                        <?= $search !== ""
                            ? "Showing search results for \"" . htmlspecialchars($search) . "\""
                            : "Student information organized by course and section." ?>
                    </p>
                </div>

                <span class="student-count-badge">
                    <?= $total_students ?> Students
                </span>

            </div>


            <?php if (mysqli_num_rows($result) > 0): ?>

                <div class="students-table-wrap">

                    <table class="students-table">

                        <thead>
                            <tr>
                                <th>Roll No.</th>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Semester</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php while ($student = mysqli_fetch_assoc($result)): ?>

                            <tr>

                                <td>
                                    <span class="roll-badge">
                                        #<?= htmlspecialchars($student["roll_no"]) ?>
                                    </span>
                                </td>


                                <td>

                                    <div class="student-name-cell">

                                        <div class="table-student-avatar">
                                            <?= strtoupper(
                                                substr(
                                                    $student["name"],
                                                    0,
                                                    1
                                                )
                                            ) ?>
                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars($student["name"]) ?>
                                            </strong>

                                            <small>
                                                <?= htmlspecialchars(
                                                    $student["department_code"]
                                                ) ?>
                                            </small>

                                        </div>

                                    </div>

                                </td>


                                <td>
                                    <span class="email-cell">
                                        <?= htmlspecialchars($student["email"]) ?>
                                    </span>
                                </td>


                                <td>

                                    <div class="course-info">

                                        <strong>
                                            <?= htmlspecialchars($student["course_name"]) ?>
                                        </strong>

                                        <small>
                                            <?= htmlspecialchars($student["course_code"]) ?>
                                        </small>

                                    </div>

                                </td>


                                <td>

                                    <span class="section-badge">
                                        <i class="fas fa-users"></i>
                                        <?= htmlspecialchars($student["section_name"]) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="semester-text">
                                        Semester <?= htmlspecialchars($student["semester"]) ?>
                                        · <?= htmlspecialchars($student["academic_year"]) ?>
                                    </span>

                                </td>


                                <td>

                                    <span class="phone-cell">
                                        <i class="fas fa-phone"></i>
                                        <?= htmlspecialchars($student["phone"]) ?>
                                    </span>

                                </td>


                                <td>

                                    <div class="student-actions">

                                        <a
                                            href="edit_student.php?id=<?= (int) $student["id"] ?>"
                                            class="student-action edit-action"
                                            title="Edit Student"
                                        >
                                            <i class="fas fa-pen"></i>
                                        </a>


                                        <a
                                            href="result.php?id=<?= (int) $student["id"] ?>"
                                            class="student-action result-action"
                                            title="View Result"
                                        >
                                            <i class="fas fa-chart-column"></i>
                                        </a>


                                        <a
                                            href="delete_student.php?id=<?= (int) $student["id"] ?>"
                                            class="student-action delete-action"
                                            title="Delete Student"
                                            onclick="return confirm('Are you sure you want to delete this student?');"
                                        >
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

                <div class="empty-students">

                    <div class="empty-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>

                    <h3>
                        <?= $search !== ""
                            ? "No students found"
                            : "No students yet" ?>
                    </h3>

                    <p>
                        <?= $search !== ""
                            ? "Try a different name, roll number, course or section."
                            : ($role === "teacher"
                                ? "Students assigned to your sections will appear here."
                                : "Add your first student to start building the student directory.") ?>
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
