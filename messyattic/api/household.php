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
    description:    string!
    image:          url
    members:        [id!]!
    rooms:          [id!]!
    items:          [id!]!
    tags:           [id!]!
}
*/
$household_id = $data["id"];
//TODO is household id from user
confirm_access_to_household($conn, $userId, $household_id);

$household_result = select_query_expect_result($conn, "SELECT naam, omschrijving, foto FROM `huishoudens` WHERE id = ?", [$household_id], 
                                                "No household with matching id found", 400);
$response = [
    'name' => $household_result['rows'][0]['naam'],
    'description' => $household_result['rows'][0]['omschrijving'],
    'image' => $household_result['rows'][0]['foto']
];
send_response($response, $code=200);
exit;

} else if ($method == "POST") {
/* 
POST
request
{
    name:           string!
    description:    string!
    with_image:     boolean!
}
response
{
    photo_url:      url
    id:             uuid!
}
*/
// get data
$household_name        = $data["name"];
$household_description = $data["description"];
$with_image            = array_key_exists("with_image", $data) ? $data["with_image"] : false;

$huishoudenId = generate_uuid_v4();
// give household own image folder
mkdir("/home/lionsatm/images/" . $huishoudenId, 0755);
mkdir("/home/lionsatm/images/" . $huishoudenId . "/rooms", 0755);
mkdir("/home/lionsatm/images/" . $huishoudenId . "/items", 0755);

// make household
$insertHuishoudenResult = execute_cud_query($conn, 'INSERT INTO huishoudens (id, naam, omschrijving) VALUES (?, ?, ?)', [$huishoudenId, $household_name, $household_description]);
if (!$insertHuishoudenResult['successful'] || $insertHuishoudenResult['affected_rows'] <= 0) {
    send_response([
        'status' => 'failed',
        'message' => 'Huishouden failed to be made.',
        'extra_info' => $insertHuishoudenResult
    ], 500);
    exit;
}
// set owner household to maker
$insertEigenaarResult = execute_cud_query($conn, 'INSERT INTO huishoud_leden_rel (gebruiker_id, huishouden_id) VALUES (?, ?)', [$userId, $huishoudenId]);
if (!$insertEigenaarResult['successful'] || $insertEigenaarResult['affected_rows'] <= 0) {
    send_response([
        'status' => 'failed',
        'message' => 'Could not add owner to huishouden',
        'extra_info' => $insertEigenaarResult
    ], 500);
    exit;
}
// potentially set up photo upload url
$response = [
    'status' => 'success',
    'message' => 'Huishouden created',
    'id' => $huishoudenId
];
if ($with_image) {
    $uploadToken = bin2hex(openssl_random_pseudo_bytes(8));
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    $insertPhotoToken = execute_cud_query($conn, 'INSERT INTO upload_tokens (token, verloopt_op, huishouden_id) VALUES (?, ?, ?)', [$uploadToken, $expiresAt, $huishoudenId]);
    if ($insertPhotoToken['successful'] && $insertPhotoToken['affected_rows']>0) {
        $response['upload_url'] = "https://lions-attic.nl/messyattic/api/image/" . $uploadToken;
    }
}
send_response($response, 201);
exit;
}
?>