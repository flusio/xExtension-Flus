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

// Initialize in French since email are only localized in French!
// It avoids to have the dates in English format.
Minz_Translate::init('fr');

Minz_View::addBasePathname(FLUS_EXTENSION_PATH);

$usernames = listUsers();
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        Minz_Log::warning("{$username} configuration does not exist!");
        continue;
    }

    $billing = $user_conf->billing;
    if (!$billing) {
        Minz_Log::warning("{$username} configuration doesn’t contain billing info!");
        continue;
    }

    if (!isset($billing['reminder']) || !$billing['reminder']) {
        // User didn't ask for a reminder
        continue;
    }

    if (!$billing['subscription_end_at']) {
        // Free plan, nothing to do
        continue;
    }

    $today = new DateTime();
    $subscription_end_at = new DateTime();
    $subscription_end_at->setTimestamp($billing['subscription_end_at']);
    $interval = $today->diff($subscription_end_at);
    $diff_days = $interval->days;

    $mailer = new \Flus\mailers\Billing();
    if ($interval->invert === 1 && $diff_days === 1) {
        // subscription ended yesterday
        $mailer->send_subscription_ended($username, $user_conf);
    } elseif ($interval->invert === 0 && ($diff_days === 2 || $diff_days === 7))  {
        // subscription end in 2 or 7 days
        $mailer->send_subscription_ending($username, $user_conf);
    }
}
