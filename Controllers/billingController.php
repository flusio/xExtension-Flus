<?php

use \Flus\services;
use \Flus\utils;

class FreshExtension_billing_Controller extends FreshRSS_index_Controller {
    public function init() {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function firstAction() {
        $user_conf = FreshRSS_Context::$user_conf;
        $subscription = $user_conf->subscription;

        $today = new \DateTime();
        $expired_at = date_create_from_format('Y-m-d H:i:sP', $subscription['expired_at']);
        $free_account = $expired_at->getTimestamp() === 0;
        if ($free_account) {
            $subscription_is_overdue = false;
        } else {
            $subscription_is_overdue = $today >= $expired_at;
        }

        $this->view->expired_at = $expired_at;
        $this->view->free_account = $free_account;
        $this->view->subscription_is_overdue = $subscription_is_overdue;

        if ($subscription_is_overdue) {
            $this->view->_layout('simple');
        }
    }

    public function indexAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Votre abonnement Flus · ');

        $this->view->expired_at_label = timestamptodate(
            $this->view->expired_at->getTimestamp(),
            false
        );

        if (!$this->view->free_account) {
            $weeks_before_soon = 2;
            $seconds_before_soon = 60 * 60 * 24 * 7 * $weeks_before_soon;
            $seconds_remaining = $this->view->expired_at->getTimestamp() - time();
            $this->view->subscription_end_is_soon = $seconds_remaining <= $seconds_before_soon;
        } else {
            $this->view->subscription_end_is_soon = false;
        }
    }
}
