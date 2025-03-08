<?php

// This file is part of xExtension-Flus
// Copyright 2019-2025 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Flus\mailers;

use Flus\utils;

class Support extends \Minz_Mailer
{
    public function __construct()
    {
        parent::__construct(utils\View::class);
    }

    public function sendMessage(string $from, string $subject, string $content): bool
    {
        $this->view->_path('index/email_support.txt');

        $this->view->from = $from;
        $this->view->content = $content;

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            'support@flus.fr',
            $subject_prefix . ' ' . $subject
        );
    }

    public function sendNotification(string $to, string $subject): bool
    {
        $this->view->_path('index/email_notification.txt');

        $this->view->subject = $subject;

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            $to,
            $subject_prefix . ' Votre message a bien été envoyé'
        );
    }
}
