<?php

class FreshExtension_billing_Controller extends FreshRSS_index_Controller {
    public function init() {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
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
    }

    public function renewAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Facturation · ');

        $system_conf = FreshRSS_Context::$system_conf;

        $this->view->paypal_illustration = $this->extension->getFileUrl('paypal.jpg', 'jpg');
        $this->view->cb_illustration = $this->extension->getFileUrl('cb.jpg', 'jpg');
        $this->view->month_price = $system_conf->billing['month_price'];
        $this->view->year_price = $system_conf->billing['year_price'];

        if (Minz_Request::isPost()) {
            // @todo this should be handled on payment service callback
            $frequency = Minz_Request::param('frequency', 'month');
            $user_conf = FreshRSS_Context::$user_conf;
            $billing = $user_conf->billing;

            if ($frequency === 'year') {
                $interval = '1 year';
            } else {
                $interval = '1 month';
            }

            $today = time();
            $current_subscription_end_at = $billing['subscription_end_at'];
            $base_date_renewal = max($today, $current_subscription_end_at);

            $subscription_end_at = date_create()->setTimestamp($base_date_renewal);
            date_add(
                $subscription_end_at, date_interval_create_from_date_string($interval)
            );
            $billing['subscription_end_at'] = $subscription_end_at->getTimestamp();

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
