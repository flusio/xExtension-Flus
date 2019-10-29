<?php

class FreshExtension_billing_Controller extends FreshRSS_index_Controller {
    public function init() {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function firstAction() {
        $user_conf = FreshRSS_Context::$user_conf;
        $today = time();
        $subscription_end_at = $user_conf->billing['subscription_end_at'];
        if ($subscription_end_at === null) {
            // Free plan
            $subscription_is_overdue = false;
        } else {
            $subscription_is_overdue = $today >= $subscription_end_at;
        }

        $this->view->subscription_is_overdue = $subscription_is_overdue;

        if ($subscription_is_overdue) {
            $this->view->_layout('simple');
        }
    }

    public function indexAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Facturation · ');

        $user_conf = FreshRSS_Context::$user_conf;
        $this->view->today = time();
        $this->view->subscription_end_at = $user_conf->billing['subscription_end_at'];
        $this->view->subscription_end_at_label = timestamptodate(
            $this->view->subscription_end_at, false
        );

        if ($this->view->subscription_end_at !== null) {
            $weeks_before_soon = 2;
            $seconds_before_soon = 60 * 60 * 24 * 7 * $weeks_before_soon;
            $seconds_remaining = $this->view->subscription_end_at - $this->view->today;
            $this->view->subscription_end_is_soon = $seconds_remaining <= $seconds_before_soon;
        } else {
            $this->view->subscription_end_is_soon = false;
        }
    }

    public function renewAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Facturation · ');

        $system_conf = FreshRSS_Context::$system_conf;
        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        $this->view->paypal_illustration = $this->extension->getFileUrl('paypal.jpg', 'jpg');
        $this->view->cb_illustration = $this->extension->getFileUrl('cb.jpg', 'jpg');
        $this->view->month_price = $system_conf->billing['month_price'];
        $this->view->year_price = $system_conf->billing['year_price'];
        $this->view->subscription_frequency = $billing['subscription_frequency'];
        $this->view->subscription_type = $billing['subscription_type'];

        if (Minz_Request::isPost()) {
            // @todo this should be handled on payment service callback
            $frequency = Minz_Request::param('frequency', 'month');
            if ($frequency !== 'month' && $frequency !== 'year') {
                $frequency = 'month';
            }
            $payment_type = Minz_Request::param('payment-type', 'card');
            //if ($payment_type !== 'card' && $payment_type !== 'paypal') {
                $payment_type = 'card';
            //}

            if ($frequency === 'year') {
                $interval = '1 year';
            } else {
                $interval = '1 month';
            }

            $today = time();
            $current_subscription_end_at = $billing['subscription_end_at'];

            // no need to renew a user with a free plan (subscription_end_at === null)
            if ($current_subscription_end_at !== null) {
                $base_date_renewal = max($today, $current_subscription_end_at);

                $subscription_end_at = date_create()->setTimestamp($base_date_renewal);
                date_add(
                    $subscription_end_at, date_interval_create_from_date_string($interval)
                );
                $billing['subscription_end_at'] = $subscription_end_at->getTimestamp();
            }

            $billing['subscription_frequency'] = $frequency;
            $billing['subscription_type'] = $payment_type;

            $user_conf->billing = $billing;
            if ($user_conf->save()) {
                Minz_Request::good('Vous avez renouvelé votre abonnement.', array(
                    'c' => 'billing',
                    'a' => 'index',
                ));
            } else {
                $error = 'Un problème est survenu lors de l’enregistrement du paiement.';
                Minz_Request::bad($error, array(
                    'c' => 'billing',
                    'a' => 'index',
                ));
            }
        }
    }
}
