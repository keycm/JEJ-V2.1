<?php
// lot_details.php
include 'config.php';

if(!isset($_SESSION['user_id'])){ header("Location: login.php"); exit(); }
if(!isset($_GET['id'])){ header("Location: index.php"); exit(); }

$id = $_GET['id'];

// Fetch Lot Details
$stmt = $conn->prepare("SELECT * FROM lots WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$lot = $stmt->get_result()->fetch_assoc();

if(!$lot) die("Property not found.");

// Fetch Gallery Images
$gallery_stmt = $conn->prepare("SELECT * FROM lot_gallery WHERE lot_id = ?");
$gallery_stmt->bind_param("i", $id);
$gallery_stmt->execute();
$gallery_res = $gallery_stmt->get_result();

// Build array of all images for the JS Gallery
$js_images = [];
// Add Main Image first
$main_img = $lot['lot_image'] ? 'uploads/'.$lot['lot_image'] : 'assets/default_lot.jpg';
$js_images[] = $main_img;

// Add Gallery Images
$gallery_html = ""; // Store HTML for thumbnails
while($img = $gallery_res->fetch_assoc()){
    $path = 'uploads/'.$img['image_path'];
    $js_images[] = $path;
    $gallery_html .= '<div class="thumb-box" onclick="openLightbox(\''.$path.'\')"><img src="'.$path.'" class="thumb-img"></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Details | JEJ Surveying Services</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800;900&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="assets/modern.css">

    <style>
        /* General Page Layout */
        .breadcrumb { margin-bottom: 25px; font-size: 14px; color: #718096; display: flex; align-items: center; gap: 10px; }
        .breadcrumb a { color: var(--primary); text-decoration: none; font-weight: 600; }
        
        .details-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 40px; align-items: start; }
        
        /* --- IMAGE GALLERY STYLES --- */
        .main-img-box {
            position: relative; border-radius: 16px; overflow: hidden; height: 450px;
            box-shadow: var(--shadow-soft); background: #EDF2F7; cursor: pointer; group;
        }
        .main-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
        .main-img-box:hover .main-img { transform: scale(1.02); }
        
        .gallery-grid {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 15px;
        }
        .thumb-box {
            height: 70px; border-radius: 10px; overflow: hidden; cursor: pointer; 
            border: 2px solid transparent; opacity: 0.8; transition: 0.2s;
        }
        .thumb-box:hover { border-color: var(--primary); opacity: 1; transform: translateY(-2px); }
        .thumb-img { width: 100%; height: 100%; object-fit: cover; }

        /* --- LIGHTBOX (FULL VIEW) --- */
        .lightbox {
            display: none; position: fixed; z-index: 2000; top: 0; left: 0;
            width: 100%; height: 100%; background: rgba(0,0,0,0.95);
            justify-content: center; align-items: center; flex-direction: column;
        }
        .lightbox img { 
            max-width: 90%; max-height: 85vh; border-radius: 4px; 
            box-shadow: 0 0 30px rgba(0,0,0,0.5); user-select: none;
        }
        .lb-controls {
            position: absolute; top: 50%; width: 100%; display: flex; justify-content: space-between; padding: 0 40px;
            transform: translateY(-50%); pointer-events: none; /* Let clicks pass through except on buttons */
        }
        .lb-btn {
            pointer-events: auto; background: rgba(255,255,255,0.1); color: white; border: none;
            width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
            backdrop-filter: blur(5px);
        }
        .lb-btn:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
        .close-btn { 
            position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer; 
            background: rgba(0,0,0,0.5); width: 40px; height: 40px; display: flex; 
            align-items: center; justify-content: center; border-radius: 50%;
        }

        /* --- SPECS & INFO --- */
        .specs-card { background: white; border-radius: 20px; padding: 35px; box-shadow: var(--shadow-soft); border: 1px solid #EDF2F7; margin-top: 30px; }
        .specs-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 30px 0; padding: 25px 0; border-top: 1px solid #EDF2F7; border-bottom: 1px solid #EDF2F7; }
        .spec-item small { display: block; font-size: 11px; text-transform: uppercase; color: #718096; font-weight: 700; margin-bottom: 4px; }
        .spec-item strong { font-size: 20px; color: var(--dark); font-weight: 800; }

        /* --- RESERVATION FORM (Sticky & Scrollable) --- */
        .form-card {
            background: white; border-radius: 20px; padding: 0;
            box-shadow: var(--shadow-hover); border: 1px solid #EDF2F7;
            position: sticky; top: 100px;
            max-height: calc(100vh - 120px);
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        
        .form-header {
            padding: 25px 30px 15px; background: white; z-index: 2;
            border-bottom: 1px solid #F7FAFC;
        }
        
        .form-body {
            padding: 0 30px 30px; 
            overflow-y: auto;
            scrollbar-width: thin; scrollbar-color: #CBD5E0 #F7FAFC;
        }
        .form-body::-webkit-scrollbar { width: 6px; }
        .form-body::-webkit-scrollbar-thumb { background-color: #CBD5E0; border-radius: 10px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 700; color: #4A5568; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border-radius: 12px; border: 1px solid #E2E8F0; background: #F7FAFC; font-size: 14px; }
        .btn-submit { width: 100%; background: var(--primary-gradient); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 700; cursor: pointer; margin-top: 10px; }
        
        #map-display { height: 300px; width: 100%; border-radius: 16px; margin-top: 15px; z-index: 1; }

        .nav-brand {
            font-size: 20px;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }

        @media (max-width: 900px) { 
            .details-grid { grid-template-columns: 1fr; } 
            .main-img-box { height: 300px; }
            .form-card { position: relative; top: 0; max-height: none; overflow: visible; }
        }
    </style>
</head>
<body>

    <div id="lightbox" class="lightbox">
        <div class="close-btn" onclick="closeLightbox()">&times;</div>
        
        <div class="lb-controls">
            <button class="lb-btn" onclick="changeSlide(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="lb-btn" onclick="changeSlide(1)"><i class="fa-solid fa-chevron-right"></i></button>
        </div>

        <div style="overflow: hidden; display: flex; justify-content: center; align-items: center; width: 100%; height: 85vh;">
            <img id="lightbox-img" src="" style="transition: transform 0.2s ease;">
        </div>
        
        <div style="display: flex; gap: 15px; margin-top: 15px; align-items: center;">
            <button class="lb-btn" onclick="zoomImage(-0.2)" style="width: 40px; height: 40px; font-size: 18px;" title="Zoom Out"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
            <div style="color: white; font-weight: 600; font-size: 14px;">
                <span id="lb-counter">1</span> / <?= count($js_images) ?>
            </div>
            <button class="lb-btn" onclick="zoomImage(0.2)" style="width: 40px; height: 40px; font-size: 18px;" title="Zoom In"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
        </div>
    </div>

    <nav class="nav">
        <div class="nav-left">
            <a href="index.php" class="brand-wrapper">
                <img src="assets/logo.png" alt="JEJ Logo" style="height: 50px; width: auto; margin-right: 10px; border-radius: 6px;">
                <span class="nav-brand">JEJ Surveying Services</span>
            </a>
        </div>
        <div class="nav-links desktop-only">
            <a href="index.php">Properties</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if(in_array($_SESSION['role'], ['SUPER ADMIN', 'ADMIN', 'MANAGER'])): ?>
                    <a href="admin.php">Admin Panel</a>
                <?php else: ?>
                    <a href="my_reservations.php">My Reservations</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="user-menu">
            <span class="user-name"><?= htmlspecialchars($_SESSION['fullname']) ?></span>
            <a href="logout.php" style="color: #E53E3E; margin-left:15px;"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </nav>

    <main class="main-content" style="margin-top: 40px;">
        <div class="breadcrumb">
            <a href="index.php">Properties</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span><?= htmlspecialchars($lot['location']) ?></span>
            <i class="fa-solid fa-chevron-right"></i>
            <strong>Block <?= $lot['block_no'] ?> Lot <?= $lot['lot_no'] ?></strong>
        </div>

        <div class="details-grid">
            
            <div class="left-col">
                <div class="main-img-box" onclick="openLightbox('<?= $main_img ?>')">
                    <img src="<?= $main_img ?>" class="main-img">
                    <span class="badge <?= $lot['status'] ?>" style="top:25px; left:25px; right:auto;"><?= $lot['status'] ?></span>
                    <div style="position: absolute; bottom: 20px; right: 20px; background: rgba(0,0,0,0.6); color: white; padding: 8px 15px; border-radius: 30px; font-size: 12px; font-weight: 600; pointer-events: none;">
                        <i class="fa-solid fa-expand"></i> Full View
                    </div>
                </div>

                <div class="gallery-grid">
                    <div class="thumb-box" onclick="openLightbox('<?= $main_img ?>')">
                        <img src="<?= $main_img ?>" class="thumb-img">
                    </div>
                    <?= $gallery_html ?>
                </div>

                <div class="specs-card">
                    <span style="font-size: 13px; font-weight: 800; color: var(--primary); text-transform: uppercase;">
                        <?= $lot['property_type'] ?: 'Residential Lot' ?>
                    </span>
                    <h1 style="font-size: 32px; font-weight: 900; color: var(--dark); margin: 10px 0;">
                        Block <?= $lot['block_no'] ?>, Lot <?= $lot['lot_no'] ?>
                    </h1>
                    <div style="color: #718096;"><i class="fa-solid fa-location-dot" style="color: #E53E3E;"></i> <?= $lot['location'] ?></div>

                    <div class="specs-grid">
                        <div class="spec-item"><small>Lot Area</small><strong><?= number_format($lot['area']) ?> m²</strong></div>
                        <div class="spec-item"><small>Price / SQM</small><strong>₱<?= number_format($lot['price_per_sqm']) ?></strong></div>
                        <div class="spec-item"><small>Total Price</small><strong style="color: var(--primary);">₱<?= number_format($lot['total_price']) ?></strong></div>
                    </div>

                    <p style="color: #4A5568; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($lot['property_overview'] ?? 'No description available.')) ?>
                    </p>

                    <?php if(!empty($lot['latitude'])): ?>
                    <div style="margin-top: 30px; border-top: 1px dashed #E2E8F0; padding-top: 20px;">
                        <h4 style="font-weight: 800; margin-bottom: 10px;"><i class="fa-solid fa-map-location-dot" style="color: var(--primary);"></i> Property Location</h4>
                        <div id="map-display"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="right-col">
                <div class="form-card">
                    <?php if($lot['status'] == 'AVAILABLE'): ?>
                        <div class="form-header">
                            <h3 style="font-size: 22px; font-weight: 800; margin-bottom: 5px;">Reserve Now</h3>
                            <p style="color: #718096; font-size: 13px;">Secure this property today.</p>
                        </div>

                        <div class="form-body">
                            <form action="actions.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="reserve">
                                <input type="hidden" name="lot_id" value="<?= $lot['id'] ?>">
                                
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="fullname" class="form-control" value="<?= $_SESSION['fullname'] ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Address <span style="color:red">*</span></label>
                                    <input type="email" name="email" class="form-control" required placeholder="juandelacruz@gmail.com">
                                </div>

                                <div style="display:flex; gap:10px;">
                                    <div class="form-group" style="flex:1;">
                                        <label>Mobile No. <span style="color:red">*</span></label>
                                        <input type="text" name="contact_number" class="form-control" required>
                                    </div>
                                    <div class="form-group" style="flex:1;">
                                        <label>Birth Date <span style="color:red">*</span></label>
                                        <input type="date" name="birth_date" class="form-control" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Home Address <span style="color:red">*</span></label>
                                    <input type="text" name="address" class="form-control" required>
                                </div>
                                
                                <div style="border-top: 1px dashed #CBD5E0; margin: 20px 0;"></div>
                                
                                <div class="form-group">
                                    <label>Valid ID</label>
                                    <input type="file" name="valid_id" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Selfie with ID</label>
                                    <input type="file" name="selfie_id" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Proof of Payment</label>
                                    <input type="file" name="proof" class="form-control" required>
                                </div>

                                <button type="submit" class="btn-submit">Submit Reservation</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 20px; background: #FFF5F5; color: #C53030;">
                            <i class="fa-solid fa-lock" style="font-size: 30px; margin-bottom: 10px;"></i><br>
                            <strong>Unavailable</strong><br>
                            This property is currently <?= $lot['status'] ?>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div style="margin-bottom: 25px;">
            <img src="assets/logo.png" alt="JEJ Logo" style="height: 70px; width: auto; border-radius: 8px; background: white; padding: 5px;">
        </div>
        <p><strong>JEJ Surveying Services</strong></p>
        <p style="opacity:0.6; margin-top:10px;">Professional surveying and blueprint solutions.</p>
        <div style="margin-top: 40px; font-size: 13px; opacity: 0.4;">
            &copy; <?= date('Y') ?> All Rights Reserved.
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // --- MAP LOGIC ---
        <?php if(!empty($lot['latitude'])): ?>
        var map = L.map('map-display').setView([<?= $lot['latitude'] ?>, <?= $lot['longitude'] ?>], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        L.marker([<?= $lot['latitude'] ?>, <?= $lot['longitude'] ?>]).addTo(map);
        <?php endif; ?>

        // --- LIGHTBOX LOGIC ---
        const allImages = <?php echo json_encode($js_images); ?>;
        let currentIdx = 0;
        let currentZoom = 1;

        function zoomImage(step) {
            currentZoom += step;
            if (currentZoom < 0.5) currentZoom = 0.5; // Min zoom limit
            if (currentZoom > 5) currentZoom = 5;     // Max zoom limit
            document.getElementById('lightbox-img').style.transform = `scale(${currentZoom})`;
        }

        function resetZoom() {
            currentZoom = 1;
            document.getElementById('lightbox-img').style.transform = `scale(${currentZoom})`;
        }

        function openLightbox(src) {
            const index = allImages.indexOf(src);
            if(index !== -1) {
                currentIdx = index;
                resetZoom();
                updateLightboxImage();
                document.getElementById('lightbox').style.display = 'flex';
            }
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            resetZoom();
        }

        function changeSlide(step) {
            currentIdx += step;
            if (currentIdx >= allImages.length) currentIdx = 0;
            if (currentIdx < 0) currentIdx = allImages.length - 1;
            resetZoom();
            updateLightboxImage();
        }

        function updateLightboxImage() {
            document.getElementById('lightbox-img').src = allImages[currentIdx];
            document.getElementById('lb-counter').innerText = currentIdx + 1;
        }

        document.addEventListener('keydown', function(e) {
            if(document.getElementById('lightbox').style.display === 'flex') {
                if(e.key === 'ArrowLeft') changeSlide(-1);
                if(e.key === 'ArrowRight') changeSlide(1);
                if(e.key === 'Escape') closeLightbox();
            }
        });
    </script>
</body>
</html>