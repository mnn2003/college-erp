<?php
require_once '../config/auth.php';
require_once '../config/db.php';

// Only admin can access
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_program'])) {
        // Add new program
        $program_name = trim($_POST['program_name']);
        $duration = (int)$_POST['duration'];
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO programs (program_name, duration_years, description) VALUES (?, ?, ?)");
            $stmt->execute([$program_name, $duration, $description]);
            $_SESSION['success'] = "Program added successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding program: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_program'])) {
        // Update existing program
        $program_id = (int)$_POST['program_id'];
        $program_name = trim($_POST['program_name']);
        $duration = (int)$_POST['duration'];
        $description = trim($_POST['description']);
        
        try {
            $stmt = $pdo->prepare("UPDATE programs SET program_name = ?, duration_years = ?, description = ? WHERE program_id = ?");
            $stmt->execute([$program_name, $duration, $description, $program_id]);
            $_SESSION['success'] = "Program updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating program: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_program'])) {
        // Delete program
        $program_id = (int)$_POST['program_id'];
        
        try {
            // First check if any students are enrolled in this program
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE program_id = ?");
            $stmt->execute([$program_id]);
            $student_count = $stmt->fetchColumn();
            
            if ($student_count > 0) {
                $_SESSION['error'] = "Cannot delete program - $student_count students are enrolled in it!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM programs WHERE program_id = ?");
                $stmt->execute([$program_id]);
                $_SESSION['success'] = "Program deleted successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting program: " . $e->getMessage();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: manage_programs.php");
    exit();
}

// Get all programs
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();

// Get program count
$program_count = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Programs</title>
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
            <h1 class="text-3xl font-bold">Manage Academic Programs</h1>
            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                <?= $program_count ?> program(s)
            </span>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add New Program Form -->
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Program</h2>
            <form method="post">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Program Name*</label>
                        <input type="text" name="program_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Duration (Years)*</label>
                        <select name="duration" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4" selected>4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Description</label>
                        <input type="text" name="description"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" name="add_program"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-2"></i> Add Program
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Programs List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Program Name</th>
                        <th class="py-3 px-4 text-left">Duration</th>
                        <th class="py-3 px-4 text-left">Description</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($programs)): ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No programs found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($programs as $program): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium"><?= htmlspecialchars($program['program_name']) ?></td>
                            <td class="py-3 px-4"><?= $program['duration_years'] ?> Year(s)</td>
                            <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($program['description']) ?></td>
                            <td class="py-3 px-4">
                                <!-- Edit Button (opens modal) -->
                                <button onclick="openEditModal(
                                    <?= $program['program_id'] ?>, 
                                    '<?= htmlspecialchars(addslashes($program['program_name'])) ?>', 
                                    <?= $program['duration_years'] ?>, 
                                    '<?= htmlspecialchars(addslashes($program['description'])) ?>'
                                )" class="text-blue-600 hover:text-blue-800 mr-3">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Delete Button -->
                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this program?');">
                                    <input type="hidden" name="program_id" value="<?= $program['program_id'] ?>">
                                    <button type="submit" name="delete_program" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Edit Program Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <h2 class="text-xl font-semibold mb-4">Edit Program</h2>
            <form method="post">
                <input type="hidden" id="edit_program_id" name="program_id">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Program Name*</label>
                        <input type="text" id="edit_program_name" name="program_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Duration (Years)*</label>
                        <select id="edit_duration" name="duration" required
                                class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Description</label>
                        <input type="text" id="edit_description" name="description"
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition">
                        Cancel
                    </button>
                    <button type="submit" name="edit_program"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>    
    <script>
        // Modal functions
        function openEditModal(id, name, duration, description) {
            document.getElementById('edit_program_id').value = id;
            document.getElementById('edit_program_name').value = name;
            document.getElementById('edit_duration').value = duration;
            document.getElementById('edit_description').value = description;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>