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

echo "email;name\n";

$usernames = FreshRSS_user_Controller::listUsers();
foreach ($usernames as $username) {
    $user_conf = FreshRSS_UserConfiguration::getForUser($username);
    if (!$user_conf) {
        continue;
    }

    $email_validated = $user_conf->email_validation_token === '';
    if (!$email_validated) {
        // Email is not validated, ignore this user.
        continue;
    }

    $email = $user_conf->mail_login;

    echo "{$email};{$username}\n";
}
