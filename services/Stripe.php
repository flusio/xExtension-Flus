<?php

namespace Flus\services;

use \Flus\models\Invoice;

class Stripe {
    public static function init($key) {
        \Stripe\Stripe::setApiKey($key);
    }

    public static function create($username, $frequency, $amount) {
        $user_conf = get_user_configuration($username);
        $email = $user_conf->mail_login;

        $success_url = \Minz_Url::display(
            ['c' => 'billing', 'a' => 'success'], 'php', true
        );
        $cancel_url = \Minz_Url::display(
            ['c' => 'billing', 'a' => 'cancel'], 'php', true
        );

        $session = \Stripe\Checkout\Session::create([
            'customer_email' => $email,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'name' => 'Abonnement Flus',
                'amount' => $amount * 100,
                'currency' => 'eur',
                'quantity' => 1,
            ]],
            'payment_intent_data' => [
                'metadata' => [
                    'username' => $username,
                    'frequency' => $frequency,
                ],
            ],
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'expand' => ['payment_intent'],
        ]);

        return new Stripe($session);
    }

    public static function retrieve($session_id) {
        $session = \Stripe\Checkout\Session::retrieve([
            'id' => $session_id,
            'expand' => ['payment_intent'],
        ]);
        return new Stripe($session);
    }

    private $session = null;
    private $status = 'unknown';
    private $invoice_number = '';

    private function __construct($session) {
        $this->session = $session;
    }

    public function syncStatus() {
        $payment_intent = $this->session->payment_intent;
        if ($payment_intent->status === 'succeeded') {
            $this->status = 'paid';
        } elseif ($payment_intent->status === 'canceled') {
            $this->status = 'canceled';
        } else {
            $this->status = 'waiting';
        }
    }

    public function cancel() {
        if ($this->status !== 'waiting') {
            try {
                $payment_intent = $this->session->payment_intent;
                $payment_intent->cancel();
            } catch (\Stripe\Exception\ApiErrorException $e) {
                // do nothing on purpose: the payment was already canceled by
                // Stripe on their side.
            }

            $this->status = 'canceled';
        }
    }

    public function save() {
        $user_conf = get_user_configuration($this->username());

        $billing = $user_conf->billing;
        $billing['payments'][$this->id()] = $this->payment();
        $user_conf->billing = $billing;

        return $user_conf->save();
    }

    public function payment() {
        return array(
            'type' => 'stripe',
            'status' => $this->status,
            'date' => $this->date(),
            'frequency' => $this->frequency(),
            'amount' => $this->amount(),
            'invoice_number' => $this->invoice_number,
        );
    }

    public function id() {
        return $this->session->id;
    }

    public function isPaid() {
        return $this->status === 'paid';
    }

    public function isCanceled() {
        return $this->status === 'canceled';
    }

    public function isWaiting() {
        return $this->status === 'waiting';
    }

    public function date() {
        $payment_intent = $this->session->payment_intent;
        return $payment_intent->created;
    }

    public function amount() {
        $payment_intent = $this->session->payment_intent;
        return $payment_intent->amount / 100;
    }

    public function address() {
        $user_conf = get_user_configuration($this->username());
        $address = $user_conf->billing['address'];
        return [
            'first_name' => $address['first_name'],
            'last_name' => $address['last_name'],
            'address1' => $address['address'],
            'postcode' => $address['postcode'],
            'city' => $address['city'],
        ];
    }

    public function username() {
        return $this->session->payment_intent->metadata['username'];
    }

    public function frequency() {
        return $this->session->payment_intent->metadata['frequency'];
    }

    public function approve() {
        $user_conf = get_user_configuration($this->username());
        $billing = $user_conf->billing;
        $current_subscription_end_at = $billing['subscription_end_at'];

        // no need to renew a user with a free plan (subscription_end_at === null)
        if ($current_subscription_end_at !== null) {
            $frequency = $this->frequency();
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

    public function generateInvoice() {
        $invoice = Invoice::generate($this);
        $invoice->saveAsPdf();

        $this->invoice_number = $invoice->number;
        $this->save();
    }
}
