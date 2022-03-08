#!/bin/env php
<?php

/**
 * @author Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be called from command line.');
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

$flus_api_host = FreshRSS_Context::$system_conf->billing['flus_api_host'];
$flus_private_key = FreshRSS_Context::$system_conf->billing['flus_private_key'];
$subscriptions_service = new \Flus\services\Subscriptions($flus_api_host, $flus_private_key);

// First, make sure all users have a subscription account.
$usernames = listUsers();
$account_ids_to_user_confs = [];
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        continue;
    }

    $subscription = $user_conf->subscription;
    if (!is_array($subscription)) {
        $expired_at = new \DateTime();
        $expired_at->modify('+1 month');
        $subscription = [
            'account_id' => null,
            'expired_at' => $expired_at->format('Y-m-d H:i:sP'),
        ];
    }

    $no_account = $user_conf->subscription['account_id'] === null;
    $email_should_be_validated = FreshRSS_Context::$system_conf->force_email_validation;
    $email_validated = !$email_should_be_validated || $user_conf->email_validation_token !== '';
    if ($no_account && $email_validated) {
        $account = $subscriptions_service->account($user_conf->mail_login);
        if (!$account) {
            continue;
        }

        $subscription = [
            'account_id' => $account['id'],
            'expired_at' => $account['expired_at']->format('Y-m-d H:i:sP'),
        ];
        $user_conf->subscription = $subscription;
        $user_conf->save();

        $account_ids_to_user_confs[$account['id']] = $user_conf;
    } elseif (!$no_account) {
        $account_ids_to_user_confs[$subscription['account_id']] = $user_conf;
    }
}

// Then, synchronize expiration dates.
$account_ids = array_keys($account_ids_to_user_confs);
$result = $subscriptions_service->sync($account_ids);
if ($result === null) {
    return;
}

foreach ($result as $account_id => $expired_at) {
    if (!isset($account_ids_to_user_confs[$account_id])) {
        \Minz\Log::error("Subscription account {$account_id} does not exist.");
        continue;
    }

    $user_conf = $account_ids_to_user_confs[$account_id];
    $subscription = $user_conf->subscription;
    if ($subscription['expired_at'] !== $expired_at) {
        $subscription['expired_at'] = $expired_at;
        $user_conf->subscription = $subscription;
        $user_conf->save();
    }
}
