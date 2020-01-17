<?php

namespace Flus\mailers;

/**
 * Manage the emails sent for billing purposes.
 */
class Billing extends \Minz_Mailer {
    public function send_subscription_ending($username, $user_conf) {
        $this->view->_path('billing/email_subscription_ending.txt');

        $subscription_end_at = $user_conf->billing['subscription_end_at'];
        $this->view->username = $username;
        $this->view->subscription_end_at = timestamptodate($subscription_end_at, false);
        $this->view->site_title = \FreshRSS_Context::$system_conf->title;
        $this->view->email_support = 'support@flus.io';
        $this->view->renew_url = \Minz_Url::display(
            array(
                'c' => 'billing',
                'a' => 'renew'
            ),
            'txt',
            true
        );

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            $user_conf->mail_login,
            $subject_prefix . ' Votre abonnement arrive à échéance'
        );
    }

    public function send_subscription_ended($username, $user_conf) {
        $this->view->_path('billing/email_subscription_ended.txt');

        $subscription_end_at = $user_conf->billing['subscription_end_at'];
        $this->view->username = $username;
        $this->view->subscription_end_at = timestamptodate($subscription_end_at, false);
        $this->view->site_title = \FreshRSS_Context::$system_conf->title;
        $this->view->email_support = 'support@flus.io';
        $this->view->renew_url = \Minz_Url::display(
            array(
                'c' => 'billing',
                'a' => 'renew'
            ),
            'txt',
            true
        );

        $subject_prefix = '[' . \FreshRSS_Context::$system_conf->title . ']';
        return $this->mail(
            $user_conf->mail_login,
            $subject_prefix . ' Votre abonnement a expiré'
        );
    }
}
