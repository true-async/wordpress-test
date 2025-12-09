<?php

use NginxUnit\HttpServer;
use NginxUnit\Request;
use NginxUnit\Response;

set_time_limit(0);

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

// Load WordPress core
include_once 'wp-loader.php';

//
// The function, the entry point, is called from NGINX UNIT.
//
HttpServer::onRequest(static function (Request $request, Response $response) {
    
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
    
    ob_start();

    try {

        WPShared::cloneGlobals();
        wp();

        $template_loader = ABSPATH . WPINC . '/template-loader.php';
        include $template_loader;

        // Get the output
        $output = ob_get_clean();

        if (!headers_sent()) {
            $response->setStatus(200);
            $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        }
        
        $response->write($output);
        $response->end();
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