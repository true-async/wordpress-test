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
