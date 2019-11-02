<?php

class FreshExtension_index_Controller extends FreshRSS_index_Controller {
    public function init() {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function indexAction() {
        if (FreshRSS_Auth::hasAccess()) {
            $prefered_output = FreshRSS_Context::$user_conf->view_mode;
            Minz_Request::forward(array(
                'c' => 'index',
                'a' => $prefered_output
            ));
        } else {
            $this->view->_layout('home');
            Minz_View::appendStyle($this->extension->getFileUrl('home.css', 'css'));
            $this->view->registration_opened = !max_registrations_reached();
            $this->view->illustration_url = $this->extension->getFileUrl('screenshot.png', 'png');
            $system_conf = FreshRSS_Context::$system_conf;
            $this->view->month_price = $system_conf->billing['month_price'];
            $this->view->year_price = $system_conf->billing['year_price'];
            Minz_View::prependTitle('Suivez l’actualité qui vous intéresse · ');
        }
    }

    public function aboutAction() {
        $this->view->about = file_get_contents($this->extension->getPath() . '/legals/about.html');
        $this->view->can_register = !max_registrations_reached();
        Minz_View::prependTitle(_t('index.about.title') . ' · ');
    }

    public function cgvAction() {
        $this->view->cgv = file_get_contents($this->extension->getPath() . '/legals/cgv.html');
        $this->view->is_connected = FreshRSS_Auth::hasAccess();
        $this->view->can_register = !max_registrations_reached();
        Minz_View::prependTitle('Conditions Générales de Vente · ');
    }
}
