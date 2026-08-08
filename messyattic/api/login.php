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
    $found_users = execute_read_query($conn, 'SELECT id, wachtwoord_hash FROM gebruikers WHERE gebruikers_naam = ?', [$username]);
    if (!$found_users['successful']) {
        send_response([
            'status' => 'failed',
            'message' => 'Error while executing query',
        ], 500);
        exit;
    } else if (empty($found_users['rows'])) {
        send_response([
            'status' => 'failed',
            'message' => 'User does not exist',
        ], 500);
    } else {
        $user_id = $found_users['rows'][0]['id'];
        $database_wachtwoord_hash = $found_users['rows'][0]['wachtwoord_hash'];
        if (password_verify($password, $database_wachtwoord_hash)) {
            //password verified
            $generatedToken = bin2hex(openssl_random_pseudo_bytes(32));
            $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
            $hashedToken = hash('sha256', $generatedToken);
            $insertResult = execute_cud_query($conn, 'INSERT INTO auth_tokens (gebruiker_id, token_hash, verloopt_op) VALUES (?, ?, ?)', [$user_id, $hashedToken, $expiresAt]);
            if ($insertResult['successful'] && $insertResult['affected_rows'] > 0) {
                send_response([
                    'status' => 'success',
                    'token' => $generatedToken,
                    'expires_at' => $expiresAt,
                ]);
                exit;
            } else {
                send_response([
                    'status'=> 'failed',
                    'message' => 'Failed to insert into database',
                    'extra_info' => $insertResult
                ], $code = 500);
                exit;
            }
        } else {
            send_response([
                'status'=> 'failed',
                'message' => 'Invalid password or wrong username'
            ], $code = 400);
        }
    }
}
?>