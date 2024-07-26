<?php
    require_once __DIR__ . '/../../includes/connect_endpoint_crontabs.php';

    $query = "SELECT id, name, last_date FROM subscriptions WHERE last_date IS NOT NULL AND last_date < date('now')";
    $stmt = $db->prepare($query);
    $subscriptionsToUpdate = $stmt->execute();
    $found = false;

    while ($row = $subscriptionsToUpdate->fetchArray(SQLITE3_ASSOC)) {
        $subID = $row['id'];
        echo "Subscription has ended: " . $row['name'] . "<br />";
        $found = true;

        $updateQuery = "UPDATE subscriptions SET inactive = 1 WHERE id = :subID";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':subID', $subID, SQLITE3_TEXT);
        $updateResult = $updateStmt->execute();

        if (!$updateResult) {
            echo "Error updating subscription: ".$row['name']." <br />";
        }

    }

    if (!$found) {
        echo "Skipping updating subscription: Nothing found <br />";
    }

    $query = "SELECT archive_afterdays FROM settings WHERE user_id = 1 AND archive_afterdays>0";
    $stmt = $db->prepare($query);
    $archiveAfterdays = $stmt->execute();
    $found = false;

    while ($row = $archiveAfterdays->fetchArray(SQLITE3_ASSOC)) {
        echo "Looking for archiving subsciptions for longer than: " . $row['archive_afterdays'] . " days<br />";
        $found = true;

        $stmtsub = $db->prepare("SELECT * FROM subscriptions WHERE inactive = 1 AND date('now') >= DATE(last_date, '+".$row['archive_afterdays']." days')");
        $archiveAfterdayssubs = $stmtsub->execute();

        while ($rowsub = $archiveAfterdayssubs->fetchArray(SQLITE3_ASSOC)) {
            $cols = array();
            $vals = array();
            foreach($rowsub as $k=>$v) {
                if ($k != "id") {
                    $cols[] = $k;
                    $vals[] = $v;
                }
            }
            if ($cols && $vals) {
                $sql = "INSERT INTO subscriptions_archive('".implode("','", $cols)."') VALUES('".implode("','", $vals)."')";
                $stmtsub = $db->prepare($sql);
                $updateResult = $stmtsub->execute();
                if (!$updateResult) {
                    echo "Error insert subscription archive: ".$rowsub['name']." <br />";
                } else {
                    $sql = "DELETE FROM subscriptions WHERE id = :id";
                    $stmtsub = $db->prepare($sql);
                    $stmtsub->bindParam(':id', $rowsub['id'], SQLITE3_INTEGER);
                    $updateResult = $stmtsub->execute();
                    if (!$updateResult) {
                        echo "Error delete subscription: ".$rowsub['name']." <br />";
                    }
                }
            }
        }
    }

    if (!$found) {
        echo "Skipping archiving subscriptions: Nothing found <br />";
    }

?>