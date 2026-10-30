<?php
require_once "auth.php";
require_once "db.php";

$user_id   = (int) $_SESSION["user_id"];
$role      = $_SESSION["role"];
$user_name = $_SESSION["user_name"];

$message = "";
$error   = "";

if (isset($_GET["deleted"]) && $_GET["deleted"] === "1") {
    $message = "Section deleted successfully.";
}

if (isset($_GET["delete_error"]) && $_GET["delete_error"] === "1") {
    $error = "The section could not be deleted or you are not allowed to delete it.";
}

/* -------------------------------------------------------
   DELETE SECTION
------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_section"])) {

    $section_id = (int) ($_POST["section_id"] ?? 0);

    if ($section_id <= 0) {
        $error = "Invalid section selected.";
    } else {

        // Check student records before deletion because students.section_id
        // is configured with ON DELETE CASCADE in the current schema.
        $student_check = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total FROM students WHERE section_id = ?"
        );
        mysqli_stmt_bind_param($student_check, "i", $section_id);
        mysqli_stmt_execute($student_check);
        $student_result = mysqli_stmt_get_result($student_check);
        $student_row = mysqli_fetch_assoc($student_result);
        $section_student_count = (int) ($student_row["total"] ?? 0);
        mysqli_stmt_close($student_check);

        if ($section_student_count > 0) {
            $error = "This section cannot be deleted because it contains "
                   . $section_student_count
                   . " student(s). Remove or reassign the students first.";
        } else {

            if ($role === "admin") {
                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM sections WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, "i", $section_id);
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "DELETE FROM sections
                     WHERE id = ? AND teacher_id = ?"
                );
                mysqli_stmt_bind_param($stmt, "ii", $section_id, $user_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $message = "Section deleted successfully.";
                } else {
                    $error = "You are not allowed to delete this section.";
                }
            } else {
                $error = "Unable to delete the section.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}

/* -------------------------------------------------------
   ADD SECTION - TEACHERS ONLY
------------------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_section"])) {

    if ($role !== "teacher") {
        $error = "Only teachers can create sections.";
    } else {

        $course_id    = (int) ($_POST["course_id"] ?? 0);
        $section_name = trim($_POST["section_name"] ?? "");
        $semester     = (int) ($_POST["semester"] ?? 0);
        $academic_year = trim($_POST["academic_year"] ?? "");

        if ($course_id <= 0 || $section_name === "" || $semester <= 0 || $academic_year === "") {
            $error = "Please fill in all section details.";
        } elseif (strlen($section_name) > 50) {
            $error = "Section name is too long.";
        } elseif ($semester < 1 || $semester > 12) {
            $error = "Please enter a valid semester.";
        } elseif (strlen($academic_year) > 20) {
            $error = "Academic year is too long.";
        } else {

            /* Make sure the selected course belongs to this teacher's department. */
            $check = mysqli_prepare(
                $conn,
                "SELECT c.id
                 FROM courses c
                 WHERE c.id = ?
                   AND c.created_by = ?"
            );
            mysqli_stmt_bind_param($check, "ii", $course_id, $user_id);
            mysqli_stmt_execute($check);
            $check_result = mysqli_stmt_get_result($check);
            $course_exists = mysqli_num_rows($check_result) > 0;
            mysqli_stmt_close($check);

            if (!$course_exists) {
                $error = "You can only create sections for courses in your department.";
            } else {

                /* Prevent duplicate section for same course/semester/year. */
                $duplicate = mysqli_prepare(
                    $conn,
                    "SELECT id
                     FROM sections
                     WHERE course_id = ?
                       AND name = ?
                       AND semester = ?
                       AND academic_year = ?"
                );
                mysqli_stmt_bind_param(
                    $duplicate,
                    "isis",
                    $course_id,
                    $section_name,
                    $semester,
                    $academic_year
                );
                mysqli_stmt_execute($duplicate);
                $duplicate_result = mysqli_stmt_get_result($duplicate);
                $already_exists = mysqli_num_rows($duplicate_result) > 0;
                mysqli_stmt_close($duplicate);

                if ($already_exists) {
                    $error = "This section already exists for the selected course, semester and academic year.";
                } else {

                    $insert = mysqli_prepare(
                        $conn,
                        "INSERT INTO sections
                        (course_id, teacher_id, name, semester, academic_year)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $insert,
                        "iisis",
                        $course_id,
                        $user_id,
                        $section_name,
                        $semester,
                        $academic_year
                    );

                    if (mysqli_stmt_execute($insert)) {
                        $message = "Section created successfully.";
                    } else {
                        $error = "Unable to create section. Please try again.";
                    }

                    mysqli_stmt_close($insert);
                }
            }
        }
    }
}

