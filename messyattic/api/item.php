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
    omschrijving:   string!
    image:          url
    huishouden:     uuid!
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
    'huishouden'    => $row['huishouden_id'],
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
exit;

} else if ($method === 'POST') {

/*
POST
request
{
    naam:           string!
    omschrijving:   string!
    huishouden:     uuid!
    kamer_id:       uuid
    loc_x:          int
    loc_y:          int
    doos_id:        uuid
    met_foto:       boolean
    labels:         [uuid!]
    eigenaren:      [uuid!]
    eigendomstype:  string
}
response
{
    item_id:        uuid!
    foto_url:       url
}
*/
// get data
$item_name = $data["name"] ?? null;
$item_description = $data["description"] ?? '';
$item_householdId = $data["household_id"] ?? null;
$item_roomId = $data["room_id"] ?? null;
$item_locX = $data["loc_x"] ?? null;
$item_locY = $data["loc_y"] ?? null;
$item_boxId = $data["box_id"] ?? null;
$item_withPhoto = $data["with_image"] ?? false;
$item_labels = $data["labels"] ?? [];
$item_owners = $data["owners"] ?? [];
$item_ownerType = $data["ownerType"] ?? "iedereen"; //owner type

// mandatory checks
if (empty($item_name)) {
    send_response(['status' => 'error', 'message' => 'name is required'], 400);
    exit;
}
if (empty($item_householdId)) {
    send_response(['status' => 'error', 'message' => 'household_id is required'], 400);
    exit;
}

// verify household exists
select_query_expect_result($conn, "SELECT id FROM huishoudens WHERE id = ?", [$item_householdId], emptyResultMessage: "Household not found", emptyResultCode: 400);
confirm_access_to_household($conn, $userId, $item_householdId);

// optional room must belong to household
if ($item_roomId !== null) {
    select_query_expect_result($conn, 
        "SELECT id FROM kamers WHERE id = ? AND huishouden_id = ?", 
        [$item_roomId, $item_householdId], 
        emptyResultMessage: "Room '{$item_roomId}' not found in household", emptyResultCode: 400
    );
}

// verify each label belongs to household
foreach ($item_labels as $labelId) {
    select_query_expect_result($conn, 
        "SELECT id FROM labels WHERE id = ? AND huishouden_id = ?",
        [$labelId, $item_householdId], 
        emptyResultMessage: "Label '{$labelId}' not found in household", emptyResultCode: 400
    );
}

// verify coordinates are inside the room
if ($item_roomId === null || $item_locX === null || $item_locY === null) {
    $item_locX = null; $item_locY = null;
} else {
    $rooms_result = select_query_expect_result($conn,
        "SELECT breedte, hoogte FROM kamers WHERE id = ?",
        [$item_roomId],
        emptyResultMessage: "Room '{$item_roomId}' not found", emptyResultCode: 400
    );
    $width = $rooms_result['rows'][0]['breedte'];
    $height = $rooms_result['rows'][0]['hoogte'];
    if ($item_locX < 0 || $item_locX > $width || $item_locY < 0 || $item_locY > $height) {
        send_response([
            'status' => 'error', 
            'message' => 'Location out of bounds'
        ], $code = 400);
    }
}

// verify each owner exists 
foreach ($item_owners as $ownerId) {
    select_query_expect_result($conn, 
        "SELECT id FROM gebruikers WHERE id = ?", 
        [$ownerId], 
        emptyResultMessage: "Owner '{$ownerId}' not found", emptyResultCode: 400
    );
}

// make id
$itemId = generate_uuid_v4();

insert_query($conn,
    "INSERT INTO voorwerpen(id, naam, omschrijving, huishouden_id, kamer_id, doos_id, loc_x, loc_y, eigendomstype) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [$itemId, $item_name, $item_description, $item_householdId, $item_roomId, $item_boxId, $item_locX, $item_locY, $item_ownerType],
    errMsg: "Item failed to be made"
);

// attach labels
foreach ($item_labels as $labelId) {
    insert_query($conn, "INSERT INTO gelabelde_voorwerpen_rel(label_id, voorwerp_id) VALUES (?, ?)", [$labelId, $itemId], errMsg: "Failed to attach label");
}

// attach owners
if ($item_ownerType === 'iedereen' && empty($item_owners)) {
    $members = select_query($conn, "SELECT gebruiker_id FROM huishoud_leden_rel WHERE huishouden_id = ?", [$item_householdId]);
    foreach ($members['rows'] as $m) {
        $item_owners[] = $m['gebruiker_id'];
    }
}
foreach ($item_owners as $ownerId) {
    insert_query($conn, "INSERT INTO voorwerp_eigendom_rel(gebruiker_id, voorwerp_id) VALUES (?, ?)", [$ownerId, $itemId], errMsg: "Failed to attach owner");
}

//setup response
$response = [
    'id' => $itemId
];

// make folder
mkdir("/home/lionsatm/images/" . $item_householdId . "/items/" . $itemId, 0755, true);

// setup upload link
if ($item_withPhoto) {
    $uploadToken = bin2hex(openssl_random_pseudo_bytes(8));
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
    $insertPhotoToken = execute_cud_query($conn, 'INSERT INTO upload_tokens (token, verloopt_op, voorwerp_id) VALUES (?, ?, ?)', [$uploadToken, $expiresAt, $itemId]);
    if ($insertPhotoToken['successful'] && $insertPhotoToken['affected_rows']>0) {
        $response['image_url'] = "https://lions-attic.nl/messyattic/api/image/" . $uploadToken;
    }
}

send_response($response, 201);
exit;

}
?>
