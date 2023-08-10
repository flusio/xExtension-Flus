<?php

namespace Flus\services;

/**
 * The Subscriptions service allows to get information about a user
 * subscription.
 *
 * @author  Marien Fressinaud <dev@marienfressinaud.fr>
 * @license http://www.gnu.org/licenses/agpl-3.0.en.html AGPL
 */
class Subscriptions
{
    /** @var string */
    private $host;

    /** @var string */
    private $private_key;

    /** @var \SpiderBits\Http */
    private $http;

    /**
     * @param string $host
     * @param string $private_key
     */
    public function __construct($host, $private_key)
    {
        $this->host = $host;
        $this->private_key = $private_key;

        $this->http = new \SpiderBits\Http();
        $this->http->user_agent = FRESHRSS_USERAGENT;
        $this->http->timeout = 5;
    }

    /**
     * Get account information for the given email. Please always make sure the
     * email has been validated first!
     *
     * @param string $email
     *
     * @return array|null
     */
    public function account($email)
    {
        try {
            $response = $this->http->get($this->host . '/api/account', [
                'email' => $email,
            ], [
                'auth_basic' => $this->private_key . ':',
            ]);
        } catch (\SpiderBits\HttpError $e) {
            \Minz_Log::error("Error while requesting a subscription account: {$e->getMessage()}");
            return null;
        }

        if ($response->success) {
            $data = json_decode($response->data, true);
            if (!$data) {
                return null;
            }

            $data['expired_at'] = date_create_from_format('Y-m-d H:i:sP', $data['expired_at']);
            return $data;
        } else {
            \Minz_Log::error("Error while requesting a subscription account: error code {$response->status}");
            return null;
        }
    }

    /**
     * Get a login URL for the given account.
     *
     * @param string $account_id
     *
     * @return string|null
     */
    public function loginUrl($account_id)
    {
        try {
            $response = $this->http->get($this->host . '/api/account/login-url', [
                'account_id' => $account_id,
                'service' => 'freshrss',
            ], [
                'auth_basic' => $this->private_key . ':',
            ]);
        } catch (\SpiderBits\HttpError $e) {
            \Minz_Log::error("Error while requesting a subscription login URL: {$e->getMessage()}");
            return null;
        }

        if ($response->success) {
            $data = json_decode($response->data, true);
            if (!$data) {
                return null;
            }

            return $data['url'];
        } else {
            \Minz_Log::error("Error while requesting a subscription login URL: error code {$response->status}");
            return null;
        }
    }

    /**
     * Get the expired_at value for the given account.
     *
     * @param string $account_id
     *
     * @return \DateTime|null
     */
    public function expiredAt($account_id)
    {
        try {
            $response = $this->http->get($this->host . '/api/account/expired-at', [
                'account_id' => $account_id,
            ], [
                'auth_basic' => $this->private_key . ':',
            ]);
        } catch (\SpiderBits\HttpError $e) {
            \Minz_Log::error("Error while requesting a subscription expiration date: {$e->getMessage()}");
            return null;
        }

        if ($response->success) {
            $data = json_decode($response->data, true);
            if (!$data) {
                \Minz_Log::error("Error while requesting a subscription expiration date: can’t parse the response data");
                return null;
            }

            return date_create_from_format('Y-m-d H:i:sP', $data['expired_at']);
        } else {
            \Minz_Log::error("Error while requesting a subscription expiration date: error code {$response->status}");
            return null;
        }
    }

    /**
     * Get the expired_at value for the given accounts.
     *
     * @param string[] $account_ids
     *
     * @return string[]|null
     */
    public function sync($account_ids)
    {
        try {
            $response = $this->http->post($this->host . '/api/accounts/sync', [
                'account_ids' => json_encode($account_ids),
            ], [
                'auth_basic' => $this->private_key . ':',
            ]);
        } catch (\SpiderBits\HttpError $e) {
            \Minz_Log::error("Error while syncing subscriptions: {$e->getMessage()}");
            return null;
        }

        if (!$response->success) {
            \Minz_Log::error("Error while syncing subscriptions: code {$response->status}");
            return null;
        }

        $data = json_decode($response->data, true);
        if (!is_array($data)) {
            \Minz_Log::error("Error while syncing subscriptions: can’t decode data");
            return null;
        }

        return $data;
    }
}
