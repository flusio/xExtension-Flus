<?php

namespace Flus\mailers;

class Support extends \Minz_Mailer {
    public function send_message($from, $subject, $content) {
        $this->view->_path('index/email_support.txt');

        $this->view->from = $from;
        $this->view->content = $content;

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            'support@flus.io',
            $subject_prefix . ' ' . $subject
        );
    }

    public function send_notification($to, $subject) {
        $this->view->_path('index/email_notification.txt');

        $this->view->subject = $subject;

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            $to,
            $subject_prefix . ' Votre message a bien été envoyé'
        );
    }
}
