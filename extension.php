<?php

class FlusExtension extends Minz_Extension {
    public function init() {
        $this->registerController('index');
        $this->registerViews();
    }
}
