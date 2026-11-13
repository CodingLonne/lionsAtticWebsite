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
?>