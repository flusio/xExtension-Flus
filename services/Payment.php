<?php

namespace Flus\services;

class Payment {
    const API_HOST = 'https://flus.fr';

    /** @var string */
    private $private_key;

    /**
     * @param string $private_key
     */
    public function __construct($private_key)
    {
        $this->private_key = $private_key;
    }
}
