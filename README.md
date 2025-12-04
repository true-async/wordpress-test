# WordPress with TrueAsync PHP

Test build demonstrating WordPress running on **TrueAsync PHP** with **NGINX Unit** - single-threaded execution with coroutine-based concurrency.

## Quick Start

### Build
```bash
docker build -t wordpress-trueasync .
```

### Run
```bash
docker run -d \
  --name wordpress-async \
  -p 8080:8080 \
  wordpress-trueasync
```

### Access
```
http://localhost:8080
```

## How It Works

WordPress runs in a single process with concurrent request handling via coroutines:

```php
HttpServer::onRequest(static function (Request $request, Response $response) {
    ob_start();
    
    try {
        include_once WP_ROOT . '/wp-load.php';

        wp();  // Each request runs as separate coroutine        
        
        if (defined('ABSPATH') && defined('WPINC')) {
            $template_loader = ABSPATH . WPINC . '/template-loader.php';
            include $template_loader;
        }
        
        $output = ob_get_clean();
        $response->setStatus(200);
        $response->write($output);
        $response->end();
    } catch (Throwable $e) {
        // Error handling
    }
});
```

Multiple requests are handled concurrently in the same PHP process using fiber-based coroutines.

### Global Isolation

This works thanks to **global isolation** in TrueAsync PHP:

- Each coroutine has its own isolated `$GLOBALS`
- When NGINX Unit starts a request coroutine, it sets unique `$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE` superglobals
- Superglobals are bound to the request scope - all child coroutines within that request inherit them
- Different requests never conflict because their superglobals are isolated from each other

This allows WordPress to handle multiple requests simultaneously in one process without data corruption or race conditions.

## Configuration

Default credentials:
- Database: `trueasync`
- User: `trueasync`
- Password: `trueasync`

Mount custom WordPress files:
```bash
docker run -d -p 8080:8080 -v $(pwd)/app:/app/www wordpress-trueasync
```

## Purpose

This is a **test environment** to demonstrate coroutine-based concurrent request handling in WordPress with TrueAsync PHP.

## Resources

- [TrueAsync PHP](https://github.com/true-async/php-src)
- [TrueAsync Extension](https://github.com/true-async/php-async)
- [NGINX Unit](https://github.com/EdmondDantes/nginx-unit)
