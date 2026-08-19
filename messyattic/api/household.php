<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


include "api-helper.php";

$method = get_method();
$data = get_request_data();

include("authenticate.php");


if ($method == "GET") {
/* 
GET
request
{
    id:             uuid!
}
response
{
    
}
*/
$household_id = $data["id"];

} else if ($method == "POST") {
/* 
POST
request
{
    name:           string!
    description:    string!
    with_photo:     boolean!
}
response
{
    photo_url:      url
    id:             uuid!
}
*/
$household_name        = $data["name"];
$household_description = $data["description"];
$with_photo            = array_key_exists("with_photo", $data) ? $data["with_photo"] : false;
send_response([
    "status"      => "success",
    "message"     => "authentication succeeded",
    "name"        => $household_name,
    "description" => $household_description,
], $code=200);
}
?>