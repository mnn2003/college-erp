<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only faculty can access
if ($_SESSION['role'] !== 'faculty') {
    header('Location: ../index.php');
    exit();
}

// Get faculty details
$stmt = $pdo->prepare("SELECT faculty_id, full_name FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_attendance'])) {
        $course_id = (int)$_POST['course_id'];
        $date = $_POST['date'];
        
        // Verify faculty is assigned to this course
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ?");
        $stmt->execute([$faculty['faculty_id'], $course_id]);
        $is_assigned = $stmt->fetchColumn();
        
        if (!$is_assigned) {
            $_SESSION['error'] = "You are not assigned to this course";
            header("Location: attendance.php");
            exit();
        }
        
        foreach ($_POST['attendance'] as $student_id => $status) {
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, course_id, date, status) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = ?
            ");
            $stmt->execute([$student_id, $course_id, $date, $status, $status]);
        }
        
        $_SESSION['success'] = "Attendance saved successfully!";
        header("Location: attendance.php?date=$date");
        exit();
    }
}

// Get selected date
$date = $_GET['date'] ?? date('Y-m-d');

// Get scheduled classes for the faculty on selected date
$classes = [];
if (!empty($date)) {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS timetable_id,
            s.subject_id,
            s.subject_code,
            s.subject_name,
            t.day,
            t.start_time,
            t.end_time,
            t.room,
            (SELECT COUNT(*) FROM attendance 
             WHERE course_id = s.subject_id AND date = ?) AS attendance_recorded
        FROM timetable t
        JOIN subjects s ON t.course_id = s.subject_id
        JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
        WHERE fs.faculty_id = ? AND t.day = ?
        ORDER BY t.start_time
    ");
    
    $day = date('l', strtotime($date));
    $stmt->execute([$date, $faculty['faculty_id'], $day]);
    $classes = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function openAttendanceModal(courseId, courseName, date) {
            document.getElementById('attendanceModalTitle').innerText = `Mark Attendance - ${courseName}`;
            document.getElementById('attendanceCourseId').value = courseId;
            document.getElementById('attendanceDate').value = date;
            document.getElementById('attendanceModal').classList.remove('hidden');
            
            // Load students via AJAX
            fetch(`get_students.php?course_id=${courseId}&date=${date}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('studentList').innerHTML = data;
                });
        }
    </script>
</head>
<body class="bg-gray-100">
<div class="md:flex">
<!-- Sidebar -->
<?php include '../includes/faculty_sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <h1 class="text-3xl font-bold mb-6">Attendance Management</h1>
        
        <?php include '../includes/messages.php'; ?>
        
        <!-- Selection Form -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <form method="get">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Select Date*</label>
                        <input type="date" name="date" value="<?= $date ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               onchange="this.form.submit()">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" 
                                class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">
                            Load Classes
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if (!empty($date)): ?>
        <!-- Classes List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Course</th>
                        <th class="py-3 px-4 text-left">Time</th>
                        <th class="py-3 px-4 text-left">Location</th>
                        <th class="py-3 px-4 text-left">Status</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="5" class="py-4 px-4 text-center text-gray-500">
                                No classes scheduled for <?= date('l, F j, Y', strtotime($date)) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classes as $class): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium">
                                <?= htmlspecialchars($class['subject_code']) ?> - 
                                <?= htmlspecialchars($class['subject_name']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <?= date('h:i A', strtotime($class['start_time'])) ?> - 
                                <?= date('h:i A', strtotime($class['end_time'])) ?>
                            </td>
                            <td class="py-3 px-4"><?= htmlspecialchars($class['room']) ?></td>
                            <td class="py-3 px-4">
                                <?php if ($class['attendance_recorded'] > 0): ?>
                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                        Recorded
                                    </span>
                                <?php else: ?>
                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <button onclick="openAttendanceModal(
                                    <?= $class['subject_id'] ?>, 
                                    '<?= htmlspecialchars(addslashes($class['subject_code'].' - '.$class['subject_name'])) ?>', 
                                    '<?= $date ?>'
                                )" class="text-blue-600 hover:text-blue-800">
                                    <?= $class['attendance_recorded'] > 0 ? 'Edit' : 'Take' ?> Attendance
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Attendance Modal -->
    <div id="attendanceModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h2 id="attendanceModalTitle" class="text-xl font-semibold">Mark Attendance</h2>
                <button onclick="document.getElementById('attendanceModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post">
                <input type="hidden" id="attendanceCourseId" name="course_id">
                <input type="hidden" id="attendanceDate" name="date">
                
                <div id="studentList" class="mb-4">
                    <!-- Student list will be loaded here via AJAX -->
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-blue-500 text-2xl"></i>
                        <p class="mt-2 text-gray-600">Loading students...</p>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="document.getElementById('attendanceModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="mark_attendance"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Save Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>    
</body>
</html>