<?php

use \Flus\services\Payplug;

class FreshExtension_billing_Controller extends FreshRSS_index_Controller {
    public function init() {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function firstAction() {
        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        $today = time();
        $subscription_end_at = $billing['subscription_end_at'];
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

        $waiting_payment_id = null;
        foreach ($billing['payments'] as $id => $payment) {
            if ($payment['status'] === 'waiting') {
                $waiting_payment_id = $id;
                break;
            }
        }
        $this->view->waiting_payment_id = $waiting_payment_id;
    }

    public function indexAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Facturation · ');

        $user_conf = FreshRSS_Context::$user_conf;
        $payments = $user_conf->billing['payments'];
        uasort(
            $payments,
            function ($payment1, $payment2) {
                return $payment2['date'] - $payment1['date'];
            }
        );

        $this->view->payments = $payments;
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

        if ($this->view->waiting_payment_id !== null) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        // disable renew while I don't have a bank account
        Minz_Request::forward(array(
            'c' => 'billing',
            'a' => 'index',
        ), true);

        Minz_View::prependTitle('Facturation · ');

        $system_conf = FreshRSS_Context::$system_conf;
        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        if (!array_key_exists('address', $billing)) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'address',
            ), true);
        }

        $this->view->paypal_illustration = $this->extension->getFileUrl('paypal.jpg', 'jpg');
        $this->view->cb_illustration = $this->extension->getFileUrl('cb.jpg', 'jpg');
        $this->view->month_price = $system_conf->billing['month_price'];
        $this->view->year_price = $system_conf->billing['year_price'];
        $this->view->subscription_frequency = $billing['subscription_frequency'];
        $this->view->subscription_type = $billing['subscription_type'];
        $this->view->address = $billing['address'];

        if (Minz_Request::isPost()) {
            $frequency = Minz_Request::param('frequency', 'month');
            if ($frequency !== 'month' && $frequency !== 'year') {
                $frequency = 'month';
            }
            $payment_type = Minz_Request::param('payment-type', 'card');
            //if ($payment_type !== 'card' && $payment_type !== 'paypal') {
                $payment_type = 'card';
            //}

            // Save prefered options for the next time
            $billing['subscription_frequency'] = $frequency;
            $billing['subscription_type'] = $payment_type;
            $user_conf->billing = $billing;
            $user_conf->save();

            if ($frequency === 'month') {
                $amount = $this->view->month_price;
            } else {
                $amount = $this->view->year_price;
            }

            $username = Minz_Session::param('currentUser', '_');

            Payplug::init(
                $system_conf->billing['payplug_secret_key'],
                $system_conf->billing['payplug_api_version']
            );
            $payment_service = Payplug::create($username, $frequency, $amount);
            $payment_service->syncStatus();
            $payment_service->save();
            $payment_service->pay();
        }
    }

    public function returnAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        invalidateHttpCache();

        $waiting_payment_id = $this->view->waiting_payment_id;
        if ($waiting_payment_id === null) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        $payment_service = $this->acknowledgeWaitingPayment($waiting_payment_id);

        if ($payment_service->isPaid()) {
            $username = $payment_service->username();
            $frequency = $payment_service->frequency();
            $this->approvePayment($username, $frequency);
            $payment_service->generateInvoiceNumber();
        }

        if ($payment_service->isPaid()) {
            Minz_View::prependTitle('Validation du paiement · ');
        } elseif ($payment_service->isCanceled()) {
            Minz_View::prependTitle('Annulation du paiement · ');
        } elseif ($payment_service->isWaiting()) {
            Minz_View::prependTitle('Prise en compte du paiement · ');
        } else {
            Minz_View::prependTitle('Échec du paiement · ');
        }

        $this->view->payment = $payment_service->payment();
    }

    public function addressAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        if ($this->view->waiting_payment_id !== null) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        Minz_View::prependTitle('Adresse de facturation · ');

        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        $first_name = '';
        $last_name = '';
        $address = '';
        $postcode = '';
        $city = '';

        $address_missing = !array_key_exists('address', $billing);
        if (!$address_missing) {
            $first_name = $billing['address']['first_name'];
            $last_name = $billing['address']['last_name'];
            $address = $billing['address']['address'];
            $postcode = $billing['address']['postcode'];
            $city = $billing['address']['city'];
        }

        if (Minz_Request::isPost()) {
            $first_name = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('first_name', '')));
            $last_name = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('last_name', '')));
            $address = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('address', '')));
            $postcode = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('postcode', '')));
            $city = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('city', '')));

            if (
                $first_name != '' &&
                $last_name != '' &&
                $address != '' &&
                $postcode != '' &&
                $city != ''
            ) {
                $billing['address'] = array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'address' => $address,
                    'postcode' => $postcode,
                    'city' => $city,
                    'country' => 'FR',
                );
                $user_conf->billing = $billing;
                if ($user_conf->save()) {
                    Minz_Request::good('Votre adresse a été enregistrée.', array(
                        'c' => 'billing',
                        'a' => 'renew',
                    ));
                } else {
                    Minz_Request::bad('Nous n’avons pas pu enregistrer votre adresse.', array(
                        'c' => 'billing',
                        'a' => 'address',
                    ));
                }
            } else {
                Minz_Request::bad('Tous les champs sont requis.', array(
                    'c' => 'billing',
                    'a' => 'address',
                ));
            }
        }

        $this->view->address_missing = $address_missing;
        $this->view->first_name = $first_name;
        $this->view->last_name = $last_name;
        $this->view->address = $address;
        $this->view->postcode = $postcode;
        $this->view->city = $city;
    }

    private function acknowledgeWaitingPayment($waiting_payment_id) {
        $system_conf = FreshRSS_Context::$system_conf;
        Payplug::init(
            $system_conf->billing['payplug_secret_key'],
            $system_conf->billing['payplug_api_version']
        );
        $payment_service = Payplug::retrieve($waiting_payment_id);
        $payment_service->syncStatus();
        $payment_service->save();
        return $payment_service;
    }

    private function approvePayment($username, $frequency) {
        $user_conf = get_user_configuration($username);
        $billing = $user_conf->billing;
        $current_subscription_end_at = $billing['subscription_end_at'];

        // no need to renew a user with a free plan (subscription_end_at === null)
        if ($current_subscription_end_at !== null) {
            if ($frequency === 'year') {
                $interval = '1 year';
            } else {
                $interval = '1 month';
            }

            $today = time();
            $base_date_renewal = max($today, $current_subscription_end_at);

            $subscription_end_at = date_create()->setTimestamp($base_date_renewal);
            date_add(
                $subscription_end_at, date_interval_create_from_date_string($interval)
            );
            $billing['subscription_end_at'] = $subscription_end_at->getTimestamp();
        }

        $user_conf->billing = $billing;
        return $user_conf->save();
    }
}
