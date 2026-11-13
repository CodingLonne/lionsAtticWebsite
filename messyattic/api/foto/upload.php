<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// setup
include("../api-helper.php");

$method = get_method();

include("../gebruiker/authenticate.php");
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
$token_purpose = execute_read_query($conn, 'SELECT huishouden_id, kamer_id, voorwerp_id, verloopt_op FROM upload_tokens WHERE token = ?', [$image_token]);
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
$huishoudenId = $token_purpose['rows'][0]['huishouden_id'] ?? null;
$kamerId = $token_purpose['rows'][0]['kamer_id'] ?? null;
$voorwerpId = $token_purpose['rows'][0]['voorwerp_id'] ?? null;

if(!is_null($huishoudenId) || !is_null($kamerId) || !is_null($voorwerpId)) {
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
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    // Determine extension
    $extension = match ($imageInfo['mime']) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
} else {
    exit;
}

if (!is_null($huishoudenId)) {
    // Store file
    $image_path = '/home/lionsatm/images/' . $huishoudenId . '/profile.' . $extension;
    if (!rename($tmpFile, $image_path)) {
        send_response(['status' => 'error', 'message' => 'Failed to save image'], $code=500);
        exit;
    }

    // Update attached picture for household  
    update_query_expect_result($conn, "UPDATE huishoudens SET foto = ? WHERE id = ?", [$image_path, $huishoudenId], errMsg: "Could not assign image to huishouden");

    // remove token
    $removeResult = execute_cud_query($conn, "DELETE FROM upload_tokens WHERE token = ?", [$image_token]);
    if (!$removeResult["successful"] || $removeResult["affected_rows"] <= 0) {
        error_log("Expected to remove token, but image token " . $image_token . " was not found");
    }

    send_response([
        'image_path' => $image_path,
        'width'      => $width,
        'height'     => $height,
    ], $code=200);
    }

if (!is_null($kamerId)) {
    // Get huishoudenId of kamer
    $rows = select_query_expect_result($conn, "SELECT huishouden_id FROM kamers WHERE id = ?", [$kamerId]);
    $huishoudenId = $rows["rows"][0]['huishouden_id'];

    // Store file
    $image_path = '/home/lionsatm/images/' . $huishoudenId . '/rooms/' . $kamerId . '/profile.' . $extension;
    if (!rename($tmpFile, $image_path)) {
        send_response(['status' => 'error', 'message' => 'Failed to save image'], $code=500);
        exit;
    }
    // Update attached picture for household  
    update_query_expect_result($conn, "UPDATE kamers SET foto = ?, breedte = ?, hoogte = ? WHERE id = ?", [$image_path, $imageInfo[0], $imageInfo[1], $kamerId], errMsg: 'Could not assign image to kamer');

    // remove token
    $removeResult = execute_cud_query($conn, "DELETE FROM upload_tokens WHERE token = ?", [$image_token]);
    if (!$removeResult["successful"] || $removeResult["affected_rows"] <= 0) {
        error_log("Expected to remove token, but image token " . $image_token . " was not found");
    }

    send_response([
        'image_path' => $image_path,
        'width'      => $width,
        'height'     => $height,
    ], $code=200);
    }

if (!is_null($voorwerpId)) {
    $rows = select_query_expect_result($conn, "SELECT huishouden_id FROM voorwerpen WHERE id = ?", [$voorwerpId]);
    $huishoudenId = $rows["rows"][0]['huishouden_id'];

    $image_path = '/home/lionsatm/images/' . $huishoudenId . '/items/' . $voorwerpId . '/profile.' . $extension;
    if (!is_dir(dirname($image_path))) {
        mkdir(dirname($image_path), 0755, true);
    }
    if (!rename($tmpFile, $image_path)) {
        send_response(['status' => 'error', 'message' => 'Failed to save image'], $code=500);
        exit;
    }
    update_query_expect_result($conn, "UPDATE voorwerpen SET foto = ? WHERE id = ?", [$image_path, $voorwerpId], errMsg: 'Could not assign image to item');

    $removeResult = execute_cud_query($conn, "DELETE FROM upload_tokens WHERE token = ?", [$image_token]);
    if (!$removeResult["successful"] || $removeResult["affected_rows"] <= 0) {
        error_log("Expected to remove token, but image token " . $image_token . " was not found");
    }

    send_response([
        'image_path' => $image_path,
        'width'      => $width,
        'height'     => $height,
    ], $code=200);
}