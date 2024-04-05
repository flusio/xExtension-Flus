<?php

use Flus\utils;

class FreshExtension_index_Controller extends FreshRSS_index_Controller
{
    private Minz_Extension $extension;

    public function __construct()
    {
        parent::__construct(utils\View::class);
    }

    public function init(): void
    {
        $this->extension = Minz_ExtensionManager::findExtension('Flus');
    }

    public function indexAction(): void
    {
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
            $this->view->max_registrations = $system_conf->limits['max_registrations'];
            Minz_View::prependTitle('Suivez l’actualité qui vous intéresse · ');
        }
    }

    public function supportAction(): void
    {
        if (!FreshRSS_Auth::hasAccess()) {
            Minz_Error::error(404);
        }

        $email = FreshRSS_Context::$user_conf->mail_login;
        $this->view->email = $email;
        $this->view->subject = '';
        $this->view->content = '';
        Minz_View::prependTitle('Aide et support · ');

        if (Minz_Request::isPost()) {
            $subject = trim(Minz_Request::param('subject'));
            $content = trim(Minz_Request::param('content'));
            $this->view->subject = $subject;
            $this->view->content = $content;

            if (!$subject || !$content) {
                $this->view->notification = [
                    'type' => 'bad',
                    'content' => 'Les deux champs sont obligatoires.',
                ];
                return;
            }

            $mailer = new \Flus\mailers\Support();
            $mailer->sendMessage($email, $subject, $content);
            $mailer->sendNotification($email, $subject);

            Minz_Request::good('Votre message a bien été envoyé', array(
                'c' => 'index',
                'a' => 'support',
            ));
        }
    }
}
