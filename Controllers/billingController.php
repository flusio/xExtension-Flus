<?php

use \Flus\services\Stripe;
use \Flus\models\Invoice;

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
        $payments = array_filter(
            $payments,
            function($payment) {
                return $payment['status'] !== 'canceled';
            }
        );
        uasort(
            $payments,
            function ($payment1, $payment2) {
                return $payment2['date'] - $payment1['date'];
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

        if (Minz_Request::isPost()) {
            $frequency = Minz_Request::param('frequency', 'month');
            if ($frequency !== 'month' && $frequency !== 'year') {
                $frequency = 'month';
            }

            // Save prefered frequency for the next time
            $billing['subscription_frequency'] = $frequency;
            $user_conf->billing = $billing;
            $user_conf->save();

            if ($frequency === 'month') {
                $amount = $this->view->month_price;
            } else {
                $amount = $this->view->year_price;
            }

            $username = Minz_Session::param('currentUser', '_');

            Stripe::init($system_conf->billing['stripe_secret_key']);
            $payment_service = Stripe::create($username, $frequency, $amount);
            $payment_service->syncStatus();
            $payment_service->save();

            Minz_Request::forward(
                ['c' => 'billing', 'a' => 'pay'], true
            );
        }
    }

    public function payAction() {
        $system_conf = FreshRSS_Context::$system_conf;
        $waiting_payment_id = $this->view->waiting_payment_id;
        if ($waiting_payment_id === null) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }

        $this->_csp([
            'default-src' => "'self' js.stripe.com",
            'script-src' => "'self' 'unsafe-inline' js.stripe.com",
        ]);

        Minz_View::appendScript('https://js.stripe.com/v3/', false, true, false);

        $this->view->_layout('redirection');
        $this->view->stripe_public_key = $system_conf->billing['stripe_public_key'];
        $this->view->checkout_session_id = $waiting_payment_id;
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
        Stripe::init($system_conf->billing['stripe_secret_key']);

        $payment_service = Stripe::retrieve($waiting_payment_id);
        $payment_service->syncStatus();
        $payment_service->save();

        if ($payment_service->isPaid()) {
            $payment_service->approve();
            $payment_service->generateInvoice();
        }

        if ($payment_service->isPaid()) {
            Minz_View::prependTitle('Validation du paiement · ');
        } elseif ($payment_service->isWaiting()) {
            Minz_View::prependTitle('Prise en compte du paiement · ');
        } else {
            Minz_View::prependTitle('Échec du paiement · ');
        }

        $this->view->payment = $payment_service->payment();
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

        $system_conf = FreshRSS_Context::$system_conf;
        Stripe::init($system_conf->billing['stripe_secret_key']);
        $payment_service = Stripe::retrieve($waiting_payment_id);
        $payment_service->cancel();
        $payment_service->save();

        Minz_View::prependTitle('Annulation du paiement · ');
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
    }

    public function downloadInvoiceAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        $requested_invoice_number = Minz_Request::param('number', '');

        $user_conf = FreshRSS_Context::$user_conf;
        $payments = $user_conf->billing['payments'];
        $user_invoice_numbers = array_column($payments, 'invoice_number');

        if (!in_array($requested_invoice_number, $user_invoice_numbers)) {
            // The user doesn't own the requested invoice
            $username = Minz_Session::param('currentUser', '_');
            Minz_Log::warning("${username} tried to access {$requested_invoice_number} invoice PDF file.", ADMIN_LOG);
            Minz_Error::error(403);
        }

        $invoice_pdf_path = Invoice::getPdfPath($requested_invoice_number);
        if (!file_exists($invoice_pdf_path)) {
            Minz_Log::warning("Invoice n°{$requested_invoice_number} PDF file does not exist.");
            Minz_Error::error(404);
        }

        if (!is_readable($invoice_pdf_path)) {
            Minz_Log::warning("Invoice n°{$requested_invoice_number} PDF file is not readable.");
            Minz_Error::error(404);
        }

        $this->view->_layout(false);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $file_name . '"');
        readfile($invoice_pdf_path);
    }
}
