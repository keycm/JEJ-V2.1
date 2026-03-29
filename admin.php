<?php
// admin.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

$view = $_GET['view'] ?? 'dashboard';
$edit_mode = false;
$edit_data = [];
$alert_msg = "";
$alert_type = "";

// --- HANDLING ALERTS ---
if(isset($_GET['msg'])){
    $m = $_GET['msg'];
    if($m=='added') { $alert_msg = "New property added successfully!"; $alert_type = "success"; }
    if($m=='updated') { $alert_msg = "Property details updated."; $alert_type = "success"; }
    if($m=='deleted') { $alert_msg = "Property deleted and moved to Archive."; $alert_type = "error"; }
}

// --- INVENTORY ACTIONS ---
if(isset($_POST['save_lot'])){
    $location = $_POST['location'];
    $prop_type = $_POST['property_type'];
    $overview = $_POST['property_overview'];
    $lat = !empty($_POST['latitude']) ? $_POST['latitude'] : NULL;
    $lng = !empty($_POST['longitude']) ? $_POST['longitude'] : NULL;
    
    $block = $_POST['block_no'];
    $lot_no = $_POST['lot_no'];
    $area = $_POST['area'];
    $price_sqm = $_POST['price_sqm'];
    $total = $_POST['total_price'];
    $status = $_POST['status'];
    
    // 1. Handle Main Image
    $lot_image = $_POST['current_image'] ?? ''; 
    if(isset($_FILES['lot_image']) && $_FILES['lot_image']['error'] == 0){
        $target_dir = "uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir);
        $filename = time() . "_" . basename($_FILES["lot_image"]["name"]);
        move_uploaded_file($_FILES["lot_image"]["tmp_name"], $target_dir . $filename);
        $lot_image = $filename;
    }

    // 2. Insert/Update Main Lot Data
    if(!empty($_POST['lot_id'])){
        $id = $_POST['lot_id'];
        $stmt = $conn->prepare("UPDATE lots SET location=?, property_type=?, block_no=?, lot_no=?, area=?, price_per_sqm=?, total_price=?, status=?, property_overview=?, latitude=?, longitude=?, lot_image=? WHERE id=?");
        $stmt->bind_param("sssidddssddsi", $location, $prop_type, $block, $lot_no, $area, $price_sqm, $total, $status, $overview, $lat, $lng, $lot_image, $id);
        $stmt->execute();
        $target_lot_id = $id;
        $msg = "updated";
        
        // AUDIT LOG: Updated Property
        logActivity($conn, $_SESSION['user_id'], "Updated Property", "Lot ID: $id | Block: $block, Lot: $lot_no | Status: $status");

    } else {
        $stmt = $conn->prepare("INSERT INTO lots (location, property_type, block_no, lot_no, area, price_per_sqm, total_price, status, property_overview, latitude, longitude, lot_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssidddssdds", $location, $prop_type, $block, $lot_no, $area, $price_sqm, $total, $status, $overview, $lat, $lng, $lot_image);
        $stmt->execute();
        $target_lot_id = $conn->insert_id;
        $msg = "added";

        // AUDIT LOG: Added Property
        logActivity($conn, $_SESSION['user_id'], "Added New Property", "Lot ID: $target_lot_id | Block: $block, Lot: $lot_no | Location: $location");
    }

    // 3. Handle Gallery Images (Multiple)
    if(isset($_FILES['gallery'])){
        $count = count($_FILES['gallery']['name']);
        if(!is_dir("uploads/")) mkdir("uploads/");
        
        for($i=0; $i<$count; $i++){
            if($_FILES['gallery']['error'][$i] == 0){
                $g_filename = time() . "_" . $i . "_" . basename($_FILES['gallery']['name'][$i]);
                if(move_uploaded_file($_FILES['gallery']['tmp_name'][$i], "uploads/" . $g_filename)){
                    $conn->query("INSERT INTO lot_gallery (lot_id, image_path) VALUES ('$target_lot_id', '$g_filename')");
                }
            }
        }
    }

    header("Location: admin.php?view=inventory&msg=$msg");
    exit();
}

