<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include "api-helper.php";

$method = get_method();
$data = get_request_data();

include("authenticate.php");
/** @var mixed  $userId */
/** @var mysqli $conn   */


if ($method == "GET") {
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

} else if ($method == "POST") {
/*
request
{
    naam:           string!
    huishouden_id:  uuid!
    met_foto:       boolean!
}
response
{
    foto_url:       url
    id:             uuid!
}
*/
$roomName     = $data["naam"] ?? null;
$withImage    = $data["met_foto"] ?? false;
$huishoudenId = $data["huishouden_id"] ?? null;

$roomId = generate_uuid_v4();

insert_query($conn, "INSERT INTO kamers(id, naam, huishouden_id) VALUES (?, ?, ?)", [$roomId, $roomName, $huishoudenId], "Room failed to be made");
// potentially set up photo upload url
$response = [
    'id' => $roomId
];
// make folder in file system for room pictures
mkdir("/home/lionsatm/images/" . $huishoudenId . "/rooms/" . $roomId, 0755);
if ($withImage) {
    $uploadToken = bin2hex(openssl_random_pseudo_bytes(8));
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    $insertPhotoToken = execute_cud_query($conn, 'INSERT INTO upload_tokens (token, verloopt_op, kamer_id) VALUES (?, ?, ?)', [$uploadToken, $expiresAt, $roomId]);
    if ($insertPhotoToken['successful'] && $insertPhotoToken['affected_rows']>0) {
        $response['foto_url'] = "https://lions-attic.nl/messyattic/api/image/" . $uploadToken;
    }
}
send_response($response, 201);
exit;
}
?>