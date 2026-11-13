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
?>