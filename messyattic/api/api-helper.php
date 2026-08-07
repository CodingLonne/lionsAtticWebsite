<?php
/*
POST    /messyattic/api/register    register account
POST    /messyattic/api/login       login account

GET     /messyattic/api/households  get huishoudens van account

GET     /messyattic/api/household   get huishouden informatie
POST    /messyattic/api/household   maak nieuw huishouden
PUT     /messyattic/api/household   verander informatie huishouden

GET     /messyattic/api/rooms       get kamers van huishouden

GET     /messyattic/api/room        get kamer informatie
POST    /messyattic/api/room        maak nieuwe kamer
PUT     /messyattic/api/room        edit kamer informatie
DELETE  /messyattic/api/room        delete kamer informatie


GET     /messyattic/api/items       get items van huishouden

GET     /messyattic/api/item        get information of item
POST    /messyattic/api/item        voeg item aan huishouden toe
PUT     /messyattic/api/item        edit information of item
DELETE  /messyattic/api/item        delete item

GET     /messyattic/api/tags        get tags van huishouden

GET     /messyattic/api/tag         get tag
POST    /messyattic/api/tag         make tag
DELETE  /messyattic/api/tag         delete tag


*/
function get_method () {
	return $_SERVER['REQUEST_METHOD'];
}

function get_request_data () {
	return array_merge(empty($_POST) ? array() : $_POST, (array) json_decode(file_get_contents('php://input'), true), $_GET);
}

function send_response ($response, $code = 200) {
	http_response_code($code);
    header('Content-Type: application/json');
	die(json_encode($response));
}

function database_connect () {
    $config = require '/home/lionsatm/config/db-config.php';
    $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['dbname']);
    // Check connection
    if ($conn->connect_error) {
        send_response([
            'status' => 'failed',
            'message' => 'connection to database failed',
        ], 500);
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

function execute_read_query(mysqli $conn, string $sql, array $params = []) {
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = $stmt->get_result();
        $rows = $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];

        $result = [
            'rows' => $rows,
            'successful' => true,
        ];

        $stmt->close();
        return $result;
    } catch (mysqli_sql_exception $e) {
        $result = [
            'successful' => false,
        ];
    }
}

function execute_cud_query(mysqli $conn, string $sql, array $params = []): array {
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();

        $result = [
            'affected_rows' => $stmt->affected_rows,
            'insert_id' => $stmt->insert_id,
            'successful' => true
        ];

        $stmt->close();
        return $result;
    } catch (mysqli_sql_exception $e) {
        $result = [
            'affected_rows' => 0,
            'successful' => false,
            'error' => $e->getMessage()
        ];
        return $result;
    }
}

?>