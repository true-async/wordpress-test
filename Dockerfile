# ===== NGINX Unit with TrueAsync PHP (Linux x64 ZTS Release) =====
# Multi-stage build: builder + runtime
# Optimized for external configuration mounting

# ---------- BUILDER STAGE ----------
FROM ubuntu:24.04 AS builder

# Updated to use global-isolation branches
ARG PHP_BRANCH=global-isolation
ARG TRUEASYNC_BRANCH=global-isolation
ARG NGINX_UNIT_BRANCH=true-async
ARG XDEBUG_BRANCH=true-async-86

# ---------- 1. System toolchain & libraries ----------
RUN apt-get update && apt-get install -y \
    autoconf bison build-essential curl re2c git \
    cmake ninja-build wget dos2unix \
    libxml2-dev libssl-dev pkg-config libargon2-dev \
    libcurl4-openssl-dev libedit-dev libreadline-dev \
    libsodium-dev libsqlite3-dev libonig-dev libzip-dev \
    libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    libgmp-dev libldap2-dev libsasl2-dev libpq-dev \
    libmysqlclient-dev libbz2-dev libenchant-2-dev \
    libffi-dev libgdbm-dev liblmdb-dev libsnmp-dev \
    libtidy-dev libxslt1-dev libicu-dev libpsl-dev \
    libpcre2-dev

# ---------- 2. libuv 1.49 ----------
RUN wget -q https://github.com/libuv/libuv/archive/v1.49.0.tar.gz \
    && tar -xf v1.49.0.tar.gz \
    && cd libuv-1.49.0 && mkdir build && cd build \
    && cmake .. -G Ninja -DCMAKE_BUILD_TYPE=Release -DBUILD_TESTING=OFF \
    && ninja && ninja install && ldconfig \
    && cd / && rm -rf libuv*

# ---------- 3. curl 8.10 ----------
RUN wget -q https://github.com/curl/curl/releases/download/curl-8_10_1/curl-8.10.1.tar.gz \
    && tar -xf curl-8.10.1.tar.gz \
    && cd curl-8.10.1 \
    && ./configure --prefix=/usr/local --with-openssl --enable-shared --disable-static \
    && make -j$(nproc) && make install && ldconfig \
    && cd / && rm -rf curl*

# ---------- 4. Clone repositories ----------
# Clone PHP with TrueAsync patches (global-isolation branch)
RUN git clone --depth=1 --branch=${PHP_BRANCH} https://github.com/true-async/php-src /usr/src/php-src

# Clone TrueAsync extension (global-isolation branch)
RUN git clone --depth=1 --branch=${TRUEASYNC_BRANCH} https://github.com/true-async/php-async /usr/src/php-async

# Clone NGINX Unit with TrueAsync support
RUN git clone --depth=1 --branch=${NGINX_UNIT_BRANCH} https://github.com/EdmondDantes/nginx-unit /usr/src/nginx-unit

# Clone XDEBUG with TrueAsync support
RUN git clone --depth=1 --branch=${XDEBUG_BRANCH} https://github.com/true-async/xdebug /usr/src/xdebug

