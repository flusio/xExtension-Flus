<?php

namespace Flus\services;

class Payplug {
    public static function init($key, $api_version) {
        \Payplug\Payplug::init(array(
            'secretKey' => $key,
            'apiVersion' => $api_version,
        ));
    }

    public static function create($username, $frequency, $amount) {
        $user_conf = get_user_configuration($username);
        $address = $user_conf->billing['address'];
        $email = $user_conf->mail_login;

        $return_url = \Minz_Url::display(
            array('c' => 'billing', 'a' => 'return'), 'php', true
        );

        $payment = \Payplug\Payment::create(array(
            'amount' => $amount * 100,
            'currency' => 'EUR',
            'billing' => array(
                'first_name'    => $address['first_name'],
                'last_name'     => $address['last_name'],
                'email'         => $email,
                'address1'      => $address['address'],
                'postcode'      => $address['postcode'],
                'city'          => $address['city'],
                'country'       => $address['country'],
                'language'      => 'fr',
            ),
            'shipping' => array(
                'first_name'    => $address['first_name'],
                'last_name'     => $address['last_name'],
                'email'         => $email,
                'address1'      => $address['address'],
                'postcode'      => $address['postcode'],
                'city'          => $address['city'],
                'country'       => $address['country'],
                'language'      => 'fr',
                'delivery_type' => 'DIGITAL_GOODS',
            ),
            'hosted_payment' => array(
                'return_url' => $return_url,
                'cancel_url' => $return_url,
            ),
            'metadata' => array(
                'username' => $username,
                'frequency' => $frequency,
            )
        ));

        return new Payplug($payment);
    }

    public static function retrieve($payment_id) {
        $payment = \Payplug\Payment::retrieve($payment_id);
        return new Payplug($payment);
    }

    private $payment = null;
    private $status = 'unknown';
    private $invoice_number = '';

    private function __construct($payment) {
        $this->payment = $payment;
    }

    public function syncStatus() {
        $failure = $this->payment->failure;
        if ($this->payment->is_paid) {
            $this->status = 'paid';
        } elseif ($failure) {
            $this->status = $failure->code;
        } else {
            $this->status = 'waiting';
        }
    }

    public function save() {
        $payment_id = $this->payment->id;
        $username = $this->payment->metadata['username'];
        $user_conf = get_user_configuration($username);
        $billing = $user_conf->billing;
        $billing['payments'][$payment_id] = $this->payment();
        $user_conf->billing = $billing;
        return $user_conf->save();
    }

    public function payment() {
        return array(
            'type' => 'payplug',
            'status' => $this->status,
            'date' => $this->payment->created_at,
            'frequency' => $this->payment->metadata['frequency'],
            'amount' => $this->payment->amount / 100,
            'invoice_number' => $this->invoice_number,
        );
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
        return $this->payment->created_at;
    }

    public function amount() {
        return $this->payment->amount / 100;
    }

    public function address() {
        return [
            'first_name' => $this->payment->billing->first_name,
            'last_name' => $this->payment->billing->last_name,
            'address1' => $this->payment->billing->address1,
            'postcode' => $this->payment->billing->postcode,
            'city' => $this->payment->billing->city,
        ];
    }

    public function username() {
        return $this->payment->metadata['username'];
    }

    public function frequency() {
        return $this->payment->metadata['frequency'];
    }

    public function pay() {
        header('Location: ' . $this->payment->hosted_payment->payment_url);
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
