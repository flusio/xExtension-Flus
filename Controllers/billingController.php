<?php

use \Flus\services;
use \Flus\utils;

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
            if (!$payment['completed_at']) {
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
                return $payment2['created_at'] - $payment1['created_at'];
            }
        );

        $this->view->reminder = (
            isset($user_conf->billing['reminder']) &&
            $user_conf->billing['reminder']
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

    public function reminderAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        if (!Minz_Request::isPost()) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        if ($billing['subscription_end_at'] === null) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        $reminder = Minz_Request::paramBoolean('reminder', false);
        $billing['reminder'] = $reminder;
        $user_conf->billing = $billing;

        if ($reminder) {
            $message = 'Vous recevrez un rappel par courriel lorsque votre abonnement arrivera à échéance.';
        } else {
            $message = 'Vous ne recevrez plus de rappel lorsque votre abonnement arrivera à échéance.';
        }

        if ($user_conf->save()) {
            Minz_Request::good($message, array(
                'c' => 'billing',
                'a' => 'index',
            ));
        } else {
            Minz_Request::bad(
                'Une erreur est survenue, vous devriez prévenir le support.',
                array(
                    'c' => 'billing',
                    'a' => 'index',
                )
            );
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

        $this->view->month_price = $system_conf->billing['month_price'];
        $this->view->year_price = $system_conf->billing['year_price'];
        $this->view->subscription_frequency = $billing['subscription_frequency'];
        $this->view->address = $billing['address'];
        $this->view->countryLabel = utils\Countries::codeToLabel($billing['address']['country']);

        if (Minz_Request::isPost()) {
            $frequency = Minz_Request::param('frequency', 'month');
            if ($frequency !== 'month' && $frequency !== 'year') {
                $frequency = 'month';
            }

            // Save prefered frequency for the next time
            $billing['subscription_frequency'] = $frequency;
            $user_conf->billing = $billing;
            $user_conf->save();

            $payment_service = new services\Payment($system_conf->billing['flus_private_key']);

            $username = Minz_Session::param('currentUser', '_');
            $email = $user_conf->mail_login;
            $payment = $payment_service->createSession(
                $username,
                $email,
                $frequency,
                $billing['address']
            );

            if ($payment) {
                $billing['payments'][$payment['id']] = $payment;
                $user_conf->billing = $billing;
                $user_conf->save();

                Minz_Request::forward($payment_service->payUrl($payment), true);
            } else {
                $this->view->notification = [
                    'type' => 'bad',
                    'content' => 'Une erreur est survenue lors du paiement, vous pouvez contacter le support pour en savoir plus.',
                ];
            }
        }
    }

    public function checkAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        invalidateHttpCache();

        $payment_id = Minz_Request::param('id');

        $system_conf = FreshRSS_Context::$system_conf;
        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        $payment_service = new services\Payment($system_conf->billing['flus_private_key']);
        $payment = $payment_service->retrievePayment($payment_id);

        if ($payment) {
            $billing['payments'][$payment['id']] = $payment;
            $user_conf->billing = $billing;
            $user_conf->save();

            if ($payment['completed_at']) {
                $this->extendSubscription($user_conf, $payment['frequency']);
            } else {
                Minz_Request::forward($payment_service->payUrl($payment), true);
            }
        }

        Minz_Request::forward(array(
            'c' => 'billing',
            'a' => 'index',
        ), true);
    }

    public function successAction() {
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

        $system_conf = FreshRSS_Context::$system_conf;
        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        $payment_service = new services\Payment($system_conf->billing['flus_private_key']);
        $payment = $payment_service->retrievePayment($waiting_payment_id);

        if ($payment) {
            $billing['payments'][$payment['id']] = $payment;
            $user_conf->billing = $billing;
            $user_conf->save();

            if ($payment['completed_at']) {
                $this->extendSubscription($user_conf, $payment['frequency']);

                Minz_View::prependTitle('Validation du paiement · ');
            } else {
                Minz_View::prependTitle('Prise en compte du paiement · ');
            }

            $this->view->payment = $payment;
        } else {
            Minz_Log::error(
                "Tried to access {$waiting_payment_id} payment, but it seems to don't exist."
            );

            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }
    }

    public function cancelAction() {
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

        $user_conf = FreshRSS_Context::$user_conf;
        $billing = $user_conf->billing;

        if (isset($billing['payments'][$waiting_payment_id])) {
            unset($billing['payments'][$waiting_payment_id]);
        }

        $user_conf->billing = $billing;
        $user_conf->save();

        Minz_View::prependTitle('Annulation du paiement · ');
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
        $country = 'FR';

        $address_missing = !array_key_exists('address', $billing);
        if (!$address_missing) {
            $first_name = $billing['address']['first_name'];
            $last_name = $billing['address']['last_name'];
            $address = $billing['address']['address'];
            $postcode = $billing['address']['postcode'];
            $city = $billing['address']['city'];
            $country = $billing['address']['country'];
        }

        if (Minz_Request::isPost()) {
            $first_name = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('first_name', '')));
            $last_name = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('last_name', '')));
            $address = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('address', '')));
            $postcode = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('postcode', '')));
            $city = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('city', '')));
            $country = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('country', '')));

            if (
                $first_name != '' &&
                $last_name != '' &&
                $address != '' &&
                $postcode != '' &&
                $city != '' &&
                utils\Countries::isSupported($country)
            ) {
                $billing['address'] = array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'address' => $address,
                    'postcode' => $postcode,
                    'city' => $city,
                    'country' => $country,
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
        $this->view->country = $country;
        $this->view->countries = utils\Countries::listSorted();
    }

    public function downloadInvoiceAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        $payment_id = Minz_Request::param('id');

        $system_conf = FreshRSS_Context::$system_conf;
        $user_conf = FreshRSS_Context::$user_conf;
        $payments = $user_conf->billing['payments'];

        if (!isset($payments[$payment_id])) {
            // The user doesn't own the requested invoice
            $username = Minz_Session::param('currentUser', '_');
            Minz_Log::warning("${username} tried to access {$payment_id} invoice PDF file.", ADMIN_LOG);
            Minz_Error::error(403);
        }

        $payment_service = new services\Payment($system_conf->billing['flus_private_key']);
        $this->view->_layout(false);
        $invoice = $payment_service->retrieveInvoice($payment_id);

        if ($invoice) {
            $date = date('Y-m-d');
            $filename = "{$date}_facture_Flus.pdf";
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            echo $invoice;
        } else {
            Minz_Error::error(404);
        }
    }

    /**
     * Extend the subscription_end_at attribute of the given user.
     *
     * @param \Minz_Configuration $user_conf
     * @param string $frequency `month` or `year`
     *
     * @return boolean
     */
    private function extendSubscription($user_conf, $frequency) {
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
            $user_conf->billing = $billing;
            return $user_conf->save();
        }

        return true;
    }
}
