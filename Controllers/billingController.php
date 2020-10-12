<?php

use \Flus\services;
use \Flus\utils;

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
    }

    public function indexAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
        }

        Minz_View::prependTitle('Facturation · ');

        $user_conf = FreshRSS_Context::$user_conf;

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

    public function addressAction() {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(403);
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
            $country = Minz_Helper::htmlspecialchars_utf8(trim(Minz_Request::param('country', '')));

            if (
                $first_name != '' &&
                $last_name != '' &&
                $address != '' &&
                $postcode != '' &&
                $city != '' &&
                utils\Countries::isSupported($country)
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
        $this->view->countries = utils\Countries::listSorted();
    }
}
