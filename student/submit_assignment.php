<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only students can access
if ($_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Get student details
$stmt = $pdo->prepare("
    SELECT s.student_id, s.roll_number, s.full_name 
    FROM students s
    WHERE s.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $assignment_id = (int)$_POST['assignment_id'];
    
    // Verify student is enrolled in this assignment's course
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM assignments a
        JOIN student_subjects ss ON a.course_id = ss.subject_id
        WHERE a.assignment_id = ? AND ss.student_id = ?
    ");
    $stmt->execute([$assignment_id, $student['student_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        $_SESSION['error'] = "You are not enrolled in this course";
        header("Location: assignments.php");
        exit();
    }
    
    // File upload handling
    $uploadDir = '../uploads/assignments/';
    $fileName = '';
    $errors = [];
    
    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['assignment_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
        } else {
            // Generate unique filename
            $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'assignment_' . $assignment_id . '_student_' . $student['student_id'] . '_' . time() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            // Check file type
            $allowedTypes = ['pdf', 'doc', 'docx', 'txt', 'odt', 'ppt', 'pptx'];
            if (!in_array(strtolower($fileExt), $allowedTypes)) {
                $errors[] = "Only PDF, Word, Text, and PowerPoint files are allowed";
            }
            
            // Check file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = "File size must be less than 5MB";
            }
            
            if (empty($errors)) {
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $errors[] = "Failed to upload file";
                }
            }
        }
    } else {
        $errors[] = "No file uploaded";
    }
    
    if (empty($errors)) {
        try {
            // Check if submission already exists
            $stmt = $pdo->prepare("SELECT submission_id FROM submissions WHERE assignment_id = ? AND student_id = ?");
            $stmt->execute([$assignment_id, $student['student_id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing submission
                $stmt = $pdo->prepare("
                    UPDATE submissions 
                    SET file_path = ?, submitted_at = NOW() 
                    WHERE submission_id = ?
                ");
                $stmt->execute([$fileName, $existing['submission_id']]);
            } else {
                // Create new submission
                $stmt = $pdo->prepare("
                    INSERT INTO submissions 
                    (assignment_id, student_id, file_path, submitted_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$assignment_id, $student['student_id'], $fileName]);
            }
            
            $_SESSION['success'] = "Assignment submitted successfully!";
            header("Location: assignments.php");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error submitting assignment: " . $e->getMessage();
            header("Location: submit_assignment.php?id=" . $assignment_id);
            exit();
        }
    } else {
        $_SESSION['errors'] = $errors;
        header("Location: submit_assignment.php?id=" . $assignment_id);
        exit();
    }
}

// Get assignment details
$assignment = null;
if (isset($_GET['id'])) {
    $assignment_id = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("
        SELECT a.*, sub.subject_code, sub.subject_name, f.full_name AS faculty_name
        FROM assignments a
        JOIN subjects sub ON a.course_id = sub.subject_id
        JOIN faculty f ON a.faculty_id = f.faculty_id
        WHERE a.assignment_id = ?
    ");
    $stmt->execute([$assignment_id]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        $_SESSION['error'] = "Assignment not found";
        header("Location: assignments.php");
        exit();
    }
    
    // Verify student is enrolled in this course
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM student_subjects 
        WHERE student_id = ? AND subject_id = ?
    ");
    $stmt->execute([$student['student_id'], $assignment['course_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        $_SESSION['error'] = "You are not enrolled in this course";
        header("Location: assignments.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Assignment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/student_sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold">Submit Assignment</h1>
            <a href="assignments.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Assignments
            </a>
        </div>
        
        <?php include '../includes/messages.php'; ?>
        
        <?php if ($assignment): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-2"><?= htmlspecialchars($assignment['title']) ?></h2>
                <div class="text-gray-600">
                    <span class="font-medium">Course:</span> 
                    <?= htmlspecialchars($assignment['subject_code']) ?> - <?= htmlspecialchars($assignment['subject_name']) ?>
                </div>
                <div class="text-gray-600">
                    <span class="font-medium">Faculty:</span> <?= htmlspecialchars($assignment['faculty_name']) ?>
                </div>
                <div class="text-gray-600">
                    <span class="font-medium">Due:</span> 
                    <?= date('M j, Y g:i A', strtotime($assignment['due_date'])) ?>
                    <?php if (strtotime($assignment['due_date']) < time()): ?>
                        <span class="text-red-600 ml-2">(Past Due)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($assignment['description'])): ?>
            <div class="mb-6 p-4 bg-gray-50 rounded border border-gray-200">
                <h3 class="font-medium mb-2">Assignment Description</h3>
                <p><?= nl2br(htmlspecialchars($assignment['description'])) ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
                
                <div class="mb-6">
                    <label class="block text-gray-700 mb-2" for="assignment_file">
                        Upload Your Assignment File*
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <input type="file" name="assignment_file" id="assignment_file" 
                               class="hidden" required accept=".pdf,.doc,.docx,.txt,.odt,.ppt,.pptx">
                        <div id="file-info" class="mb-2">
                            <i class="fas fa-cloud-upload-alt fa-2x text-gray-400 mb-2"></i>
                            <p class="text-gray-500">Click to select or drag and drop your file</p>
                            <p class="text-sm text-gray-400 mt-1">(PDF, Word, Text, or PowerPoint, max 5MB)</p>
                        </div>
                        <button type="button" onclick="document.getElementById('assignment_file').click()" 
                                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            Select File
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="submit_assignment"
                            class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Assignment
                    </button>
                </div>
            </form>
        </div>
        
        <script>
            // File input handling
            const fileInput = document.getElementById('assignment_file');
            const fileInfo = document.getElementById('file-info');
            
            fileInput.addEventListener('change', function(e) {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = (file.size / (1024 * 1024)).toFixed(2); // in MB
                    
                    fileInfo.innerHTML = `
                        <div class="flex items-center justify-center">
                            <i class="fas fa-file-alt fa-2x text-blue-500 mr-3"></i>
                            <div class="text-left">
                                <div class="font-medium">${file.name}</div>
                                <div class="text-sm text-gray-500">${fileSize} MB</div>
                            </div>
                        </div>
                    `;
                }
            });
            
            // Drag and drop functionality
            const dropArea = document.querySelector('.border-dashed');
            
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropArea.classList.add('border-blue-400', 'bg-blue-50');
            }
            
            function unhighlight() {
                dropArea.classList.remove('border-blue-400', 'bg-blue-50');
            }
            
            dropArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                
                // Trigger change event
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        </script>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-exclamation-triangle fa-2x text-yellow-500 mb-4"></i>
                <p class="text-lg">No assignment selected for submission.</p>
                <a href="assignments.php" class="text-blue-600 hover:text-blue-800 mt-4 inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Assignments
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>