/* -------------------------------------------------------
   GET TEACHER DEPARTMENT
------------------------------------------------------- */
$teacher_department_id = 0;

if ($role === "teacher") {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT department_id
         FROM users
         WHERE id = ? AND role = 'teacher'
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $teacher_data = mysqli_fetch_assoc($result);
    $teacher_department_id = (int) ($teacher_data["department_id"] ?? 0);
    mysqli_stmt_close($stmt);
}

/* -------------------------------------------------------
   COURSES FOR ADD SECTION FORM
------------------------------------------------------- */
$courses_for_form = [];

if ($role === "teacher" && $teacher_department_id > 0) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, name, code
         FROM courses
         WHERE department_id = ?
           AND created_by = ?
         ORDER BY name ASC"
    );
    mysqli_stmt_bind_param($stmt, "ii", $teacher_department_id, $user_id);
    mysqli_stmt_execute($stmt);
    $course_result = mysqli_stmt_get_result($stmt);

    while ($course = mysqli_fetch_assoc($course_result)) {
        $courses_for_form[] = $course;
    }

    mysqli_stmt_close($stmt);
}

/* -------------------------------------------------------
   SECTION LIST
------------------------------------------------------- */
if ($role === "admin") {

    $section_query = "
        SELECT
            s.id,
            s.name,
            s.semester,
            s.academic_year,
            s.created_at,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code,
            u.name AS teacher_name,
            (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id) AS student_count
        FROM sections s
        INNER JOIN courses c ON s.course_id = c.id
        INNER JOIN departments d ON c.department_id = d.id
        INNER JOIN users u ON s.teacher_id = u.id
        ORDER BY s.id DESC
    ";

    $sections_result = mysqli_query($conn, $section_query);

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            s.id,
            s.name,
            s.semester,
            s.academic_year,
            s.created_at,
            c.name AS course_name,
            c.code AS course_code,
            d.name AS department_name,
            d.code AS department_code,
            u.name AS teacher_name,
            (SELECT COUNT(*) FROM students st WHERE st.section_id = s.id) AS student_count
         FROM sections s
         INNER JOIN courses c ON s.course_id = c.id
         INNER JOIN departments d ON c.department_id = d.id
         INNER JOIN users u ON s.teacher_id = u.id
         WHERE s.teacher_id = ?
         ORDER BY s.id DESC"
    );

    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $sections_result = mysqli_stmt_get_result($stmt);
}

/* -------------------------------------------------------
   STATISTICS
------------------------------------------------------- */
$total_sections = 0;
$total_students = 0;
$total_courses  = 0;

if ($role === "admin") {

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM sections");
    $total_sections = (int) mysqli_fetch_assoc($result)["total"];

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM students");
    $total_students = (int) mysqli_fetch_assoc($result)["total"];

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM courses");
    $total_courses = (int) mysqli_fetch_assoc($result)["total"];

} else {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM sections
         WHERE teacher_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_sections = (int) mysqli_fetch_assoc($result)["total"];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM students s
         INNER JOIN sections sec ON s.section_id = sec.id
         WHERE sec.teacher_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_students = (int) mysqli_fetch_assoc($result)["total"];
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM courses c
         INNER JOIN users u ON c.department_id = u.department_id
         WHERE u.id = ?"
    );
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $total_courses = (int) mysqli_fetch_assoc($result)["total"];
    mysqli_stmt_close($stmt);
}

