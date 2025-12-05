<?php
// save_photo.php

header('Content-Type: application/json');

// Only accept POST with JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Only POST method allowed'
    ]);
    exit;
}

// Read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['imageData'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON or missing imageData'
    ]);
    exit;
}

$imageData = $data['imageData'];
$latitude  = $data['latitude']  ?? null;
$longitude = $data['longitude'] ?? null;
$accuracy  = $data['accuracy']  ?? null;

// Remove "data:image/png;base64," prefix if present
if (strpos($imageData, 'base64,') !== false) {
    $parts = explode('base64,', $imageData, 2);
    $imageData = $parts[1];
}

// Decode Base64
$imageBinary = base64_decode($imageData);

if ($imageBinary === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to decode image data'
    ]);
    exit;
}

// Ensure uploads directory exists
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create uploads directory'
        ]);
        exit;
    }
}

// Generate unique filename
$timestamp = date('Ymd_His');
try {
    $random = bin2hex(random_bytes(4));
} catch (Exception $e) {
    $random = mt_rand(100000, 999999);
}
$filename = "photo_{$timestamp}_{$random}.png";
$filepath = $uploadDir . '/' . $filename;

// Save image file
if (file_put_contents($filepath, $imageBinary) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save image file'
    ]);
    exit;
}

// Optional: log location data
$googleMapLink = null;
if (!empty($latitude) && !empty($longitude)) {
    $googleMapLink = 'https://www.google.com/maps?q=' . $latitude . ',' . $longitude;
}

// Log data including Google Maps link
$logLine = sprintf(
    "[%s] file=%s lat=%s lng=%s acc=%s map=%s\n",
    date('Y-m-d H:i:s'),
    $filename,
    $latitude ?? 'NULL',
    $longitude ?? 'NULL',
    $accuracy ?? 'NULL',
    $googleMapLink ?? 'NULL'
);
file_put_contents($uploadDir . '/location_log.txt', $logLine, FILE_APPEND);

// Respond with JSON
echo json_encode([
    'success'  => true,
    'message'  => 'Image saved successfully',
    'filepath' => 'uploads/' . $filename,
    'latitude' => $latitude,
    'longitude'=> $longitude,
    'accuracy' => $accuracy,
]);
