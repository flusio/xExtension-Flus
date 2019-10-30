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

        $return_url = \Minz_Url::display(array(
            'c' => 'billing',
            'a' => 'return',
            'params' => array(
                'username' => $username,
            ),
        ), 'php', true);
        $cancel_url = \Minz_Url::display(array(
            'c' => 'billing',
            'a' => 'cancel',
            'params' => array(
                'username' => $username,
            ),
        ), 'php', true);

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
                'cancel_url' => $cancel_url,
            ),
            'metadata' => array(
                'username' => $username,
                'frequency' => $frequency,
            )
        ));

        return new Payplug($payment);
    }

    private $payment = null;
    private $status = 'unknown';

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
        );
    }

    }

    public function pay() {
        header('Location: ' . $this->payment->hosted_payment->payment_url);
    }
}
