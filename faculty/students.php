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

// Get faculty's courses
$courses = $pdo->prepare("
    SELECT s.subject_id, s.subject_code, s.subject_name, p.program_name
    FROM subjects s
    JOIN faculty_subjects fs ON s.subject_id = fs.subject_id
    JOIN programs p ON s.program_id = p.program_id
    WHERE fs.faculty_id = ?
    ORDER BY s.subject_name
");
$courses->execute([$faculty['faculty_id']]);
$courses = $courses->fetchAll();

// Get selected course from URL
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Get students for selected course
$students = [];
if ($selected_course_id > 0) {
    // Verify faculty is assigned to this course
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ?");
    $stmt->execute([$faculty['faculty_id'], $selected_course_id]);
    
    if ($stmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.roll_number, s.full_name, s.contact, 
                   p.program_name, s.batch,
                   (SELECT COUNT(*) FROM attendance 
                    WHERE student_id = s.student_id AND course_id = ? AND status = 'Present') AS present_count,
                   (SELECT COUNT(*) FROM attendance 
                    WHERE student_id = s.student_id AND course_id = ?) AS total_classes
            FROM students s
            JOIN student_subjects ss ON s.student_id = ss.student_id
            JOIN programs p ON s.program = p.program_id
            WHERE ss.subject_id = ?
            ORDER BY s.roll_number
        ");
        $stmt->execute([$selected_course_id, $selected_course_id, $selected_course_id]);
        $students = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Students</h1>
            <div class="text-lg text-gray-600">
                <?= htmlspecialchars($faculty['full_name']) ?>
            </div>
        </div>
        
        <!-- Course Selection -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <form method="get">
                <div class="flex items-end space-x-4">
                    <div class="flex-1">
                        <label class="block text-gray-700 mb-2">Select Course</label>
                        <select name="course_id" onchange="this.form.submit()"
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select a course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['subject_id'] ?>"
                                <?= $selected_course_id == $course['subject_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['subject_code']) ?> - 
                                <?= htmlspecialchars($course['subject_name']) ?>
                                (<?= htmlspecialchars($course['program_name']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" 
                            class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition">
                        Load Students
                    </button>
                </div>
            </form>
        </div>
        
        <?php if ($selected_course_id > 0): ?>
        <!-- Students List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-4 border-b bg-gray-50">
                <h2 class="text-xl font-semibold">
                    <?= htmlspecialchars($courses[array_search($selected_course_id, array_column($courses, 'subject_id'))]['subject_name']) ?>
                    <span class="text-sm font-normal text-gray-600">
                        (<?= count($students) ?> students)
                    </span>
                </h2>
            </div>
            
            <?php if (empty($students)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-user-graduate fa-2x mb-2"></i>
                    <p>No students enrolled in this course.</p>
                </div>
            <?php else: ?>
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left">Roll No.</th>
                            <th class="py-3 px-4 text-left">Student Name</th>
                            <th class="py-3 px-4 text-left">Program</th>
                            <th class="py-3 px-4 text-left">Batch</th>
                            <th class="py-3 px-4 text-left">Attendance</th>
                            <th class="py-3 px-4 text-left">Contact</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <?= htmlspecialchars($student['roll_number']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium"><?= htmlspecialchars($student['full_name']) ?></div>
                            </td>
                            <td class="py-3 px-4">
                                <?= htmlspecialchars($student['program_name']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <?= htmlspecialchars($student['batch']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($student['total_classes'] > 0): ?>
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                            <div class="bg-green-600 h-2.5 rounded-full" 
                                                 style="width: <?= round(($student['present_count'] / $student['total_classes']) * 100) ?>%"></div>
                                        </div>
                                        <span class="text-sm">
                                            <?= $student['present_count'] ?>/<?= $student['total_classes'] ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500">No records</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?= htmlspecialchars($student['contact']) ?>
                            </td>
                            <td class="py-3 px-4">
                                <a href="student_profile.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $selected_course_id ?>"
                                   class="text-blue-600 hover:text-blue-800">
                                    View Profile
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Attendance Summary -->
        <?php if (!empty($students)): ?>
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-lg font-medium text-gray-600 mb-2">Attendance Summary</div>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Present</span>
                            <span>
                                <?= array_sum(array_column($students, 'present_count')) ?> classes
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" 
                                 style="width: <?= 
                                    array_sum(array_column($students, 'total_classes')) > 0 ? 
                                    round(array_sum(array_column($students, 'present_count')) / array_sum(array_column($students, 'total_classes')) * 100) : 0 
                                 ?>%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span>Average Attendance Rate</span>
                            <span>
                                <?= 
                                    array_sum(array_column($students, 'total_classes')) > 0 ? 
                                    round(array_sum(array_column($students, 'present_count')) / array_sum(array_column($students, 'total_classes')) * 100) : 0 
                                ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-lg font-medium text-gray-600 mb-2">Top Attendees</div>
                <div class="space-y-2">
                    <?php 
                    usort($students, function($a, $b) {
                        $rateA = $a['total_classes'] > 0 ? ($a['present_count'] / $a['total_classes']) : 0;
                        $rateB = $b['total_classes'] > 0 ? ($b['present_count'] / $b['total_classes']) : 0;
                        return $rateB <=> $rateA;
                    });
                    
                    $topStudents = array_slice($students, 0, 3);
                    foreach ($topStudents as $student): 
                        $attendanceRate = $student['total_classes'] > 0 ? 
                            round(($student['present_count'] / $student['total_classes']) * 100) : 0;
                    ?>
                    <div class="flex justify-between items-center">
                        <div class="text-sm truncate"><?= htmlspecialchars($student['full_name']) ?></div>
                        <div class="text-sm font-medium"><?= $attendanceRate ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-lg font-medium text-gray-600 mb-2">Lowest Attendees</div>
                <div class="space-y-2">
                    <?php 
                    usort($students, function($a, $b) {
                        $rateA = $a['total_classes'] > 0 ? ($a['present_count'] / $a['total_classes']) : 0;
                        $rateB = $b['total_classes'] > 0 ? ($b['present_count'] / $b['total_classes']) : 0;
                        return $rateA <=> $rateB;
                    });
                    
                    $lowStudents = array_slice($students, 0, 3);
                    foreach ($lowStudents as $student): 
                        $attendanceRate = $student['total_classes'] > 0 ? 
                            round(($student['present_count'] / $student['total_classes']) * 100) : 0;
                    ?>
                    <div class="flex justify-between items-center">
                        <div class="text-sm truncate"><?= htmlspecialchars($student['full_name']) ?></div>
                        <div class="text-sm font-medium"><?= $attendanceRate ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>