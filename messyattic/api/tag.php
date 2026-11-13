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
request
{
    id:             uuid!
}
response
{
    huishouden_id:  uuid!
    naam:           string!
}
*/
$tag_id = $data["id"] ?? null;
if (empty($tag_id)) {
    send_response(['status' => 'error', 'message' => 'id is required'], 400);
    exit;
}

$tag_result = select_query_expect_result($conn, "SELECT huishouden_id, naam FROM labels WHERE id = ?", [$tag_id], emptyResultMessage: "Tag not found", emptyResultCode: 400);
$row = $tag_result['rows'][0];
confirm_access_to_household($conn, $userId, $row['huishouden_id']);

send_response([
    'huishouden_id' => $row['huishouden_id'],
    'naam'          => $row['naam'],
], 200);
exit;

} else if ($method === 'POST') {
/*
request
{
    huishouden_id:  uuid!
    naam:           string!
}
response:
    id:             uuid!
*/
$household_id = $data["huishouden_id"] ?? null;
$tag_name = $data["naam"] ?? null;

if (empty($household_id)) {
    send_response(['status' => 'error', 'message' => 'huishouden_id is nodig'], 400);
    exit;
}
if (empty($tag_name)) {
    send_response(['status' => 'error', 'message' => 'naam is nodig'], 400);
    exit;
}

confirm_access_to_household($conn, $userId, $household_id);

$tag_id = generate_uuid_v4();
insert_query($conn, 
    "INSERT INTO labels (id, huishouden_id, naam) VALUES (?, ?, ?)", 
    [$tag_id, $household_id, $tag_name], 
    errMsg: "Label opslaan in database gefaald");

send_response([
    'id' => $tag_id
], 201);
exit;

}