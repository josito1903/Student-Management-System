<?php
require_once "auth.php";
require_once "db.php";

$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$selected_student_id = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : 0;
$error = "";
$student = null;
$subjects = [];

/*
|--------------------------------------------------------------------------
| Load selected student
|--------------------------------------------------------------------------
*/
if ($selected_student_id > 0) {

    if ($role === "admin") {
        $stmt = mysqli_prepare($conn, "
            SELECT
                s.id,
                s.roll_no,
                s.name,
                s.email,
                s.phone,
                sec.name AS section_name,
                sec.semester,
                sec.academic_year,
                c.id AS course_id,
                c.name AS course_name,
                c.code AS course_code,
                d.name AS department_name,
                d.code AS department_code
            FROM students s
            INNER JOIN sections sec ON s.section_id = sec.id
            INNER JOIN courses c ON sec.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            WHERE s.id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $selected_student_id);

    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT
                s.id,
                s.roll_no,
                s.name,
                s.email,
                s.phone,
                sec.name AS section_name,
                sec.semester,
                sec.academic_year,
                c.id AS course_id,
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
        ");
        mysqli_stmt_bind_param($stmt, "ii", $selected_student_id, $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$student) {
        $error = "Student not found or you do not have permission to view this result.";
        $selected_student_id = 0;
    }
}

/*
|--------------------------------------------------------------------------
| Load subjects + marks
|--------------------------------------------------------------------------
*/
$total_obtained = 0;
$total_max = 0;
$entered_subjects = 0;
$passed_subjects = 0;
$failed_subjects = 0;

if ($student) {

    $stmt = mysqli_prepare($conn, "
        SELECT
            sub.id,
            sub.name,
            sub.code,
            sub.max_marks,
            m.marks
        FROM subjects sub
        LEFT JOIN marks m
            ON m.subject_id = sub.id
           AND m.student_id = ?
        WHERE sub.course_id = ?
          AND sub.semester = ?
        ORDER BY sub.id ASC
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "iii",
        $student["id"],
        $student["course_id"],
        $student["semester"]
    );

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;

        $max = (float)$row["max_marks"];
        $mark = $row["marks"];

        $total_max += $max;

        if ($mark !== null) {
            $mark = (float)$mark;
            $total_obtained += $mark;
            $entered_subjects++;

            /*
             * Subject pass rule:
             * minimum 40% in each subject.
             */
            $subject_percentage = $max > 0 ? ($mark / $max) * 100 : 0;

            if ($subject_percentage >= 40) {
                $passed_subjects++;
            } else {
                $failed_subjects++;
            }
        }
    }

    mysqli_stmt_close($stmt);
}

$percentage = $total_max > 0
    ? ($total_obtained / $total_max) * 100
    : 0;

/*
|--------------------------------------------------------------------------
| Grade
|--------------------------------------------------------------------------
*/
if ($percentage >= 90) {
    $grade = "A+";
} elseif ($percentage >= 80) {
    $grade = "A";
} elseif ($percentage >= 70) {
    $grade = "B";
} elseif ($percentage >= 60) {
    $grade = "C";
} elseif ($percentage >= 50) {
    $grade = "D";
} else {
    $grade = "F";
}

/*
|--------------------------------------------------------------------------
| Final result
|--------------------------------------------------------------------------
|
| Student must:
| 1. Have marks entered for all subjects
| 2. Score at least 40% in every subject
| 3. Have an overall percentage of at least 40%
|
*/
$is_complete = count($subjects) > 0 &&
               $entered_subjects === count($subjects);

$is_pass = $is_complete &&
           $failed_subjects === 0 &&
           $percentage >= 40;

$result_status = $is_pass ? "PASS" : "FAIL";

/*
|--------------------------------------------------------------------------
| Load student list
|--------------------------------------------------------------------------
*/
$students = [];

