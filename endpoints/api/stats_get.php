<?php
    require_once '../../includes/connect_endpoint.php';
    require_once '../../includes/currency_formatter.php';
    require_once '../../includes/getdbkeys.php';

    function getPricePerMonth($cycle, $frequency, $price) {
        switch ($cycle) {
        case 1:
            $numberOfPaymentsPerMonth = (30 / $frequency); 
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 2:
            $numberOfPaymentsPerMonth = (4.35 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 3:
            $numberOfPaymentsPerMonth = (1 / $frequency);
            return $price * $numberOfPaymentsPerMonth;
            break;
        case 4:
          $numberOfMonths = (12 * $frequency);
          return $price / $numberOfMonths;
          break;
        }
      }
    
    
      function getPriceConverted($price, $currency, $database) {
          $query = "SELECT rate FROM currencies WHERE id = :currency AND user_id = :userId";
          $stmt = $database->prepare($query);
          $stmt->bindParam(':currency', $currency, SQLITE3_INTEGER);
          $stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
          $result = $stmt->execute();
          
          $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
          if ($exchangeRate === false) {
              return $price;
          } else {
              $fromRate = $exchangeRate['rate'];
              return $price / $fromRate;
          }
      }

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

    if ((isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) || $token!=null) {
        //Get household members
        $members = array();
        $query = "SELECT * FROM household WHERE user_id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $memberId = $row['id'];
            $members[$memberId] = $row;
            $memberCost[$memberId]['cost'] = 0;
            $memberCost[$memberId]['name'] = $row['name'];
        }
        
        // Get categories
        $categories = array();
        $query = "SELECT * FROM categories WHERE user_id = :userId ORDER BY 'order' ASC";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $categoryId = $row['id'];
            $categories[$categoryId] = $row;
            $categoryCost[$categoryId]['cost'] = 0;
            $categoryCost[$categoryId]['name'] = $row['name'];
        }
        
        // Get payment methods
        $paymentMethodCount = array();
        $query = "SELECT * FROM payment_methods WHERE user_id = :userId AND enabled = 1";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $paymentMethodId = $row['id'];
            $paymentMethodCount[$paymentMethodId] = $row;
            $paymentMethodCount[$paymentMethodId]['count'] = 0;
            $paymentMethodCount[$paymentMethodId]['name'] = $row['name'];
        }
        
        // Get code of main currency to display on statistics
        $query = "SELECT c.code
                  FROM currencies c
                  INNER JOIN user u ON c.id = u.main_currency
                  WHERE u.id = :userId";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $code = $row['code'];
        
        $activeSubscriptions = 0;
        $inactiveSubscriptions = 0;
        // Calculate total monthly price
        $mostExpensiveSubscription = array();
        $mostExpensiveSubscription['price'] = 0;
        $amountDueThisMonth = 0;
        $totalCostPerMonth = 0;
        $totalCostPerMonthPure = 0;
        $totalSavingsPerMonth = 0;
        $pauschal = 0;
        $sparplan = 0;
        
        $statsSubtitleParts = [];
        $query = "SELECT name, price, logo, frequency, cycle, currency_id, next_payment, payer_user_id, category_id, payment_method_id, inactive FROM subscriptions";
        $conditions = [];
        $params = [];
        
        if (isset($_GET['member'])) {
            $conditions[] = "payer_user_id = :member";
            $params[':member'] = $_GET['member'];
            $statsSubtitleParts[] = $members[$_GET['member']]['name'];
        }
        
        if (isset($_GET['category'])) {
            $conditions[] = "category_id = :category";
            $params[':category'] = $_GET['category'];
            $statsSubtitleParts[] = $categories[$_GET['category']]['name'];
        }
        
        if (isset($_GET['payment'])) {
            $conditions[] = "payment_method_id = :payment";
            $params[':payment'] = $_GET['payment'];
            $statsSubtitleParts[] = $paymentMethodCount[$_GET['payment']]['name'];
        }
        
        $conditions[] = "user_id = :userId";
        $params[':userId'] = $userId;
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $db->prepare($query);
        $statsSubtitle = !empty($statsSubtitleParts) ? '(' . implode(', ', $statsSubtitleParts) . ')' : "";
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, SQLITE3_INTEGER);
        }
        
        $result = $stmt->execute();
        if ($result) {
          while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $subscriptions[] = $row;
          }
          if (isset($subscriptions)) {
            foreach ($subscriptions as $subscription) {
              $name = $subscription['name'];
              $price = $subscription['price'];
              $logo = $subscription['logo'];
              $frequency = $subscription['frequency'];
              $cycle = $subscription['cycle'];
              $currency = $subscription['currency_id'];
              $next_payment = $subscription['next_payment'];
              $payerId = $subscription['payer_user_id'];
              $categoryId = $subscription['category_id'];
              $paymentMethodId = $subscription['payment_method_id'];
              $inactive = $subscription['inactive'];
              $originalSubscriptionPrice = getPriceConverted($price, $currency, $db);
              $pricepure = 0;
              if ($cycle==3) {
                $pricepure = $price * (1 / $frequency);
              }
              $price = getPricePerMonth($cycle, $frequency, $originalSubscriptionPrice);
        
              if ($inactive == 0) {
                $activeSubscriptions++;
                $totalCostPerMonth += $price;
                $totalCostPerMonthPure += $pricepure;
                $memberCost[$payerId]['cost'] += $price;
                $categoryCost[$categoryId]['cost'] += $price;
                $paymentMethodCount[$paymentMethodId]['count'] += 1;
                if ($price > $mostExpensiveSubscription['price']) {
                  $mostExpensiveSubscription['price'] = $price;
                  $mostExpensiveSubscription['name'] = $name;
                  $mostExpensiveSubscription['logo'] = $logo;
                }
        
                if ($categoryId==5) {
                  $pauschal = $pauschal + $price;
                }
                if ($categoryId==6) {
                  $sparplan = $sparplan + $price;
                } 
        
                // Calculate ammount due this month
                $nextPaymentDate = DateTime::createFromFormat('Y-m-d', trim($next_payment));
                $tomorrow = new DateTime('tomorrow');
                $endOfMonth = new DateTime('last day of this month');
            
                if ($nextPaymentDate >= $tomorrow && $nextPaymentDate <= $endOfMonth) {
                    $timesToPay = 1;
                    $daysInMonth = $endOfMonth->diff($tomorrow)->days + 1;
                    $daysRemaining = $endOfMonth->diff($nextPaymentDate)->days + 1;
                    if ($cycle == 1) {
                      $timesToPay = $daysRemaining / $frequency;
                    }
                    if ($cycle == 2) {
                      $weeksInMonth = ceil($daysInMonth / 7);
                      $weeksRemaining = ceil($daysRemaining / 7);
                      $timesToPay = $weeksRemaining / $frequency;
                    }
                    $amountDueThisMonth += $originalSubscriptionPrice * $timesToPay;
                }
              } else {
                $inactiveSubscriptions++;
                $totalSavingsPerMonth += $price;
              }
        
            }
          
            // Calculate yearly price
            $totalCostPerYear = $totalCostPerMonth * 12;
          
            // Calculate average subscription monthly cost
            if ($activeSubscriptions > 0) {
              $averageSubscriptionCost = $totalCostPerMonth / $activeSubscriptions;
            } else {
              $totalCostPerYear = 0;
              $averageSubscriptionCost = 0;
            }
          } else {
            $totalCostPerYear = 0;
            $averageSubscriptionCost = 0;
          }
        }
        
        $budgetLeft = 0;
        $overBudgetAmount = 0;
        $budgetUsed = 0;
        if (isset($userData['budget']) && $userData['budget'] > 0) {
          $budget = $userData['budget'];
          $budgetLeft = $budget - $totalCostPerMonth;
          $budgetLeft = $budgetLeft < 0 ? 0 : $budgetLeft;
          $budgetUsed = ($totalCostPerMonth / $budget) * 100;
          $budgetUsed = $budgetUsed > 100 ? 100 : $budgetUsed;
          if ($totalCostPerMonth > $budget) {
            $overBudgetAmount = $totalCostPerMonth - $budget;
          }
        }

        $categoryDataPoints = array();
        foreach ($categoryCost as $category) {
            if ($category['cost'] != 0) {
              $categoryDataPoints[] = [
                  "label" => $category['name'],
                  "sum"     => $category["cost"],
              ];
            }
          }
        
        $print = array(
            "subscriptions_active" => $activeSubscriptions,
            "subscriptions_inactive" => $inactiveSubscriptions,
            "total_monthpure" => CurrencyFormatter::format($totalCostPerMonthPure, $code),
            "total_month" => CurrencyFormatter::format($totalCostPerMonth, $code),
            "total_year" => CurrencyFormatter::format($totalCostPerYear, $code),
            "avg_subscriptions_cost" => CurrencyFormatter::format($averageSubscriptionCost, $code),
            "most_expensive" => CurrencyFormatter::format($mostExpensiveSubscription['price'], $code),
            "due_month" => CurrencyFormatter::format($amountDueThisMonth, $code),
            "sparplan" => CurrencyFormatter::format($sparplan, $code),
            "pauschal" => CurrencyFormatter::format($pauschal, $code),
            "budget_used" => number_format($budgetUsed, 2),
            "budget_left" => CurrencyFormatter::format($budgetLeft, $code),
            "budget_over_amount" => CurrencyFormatter::format($overBudgetAmount, $code),
            "total_savings_month" => CurrencyFormatter::format($totalSavingsPerMonth, $code),
            "total_savings_year" => CurrencyFormatter::format($totalSavingsPerMonth * 12, $code),
            "total_categories" => $categoryDataPoints
        );

        if (isset($print)) {
          $response = array(
              "success" => true,
              "data" => $print
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