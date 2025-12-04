<?php

use NginxUnit\HttpServer;
use NginxUnit\Request;
use NginxUnit\Response;

set_time_limit(0);

// WordPress root directory
define( 'WP_ROOT' , __DIR__);

// Logging function
function log_debug($message) {
    file_put_contents(__DIR__.'/debug.log', date('[Y-m-d H:i:s] ') . $message . "\n", FILE_APPEND);
}

log_debug("=== Entrypoint started ===");

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        log_debug("SHUTDOWN: " . print_r($error, true));
    }
});

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    log_debug("ERROR[$errno]: $errstr in $errfile:$errline");
    return false; // Let PHP handle it normally
});

$WordPressInstalled = false;

if ( file_exists( 'wp-config.php' ) ) {
    $WordPressInstalled = true;
    log_debug("WordPress wp-config.php found");
}

log_debug("Registering onRequest handler...");

//
// The function, the entry point, is called from NGINX UNIT.
//
HttpServer::onRequest(static function (Request $request, Response $response) use(&$WordPressInstalled) {
    
    log_debug("=== Request received ===");
    
    $method = $request->getMethod();
    $uri = $request->getUri();
    $path = parse_url($uri, PHP_URL_PATH);

    // Remove leading slash
    $path = ltrim($path, '/');

    // Default to index.php if path is empty
    if (empty($path) || $path === '/') {
        $path = 'index.php';
    }

    // Full file path
    $file = WP_ROOT . '/' . $path;

    // Check if it's a static file (not PHP)
    if (is_file($file) && !str_ends_with($path, '.php')) {
        // Serve static file
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';

        $response->setHeader('Content-Type', $contentType);
        $response->setStatus(200);
        $response->write(file_get_contents($file));
        $response->end();
        return;
    }
    
    // Set up WordPress environment variables
    $_SERVER['SCRIPT_FILENAME']     = $file;
    $_SERVER['SCRIPT_NAME']         = '/' . $path;
    $_SERVER['PHP_SELF']            = '/' . $path;
    $_SERVER['DOCUMENT_ROOT']       = WP_ROOT;
    
    if(false === $WordPressInstalled) {
        $response->setStatus(200);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->write('<h1>WordPress is not installed</h1><p>Please run the installation.</p>');
        $response->end();
        return;
    }
    
    log_debug("Processing PHP file: $file");
    
    if(!defined('WP_USE_THEMES')) {
        define( 'WP_USE_THEMES', true );
    }
    
    ob_start();
    
    try {
        include_once WP_ROOT . '/wp-load.php';
        
        if (function_exists('wp')) {
            wp();
        } else {
            log_debug("ERROR: wp() function not found!");
        }
        
        if (defined('ABSPATH') && defined('WPINC')) {
            $template_loader = ABSPATH . WPINC . '/template-loader.php';
            include $template_loader;
        } else {
            log_debug("ERROR: ABSPATH or WPINC not defined!");
        }
        
        // Get the output
        $output = ob_get_clean();
        log_debug("Output captured, length: " . strlen($output));
        
        if (!headers_sent()) {
            $response->setStatus(200);
            $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        }
        
        $response->write($output);
        log_debug("Response written, calling end()...");
        $response->end();
        log_debug("Response end() called");
    } catch (Throwable $e) {
        log_debug("ERROR processing PHP file: " . $e->getMessage());
        ob_end_clean();
        
        $response->setStatus(500);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->write('<h1>Error</h1>');
        $response->write('<pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
        $response->write('<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>');
        $response->end();
    }
});
