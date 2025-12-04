<?php

use NginxUnit\HttpServer;
use NginxUnit\Request;
use NginxUnit\Response;

set_time_limit(0);

echo "Starting NginxUnit HttpServer with Superglobals Test...\n";

HttpServer::onRequest(static function (Request $request, Response $response) {
    // Set response headers
    $response->setHeader('Content-Type', 'application/json');
    $response->setStatus(200);

    // Test superglobals
    $responseData = [
        'message' => 'Superglobals Test',
        'phpinfo' => get_loaded_extensions(true),
        'request_info' => [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
        ],
        'superglobals' => [
            '$_GET' => $_GET,
            '$_POST' => $_POST,
            '$_COOKIE' => $_COOKIE,
            '$_SERVER' => [
                'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
                'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT SET',
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'NOT SET',
                'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'NOT SET',
                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'NOT SET',
            ],
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];

    $response->write(json_encode($responseData, JSON_PRETTY_PRINT));
    $response->end();
});

echo "Superglobals test handler registered. Server ready.\n";
