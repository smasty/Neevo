<?php
/**
 * Neevo - Tiny database layer for PHP. (http://neevo.smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * Copyright (c) 2013 Smasty (http://smasty.net)
 *
 */
// PHP compatibility
if (version_compare(PHP_VERSION, '5.3', '<')) {
    trigger_error('Neevo requires PHP version 5.3 or newer', E_USER_ERROR);
}


// Try to turn magic quotes off - Neevo handles SQL quoting.
if (function_exists('set_magic_quotes_runtime')) @set_magic_quotes_runtime(false);


// Register PSR-0 autoloader.
spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    if (strncmp($class, 'Neevo', 5) === 0) {
        $file = __DIR__ . '/' . strtr($class, '\\', '/') . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
});
