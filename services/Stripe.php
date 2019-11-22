<?php

namespace Flus\services;

class Stripe {
    public static function init($key) {
        \Stripe\Stripe::setApiKey($key);
    }

    public static function create($username, $frequency, $amount) {
        $user_conf = get_user_configuration($username);
        $email = $user_conf->mail_login;

        $return_url = \Minz_Url::display(
            ['c' => 'billing', 'a' => 'return'], 'php', true
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
            'success_url' => $return_url,
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
            $this->status = 'canceled';
            $payment_intent = $this->session->payment_intent;
            $payment_intent->cancel();
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

    public function generateInvoiceNumber() {
        $invoices_path = DATA_PATH . '/extensions-data/xExtension-Flus/invoices';
        $lock_path = $invoices_path . '/.lock';

        $lock_file = fopen($lock_path, 'r+');

        if (flock($lock_file, LOCK_EX)) {
            $last_invoice_number = @fread($lock_file, filesize($lock_path));
            $this->invoice_number = $this->getNextInvoiceNumber($last_invoice_number);
            $this->save();

            rewind($lock_file);
            fwrite($lock_file, $this->invoice_number);

            flock($lock_file, LOCK_UN);
        }

        fclose($lock_file);

        return $this->invoice_number;
    }

    private function getNextInvoiceNumber($last_invoice_number) {
        $current_date = getdate();
        $year = $current_date['year'];
        $month = $current_date['mon'];

        $invoice_sequence = 1;
        if ($last_invoice_number) {
            list(
                $last_invoice_year,
                $last_invoice_month,
                $last_invoice_sequence
            ) = array_map('intval', explode('-', $last_invoice_number));

            if ($last_invoice_year === $year) {
                $invoice_sequence = $last_invoice_sequence + 1;
            }
        }

        $invoice_format = '%04d-%02d-%04d';
        return sprintf(
            $invoice_format, $year, $month, $invoice_sequence
        );
    }
}
