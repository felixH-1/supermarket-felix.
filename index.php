<?php
// ====================================================================
// PEMROSESAN SISI SERVER (PHP BACKEND API)
// ====================================================================

// --- Konfigurasi dan Fungsi Helper ---
header('Content-Type: application/json');
define('DATA_FILE', 'tasks.json');

// Fungsi untuk mengirim respons JSON dan keluar
function send_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Fungsi untuk membaca tugas dari file JSON
function get_tasks() {
    if (!file_exists(DATA_FILE)) {
        file_put_contents(DATA_FILE, json_encode([]));
    }
    $json_data = file_get_contents(DATA_FILE);
    return json_decode($json_data, true);
}

// Fungsi untuk menyimpan tugas ke file JSON
function save_tasks($tasks) {
    file_put_contents(DATA_FILE, json_encode($tasks, JSON_PRETTY_PRINT));
}

// Fungsi untuk membersihkan input
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}


// --- Routing API ---
$request_method = $_SERVER['REQUEST_METHOD'];
$request_uri = strtok($_SERVER['REQUEST_URI'], '?'); // Hapus query string
$api_prefix = '/api/tasks';

// Cek apakah request ditujukan untuk API
if (strpos($request_uri, $api_prefix) !== false) {
    $tasks = get_tasks();
    $task_id = isset($_GET['id']) ? $_GET['id'] : null;

    switch ($request_method) {
        case 'GET':
            send_response($tasks);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $new_task = [
                'id' => uniqid(),
                'text' => clean_input($input['text']),
                'category' => clean_input($input['category']),
                'priority' => clean_input($input['priority']),
                'dueDate' => clean_input($input['dueDate']),
                'completed' => false,
                'createdAt' => date('Y-m-d H:i:s')
            ];
            $tasks[] = $new_task;
            save_tasks($tasks);
            send_response($new_task, 201); // 201 Created
            break;

        case 'PUT':
            if (!$task_id) send_response(['message' => 'ID is required for update'], 400);
            
            $input = json_decode(file_get_contents('php://input'), true);
            $updated = false;
            foreach ($tasks as &$task) {
                if ($task['id'] === $task_id) {
                    $task['text'] = clean_input($input['text']);
                    $task['category'] = clean_input($input['category']);
                    $task['priority'] = clean_input($input['priority']);
                    $task['dueDate'] = clean_input($input['dueDate']);
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                save_tasks($tasks);
                send_response(['message' => 'Task updated successfully']);
            } else {
                send_response(['message' => 'Task not found'], 404);
            }
            break;
            
        case 'DELETE':
            if (!$task_id) send_response(['message' => 'ID is required for delete'], 400);

            $initial_count = count($tasks);
            $tasks = array_filter($tasks, function($task) use ($task_id) {
                return $task['id'] !== $task_id;
            });

            if (count($tasks) < $initial_count) {
                save_tasks(array_values($tasks)); // Re-index array
                send_response(['message' => 'Task deleted successfully']);
            } else {
                send_response(['message' => 'Task not found'], 404);
            }
            break;

        default:
            send_response(['message' => 'Method not allowed'], 405);
            break;
    }
}

// Jika bukan request API, lanjutkan ke render HTML
header_remove('Content-Type');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productivity Dashboard</title>
    
    <!-- Font Awesome untuk Ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Chart.js untuk Grafik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ====================================================================
           STYLING (CSS LANJUTAN DENGAN DARK MODE)
           ==================================================================== */
        
        /* CSS Variabel untuk Tema Terang */
        :root {
            --bg-color: #f0f2f5;
            --card-bg: #ffffff;
            --text-color: #1c1e21;
            --text-color-secondary: #65676b;
            --border-color: #dddfe2;
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --priority-high: #dc3545;
            --priority-medium: #ffc107;
            --priority-low: #28a745;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --font-family: 'Roboto', 'Segoe UI', sans-serif;
        }

        /* CSS Variabel untuk Tema Gelap */
        [data-theme="dark"] {
            --bg-color: #18191a;
            --card-bg: #242526;
            --text-color: #e4e6eb;
            --text-color-secondary: #b0b3b8;
            --border-color: #3e4042;
            --primary-color: #2d88ff;
            --primary-hover: #1a6dff;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
        }

        /* Reset dan Styling Dasar */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 250px 1fr;
            grid-template-rows: auto 1fr;
            gap: 20px;
            min-height: 100vh;
        }

        /* Header */
        .header {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--success-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Sidebar */
        .sidebar {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .sidebar h2 { font-size: 1.2rem; margin-bottom: 15px; }
        .sidebar .section { margin-bottom: 25px; }
        .sidebar .section a { display: block; padding: 8px 0; color: var(--text-color-secondary); text-decoration: none; transition: color 0.2s; }
        .sidebar .section a:hover { color: var(--primary-color); }
        .sidebar .section a i { margin-right: 10px; width: 20px; text-align: center; }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .controls, .stats-container {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .controls { display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .controls input[type="text"], .controls select { padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-color); color: var(--text-color); }
        .controls .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; transition: transform 0.2s, box-shadow 0.2s; }
        .controls .btn-primary { background-color: var(--primary-color); color: white; }
        .controls .btn-primary:hover { background-color: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,123,255,0.3); }
        
        .stats-container { display: flex; justify-content: space-around; align-items: center; }
        .stats-container .stat-item { text-align: center; }
        .stats-container .stat-number { font-size: 2rem; font-weight: bold; }
        .stats-container .stat-label { color: var(--text-color-secondary); }
        .chart-container { max-width: 200px; max-height: 200px; }

        /* Task List */
        .task-list-container {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            flex-grow: 1;
            overflow-y: auto;
        }

        .task-list-container h2 { margin-bottom: 20px; }
        .task-list { list-style-type: none; }
        .task-item {
            background-color: var(--bg-color);
            border-left: 5px solid;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
            animation: fadeIn 0.3s ease-out;
        }
        .task-item:hover { transform: translateX(5px); box-shadow: var(--shadow); }
        .task-item.priority-high { border-left-color: var(--priority-high); }
        .task-item.priority-medium { border-left-color: var(--priority-medium); }
        .task-item.priority-low { border-left-color: var(--priority-low); }
        .task-item.completed { opacity: 0.6; }
        .task-item.completed .task-info .task-text { text-decoration: line-through; }

        .task-info { flex-grow: 1; }
        .task-info .task-text { font-size: 1.1rem; font-weight: 500; }
        .task-info .task-meta { font-size: 0.85rem; color: var(--text-color-secondary); margin-top: 5px; }
        .task-info .task-meta span { margin-right: 15px; }
        .task-info .task-meta i { margin-right: 5px; }

        .task-actions { display: flex; gap: 10px; }
        .task-actions button { background: none; border: none; color: var(--text-color-secondary); cursor: pointer; font-size: 1.1rem; padding: 5px; transition: color 0.2s; }
        .task-actions button:hover { color: var(--primary-color); }
        .task-actions button.delete:hover { color: var(--danger-color); }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); animation: fadeIn 0.3s; }
        .modal.show { display: flex; justify-content: center; align-items: center; }
        .modal-content { background-color: var(--card-bg); padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); animation: slideUp 0.3s; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h2 { margin: 0; }
        .close { font-size: 28px; font-weight: bold; cursor: pointer; color: var(--text-color-secondary); }
        .close:hover { color: var(--danger-color); }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body label { display: block; margin-bottom: 5px; font-weight: 500; }
        .modal-body input, .modal-body select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background-color: var(--bg-color); color: var(--text-color); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .modal-footer .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .modal-footer .btn-primary { background-color: var(--primary-color); color: white; }
        .modal-footer .btn-secondary { background-color: var(--text-color-secondary); color: white; }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container { grid-template-columns: 1fr; }
            .sidebar { position: static; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <h1><i class="fas fa-rocket"></i> Productivity Dashboard</h1>
            <button id="theme-toggle" class="btn btn-primary" style="background:var(--text-color-secondary); padding:10px 15px;">
                <i class="fas fa-moon"></i>
            </button>
        </header>

        <aside class="sidebar">
            <div class="section">
                <h2>Filter</h2>
                <a href="#" data-filter="all"><i class="fas fa-list"></i> Semua Tugas</a>
                <a href="#" data-filter="active"><i class="fas fa-clock"></i> Aktif</a>
                <a href="#" data-filter="completed"><i class="fas fa-check-circle"></i> Selesai</a>
            </div>
            <div class="section">
                <h2>Prioritas</h2>
                <a href="#" data-priority="high"><i class="fas fa-exclamation-circle" style="color:var(--priority-high)"></i> Tinggi</a>
                <a href="#" data-priority="medium"><i class="fas fa-exclamation-triangle" style="color:var(--priority-medium)"></i> Sedang</a>
                <a href="#" data-priority="low"><i class="fas fa-info-circle" style="color:var(--priority-low)"></i> Rendah</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="controls">
                <input type="text" id="search-input" placeholder="Cari tugas...">
                <select id="category-filter">
                    <option value="">Semua Kategori</option>
                </select>
                <button id="add-task-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Tugas Baru</button>
            </div>

            <div class="stats-container">
                <div class="stat-item">
                    <div class="stat-number" id="total-tasks">0</div>
                    <div class="stat-label">Total Tugas</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="active-tasks">0</div>
                    <div class="stat-label">Aktif</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number" id="completed-tasks">0</div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="chart-container">
                    <canvas id="progress-chart"></canvas>
                </div>
            </div>

            <div class="task-list-container">
                <h2>Daftar Tugas</h2>
                <ul id="task-list" class="task-list">
                    <!-- Tugas akan dimuat di sini oleh JavaScript -->
                </ul>
            </div>
        </main>
    </div>

    <!-- Modal untuk Tambah/Edit Tugas -->
    <div id="task-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Tambah Tugas Baru</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="task-form">
                    <input type="hidden" id="task-id">
                    <div class="form-group">
                        <label for="task-text">Teks Tugas</label>
                        <input type="text" id="task-text" required>
                    </div>
                    <div class="form-group">
                        <label for="task-category">Kategori</label>
                        <input type="text" id="task-category" placeholder="misal: Pekerjaan">
                    </div>
                    <div class="form-group">
                        <label for="task-priority">Prioritas</label>
                        <select id="task-priority">
                            <option value="low">Rendah</option>
                            <option value="medium" selected>Sedang</option>
                            <option value="high">Tinggi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="task-due-date">Tanggal Jatuh Tempo</label>
                        <input type="date" id="task-due-date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-btn">Batal</button>
                <button type="submit" form="task-form" class="btn btn-primary">Simpan Tugas</button>
            </div>
        </div>
    </div>

    <script>
        // ====================================================================
        // LOGIKA KLIEN (JAVASCRIPT LANJUTAN)
        // ====================================================================
        document.addEventListener('DOMContentLoaded', () => {
            // --- Elemen DOM & Konfigurasi ---
            const API_URL = 'index.php/api/tasks';
            const modal = document.getElementById('task-modal');
            const taskForm = document.getElementById('task-form');
            const taskList = document.getElementById('task-list');
            const searchInput = document.getElementById('search-input');
            const categoryFilter = document.getElementById('category-filter');
            const themeToggle = document.getElementById('theme-toggle');
            
            let tasks = [];
            let currentFilter = 'all';
            let currentPriorityFilter = '';
            let chart = null; // Variabel untuk menyimpan instance chart

            // --- Fungsi API ---
            const apiCall = async (url, options = {}) => {
                try {
                    const response = await fetch(url, {
                        headers: { 'Content-Type': 'application/json', ...options.headers },
                        ...options
                    });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    return await response.json();
                } catch (error) {
                    console.error('API Call Error:', error);
                    alert('Terjadi kesalahan saat menghubungi server.');
                    return null;
                }
            };

            // --- Fungsi Fetch & Render ---
            const fetchTasks = async () => {
                const data = await apiCall(API_URL);
                if (data) {
                    tasks = data;
                    renderTasks();
                    updateStats();
                    updateCategoryFilter();
                }
            };

            const renderTasks = () => {
                let filteredTasks = tasks;

                // Filter berdasarkan status
                if (currentFilter === 'active') {
                    filteredTasks = filteredTasks.filter(t => !t.completed);
                } else if (currentFilter === 'completed') {
                    filteredTasks = filteredTasks.filter(t => t.completed);
                }

                // Filter berdasarkan prioritas
                if (currentPriorityFilter) {
                    filteredTasks = filteredTasks.filter(t => t.priority === currentPriorityFilter);
                }
                
                // Filter berdasarkan pencarian
                const searchTerm = searchInput.value.toLowerCase();
                if (searchTerm) {
                    filteredTasks = filteredTasks.filter(t => 
                        t.text.toLowerCase().includes(searchTerm) || 
                        t.category.toLowerCase().includes(searchTerm)
                    );
                }

                // Filter berdasarkan kategori
                const categoryTerm = categoryFilter.value.toLowerCase();
                if (categoryTerm) {
                    filteredTasks = filteredTasks.filter(t => 
                        t.category.toLowerCase() === categoryTerm
                    );
                }

                taskList.innerHTML = '';
                if (filteredTasks.length === 0) {
                    taskList.innerHTML = '<li style="text-align: center; padding: 20px; color: var(--text-color-secondary);">Tidak ada tugas yang cocok.</li>';
                    return;
                }

                filteredTasks.forEach(task => {
                    const li = document.createElement('li');
                    li.className = `task-item priority-${task.priority} ${task.completed ? 'completed' : ''}`;
                    li.dataset.id = task.id;
                    
                    const dueDate = task.dueDate ? new Date(task.dueDate).toLocaleDateString('id-ID') : 'Tanpa Tenggat';
                    const category = task.category || 'Tanpa Kategori';

                    li.innerHTML = `
                        <div class="task-info">
                            <div class="task-text">${task.text}</div>
                            <div class="task-meta">
                                <span><i class="fas fa-tag"></i> ${category}</span>
                                <span><i class="fas fa-calendar"></i> ${dueDate}</span>
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="complete-btn" title="Tandai Selesai"><i class="fas fa-${task.completed ? 'undo' : 'check'}"></i></button>
                            <button class="edit-btn" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="delete-btn" title="Hapus"><i class="fas fa-trash"></i></button>
                        </div>
                    `;
                    taskList.appendChild(li);
                });
            };
            
            const updateStats = () => {
                const total = tasks.length;
                const completed = tasks.filter(t => t.completed).length;
                const active = total - completed;

                document.getElementById('total-tasks').textContent = total;
                document.getElementById('active-tasks').textContent = active;
                document.getElementById('completed-tasks').textContent = completed;

                // Update Chart
                const ctx = document.getElementById('progress-chart').getContext('2d');
                if (chart) chart.destroy(); // Hancurkan chart lama sebelum membuat yang baru
                
                chart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Selesai', 'Aktif'],
                        datasets: [{
                            data: [completed, active],
                            backgroundColor: [ 'var(--success-color)', 'var(--primary-color)'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: 'var(--text-color)' } }
                        }
                    }
                });
            };

            const updateCategoryFilter = () => {
                const categories = [...new Set(tasks.map(t => t.category).filter(Boolean))];
                categoryFilter.innerHTML = '<option value="">Semua Kategori</option>';
                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    categoryFilter.appendChild(option);
                });
            };

            // --- Fungsi Modal ---
            const openModal = (task = null) => {
                modal.classList.add('show');
                document.getElementById('modal-title').textContent = task ? 'Edit Tugas' : 'Tambah Tugas Baru';
                document.getElementById('task-id').value = task ? task.id : '';
                document.getElementById('task-text').value = task ? task.text : '';
                document.getElementById('task-category').value = task ? task.category : '';
                document.getElementById('task-priority').value = task ? task.priority : 'medium';
                document.getElementById('task-due-date').value = task ? task.dueDate : '';
            };
            const closeModal = () => modal.classList.remove('show');

            // --- Event Listeners ---
            // Form Submit (Tambah/Edit)
            taskForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const id = document.getElementById('task-id').value;
                const taskData = {
                    text: document.getElementById('task-text').value,
                    category: document.getElementById('task-category').value,
                    priority: document.getElementById('task-priority').value,
                    dueDate: document.getElementById('task-due-date').value,
                };

                if (id) { // Edit
                    await apiCall(`${API_URL}?id=${id}`, { method: 'PUT', body: JSON.stringify(taskData) });
                } else { // Add
                    await apiCall(API_URL, { method: 'POST', body: JSON.stringify(taskData) });
                }
                fetchTasks();
                closeModal();
            });

            // Tombol Tambah Buka Modal
            document.getElementById('add-task-btn').addEventListener('click', () => openModal());

            // Tombol Tutup Modal
            document.querySelector('.close').addEventListener('click', closeModal);
            document.getElementById('cancel-btn').addEventListener('click', closeModal);

            // Event Delegation untuk Aksi di Task List
            taskList.addEventListener('click', async (e) => {
                const taskItem = e.target.closest('.task-item');
                if (!taskItem) return;
                const id = taskItem.dataset.id;
                const task = tasks.find(t => t.id === id);

                if (e.target.closest('.complete-btn')) {
                    task.completed = !task.completed;
                    await apiCall(`${API_URL}?id=${id}`, { method: 'PUT', body: JSON.stringify(task) });
                    fetchTasks();
                } else if (e.target.closest('.edit-btn')) {
                    openModal(task);
                } else if (e.target.closest('.delete-btn')) {
                    if (confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
                        await apiCall(`${API_URL}?id=${id}`, { method: 'DELETE' });
                        fetchTasks();
                    }
                }
            });
            
            // Event Listener untuk Filter & Pencarian
            searchInput.addEventListener('input', renderTasks);
            categoryFilter.addEventListener('change', renderTasks);
            
            document.querySelectorAll('.sidebar a[data-filter]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentFilter = e.currentTarget.dataset.filter;
                    renderTasks();
                });
            });
            document.querySelectorAll('.sidebar a[data-priority]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentPriorityFilter = e.currentTarget.dataset.priority;
                    renderTasks();
                });
            });

            // Dark Mode Toggle
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', currentTheme);
            themeToggle.innerHTML = currentTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            
            themeToggle.addEventListener('click', () => {
                const theme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                themeToggle.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });

            // --- Inisialisasi Awal ---
            fetchTasks();
        });
    </script>
</body>
</html>