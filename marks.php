<?php
require_once "auth.php";
require_once "db.php";

$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$message = "";
$error = "";

$selected_section_id = (int)($_GET["section_id"] ?? $_POST["section_id"] ?? 0);
$selected_subject_id = (int)($_GET["subject_id"] ?? $_POST["subject_id"] ?? 0);

/* Get sections the current user can manage */
$sections = [];

if ($role === "admin") {
    $stmt = mysqli_prepare($conn, "
        SELECT sec.id, sec.name, sec.semester, sec.academic_year,
               c.id AS course_id, c.name AS course_name, c.code AS course_code,
               d.name AS department_name
        FROM sections sec
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        ORDER BY d.name, c.name, sec.semester, sec.name
    ");
} else {
    $stmt = mysqli_prepare($conn, "
        SELECT sec.id, sec.name, sec.semester, sec.academic_year,
               c.id AS course_id, c.name AS course_name, c.code AS course_code,
               d.name AS department_name
        FROM sections sec
        INNER JOIN courses c ON sec.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        WHERE sec.teacher_id = ?
        ORDER BY d.name, c.name, sec.semester, sec.name
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $sections[] = $row;
}
mysqli_stmt_close($stmt);

/* Verify selected section */
$section = null;
if ($selected_section_id > 0) {
    if ($role === "admin") {
        $stmt = mysqli_prepare($conn, "
            SELECT sec.id, sec.name, sec.semester, sec.academic_year,
                   c.id AS course_id, c.name AS course_name, c.code AS course_code,
                   d.name AS department_name
            FROM sections sec
            INNER JOIN courses c ON sec.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            WHERE sec.id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $selected_section_id);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT sec.id, sec.name, sec.semester, sec.academic_year,
                   c.id AS course_id, c.name AS course_name, c.code AS course_code,
                   d.name AS department_name
            FROM sections sec
            INNER JOIN courses c ON sec.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            WHERE sec.id = ? AND sec.teacher_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $selected_section_id, $user_id);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $section = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$section) {
        $selected_section_id = 0;
        $selected_subject_id = 0;
        $error = "Section not found or access denied.";
    }
}

/* Load subjects for selected section */
$subjects = [];
if ($section) {
    $stmt = mysqli_prepare($conn, "
        SELECT id, name, code, semester, max_marks
        FROM subjects
        WHERE course_id = ? AND semester = ?
        ORDER BY name ASC
    ");
    mysqli_stmt_bind_param($stmt, "ii", $section["course_id"], $section["semester"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $subjects[] = $row;
    }
    mysqli_stmt_close($stmt);

    $valid_subject = false;
    foreach ($subjects as $subject) {
        if ((int)$subject["id"] === $selected_subject_id) {
            $valid_subject = true;
            break;
        }
    }

    if (!$valid_subject) {
        $selected_subject_id = 0;
    }
}

/* Verify selected subject */
$subject = null;
if ($section && $selected_subject_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT id, name, code, semester, max_marks
        FROM subjects
        WHERE id = ? AND course_id = ? AND semester = ?
    ");
    mysqli_stmt_bind_param(
        $stmt,
        "iii",
        $selected_subject_id,
        $section["course_id"],
        $section["semester"]
    );
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $subject = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$subject) {
        $selected_subject_id = 0;
        $error = "Subject not found for this section.";
    }
}

/* Save marks for every student in the selected section */
if ($_SERVER["REQUEST_METHOD"] === "POST" && $section && $subject) {
    $marks = $_POST["marks"] ?? [];
    $student_ids = array_keys($marks);

    mysqli_begin_transaction($conn);

    try {
        foreach ($student_ids as $student_id_raw) {
            $student_id = (int)$student_id_raw;
            $value = trim((string)$marks[$student_id_raw]);

            /* Confirm student belongs to selected section */
            $check_stmt = mysqli_prepare($conn, "
                SELECT id
                FROM students
                WHERE id = ? AND section_id = ?
            ");
            mysqli_stmt_bind_param($check_stmt, "ii", $student_id, $selected_section_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            $valid_student = mysqli_fetch_assoc($check_result);
            mysqli_stmt_close($check_stmt);

            if (!$valid_student) {
                throw new Exception("Invalid student selected.");
            }

            if ($value === "") {
                $delete_stmt = mysqli_prepare($conn, "
                    DELETE FROM marks
                    WHERE student_id = ? AND subject_id = ?
                ");
                mysqli_stmt_bind_param($delete_stmt, "ii", $student_id, $selected_subject_id);
                mysqli_stmt_execute($delete_stmt);
                mysqli_stmt_close($delete_stmt);
                continue;
            }

            if (!is_numeric($value)) {
                throw new Exception("Marks must contain numbers only.");
            }

            $mark_value = (float)$value;
            $max_marks = (float)$subject["max_marks"];

            if ($mark_value < 0 || $mark_value > $max_marks) {
                throw new Exception(
                    "Marks must be between 0 and " . $subject["max_marks"] . "."
                );
            }

            $upsert_stmt = mysqli_prepare($conn, "
                INSERT INTO marks (student_id, subject_id, marks)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE marks = VALUES(marks)
            ");
            mysqli_stmt_bind_param(
                $upsert_stmt,
                "iid",
                $student_id,
                $selected_subject_id,
                $mark_value
            );
            mysqli_stmt_execute($upsert_stmt);
            mysqli_stmt_close($upsert_stmt);
        }

        mysqli_commit($conn);

        header(
            "Location: marks.php?section_id=" .
            $selected_section_id .
            "&subject_id=" .
            $selected_subject_id .
            "&saved=1"
        );
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

if (isset($_GET["saved"])) {
    $message = "Marks saved successfully for the selected subject.";
}

/* Load students for selected section and their existing marks */
$students = [];

if ($section && $subject) {
    $stmt = mysqli_prepare($conn, "
        SELECT
            s.id,
            s.roll_no,
            s.name,
            s.email,
            m.marks
        FROM students s
        LEFT JOIN marks m
            ON m.student_id = s.id
           AND m.subject_id = ?
        WHERE s.section_id = ?
        ORDER BY s.roll_no ASC, s.name ASC
    ");
    mysqli_stmt_bind_param($stmt, "ii", $selected_subject_id, $selected_section_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$entered_count = 0;
$total_obtained = 0;

foreach ($students as $student) {
    if ($student["marks"] !== null) {
        $entered_count++;
        $total_obtained += (float)$student["marks"];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marks Management | Student Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    * {
        box-sizing: border-box
    }

    .marks-page {
        min-height: 100vh;
        background: #f5f7fb
    }

    .marks-page .dashboard-layout {
        width: 100%;
        min-width: 0;
        min-height: 100vh
    }

    .marks-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box
    }

    .marks-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        padding: 30px;
        box-sizing: border-box
    }

    .marks-hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #4338ca 0%, #6366f1 52%, #7c3aed 100%);
        color: #fff;
        border-radius: 24px;
        padding: 32px 34px;
        margin-bottom: 24px;
        box-shadow: 0 18px 45px rgba(67, 56, 202, .18)
    }

    .marks-hero:after {
        content: "";
        position: absolute;
        width: 180px;
        height: 180px;
        right: -55px;
        top: -75px;
        border-radius: 50%;
        background: rgba(255, 255, 255, .1);
        pointer-events: none
    }

    .marks-hero h1 {
        position: relative;
        z-index: 1;
        margin: 0 0 8px;
        font-size: 32px
    }

    .marks-hero p {
        position: relative;
        z-index: 1;
        margin: 0;
        color: rgba(255, 255, 255, .86);
        font-size: 14px;
        line-height: 1.6
    }

    .marks-card {
        background: #fff;
        border: 1px solid #e7eaf0;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 24px;
        box-shadow: 0 8px 25px rgba(20, 30, 50, .05)
    }

    .marks-card h2 {
        margin: 0 0 18px;
        color: #182033;
        font-size: 18px
    }

    .selection-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 15px;
        align-items: end
    }

    .form-group label {
        display: block;
        color: #374151;
        font-weight: 700;
        font-size: 12px;
        margin-bottom: 7px
    }

    .form-group select {
        width: 100%;
        height: 47px;
        padding: 0 14px;
        border: 1px solid #d8dde7;
        border-radius: 11px;
        font-size: 13px;
        background: #fff;
        color: #20283a;
        outline: none
    }

    .form-group select:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .1)
    }

    .btn-primary,
    .btn-secondary {
        min-height: 44px;
        border-radius: 11px;
        padding: 11px 18px;
        cursor: pointer;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: .2s ease
    }

    .btn-primary {
        border: 0;
        background: #4f46e5;
        color: #fff;
        box-shadow: 0 7px 18px rgba(79, 70, 229, .16)
    }

    .btn-primary:hover {
        background: #4338ca;
        transform: translateY(-1px)
    }

    .btn-secondary {
        border: 1px solid #d8dde7;
        background: #fff;
        color: #334155
    }

    .btn-secondary:hover {
        background: #f8fafc;
        border-color: #cbd5e1
    }

    .alert {
        display: flex;
        align-items: flex-start;
        gap: 9px;
        border-radius: 12px;
        padding: 14px 17px;
        margin-bottom: 20px;
        font-size: 12px;
        line-height: 1.5;
        font-weight: 600
    }

    .alert-success {
        background: #ecfdf5;
        color: #047857;
        border: 1px solid #bbf7d0
    }

    .alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca
    }

    .section-info {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px
    }

    .info-box {
        min-width: 0;
        background: #f7f9fc;
        border: 1px solid #edf0f5;
        border-radius: 13px;
        padding: 14px
    }

    .info-box small {
        display: block;
        color: #697386;
        font-size: 10px;
        font-weight: 600;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: .3px
    }

    .info-box strong {
        display: block;
        color: #273044;
        font-size: 13px;
        overflow-wrap: anywhere
    }

    .subject-header {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: center;
        margin-bottom: 20px
    }

    .subject-header h2 {
        margin: 0 0 5px
    }

    .subject-header p {
        margin: 0;
        color: #7b8495;
        font-size: 12px;
        line-height: 1.6
    }

    .subject-badge {
        flex: 0 0 auto;
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #dbe4ff;
        border-radius: 11px;
        padding: 10px 14px;
        font-size: 12px;
        font-weight: 800
    }

    .marks-table-wrap {
        width: 100%;
        overflow-x: auto;
        border: 1px solid #edf0f5;
        border-radius: 14px
    }

    .marks-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 760px
    }

    .marks-table th,
    .marks-table td {
        padding: 14px 15px;
        border-bottom: 1px solid #edf0f4;
        text-align: left;
        vertical-align: middle
    }

    .marks-table th {
        background: #f8fafc;
        color: #64748b;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .5px;
        white-space: nowrap
    }

    .marks-table tbody tr:last-child td {
        border-bottom: 0
    }

    .marks-table tbody tr:hover {
        background: #fafbff
    }

    .marks-input {
        width: 130px;
        height: 43px;
        padding: 0 12px;
        border: 1px solid #d8dde7;
        border-radius: 9px;
        font-size: 14px;
        outline: none
    }

    .marks-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, .1)
    }

    .save-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-top: 20px
    }

    .save-actions {
        display: flex;
        align-items: center;
        gap: 10px
    }

    .summary-box {
        background: #f7f9fc;
        border: 1px solid #edf0f5;
        border-radius: 14px;
        padding: 13px 16px;
        color: #334155;
        font-size: 12px;
        line-height: 1.6
    }

    .summary-box strong {
        color: #182033
    }

    .summary-box small {
        color: #7b8495
    }

    .empty-state {
        text-align: center;
        padding: 45px 20px;
        color: #697386
    }

    .empty-state h3 {
        margin: 0 0 7px;
        color: #273044;
        font-size: 16px
    }

    .empty-state p {
        margin: 0 0 17px;
        font-size: 12px;
        line-height: 1.6
    }

    .marks-page .sidebar-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 24px 20px 18px;
        color: #fff
    }

    .marks-page .sidebar-logo-icon {
        width: 48px;
        height: 48px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: rgba(255, 255, 255, .16);
        font-size: 21px
    }

    .marks-page .sidebar-logo h2 {
        margin: 0;
        font-size: 17px;
        line-height: 1.1
    }

    .marks-page .sidebar-logo span {
        display: block;
        margin-top: 4px;
        color: rgba(255, 255, 255, .68);
        font-size: 11px
    }

    .marks-page .sidebar-menu {
        padding: 12px
    }

    .marks-page .sidebar-menu a,
    .marks-page .sidebar-bottom a {
        display: flex;
        align-items: center;
        gap: 13px;
        min-height: 46px;
        padding: 0 14px;
        margin-bottom: 5px;
        border-radius: 11px;
        color: rgba(255, 255, 255, .76);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: .2s ease
    }

    .marks-page .sidebar-menu a i,
    .marks-page .sidebar-bottom a i {
        width: 18px;
        text-align: center;
        font-size: 14px
    }

    .marks-page .sidebar-menu a:hover,
    .marks-page .sidebar-bottom a:hover {
        background: rgba(255, 255, 255, .1);
        color: #fff
    }

    .marks-page .sidebar-menu a.active {
        background: rgba(255, 255, 255, .16);
        color: #fff;
        box-shadow: inset 3px 0 0 #fff
    }

    .marks-page .sidebar-bottom {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        padding: 12px;
        border-top: 1px solid rgba(255, 255, 255, .1)
    }

    @media(max-width:1100px) {
        .section-info {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .selection-grid {
            grid-template-columns: 1fr 1fr
        }

        .selection-grid .btn-primary {
            width: 100%
        }
    }

    @media(max-width:900px) {
        .marks-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px
        }

        .marks-page .sidebar {
            width: 260px
        }
    }

    @media(max-width:650px) {
        .marks-page .dashboard-main {
            padding: 15px
        }

        .marks-hero {
            padding: 25px 20px;
            border-radius: 20px
        }

        .marks-hero h1 {
            font-size: 25px
        }

        .marks-card {
            padding: 18px;
            border-radius: 17px
        }

        .selection-grid,
        .section-info {
            grid-template-columns: 1fr
        }

        .subject-header,
        .save-row {
            flex-direction: column;
            align-items: stretch
        }

        .subject-badge {
            text-align: center
        }

        .save-actions {
            flex-direction: column;
            width: 100%
        }

        .save-actions .btn-primary,
        .save-actions .btn-secondary {
            width: 100%
        }
    }
    </style>
</head>

<body class="marks-page">
    <div class="dashboard-layout">

        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon"><i class="fas fa-graduation-cap"></i></div>
                <div>
                    <h2>Student MS</h2><span><?= $role === "admin" ? "Management System" : "Teacher Portal" ?></span>
                </div>
            </div>
            <nav class="sidebar-menu">
                <?php if ($role === "admin"): ?>
                <a href="admin_dashboard.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
                <a href="departments.php"><i class="fas fa-building-columns"></i><span>Departments</span></a>
                <a href="teachers.php"><i class="fas fa-chalkboard-user"></i><span>Teachers</span></a>
                <a href="courses.php"><i class="fas fa-book"></i><span>Courses</span></a>
                <a href="sections.php"><i class="fas fa-layer-group"></i><span>Sections</span></a>
                <a href="students.php"><i class="fas fa-user-graduate"></i><span>Students</span></a>
                <a href="subjects.php"><i class="fas fa-book-open"></i><span>Subjects</span></a>
                <a href="marks.php" class="active"><i class="fas fa-pen-to-square"></i><span>Marks &amp;
                        Results</span></a>
                <?php else: ?>
                <a href="teacher_dashboard.php"><i class="fas fa-house"></i><span>Dashboard</span></a>
                <a href="courses.php"><i class="fas fa-book"></i><span>My Courses</span></a>
                <a href="sections.php"><i class="fas fa-layer-group"></i><span>My Sections</span></a>
                <a href="students.php"><i class="fas fa-user-graduate"></i><span>My Students</span></a>
                <a href="subjects.php"><i class="fas fa-book-open"></i><span>Subjects</span></a>
                <a href="marks.php" class="active"><i class="fas fa-pen-to-square"></i><span>Marks</span></a>
                <a href="result.php"><i class="fas fa-chart-column"></i><span>Results</span></a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-bottom"><a href="logout.php"><i
                        class="fas fa-right-from-bracket"></i><span>Logout</span></a></div>
        </aside>

        <main class="dashboard-main">

            <section class="marks-hero">
                <h1>Marks Management</h1>
                <p>Select a section and subject, then record marks for all students.</p>
            </section>

            <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <section class="marks-card">
                <h2>1. Select Section and Subject</h2>

                <?php if (empty($sections)): ?>
                <div class="empty-state">
                    <h3>No sections available</h3>
                    <p>Create a section and add students before entering marks.</p>
                    <a href="sections.php" class="btn-primary">Go to Sections</a>
                </div>
                <?php else: ?>
                <form method="GET">
                    <div class="selection-grid">

                        <div class="form-group">
                            <label for="section_id">Section</label>
                            <select name="section_id" id="section_id" onchange="this.form.submit()" required>
                                <option value="">-- Select Section --</option>
                                <?php foreach ($sections as $s): ?>
                                <option value="<?= (int)$s["id"] ?>"
                                    <?= $selected_section_id == $s["id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars(
                                    $s["course_code"] . " - " .
                                    $s["course_name"] . " | Section " .
                                    $s["name"] . " | Semester " .
                                    $s["semester"] . " | " .
                                    $s["academic_year"]
                                ) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="subject_id">Subject</label>
                            <select name="subject_id" id="subject_id" required <?= !$section ? "disabled" : "" ?>>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($subjects as $sub): ?>
                                <option value="<?= (int)$sub["id"] ?>"
                                    <?= $selected_subject_id == $sub["id"] ? "selected" : "" ?>>
                                    <?= htmlspecialchars(
                                    $sub["code"] . " - " . $sub["name"] .
                                    " (Max " . $sub["max_marks"] . ")"
                                ) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary">
                            Load Students
                        </button>

                    </div>
                </form>
                <?php endif; ?>
            </section>

            <?php if ($section): ?>
            <section class="marks-card">
                <h2>Section Information</h2>

                <div class="section-info">
                    <div class="info-box">
                        <small>Course</small><strong><?= htmlspecialchars($section["course_name"]) ?></strong></div>
                    <div class="info-box"><small>Course
                            Code</small><strong><?= htmlspecialchars($section["course_code"]) ?></strong></div>
                    <div class="info-box">
                        <small>Section</small><strong><?= htmlspecialchars($section["name"]) ?></strong></div>
                    <div class="info-box"><small>Semester</small><strong><?= (int)$section["semester"] ?></strong></div>
                    <div class="info-box"><small>Academic
                            Year</small><strong><?= htmlspecialchars($section["academic_year"]) ?></strong></div>
                    <div class="info-box">
                        <small>Department</small><strong><?= htmlspecialchars($section["department_name"]) ?></strong>
                    </div>
                    <div class="info-box"><small>Students</small><strong><?= count($students) ?></strong></div>
                    <?php if ($subject): ?>
                    <div class="info-box"><small>Marks Entered</small><strong><?= $entered_count ?> /
                            <?= count($students) ?></strong></div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($section && !$subject): ?>
            <section class="marks-card">
                <div class="empty-state">
                    <?php if (empty($subjects)): ?>
                    <h3>No subjects found</h3>
                    <p>No subjects exist for this course and semester.</p>
                    <a href="subjects.php" class="btn-primary">Add Subjects</a>
                    <?php else: ?>
                    <h3>Select a subject</h3>
                    <p>Choose a subject above to display all students and record their marks.</p>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($section && $subject): ?>

            <section class="marks-card">
                <div class="subject-header">
                    <div>
                        <h2>2. Record Marks</h2>
                        <p>
                            Enter the marks scored by each student in
                            <strong><?= htmlspecialchars($subject["name"]) ?></strong>.
                        </p>
                    </div>
                    <div class="subject-badge">
                        Max Marks: <?= htmlspecialchars($subject["max_marks"]) ?>
                    </div>
                </div>

                <?php if (empty($students)): ?>

                <div class="empty-state">
                    <h3>No students in this section</h3>
                    <p>Add students to this section before recording marks.</p>
                    <a href="add_student.php" class="btn-primary">Add Student</a>
                </div>

                <?php else: ?>

                <form method="POST">
                    <input type="hidden" name="section_id" value="<?= (int)$selected_section_id ?>">
                    <input type="hidden" name="subject_id" value="<?= (int)$selected_subject_id ?>">

                    <div class="marks-table-wrap">
                        <table class="marks-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Roll No.</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Marks / <?= htmlspecialchars($subject["max_marks"]) ?></th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($student["roll_no"]) ?></strong></td>
                                    <td><?= htmlspecialchars($student["name"]) ?></td>
                                    <td><?= htmlspecialchars($student["email"]) ?></td>
                                    <td>
                                        <input type="number" name="marks[<?= (int)$student["id"] ?>]"
                                            class="marks-input" min="0"
                                            max="<?= htmlspecialchars($subject["max_marks"]) ?>" step="0.01"
                                            placeholder="Enter marks"
                                            value="<?= $student["marks"] !== null ? htmlspecialchars($student["marks"]) : "" ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="save-row">
                        <div class="summary-box">
                            <strong><?= $entered_count ?> of <?= count($students) ?> marks entered</strong>
                            <?php if ($entered_count > 0): ?>
                            <br>
                            <small>
                                Total entered:
                                <?= number_format($total_obtained, 2) ?>
                            </small>
                            <?php endif; ?>
                            <br>
                            <small>Leave a field blank to remove that student's mark.</small>
                        </div>

                        <div class="save-actions">
                            <a href="marks.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Change
                                Selection</a>
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save All
                                Marks</button>
                        </div>
                    </div>
                </form>

                <?php endif; ?>
            </section>

            <?php endif; ?>

        </main>
    </div>

    <script>
    const sectionSelect = document.getElementById("section_id");
    const subjectSelect = document.getElementById("subject_id");

    if (sectionSelect && subjectSelect) {
        sectionSelect.addEventListener("change", function() {
            subjectSelect.value = "";
        });
    }
    </script>

</body>

</html>