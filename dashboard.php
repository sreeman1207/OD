<?php
// Start session and prevent session fixation
session_start();

// Check if user is authenticated; redirect to login if not
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection instance ($pdo)
require_once __DIR__ . '/db.php';

$message = '';
$message_type = ''; // 'success' or 'error'

// Handle CSV Import Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['csv_file']['tmp_name'];
        $file_name     = $_FILES['csv_file']['name'];
        $file_ext      = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Validate extension
        if ($file_ext !== 'csv') {
            $message = 'Please upload a valid .csv file format.';
            $message_type = 'error';
        } else {
            try {
                if (($handle = fopen($file_tmp_path, 'r')) !== false) {
                    // Skip header row
                    $headers = fgetcsv($handle, 2000, ',');

                    // Prepared query matching all 7 schema columns
                    $sql = "INSERT INTO records (
                                column_one, 
                                column_two, 
                                column_three, 
                                column_four, 
                                column_five, 
                                column_six, 
                                column_seven
                            ) VALUES (
                                :col1, :col2, :col3, :col4, :col5, :col6, :col7
                            )";
                    
                    $stmt = $pdo->prepare($sql);

                    $pdo->beginTransaction();
                    $imported_count = 0;

                    while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                        // Skip completely empty rows
                        if (empty(array_filter($row, fn($val) => trim($val) !== ''))) {
                            continue;
                        }

                        $stmt->execute([
                            ':col1' => isset($row[0]) && trim($row[0]) !== '' ? trim($row[0]) : null,
                            ':col2' => isset($row[1]) && trim($row[1]) !== '' ? trim($row[1]) : null,
                            ':col3' => isset($row[2]) && trim($row[2]) !== '' ? trim($row[2]) : null,
                            ':col4' => isset($row[3]) && trim($row[3]) !== '' ? trim($row[3]) : null,
                            ':col5' => isset($row[4]) && trim($row[4]) !== '' ? trim($row[4]) : null,
                            ':col6' => isset($row[5]) && trim($row[5]) !== '' ? trim($row[5]) : null,
                            ':col7' => isset($row[6]) && trim($row[6]) !== '' ? trim($row[6]) : null,
                        ]);
                        $imported_count++;
                    }

                    $pdo->commit();
                    fclose($handle);

                    $message = "Successfully imported {$imported_count} rows.";
                    $message_type = 'success';
                } else {
                    $message = 'Failed to open the uploaded CSV file.';
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                $message_type = 'error';
            }
        }
    } else {
        $message = 'Please select a valid CSV file to upload.';
        $message_type = 'error';
    }
}

// User details from session
$username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
$role     = htmlspecialchars($_SESSION['role'] ?? 'Member', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        :root {
            --bg-color: #f4f6f8;
            --card-bg: #ffffff;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
            --border-color: #e5e7eb;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: system-ui, -apple-system, sans-serif; }
        body { background: var(--bg-color); color: var(--text-main); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        aside { width: 240px; background: #111827; color: #fff; padding: 24px 16px; display: flex; flex-direction: column; justify-content: space-between; }
        aside h2 { font-size: 1.25rem; margin-bottom: 24px; color: #fff; }
        aside nav a { display: block; color: #9ca3af; text-decoration: none; padding: 10px 12px; border-radius: 6px; margin-bottom: 6px; }
        aside nav a.active, aside nav a:hover { background: #1f2937; color: #fff; }
        
        /* Main Content */
        main { flex: 1; padding: 32px; max-width: 1200px; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .card { background: var(--card-bg); padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; }
        .card h3 { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 8px; }
        .card .value { font-size: 1.75rem; font-weight: 700; }
        
        /* Alert Banners */
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 0.875rem; }
        .alert-success { background: var(--success-bg); color: var(--success-text); }
        .alert-error { background: var(--error-bg); color: var(--error-text); }

        /* Form Controls */
        .import-form { display: flex; flex-direction: column; gap: 16px; margin-top: 12px; }
        .file-input-wrapper { display: flex; align-items: center; gap: 12px; }
        input[type="file"] { border: 1px solid var(--border-color); padding: 8px 12px; border-radius: 6px; font-size: 0.875rem; background: #fff; }
        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 9px 18px; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: background 0.2s ease; width: fit-content; }
        .btn-submit:hover { background: var(--primary-hover); }

        .btn-logout { color: #ef4444; text-decoration: none; font-size: 0.875rem; }
    </style>
</head>
<body>

    <aside>
        <div>
            <h2>AppPanel</h2>
            <nav>
                <a href="dashboard.php" class="active">Overview</a>
                <a href="profile.php">Profile</a>
                <a href="settings.php">Settings</a>
            </nav>
        </div>
        <div>
            <a href="logout.php" class="btn-logout">Log Out</a>
        </div>
    </aside>

    <main>
        <header>
            <div>
                <h1>Welcome back, <?= $username ?>!</h1>
                <p style="color: var(--text-muted);">Role: <?= $role ?></p>
            </div>
        </header>

        <?php if (!empty($message)): ?>
            <div class="alert <?= $message_type === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <section class="grid">
            <div class="card">
                <h3>Total Activity</h3>
                <div class="value">128</div>
            </div>
            <div class="card">
                <h3>Pending Tasks</h3>
                <div class="value">4</div>
            </div>
            <div class="card">
                <h3>System Status</h3>
                <div class="value" style="color: #10b981; font-size: 1.25rem;">Active</div>
            </div>
        </section>

        <!-- CSV Import Card -->
        <section class="card">
            <h3 style="margin-bottom: 4px; font-size: 1rem; color: var(--text-main);">Import Data (CSV)</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Upload a 7-column CSV file to batch insert records into the database.</p>
            
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="import-form">
                <div class="file-input-wrapper">
                    <input type="file" name="csv_file" accept=".csv" required>
                    <button type="submit" name="import_csv" class="btn-submit">Upload &amp; Import</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h3 style="margin-bottom: 12px; font-size: 1rem; color: var(--text-main);">Recent Notifications</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Your account session was verified successfully.</p>
        </section>
    </main>
<!-- Add this script at the bottom of dashboard.php before </body> -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector(".import-form");
    const fileInput = document.querySelector("input[name='csv_file']");

    form.addEventListener("submit", (e) => {
        // Prevent default form submission if you want to store locally first
        // e.preventDefault();

        const file = fileInput.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const csvData = event.target.result;

                // Store raw CSV in localStorage
                localStorage.setItem("uploadedCSV", csvData);

                // Optionally parse and store as JSON
                const rows = csvData.split("\n").map(r => r.split(","));
                localStorage.setItem("uploadedCSV_JSON", JSON.stringify(rows));

                console.log("CSV stored in localStorage!");
            };
            reader.readAsText(file);
        }
    });
});

// Example API-like function to fetch stored data
function getStoredCSV() {
    const data = localStorage.getItem("uploadedCSV_JSON");
    return data ? JSON.parse(data) : [];
}
</script>

</body>
</html>
