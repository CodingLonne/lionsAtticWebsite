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
GET
request
{
    id:             uuid!
}
response
{
    naam:           string!
    omschrijving:   string!
    image:          url
    huishouden_id:     uuid!
    kamer_id:       uuid
    loc_x:          int
    loc_y:          int
    doos_id:        uuid
    labels:         [uuid!]
    eigenaren:      [uuid!]
    eigendomstype:  string!
}
*/
$item_id = $data["id"] ?? null;
if (empty($item_id)) {
    send_response(['status' => 'error', 'message' => 'id is required'], 400);
    exit;
}
$item_result = select_query_expect_result($conn, 
    "SELECT naam, omschrijving, foto, huishouden_id, kamer_id, loc_x, loc_y, doos_id, eigendomstype FROM voorwerpen WHERE id = ?", 
    [$item_id], 
    emptyResultMessage: "Item not found", emptyResultCode: 400);
$row = $item_result['rows'][0];
confirm_access_to_household($conn, $userId, $row['huishouden_id']);

$response = [
    'naam'          => $row['naam'],
    'omschrijving'  => $row['omschrijving'],
    'image'         => $row['foto'],
    'huishouden_id' => $row['huishouden_id'],
    'kamer_id'      => $row['kamer_id'],
    'loc_x'         => $row['loc_x'],
    'loc_y'         => $row['loc_y'],
    'doos_id'       => $row['doos_id'],
    'labels'        => [],
    'eigenaren'     => [],
    'eigendomstype' => $row['eigendomstype'],
];

$labels_result = select_query($conn, "SELECT label_id FROM gelabelde_voorwerpen_rel WHERE voorwerp_id = ?", [$item_id]);
foreach ($labels_result["rows"] as $l) {
    $response['labels'][] = $l['label_id'];
}
$owners_result = select_query($conn, "SELECT gebruiker_id FROM voorwerp_eigendom_rel WHERE voorwerp_id = ?", [$item_id]);
foreach ($owners_result["rows"] as $o) {
    $response['eigenaren'][] = $o['gebruiker_id'];
}

send_response($response, 200);
$conn->close();
exit;