if ($role === "admin") {
    $stmt = mysqli_prepare($conn, "
        SELECT
            s.id,
            s.roll_no,
            s.name,
            sec.name AS section_name,
            sec.semester,
            c.code AS course_code
        FROM students s
        INNER JOIN sections sec ON s.section_id = sec.id
        INNER JOIN courses c ON sec.course_id = c.id
        ORDER BY s.name ASC
    ");
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT
            s.id,
            s.roll_no,
            s.name,
            sec.name AS section_name,
            sec.semester,
            c.code AS course_code
        FROM students s
        INNER JOIN sections sec ON s.section_id = sec.id
        INNER JOIN courses c ON sec.course_id = c.id
        WHERE sec.teacher_id = ?
        ORDER BY s.name ASC
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}

mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($student_result)) {
    $students[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Result | Student Management System</title>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    .result-page {
        background: #f5f7fb;
    }

    .result-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh;
    }

    .result-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .result-page .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 24px 20px;
    }

    .result-page .sidebar-logo-icon {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: #4f46e5;
        color: #fff;
        font-size: 18px;
    }

    .result-page .sidebar-logo h2 {
        margin: 0;
    }

    .result-page .sidebar-logo span {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        opacity: .65;
    }

    .result-page .sidebar-menu {
        padding: 8px 14px;
    }

    .result-page .sidebar-menu a,
    .result-page .sidebar-bottom a {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 44px;
        padding: 0 14px;
        margin-bottom: 5px;
        border-radius: 10px;
        color: inherit;
        text-decoration: none;
        transition: .2s ease;
    }

    .result-page .sidebar-menu a i,
    .result-page .sidebar-bottom a i {
        width: 20px;
        text-align: center;
    }

    .result-page .sidebar-menu a:hover,
    .result-page .sidebar-bottom a:hover {
        background: rgba(255, 255, 255, .08);
    }

    .result-page .sidebar-menu a.active {
        background: #4f46e5;
        color: #fff;
    }

    .result-page .sidebar-bottom {
        position: absolute;
        left: 14px;
        right: 14px;
        bottom: 18px;
        padding-top: 12px;
        border-top: 1px solid rgba(255, 255, 255, .08);
    }

    .result-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        box-sizing: border-box;
        padding: 30px;
    }

    .result-page .btn-primary,
    .result-page .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: .2s ease;
    }

    .result-page {
        min-height: 100vh;
    }

    .result-page .dashboard-layout {
        width: 100%;
        min-width: 0;
    }

    .result-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box;
    }

    .result-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        box-sizing: border-box;
        padding: 30px;
    }

    .result-hero {
        background: linear-gradient(135deg, #182848, #4b6cb7);
        color: white;
        border-radius: 24px;
        padding: 34px;
        margin-bottom: 24px;
    }

    .result-hero h1 {
        margin: 0 0 8px;
        font-size: 32px;
    }

    .result-hero p {
        margin: 0;
        opacity: .9;
    }

    .result-card {
        background: #fff;
        border: 1px solid #e7eaf0;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 24px;
        box-shadow: 0 8px 25px rgba(20, 30, 50, .05);
    }

    .result-card h2 {
        margin-top: 0;
    }

    .student-selector {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 14px;
        align-items: end;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 7px;
    }

    .form-group select {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid #d8dde7;
        border-radius: 10px;
        font-size: 15px;
        background: white;
        box-sizing: border-box;
    }

    .btn-primary,
    .btn-secondary {
        border-radius: 10px;
        padding: 12px 19px;
        cursor: pointer;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
    }

    .btn-primary {
        border: 0;
        background: #182848;
        color: white;
    }

    .btn-secondary {
        border: 1px solid #d8dde7;
        background: white;
        color: #182848;
    }

    .alert {
        border-radius: 12px;
        padding: 14px 17px;
        margin-bottom: 20px;
        background: #fff0f0;
        color: #a62929;
        border: 1px solid #f0c5c5;
    }

    .student-info {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
    }

    .info-box {
        background: #f7f9fc;
        border-radius: 12px;
        padding: 14px;
    }

    .info-box small {
        display: block;
        color: #697386;
        margin-bottom: 4px;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
        margin-bottom: 24px;
    }

    .summary-item {
        border: 1px solid #e7eaf0;
        border-radius: 16px;
        padding: 20px;
        background: #fff;
        box-shadow: 0 8px 25px rgba(20, 30, 50, .04);
    }

    .summary-item small {
        display: block;
        color: #697386;
        margin-bottom: 7px;
    }

    .summary-item strong {
        font-size: 27px;
    }

    .status-card {
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 24px;
        border: 1px solid;
    }

    .status-pass {
        background: #ecf9f0;
        border-color: #bde5c9;
        color: #176b38;
    }

    .status-fail {
        background: #fff1f1;
        border-color: #efc4c4;
        color: #a62929;
    }

    .status-card h2 {
        margin: 0 0 6px;
    }

    .status-card p {
        margin: 0;
    }

    .result-table-wrap {
        overflow-x: auto;
    }

    .result-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 650px;
    }

    .result-table th,
    .result-table td {
        padding: 14px;
        border-bottom: 1px solid #edf0f4;
        text-align: left;
    }

    .result-table th {
        background: #f7f9fc;
        color: #4d5666;
    }

    .subject-pass {
        font-weight: 600;
        color: #176b38;
    }

    .subject-fail {
        font-weight: 600;
        color: #a62929;
    }

    .subject-pending {
        font-weight: 600;
        color: #8a6400;
    }

    .grade-badge {
        display: inline-block;
        min-width: 42px;
        text-align: center;
        padding: 6px 10px;
        border-radius: 20px;
        background: #eef2ff;
        font-weight: 700;
    }

    .result-actions {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        align-items: center;
        margin-top: 20px;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #697386;
    }

    .print-only {
        display: none;
    }

    @media print {

        .sidebar,
        .student-selector-card,
        .result-actions,
        .result-hero {
            display: none !important;
        }

        .result-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 0 !important;
        }

        .result-card,
        .summary-item {
            box-shadow: none;
            border: 1px solid #ddd;
        }

        .print-only {
            display: block;
        }
    }

    @media (max-width: 1100px) {

        .student-info,
        .summary-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 900px) {
        .result-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px;
        }

        .result-page .sidebar {
            width: 260px;
        }

        .result-page .sidebar-logo {
            padding: 20px 16px;
        }

        .student-selector {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 600px) {
        .result-page .dashboard-main {
            padding: 15px;
        }

        .result-hero {
            padding: 25px 20px;
        }

        .result-hero h1 {
            font-size: 25px;
        }

        .result-card {
            padding: 18px;
        }

        .student-info,
        .summary-grid {
            grid-template-columns: 1fr;
        }

        .result-actions {
            flex-direction: column;
            align-items: stretch;
        }
    }
    </style>
