<?php
require_once "auth.php";
require_once "db.php";

if ($_SESSION["role"] !== "teacher") {
    header("Location: admin_dashboard.php");
    exit();
}

$teacher_id = (int)$_SESSION["user_id"];
$teacher_name = $_SESSION["user_name"];

function countForTeacher($conn, $sql, $teacher_id) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $teacher_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (int)$row["total"];
}

$department = null;
$stmt = mysqli_prepare($conn, "SELECT d.id,d.name,d.code FROM users u LEFT JOIN departments d ON u.department_id=d.id WHERE u.id=? AND u.role='teacher'");
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$department = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$total_courses = countForTeacher($conn, "SELECT COUNT(*) total FROM courses WHERE created_by=?", $teacher_id);
$total_sections = countForTeacher($conn, "SELECT COUNT(*) total FROM sections WHERE teacher_id=?", $teacher_id);
$total_students = countForTeacher($conn, "SELECT COUNT(*) total FROM students s INNER JOIN sections sec ON s.section_id=sec.id WHERE sec.teacher_id=?", $teacher_id);
$total_subjects = countForTeacher($conn, "SELECT COUNT(*) total FROM subjects sub INNER JOIN courses c ON sub.course_id=c.id WHERE c.created_by=?", $teacher_id);
$total_marks = countForTeacher($conn, "SELECT COUNT(*) total FROM marks m INNER JOIN students s ON m.student_id=s.id INNER JOIN sections sec ON s.section_id=sec.id WHERE sec.teacher_id=?", $teacher_id);

$courses=[];
$stmt=mysqli_prepare($conn,"SELECT c.id,c.name,c.code,c.duration_years,COUNT(DISTINCT sec.id) section_count,COUNT(DISTINCT sub.id) subject_count FROM courses c LEFT JOIN sections sec ON sec.course_id=c.id AND sec.teacher_id=? LEFT JOIN subjects sub ON sub.course_id=c.id WHERE c.created_by=? GROUP BY c.id,c.name,c.code,c.duration_years ORDER BY c.id DESC");
mysqli_stmt_bind_param($stmt,"ii",$teacher_id,$teacher_id);
mysqli_stmt_execute($stmt); $r=mysqli_stmt_get_result($stmt);
while($row=mysqli_fetch_assoc($r)) $courses[]=$row;
mysqli_stmt_close($stmt);

$sections=[];
$stmt=mysqli_prepare($conn,"SELECT sec.id,sec.name,sec.semester,sec.academic_year,c.name course_name,c.code course_code,COUNT(s.id) student_count FROM sections sec INNER JOIN courses c ON sec.course_id=c.id LEFT JOIN students s ON s.section_id=sec.id WHERE sec.teacher_id=? GROUP BY sec.id,sec.name,sec.semester,sec.academic_year,c.name,c.code ORDER BY sec.id DESC LIMIT 6");
mysqli_stmt_bind_param($stmt,"i",$teacher_id);
mysqli_stmt_execute($stmt); $r=mysqli_stmt_get_result($stmt);
while($row=mysqli_fetch_assoc($r)) $sections[]=$row;
mysqli_stmt_close($stmt);

