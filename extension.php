<?php

class FlusExtension extends Minz_Extension {
    public function init() {
        $this->registerController('index');
        $this->registerController('billing');
        $this->registerViews();

        $this->registerHook('menu_configuration_entry', array('FlusExtension', 'getMenuEntry'));
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
}
