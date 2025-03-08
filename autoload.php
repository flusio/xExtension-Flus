<?php

// This file is part of xExtension-Flus
// Copyright 2019-2025 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'Flus') === 0) {
        $class_name = substr($class_name, 5);
        include(__DIR__ . '/' . str_replace('\\', '/', $class_name) . '.php');
    } elseif (strpos($class_name, 'SpiderBits') === 0) {
        include __DIR__ . '/lib/SpiderBits/autoload.php';
    }
});
