<?php

// This file is part of xExtension-Flus
// Copyright 2019-2025 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

use Flus\services;
use Flus\utils;

class FreshExtension_billing_Controller extends FreshRSS_ActionController
{
    private FlusExtension $extension;

    public function __construct()
    {
        parent::__construct(utils\View::class);
    }

    public function init(): void
    {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function firstAction(): void
    {
        $user_conf = FreshRSS_Context::$user_conf;
        $subscription = $user_conf->subscription;

        $today = new \DateTime();
        $expired_at = date_create_from_format('Y-m-d H:i:sP', $subscription['expired_at']);
        $free_account = $expired_at->getTimestamp() === 0;
        if ($free_account) {
            $subscription_is_overdue = false;
        } else {
            $subscription_is_overdue = $today >= $expired_at;
        }

        $this->view->expired_at = $expired_at;
        $this->view->free_account = $free_account;
        $this->view->subscription_is_overdue = $subscription_is_overdue;

        if ($subscription_is_overdue) {
            $this->view->_layout('simple');
        }
    }

    public function indexAction(): void
    {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Votre abonnement Flus · ');

        $this->view->expired_at_label = timestamptodate(
            $this->view->expired_at->getTimestamp(),
            false
        );

        if (!$this->view->free_account) {
            $weeks_before_soon = 2;
            $seconds_before_soon = 60 * 60 * 24 * 7 * $weeks_before_soon;
            $seconds_remaining = $this->view->expired_at->getTimestamp() - time();
            $this->view->subscription_end_is_soon = $seconds_remaining <= $seconds_before_soon;
        } else {
            $this->view->subscription_end_is_soon = false;
        }
    }

    public function redirectAction(): Minz_Request
    {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        $user_conf = FreshRSS_Context::$user_conf;
        $subscription = $user_conf->subscription;
        if (empty($subscription['account_id'])) {
            Minz_Log::error("Le compte de paiement de {$user_conf->mail_login} n’a pas été initialisé.");
            return Minz_Request::bad('Votre compte de paiement n’a pas encore été initialisé.', [
                'c' => 'billing',
                'a' => 'index',
            ], true);
        }

        $flus_api_host = FreshRSS_Context::$system_conf->billing['flus_api_host'] ?? '';
        $flus_private_key = FreshRSS_Context::$system_conf->billing['flus_private_key'] ?? '';

        if (!$flus_api_host || !$flus_private_key) {
            Minz_Log::error('Le serveur de paiement n’a pas été correctement configuré.');
            return Minz_Request::bad('Une erreur est survenue lors de la connexion au compte de paiement.', [
                'c' => 'billing',
                'a' => 'index',
            ]);
        }

        $subscriptions_service = new services\Subscriptions($flus_api_host, $flus_private_key);
        $account_id = $subscription['account_id'];

        $url = $subscriptions_service->loginUrl($account_id);
        if ($url) {
            header('Location: ' . $url);
            exit();
        } else {
            return Minz_Request::bad('Une erreur est survenue lors de la connexion au compte de paiement.', [
                'c' => 'billing',
                'a' => 'index',
            ], true);
        }
    }
}
