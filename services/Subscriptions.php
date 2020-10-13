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
     * @return array
     */
    public function getAccount($email, $expired_at = null, $reminder = null)
    {
        $url = self::API_HOST . '/account';
        $params = [
            'email' => $email,
        ];
        if ($expired_at) {
            $params['expired_at'] = $expired_at->format('Y-m-d H:i:sP');
        }
        if ($reminder !== null) {
            $params['reminder'] = $reminder;
        }

        $curl_session = curl_init();
        curl_setopt($curl_session, CURLOPT_URL, $url . '?' . http_build_query($params));
        curl_setopt($curl_session, CURLOPT_HEADER, false);
        curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_session, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl_session, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl_session, CURLOPT_USERPWD, $this->private_key . ':');

        $result = curl_exec($curl_session);
        $http_code = curl_getinfo($curl_session, CURLINFO_RESPONSE_CODE);

        if ($result === false) {
            $error = curl_error($curl_session);
            \Minz_Log::error("getAccount failed: {$error}.");
        }

        if ($http_code < 200 || $http_code >= 300) {
            \Minz_Log::error("getAccount failed, HTTP code {$http_code}.");
        }

        curl_close($curl_session);

        if ($result !== false) {
            return json_decode($result, true);
        } else {
            return null;
        }
    }
}
