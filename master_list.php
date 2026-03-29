<?php
// master_list.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

$alert_msg = "";
$alert_type = "";

// --- 1. HANDLE NEW MAP IMAGE UPLOAD ---
if(isset($_POST['upload_map']) && isset($_FILES['map_image']) && $_FILES['map_image']['error'] == 0){
    $target_dir = "uploads/";
    if(!is_dir($target_dir)) mkdir($target_dir);
    
    // Get file extension and force a specific name to overwrite the old one
    $ext = pathinfo($_FILES['map_image']['name'], PATHINFO_EXTENSION);
    $mapPath = $target_dir . "master_scheme_map." . $ext;
    
    // Delete existing map variations to prevent conflicts
    @unlink($target_dir . "master_scheme_map.png");
    @unlink($target_dir . "master_scheme_map.jpg");
    @unlink($target_dir . "master_scheme_map.jpeg");

    if(move_uploaded_file($_FILES['map_image']['tmp_name'], $mapPath)){
        $alert_msg = "New Scheme Map uploaded successfully!";
        $alert_type = "success";
    } else {
        $alert_msg = "Failed to upload the map image.";
        $alert_type = "error";
    }
}

// Determine which map image to show (Checks uploads folder first, falls back to default)
$current_map = "assets/map.png"; 
if(file_exists("uploads/master_scheme_map.png")) $current_map = "uploads/master_scheme_map.png";
elseif(file_exists("uploads/master_scheme_map.jpg")) $current_map = "uploads/master_scheme_map.jpg";
elseif(file_exists("uploads/master_scheme_map.jpeg")) $current_map = "uploads/master_scheme_map.jpeg";
// --------------------------------------


$lots = [];
$statusCounts = [
    'AVAILABLE' => 0,
    'SOLD' => 0,
    'RESERVED' => 0
];

