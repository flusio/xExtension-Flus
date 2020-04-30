<?php

namespace Flus\services;

class Payment {
    const API_HOST = 'https://flus.fr';

    /** @var string */
    private $private_key;

    /** @var string */
    public $success_url;

    /** @var string */
    public $cancel_url;

    /**
     * @param string $private_key
     */
    public function __construct($private_key)
    {
        $this->private_key = $private_key;
        $this->success_url = \Minz_Url::display(
            ['c' => 'billing', 'a' => 'success'], 'php', true
        );
        $this->cancel_url = \Minz_Url::display(
            ['c' => 'billing', 'a' => 'cancel'], 'php', true
        );
    }

    /**
     * Call Flus payment API to create a Payment session
     *
     * @param string $username
     * @param string $email
     * @param string $frequency
     * @param array $address
     *
     * @return array
     */
    public function createSession($username, $email, $frequency, $address)
    {
        $curl_session = curl_init();
        $url = self::API_HOST . '/payments/subscriptions';
        curl_setopt($curl_session, CURLOPT_URL, $url);
        curl_setopt($curl_session, CURLOPT_POST, true);
        curl_setopt($curl_session, CURLOPT_HEADER, false);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl_session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_session, CURLOPT_USERPWD, $this->private_key . ':');

        curl_setopt($curl_session, CURLOPT_POSTFIELDS, [
            'success_url' => $this->success_url,
            'cancel_url' => $this->cancel_url,

            'username' => $username,
            'email' => $email,
            'frequency' => $frequency,
            'address[first_name]' => $address['first_name'],
            'address[last_name]' => $address['last_name'],
            'address[address1]' => $address['address'],
            'address[postcode]' => $address['postcode'],
            'address[city]' => $address['city'],
            'address[country]' => $address['country'],
        ]);

        $result = curl_exec($curl_session);
        $http_code = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            $error = curl_error($curl_session);
            \Minz_Log::error("Payment createSession failed: {$error}.");
        }

        if ($http_code < 200 || $http_code >= 300) {
            \Minz_Log::error("Payment createSession failed, HTTP code {$http_code}.");
        }

        curl_close($curl_session);

        if ($result !== false) {
            return json_decode($result, true);
        } else {
            return null;
        }
    }

    /**
     * Get information about a payment
     *
     * @param string $id
     *
     * @return array
     */
    public function retrievePayment($id)
    {
        $curl_session = curl_init();
        $url = self::API_HOST . '/payments/' . $id;
        curl_setopt($curl_session, CURLOPT_URL, $url);
        curl_setopt($curl_session, CURLOPT_HEADER, false);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl_session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_session, CURLOPT_USERPWD, $this->private_key . ':');

        $result = curl_exec($curl_session);
        $http_code = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            $result = '';
            $error = curl_error($curl_session);
            \Minz_Log::error("Payment retrieve failed: {$error}.");
        }

        if ($http_code < 200 || $http_code >= 300) {
            \Minz_Log::error("Payment retrieve failed, HTTP code {$http_code}.");
        }

        curl_close($curl_session);

        if ($result !== false) {
            return json_decode($result, true);
        } else {
            return null;
        }
    }

    /**
     * Get invoice for a given payment id
     *
     * @param string $id
     *
     * @return 
     */
    public function retrieveInvoice($id)
    {
        $curl_session = curl_init();
        $url = self::API_HOST . '/invoices/pdf/' . $id;
        curl_setopt($curl_session, CURLOPT_URL, $url);
        curl_setopt($curl_session, CURLOPT_HEADER, false);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl_session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_session, CURLOPT_USERPWD, $this->private_key . ':');

        $result = curl_exec($curl_session);
        $http_code = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            $error = curl_error($curl_session);
            \Minz_Log::error("Invoice retrieve failed: {$error}.");
        }

        if ($http_code < 200 || $http_code >= 300) {
            \Minz_Log::error("Invoice retrieve failed, HTTP code {$http_code}.");
        }

        curl_close($curl_session);

        return $result;
    }

    /**
     * Return the URL to pay a Payment on Flus
     *
     * @param array $payment
     *
     * @return string
     */
    public function payUrl($payment)
    {
        return self::API_HOST . "/payments/{$payment['id']}/pay";
    }
}
