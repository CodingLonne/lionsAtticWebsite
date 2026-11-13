<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include "api-helper.php";

$method = get_method();
$data = get_request_data();

if ($method === 'POST') {
/*
request
{
    gebruikersnaam: string!
    wachtwoord: string!
}
response
{

}
*/

$username = trim($data['gebruikersnaam'] ?? '');
$password = $data['wachtwoord'] ?? '';
$hash = password_hash($password, "2y");

//connect to database
$conn = database_connect();

//check if username is already taken
$existing_users = execute_read_query($conn, 'SELECT id FROM gebruikers WHERE gebruikers_naam = ?', [$username]);
if (!$existing_users['successful']) {
    send_response([
        'status' => 'gefaald',
        'message' => 'Error tijdens query uitvoeren',
    ], 500);
    exit;
}
if (!empty($existing_users['rows'])) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Gebruikersnaam bezet']);
    exit;
}

$insertResult = insert_query($conn, 
    'INSERT INTO gebruikers (gebruikers_naam, wachtwoord_hash) VALUES (?, ?)', 
    [$username, $hash],
    errMsg: 'Account opslaan in database mislukt');

send_response([
    'status' => 'success',
    'message' => 'Account succesvol aangemaakt',
], 201);

$conn->close();
}
?>