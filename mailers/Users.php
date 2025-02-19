<?php

namespace Flus\mailers;

use Flus\utils;

class Users extends \Minz_Mailer
{
    public function __construct()
    {
        parent::__construct(utils\View::class);
    }

    public function sendInactivityEmail(string $username, string $to): bool
    {
        $this->view->_path('users/inactivity_email.txt');

        $this->view->username = $username;
        $this->view->app_title = \FreshRSS_Context::$system_conf->title;

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';

        return $this->mail(
            $to,
            $subject_prefix . ' Votre compte sera bientôt supprimé pour cause d’inactivité'
        );
    }
}
