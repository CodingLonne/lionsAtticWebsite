<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include("../api-helper.php");

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
/* 
POST
request
{
    gebruikersnaam: string!
    wachtwoord:     string!
}
response
{
    id:             uuid!
    verloopt_op:    datetime!
    token:          string!
}
*/
$username = trim($data['gebruikersnaam'] ?? '');
$password = $data['wachtwoord'] ?? '';

//connect to database
$conn = database_connect();

//check if username is known
$found_users = select_query_expect_result($conn, 
    'SELECT id, wachtwoord_hash FROM gebruikers WHERE gebruikers_naam = ?', 
    [$username],
    emptyResultMessage: 'Gebruikersnaam of wachtwoord onjuist', emptyResultCode: 400);

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
            'id' => $user_id,
            'status' => 'success',
            'token' => $generatedToken,
            'verloopt_op' => $expiresAt,
        ]);
        $conn->close();
        exit;
    } else {
        send_response([
            'status'=> 'failed',
            'message' => 'Login token opslaan in database gefaald',
            'extra_info' => $insertResult
        ], $code = 500);
        $conn->close();
        exit;
    }
} else {
    send_response([
        'status'=> 'failed',
        'message' => 'Gebruikersnaam of wachtwoord onjuist'
    ], $code = 400);
    $conn->close();
    exit;
}
}
?>