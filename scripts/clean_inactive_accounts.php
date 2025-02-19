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

// Delete accounts inactive for +12 months
foreach ($usernames as $username) {
    $user_conf = get_user_configuration($username);
    if (!$user_conf || !$user_conf->hasParam('deletion_notified_at')) {
        continue;
    }

    $notified_at = \DateTime::createFromFormat('Y-m-d', $user_conf->deletion_notified_at);
    $one_month_ago = new \DateTime('-1 month');

    if ($notified_at > $one_month_ago) {
        // We wait for 1 month after the notification to delete the account.
        continue;
    }

    echo "FreshRSS deleting inactive user “{$username}”…\n";

    FreshRSS_user_Controller::deleteUser($username);
}
