<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Get all courses, faculty, and classrooms for dropdowns
$courses = $pdo->query("
    SELECT s.subject_id, s.subject_code, s.subject_name, p.program_name 
    FROM subjects s
    JOIN programs p ON s.program_id = p.program_id
    ORDER BY p.program_name, s.subject_name
")->fetchAll();

$faculty = $pdo->query("SELECT faculty_id, full_name FROM faculty ORDER BY full_name")->fetchAll();
$classrooms = $pdo->query("SELECT room_name FROM classrooms ORDER BY room_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_schedule'])) {
        // Add new schedule
        $course_id = (int)$_POST['course_id'];
        $faculty_id = (int)$_POST['faculty_id'];
        $class_date = $_POST['class_date'];
        $day = $_POST['day']; // Automatically populated by JavaScript
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $room = $_POST['room'];

        // Validate inputs
        $errors = [];
        if ($course_id <= 0) $errors[] = "Please select a valid course";
        if ($faculty_id <= 0) $errors[] = "Please select a valid faculty";
        if (empty($class_date)) $errors[] = "Please select a valid class date";
        if (empty($day)) $errors[] = "Day could not be determined from the selected date";
        if (empty($start_time)) $errors[] = "Start time is required";
        if (empty($end_time)) $errors[] = "End time is required";
        if (empty($room)) $errors[] = "Please select a room";

        // Check for time conflicts
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM timetable 
                WHERE day = ? AND class_date = ? AND room = ? AND (
                    (start_time <= ? AND end_time > ?) OR
                    (start_time < ? AND end_time >= ?) OR
                    (start_time >= ? AND end_time <= ?)
                )
            ");
            $stmt->execute([$day, $class_date, $room, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time]);
            $conflicts = $stmt->fetchColumn();
            if ($conflicts > 0) $errors[] = "Time conflict with another class in the same room on the same date";
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO timetable (course_id, faculty_id, day, class_date, start_time, end_time, room) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$course_id, $faculty_id, $day, $class_date, $start_time, $end_time, $room]);
                $_SESSION['success'] = "Class scheduled successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error scheduling class: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['delete_schedule'])) {
        // Delete schedule
        $schedule_id = (int)$_POST['schedule_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM timetable WHERE id = ?");
            $stmt->execute([$schedule_id]);
            $_SESSION['success'] = "Schedule deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting schedule: " . $e->getMessage();
        }
    }
    header("Location: manage_timetable.php");
    exit();
}

// Get current timetable with all details
$timetable = $pdo->query("
    SELECT t.*, 
           s.subject_code, s.subject_name,
           f.full_name AS faculty_name,
           p.program_name
    FROM timetable t
    JOIN subjects s ON t.course_id = s.subject_id
    JOIN faculty f ON t.faculty_id = f.faculty_id
    JOIN programs p ON s.program_id = p.program_id
    ORDER BY 
        CASE t.day
            WHEN 'Monday' THEN 1
            WHEN 'Tuesday' THEN 2
            WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4
            WHEN 'Friday' THEN 5
            WHEN 'Saturday' THEN 6
            WHEN 'Sunday' THEN 7
        END,
        t.start_time
")->fetchAll();

// Group timetable by day for better display
$timetable_by_day = [];
foreach ($timetable as $entry) {
    $timetable_by_day[$entry['day']][] = $entry;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
<div class="md:flex">
    <!-- Sidebar -->
    <?php include '../includes/admin_sidebar.php'; ?>
    <!-- Main Content -->
    <div class="main-content p-8 mt-16 md:mt-0 mt-16 md:ml-64 w-full">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Manage Timetable</h1>
            <button onclick="document.getElementById('addScheduleModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Add Schedule
            </button>
        </div>
        <?php include '../includes/messages.php'; ?>
        <!-- Timetable View -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="py-3 px-4 text-left">Day</th>
                            <th class="py-3 px-4 text-left">Date</th>
                            <th class="py-3 px-4 text-left">Time</th>
                            <th class="py-3 px-4 text-left">Course</th>
                            <th class="py-3 px-4 text-left">Program</th>
                            <th class="py-3 px-4 text-left">Faculty</th>
                            <th class="py-3 px-4 text-left">Room</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($timetable)): ?>
                            <tr>
                                <td colspan="8" class="py-4 px-4 text-center text-gray-500">No schedules found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($days as $day): ?>
                                <?php if (isset($timetable_by_day[$day])): ?>
                                    <?php foreach ($timetable_by_day[$day] as $index => $schedule): ?>
                                    <tr class="border-t hover:bg-gray-50">
                                        <?php if ($index === 0): ?>
                                            <td class="py-3 px-4 font-medium" rowspan="<?= count($timetable_by_day[$day]) ?>">
                                                <?= $day ?>
                                            </td>
                                        <?php endif; ?>
                                        <td class="py-3 px-4"><?= htmlspecialchars($schedule['class_date']) ?></td>
                                        <td class="py-3 px-4">
                                            <?= date('h:i A', strtotime($schedule['start_time'])) ?> - 
                                            <?= date('h:i A', strtotime($schedule['end_time'])) ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?= htmlspecialchars($schedule['subject_code']) ?> - 
                                            <?= htmlspecialchars($schedule['subject_name']) ?>
                                        </td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($schedule['program_name']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($schedule['faculty_name']) ?></td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($schedule['room']) ?></td>
                                        <td class="py-3 px-4">
                                            <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this schedule?');">
                                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                                <button type="submit" name="delete_schedule" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Add Schedule Modal -->
    <div id="addScheduleModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Schedule New Class</h2>
                <button onclick="document.getElementById('addScheduleModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Course*</label>
                        <select name="course_id" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['subject_id'] ?>">
                                <?= htmlspecialchars($course['program_name']) ?> - 
                                <?= htmlspecialchars($course['subject_code']) ?>: 
                                <?= htmlspecialchars($course['subject_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Faculty*</label>
                        <select name="faculty_id" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Faculty</option>
                            <?php foreach ($faculty as $member): ?>
                            <option value="<?= $member['faculty_id'] ?>"><?= htmlspecialchars($member['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Class Date*</label>
                        <input type="date" name="class_date" id="class_date" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <input type="hidden" name="day" id="day" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Room*</label>
                            <select name="room" required
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Room</option>
                                <?php foreach ($classrooms as $room): ?>
                                <option value="<?= $room['room_name'] ?>"><?= htmlspecialchars($room['room_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">Start Time*</label>
                            <input type="time" name="start_time" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 mb-2">End Time*</label>
                            <input type="time" name="end_time" required
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('addScheduleModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="add_schedule"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Add Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    // Auto-calculate the day of the week based on the selected date
    document.getElementById('class_date').addEventListener('change', function () {
        const dateInput = this.value;
        if (dateInput) {
            const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const selectedDate = new Date(dateInput);
            const dayName = daysOfWeek[selectedDate.getDay()];
            document.getElementById('day').value = dayName;
        }
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('addScheduleModal');
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    };
</script>
</body>
</html>