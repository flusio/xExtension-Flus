<?php

class FreshExtension_billing_Controller extends FreshRSS_index_Controller {
    public function indexAction() {
        Minz_View::prependTitle('Facturation · ');
    }
}
