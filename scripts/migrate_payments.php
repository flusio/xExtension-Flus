<?php

if (php_sapi_name() !== 'cli') {
    die('FreshRSS error: This PHP script may only be invoked from command line!');
}

const FLUS_EXTENSION_PATH = __DIR__ . '/..';
require(FLUS_EXTENSION_PATH . '/../../constants.php');
require(FLUS_EXTENSION_PATH . '/autoload.php');
require(LIB_PATH . '/lib_rss.php');

$database = new PDO('sqlite:' . DATA_PATH . '/payments.sqlite');

$usernames = listUsers();
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        echo "[warning] {$username} configuration does not exist!\n";
        continue;
    }

    $billing = $user_conf->billing;
    if (!$billing) {
        echo "[warning] {$username} configuration doesn’t contain billing info!\n";
        continue;
    }

    if (!isset($billing['address'])) {
        echo "[notice] {$username} has no addresses.\n";
        continue;
    }

    if (!isset($billing['payments'])) {
        echo "[notice] {$username} has no payments.\n";
        continue;
    }

    $payments = $billing['payments'];
    $address = $billing['address'];

    $new_payments = [];

    foreach ($payments as $session_id => $initial_payment) {
        if ($initial_payment['status'] !== 'paid') {
            echo "[notice] Ignore payment {$session_id} (status {$initial_payment['status']})\n";
            continue;
        }

        $payment_for_db = [
            'id' => bin2hex(random_bytes(16)),
            'created_at' => $initial_payment['date'],
            'type' => 'subscription',
            'email' => $user_conf->mail_login,
            'amount' => $initial_payment['amount'] * 100,
            'address_first_name' => $address['first_name'],
            'address_last_name' => $address['last_name'],
            'address_address1' => $address['address'],
            'address_postcode' => $address['postcode'],
            'address_city' => $address['city'],
            'session_id' => $session_id,
            'username' => $username,
            'frequency' => $initial_payment['frequency'],
            'invoice_number' => $initial_payment['invoice_number'],
            'completed_at' => $initial_payment['date'],
        ];

        $properties = array_keys($payment_for_db);
        $values_as_question_marks = array_fill(0, count($payment_for_db), '?');
        $values_placeholder = implode(", ", $values_as_question_marks);
        $columns_placeholder = implode(", ", $properties);

        $sql = "INSERT INTO payments ({$columns_placeholder}) VALUES ({$values_placeholder})";
        $statement = $database->prepare($sql);
        $result = $statement->execute(array_values($payment_for_db));

        if (!$result) {
            $error_info = $statement->errorInfo();
            echo "[error] Error in SQL statement: {$error_info[2]} ({$error_info[0]}).\n";
        }

        $new_payments[$payment_for_db['id']] = [
            'id' => $payment_for_db['id'],
            'created_at' => $payment_for_db['created_at'],
            'completed_at' => $payment_for_db['completed_at'],
            'frequency' => $payment_for_db['frequency'],
            'amount' => $payment_for_db['amount'],
        ];
    }

    $billing['payments'] = $new_payments;
    $user_conf->billing = $billing;
    $user_conf->save();

    echo "Payments for user {$username}: OK\n";
    print_r($new_payments);
}
