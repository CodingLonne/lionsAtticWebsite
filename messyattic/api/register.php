<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include "api-helper.php";

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    //connect to database
    $conn = database_connect();

    //check if username is already taken
    $existing_users = execute_read_query($conn, 'SELECT id FROM gebruikers WHERE gebruikers_naam = ?', [$username]);
    if (!$existing_users['successful']) {
        send_response([
            'status' => 'failed',
            'message' => 'Error while executing query',
        ], 500);
        exit;
    }
    if (!empty($existing_users['rows'])) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username already taken']);
        exit;
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insertResult = execute_cud_query($conn, 'INSERT INTO gebruikers (gebruikers_naam, wachtwoord_hash) VALUES (?, ?)', [$username, $hash]);

    if ($insertResult['successful'] && $insertResult['affected_rows']>0) {
        send_response([
            'status' => 'success',
            'message' => 'Account created',
            'extra_info' => $insertResult
        ], 201);
    } else {
        send_response([
            'status' => 'failed',
            'message' => 'record failed to be made.',
            'extra_info' => $insertResult
        ], 500);
    }

    $conn->close();
}
?>