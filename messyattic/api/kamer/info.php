<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include("../api-helper.php");

$method = get_method();
$data = get_request_data();

include("../gebruiker/authenticate.php");
/** @var mixed  $userId */
/** @var mysqli $conn   */


if ($method != "GET") {
    send_response([
        'status' => 'failed',
        'message' => 'Method niet toegestaan',
    ], $code = 405);
    exit;
}
/* 
GET
request
{
    id:             uuid!
}
response
{
    naam:           string!
    huishouden_id:  uuid!
    foto:           url
    breedte:        int!
    hoogte:         int!
    voorwerpen:     [uuid!]!
}
*/
$room_id = $data["id"];

// implementation
$room_result = select_query_expect_result($conn, "SELECT naam, huishouden_id, foto, breedte, hoogte FROM kamers WHERE id = ?", [$room_id], emptyResultMessage: "Room not found", emptyResultCode: 400);
$row = $room_result['rows'][0];
confirm_access_to_household($conn, $userId, $row['huishouden_id']);

$response = [
    'naam'          => $row['naam'],
    'huishouden_id' => $row['huishouden_id'],
    'foto'          => $row['foto'],
    'breedte'       => $row['breedte'],
    'hoogte'        => $row['hoogte'],
    'voorwerpen'    => [],
];

$items_result = select_query($conn, "SELECT id FROM voorwerpen WHERE kamer_id = ?", [$room_id]);
foreach ($items_result["rows"] as $r) {
    $response['voorwerpen'][] = $r['id'];
}

send_response($response, 200);
$conn->close();
exit;
?>