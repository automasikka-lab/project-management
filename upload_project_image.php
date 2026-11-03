<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
header('Content-Type: application/json');

startSecureSession();
if (empty($_SESSION['admin_id'])) { echo json_encode(['ok' => false, 'msg' => 'Unauthorized. Silakan login sebagai admin.']); exit; }
requireWriteAccess(); // hak akses: super admin menentukan CRUD / Read Only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'No image uploaded or upload error']);
    exit;
}

$projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
if ($projectId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid project ID']);
    exit;
}

$file = $_FILES['image'];

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['ok' => false, 'msg' => 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.']);
    exit;
}

$maxSize = 2 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'msg' => 'Ukuran gambar maksimal 2MB.']);
    exit;
}

$cropX = isset($_POST['crop_x']) ? (float)$_POST['crop_x'] : 0;
$cropY = isset($_POST['crop_y']) ? (float)$_POST['crop_y'] : 0;
$cropW = isset($_POST['crop_width']) ? (float)$_POST['crop_width'] : 0;
$cropH = isset($_POST['crop_height']) ? (float)$_POST['crop_height'] : 0;

$srcImage = null;
switch ($mimeType) {
    case 'image/jpeg':
        $srcImage = imagecreatefromjpeg($file['tmp_name']);
        break;
    case 'image/png':
        $srcImage = imagecreatefrompng($file['tmp_name']);
        break;
    case 'image/webp':
        $srcImage = imagecreatefromwebp($file['tmp_name']);
        break;
}

if (!$srcImage) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal membaca gambar.']);
    exit;
}

$srcW = imagesx($srcImage);
$srcH = imagesy($srcImage);

if ($cropW > 0 && $cropH > 0) {
    $cropX = max(0, min($cropX, $srcW));
    $cropY = max(0, min($cropY, $srcH));
    $cropW = min($cropW, $srcW - $cropX);
    $cropH = min($cropH, $srcH - $cropY);

    $cropped = imagecreatetruecolor((int)$cropW, (int)$cropH);
    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
        imagealphablending($cropped, false);
        imagesavealpha($cropped, true);
        $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
        imagefilledrectangle($cropped, 0, 0, (int)$cropW - 1, (int)$cropH - 1, $transparent);
    }
    imagecopyresampled($cropped, $srcImage, 0, 0, (int)$cropX, (int)$cropY, (int)$cropW, (int)$cropH, (int)$cropW, (int)$cropH);
    imagedestroy($srcImage);
    $srcImage = $cropped;
}

$outputSize = 800;
$finalW = imagesx($srcImage);
$finalH = imagesy($srcImage);

if ($finalW > $outputSize || $finalH > $outputSize) {
    if ($finalW >= $finalH) {
        $newW = $outputSize;
        $newH = (int)($finalH * ($outputSize / $finalW));
    } else {
        $newH = $outputSize;
        $newW = (int)($finalW * ($outputSize / $finalH));
    }
    $resized = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newW, $newH, $finalW, $finalH);
    imagedestroy($srcImage);
    $srcImage = $resized;
}

// Fill white background for PNG/WebP with transparency before saving as JPEG
if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
    $finalW = imagesx($srcImage);
    $finalH = imagesy($srcImage);
    $background = imagecreatetruecolor($finalW, $finalH);
    $white = imagecolorallocate($background, 255, 255, 255);
    imagefill($background, 0, 0, $white);
    imagecopy($background, $srcImage, 0, 0, 0, 0, $finalW, $finalH, $finalW, $finalH);
    imagedestroy($srcImage);
    $srcImage = $background;
}

$uploadDir = __DIR__ . '/../uploads/projects/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = $projectId . '.jpg';
$filepath = $uploadDir . $filename;

$result = imagejpeg($srcImage, $filepath, 90);
imagedestroy($srcImage);

if (!$result) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan gambar.']);
    exit;
}

$imageUrl = 'uploads/projects/' . $filename . '?v=' . time();

try {
    $stmt = $pdo->prepare("UPDATE projects SET image_path = ? WHERE id = ?");
    $stmt->execute([$imageUrl, $projectId]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan ke database.']);
    exit;
}

logActivity('create', 'image', 'Gambar proyek diunggah', $projectId);
echo json_encode(['ok' => true, 'url' => $imageUrl]);
