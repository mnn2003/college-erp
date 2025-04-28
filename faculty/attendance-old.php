<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only faculty can access
if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Get faculty's courses
$stmt = $pdo->prepare("SELECT c.course_id, c.course_name 
                      FROM courses c
                      JOIN timetable t ON c.course_id = t.course_id
                      WHERE t.faculty_id = ?
                      GROUP BY c.course_id");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

// Process attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $course_id = $_POST['course_id'];
    $date = $_POST['date'];
    
    foreach ($_POST['attendance'] as $student_id => $status) {
        $stmt = $pdo->prepare("INSERT INTO attendance 
                              (student_id, course_id, date, status) 
                              VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE status = ?");
        $stmt->execute([$student_id, $course_id, $date, $status, $status]);
    }
    
    $_SESSION['success'] = "Attendance marked successfully!";
    header("Location: attendance.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <h1 class="text-3xl font-bold mb-6">Mark Attendance</h1>
        
        <form method="post" class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 mb-2">Course</label>
                    <select name="course_id" required
                            class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['course_id'] ?>">
                            <?= htmlspecialchars($course['course_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" required 
                           class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="flex items-end">
                    <button type="submit" name="load_students"
                            class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                        Load Students
                    </button>
                </div>
            </div>
        </form>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_students'])): 
            $course_id = $_POST['course_id'];
            $date = $_POST['date'];
            
            // Get students enrolled in this course
            $stmt = $pdo->prepare("SELECT s.student_id, s.full_name, s.roll_number, 
                                  a.status AS attendance_status
                                  FROM students s
                                  LEFT JOIN attendance a ON s.student_id = a.student_id 
                                  AND a.course_id = ? AND a.date = ?
                                  ORDER BY s.roll_number");
            $stmt->execute([$course_id, $date]);
            $students = $stmt->fetchAll();
        ?>
        
        <form method="post" class="bg-white p-6 rounded-lg shadow">
            <input type="hidden" name="course_id" value="<?= $course_id ?>">
            <input type="hidden" name="date" value="<?= $date ?>">
            
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="py-2 px-4 border">Roll No.</th>
                            <th class="py-2 px-4 border">Student Name</th>
                            <th class="py-2 px-4 border">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="py-2 px-4 border"><?= htmlspecialchars($student['roll_number']) ?></td>
                            <td class="py-2 px-4 border"><?= htmlspecialchars($student['full_name']) ?></td>
                            <td class="py-2 px-4 border">
                                <select name="attendance[<?= $student['student_id'] ?>]"
                                        class="px-2 py-1 border rounded focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    <option value="Present" <?= $student['attendance_status'] === 'Present' ? 'selected' : '' ?>>Present</option>
                                    <option value="Absent" <?= $student['attendance_status'] === 'Absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="Late" <?= $student['attendance_status'] === 'Late' ? 'selected' : '' ?>>Late</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4">
                <button type="submit" name="mark_attendance"
                        class="bg-green-600 text-white py-2 px-6 rounded-lg hover:bg-green-700 transition duration-200">
                    Submit Attendance
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>