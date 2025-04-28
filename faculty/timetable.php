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

// Get current week's timetable
$current_day = date('l');
$week_start = date('Y-m-d', strtotime('last Monday'));
$week_end = date('Y-m-d', strtotime('next Sunday'));

$timetable = $pdo->prepare("
    SELECT t.*, s.subject_code, s.subject_name, p.program_name, c.room_name
    FROM timetable t
    JOIN subjects s ON t.course_id = s.subject_id
    JOIN programs p ON s.program_id = p.program_id
    JOIN classrooms c ON t.room = c.room_name
    WHERE t.faculty_id = ?
    ORDER BY 
        CASE t.day
            WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
            WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6
        END,
        t.start_time
");
$timetable->execute([$faculty['faculty_id']]);
$timetable = $timetable->fetchAll();

// Group timetable by day
$timetable_by_day = [];
foreach ($timetable as $class) {
    $timetable_by_day[$class['day']][] = $class;
}

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Timetable</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .today {
            background-color: #EFF6FF;
            border-left: 4px solid #3B82F6;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include '../includes/faculty_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">My Timetable</h1>
            <div class="text-lg font-medium">
                <?= $faculty['full_name'] ?> | Week: <?= date('M j', strtotime($week_start)) ?> - <?= date('M j, Y', strtotime($week_end)) ?>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="w-32 py-3 px-4 text-left">Time</th>
                            <?php foreach ($days as $day): ?>
                            <th class="py-3 px-4 text-left <?= $day === $current_day ? 'text-blue-600 font-bold' : '' ?>">
                                <?= $day ?>
                                <?php if ($day === $current_day): ?>
                                    <span class="text-sm font-normal">(Today)</span>
                                <?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Generate time slots from 8 AM to 6 PM
                        for ($hour = 8; $hour <= 18; $hour++): 
                            $time_slot = sprintf('%02d:00', $hour);
                            $next_slot = sprintf('%02d:00', $hour + 1);
                        ?>
                        <tr class="border-t">
                            <td class="py-2 px-4 text-gray-500">
                                <?= date('g:i A', strtotime($time_slot)) ?> - <?= date('g:i A', strtotime($next_slot)) ?>
                            </td>
                            
                            <?php foreach ($days as $day): ?>
                            <td class="py-2 px-4 <?= $day === $current_day ? 'today' : '' ?>">
                                <?php 
                                if (isset($timetable_by_day[$day])) {
                                    foreach ($timetable_by_day[$day] as $class) {
                                        $class_start = date('H:i', strtotime($class['start_time']));
                                        $class_end = date('H:i', strtotime($class['end_time']));
                                        
                                        if ($class_start >= $time_slot && $class_start < $next_slot) {
                                            echo '<div class="mb-2 p-2 bg-blue-50 rounded border border-blue-100">';
                                            echo '<div class="font-medium text-blue-700">'.$class['subject_code'].'</div>';
                                            echo '<div class="text-sm">'.$class['subject_name'].'</div>';
                                            echo '<div class="text-sm text-gray-600">'.$class['room_name'].'</div>';
                                            echo '<div class="text-xs text-gray-500 mt-1">';
                                            echo date('g:i A', strtotime($class['start_time'])).' - '.date('g:i A', strtotime($class['end_time']));
                                            echo '</div>';
                                            echo '</div>';
                                        }
                                    }
                                }
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Upcoming Classes Section -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold mb-4">Upcoming Classes</h2>
            
            <?php
            $upcoming = $pdo->prepare("
                SELECT t.*, s.subject_code, s.subject_name, c.room_name
                FROM timetable t
                JOIN subjects s ON t.course_id = s.subject_id
                JOIN classrooms c ON t.room = c.room_name
                WHERE t.faculty_id = ? 
                AND (t.day = ? AND t.start_time > TIME(NOW()) OR t.day > ?)
                ORDER BY t.day, t.start_time
                LIMIT 5
            ");
            $upcoming->execute([$faculty['faculty_id'], $current_day, $current_day]);
            $upcoming_classes = $upcoming->fetchAll();
            ?>
            
            <?php if (empty($upcoming_classes)): ?>
                <div class="bg-white p-4 rounded-lg shadow">
                    <p class="text-gray-500">No upcoming classes for the rest of the week.</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4 text-left">Day</th>
                                <th class="py-2 px-4 text-left">Time</th>
                                <th class="py-2 px-4 text-left">Course</th>
                                <th class="py-2 px-4 text-left">Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_classes as $class): ?>
                            <tr class="border-t hover:bg-gray-50">
                                <td class="py-2 px-4"><?= $class['day'] ?></td>
                                <td class="py-2 px-4"><?= date('g:i A', strtotime($class['start_time'])) ?> - <?= date('g:i A', strtotime($class['end_time'])) ?></td>
                                <td class="py-2 px-4">
                                    <div class="font-medium"><?= $class['subject_code'] ?></div>
                                    <div class="text-sm text-gray-600"><?= $class['subject_name'] ?></div>
                                </td>
                                <td class="py-2 px-4"><?= $class['room_name'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>