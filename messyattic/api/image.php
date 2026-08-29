<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// setup
include "api-helper.php";

$method = get_method();

include("authenticate.php");
/** @var mixed  $userId */
/** @var mysqli $conn   */

// check that method is post
if ($method !== 'POST') {
    http_response_code(405); // Method Not Allowed
    header('Allow: POST');
    send_response(['status' => 'error', 'message' => 'Only POST is allowed']);
    exit;
}

// find image token
$image_token = $_GET['token'] ?? null;
if (!$image_token) {
    http_response_code(400);
    send_response(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

// connect to database
$conn = database_connect();

// process token
$token_purpose = execute_read_query($conn, 'SELECT huishouden_id, verloopt_op FROM upload_tokens WHERE token = ?', [$image_token]);
if (!$token_purpose['successful']) {
    send_response([
        'status' => 'failed',
        'message' => 'Error while executing query',
    ], 500);
    exit;
} else if (empty($token_purpose['rows'])) {
    send_response([
        'status' => 'failed',
        'message' => 'User does not exist',
    ], 500);
    exit;
}
$huishoudenId = $token_purpose['rows'][0]['huishouden_id'];
if (!is_null($huishoudenId)) {
    // get image data and enforce max size
    $imageData = file_get_contents('php://input');
    if (empty($imageData)) {
        send_response(['status' => 'error', 'message' => 'No image data received'], $code=400);
        exit;
    }
    if (strlen($imageData) > 5 * 1024 * 1024) {
        send_response(['status' => 'error', 'message' => 'Image too large'], $code=400);
        exit;
    }
    // Validate it's actually an image by writing to a temp file and checking
    $tmpFile = tempnam(sys_get_temp_dir(), 'upload_');
    file_put_contents($tmpFile, $imageData);
    $imageInfo = getimagesize($tmpFile);
    if ($imageInfo === false) {
        unlink($tmpFile);
        send_response(['status' => 'error', 'message' => 'Invalid image data'], $code=400);
        exit;
    }
    // Store file
    $extension = match ($imageInfo['mime']) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    $image_path = '/home/lionsatm/images/' . $huishoudenId . '/profile.' . $extension;
    if (!rename($tmpFile, $image_path)) {
        send_response(['status' => 'error', 'message' => 'Failed to save image'], $code=500);
        exit;
    }
    // Update attached picture for household  
    $updateResult = execute_cud_query($conn, "UPDATE huishoudens SET foto = ? WHERE id = ?", [$image_path, $huishoudenId]);
    if (!$updateResult['successful'] || $updateResult['affected_rows'] <= 0) {
        send_response([
            'status' => 'failed',
            'message' => 'Could not assign image to huishouden',
            'extra_info' => $updateResult
        ], 500);
        exit;
    }

    // remove token
    $removeResult = execute_cud_query($conn, "DELETE FROM upload_tokens WHERE token = ?", [$image_token]);
    if (!$removeResult["successful"] || $removeResult["affected_rows"] <= 0) {
        error_log("Expected to remove token, but image token " . $image_token . " was not found");
    }

    send_response([
        'image_path' => $image_path
    ], $code=200);

}