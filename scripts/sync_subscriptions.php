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

FreshRSS_Context::initSystem();

$flus_api_host = FreshRSS_Context::systemConf()->billing['flus_api_host'];
$flus_private_key = FreshRSS_Context::systemConf()->billing['flus_private_key'];
$subscriptions_service = new \Flus\services\Subscriptions($flus_api_host, $flus_private_key);

$limits = FreshRSS_Context::systemConf()->limits;
$min_last_activity = time() - $limits['max_inactivity'];

// First, make sure all users have a subscription account.
$usernames = listUsers();
$account_ids_to_user_confs = [];
$account_ids_to_usernames = [];
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
    $email_should_be_validated = FreshRSS_Context::systemConf()->force_email_validation;
    $email_validated = !$email_should_be_validated || $user_conf->email_validation_token === '';
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
        $account_ids_to_usernames[$account['id']] = $username;
    } elseif (!$no_account) {
        $account_ids_to_user_confs[$subscription['account_id']] = $user_conf;
        $account_ids_to_usernames[$subscription['account_id']] = $username;
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
        Minz_Log::error("Subscription account {$account_id} does not exist.");
        continue;
    }

    $user_conf = $account_ids_to_user_confs[$account_id];
    $subscription = $user_conf->subscription;
    if ($subscription['expired_at'] !== $expired_at) {
        $subscription['expired_at'] = $expired_at;
        $user_conf->subscription = $subscription;
        $user_conf->save();
    }

    // Make the user inactive if account is overdue
    $today = new \DateTime();
    $expired_at_date = date_create_from_format('Y-m-d H:i:sP', $expired_at);
    if ($expired_at_date === false) {
        Minz_Log::warning("Subscription account {$account_id} has invalid expiration date ({$expired_at}).");
        continue;
    }

    $username = $account_ids_to_usernames[$account_id];
    $free_account = $expired_at_date->getTimestamp() === 0;
    $subscription_is_overdue = $today >= $expired_at_date;
    $is_inactive = FreshRSS_UserDAO::mtime($username) < $min_last_activity;
    if (!$free_account && $subscription_is_overdue && !$is_inactive) {
        touch(USERS_PATH . '/' . $username . '/config.php', $min_last_activity);
    }
}
