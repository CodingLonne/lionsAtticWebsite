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
?>