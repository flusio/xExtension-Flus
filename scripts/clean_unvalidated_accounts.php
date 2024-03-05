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

if (!FreshRSS_Context::systemConf()->force_email_validation) {
    Minz_Log::error('"force_email_validation" must be set to true in config before cleaning accounts.');
    return;
}

$usernames = listUsers();
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf) {
        continue;
    }

    $email_validated = $user_conf->email_validation_token === '';
    if ($email_validated) {
        // Email was validated, ignore this user.
        continue;
    }

    $last_activity = FreshRSS_UserDAO::mtime($username);
    $six_months = strtotime('-6 months');
    if ($last_activity >= $six_months) {
        // User was active within the last six months, ignore them.
        continue;
    }

    echo 'FreshRSS deleting user “', $username, "”…\n";

    FreshRSS_user_Controller::deleteUser($username);
}
