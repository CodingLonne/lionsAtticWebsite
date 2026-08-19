<?php

/**
 * @return array{authenticated: bool, user_id: string}
 */

$headers = getallheaders();
$bearerToken = $headers["Authorization"];
if (substr($bearerToken, 0, 7) != "Bearer " && substr($bearerToken, 0, 7) != "bearer ") {
    send_response([
        'status' => 'error', 
        'message' => 'Token must start with \'Bearer \''
    ], $code=400);
    exit;
}
$token = substr($bearerToken,7);

$hashedToken = hash('sha256', $token);

$conn = database_connect();
$token_search_result = execute_read_query(
    $conn, 
    'SELECT gebruiker_id FROM auth_tokens WHERE token_hash = ? AND verloopt_op > NOW()', 
    [$hashedToken]
);
if (!$token_search_result['successful']) {
    send_response([
        'status' => 'failed',
        'message' => 'Something went wrong authenticating in the database'
    ], $code=500);
    exit;
}
if (empty($token_search_result['rows'])) {
    send_response([
        'status' => 'error', 
        'message' => 'Invalid or expired token'
    ], $code=401);
    exit;
}

$userId = $token_search_result['rows'][0]['gebruiker_id'];

?>