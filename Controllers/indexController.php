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
            Minz_View::appendStyle($this->extension->getFileUrl('style.css', 'css'));
            $this->view->illustration_url = $this->extension->getFileUrl('screenshot.png', 'png');
            Minz_View::prependTitle('Suivez l’actualité qui vous intéresse' . ' · ');
        }
    }
}
