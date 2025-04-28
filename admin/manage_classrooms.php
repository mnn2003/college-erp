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
    if (isset($_POST['add_classroom'])) {
        // Add new classroom
        $room_name = trim($_POST['room_name']);
        $building = trim($_POST['building']);
        $capacity = (int)$_POST['capacity'];

        // Validate inputs
        $errors = [];
        if (empty($room_name)) $errors[] = "Room name is required";
        if (empty($building)) $errors[] = "Building name is required";
        if ($capacity <= 0) $errors[] = "Capacity must be greater than zero";

        // Check for duplicate room name
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classrooms WHERE room_name = ? AND building = ?");
        $stmt->execute([$room_name, $building]);
        if ($stmt->fetchColumn() > 0) $errors[] = "A classroom with this name already exists in the same building";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classrooms (room_name, building, capacity) VALUES (?, ?, ?)");
                $stmt->execute([$room_name, $building, $capacity]);
                $_SESSION['success'] = "Classroom added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding classroom: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['edit_classroom'])) {
        // Edit existing classroom
        $room_id = (int)$_POST['room_id'];
        $room_name = trim($_POST['room_name']);
        $building = trim($_POST['building']);
        $capacity = (int)$_POST['capacity'];

        // Validate inputs
        $errors = [];
        if (empty($room_name)) $errors[] = "Room name is required";
        if (empty($building)) $errors[] = "Building name is required";
        if ($capacity <= 0) $errors[] = "Capacity must be greater than zero";

        // Check for duplicate room name (excluding the current classroom)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classrooms WHERE room_name = ? AND building = ? AND room_id != ?");
        $stmt->execute([$room_name, $building, $room_id]);
        if ($stmt->fetchColumn() > 0) $errors[] = "A classroom with this name already exists in the same building";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE classrooms SET room_name = ?, building = ?, capacity = ? WHERE room_id = ?");
                $stmt->execute([$room_name, $building, $capacity, $room_id]);
                $_SESSION['success'] = "Classroom updated successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating classroom: " . $e->getMessage();
            }
        } else {
            $_SESSION['errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
        }
    } elseif (isset($_POST['delete_classroom'])) {
        // Delete classroom
        $room_id = (int)$_POST['room_id'];
        try {
            // Check if classroom has any enrollments (optional validation)
            $stmt = $pdo->prepare("DELETE FROM classrooms WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $_SESSION['success'] = "Classroom deleted successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting classroom: " . $e->getMessage();
        }
    }
    header("Location: manage_classrooms.php");
    exit();
}

// Get all classrooms
$classrooms = $pdo->query("SELECT * FROM classrooms ORDER BY building, room_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classrooms</title>
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
            <h1 class="text-3xl font-bold">Manage Classrooms</h1>
            <button onclick="document.getElementById('addClassroomModal').classList.remove('hidden')"
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-2"></i> Add Classroom
            </button>
        </div>
        <?php include '../includes/messages.php'; ?>
        <!-- Classrooms Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-4 text-left">Room Name</th>
                        <th class="py-3 px-4 text-left">Building</th>
                        <th class="py-3 px-4 text-left">Capacity</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classrooms)): ?>
                        <tr>
                            <td colspan="4" class="py-4 px-4 text-center text-gray-500">No classrooms found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classrooms as $classroom): ?>
                        <tr class="border-t hover:bg-gray-50">
                            <td class="py-3 px-4"><?= htmlspecialchars($classroom['room_name']) ?></td>
                            <td class="py-3 px-4"><?= htmlspecialchars($classroom['building']) ?></td>
                            <td class="py-3 px-4"><?= $classroom['capacity'] ?></td>
                            <td class="py-3 px-4 space-x-2">
                                <button onclick="openEditModal(<?= htmlspecialchars(json_encode($classroom)) ?>)"
                                        class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this classroom?');">
                                    <input type="hidden" name="room_id" value="<?= $classroom['room_id'] ?>">
                                    <button type="submit" name="delete_classroom" class="text-red-600 hover:text-red-800">
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

    <!-- Add Classroom Modal -->
    <div id="addClassroomModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Add New Classroom</h2>
                <button onclick="document.getElementById('addClassroomModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Room Name*</label>
                        <input type="text" name="room_name" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Room 101">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Building*</label>
                        <input type="text" name="building" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Building A">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Capacity*</label>
                        <input type="number" name="capacity" min="1" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., 30">
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('addClassroomModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="add_classroom"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Add Classroom
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Classroom Modal -->
    <div id="editClassroomModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Edit Classroom</h2>
                <button onclick="document.getElementById('editClassroomModal').classList.add('hidden')"
                        class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" id="editClassroomForm">
                <input type="hidden" name="room_id" id="editRoomId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Room Name*</label>
                        <input type="text" name="room_name" id="editRoomName" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Room 101">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Building*</label>
                        <input type="text" name="building" id="editBuilding" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., Building A">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Capacity*</label>
                        <input type="number" name="capacity" id="editCapacity" min="1" required
                               class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g., 30">
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="document.getElementById('editClassroomModal').classList.add('hidden')"
                            class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition mr-3">
                        Cancel
                    </button>
                    <button type="submit" name="edit_classroom"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Update Classroom
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    <script>
        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addClassroomModal');
            const editModal = document.getElementById('editClassroomModal');
            if (event.target === addModal) addModal.classList.add('hidden');
            if (event.target === editModal) editModal.classList.add('hidden');
        }

        // Function to open edit modal and populate fields
        function openEditModal(classroom) {
            document.getElementById('editRoomId').value = classroom.room_id;
            document.getElementById('editRoomName').value = classroom.room_name;
            document.getElementById('editBuilding').value = classroom.building;
            document.getElementById('editCapacity').value = classroom.capacity;
            document.getElementById('editClassroomModal').classList.remove('hidden');
        }
    </script>
</body>
</html>