<?php

namespace Flus\services;

class Subscriptions {
    const API_HOST = 'https://flus.fr/api';

    /** @var string */
    private $private_key;

    /**
     * @param string $private_key
     */
    public function __construct($private_key)
    {
        $this->private_key = $private_key;
    }

    /**
     * Get account information
     *
     * @param string $email
     * @param \DateTime|null $expired_at
     * @param boolean|null $reminder
     *
     * @return array|null
     */
    public function getAccount($email, $expired_at = null, $reminder = null)
    {
        $params = [
            'email' => $email,
        ];
        if ($expired_at) {
            $params['expired_at'] = $expired_at->format('Y-m-d H:i:sP');
        }
        if ($reminder !== null) {
            $params['reminder'] = $reminder;
        }

        return $this->get('/account', $params);
    }

    /**
     * Get a URL to login on Flus subscription center
     *
     * @param string $account_id
     *
     * @return string|null
     */
    public function loginUrl($account_id)
    {
        $response = $this->get('/account/login-url', [
            'account_id' => $account_id,
        ]);
        if ($response) {
            return $response['url'];
        } else {
            return null;
        }
    }

    /**
     * Get the expiration date for a given account
     *
     * @param string $account_id
     *
     * @return string|null
     */
    public function expiredAt($account_id)
    {
        $response = $this->get('/account/expired-at', [
            'account_id' => $account_id,
        ]);
        if ($response) {
            return $response['expired_at'];
        } else {
            return null;
        }
    }

    /**
     * @param string $endpoint
     * @param mixed[] $params
     *
     * @return array|null
     */
    private function get($endpoint, $params = [])
    {
        $url = self::API_HOST . $endpoint;
        if ($params) {
            $url = $url . '?' . http_build_query($params);
        }

        $curl_session = curl_init();
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
            \Minz_Log::error("services/Subscriptions#get {$endpoint} failed: {$error}.");
        }

        if ($http_code < 200 || $http_code >= 300) {
            \Minz_Log::error("services/Subscriptions#get {$endpoint} failed, HTTP code {$http_code}.");
        }

        curl_close($curl_session);

        if ($result !== false) {
            return json_decode($result, true);
        } else {
            return null;
        }
    }
}
