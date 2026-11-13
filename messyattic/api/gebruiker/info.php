<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include("../api-helper.php");

$method = get_method();
$data = get_request_data();

include("../gebruiker/authenticate.php");
/** @var mixed  $userId */
/** @var mysqli $conn   */

validate_method("GET");
/*
request
{
    id:             uuid!
}
response
{
    naam:           string!
}
*/
$user_id = $data["id"] ?? null;
if (empty($user_id)) {
    send_response(['status' => 'error', 'message' => 'id is required'], 400);
    exit;
}

$user_result = select_query_expect_result($conn, "SELECT gebruikers_naam FROM gebruikers WHERE id = ?", [$user_id], emptyResultMessage: "User not found", emptyResultCode: 400);
$row = $user_result['rows'][0];

$response = [
    'naam' => $row['gebruikers_naam']
];

send_response($response, 200);
exit;