// --- ARCHIVE & DELETE ACTION ---
if(isset($_GET['delete_id'])){
    $id = $_GET['delete_id'];
    
    // 1. Fetch data before deleting to save in Archive
    $lot_data = $conn->query("SELECT * FROM lots WHERE id='$id'")->fetch_assoc();
    if($lot_data) {
        // Log to Delete History
        logDeletion($conn, 'Property Inventory', $id, $lot_data, $_SESSION['user_id']);
    }

    // 2. AUDIT LOG: Deleted Property
    logActivity($conn, $_SESSION['user_id'], "Deleted Property", "Removed Lot ID: $id from inventory");

    // 3. DELETE CHILD RECORDS FIRST to satisfy Foreign Key constraints
    $conn->query("DELETE FROM reservations WHERE lot_id='$id'");
    $conn->query("DELETE FROM lot_gallery WHERE lot_id='$id'");
    
    // 4. Actually Delete from Main Tables
    $conn->query("DELETE FROM lots WHERE id='$id'");
    
    header("Location: admin.php?view=inventory&msg=deleted");
    exit();
}

if(isset($_GET['edit_id'])){
    $view = 'inventory'; 
    $edit_mode = true;
    $id = $_GET['edit_id'];
    $edit_data = $conn->query("SELECT * FROM lots WHERE id='$id'")->fetch_assoc();
}

// --- DATA FETCHING (PROPERTY INVENTORY) ---
$stats_pending = $conn->query("SELECT COUNT(*) as count FROM reservations WHERE status='PENDING'")->fetch_assoc()['count'];
$stats_reserved = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='RESERVED'")->fetch_assoc()['count'];
$stats_sold    = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='SOLD'")->fetch_assoc()['count'];
$stats_avail   = $conn->query("SELECT COUNT(*) as count FROM lots WHERE status='AVAILABLE'")->fetch_assoc()['count'];

// --- DATA FETCHING (MINI FINANCIAL & CALENDAR) ---
$chart_dates = []; $chart_totals = [];
$calendar_events = [];

// 1. Fetch Reservations for Calendar & Recent Table
$resQuery = $conn->query("SELECT r.reservation_date, l.block_no, l.lot_no FROM reservations r JOIN lots l ON r.lot_id = l.id");
while($r = $resQuery->fetch_assoc()){
    $calendar_events[] = [
        'title' => 'Reserved: B'.$r['block_no'].' L'.$r['lot_no'],
        'start' => date('Y-m-d', strtotime($r['reservation_date'])),
        'color' => '#F6AD55' // Orange for reservations
    ];
}

