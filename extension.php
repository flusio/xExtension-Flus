<?php

class FlusExtension extends Minz_Extension {
    public function install() {
        $cgu_initial_path = $this->getPath() . '/legals/cgu.html';
        $cgu_destination_path = DATA_PATH . '/tos.html';
        return copy($cgu_initial_path, $cgu_destination_path);
    }

    public function uninstall() {
        $cgu_path = DATA_PATH . '/tos.html';
        return unlink($cgu_path);
    }

    public function init() {
        Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'));

        $this->registerController('index');
        $this->registerController('billing');
        $this->registerViews();

        $this->registerHook('menu_configuration_entry', array('FlusExtension', 'getMenuEntry'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'initBillingConfiguration'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'blockIfOverdue'));
    }

    public static function getMenuEntry() {
        if (Minz_Request::controllerName() === 'billing') {
            $active_class = ' active';
        } else {
            $active_class = '';
        }
        $url = _url('billing', 'index');
        $label = 'Facturation';

        return "<li class=\"item$active_class\"><a href=\"$url\">$label</a></li>";
    }

    public static function initBillingConfiguration() {
        $user_conf = FreshRSS_Context::$user_conf;
        if ($user_conf && !is_array($user_conf->billing)) {
            $user_conf->billing = array(
                'subscription_end_at' => strtotime("+1 month"),
                'subscription_frequency' => 'month',
                'subscription_type' => 'card',
            );
            $user_conf->save();
        }

        $system_conf = FreshRSS_Context::$system_conf;
        if ($system_conf && !is_array($system_conf->billing)) {
            $system_conf->billing = array(
                'month_price' => 5,
                'year_price' => 50,
            );
            $system_conf->save();
        }
    }

    public static function blockIfOverdue() {
        // don't block if user is not authenticated
        if (!FreshRSS_Auth::hasAccess()) {
            return;
        }

        $user_conf = FreshRSS_Context::$user_conf;
        if (!$user_conf) {
            // It should not happen, but make it sure
            return;
        }

        $today = time();
        $subscription_end_at = $user_conf->billing['subscription_end_at'];
        if ($subscription_end_at === null) {
            // Free plan
            return;
        }

        $subscription_is_overdue = $today >= $subscription_end_at;
        $action_is_allowed = (
            Minz_Request::is('auth', 'logout') ||
            Minz_Request::is('billing', 'index') ||
            Minz_Request::is('billing', 'renew')
        );
        if ($subscription_is_overdue && !$action_is_allowed) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }
    }
}
