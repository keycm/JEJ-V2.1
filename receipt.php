<?php
// receipt.php
include 'config.php';

// Security Check
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])){
    die("Access Denied");
}

if(!isset($_GET['id'])){
    die("Invalid Request");
}

$id = $_GET['id'];

// Fetch Reservation Data
$stmt = $conn->prepare("SELECT r.*, u.fullname, l.block_no, l.lot_no, l.area, l.price_per_sqm, l.total_price, l.location, l.property_type 
                        FROM reservations r 
                        JOIN users u ON r.user_id = u.id 
                        JOIN lots l ON r.lot_id = l.id 
                        WHERE r.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if(!$data) die("Reservation not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?= $data['id'] ?> - EcoEstates</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; background: #555; display: flex; justify-content: center; padding: 30px; }
        .receipt-container { 
            background: white; width: 800px; min-height: 900px; padding: 50px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.3); position: relative; 
        }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #2E7D32; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: 800; color: #2E7D32; display: flex; align-items: center; gap: 10px; }
        .company-info { text-align: right; font-size: 12px; color: #555; line-height: 1.5; }
        
        .title { text-align: center; font-size: 24px; font-weight: 800; margin-bottom: 40px; text-transform: uppercase; letter-spacing: 2px; color: #333; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .box-title { font-size: 14px; font-weight: 700; color: #2E7D32; text-transform: uppercase; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .label { color: #666; }
        .val { font-weight: 600; color: #333; }

        .total-section { background: #F9FAFB; padding: 20px; border-radius: 8px; margin-top: 20px; text-align: right; }
        .total-label { font-size: 14px; color: #666; margin-bottom: 5px; }
        .total-amount { font-size: 28px; font-weight: 800; color: #2E7D32; }

        .footer { position: absolute; bottom: 50px; left: 50px; right: 50px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px; }
        
        .btn-print { 
            position: fixed; top: 20px; right: 20px; 
            background: #2E7D32; color: white; padding: 15px 30px; 
            border: none; border-radius: 50px; cursor: pointer; font-weight: 700; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; width: 100%; height: auto; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">Download / Print PDF</button>

    <div class="receipt-container">
        
        <div class="header">
            <div class="logo">
                <span>🌲</span> EcoEstates Land Inc.
            </div>
            <div class="company-info">
                <strong>EcoEstates Head Office</strong><br>
                123 Green Valley Road, Eco City<br>
                Tel: (045) 123-4567<br>
                Email: support@ecoestates.com
            </div>
        </div>

        <div class="title">Official Reservation Receipt</div>

        <div class="grid">
            <div>
                <div class="box-title">Buyer Information</div>
                <div class="info-row"><span class="label">Name:</span> <span class="val"><?= $data['fullname'] ?></span></div>
                <div class="info-row"><span class="label">Contact:</span> <span class="val"><?= $data['contact_number'] ?></span></div>
                <div class="info-row"><span class="label">Email:</span> <span class="val"><?= $data['email'] ?></span></div>
                <div class="info-row"><span class="label">Address:</span> <span class="val"><?= $data['buyer_address'] ?></span></div>
            </div>

            <div>
                <div class="box-title">Transaction Details</div>
                <div class="info-row"><span class="label">Date:</span> <span class="val"><?= date('F d, Y', strtotime($data['reservation_date'])) ?></span></div>
                <div class="info-row"><span class="label">Receipt #:</span> <span class="val"><?= str_pad($data['id'], 6, '0', STR_PAD_LEFT) ?></span></div>
                <div class="info-row"><span class="label">Status:</span> <span class="val" style="text-transform:uppercase;"><?= $data['status'] ?></span></div>
            </div>
        </div>

        <div>
            <div class="box-title">Property Details</div>
            <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                <tr style="background:#eee; font-size:12px; font-weight:700; text-transform:uppercase;">
                    <td style="padding:8px;">Description</td>
                    <td style="padding:8px; text-align:right;">Area</td>
                    <td style="padding:8px; text-align:right;">Price/Sqm</td>
                    <td style="padding:8px; text-align:right;">Amount</td>
                </tr>
                <tr style="font-size:14px; border-bottom:1px solid #eee;">
                    <td style="padding:12px 8px;">
                        <strong>Block <?= $data['block_no'] ?> Lot <?= $data['lot_no'] ?></strong><br>
                        <small style="color:#777;"><?= $data['property_type'] ?> - <?= $data['location'] ?></small>
                    </td>
                    <td style="padding:12px 8px; text-align:right;"><?= number_format($data['area']) ?> m²</td>
                    <td style="padding:12px 8px; text-align:right;">₱<?= number_format($data['price_per_sqm']) ?></td>
                    <td style="padding:12px 8px; text-align:right;">₱<?= number_format($data['total_price']) ?></td>
                </tr>
            </table>
        </div>

        <div class="total-section">
            <div class="total-label">Total Contract Price</div>
            <div class="total-amount">₱<?= number_format($data['total_price'], 2) ?></div>
            <div style="font-size:12px; color:#777; margin-top:5px;">Reservation Status: <?= $data['status'] ?></div>
        </div>

        <div style="margin-top: 80px; display:flex; justify-content:space-between;">
            <div style="text-align:center; width: 200px;">
                <div style="border-bottom:1px solid #333; margin-bottom:5px;"></div>
                <small>Buyer's Signature</small>
            </div>
            <div style="text-align:center; width: 200px;">
                <div style="border-bottom:1px solid #333; margin-bottom:5px; font-weight:700; color:#333;">Admin</div>
                <small>Authorized Representative</small>
            </div>
        </div>

        <div class="footer">
            This is a system-generated receipt. Valid for reservation purposes only.<br>
            EcoEstates Land Inc. | All Rights Reserved <?= date('Y') ?>
        </div>

    </div>

</body>
</html>