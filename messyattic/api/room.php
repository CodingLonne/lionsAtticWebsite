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
    name:           string!
    huishouden_id:  uuid!
    image:          url!
    dim_x:          int!
    dim_y:          int!
    items:          [uuid!]!
}
*/
$room_id = $data["id"];

} else if ($method == "POST") {
/*
request
{
    name:           string!
    huishouden_id:  uuid!
    with_image:     boolean!
}
response
{
    image_url:      url
    id:             uuid!
}
*/
$roomName     = $data["name"];
$withImage    = $data["with_image"];
$huishoudenId = $data["huishouden_id"];

$roomId = generate_uuid_v4();

insert_query($conn, "INSERT INTO kamers(id, naam, huishouden_id) VALUES (?, ?, ?)", [$roomId, $roomName, $huishoudenId], "Room failed to be made");
// potentially set up photo upload url
$response = [
    'id' => $huishoudenId
];
if ($withImage) {
    $uploadToken = bin2hex(openssl_random_pseudo_bytes(8));
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    $insertPhotoToken = execute_cud_query($conn, 'INSERT INTO upload_tokens (token, verloopt_op, kamer_id) VALUES (?, ?, ?)', [$uploadToken, $expiresAt, $roomId]);
    if ($insertPhotoToken['successful'] && $insertPhotoToken['affected_rows']>0) {
        $response['image_url'] = "https://lions-attic.nl/messyattic/api/image/" . $uploadToken;
    }
}
send_response($response, 201);
exit;
}
?>