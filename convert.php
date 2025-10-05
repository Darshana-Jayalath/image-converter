<?php
// Simple and reliable image converter
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Response function
function respond($success, $message, $file = null, $filename = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'file' => $file,
        'filename' => $filename
    ]);
    exit;
}

// Check request
if (!isset($_FILES['image']) || !isset($_POST['format'])) {
    respond(false, 'Missing image or format');
}

$uploadedFile = $_FILES['image'];
$format = strtolower($_POST['format']);

// Check upload errors
if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    respond(false, 'Upload failed: Error code ' . $uploadedFile['error']);
}

// Validate format
$allowedFormats = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
if (!in_array($format, $allowedFormats)) {
    respond(false, 'Invalid format selected');
}

// Setup output directory
$outputDir = 'converted/';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// Clean old files
foreach (glob($outputDir . '*') as $file) {
    if (time() - filemtime($file) > 3600) {
        @unlink($file);
    }
}

// Generate output filename
$baseName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
$baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
$outputName = $baseName . '_' . time() . '.' . ($format == 'jpeg' ? 'jpg' : $format);
$outputPath = $outputDir . $outputName;

// Method 1: Try ImageMagick (more reliable)
if (extension_loaded('imagick')) {
    try {
        $imagick = new Imagick($uploadedFile['tmp_name']);
        
        // Set format
        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(90);
                // Add white background for transparency
                $imagick->setImageBackgroundColor('white');
                $imagick = $imagick->flattenImages();
                break;
            case 'png':
                $imagick->setImageFormat('png');
                break;
            case 'gif':
                $imagick->setImageFormat('gif');
                break;
            case 'bmp':
                $imagick->setImageFormat('bmp');
                break;
            case 'webp':
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality(90);
                break;
        }
        
        // Save file
        $imagick->writeImage($outputPath);
        $imagick->clear();
        $imagick->destroy();
        
        if (file_exists($outputPath)) {
            respond(true, 'Converted successfully', $outputPath, $outputName);
        }
        
    } catch (Exception $e) {
        // If ImageMagick fails, try GD below
    }
}

// Method 2: Use GD Library (fallback)
if (!extension_loaded('gd')) {
    respond(false, 'Neither ImageMagick nor GD library is available. Please enable GD in php.ini');
}

// Detect image type
$imageInfo = @getimagesize($uploadedFile['tmp_name']);
if (!$imageInfo) {
    respond(false, 'Invalid image file');
}

$mimeType = $imageInfo['mime'];

// Create image resource from uploaded file
$sourceImage = null;

switch ($mimeType) {
    case 'image/jpeg':
        $sourceImage = @imagecreatefromjpeg($uploadedFile['tmp_name']);
        break;
    case 'image/png':
        $sourceImage = @imagecreatefrompng($uploadedFile['tmp_name']);
        break;
    case 'image/gif':
        $sourceImage = @imagecreatefromgif($uploadedFile['tmp_name']);
        break;
    case 'image/bmp':
    case 'image/x-ms-bmp':
        if (function_exists('imagecreatefrombmp')) {
            $sourceImage = @imagecreatefrombmp($uploadedFile['tmp_name']);
        } else {
            respond(false, 'BMP format not supported by your PHP version');
        }
        break;
    case 'image/webp':
        if (function_exists('imagecreatefromwebp')) {
            $sourceImage = @imagecreatefromwebp($uploadedFile['tmp_name']);
        } else {
            respond(false, 'WEBP format not supported by your PHP version');
        }
        break;
    default:
        respond(false, 'Unsupported image type: ' . $mimeType);
}

if (!$sourceImage) {
    respond(false, 'Failed to read image. File may be corrupted.');
}

// Get dimensions
$width = imagesx($sourceImage);
$height = imagesy($sourceImage);

// Convert based on target format
$result = false;

switch ($format) {
    case 'jpg':
    case 'jpeg':
        // Create new true color image
        $newImage = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);
        imagecopy($newImage, $sourceImage, 0, 0, 0, 0, $width, $height);
        $result = imagejpeg($newImage, $outputPath, 90);
        imagedestroy($newImage);
        break;
        
    case 'png':
        imagesavealpha($sourceImage, true);
        $result = imagepng($sourceImage, $outputPath, 9);
        break;
        
    case 'gif':
        $result = imagegif($sourceImage, $outputPath);
        break;
        
    case 'bmp':
        if (function_exists('imagebmp')) {
            $result = imagebmp($sourceImage, $outputPath);
        } else {
            imagedestroy($sourceImage);
            respond(false, 'BMP export not supported by your PHP version');
        }
        break;
        
    case 'webp':
        if (function_exists('imagewebp')) {
            $result = imagewebp($sourceImage, $outputPath, 90);
        } else {
            imagedestroy($sourceImage);
            respond(false, 'WEBP export not supported by your PHP version');
        }
        break;
}

imagedestroy($sourceImage);

if ($result && file_exists($outputPath)) {
    respond(true, 'Conversion successful', $outputPath, $outputName);
} else {
    respond(false, 'Failed to save converted image. Check folder permissions.');
}
?>