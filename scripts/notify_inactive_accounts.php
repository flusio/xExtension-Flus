#!/bin/env php
<?php

// This file is part of xExtension-Flus
// Copyright 2019-2025 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

if (php_sapi_name() !== 'cli') {
    die('This script must be called from command line.');
}

const FLUS_EXTENSION_PATH = __DIR__ . '/..';
require(FLUS_EXTENSION_PATH . '/../../constants.php');
require(FLUS_EXTENSION_PATH . '/autoload.php');
require(LIB_PATH . '/lib_rss.php');

FreshRSS_Context::initSystem();

if (!FreshRSS_Context::systemConf()->force_email_validation) {
    Minz_Log::error('"force_email_validation" must be set to true in config before cleaning accounts.');
    return;
}

\Minz_View::addBasePathname(FLUS_EXTENSION_PATH);

$usernames = listUsers();

$count_notified = 0;

// Notify accounts inactive for +12 months
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        continue;
    }

    $already_notified = $user_conf->hasParam('deletion_notified_at');

    $last_activity = FreshRSS_UserDAO::mtime($username);
    $twelve_months = strtotime('-12 months');
    $is_active = $last_activity >= $twelve_months;

    if ($already_notified || $is_active) {
        continue;
    }

    echo "FreshRSS notifying user “{$username}” about deleting their account…\n";

    $mailer = new \Flus\mailers\Users();
    $mailer->sendInactivityEmail($username, $user_conf->mail_login);

    $deletion_notified_at = new \DateTime();
    $user_conf->deletion_notified_at = $deletion_notified_at->format('Y-m-d');
    $user_conf->save();

    // This is a bit hacky, but by saving the user conf, we changed the last
    // activity date. This allows to reset the last activity to its previous
    // value.
    touch(USERS_PATH . '/' . $username . '/config.php', $last_activity);

    $count_notified += 1;

    if ($count_notified >= 20) {
        // Stop after 20 notified accounts so we don't send too many emails at
        // once.
        echo "FreshRSS stopping to notify users after 20 emails.\n";
        break;
    }

    // Don't spam the notifications
    $sleep_seconds = random_int(2, 5);
    sleep($sleep_seconds);
}