</head>

<body class="result-page">

    <div class="dashboard-layout">

        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h2>Student MS</h2>
                    <span><?= $role === "admin" ? "Management System" : "Teacher Portal" ?></span>
                </div>
            </div>

            <nav class="sidebar-menu">
                <?php if ($role === "admin"): ?>
                <a href="admin_dashboard.php">
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="departments.php">
                    <i class="fas fa-building-columns"></i>
                    <span>Departments</span>
                </a>
                <a href="teachers.php">
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
                <a href="subjects.php">
                    <i class="fas fa-book-open"></i>
                    <span>Subjects</span>
                </a>
                <a href="marks.php">
                    <i class="fas fa-pen-to-square"></i>
                    <span>Marks &amp; Results</span>
                </a>
                <?php else: ?>
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
                <a href="result.php" class="active">
                    <i class="fas fa-chart-column"></i>
                    <span>Results</span>
                </a>
                <a href="teacher_profile.php">
                    <i class="fas fa-user-circle"></i>
                    <span>My Profile</span>
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

        <main class="dashboard-main">

            <section class="result-hero">
                <h1>Student Results</h1>
                <p>View marks, percentage, grades and final result status.</p>
            </section>

            <?php if ($error): ?>
            <div class="alert">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <section class="result-card student-selector-card">
                <h2>Select Student</h2>

                <?php if (empty($students)): ?>

                <div class="empty-state">
                    <h3>No students available</h3>
                    <p>Add students before viewing results.</p>
                    <a href="students.php" class="btn-primary">
                        Go to Students
                    </a>
                </div>

                <?php else: ?>

                <form method="GET">

                    <div class="student-selector">

                        <div class="form-group">
                            <label for="student_id">Student</label>

                            <select name="student_id" id="student_id" required>
                                <option value="">
                                    -- Select Student --
                                </option>

                                <?php foreach ($students as $s): ?>

                                <option value="<?= (int)$s["id"] ?>"
                                    <?= $selected_student_id == $s["id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars(
                                            $s["roll_no"] .
                                            " - " .
                                            $s["name"] .
                                            " | " .
                                            $s["course_code"] .
                                            " | Section " .
                                            $s["section_name"]
                                        ) ?>
                                </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <button type="submit" class="btn-primary">
                            View Result
                        </button>

                    </div>

                </form>

                <?php endif; ?>

            </section>

            <?php if ($student): ?>

            <div class="print-only">
                <h1>Student Result</h1>
                <p>Student Management System</p>
            </div>

            <section class="result-card">

                <h2>Student Information</h2>

                <div class="student-info">

                    <div class="info-box">
                        <small>Student</small>
                        <strong>
                            <?= htmlspecialchars($student["name"]) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Roll Number</small>
                        <strong>
                            <?= htmlspecialchars($student["roll_no"]) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Course</small>
                        <strong>
                            <?= htmlspecialchars(
                                $student["course_name"] .
                                " (" .
                                $student["course_code"] .
                                ")"
                            ) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Section</small>
                        <strong>
                            <?= htmlspecialchars(
                                $student["section_name"] .
                                " • Semester " .
                                $student["semester"]
                            ) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Academic Year</small>
                        <strong>
                            <?= htmlspecialchars($student["academic_year"]) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Department</small>
                        <strong>
                            <?= htmlspecialchars(
                                $student["department_name"] .
                                " (" .
                                $student["department_code"] .
                                ")"
                            ) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Email</small>
                        <strong>
                            <?= htmlspecialchars($student["email"]) ?>
                        </strong>
                    </div>

                    <div class="info-box">
                        <small>Phone</small>
                        <strong>
                            <?= htmlspecialchars($student["phone"]) ?>
                        </strong>
                    </div>

                </div>

            </section>

            <div class="summary-grid">

                <div class="summary-item">
                    <small>Total Marks</small>
                    <strong>
                        <?= number_format($total_obtained, 2) ?>
                        /
                        <?= number_format($total_max, 2) ?>
                    </strong>
                </div>

                <div class="summary-item">
                    <small>Percentage</small>
                    <strong>
                        <?= number_format($percentage, 2) ?>%
                    </strong>
                </div>

                <div class="summary-item">
                    <small>Grade</small>
                    <strong>
                        <?= htmlspecialchars($grade) ?>
                    </strong>
                </div>

                <div class="summary-item">
                    <small>Subjects</small>
                    <strong>
                        <?= $entered_subjects ?> /
                        <?= count($subjects) ?>
                    </strong>
                </div>

            </div>

            <section class="status-card <?= $is_pass ? "status-pass" : "status-fail" ?>">

                <h2>
                    Final Result: <?= $result_status ?>
                </h2>

                <?php if (!$is_complete): ?>

                <p>
                    Marks are incomplete. Enter marks for all subjects
                    before treating this result as final.
                </p>

                <?php elseif ($failed_subjects > 0): ?>

                <p>
                    The student has failed
                    <?= $failed_subjects ?>
                    subject<?= $failed_subjects === 1 ? "" : "s" ?>.
                    The minimum subject pass requirement is 40%.
                </p>

                <?php else: ?>

                <p>
                    The student has passed all subjects with an overall
                    percentage of <?= number_format($percentage, 2) ?>%.
                </p>

                <?php endif; ?>

            </section>

            <section class="result-card">

                <h2>Subject-wise Result</h2>

                <?php if (empty($subjects)): ?>

                <div class="empty-state">

                    <h3>No subjects found</h3>

                    <p>
                        No subjects have been created for this student's
                        course and semester.
                    </p>

                    <a href="subjects.php" class="btn-primary">
                        Manage Subjects
                    </a>

                </div>

                <?php else: ?>

                <div class="result-table-wrap">

                    <table class="result-table">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Max Marks</th>
                                <th>Obtained</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($subjects as $index => $subject): ?>

                            <?php
                                $max = (float)$subject["max_marks"];

                                if ($subject["marks"] === null) {
                                    $mark = null;
                                    $subject_percentage = 0;
                                    $subject_grade = "-";
                                    $subject_status = "Pending";
                                    $status_class = "subject-pending";
                                } else {
                                    $mark = (float)$subject["marks"];

                                    $subject_percentage = $max > 0
                                        ? ($mark / $max) * 100
                                        : 0;

                                    if ($subject_percentage >= 90) {
                                        $subject_grade = "A+";
                                    } elseif ($subject_percentage >= 80) {
                                        $subject_grade = "A";
                                    } elseif ($subject_percentage >= 70) {
                                        $subject_grade = "B";
                                    } elseif ($subject_percentage >= 60) {
                                        $subject_grade = "C";
                                    } elseif ($subject_percentage >= 50) {
                                        $subject_grade = "D";
                                    } else {
                                        $subject_grade = "F";
                                    }

                                    if ($subject_percentage >= 40) {
                                        $subject_status = "Pass";
                                        $status_class = "subject-pass";
                                    } else {
                                        $subject_status = "Fail";
                                        $status_class = "subject-fail";
                                    }
                                }
                                ?>

                            <tr>

                                <td><?= $index + 1 ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($subject["name"]) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($subject["code"]) ?>
                                </td>

                                <td>
                                    <?= number_format($max, 2) ?>
                                </td>

                                <td>
                                    <?= $mark === null
                                            ? "-"
                                            : number_format($mark, 2) ?>
                                </td>

                                <td>
                                    <?= $mark === null
                                            ? "-"
                                            : number_format($subject_percentage, 2) . "%" ?>
                                </td>

                                <td>
                                    <span class="grade-badge">
                                        <?= htmlspecialchars($subject_grade) ?>
                                    </span>
                                </td>

                                <td class="<?= $status_class ?>">
                                    <?= $subject_status ?>
                                </td>

                            </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="result-actions">

                    <a href="marks.php" class="btn-secondary">
                        <i class="fas fa-pen-to-square"></i>
                        Edit Marks
                    </a>

                    <button type="button" class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i>
                        Print Result
                    </button>

                </div>

                <?php endif; ?>

            </section>

            <?php endif; ?>

        </main>

    </div>

</body>

</html>