<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once '../includes/config.php';

// Validate authentication
$user_id = validateToken();
if (!$user_id) {
    respondError('Unauthorized', 401);
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    respondError('No file uploaded or upload error');
}

$file = $_FILES['file'];
$fileType = mime_content_type($file['tmp_name']) ?: $file['type'];
$fileSize = $file['size'];
$fileTmpPath = $file['tmp_name'];
$originalName = $file['name'];

// Validate file size
if ($fileSize > MAX_FILE_SIZE) {
    respondError('File too large. Maximum size is ' . formatFileSize(MAX_FILE_SIZE));
}

// Validate file type
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$allowedVideoTypes = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm'];
$allowedTypes = array_merge($allowedImageTypes, $allowedVideoTypes);

if (!in_array($fileType, $allowedTypes)) {
    respondError('Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG, MP4, MPEG, MOV');
}

// Determine media type
$mediaType = in_array($fileType, $allowedImageTypes) ? 'image' : 'video';

// Generate unique filename
$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
$filePath = UPLOAD_DIR . $filename;
$relativePath = 'uploads/' . $filename;

// Get image dimensions if it's an image
$width = null;
$height = null;
$duration = null;

if ($mediaType === 'image' && $fileType !== 'image/svg+xml') {
    $imageInfo = getimagesize($fileTmpPath);
    if ($imageInfo) {
        $width = $imageInfo[0];
        $height = $imageInfo[1];
    }
} elseif ($mediaType === 'video') {
    // You can use FFmpeg to get video duration if installed
    // This is optional and requires FFmpeg
    if (function_exists('exec')) {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($fileTmpPath);
        $duration = floatval(exec($cmd));
    }
}

// Move uploaded file
if (!move_uploaded_file($fileTmpPath, $filePath)) {
    respondError('Failed to save file');
}

// Compress image if it's large and an image
if ($mediaType === 'image' && $fileType !== 'image/svg+xml' && $fileSize > 2 * 1024 * 1024) {
    compressImage($filePath, $filePath, 80);
}

// Insert into database
$stmt = $db->prepare("
    INSERT INTO media (user_id, filename, original_filename, file_path, file_type, mime_type, file_size, width, height, duration) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssssiiii",
    $user_id,
    $filename,
    $originalName,
    $relativePath,
    $mediaType,
    $fileType,
    $fileSize,
    $width,
    $height,
    $duration
);

if ($stmt->execute()) {
    $mediaId = $db->insert_id;
    
    // Log activity
    logActivity($user_id, 'upload', "Uploaded {$mediaType}: {$originalName}");
    
    respond([
        'success' => true,
        'message' => 'File uploaded successfully',
        'media' => [
            'id' => $mediaId,
            'filename' => $filename,
            'original_filename' => $originalName,
            'url' => buildAppUrl('backend/' . $relativePath),
            'type' => $mediaType,
            'mime_type' => $fileType,
            'size' => $fileSize,
            'size_formatted' => formatFileSize($fileSize),
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    // Delete file if database insert fails
    unlink($filePath);
    respondError('Failed to save to database: ' . $db->error);
}

// Helper function to compress image
function compressImage($source, $destination, $quality) {
    $info = getimagesize($source);
    
    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($source);
        imagepng($image, $destination, 9);
    } elseif ($info['mime'] == 'image/gif') {
        $image = imagecreatefromgif($source);
        imagegif($image, $destination);
    }
    
    if (isset($image)) {
        imagedestroy($image);
    }
}
?>
