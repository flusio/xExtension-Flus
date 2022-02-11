<?php

spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'Flus') === 0) {
        $class_name = substr($class_name, 5);
        include(__DIR__ . '/' . str_replace('\\', '/', $class_name) . '.php');
    } elseif (strpos($class_name, 'SpiderBits') === 0) {
        include __DIR__ . '/lib/SpiderBits/autoload.php';
    }
});
