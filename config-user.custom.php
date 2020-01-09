<?php

return array (
    'theme' => 'xTheme-Flus',
    'content_width' => 'medium',
    'posts_per_page' => 25,
    'mark_when' => array (
        'article' => true,
        'site' => true,
        'scroll' => false,
        'reception' => false,
    ),
    'sharing' => array (
        array (
            'name' => 'Twitter',
            'type' => 'twitter',
            'url' => null,
        ),
        array (
            'name' => 'Courriel',
            'type' => 'email',
            'url' => null,
        ),
        array (
            'name' => 'Imprimer',
            'type' => 'print',
            'url' => null,
        ),
    ),
    'extensions_enabled' => array (
        'YouTube/PeerTube Video Feed' => true,
    ),
);