// Fetch all lots
$sql = "SELECT * FROM lots ORDER BY CAST(block_no AS UNSIGNED), CAST(lot_no AS UNSIGNED)";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $lots[] = $row;
        $status = strtoupper($row['status']);
        if (isset($statusCounts[$status])) {
            $statusCounts[$status]++;
        }
    }
}
$totalLots = count($lots);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master List & Map | JEJ Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    
    <style>
        body { background-color: #F7FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #EDF2F7; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; }
        .brand-box { padding: 25px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 20px 10px; flex: 1; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #718096; text-decoration: none; font-weight: 600; border-radius: 12px; margin-bottom: 5px; transition: all 0.2s; }
        .menu-link:hover, .menu-link.active { background: #F0FFF4; color: var(--primary); }
        .menu-link i { width: 20px; text-align: center; }
        .main-panel { margin-left: 260px; flex: 1; padding: 30px 40px; width: calc(100% - 260px); }
        
        /* Stats Styling */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); display: flex; flex-direction: column; }
        .stat-card span { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #A0AEC0; margin-bottom: 5px; }
        .stat-card strong { font-size: 28px; font-weight: 800; color: var(--dark); }
        .sc-avail { border-bottom: 4px solid #48BB78; }
        .sc-res { border-bottom: 4px solid #F6AD55; }
        .sc-sold { border-bottom: 4px solid #E53E3E; }
        .sc-total { border-bottom: 4px solid #3182CE; }

        /* Map UI Styling */
        .map-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); padding: 20px; margin-bottom: 30px; }
        .map-toolbar { display: flex; gap: 10px; margin-bottom: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between; }
        .map-toolbar-left { display: flex; gap: 10px; align-items: center; flex: 1; }
        .map-toolbar input[type="text"], .map-toolbar select, .map-toolbar button { padding: 10px 15px; border-radius: 8px; border: 1px solid #E2E8F0; font-family: inherit; font-size: 13px; outline: none; }
        .map-toolbar input[type="text"] { min-width: 200px; }
        .map-toolbar button { background: var(--primary); color: white; border: none; font-weight: 600; cursor: pointer; }
        .map-toolbar button:hover { opacity: 0.9; }
        
        .legend { display: flex; gap: 15px; font-size: 12px; font-weight: 600; color: #4A5568; margin-bottom: 15px;}
        .legend span { display: flex; align-items: center; gap: 5px; }
        .legend i { width: 14px; height: 14px; border-radius: 3px; border: 1px solid rgba(0,0,0,0.1); }

        .map-wrapper { width: 100%; overflow: auto; border-radius: 12px; border: 1px solid #E2E8F0; background: #EDF2F7; position: relative; }
        #schemeMap { width: 100%; min-width: 1000px; display: block; }
        
        /* Interactive Polygon Styling */
        .lot { stroke: #ffffff; stroke-width: 1.5; cursor: pointer; transition: all 0.3s ease; }
        .lot:hover { stroke: #2D3748; stroke-width: 3; filter: brightness(1.1); }
        .lot.available { fill: rgba(72, 187, 120, 0.6); }  /* Green */
        .lot.reserved { fill: rgba(246, 173, 85, 0.6); }   /* Orange */
        .lot.sold { fill: rgba(229, 62, 62, 0.85); stroke: #9B2C2C; } /* Bold Red for Sold */
        .lot.hidden-by-filter { opacity: 0; pointer-events: none; } /* Used for filter isolation */
        
        /* Pinpoint Locate Highlight Styling */
        .lot.lot-dimmed { opacity: 0.15 !important; pointer-events: none; }
        .lot.lot-focused { 
            stroke: #3182CE !important; 
            stroke-width: 6 !important; 
            animation: pulseLot 1.5s infinite; 
            z-index: 100;
        }
        @keyframes pulseLot {
            0% { filter: drop-shadow(0 0 2px #3182CE) brightness(1); }
            50% { filter: drop-shadow(0 0 15px #3182CE) brightness(1.5); }
            100% { filter: drop-shadow(0 0 2px #3182CE) brightness(1); }
        }

        /* Table Styling */
        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 18px; font-weight: 800; color: var(--dark); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: middle; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; border: none; transition: 0.2s;}
        .btn-action:hover { opacity: 0.8; }
        .btn-locate { background: #EBF8FF; color: #3182CE; }
        .btn-edit { background: #EDF2F7; color: #4A5568; }
        
        /* Modal & Form Styling */
        .modal { display: none; position: fixed; z-index: 9999; inset: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(2px); padding: 30px; overflow-y: auto; }
        .modal-content { max-width: 550px; margin: 5vh auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; background: #F7FAFC; }
        .modal-header h2 { margin: 0; font-size: 18px; color: var(--dark); }
        .close-btn { background: none; border: none; font-size: 20px; color: #A0AEC0; cursor: pointer; }
        #modalBody { padding: 25px; }

        /* Alert Styling */
        .alert-box { padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; font-size: 14px; }
        .alert-success { background: #F0FFF4; color: #2F855A; border: 1px solid #C6F6D5; }
        .alert-error { background: #FFF5F5; color: #C53030; border: 1px solid #FED7D7; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="brand-box">
            <img src="assets/logo.png" style="height: 40px; width: auto; border-radius: 6px; margin-right: 10px;">
            <span style="font-size: 18px; font-weight: 800; color: var(--primary);">JEJ Admin</span>
        </div>
        
        <div class="sidebar-menu">
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-bottom: 10px;">MAIN MENU</small>
            <a href="admin.php?view=dashboard" class="menu-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link active"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-plus-circle"></i> Add Property</a>
            <a href="financial.php" class="menu-link"><i class="fa-solid fa-coins"></i> Financials</a>
            <a href="payment_tracking.php" class="menu-link"><i class="fa-solid fa-file-invoice-dollar"></i> Payment Tracking</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="audit_logs.php" class="menu-link"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a>
            <a href="delete_history.php" class="menu-link"><i class="fa-solid fa-trash-can"></i> Delete History</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 25px;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Master List & Scheme Map</h1>
            <p style="color: #718096;">Interactive subdivision map and complete property inventory.</p>
        </div>

        <?php if($alert_msg): ?>
            <div class="alert-box <?= $alert_type == 'success' ? 'alert-success' : 'alert-error' ?>">
                <i class="fa-solid <?= $alert_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" style="margin-right: 8px;"></i>
                <?= $alert_msg ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card sc-total">
                <span>Total Lots</span>
                <strong><?= $totalLots ?></strong>
            </div>
            <div class="stat-card sc-avail">
                <span>Available</span>
                <strong><?= $statusCounts['AVAILABLE'] ?></strong>
            </div>
            <div class="stat-card sc-res">
                <span>Reserved</span>
                <strong><?= $statusCounts['RESERVED'] ?></strong>
            </div>
            <div class="stat-card sc-sold">
                <span>Sold</span>
                <strong><?= $statusCounts['SOLD'] ?></strong>
            </div>
        </div>

        <div class="map-container">
            <div class="map-toolbar">
                <div class="map-toolbar-left">
                    <i class="fa-solid fa-search" style="color: #A0AEC0; margin-left: 5px;"></i>
                    <input type="text" id="searchLot" placeholder="Search by Block or Lot No...">
                    <select id="filterStatus">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="sold">Sold</option>
                    </select>
                    <button type="button" onclick="resetFilters()"><i class="fa-solid fa-rotate-right"></i> Reset Search</button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" style="display:flex; gap:10px; align-items:center; background: #F7FAFC; padding: 5px 15px; border-radius: 8px; border: 1px solid #E2E8F0;">
                    <span style="font-size: 11px; font-weight: 700; color: #718096; text-transform: uppercase;">Map Background:</span>
                    <input type="file" name="map_image" accept="image/*" required style="font-size: 12px; max-width: 180px;">
                    <button type="submit" name="upload_map" style="background: #2D3748; padding: 6px 12px; font-size: 12px;"><i class="fa-solid fa-upload"></i> Upload</button>
                </form>
            </div>

            <div class="legend">
                <span><i style="background: rgba(72, 187, 120, 0.8);"></i> Available</span>
                <span><i style="background: rgba(246, 173, 85, 0.8);"></i> Reserved</span>
                <span><i style="background: rgba(229, 62, 62, 0.9);"></i> Sold</span>
            </div>

            <div class="map-wrapper" id="svgWrapper">
                <svg id="schemeMap" viewBox="0 0 1464 1052" preserveAspectRatio="xMidYMid meet">
                    <image href="<?= $current_map ?>?v=<?= time() ?>" x="0" y="0" width="1464" height="1052"></image>

                    <?php foreach ($lots as $lot): ?>
                        <?php
                            $statusClass = strtolower($lot['status']);
                            $dataBlock = htmlspecialchars($lot['block_no']);
                            $dataLot = htmlspecialchars($lot['lot_no']);
                            $dataStatus = htmlspecialchars($lot['status']);
                            $dataId = (int)$lot['id'];
                            $points = isset($lot['coordinates']) ? htmlspecialchars($lot['coordinates']) : ''; 
                        ?>
                        <?php if(!empty($points)): ?>
                        <polygon
                            class="lot <?= $statusClass ?>"
                            points="<?= $points ?>"
                            data-id="<?= $dataId ?>"
                            data-block="<?= $dataBlock ?>"
                            data-lot="<?= $dataLot ?>"
                            data-status="<?= $dataStatus ?>"
                            onclick="openLotDetails(<?= $dataId ?>)"
                        >
                            <title>Block <?= $dataBlock ?> - Lot <?= $dataLot ?> (<?= $dataStatus ?>)</title>
                        </polygon>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </svg>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <span class="table-title"><i class="fa-solid fa-list" style="color: #3182CE; margin-right: 8px;"></i> Master List Directory</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Location</th>
                        <th>Block/Lot</th>
                        <th>Area</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="masterTableBody">
                    <?php foreach($lots as $lot): ?>
                    <tr class="lot-row" data-block="<?= strtolower($lot['block_no']) ?>" data-lot="<?= strtolower($lot['lot_no']) ?>" data-status="<?= strtolower($lot['status']) ?>">
                        <td><img src="uploads/<?= $lot['lot_image']?:'default_lot.jpg' ?>" style="width: 45px; height: 45px; object-fit: cover; border-radius: 8px;"></td>
                        <td>
                            <strong><?= $lot['location'] ?? 'N/A' ?></strong>
                            <div style="font-size: 11px; color: #A0AEC0;"><?= $lot['property_type'] ?></div>
                        </td>
                        <td style="font-weight: 700;">B-<?= $lot['block_no'] ?> L-<?= $lot['lot_no'] ?></td>
                        <td><?= $lot['area'] ?> sqm</td>
                        <td style="font-family: 'Open Sans', sans-serif; font-weight: 600;">₱<?= number_format($lot['total_price']) ?></td>
                        <td>
                            <?php 
                                $badges = ['AVAILABLE' => ['bg'=>'#C6F6D5', 'col'=>'#22543D'], 'RESERVED'  => ['bg'=>'#FEEBC8', 'col'=>'#744210'], 'SOLD'      => ['bg'=>'#FED7D7', 'col'=>'#822727']];
                                $b = $badges[strtoupper($lot['status'])] ?? $badges['AVAILABLE'];
                            ?>
                            <span style="background: <?= $b['bg'] ?>; color: <?= $b['col'] ?>; padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 800;"><?= strtoupper($lot['status']) ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn-action btn-locate" onclick="locateLot(<?= $lot['id'] ?>)"><i class="fa-solid fa-location-dot"></i> Locate</button>
                            <button type="button" class="btn-action btn-edit" onclick="openLotDetails(<?= $lot['id'] ?>)"><i class="fa-solid fa-pen"></i> Quick Edit</button>
                            <a href="admin.php?view=inventory&edit_id=<?= $lot['id'] ?>" class="btn-action" style="background:#F7FAFC; color:#718096; border: 1px solid #E2E8F0;"><i class="fa-solid fa-gear"></i> Full Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="lotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Quick Edit Property</h2>
                <button class="close-btn" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div id="modalBody">
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('lotModal');
        const modalBody = document.getElementById('modalBody');

        // --- 1. PINPOINT/LOCATE LOT ON MAP ---
        function locateLot(id) {
            // Scroll smoothly to the map
            document.getElementById('svgWrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Highlight the specific lot and dim others
            document.querySelectorAll('polygon.lot').forEach(lot => {
                if(parseInt(lot.dataset.id) === parseInt(id)) {
                    lot.classList.remove('hidden-by-filter');
                    lot.classList.remove('lot-dimmed');
                    lot.classList.add('lot-focused');
                } else {
                    lot.classList.remove('lot-focused');
                    lot.classList.add('lot-dimmed');
                }
            });

            // Show a "Clear Focus" button in the toolbar if it's not already there
            let resetBtn = document.getElementById('clearFocusBtn');
            if(!resetBtn) {
                resetBtn = document.createElement('button');
                resetBtn.id = 'clearFocusBtn';
                resetBtn.type = 'button';
                resetBtn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Clear Map Focus';
                resetBtn.style.cssText = "background: #E53E3E; color: white; border: none; padding: 10px 15px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-left: 10px; animation: pulseLot 1.5s infinite;";
                resetBtn.onclick = function() {
                    restoreMapVisibility();
                };
                document.querySelector('.map-toolbar-left').appendChild(resetBtn);
            }
        }


        // --- 2. OPEN MODAL & ISOLATE POLYGON ---
        function openLotDetails(id) {
            if (isDrawing) return; 

            // Trigger the same visual highlighting as the locate button
            locateLot(id);

            modal.style.display = 'block';
            modalBody.innerHTML = '<p style="text-align:center; color:#718096; padding: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading data...</p>';

            // Load the edit form from get_lot.php
            fetch('get_lot.php?id=' + encodeURIComponent(id))
                .then(response => response.text())
                .then(html => { modalBody.innerHTML = html; })
                .catch(() => { modalBody.innerHTML = '<p style="color:#E53E3E; text-align:center; padding: 20px;">Failed to load data. Please check your connection.</p>'; });
        }

        // Close modal and restore visibility to all lots
        function closeModal() { 
            modal.style.display = 'none'; 
            restoreMapVisibility();
        }

        function restoreMapVisibility() {
            document.querySelectorAll('polygon.lot').forEach(lot => {
                lot.classList.remove('hidden-by-filter');
                lot.classList.remove('lot-focused');
                lot.classList.remove('lot-dimmed');
            });
            applyFilters(); // Re-apply search filters if any were typed
            
            // Remove the clear focus button
            let resetBtn = document.getElementById('clearFocusBtn');
            if(resetBtn) resetBtn.remove();
        }

        window.onclick = function(event) { 
            if (event.target === modal) closeModal(); 
        };


        // --- 3. AJAX FORM SUBMISSION (Save changes without reloading) ---
        function saveLot(event) {
            event.preventDefault(); 

            const form = document.getElementById('lotForm');
            const formData = new FormData(form);
            
            let saveResult = document.getElementById('saveResult');
            if (!saveResult) {
                saveResult = document.createElement('div');
                saveResult.id = 'saveResult';
                saveResult.style.marginTop = '15px';
                saveResult.style.textAlign = 'center';
                form.appendChild(saveResult);
            }

            saveResult.innerHTML = '<p style="color:#3182CE; font-size:14px; font-weight:700;"><i class="fa-solid fa-spinner fa-spin"></i> Saving changes...</p>';

            // Post to save_lot.php
            fetch('save_lot.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    saveResult.innerHTML = '<p style="color:#48BB78; font-weight:bold; font-size:14px;"><i class="fa-solid fa-check-circle"></i> ' + data.message + '</p>';
                    setTimeout(() => { location.reload(); }, 800);
                } else {
                    saveResult.innerHTML = '<p style="color:#E53E3E; font-weight:bold; font-size:14px;"><i class="fa-solid fa-circle-exclamation"></i> ' + data.message + '</p>';
                }
            })
            .catch(() => {
                saveResult.innerHTML = '<p style="color:#E53E3E; font-weight:bold; font-size:14px;"><i class="fa-solid fa-circle-exclamation"></i> Server communication error.</p>';
            });
        }


        // --- 4. INTERACTIVE MAP PINNING TOOL ---
        let isDrawing = false;
        let tempPoints = [];
        let tempPolygon = null;

        function startDrawing() {
            modal.style.display = 'none'; 
            isDrawing = true;
            tempPoints = [];
            
            // Show drawing instruction banner
            let banner = document.getElementById('drawBanner');
            if(!banner) {
                banner = document.createElement('div');
                banner.id = 'drawBanner';
                banner.innerHTML = `
                    <div style="display:flex; align-items:center; gap:15px;">
                        <span><i class="fa-solid fa-pen-ruler"></i> <strong>Map Pin Mode:</strong> Click the corners of the lot on the map to draw its shape.</span>
                        <button onclick="finishDrawing()" style="background: #48BB78; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 800; font-size:13px;">Done</button> 
                        <button onclick="cancelDrawing()" style="background: #E53E3E; color: white; border: none; padding: 8px 20px; border-radius: 6px; cursor: pointer; font-weight: 800; font-size:13px;">Cancel</button>
                    </div>
                `;
                banner.style.cssText = "position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #2D3748; color: white; padding: 15px 25px; border-radius: 12px; z-index: 10000; box-shadow: 0 10px 25px rgba(0,0,0,0.3); font-size: 14px;";
                document.body.appendChild(banner);
            }
            banner.style.display = 'block';

            // Create temporary polygon
            const svg = document.getElementById('schemeMap');
            tempPolygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
            tempPolygon.setAttribute('fill', 'rgba(246, 173, 85, 0.6)'); 
            tempPolygon.setAttribute('stroke', '#DD6B20');
            tempPolygon.setAttribute('stroke-width', '4');
            svg.appendChild(tempPolygon);
            
            // Scroll user smoothly to the map
            document.getElementById('svgWrapper').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // SVG Click listener to plot points
        document.getElementById('schemeMap').addEventListener('click', function(e) {
            if(!isDrawing) return;
            
            const svg = document.getElementById('schemeMap');
            let pt = svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            
            // Convert screen coordinates to exact SVG map coordinates
            let svgP = pt.matrixTransform(svg.getScreenCTM().inverse());
            
            let x = Math.round(svgP.x * 10) / 10;
            let y = Math.round(svgP.y * 10) / 10;
            
            tempPoints.push(`${x},${y}`);
            tempPolygon.setAttribute('points', tempPoints.join(' '));
        });

        function finishDrawing() {
            isDrawing = false;
            document.getElementById('drawBanner').style.display = 'none';
            modal.style.display = 'block'; 
            
            if(tempPoints.length > 2) {
                // Find the points textarea and fill it
                let pointsInput = document.getElementById('polygonPoints');
                if(pointsInput) pointsInput.value = tempPoints.join(' ');
            } else {
                alert("Please click at least 3 points on the map to create a valid shape.");
            }
            if(tempPolygon) tempPolygon.remove();
        }

        function cancelDrawing() {
            isDrawing = false;
            document.getElementById('drawBanner').style.display = 'none';
            modal.style.display = 'block';
            if(tempPolygon) tempPolygon.remove();
        }


        // --- 5. SEARCH & FILTER LOGIC (Filters map & table simultaneously) ---
        function applyFilters() {
            const searchValue = document.getElementById('searchLot').value.trim().toLowerCase();
            const statusValue = document.getElementById('filterStatus').value.trim().toLowerCase();

            // Filter Map Polygons
            document.querySelectorAll('polygon.lot').forEach(lot => {
                const block = (lot.dataset.block || '').toLowerCase();
                const lotNo = (lot.dataset.lot || '').toLowerCase();
                const status = (lot.dataset.status || '').toLowerCase();

                const matchesSearch = searchValue === '' || block.includes(searchValue) || lotNo.includes(searchValue) || (`b-${block} l-${lotNo}`).includes(searchValue) || (`block ${block} lot ${lotNo}`).includes(searchValue);
                const matchesStatus = statusValue === '' || status === statusValue;

                if (matchesSearch && matchesStatus) {
                    // Only remove hidden filter if it's not currently dimmed by the locate function
                    if(!lot.classList.contains('lot-dimmed')) lot.classList.remove('hidden-by-filter');
                } else {
                    lot.classList.add('hidden-by-filter');
                }
            });

            // Filter Table Rows
            document.querySelectorAll('.lot-row').forEach(row => {
                const block = row.dataset.block;
                const lotNo = row.dataset.lot;
                const status = row.dataset.status;

                const matchesSearch = searchValue === '' || block.includes(searchValue) || lotNo.includes(searchValue) || (`b-${block} l-${lotNo}`).includes(searchValue);
                const matchesStatus = statusValue === '' || status === statusValue;

                if (matchesSearch && matchesStatus) row.style.display = '';
                else row.style.display = 'none';
            });
        }

        function resetFilters() {
            document.getElementById('searchLot').value = '';
            document.getElementById('filterStatus').value = '';
            restoreMapVisibility(); // Clear out all filters and focus
        }

        document.getElementById('searchLot').addEventListener('input', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
    </script>
</body>
</html>