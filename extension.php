<?php

class FlusExtension extends Minz_Extension {
    public function install() {
        $files_to_install = array(
            '/legals/cgu.html' => DATA_PATH . '/tos.html',
            '/config-user.custom.php' => DATA_PATH . '/config-user.custom.php',
        );

        foreach ($files_to_install as $src_file => $dest_file) {
            $res = copy($this->getPath() . $src_file, $dest_file);
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    public function uninstall() {
        $files_to_unlink = array(
            DATA_PATH . '/tos.html',
            DATA_PATH . '/config-user.custom.php',
        );

        foreach ($files_to_unlink as $file) {
            if (file_exists($file)) {
                $res = unlink($file);
                if (!$res) {
                    return false;
                }
            }
        }
        return true;
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

        spl_autoload_register(array($this, 'loader'));
    }

    public function loader($class_name) {
        if (strpos($class_name, 'Flus') === 0) {
            $class_name = substr($class_name, 5);
            $base_path = $this->getPath() . '/';
            include($base_path . str_replace('\\', '/', $class_name) . '.php');
        } elseif (strpos($class_name, 'Payplug') === 0) {
            $base_path = $this->getPath() . '/lib/payplug-php/lib/';
            include($base_path . str_replace('\\', '/', $class_name) . '.php');
        }
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
                'payments' => array(),
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
            Minz_Request::is('index', 'about') ||
            Minz_Request::is('index', 'tos') ||
            Minz_Request::is('index', 'cgv') ||
            Minz_Request::is('auth', 'logout') ||
            Minz_Request::is('billing', 'index') ||
            Minz_Request::is('billing', 'address') ||
            Minz_Request::is('billing', 'renew') ||
            Minz_Request::is('billing', 'return')
        );
        if ($subscription_is_overdue && !$action_is_allowed) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }
    }
}
