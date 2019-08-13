<?php

class FlusExtension extends Minz_Extension {
    public function init() {
        Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'));

        $this->registerController('index');
        $this->registerController('billing');
        $this->registerViews();

        $this->registerHook('menu_configuration_entry', array('FlusExtension', 'getMenuEntry'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'initBillingConfiguration'));
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
}
