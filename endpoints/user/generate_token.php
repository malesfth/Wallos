<?php
    require_once '../../includes/connect_endpoint.php';
    require_once '../../includes/inputvalidation.php';

    if (isset($_SESSION['username'])) {
        $token = bin2hex(random_bytes(78));
        $sql = "UPDATE user SET token = :token WHERE id = :userId";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':token', $token, SQLITE3_TEXT);
        $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();

    $db->close();
    unset($db);

        if ($result) {
            $response = [
                "success" => true,
                "message" => translate('user_details_saved', $i18n),
                "data"  => $token
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "errorMessage" => translate('error_updating_user_data', $i18n)
            ];
            echo json_encode($response);
        }

        exit();
    } else {
        $response = [
            "success" => false,
            "errorMessage" => translate('please_login', $i18n)
        ];
        echo json_encode($response);
        exit();
    }

?>
