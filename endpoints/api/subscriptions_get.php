<?php
    require_once '../../includes/connect_endpoint.php';

    header('Content-Type: application/json; charset=utf-8');

    $token = null;
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
      $matches = array();
      preg_match('/Bearer (.*)/', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $matches);
      if(isset($matches[1])){
        $token = $matches[1];
        $sql = "SELECT id, token FROM user WHERE token = :token";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':token', $token, SQLITE3_TEXT);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $userId = $row['id'];
        }
      }
    }

    require_once '../../includes/currency_formatter.php';
    require_once '../../includes/getdbkeys.php';

    require_once '../../includes/getsettings.php';
    include_once '../../includes/list_subscriptions.php';

    if ((isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $token!=null) {
        $sort = "next_payment";
        $order = "ASC";
        $params = array();
        $sql = "SELECT * FROM subscriptions WHERE user_id = :userId";

        if (isset($_GET['category']) && $_GET['category'] != "") {
            $sql .= " AND category_id = :category";
            $params[':category'] = $_GET['category'];
        }

        if (isset($_GET['payment']) && $_GET['payment'] != "") {
            $sql .= " AND payment_method_id = :payment";
            $params[':payment'] = $_GET['payment'];
        }

        if (isset($_GET['member']) && $_GET['member'] != "") {
            $sql .= " AND payer_user_id = :member";
            $params[':member'] = $_GET['member'];
        }

        $sql .= " ORDER BY $sort $order, inactive ASC";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $result = $stmt->execute();
        if ($result) {
            $subscriptions = array();
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $subscriptions[] = $row;
            }
        }

        $sumCategory = array();
        $defaultLogo = "images/siteicons/blue/wallos.png";
        foreach ($subscriptions as $subscription) {
          if ($subscription['inactive'] == 1 && isset($settings['hideDisabledSubscriptions']) && $settings['hideDisabledSubscriptions'] === 'true') {
            continue;
          }
          $id = $subscription['id'];
          $print[$id]['id'] = $id;
          $print[$id]['logo'] = $subscription['logo'] != "" ? "images/uploads/logos/".$subscription['logo'] : $defaultLogo;
          $print[$id]['name'] = htmlspecialchars_decode($subscription['name'] ?? ""); 
          $cycle = $subscription['cycle'];
          $frequency = $subscription['frequency'];
          $print[$id]['billing_cycle'] = getBillingCycle($cycle, $frequency, $i18n);
          $paymentMethodId = $subscription['payment_method_id'];
          $print[$id]['currency_code'] = $currencies[$subscription['currency_id']]['code'];
          $currencyId = $subscription['currency_id'];
          $print[$id]['next_payment'] = $subscription['next_payment'];
          $print[$id]['last_date'] = ($subscription['last_date'] ? date('M d, Y', strtotime($subscription['last_date'])) : '');
          $paymentIconFolder = (strpos($payment_methods[$paymentMethodId]['icon'], 'images/uploads/icons/') !== false ? "" : "images/uploads/logos/");
          $print[$id]['payment_method_icon'] = $paymentIconFolder . $payment_methods[$paymentMethodId]['icon'];
          $print[$id]['payment_method_name'] = $payment_methods[$paymentMethodId]['name'];
          $print[$id]['payment_method_id'] = $paymentMethodId;
          $print[$id]['category_id'] = $subscription['category_id'];
          $print[$id]['payer_user_id'] = $subscription['payer_user_id'];
          $print[$id]['price'] = floatval($subscription['price']);
          $print[$id]['inactive'] = $subscription['inactive'];
          $print[$id]['url'] = htmlspecialchars_decode($subscription['url'] ?? "");
          $print[$id]['notes'] = htmlspecialchars_decode($subscription['notes'] ?? "");

          if (isset($settings['convertCurrency']) && $settings['convertCurrency'] === 'true' && $currencyId != $mainCurrencyId) {
            $print[$id]['price'] = getPriceConverted($print[$id]['price'], $currencyId, $db);
            $print[$id]['currency_code'] = $currencies[$mainCurrencyId]['code'];
          }
          if (isset($settings['showMonthlyPrice']) && $settings['showMonthlyPrice'] === 'true') {
            $print[$id]['price'] = getPricePerMonth($cycle, $frequency, $print[$id]['price']);
          }

          //if (isset($sumCategory[$print[$id]['category_id']])) { $sumCategory[$print[$id]['category_id']] = $sumCategory[$print[$id]['category_id']] + $print[$id]['price']; }
          $sumCategory[$print[$id]['category_id']] = (isset($sumCategory[$print[$id]['category_id']]) ? $sumCategory[$print[$id]['category_id']] : 0) + $print[$id]['price'];
        }

        foreach($categories as $category) {
          $categories[$category["id"]]["sum"] = (isset($sumCategory[$category["id"]]) ? $sumCategory[$category["id"]] : 0);
        }

        if (isset($print)) {
          $response = array(
              "success" => true,
              //"members" => $members,
              "categories" => $categories,
              "data" => $print,
          );
          die(json_encode($response, JSON_PRETTY_PRINT));
        }
    } else {
      $response = array(
        "success" => false,
        "errorMessage" => translate('please_login', $i18n)
      );
      die(json_encode($response, JSON_PRETTY_PRINT));
    }

    $db->close();
?>