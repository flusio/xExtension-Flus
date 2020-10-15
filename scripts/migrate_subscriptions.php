<?php

if (php_sapi_name() !== 'cli') {
    die('FreshRSS error: This PHP script may only be invoked from command line!');
}

const FLUS_EXTENSION_PATH = __DIR__ . '/..';
require(FLUS_EXTENSION_PATH . '/../../constants.php');
require(FLUS_EXTENSION_PATH . '/autoload.php');
require(LIB_PATH . '/lib_rss.php');

Minz_Configuration::register(
    'system',
    DATA_PATH . '/config.php',
    FRESHRSS_PATH . '/config.default.php'
);
FreshRSS_Context::$system_conf = Minz_Configuration::get('system');

$flus_private_key = FreshRSS_Context::$system_conf->billing['flus_private_key'];
$subscriptions_service = new \Flus\services\Subscriptions($flus_private_key);

$usernames = listUsers();
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        Minz_Log::warning("{$username} configuration does not exist!");
        continue;
    }

    if ($user_conf->email_validation_token !== '') {
        Minz_Log::warning("{$username} email is not validated!");
        continue;
    }

    $billing = $user_conf->billing;
    if (!$billing) {
        $billing = [
            'subscription_end_at' => strtotime("+1 month"),
        ];
    }

    if ($billing['subscription_end_at'] === null) {
        // free accounts are represented differently in the new system
        $expired_at = new \DateTime('1970-01-01');
    } else {
        $expired_at = new \DateTime();
        $expired_at->setTimestamp($billing['subscription_end_at']);
    }

    $account = $subscriptions_service->getAccount(
        $user_conf->mail_login,
        $expired_at,
        isset($billing['reminder']) && $billing['reminder'],
    );

    if ($account) {
        $user_conf->subscription = [
            'account_id' => $account['id'],
            'expired_at' => $account['expired_at'],
        ];
        $user_conf->save();
        echo "{$username} migrated ({$account['id']})\n";
    }
}
