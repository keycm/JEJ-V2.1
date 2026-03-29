<?php
include 'config.php';
checkAdmin();

$res_id = isset($_GET['res_id']) ? (int)$_GET['res_id'] : 0;
if ($res_id <= 0) {
    echo '<div style="color:#C53030; font-weight:700;">Invalid reservation ID.</div>';
    exit;
}

$stmt = $conn->prepare(
    "SELECT r.id, r.reservation_date, r.payment_type, r.installment_months, r.monthly_payment,
            u.fullname,
            l.block_no, l.lot_no, l.total_price
     FROM reservations r
     JOIN users u ON u.id = r.user_id
     JOIN lots l ON l.id = r.lot_id
     WHERE r.id = ?"
);
$stmt->bind_param('i', $res_id);
$stmt->execute();
$res_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res_data) {
    echo '<div style="color:#C53030; font-weight:700;">Reservation not found.</div>';
    exit;
}

$total_price = (float)$res_data['total_price'];
$down_payment = $total_price * 0.20;
$balance = $total_price - $down_payment;
$months = (int)($res_data['installment_months'] ?? 0);
$monthly_payment = (float)($res_data['monthly_payment'] ?? 0);
$buyer_name = htmlspecialchars($res_data['fullname']);

$tx_stmt = $conn->prepare(
    "SELECT id, transaction_date, amount, description
     FROM transactions
     WHERE type = 'INCOME' AND description LIKE ?
     ORDER BY transaction_date ASC, id ASC"
);
$tx_like = "%Res#" . $res_id . "%";
$tx_stmt->bind_param('s', $tx_like);
$tx_stmt->execute();
$tx_result = $tx_stmt->get_result();
$transactions = [];
while ($row = $tx_result->fetch_assoc()) {
    $transactions[] = $row;
}
$tx_stmt->close();

$dp_paid = false;
$dp_paid_date = '';
foreach ($transactions as $tx) {
    if (stripos($tx['description'], 'Down Payment') !== false && abs((float)$tx['amount'] - $down_payment) < 1) {
        $dp_paid = true;
        $dp_paid_date = date('M d, Y', strtotime($tx['transaction_date']));
        break;
    }
}

$reservation_ts = strtotime($res_data['reservation_date']);
$first_due_ts = strtotime('+20 days', $reservation_ts);

function formatPeso($value) {
    return 'PHP ' . number_format((float)$value, 2);
}

echo '<div style="display:grid; gap:10px; margin-bottom:16px;">';
echo '<div><strong>Buyer Name:</strong> ' . $buyer_name . '</div>';
echo '<div><strong>Property:</strong> Block ' . htmlspecialchars($res_data['block_no']) . ' Lot ' . htmlspecialchars($res_data['lot_no']) . '</div>';
echo '<div><strong>Total Contract Price:</strong> ' . formatPeso($total_price) . '</div>';
echo '<div><strong>Down Payment (20%):</strong> ' . formatPeso($down_payment) . ' - ' . ($dp_paid ? '<span style="color:#2F855A; font-weight:700;">PAID</span> (' . $dp_paid_date . ')' : '<span style="color:#C53030; font-weight:700;">UNPAID</span>') . '</div>';
echo '<div><strong>Total Amortization:</strong> ' . formatPeso($balance) . '</div>';
echo '</div>';

if ($res_data['payment_type'] !== 'INSTALLMENT' || $months <= 0 || $monthly_payment <= 0) {
    echo '<div style="padding:12px; background:#F7FAFC; border:1px solid #E2E8F0; border-radius:8px; color:#4A5568;">';
    echo 'No installment schedule configured for this reservation.';
    echo '</div>';
    exit;
}

$remaining_tx = [];
foreach ($transactions as $tx) {
    // Exclude the down payment entry from installment matching.
    if (stripos($tx['description'], 'Down Payment') !== false && abs((float)$tx['amount'] - $down_payment) < 1) {
        continue;
    }
    if (stripos($tx['description'], 'Amortization') === false) {
        continue;
    }
    $remaining_tx[] = $tx;
}

$used_ids = [];
echo '<div style="overflow:auto;">';
echo '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
echo '<thead><tr style="background:#F7FAFC;">';
echo '<th style="text-align:left; padding:10px; border:1px solid #E2E8F0;">Month #</th>';
echo '<th style="text-align:left; padding:10px; border:1px solid #E2E8F0;">Due Date</th>';
echo '<th style="text-align:left; padding:10px; border:1px solid #E2E8F0;">Amount</th>';
echo '<th style="text-align:left; padding:10px; border:1px solid #E2E8F0;">Status</th>';
echo '<th style="text-align:left; padding:10px; border:1px solid #E2E8F0;">Date Paid</th>';
echo '</tr></thead><tbody>';

for ($i = 1; $i <= $months; $i++) {
    $due_ts = strtotime('+' . $i . ' month', $first_due_ts);
    $due = date('M d, Y', $due_ts);

    $paid = false;
    $paid_date = '-';

    foreach ($remaining_tx as $tx) {
        if (in_array($tx['id'], $used_ids, true)) {
            continue;
        }

        // Match installment payments by near-equal amount.
        if (abs((float)$tx['amount'] - $monthly_payment) < 1) {
            $paid = true;
            $paid_date = date('M d, Y', strtotime($tx['transaction_date']));
            $used_ids[] = $tx['id'];
            break;
        }
    }

    $status_html = $paid
        ? '<span style="background:#C6F6D5; color:#2F855A; padding:3px 8px; border-radius:999px; font-weight:700; font-size:11px;">PAID</span>'
        : '<span style="background:#FED7D7; color:#C53030; padding:3px 8px; border-radius:999px; font-weight:700; font-size:11px;">UNPAID</span>';

    echo '<tr>';
    echo '<td style="padding:10px; border:1px solid #E2E8F0;">' . $i . '</td>';
    echo '<td style="padding:10px; border:1px solid #E2E8F0;">' . $due . '</td>';
    echo '<td style="padding:10px; border:1px solid #E2E8F0;">' . formatPeso($monthly_payment) . '</td>';
    echo '<td style="padding:10px; border:1px solid #E2E8F0;">' . $status_html . '</td>';
    echo '<td style="padding:10px; border:1px solid #E2E8F0;">' . $paid_date . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
