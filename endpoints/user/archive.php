<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die(json_encode([
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $postData = file_get_contents("php://input");
    $data = json_decode($postData, true);

    if (
        !isset($data["days"]) || $data["days"] == ""
    ) {
        $response = [
            "success" => false,
            "message" => translate('fill_mandatory_fields', $i18n)
        ];
        echo json_encode($response);
    } else {
        $days = $data['days'];

        $stmt = $db->prepare('UPDATE settings SET archive_afterdays = :days WHERE user_id = :userId');
        $stmt->bindParam(':days', $days, SQLITE3_INTEGER);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
    
        if ($stmt->execute()) {
            die(json_encode([
                "success" => true,
                "message" => translate("success", $i18n)
            ]));
        } else {
            die(json_encode([
                "success" => false,
                "message" => translate("error", $i18n)
            ]));
        }
    }
}


?>