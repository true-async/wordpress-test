<?php

if (!file_exists( 'wp-config.php' ) ) {
    exit('WP not installed.');
}

const WP_ROOT = __DIR__;
const WP_USE_THEMES = true;
const ABSPATH = __DIR__ . '/';

include_once WP_ROOT . '/wp-config.php';
include_once WP_ROOT . '/wp-settings.php';

class WPShared
{
    public static array $globals = [];

    public static array $superglobals = ['_GET', '_POST', '_COOKIE', '_SERVER', '_FILES'];

    public static function cloneGlobals(): void
    {
        foreach (WPShared::$globals as $name => $value) {
            // $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
            if($name == 'wp_query') {
                continue;
            }
            else if($name == 'wp_the_query') {
                $GLOBALS[$name] = clone $value;
                // alias for wp_the_query
                $GLOBALS['wp_query'] = $GLOBALS[$name];
            } else if(is_object($value)) {
                $GLOBALS[$name] = clone $value;
            } else {
                $GLOBALS[$name] = $value;
            }
        }
    }
}

foreach ($GLOBALS as $key => $value) {

    // Without superglobals
    if (in_array($key, WPShared::$superglobals)) continue;
    WPShared::$globals[$key] = $GLOBALS[$key];
}

