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
    naam:            string!
    omschrijving:    string!
    foto:            url
    leden:           [id!]!
    kamers:          [id!]!
    voorwerpen:      [id!]!
    labels:          [id!]!
}
*/
$household_id = $data["id"];
confirm_access_to_household($conn, $userId, $household_id);

$household_result = select_query_expect_result($conn, "SELECT naam, omschrijving, foto FROM `huishoudens` WHERE id = ?", [$household_id], 
                                                "No household with matching id found", 400);
$response = [
    'naam'         => $household_result['rows'][0]['naam'],
    'omschrijving' => $household_result['rows'][0]['omschrijving'],
    'foto'         => $household_result['rows'][0]['foto'],
    'leden'        => [],
    'kamers'       => [],
    'voorwerpen'   => [],
    'labels'       => [],
];
// check members
$members_result = select_query($conn, "SELECT gebruiker_id FROM `huishoud_leden_rel` WHERE huishouden_id = ?", [$household_id]);
foreach ($members_result["rows"] as $row) {
    array_push($response["leden"], $row["gebruiker_id"]);
}
// list rooms
$rooms_result = select_query($conn, "SELECT id FROM `kamers` WHERE huishouden_id = ?", [$household_id]);
foreach ($rooms_result["rows"] as $row) {
    array_push($response["kamers"], $row["id"]);
}
// list items/boxes
$items_result = select_query($conn, "SELECT id FROM `voorwerpen` WHERE huishouden_id = ?", [$household_id]);
foreach ($items_result["rows"] as $row) {
    array_push($response["voorwerpen"], $row["id"]);
}
// list tags
$tags_result = select_query($conn, "SELECT id FROM `labels` WHERE huishouden_id = ?", [$household_id]);
foreach ($tags_result["rows"] as $row) {
    array_push($response["labels"], $row["id"]);
}

send_response($response, $code=200);
$conn->close();
exit;

} else if ($method == "POST") {
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
}
?>