# ---------- 5. Copy async extension into PHP source ----------
RUN mkdir -p /usr/src/php-src/ext/async \
    && cp -r /usr/src/php-async/* /usr/src/php-src/ext/async/

# ---------- 5.1. Fix Windows line endings ----------
RUN dos2unix /usr/src/php-src/ext/async/config.m4 || true

# ---------- 6. Configure & build PHP ----------
WORKDIR /usr/src/php-src

RUN ./buildconf --force && \
    ./configure \
    --prefix=/usr/local \
    --enable-embed \
    --with-pdo-mysql=mysqlnd --with-mysqli=mysqlnd \
    --with-pgsql --with-pdo-pgsql --with-pdo-sqlite \
    --without-pear \
    --enable-gd --with-jpeg --with-webp --with-freetype \
    --enable-exif --with-zip --with-zlib \
    --enable-soap --enable-xmlreader --with-xsl --with-tidy --with-libxml \
    --enable-sysvsem --enable-sysvshm --enable-shmop --enable-pcntl \
    --with-readline \
    --enable-mbstring --with-curl --with-gettext \
    --enable-sockets --with-bz2 --with-openssl --with-gmp \
    --enable-bcmath --enable-calendar --enable-ftp --with-enchant \
    --enable-sysvmsg --with-ffi --enable-dba --with-lmdb --with-gdbm \
    --enable-snmp --enable-intl --with-ldap --with-ldap-sasl \
    --enable-werror \
    --with-config-file-path=/etc --with-config-file-scan-dir=/etc/php.d \
    --disable-debug --enable-zts \
    --enable-async && \
    make -j$(nproc) && make install

# Verify phpize is available
RUN phpize --version && php-config --version

# ---------- 7. Build XDEBUG extension ----------
WORKDIR /usr/src/xdebug

RUN phpize && \
    ./configure && \
    make -j$(nproc) && \
    make install

# ---------- 8. PHP configuration ----------
RUN mkdir -p /etc/php.d && echo "opcache.enable_cli=1" > /etc/php.d/opcache.ini

# Configure XDEBUG
RUN echo "zend_extension=xdebug.so" > /etc/php.d/xdebug.ini && \
    echo "xdebug.mode=debug" >> /etc/php.d/xdebug.ini && \
    echo "xdebug.start_with_request=yes" >> /etc/php.d/xdebug.ini && \
    echo "xdebug.client_host=host.docker.internal" >> /etc/php.d/xdebug.ini && \
    echo "xdebug.client_port=9009" >> /etc/php.d/xdebug.ini && \
    echo "xdebug.log=/app/www/xdebug.log" >> /etc/php.d/xdebug.ini && \
    echo "xdebug.log_level=7" >> /etc/php.d/xdebug.ini

# ---------- 9. Build NGINX Unit ----------
WORKDIR /usr/src/nginx-unit

# Configure and build Unit core
RUN ./configure && make -j$(nproc) && make install

# ---------- 10. Build PHP module for NGINX Unit ----------
RUN ./configure php && make php && make php-install

# ---------- 11. Verify module was installed ----------
RUN ls -la /usr/local/lib/unit/modules/

# Copy example configuration files to a temporary location for extraction
RUN mkdir -p /tmp/examples && \
    cp /usr/src/nginx-unit/src/true-async-php/entrypoint.php /tmp/examples/entrypoint.php 2>/dev/null || \
    echo '<?php\necho "Hello from TrueAsync PHP on NGINX Unit!\n";' > /tmp/examples/entrypoint.php

RUN cp /usr/src/nginx-unit/src/true-async-php/unit-config.json /tmp/examples/unit-config.json 2>/dev/null || \
    echo '{\n  "listeners": {\n    "*:8080": {\n      "pass": "applications/trueasync"\n    }\n  },\n  "applications": {\n    "trueasync": {\n      "type": "php",\n      "root": "/app/www",\n      "script": "entrypoint.php"\n    }\n  }\n}' > /tmp/examples/unit-config.json

# ---------- RUNTIME STAGE ----------
FROM ubuntu:24.04 AS runtime

# Install only runtime dependencies + MySQL 8.0
RUN apt-get update && apt-get install -y --no-install-recommends \
    libxml2 libssl3 libargon2-1 \
    libcurl4 libedit2 libreadline8 \
    libsodium23 libsqlite3-0 libonig5 libzip4 \
    libpng16-16 libjpeg8 libwebp7 libfreetype6 \
    libgmp10 libldap2 libsasl2-2 libpq5 \
    libmysqlclient21 libbz2-1.0 libenchant-2-2 \
    libffi8 libgdbm6 liblmdb0 libsnmp40 \
    libtidy5deb1 libxslt1.1 libicu74 libpsl5 \
    libpcre2-8-0 \
    ca-certificates curl \
    mysql-server-8.0 \
    && rm -rf /var/lib/apt/lists/*

# Create unit user and group with fixed UID/GID
RUN groupadd -r -g 999 unit && useradd -r -g unit -u 999 unit

# Create mysql directories and set permissions
RUN mkdir -p /var/run/mysqld /var/lib/mysql /var/log/mysql \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql /var/log/mysql \
    && chmod 755 /var/run/mysqld

# Copy built PHP and libraries from builder
COPY --from=builder /usr/local /usr/local
COPY --from=builder /etc/php.d /etc/php.d

# Copy example files for reference (users can extract these)
COPY --from=builder /tmp/examples /usr/local/share/unit/examples

# Create runtime directories with proper permissions
RUN mkdir -p /usr/local/var/run/unit /usr/local/var/lib/unit /usr/local/var/log/unit \
    /app/www \
    && chown -R unit:unit /usr/local/var/run/unit /usr/local/var/lib/unit /usr/local/var/log/unit /app

# Update library cache
RUN ldconfig

# Set PATH
ENV PATH="/usr/local/bin:/usr/local/sbin:$PATH"

# Verify installations
RUN php -v && unitd --version

# Verify PHP module exists
RUN ls -la /usr/local/lib/unit/modules/ && \
    test -f /usr/local/lib/unit/modules/php.unit.so || \
    (echo "ERROR: PHP module not found!" && exit 1)

WORKDIR /app

# MySQL environment variables (can be overridden)
ENV MYSQL_ROOT_PASSWORD=root \
    MYSQL_DATABASE=trueasync \
    MYSQL_USER=trueasync \
    MYSQL_PASSWORD=trueasync

# Expose HTTP, MySQL and XDEBUG ports
EXPOSE 8080 3306 9009

# Copy startup script
COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

# Define volumes for application, configuration and MySQL data
VOLUME ["/app/www", "/var/lib/mysql"]

# Note: Running as root to manage both MySQL and NGINX Unit services
# MySQL requires specific user permissions which are handled in the startup script

# Start using startup script
CMD ["/app/start.sh"]