$recent_students=[];
$stmt=mysqli_prepare($conn,"SELECT s.id,s.roll_no,s.name,c.name course_name,c.code course_code,sec.name section_name FROM students s INNER JOIN sections sec ON s.section_id=sec.id INNER JOIN courses c ON sec.course_id=c.id WHERE sec.teacher_id=? ORDER BY s.id DESC LIMIT 6");
mysqli_stmt_bind_param($stmt,"i",$teacher_id);
mysqli_stmt_execute($stmt); $r=mysqli_stmt_get_result($stmt);
while($row=mysqli_fetch_assoc($r)) $recent_students[]=$row;
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Teacher Dashboard | Student Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
    .teacher-dashboard-page {
        min-height: 100vh
    }

    .teacher-dashboard-page .dashboard-layout {
        width: 100%;
        min-width: 0
    }

    .teacher-dashboard-page .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        box-sizing: border-box
    }

    .teacher-dashboard-page .dashboard-main {
        margin-left: 280px !important;
        width: calc(100% - 280px) !important;
        max-width: none !important;
        min-width: 0;
        min-height: 100vh;
        box-sizing: border-box;
        padding: 30px
    }

    .teacher-hero {
        background: linear-gradient(135deg, #182848, #4b6cb7);
        color: white;
        border-radius: 24px;
        padding: 34px;
        margin-bottom: 24px
    }

    .teacher-hero h1 {
        margin: 0 0 8px;
        font-size: 32px
    }

    .teacher-hero p {
        margin: 0;
        opacity: .9
    }

    .department-badge {
        display: inline-block;
        margin-top: 18px;
        padding: 9px 14px;
        border-radius: 30px;
        background: rgba(255, 255, 255, .15);
        font-size: 14px
    }

    .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 24px
    }

    .stat-card,
    .quick-action,
    .dashboard-card {
        background: white;
        border: 1px solid #e7eaf0;
        border-radius: 18px;
        box-shadow: 0 8px 25px rgba(20, 30, 50, .05)
    }

    .stat-card {
        padding: 20px
    }

    .stat-card small {
        display: block;
        color: #697386;
        margin-bottom: 7px
    }

    .stat-card strong {
        font-size: 28px
    }

    .quick-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px
    }

    .quick-action {
        padding: 19px;
        text-decoration: none;
        color: #182848
    }

    .quick-action strong {
        display: block;
        margin-bottom: 5px
    }

    .quick-action span {
        color: #697386;
        font-size: 14px
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 1.15fr .85fr;
        gap: 24px
    }

    .dashboard-card {
        padding: 24px;
        margin-bottom: 24px
    }

    .dashboard-card h2 {
        margin: 0 0 18px
    }

    .course-list,
    .section-list,
    .student-list {
        display: grid;
        gap: 12px
    }

    .course-item,
    .section-item,
    .student-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        padding: 15px;
        background: #f7f9fc;
        border-radius: 13px
    }

    .item-title {
        font-weight: 700;
        margin-bottom: 4px
    }

    .item-meta {
        color: #697386;
        font-size: 13px
    }

    .item-link {
        text-decoration: none;
        color: #182848;
        font-weight: 600;
        white-space: nowrap
    }

    .empty-state {
        text-align: center;
        padding: 30px 15px;
        color: #697386
    }

    .btn-primary {
        display: inline-block;
        padding: 11px 17px;
        border-radius: 10px;
        background: #182848;
        color: white;
        text-decoration: none;
        font-weight: 600
    }

    .welcome-note {
        background: #f7f9fc;
        border-radius: 15px;
        padding: 18px;
        color: #4d5666
    }

    .profile-nav-link {
        font-weight: 700;
    }

    .profile-hero-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 9px 14px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .18);
        color: #fff;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
        transition: .2s ease;
    }

    .profile-hero-link:hover {
        background: rgba(255, 255, 255, .20);
        transform: translateY(-1px);
    }

    /* =====================================================
       TEACHER SIDEBAR
    ===================================================== */
    .teacher-dashboard-page .sidebar {
        display: flex;
        flex-direction: column;
        padding: 22px 14px 16px;
        background: #111827;
        color: #fff;
        box-shadow: 8px 0 28px rgba(15, 23, 42, .08);
    }

    .teacher-sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 2px 10px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, .08);
    }

    .teacher-sidebar-logo {
        width: 43px;
        height: 43px;
        display: grid;
        place-items: center;
        flex: 0 0 43px;
        border-radius: 13px;
        background: linear-gradient(135deg, #4f46e5, #6366f1);
        color: #fff;
        font-size: 19px;
    }

    .teacher-sidebar-brand h2 {
        margin: 0;
        color: #fff;
        font-size: 17px;
    }

    .teacher-sidebar-brand p {
        margin: 3px 0 0;
        color: #9ca3af;
        font-size: 11px;
    }

    .teacher-sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 5px;
        padding: 22px 0;
    }

    .teacher-sidebar-nav a,
    .teacher-sidebar-bottom a {
        display: flex;
        align-items: center;
        gap: 13px;
        min-height: 45px;
        padding: 0 13px;
        border-radius: 11px;
        color: #aeb6c5;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: .2s ease;
    }

    .teacher-sidebar-nav a i,
    .teacher-sidebar-bottom a i {
        width: 20px;
        text-align: center;
        font-size: 15px;
    }

    .teacher-sidebar-nav a:hover,
    .teacher-sidebar-bottom a:hover {
        background: rgba(255, 255, 255, .07);
        color: #fff;
        transform: translateX(2px);
    }

    .teacher-sidebar-nav a.active {
        background: linear-gradient(90deg, #4f46e5, #6366f1);
        color: #fff;
        box-shadow: 0 8px 18px rgba(79, 70, 229, .18);
    }

    .teacher-sidebar-bottom {
        margin-top: auto;
        padding-top: 14px;
        border-top: 1px solid rgba(255, 255, 255, .08);
    }

    .teacher-sidebar-bottom a {
        color: #fca5a5;
    }

    .teacher-sidebar-bottom a:hover {
        background: rgba(239, 68, 68, .10);
        color: #fecaca;
    }

    @media(max-width:1200px) {
        .dashboard-stats {
            grid-template-columns: repeat(3, 1fr)
        }

        .quick-actions {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:1000px) {
        .dashboard-grid {
            grid-template-columns: 1fr
        }
    }

    @media(max-width:900px) {
        .teacher-dashboard-page .dashboard-main {
            margin-left: 0 !important;
            width: 100% !important;
            padding: 20px
        }

        .teacher-dashboard-page .sidebar {
            width: 260px
        }

        .dashboard-stats {
            grid-template-columns: repeat(2, 1fr)
        }
    }

    @media(max-width:600px) {
        .teacher-dashboard-page .dashboard-main {
            padding: 15px
        }

        .teacher-hero {
            padding: 25px 20px
        }

        .teacher-hero h1 {
            font-size: 25px
        }

        .dashboard-stats,
        .quick-actions {
            grid-template-columns: 1fr
        }

        .dashboard-card {
            padding: 18px
        }

        .course-item,
        .section-item,
        .student-item {
            align-items: flex-start;
            flex-direction: column
        }
    }
    </style>
</head>

<body class="teacher-dashboard-page">
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="teacher-sidebar-brand">
                <div class="teacher-sidebar-logo">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <h2>Student MS</h2>
                    <p>Teacher Portal</p>
                </div>
            </div>

            <nav class="teacher-sidebar-nav">
                <a href="teacher_dashboard.php" class="active">
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
                <a href="teacher_profile.php">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
            </nav>

            <div class="teacher-sidebar-bottom">
                <a href="logout.php">
                    <i class="fas fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>

        </aside>
        <main class="dashboard-main">
            <section class="teacher-hero">
                <h1>Welcome, <?=htmlspecialchars($teacher_name)?></h1>
                <p>Manage your courses, sections, students, subjects and academic marks.</p>
                <span class="department-badge">Department:
                    <?=htmlspecialchars($department ? $department["name"]." (".$department["code"].")" : "Not assigned")?></span>
                <a href="teacher_profile.php" class="profile-hero-link">
                    <i class="fas fa-user-cog"></i>
                    Manage My Profile
                </a>
            </section>
            <section class="dashboard-stats">
                <div class="stat-card"><small>My Courses</small><strong><?=$total_courses?></strong></div>
                <div class="stat-card"><small>My Sections</small><strong><?=$total_sections?></strong></div>
                <div class="stat-card"><small>My Students</small><strong><?=$total_students?></strong></div>
                <div class="stat-card"><small>My Subjects</small><strong><?=$total_subjects?></strong></div>
                <div class="stat-card"><small>Marks Records</small><strong><?=$total_marks?></strong></div>
            </section>
            <section class="quick-actions">
                <a href="courses.php" class="quick-action"><strong>Manage Courses</strong><span>Create and manage your
                        courses.</span></a>
                <a href="sections.php" class="quick-action"><strong>Manage Sections</strong><span>Create and manage your
                        sections.</span></a>
                <a href="subjects.php" class="quick-action"><strong>Add Subjects</strong><span>Add subjects and maximum
                        marks.</span></a>
                <a href="marks.php" class="quick-action"><strong>Enter Marks</strong><span>Record marks for students by
                        subject.</span></a>
            </section>
            <div class="dashboard-grid">
                <div>
                    <section class="dashboard-card">
                        <h2>My Courses</h2>
                        <?php if(!$courses):?><div class="empty-state">
                            <p>You have not created any courses yet.</p><a href="courses.php" class="btn-primary">Create
                                Course</a>
                        </div>
                        <?php else:?><div class="course-list"><?php foreach($courses as $c):?><div class="course-item">
                                <div>
                                    <div class="item-title"><?=htmlspecialchars($c["name"])?></div>
                                    <div class="item-meta"><?=htmlspecialchars($c["code"])?> · <?=$c["duration_years"]?>
                                        years · <?=$c["subject_count"]?> subjects · <?=$c["section_count"]?> sections
                                    </div>
                                </div><a href="courses.php" class="item-link">Manage</a>
                            </div><?php endforeach;?></div><?php endif;?>
                    </section>
                    <section class="dashboard-card">
                        <h2>My Sections</h2>
                        <?php if(!$sections):?><div class="empty-state">
                            <p>No sections have been assigned to you.</p><a href="sections.php"
                                class="btn-primary">Manage Sections</a>
                        </div>
                        <?php else:?><div class="section-list"><?php foreach($sections as $s):?><div
                                class="section-item">
                                <div>
                                    <div class="item-title"><?=htmlspecialchars($s["course_code"])?> -
                                        <?=htmlspecialchars($s["name"])?></div>
                                    <div class="item-meta">Semester <?=$s["semester"]?> ·
                                        <?=htmlspecialchars($s["academic_year"])?> · <?=$s["student_count"]?> students
                                    </div>
                                </div><a href="students.php" class="item-link">Students</a>
                            </div><?php endforeach;?></div><?php endif;?>
                    </section>
                </div>
                <div>
                    <section class="dashboard-card">
                        <h2>Recent Students</h2>
                        <?php if(!$recent_students):?><div class="empty-state">
                            <p>No students found in your sections.</p><a href="students.php" class="btn-primary">Manage
                                Students</a>
                        </div>
                        <?php else:?><div class="student-list"><?php foreach($recent_students as $s):?><div
                                class="student-item">
                                <div>
                                    <div class="item-title"><?=htmlspecialchars($s["name"])?></div>
                                    <div class="item-meta">Roll: <?=htmlspecialchars($s["roll_no"])?> ·
                                        <?=htmlspecialchars($s["course_code"])?> -
                                        <?=htmlspecialchars($s["section_name"])?></div>
                                </div><a href="result.php?student_id=<?=$s["id"]?>" class="item-link">Result</a>
                            </div><?php endforeach;?></div><?php endif;?>
                    </section>
                    <section class="dashboard-card">
                        <h2>Academic Workflow</h2>
                        <div class="welcome-note"><strong>Recommended order:</strong>
                            <p>Create a course → create a section → add students → add subjects → enter marks → view
                                results.</p><a href="subjects.php" class="btn-primary">Go to Subjects</a>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
</body>

</html>