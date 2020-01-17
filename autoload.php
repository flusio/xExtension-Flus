<?php

spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'Flus') === 0) {
        $class_name = substr($class_name, 5);
        include(__DIR__ . '/' . str_replace('\\', '/', $class_name) . '.php');
    } elseif (strpos($class_name, 'Stripe') === 0) {
        include(__DIR__ . '/lib/stripe-php/init.php');
    } elseif ($class_name === 'FPDF') {
        include(__DIR__ . '/lib/fpdf/fpdf.php');
    }
});
