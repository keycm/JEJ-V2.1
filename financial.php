<?php
// financial.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    header("Location: login.php");
    exit();
}

// --- DATA FETCHING (FINANCIAL & ACCOUNTING) ---
$total_in = 0; $total_out = 0; $current_balance = 0;
$low_fund_threshold = 20000;
$chart_dates = []; $chart_totals = [];
$calendar_events = [];
$check_vouchers = [];

$checkTable = $conn->query("SHOW TABLES LIKE 'transactions'");
if($checkTable && $checkTable->num_rows > 0) {
    // 1. Calculate Balances
    $fundQuery = $conn->query("SELECT 
        SUM(CASE WHEN type='INCOME' THEN amount ELSE 0 END) as total_in,
        SUM(CASE WHEN type='EXPENSE' THEN amount ELSE 0 END) as total_out
        FROM transactions");
    if($funds = $fundQuery->fetch_assoc()){
        $total_in = $funds['total_in'] ?? 0;
        $total_out = $funds['total_out'] ?? 0;
        $current_balance = $total_in - $total_out;
    }

    // 2. Fetch Chart Data (Last 7 Days Expenses)
    $chartData = $conn->query("SELECT transaction_date, SUM(amount) as daily_total FROM transactions WHERE type='EXPENSE' GROUP BY transaction_date ORDER BY transaction_date DESC LIMIT 7");
    while($row = $chartData->fetch_assoc()){
        $chart_dates[] = $row['transaction_date'];
        $chart_totals[] = $row['daily_total'];
    }
    $chart_dates = array_reverse($chart_dates);
    $chart_totals = array_reverse($chart_totals);

    // 3. Fetch Calendar Events
    $ev = $conn->query("SELECT * FROM transactions");
    while($row = $ev->fetch_assoc()){
        $color = ($row['type'] == 'INCOME') ? '#48BB78' : '#E53E3E';
        $calendar_events[] = [
            'title' => $row['type'] . ': ₱' . number_format($row['amount'], 2),
            'start' => $row['transaction_date'],
            'color' => $color
        ];
    }

    // 4. Fetch Recent Check Vouchers
    // Check if the is_check column exists first to prevent errors before SQL update
    $colCheck = $conn->query("SHOW COLUMNS FROM transactions LIKE 'is_check'");
    if($colCheck && $colCheck->num_rows > 0) {
        $cv_query = $conn->query("SELECT t.*, c.name as category FROM transactions t LEFT JOIN accounting_categories c ON t.category_id = c.id WHERE t.is_check = 1 ORDER BY t.transaction_date DESC, t.id DESC LIMIT 10");
        if($cv_query) {
            while($row = $cv_query->fetch_assoc()){
                $check_vouchers[] = $row;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Dashboard | JEJ Surveying</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/modern.css">
    
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
        
        .dashboard-widgets { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        @media (max-width: 1100px) { .dashboard-widgets { grid-template-columns: 1fr; } }
        .widget-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); }
        .widget-title { font-size: 18px; font-weight: 800; color: var(--dark); margin-bottom: 20px; border-bottom: 1px solid #EDF2F7; padding-bottom: 15px; }

        .sc-green { border-bottom: 4px solid #48BB78; } 
        .sc-red { border-bottom: 4px solid #E53E3E; } 
        .sc-blue { border-bottom: 4px solid #63B3ED; } 
        
        .btn-financial { background: #2B6CB0; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;}
        .btn-financial:hover { background: #2C5282; color: white;}
        .btn-check-voucher { background: #805AD5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;}
        .btn-check-voucher:hover { background: #6B46C1; color: white;}
        .btn-export { background: #38A169; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;}
        .btn-export:hover { background: #2F855A; color: white;}

        .table-container { background: white; border-radius: 16px; border: 1px solid #EDF2F7; box-shadow: var(--shadow-soft); overflow: hidden; margin-bottom: 30px; }
        .table-header { padding: 20px 25px; border-bottom: 1px solid #EDF2F7; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 25px; font-size: 12px; font-weight: 700; color: #718096; text-transform: uppercase; background: #F7FAFC; border-bottom: 1px solid #EDF2F7; }
        td { padding: 15px 25px; border-bottom: 1px solid #EDF2F7; color: #4A5568; font-size: 14px; vertical-align: middle; }
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
            <a href="admin.php?view=inventory" class="menu-link"><i class="fa-solid fa-list-check"></i> Inventory</a>
            <a href="financial.php" class="menu-link active"><i class="fa-solid fa-coins"></i> Financials</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">MANAGEMENT</small>
            <a href="accounts.php" class="menu-link"><i class="fa-solid fa-users-gear"></i> Accounts</a>
            <a href="audit_logs.php" class="menu-link"><i class="fa-solid fa-clock-rotate-left"></i> Audit Logs</a>
            
            <small style="padding: 0 20px; color: #A0AEC0; font-weight: 700; font-size: 11px; display: block; margin-top: 20px; margin-bottom: 10px;">SYSTEM</small>
            <a href="index.php" class="menu-link" target="_blank"><i class="fa-solid fa-globe"></i> View Website</a>
            <a href="logout.php" class="menu-link" style="color: #E53E3E;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <div class="main-panel">
        <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:15px;">
            <div>
                <h1 style="font-size: 24px; font-weight: 800; color: var(--dark);">Financial Dashboard</h1>
                <p style="color: #718096;">Track income, expenses, vouchers, and project accounting.</p>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap:wrap;">
                <a href="issue_check.php" class="btn-check-voucher"><i class="fa-solid fa-money-check"></i> Issue Check Voucher</a>
                <a href="pos.php" class="btn-financial"><i class="fa-solid fa-cash-register"></i> Enter Bills (Cash)</a>
                <a href="export_excel.php" class="btn-export"><i class="fa-solid fa-file-excel"></i> Export Finance</a>
            </div>
        </div>

        <?php if($current_balance < $low_fund_threshold): ?>
        <div style="background: #FFF5F5; border-left: 5px solid #E53E3E; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; align-items: center; gap: 15px;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #E53E3E; font-size: 24px;"></i>
            <div>
                <strong style="color: #C53030; font-size: 16px; display: block;">LOW FUND WARNING</strong>
                <span style="color: #E53E3E; font-size: 14px;">Current Balance is <b>₱<?= number_format($current_balance, 2) ?></b>, which is below the safe threshold of ₱<?= number_format($low_fund_threshold) ?>.</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card sc-green" style="background: #F0FFF4;">
                <small>Total Income (Collected)</small>
                <h2 style="color: #2F855A;">₱<?= number_format($total_in, 2) ?></h2>
                <i class="fa-solid fa-arrow-trend-up stat-icon" style="color: #48BB78;"></i>
            </div>
            <div class="stat-card sc-red" style="background: #FFF5F5;">
                <small>Total Expenses (Bills/Checks)</small>
                <h2 style="color: #C53030;">₱<?= number_format($total_out, 2) ?></h2>
                <i class="fa-solid fa-arrow-trend-down stat-icon" style="color: #E53E3E;"></i>
            </div>
            <div class="stat-card sc-blue" style="background: #EBF8FF;">
                <small>Current Remaining Balance</small>
                <h2 style="color: #2B6CB0;">₱<?= number_format($current_balance, 2) ?></h2>
                <i class="fa-solid fa-wallet stat-icon" style="color: #4299E1;"></i>
            </div>
        </div>

        <div class="dashboard-widgets">
            <div class="widget-card">
                <div class="widget-title"><i class="fa-solid fa-chart-line" style="color: #4A5568; margin-right: 8px;"></i> Expense Trends (Last 7 Days)</div>
                <canvas id="expenseChart" style="max-height: 350px;"></canvas>
            </div>
            <div class="widget-card">
                <div class="widget-title"><i class="fa-solid fa-calendar-days" style="color: #4A5568; margin-right: 8px;"></i> Financial Calendar Tracker</div>
                <div id="calendar" style="height: 350px;"></div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-header">
                <h3 style="margin: 0; font-size: 18px; font-weight: 800; color: var(--dark);"><i class="fa-solid fa-money-check" style="color: #805AD5;"></i> Recent Check Vouchers</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>CV Number</th>
                        <th>Payee</th>
                        <th>Bank & Check No</th>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($check_vouchers)): ?>
                        <?php foreach($check_vouchers as $cv): ?>
                        <tr>
                            <td style="font-weight: 600; color: #A0AEC0; font-size: 13px;"><?= date('M d, Y', strtotime($cv['transaction_date'])) ?></td>
                            <td><strong style="color: #805AD5;"><?= $cv['or_number'] ?></strong></td>
                            <td style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($cv['payee']) ?></td>
                            <td>
                                <div style="font-size: 12px; font-weight: 700;"><?= htmlspecialchars($cv['bank_name']) ?></div>
                                <div style="font-size: 11px; color: #718096;">Check #: <?= htmlspecialchars($cv['check_number']) ?></div>
                            </td>
                            <td style="font-size: 12px; color: #4A5568; max-width: 250px;"><?= htmlspecialchars($cv['description']) ?></td>
                            <td style="font-weight: 700; color: #E53E3E;">₱<?= number_format($cv['amount'], 2) ?></td>
                            <td>
                                <a href="print_check_voucher.php?cv=<?= $cv['or_number'] ?>" target="_blank" style="background: #EBF8FF; color: #3182CE; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-decoration: none; display: inline-block;">
                                    <i class="fa-solid fa-print"></i> Print
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #A0AEC0;">No Check Vouchers recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('expenseChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_dates) ?>,
                    datasets: [{
                        label: 'Daily Expenses (₱)',
                        data: <?= json_encode($chart_totals) ?>,
                        backgroundColor: 'rgba(229, 62, 62, 0.7)',
                        borderColor: 'rgba(229, 62, 62, 1)',
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#EDF2F7' } }, x: { grid: { display: false } } } }
            });

            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth', height: '100%', headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
                events: <?= json_encode($calendar_events) ?>, eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' }
            });
            calendar.render();
        });
        </script>
    </div>
</body>
</html>