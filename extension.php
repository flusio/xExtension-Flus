<?php

class FlusExtension extends Minz_Extension
{
    public function install(): bool
    {
        $files_to_install = array(
            '/legals/cgu.html' => DATA_PATH . '/tos.html',
            '/config-user.custom.php' => DATA_PATH . '/config-user.custom.php',
            '/default-feeds.xml' => DATA_PATH . '/opml.xml',
        );

        foreach ($files_to_install as $src_file => $dest_file) {
            $res = copy($this->getPath() . $src_file, $dest_file);
            if (!$res) {
                return false;
            }
        }

        return true;
    }

    public function uninstall(): bool
    {
        $files_to_unlink = array(
            DATA_PATH . '/tos.html',
            DATA_PATH . '/config-user.custom.php',
            DATA_PATH . '/opml.xml',
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

    public function init(): void
    {
        Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
        Minz_View::appendScript($this->getFileUrl('script.js', 'js'));

        $this->registerController('index');
        $this->registerController('billing');
        $this->registerViews();
        $this->registerTranslates();

        $this->registerHook('menu_configuration_entry', array('FlusExtension', 'getMenuEntry'));
        $this->registerHook('menu_other_entry', array('FlusExtension', 'getSupportEntry'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'initBillingConfiguration'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'syncIfOverdue'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'blockIfOverdue'));
        $this->registerHook('freshrss_init', array('FlusExtension', 'registerFlusSharing'));

        require(__DIR__ . '/autoload.php');
    }

    public static function getMenuEntry(): string
    {
        if (Minz_Request::controllerName() === 'billing') {
            $active_class = ' active';
        } else {
            $active_class = '';
        }
        $url = _url('billing', 'index');
        $label = 'Abonnement Flus';

        return "<li class=\"item$active_class\"><a href=\"$url\">$label</a></li>";
    }

    public static function getSupportEntry(): string
    {
        if (Minz_Request::is('index', 'support')) {
            $active_class = ' active';
        } else {
            $active_class = '';
        }
        $url = _url('index', 'support');
        $label = 'Aide et support';

        return "<li class=\"item$active_class\"><a href=\"$url\">$label</a></li>";
    }

    public static function initBillingConfiguration(): void
    {
        // Initialize the basic subscription info for all the users
        $user_conf = FreshRSS_Context::$user_conf;
        if ($user_conf && !is_array($user_conf->subscription)) {
            $expired_at = new \DateTime();
            $expired_at->modify('+1 month');
            $user_conf->subscription = [
                'account_id' => null,
                'expired_at' => $expired_at->format('Y-m-d H:i:sP'),
            ];
            $user_conf->save();
        }

        // Get a Flus subscription account id for validated users who don't
        // have one yet.
        $no_account = $user_conf->subscription['account_id'] === null;
        $email_should_be_validated = FreshRSS_Context::$system_conf->force_email_validation;
        $email_validated = !$email_should_be_validated || $user_conf->email_validation_token === '';
        if ($no_account && $email_validated) {
            $flus_api_host = FreshRSS_Context::$system_conf->billing['flus_api_host'] ?? '';
            $flus_private_key = FreshRSS_Context::$system_conf->billing['flus_private_key'] ?? '';

            if (!$flus_api_host || !$flus_private_key) {
                return;
            }

            $subscriptions_service = new \Flus\services\Subscriptions($flus_api_host, $flus_private_key);
            $account = $subscriptions_service->account($user_conf->mail_login);

            if ($account) {
                $user_conf->subscription = [
                    'account_id' => $account['id'],
                    'expired_at' => $account['expired_at']->format('Y-m-d H:i:sP'),
                ];
                $user_conf->save();
            }
        }
    }

    public static function syncIfOverdue(): void
    {
        if (!FreshRSS_Auth::hasAccess()) {
            return;
        }

        $user_conf = FreshRSS_Context::$user_conf;
        if (!$user_conf) {
            return;
        }

        $today = new \DateTime();
        $subscription = $user_conf->subscription;
        $expired_at = date_create_from_format('Y-m-d H:i:sP', $subscription['expired_at']);

        $free_account = $expired_at->getTimestamp() === 0;
        if ($free_account) {
            return;
        }

        $subscription_is_overdue = $today >= $expired_at;
        if (!$subscription_is_overdue) {
            return;
        }

        $flus_api_host = FreshRSS_Context::$system_conf->billing['flus_api_host'] ?? '';
        $flus_private_key = FreshRSS_Context::$system_conf->billing['flus_private_key'] ?? '';

        if (!$flus_api_host || !$flus_private_key) {
            return;
        }

        $subscriptions_service = new \Flus\services\Subscriptions($flus_api_host, $flus_private_key);
        $account_id = $subscription['account_id'];

        $expired_at = $subscriptions_service->expiredAt($account_id);
        if (!$expired_at) {
            return;
        }

        $subscription['expired_at'] = $expired_at->format('Y-m-d H:i:sP');
        $user_conf->subscription = $subscription;
        $user_conf->save();
    }

    public static function blockIfOverdue(): void
    {
        // don't block if user is not authenticated
        if (!FreshRSS_Auth::hasAccess()) {
            return;
        }

        $user_conf = FreshRSS_Context::$user_conf;
        if (!$user_conf) {
            // It should not happen, but make it sure
            return;
        }

        $today = new \DateTime();
        $subscription = $user_conf->subscription;
        $expired_at = date_create_from_format('Y-m-d H:i:sP', $subscription['expired_at']);

        $free_account = $expired_at->getTimestamp() === 0;
        if ($free_account) {
            return;
        }

        $subscription_is_overdue = $today >= $expired_at;
        $action_is_allowed = (
            Minz_Request::is('index', 'tos') ||
            Minz_Request::is('auth', 'logout') ||
            Minz_Request::is('feed', 'actualize') ||
            Minz_Request::is('user', 'validateEmail') ||
            Minz_Request::is('user', 'sendValidationEmail') ||
            Minz_Request::is('user', 'profile') ||
            Minz_Request::is('user', 'delete') ||
            Minz_Request::is('javascript', 'nonce') ||
            Minz_Request::controllerName() === 'billing'
        );
        if ($subscription_is_overdue && !$action_is_allowed) {
            Minz_Request::forward(array(
                'c' => 'billing',
                'a' => 'index',
            ), true);
        }
    }

    public static function registerFlusSharing(): void
    {
        FreshRSS_Share::register([
            'type' => 'flus',
            'url' => 'https://app.flus.fr/links/new?url=~LINK~',
            'transform' => array('rawurlencode'),
            'form' => 'simple',
            'method' => 'GET',
        ]);
    }
}