// Fetch recent 10 reservations for the dashboard table display
$recent_reservations = $conn->query("
    SELECT r.*, u.fullname, l.block_no, l.lot_no, l.total_price 
    FROM reservations r 
    JOIN users u ON r.user_id = u.id 
    JOIN lots l ON r.lot_id = l.id 
    ORDER BY r.reservation_date DESC LIMIT 10
");

// 2. Fetch Financials (if table exists)
$checkTable = $conn->query("SHOW TABLES LIKE 'transactions'");
if($checkTable && $checkTable->num_rows > 0) {
    // Chart: Last 7 Days Expenses
    $chartData = $conn->query("SELECT transaction_date, SUM(amount) as daily_total FROM transactions WHERE type='EXPENSE' GROUP BY transaction_date ORDER BY transaction_date DESC LIMIT 7");
    while($row = $chartData->fetch_assoc()){
        $chart_dates[] = $row['transaction_date'];
        $chart_totals[] = $row['daily_total'];
    }
    $chart_dates = array_reverse($chart_dates);
    $chart_totals = array_reverse($chart_totals);

    // Calendar: Financial Transactions
    $txQuery = $conn->query("SELECT transaction_date, type, amount FROM transactions");
    while($tx = $txQuery->fetch_assoc()){
        $color = ($tx['type'] == 'INCOME') ? '#48BB78' : '#E53E3E'; // Green/Red
        $prefix = ($tx['type'] == 'INCOME') ? 'Inc: ₱' : 'Exp: ₱';
        $calendar_events[] = [
            'title' => $prefix . number_format($tx['amount'], 0),
            'start' => $tx['transaction_date'],
            'color' => $color
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | JEJ Surveying</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-geosearch@3.11.0/dist/geosearch.css" />

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

    <style>
        body { background-color: #F7FAFC; display: flex; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: white; border-right: 1px solid #EDF2F7; display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 100; }
        .brand-box { padding: 25px; border-bottom: 1px solid #EDF2F7; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu { padding: 20px 10px; flex: 1; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 14px 20px; color: #718096; text-decoration: none; font-weight: 600; border-radius: 12px; margin-bottom: 5px; transition: all 0.2s; }
        .menu-link:hover, .menu-link.active { background: #F0FFF4; color: var(--primary); }
        .menu-link i { width: 20px; text-align: center; }
        .main-panel { margin-left: 260px; flex: 1; padding: 30px 40px; width: calc(100% - 260px); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); position: relative; overflow: hidden; }
        .stat-card h2 { font-size: 32px; font-weight: 800; color: var(--dark); margin: 5px 0 0; }
        .stat-card small { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #A0AEC0; letter-spacing: 0.5px; }
        .stat-icon { position: absolute; right: -10px; bottom: -10px; font-size: 80px; opacity: 0.1; transform: rotate(-15deg); }
        
        /* Mini Widgets */
        .dashboard-widgets { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        @media (max-width: 1100px) { .dashboard-widgets { grid-template-columns: 1fr; } }
        .widget-card { background: white; padding: 20px; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); }
        .widget-title { font-size: 15px; font-weight: 800; color: var(--dark); margin-bottom: 15px; border-bottom: 1px solid #EDF2F7; padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center;}
        
        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; }
        .table-title { font-size: 18px; font-weight: 800; color: var(--dark); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; font-size: 12px; font-weight: 700; color: #718096; margin-bottom: 8px; text-transform: uppercase; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #E2E8F0; border-radius: 8px; background: #F9FAFB; font-family: inherit; font-size: 14px; transition: 0.2s; }
        .form-control:focus { background: white; border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1); }
        
        .sc-orange { border-bottom: 4px solid #F6AD55; } .sc-orange .stat-icon { color: #F6AD55; }
        .sc-purple { border-bottom: 4px solid #9F7AEA; } .sc-purple .stat-icon { color: #9F7AEA; }
        .sc-blue { border-bottom: 4px solid #63B3ED; } .sc-blue .stat-icon { color: #63B3ED; }
        .sc-green { border-bottom: 4px solid #48BB78; } .sc-green .stat-icon { color: #48BB78; }
        
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-block; cursor: pointer;}
        .btn-edit { background: #EBF8FF; color: #3182CE; }
        .btn-del { background: #FFF5F5; color: #E53E3E; margin-left: 5px; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        
        #map { height: 350px; width: 100%; border-radius: 12px; border: 1px solid #E2E8F0; z-index: 1; }
        .leaflet-control-geosearch form { background: white; padding: 2px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border: 1px solid #E2E8F0; }
        .leaflet-control-geosearch input { height: 35px; border: none; outline: none; padding-left: 10px; font-size: 13px; }

        .fc .fc-toolbar-title { font-size: 14px !important; color: var(--dark); font-weight: 800;}
        .fc .fc-button { padding: 2px 8px !important; font-size: 12px !important; background: var(--primary) !important; border: none !important;}
        .fc-event { font-size: 10px !important; padding: 1px 3px !important; border: none !important; border-radius: 3px !important;}
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
            <a href="admin.php?view=dashboard" class="menu-link <?= $view=='dashboard'?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="reservation.php" class="menu-link"><i class="fa-solid fa-file-signature"></i> Reservations</a>
            <a href="master_list.php" class="menu-link"><i class="fa-solid fa-map-location-dot"></i> Master List / Map</a>
            <a href="admin.php?view=inventory" class="menu-link <?= $view=='inventory'?'active':'' ?>"><i class="fa-solid fa-plus-circle"></i> Add Property</a>
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
        
        <div style="padding: 20px; border-top: 1px solid #EDF2F7;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 35px; height: 35px; background: var(--dark); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">A</div>
                <div style="line-height: 1.2;">
                    <strong style="display: block; font-size: 13px; color: var(--dark);">Administrator</strong>
                    <small style="font-size: 11px; color: #718096;">System Admin</small>
                </div>
            </div>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">
                <?= $view == 'dashboard' ? 'Overview' : 'Property Inventory' ?>
            </h1>
            <p style="color: #718096;">Welcome back! Manage your estate efficiently.</p>
        </div>

        <?php if($alert_msg): ?>
            <div style="padding: 15px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; font-size: 14px; background: <?= $alert_type=='success' ? '#F0FFF4' : '#FFF5F5' ?>; color: <?= $alert_type=='success' ? '#2F855A' : '#C53030' ?>; border: 1px solid <?= $alert_type=='success' ? '#C6F6D5' : '#FED7D7' ?>;">
                <i class="fa-solid <?= $alert_type=='success'?'fa-check-circle':'fa-exclamation-circle' ?>" style="margin-right: 8px;"></i>
                <?= $alert_msg ?>
            </div>
        <?php endif; ?>

        <?php if($view == 'dashboard'): ?>

            <div class="stats-grid">
                <div class="stat-card sc-orange">
                    <small>Pending Requests</small>
                    <h2><?= $stats_pending ?></h2>
                    <i class="fa-solid fa-clock stat-icon"></i>
                </div>
                <div class="stat-card sc-purple">
                    <small>Reserved Properties</small>
                    <h2><?= $stats_reserved ?></h2>
                    <i class="fa-solid fa-bookmark stat-icon"></i>
                </div>
                <div class="stat-card sc-blue">
                    <small>Sold Units</small>
                    <h2><?= $stats_sold ?></h2>
                    <i class="fa-solid fa-handshake stat-icon"></i>
                </div>
                <div class="stat-card sc-green">
                    <small>Available Lots</small>
                    <h2><?= $stats_avail ?></h2>
                    <i class="fa-solid fa-map stat-icon"></i>
                </div>
            </div>

            <div class="dashboard-widgets">
                <div class="widget-card">
                    <div class="widget-title">
                        <span><i class="fa-solid fa-chart-line" style="color: #4A5568; margin-right: 5px;"></i> Mini Expense Trend</span>
                        <a href="financial.php" style="font-size: 11px; color: #3182CE; text-decoration: none; font-weight: 600;">View Full <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <canvas id="miniChart" style="max-height: 250px;"></canvas>
                </div>

                <div class="widget-card">
                    <div class="widget-title">
                        <span><i class="fa-solid fa-calendar-days" style="color: #4A5568; margin-right: 5px;"></i> Mini Calendar</span>
                        <a href="financial.php" style="font-size: 11px; color: #3182CE; text-decoration: none; font-weight: 600;">View Full <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div id="miniCalendar" style="height: 250px;"></div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('miniChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line', 
                    data: {
                        labels: <?= json_encode($chart_dates) ?>,
                        datasets: [{
                            label: 'Expenses',
                            data: <?= json_encode($chart_totals) ?>,
                            backgroundColor: 'rgba(229, 62, 62, 0.1)',
                            borderColor: 'rgba(229, 62, 62, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 3
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { display: false }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } } }
                });

                var calendarEl = document.getElementById('miniCalendar');
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth', height: 250, headerToolbar: { left: 'prev,next', center: 'title', right: '' },
                    events: <?= json_encode($calendar_events) ?>, displayEventTime: false
                });
                calendar.render();
            });
            </script>

            <div class="table-container">
                <div class="table-header">
                    <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--dark);"><i class="fa-solid fa-file-signature" style="color: #3182CE; margin-right: 8px;"></i> Recent Reservations</h3>
                    <a href="reservation.php" style="font-size: 12px; font-weight: 700; color: #3182CE; text-decoration: none;">View All Management <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date Submited</th>
                            <th>Buyer Name</th>
                            <th>Property Details</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_reservations && $recent_reservations->num_rows > 0): ?>
                            <?php while($res = $recent_reservations->fetch_assoc()): ?>
                            <tr>
                                <td style="font-weight: 600; color: #A0AEC0; font-size: 12px;"><?= date('M d, Y', strtotime($res['reservation_date'])) ?></td>
                                <td style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($res['fullname']) ?></td>
                                <td style="font-weight: 700; color: var(--primary);">Block <?= $res['block_no'] ?>, Lot <?= $res['lot_no'] ?></td>
                                <td style="font-weight: 700;">₱<?= number_format($res['total_price']) ?></td>
                                <td>
                                    <?php 
                                        $status_colors = [
                                            'PENDING' => ['bg'=>'#FEEBC8', 'col'=>'#C05621'],
                                            'APPROVED' => ['bg'=>'#C6F6D5', 'col'=>'#2F855A'],
                                            'REJECTED' => ['bg'=>'#FED7D7', 'col'=>'#C53030']
                                        ];
                                        $sc = $status_colors[$res['status']] ?? ['bg'=>'#EDF2F7', 'col'=>'#4A5568'];
                                    ?>
                                    <span style="background: <?= $sc['bg'] ?>; color: <?= $sc['col'] ?>; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 800;"><?= $res['status'] ?></span>
                                </td>
                                <td>
                                    <a href="reservation.php?status=<?= $res['status'] ?>" class="btn-action btn-edit"><i class="fa-solid fa-list-check"></i> Manage</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px; color: #A0AEC0;">No recent reservations available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif($view == 'inventory'): ?>
            <div class="table-container" style="padding: 0; overflow: visible; background: transparent; border: none; box-shadow: none;">
                <div style="background: white; padding: 30px; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); margin-bottom: 30px;">
                    <div style="margin-bottom: 20px; border-bottom: 1px solid #EDF2F7; padding-bottom: 15px;">
                        <span class="table-title"><?= $edit_mode ? 'Edit Property Details' : 'Add New Property' ?></span>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="lot_id" value="<?= $edit_mode ? $edit_data['id'] : '' ?>">
                        <input type="hidden" name="current_image" value="<?= $edit_mode ? $edit_data['lot_image'] : '' ?>">
                        <input type="hidden" name="latitude" id="lat" value="<?= $edit_mode ? $edit_data['latitude'] : '' ?>">
                        <input type="hidden" name="longitude" id="lng" value="<?= $edit_mode ? $edit_data['longitude'] : '' ?>">
                        
                        <div class="form-grid">
                            <div class="input-group">
                                <label>Location / Phase</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g. Phase 1 - Green Hills" value="<?= $edit_mode ? ($edit_data['location'] ?? '') : '' ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Property Type</label>
                                <input type="text" name="property_type" class="form-control" placeholder="e.g. Residential Lot" value="<?= $edit_mode ? $edit_data['property_type'] : '' ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Block Number</label>
                                <input type="text" name="block_no" class="form-control" placeholder="e.g., 5" value="<?= $edit_mode?$edit_data['block_no']:'' ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Lot Number</label>
                                <input type="text" name="lot_no" class="form-control" placeholder="e.g., 12" value="<?= $edit_mode?$edit_data['lot_no']:'' ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Area (sqm)</label>
                                <input type="number" name="area" id="area" class="form-control" placeholder="0" value="<?= $edit_mode?$edit_data['area']:'' ?>" required oninput="calcTotal()">
                            </div>
                            <div class="input-group">
                                <label>Price per SQM</label>
                                <input type="number" name="price_sqm" id="price_sqm" class="form-control" placeholder="0.00" value="<?= $edit_mode?$edit_data['price_per_sqm']:'' ?>" required oninput="calcTotal()">
                            </div>
                            <div class="input-group">
                                <label>Total Price (Auto-calc)</label>
                                <input type="number" name="total_price" id="total" class="form-control" style="background: #EDF2F7; cursor: not-allowed;" readonly value="<?= $edit_mode?$edit_data['total_price']:'' ?>">
                            </div>
                            <div class="input-group">
                                <label>Current Status</label>
                                <select name="status" class="form-control">
                                    <option value="AVAILABLE" <?= ($edit_mode && $edit_data['status']=='AVAILABLE')?'selected':'' ?>>Available</option>
                                    <option value="RESERVED" <?= ($edit_mode && $edit_data['status']=='RESERVED')?'selected':'' ?>>Reserved</option>
                                    <option value="SOLD" <?= ($edit_mode && $edit_data['status']=='SOLD')?'selected':'' ?>>Sold</option>
                                </select>
                            </div>
                        </div>

                        <div class="input-group" style="margin-top: 15px;">
                            <label>Property Overview</label>
                            <textarea name="property_overview" class="form-control" rows="4" placeholder="Describe the property, nearby landmarks, or specific features..."><?= $edit_mode ? ($edit_data['property_overview'] ?? '') : '' ?></textarea>
                        </div>

                        <div class="input-group" style="margin-top: 20px;">
                            <label><i class="fa-solid fa-map-pin" style="color:#E53E3E;"></i> Pin Location (Search or Click)</label>
                            <div id="map"></div>
                            <small style="color: #718096; display: block; margin-top: 5px;">Use the search icon (top-left) to find a city, or click anywhere to pin manually.</small>
                        </div>

                        <div class="input-group" style="margin-top: 20px;">
                            <label>Main Property Image</label>
                            <input type="file" name="lot_image" class="form-control">
                            <?php if($edit_mode && $edit_data['lot_image']): ?>
                                <small style="display:block; margin-top:5px; color:#718096;">Current: <?= $edit_data['lot_image'] ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="input-group" style="margin-top: 20px;">
                            <label>Other Angles / Gallery (Multiple)</label>
                            <input type="file" name="gallery[]" class="form-control" multiple accept="image/*">
                            <small style="color: #718096;">Hold Ctrl/Cmd to select multiple images.</small>

                            <?php if($edit_mode): ?>
                                <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                                    <?php 
                                    $gal_res = $conn->query("SELECT * FROM lot_gallery WHERE lot_id='$id'");
                                    while($img = $gal_res->fetch_assoc()):
                                    ?>
                                        <div style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; border: 1px solid #E2E8F0;">
                                            <img src="uploads/<?= $img['image_path'] ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 20px; text-align: right;">
                            <?php if($edit_mode): ?>
                                <a href="master_list.php" class="btn-action" style="background:#EDF2F7; color:#4A5568; margin-right:10px; padding: 12px 20px;">Cancel</a>
                            <?php endif; ?>
                            <button type="submit" name="save_lot" class="btn-save">
                                <i class="fa-solid fa-save"></i> <?= $edit_mode ? 'Update Property' : 'Save Property' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="https://unpkg.com/leaflet-geosearch@3.11.0/dist/bundle.min.js"></script>
            <script>
            function calcTotal(){
                let area = document.getElementById('area').value;
                let price = document.getElementById('price_sqm').value;
                if(area && price){ document.getElementById('total').value = (area * price).toFixed(2); }
            }

            document.addEventListener('DOMContentLoaded', function() {
                var initialLat = <?= $edit_mode && !empty($edit_data['latitude']) ? $edit_data['latitude'] : '14.5995' ?>; 
                var initialLng = <?= $edit_mode && !empty($edit_data['longitude']) ? $edit_data['longitude'] : '120.9842' ?>;
                
                var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 });
                var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19 });

                var map = L.map('map', { center: [initialLat, initialLng], zoom: 13, layers: [satelliteLayer] });
                L.control.layers({"Satellite": satelliteLayer, "Streets": streetLayer}).addTo(map);

                const provider = new GeoSearch.OpenStreetMapProvider();
                const searchControl = new GeoSearch.GeoSearchControl({ provider: provider, style: 'bar', showMarker: true });
                map.addControl(searchControl);

                var marker;
                function updatePin(lat, lng) {
                    document.getElementById('lat').value = lat;
                    document.getElementById('lng').value = lng;
                    if (marker) marker.setLatLng([lat, lng]);
                    else marker = L.marker([lat, lng]).addTo(map);
                }

                <?php if($edit_mode && !empty($edit_data['latitude'])): ?>
                    marker = L.marker([initialLat, initialLng]).addTo(map);
                <?php endif; ?>

                map.on('click', function(e) { updatePin(e.latlng.lat, e.latlng.lng); });
                map.on('geosearch/showlocation', function(result) { updatePin(result.location.y, result.location.x); });
            });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>