if ($role === "teacher" && $teacher_department_id > 0) {
    $dept_stmt = mysqli_prepare(
        $conn,
        "SELECT name, code FROM departments WHERE id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($dept_stmt, "i", $teacher_department_id);
    mysqli_stmt_execute($dept_stmt);
    $dept_result = mysqli_stmt_get_result($dept_stmt);
    $department = mysqli_fetch_assoc($dept_result);
    mysqli_stmt_close($dept_stmt);
} else {
    $department = null;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections | Student Management System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: "Inter", sans-serif;
        background: #f5f7fb;
        color: #172033;
    }

    a {
        text-decoration: none;
    }

    .sections-page {
        min-height: 100vh;
    }

    /* SIDEBAR */
    .sidebar {
        position: fixed;
        left: 0;
        top: 0;
        width: 260px;
        height: 100vh;
        background: linear-gradient(180deg, #172554 0%, #1e3a8a 55%, #2563eb 100%);
        color: #fff;
        padding: 24px 16px;
        z-index: 1000;
        overflow-y: auto;
        box-shadow: 5px 0 25px rgba(15, 23, 42, .12);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 10px 28px;
        color: #fff;
    }

    .brand-icon {
        width: 45px;
        height: 45px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, .15);
        font-size: 21px;
    }

    .brand-text strong {
        display: block;
        font-size: 15px;
        font-weight: 800;
    }

    .brand-text span {
        display: block;
        margin-top: 3px;
        font-size: 11px;
        opacity: .72;
    }

    .nav-title {
        color: rgba(255, 255, 255, .55);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        font-weight: 700;
        margin: 8px 12px 10px;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 12px;
        color: rgba(255, 255, 255, .82);
        padding: 12px 13px;
        border-radius: 11px;
        margin-bottom: 5px;
        font-size: 13px;
        font-weight: 600;
        transition: .2s ease;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 14px;
    }

    .nav-link:hover,
    .nav-link.active {
        background: rgba(255, 255, 255, .15);
        color: #fff;
        transform: translateX(2px);
    }

    .nav-link.active {
        box-shadow: inset 3px 0 #fff;
    }

    .logout-link {
        margin-top: 18px;
        color: #fecaca;
    }

    /* MAIN */
    .main {
        margin-left: 260px;
        min-height: 100vh;
        padding: 28px 34px 45px;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-bottom: 24px;
    }

    .page-heading small {
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
    }

    .page-heading h1 {
        margin-top: 5px;
        font-size: 27px;
        line-height: 1.2;
        color: #111827;
    }

    .user-box {
        display: flex;
        align-items: center;
        gap: 11px;
        background: #fff;
        border: 1px solid #e8edf5;
        padding: 8px 13px 8px 8px;
        border-radius: 14px;
        box-shadow: 0 5px 20px rgba(15, 23, 42, .05);
    }

    .avatar {
        width: 39px;
        height: 39px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #fff;
        font-weight: 800;
    }

    .user-box strong {
        display: block;
        font-size: 12px;
    }

    .user-box span {
        color: #64748b;
        font-size: 10px;
        text-transform: capitalize;
    }

    /* HERO */
    .hero {
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #1d4ed8, #4338ca 60%, #7c3aed);
        border-radius: 22px;
        padding: 30px 32px;
        color: #fff;
        margin-bottom: 22px;
        box-shadow: 0 18px 45px rgba(37, 99, 235, .20);
    }

    .hero::before,
    .hero::after {
        content: "";
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, .08);
    }

    .hero::before {
        width: 210px;
        height: 210px;
        right: 8%;
        top: -120px;
    }

    .hero::after {
        width: 140px;
        height: 140px;
        right: -35px;
        bottom: -70px;
    }

    .hero-content {
        position: relative;
        z-index: 2;
        max-width: 760px;
    }

    .hero-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 30px;
        padding: 7px 11px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .8px;
        margin-bottom: 13px;
    }

    .hero h2 {
        font-size: 28px;
        margin-bottom: 9px;
    }

    .hero p {
        max-width: 680px;
        color: rgba(255, 255, 255, .82);
        font-size: 13px;
        line-height: 1.7;
    }

    .department-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 18px;
        padding: 8px 12px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .13);
        font-size: 11px;
        font-weight: 600;
    }

    /* STATS */
    .stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 17px;
        margin-bottom: 22px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #e8edf5;
        border-radius: 17px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 7px 25px rgba(15, 23, 42, .045);
    }

    .stat-icon {
        width: 47px;
        height: 47px;
        flex: 0 0 47px;
        border-radius: 13px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb;
        font-size: 18px;
    }

    .stat-card:nth-child(2) .stat-icon {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .stat-card:nth-child(3) .stat-icon {
        background: #ecfdf5;
        color: #059669;
    }

    .stat-card span {
        display: block;
        color: #64748b;
        font-size: 11px;
        font-weight: 600;
    }

    .stat-card strong {
        display: block;
        color: #111827;
        font-size: 23px;
        margin-top: 3px;
    }

    /* CONTENT GRID */
    .content-grid {
        display: grid;
        grid-template-columns: 350px minmax(0, 1fr);
        gap: 21px;
        align-items: start;
    }

    .card {
        background: #fff;
        border: 1px solid #e8edf5;
        border-radius: 18px;
        box-shadow: 0 7px 25px rgba(15, 23, 42, .045);
    }

    .card-header {
        padding: 20px 21px 15px;
        border-bottom: 1px solid #eef2f7;
    }

    .card-header h3 {
        font-size: 15px;
        color: #111827;
    }

    .card-header p {
        margin-top: 5px;
        color: #64748b;
        font-size: 11px;
        line-height: 1.5;
    }

    /* FORM */
    .form-body {
        padding: 20px 21px 22px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-group label {
        display: block;
        margin-bottom: 7px;
        color: #334155;
        font-size: 11px;
        font-weight: 700;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 12px;
        pointer-events: none;
    }

    .form-control,
    select {
        width: 100%;
        height: 43px;
        border: 1px solid #dbe3ee;
        border-radius: 10px;
        padding: 0 12px;
        background: #fff;
        color: #1e293b;
        outline: none;
        font-family: inherit;
        font-size: 12px;
        transition: .2s;
    }

    .input-wrap .form-control {
        padding-left: 35px;
    }

    .form-control:focus,
    select:focus {
        border-color: #4f46e5;
        box-shadow: 0 0 0 3px rgba(79, 70, 229, .10);
    }

    .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 11px;
    }

    .btn-primary {
        width: 100%;
        height: 44px;
        border: 0;
        border-radius: 11px;
        background: linear-gradient(135deg, #2563eb, #4f46e5);
        color: #fff;
        font-family: inherit;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 8px 18px rgba(37, 99, 235, .20);
        transition: .2s;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 11px 23px rgba(37, 99, 235, .25);
    }

    .empty-form {
        padding: 24px 21px;
        text-align: center;
        color: #64748b;
        font-size: 12px;
        line-height: 1.6;
    }

    .empty-form i {
        font-size: 30px;
        color: #cbd5e1;
        margin-bottom: 10px;
    }

    /* ALERTS */
    .alert {
        border-radius: 12px;
        padding: 12px 14px;
        margin-bottom: 17px;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .alert-success {
        color: #166534;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
    }

    .alert-error {
        color: #991b1b;
        background: #fef2f2;
        border: 1px solid #fecaca;
    }

    /* SECTION LIST */
    .list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 20px 21px;
        border-bottom: 1px solid #eef2f7;
    }

    .list-title h3 {
        font-size: 15px;
        color: #111827;
    }

    .list-title p {
        color: #64748b;
        font-size: 11px;
        margin-top: 4px;
    }

    .count-badge {
        min-width: 32px;
        height: 28px;
        padding: 0 9px;
        border-radius: 9px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 800;
    }

    .section-list {
        padding: 8px 14px 15px;
    }

    .section-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 15px 8px;
        border-bottom: 1px solid #f0f3f8;
    }

    .section-item:last-child {
        border-bottom: 0;
    }

    .section-icon {
        width: 49px;
        height: 49px;
        flex: 0 0 49px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #dbeafe, #ede9fe);
        color: #4338ca;
        font-size: 17px;
        font-weight: 800;
    }

    .section-info {
        flex: 1 1 auto;
        min-width: 0;
    }

    .section-info h4 {
        color: #111827;
        font-size: 13px;
        font-weight: 800;
        white-space: normal;
        word-break: normal;
        overflow-wrap: break-word;
    }

    .course-code {
        display: inline-block;
        margin-left: 6px;
        color: #4f46e5;
        background: #eef2ff;
        border-radius: 5px;
        padding: 3px 6px;
        font-size: 9px;
        font-weight: 800;
        vertical-align: middle;
    }

    .section-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-top: 7px;
    }

    .meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #64748b;
        background: #f8fafc;
        border: 1px solid #edf1f6;
        border-radius: 7px;
        padding: 5px 7px;
        font-size: 9px;
        font-weight: 600;
    }

    .meta-chip i {
        color: #94a3b8;
    }

    .section-actions {
        display: flex;
        align-items: center;
        gap: 7px;
    }

    .delete-form {
        margin: 0;
    }

    .delete-btn {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #fecaca;
        border-radius: 12px;
        background: #fff5f5;
        color: #ef4444;
        text-decoration: none;
        cursor: pointer;
        font-size: 16px;
        transition: all .2s ease;
    }

    .delete-btn:hover {
        background: #ef4444;
        color: #ffffff;
        border-color: #ef4444;
        transform: translateY(-1px);
    }

    .delete-btn i {
        pointer-events: none;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        line-height: 1;
        margin: 0;
        padding: 0;
        position: relative;
        top: 1px;
    }

    .edit-btn {
        width: 35px;
        height: 35px;
        border: 1px solid #dbeafe;
        border-radius: 9px;
        background: #eff6ff;
        color: #2563eb;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .2s;
    }

    .edit-btn:hover {
        background: #dbeafe;
        transform: translateY(-1px);
    }

    .edit-btn i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        line-height: 1;
        margin: 0;
        padding: 0;
    }

    .admin-teacher {
        margin-top: 7px;
        color: #64748b;
        font-size: 10px;
    }

    .admin-teacher i {
        color: #8b5cf6;
        margin-right: 4px;
    }

    /* EMPTY STATE */
    .empty-state {
        padding: 55px 20px;
        text-align: center;
    }

    .empty-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 15px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 27px;
    }

    .empty-state h3 {
        color: #334155;
        font-size: 15px;
    }

    .empty-state p {
        max-width: 400px;
        margin: 7px auto 0;
        color: #94a3b8;
        font-size: 11px;
        line-height: 1.6;
    }

    /* ADMIN NOTE */
    .admin-note {
        margin: 0 21px 21px;
        border-radius: 11px;
        padding: 12px;
        background: #f8fafc;
        border: 1px solid #e9eef5;
        color: #64748b;
        font-size: 10px;
        line-height: 1.6;
    }

    .admin-note i {
        color: #4f46e5;
        margin-right: 5px;
    }

    /* MOBILE */
    @media (max-width: 1100px) {
        .content-grid {
            grid-template-columns: 310px minmax(0, 1fr);
        }

        .main {
            padding: 24px;
        }
    }

    @media (max-width: 900px) {
        .sidebar {
            position: relative;
            width: 100%;
            height: auto;
            min-height: auto;
            padding: 12px;
        }

        .brand {
            padding: 5px 8px 13px;
        }

        .nav-title {
            display: none;
        }

        .nav-link {
            display: inline-flex;
            width: auto;
            margin: 2px;
            padding: 9px 10px;
        }

        .nav-link.active {
            box-shadow: none;
        }

        .logout-link {
            margin-top: 0;
        }

        .main {
            margin-left: 0;
            padding: 22px 18px 40px;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .stats {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 650px) {
        .topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .user-box {
            width: 100%;
        }

        .hero {
            padding: 24px 21px;
            border-radius: 18px;
        }

        .hero h2 {
            font-size: 23px;
        }

        .stats {
            grid-template-columns: 1fr;
        }

        .section-item {
            align-items: flex-start;
        }

        .section-actions {
            align-self: center;
        }

        .section-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .course-code {
            margin-left: 3px;
        }
    }

    @media (max-width: 430px) {
        .main {
            padding: 17px 12px 30px;
        }

        .hero p {
            font-size: 11px;
        }

        .two-columns {
            grid-template-columns: 1fr;
        }

        .section-item {
            gap: 10px;
        }

        .section-icon {
            width: 43px;
            height: 43px;
            flex-basis: 43px;
        }

        .section-info h4 {
            font-size: 12px;
        }

        .section-actions {
            margin-left: auto;
        }

        .delete-btn {
            width: 38px;
            height: 38px;
            font-size: 14px;
        }
    }
    </style>
</head>

<body>
    <div class="sections-page">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <a href="<?= $role === 'admin' ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" class="brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="brand-text">
                    <strong>Student Management</strong>
                    <span><?= ucfirst($role) ?> Portal</span>
                </div>
            </a>

            <div class="nav-title">Main Menu</div>

            <?php if ($role === "admin"): ?>

            <a href="admin_dashboard.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="departments.php" class="nav-link">
                <i class="fa-solid fa-building-columns"></i>
                <span>Departments</span>
            </a>

            <a href="teachers.php" class="nav-link">
                <i class="fa-solid fa-chalkboard-user"></i>
                <span>Teachers</span>
            </a>

            <a href="courses.php" class="nav-link">
                <i class="fa-solid fa-book-open"></i>
                <span>Courses</span>
            </a>

            <a href="sections.php" class="nav-link active">
                <i class="fa-solid fa-layer-group"></i>
                <span>Sections</span>
            </a>

            <a href="students.php" class="nav-link">
                <i class="fa-solid fa-user-graduate"></i>
                <span>Students</span>
            </a>

            <a href="marks.php" class="nav-link">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Marks & Results</span>
            </a>

            <?php else: ?>

            <a href="teacher_dashboard.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>

            <a href="courses.php" class="nav-link">
                <i class="fa-solid fa-book-open"></i>
                <span>My Courses</span>
            </a>

            <a href="sections.php" class="nav-link active">
                <i class="fa-solid fa-layer-group"></i>
                <span>My Sections</span>
            </a>

            <a href="students.php" class="nav-link">
                <i class="fa-solid fa-user-graduate"></i>
                <span>My Students</span>
            </a>

            <a href="marks.php" class="nav-link">
                <i class="fa-solid fa-square-poll-vertical"></i>
                <span>Marks</span>
            </a>

            <a href="result.php" class="nav-link">
                <i class="fa-solid fa-award"></i>
                <span>Results</span>
            </a>

            <?php endif; ?>

            <a href="logout.php" class="nav-link logout-link">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>

        </aside>

        <!-- MAIN -->
        <main class="main">

            <div class="topbar">
                <div class="page-heading">
                    <small>Student Management System / Sections</small>
                    <h1><?= $role === "admin" ? "Section Management" : "My Sections" ?></h1>
                </div>

                <div class="user-box">
                    <div class="avatar">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($user_name) ?></strong>
                        <span><?= htmlspecialchars($role) ?></span>
                    </div>
                </div>
            </div>

            <?php if ($message !== ""): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <?php if ($error !== ""): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- HERO -->
            <section class="hero">
                <div class="hero-content">

                    <div class="hero-label">
                        <i class="fa-solid fa-layer-group"></i>
                        Academic Sections
                    </div>

                    <h2>
                        <?= $role === "admin"
                        ? "Manage College Sections"
                        : "Organize Your Classes" ?>
                    </h2>

                    <p>
                        <?= $role === "admin"
                        ? "View and manage sections across departments, courses and academic years from one central place."
                        : "Create and organize your course sections so students can be grouped correctly before adding student records and marks." ?>
                    </p>

                    <?php if ($role === "teacher" && $department): ?>
                    <div class="department-pill">
                        <i class="fa-solid fa-building-columns"></i>
                        <?= htmlspecialchars($department["name"]) ?>
                        (<?= htmlspecialchars($department["code"]) ?>)
                    </div>
                    <?php endif; ?>

                </div>
            </section>

            <!-- STATS -->
            <section class="stats">

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <div>
                        <span><?= $role === "admin" ? "Total Sections" : "My Sections" ?></span>
                        <strong><?= $total_sections ?></strong>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <div>
                        <span><?= $role === "admin" ? "Total Courses" : "Department Courses" ?></span>
                        <strong><?= $total_courses ?></strong>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div>
                        <span><?= $role === "admin" ? "All Students" : "My Students" ?></span>
                        <strong><?= $total_students ?></strong>
                    </div>
                </div>

            </section>

            <!-- CONTENT -->
            <section class="content-grid">

                <?php if ($role === "teacher"): ?>

                <!-- CREATE SECTION -->
                <div class="card">

                    <div class="card-header">
                        <h3><i class="fa-solid fa-plus"></i> Create New Section</h3>
                        <p>Add a section for one of the courses in your department.</p>
                    </div>

                    <?php if ($teacher_department_id <= 0): ?>

                    <div class="empty-form">
                        <i class="fa-solid fa-building-circle-exclamation"></i>
                        <div>
                            Your account is not connected to a department.
                            Please contact the administrator.
                        </div>
                    </div>

                    <?php elseif (count($courses_for_form) === 0): ?>

                    <div class="empty-form">
                        <i class="fa-solid fa-book-open"></i>
                        <div>
                            No courses are available in your department yet.
                            Create a course first.
                        </div>
                    </div>

                    <?php else: ?>

                    <form method="POST" class="form-body">

                        <div class="form-group">
                            <label for="course_id">Course</label>

                            <div class="input-wrap">
                                <i class="fa-solid fa-book"></i>

                                <select name="course_id" id="course_id" required>
                                    <option value="">Select course</option>

                                    <?php foreach ($courses_for_form as $course): ?>
                                    <option value="<?= (int) $course["id"] ?>">
                                        <?= htmlspecialchars($course["name"]) ?>
                                        — <?= htmlspecialchars($course["code"]) ?>
                                    </option>
                                    <?php endforeach; ?>

                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="section_name">Section Name</label>

                            <div class="input-wrap">
                                <i class="fa-solid fa-layer-group"></i>
                                <input type="text" id="section_name" name="section_name" class="form-control"
                                    placeholder="e.g. A" maxlength="50" required>
                            </div>
                        </div>

                        <div class="two-columns">

                            <div class="form-group">
                                <label for="semester">Semester</label>

                                <select name="semester" id="semester" required>
                                    <option value="">Select</option>

                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>">
                                        Semester <?= $i ?>
                                    </option>
                                    <?php endfor; ?>

                                </select>
                            </div>

                            <div class="form-group">
                                <label for="academic_year">Academic Year</label>

                                <div class="input-wrap">
                                    <i class="fa-solid fa-calendar"></i>
                                    <input type="text" id="academic_year" name="academic_year" class="form-control"
                                        placeholder="2026-27" maxlength="20" required>
                                </div>
                            </div>

                        </div>

                        <button type="submit" name="add_section" class="btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Create Section
                        </button>

                    </form>

                    <?php endif; ?>

                </div>

                <?php else: ?>

                <!-- ADMIN INFORMATION -->
                <div class="card">

                    <div class="card-header">
                        <h3><i class="fa-solid fa-shield-halved"></i> Administrator View</h3>
                        <p>College-wide section overview and management.</p>
                    </div>

                    <div class="empty-form">
                        <i class="fa-solid fa-layer-group"></i>
                        <div>
                            All sections created by teachers are displayed here.
                            Use the delete button when a section needs to be removed.
                        </div>
                    </div>

                    <div class="admin-note">
                        <i class="fa-solid fa-circle-info"></i>
                        Deleting a section can also remove its students because
                        <strong>students.section_id</strong> uses a cascading foreign key.
                        Delete sections carefully.
                    </div>

                </div>

                <?php endif; ?>

                <!-- SECTION LIST -->
                <div class="card">

                    <div class="list-header">
                        <div class="list-title">
                            <h3>
                                <?= $role === "admin"
                                ? "All Sections"
                                : "Your Sections" ?>
                            </h3>
                            <p>
                                <?= $role === "admin"
                                ? "Sections across all departments"
                                : "Sections assigned to your account" ?>
                            </p>
                        </div>

                        <div class="count-badge">
                            <?= $total_sections ?>
                        </div>
                    </div>

                    <div class="section-list">

                        <?php if ($sections_result && mysqli_num_rows($sections_result) > 0): ?>

                        <?php while ($section = mysqli_fetch_assoc($sections_result)): ?>

                        <div class="section-item">

                            <div class="section-icon">
                                <?= htmlspecialchars(strtoupper(substr($section["name"], 0, 2))) ?>
                            </div>

                            <div class="section-info">

                                <h4>
                                    Section <?= htmlspecialchars($section["name"]) ?>

                                    <span class="course-code">
                                        <?= htmlspecialchars($section["course_code"]) ?>
                                    </span>
                                </h4>

                                <div class="section-meta">

                                    <span class="meta-chip">
                                        <i class="fa-solid fa-book"></i>
                                        <?= htmlspecialchars($section["course_name"]) ?>
                                    </span>

                                    <span class="meta-chip">
                                        <i class="fa-solid fa-graduation-cap"></i>
                                        Semester <?= (int) $section["semester"] ?>
                                    </span>

                                    <span class="meta-chip">
                                        <i class="fa-solid fa-calendar-days"></i>
                                        <?= htmlspecialchars($section["academic_year"]) ?>
                                    </span>

                                    <span class="meta-chip">
                                        <i class="fa-solid fa-user-graduate"></i>
                                        <?= (int) $section["student_count"] ?> Students
                                    </span>

                                    <?php if ($role === "admin"): ?>
                                    <span class="meta-chip">
                                        <i class="fa-solid fa-building-columns"></i>
                                        <?= htmlspecialchars($section["department_code"]) ?>
                                    </span>
                                    <?php endif; ?>

                                </div>

                                <?php if ($role === "admin"): ?>
                                <div class="admin-teacher">
                                    <i class="fa-solid fa-chalkboard-user"></i>
                                    Teacher:
                                    <?= htmlspecialchars($section["teacher_name"]) ?>
                                </div>
                                <?php endif; ?>

                            </div>

                            <div class="section-actions">

                                <a href="edit_section.php?id=<?= (int) $section["id"] ?>" class="edit-btn"
                                    title="Edit section">
                                    <i class="fa-solid fa-pen"></i>
                                </a>

                                <a href="delete_section.php?id=<?= (int) $section["id"] ?>" class="delete-btn"
                                    title="Delete section">
                                    <i class="fa-solid fa-trash"></i>
                                </a>

                            </div>

                        </div>

                        <?php endwhile; ?>

                        <?php else: ?>

                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fa-solid fa-layer-group"></i>
                            </div>

                            <h3>No sections yet</h3>

                            <p>
                                <?= $role === "teacher"
                                    ? "Create your first section using the form. Once sections exist, students can be assigned to them."
                                    : "No sections have been created in the system yet." ?>
                            </p>
                        </div>

                        <?php endif; ?>

                    </div>

                </div>

            </section>

        </main>
    </div>

</body>

</html>