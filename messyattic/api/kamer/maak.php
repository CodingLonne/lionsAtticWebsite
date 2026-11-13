<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include("../api-helper.php");

$method = get_method();
$data = get_request_data();

include("../gebruiker/authenticate.php");
/** @var mixed  $userId */
/** @var mysqli $conn   */


validate_method("POST");
/*
POST
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
?>