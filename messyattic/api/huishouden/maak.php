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
    naam:         string!
    omschrijving: string!
    met_foto:     boolean
}
response
{
    foto_url:     url
    id:           uuid!
}
*/
// get data
$household_name        = $data["naam"] ?? null;
$household_description = $data["omschrijving"] ?? '';
$with_image            = $data["met_foto"] ?? false;

// check that needed data is supplied
if (empty($household_name)) {
    send_response(['status' => 'error', 'message' => 'naam nodig'], 400);
    $conn->close();
    exit;
}

// start making household
$huishoudenId = generate_uuid_v4();
// give household own image folder
mkdir("/home/lionsatm/images/" . $huishoudenId, 0755);
mkdir("/home/lionsatm/images/" . $huishoudenId . "/rooms", 0755);
mkdir("/home/lionsatm/images/" . $huishoudenId . "/items", 0755);

// make household
$insertHuishoudenResult = insert_query($conn, 
    'INSERT INTO huishoudens (id, naam, omschrijving) VALUES (?, ?, ?)', 
    [$huishoudenId, $household_name, $household_description],
    errMsg: 'Huishouden maken gefaald');
// set owner household to maker
$insertEigenaarResult = insert_query($conn, 
    'INSERT INTO huishoud_leden_rel (gebruiker_id, huishouden_id) VALUES (?, ?)', 
    [$userId, $huishoudenId],
    errMsg: 'Kon gebruiker niet toevoegen aan huishouden als (mede)eigenaar');

// potentially set up photo upload url
$response = [
    'id' => $huishoudenId
];
if ($with_image) {
    $uploadToken = bin2hex(openssl_random_pseudo_bytes(8));
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    $insertPhotoToken = execute_cud_query($conn, 'INSERT INTO upload_tokens (token, verloopt_op, huishouden_id) VALUES (?, ?, ?)', [$uploadToken, $expiresAt, $huishoudenId]);
    if ($insertPhotoToken['successful'] && $insertPhotoToken['affected_rows']>0) {
        $response['foto_url'] = "https://lions-attic.nl/messyattic/api/image/" . $uploadToken;
    }
}
send_response($response, 201);
$conn->close();
exit;
?>