#!/bin/bash
set -e

echo "=== NGINX Unit with TrueAsync PHP + MySQL 8.0 ==="
echo "PHP Version: $(php -v | head -n1)"
echo "Unit Version: $(unitd --version 2>&1)"
echo "MySQL Version: $(mysqld --version)"
echo ""

# Initialize MySQL if needed
if [ ! -d "/var/lib/mysql/mysql" ]; then
  echo "Initializing MySQL database..."
  mysqld --initialize-insecure --user=mysql --datadir=/var/lib/mysql
fi

# Start MySQL
echo "Starting MySQL..."
mysqld --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock &
MYSQL_PID=$!

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
for i in {1..30}; do
  if mysqladmin ping -h localhost --silent 2>/dev/null; then
    echo "MySQL is ready"
    break
  fi
  sleep 1
done

# Configure MySQL (set root password, create database and user)
if [ -n "$MYSQL_ROOT_PASSWORD" ]; then
  mysql -u root <<-EOSQL
    ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
    CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE};
    CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_PASSWORD}';
    CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED WITH mysql_native_password BY '${MYSQL_PASSWORD}';
    GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'localhost';
    GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE}.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL
  echo "MySQL configured: database=${MYSQL_DATABASE}, user=${MYSQL_USER}"
fi

# Import database dump if exists and database is empty
DB_DUMP="/app/www/db.sql"
if [ -f "$DB_DUMP" ]; then
  echo "Found database dump at $DB_DUMP"
  TABLE_COUNT=$(mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} -e "SHOW TABLES;" 2>/dev/null | wc -l)
  if [ "$TABLE_COUNT" -le 1 ]; then
    echo "Importing database dump..."
    mysql -u root -p${MYSQL_ROOT_PASSWORD} ${MYSQL_DATABASE} < "$DB_DUMP"
    echo "Database dump imported successfully"
  else
    echo "Database already contains tables, skipping import"
  fi
else
  echo "No database dump found at $DB_DUMP"
fi

# Configuration paths
CONFIG_FILE="${UNIT_CONFIG_FILE:-/app/www/unit-config.json}"

echo "Configuration: $CONFIG_FILE"
echo "Web Root: /app/www"
echo ""

# Check if configuration file exists
if [ ! -f "$CONFIG_FILE" ]; then
  echo "WARNING: Configuration file not found at $CONFIG_FILE"
  echo "Using default configuration..."
  CONFIG_FILE="/usr/local/share/unit/examples/unit-config.json"
fi

# Start Unit daemon
echo "Starting NGINX Unit..."
unitd --no-daemon &
UNITD_PID=$!

# Wait for control socket
echo "Waiting for control socket..."
for i in {1..10}; do
  if [ -S /usr/local/var/run/unit/control.unit.sock ]; then
    echo "Control socket ready"
    break
  fi
  sleep 1
done

if [ ! -S /usr/local/var/run/unit/control.unit.sock ]; then
  echo "ERROR: Control socket not available after 10 seconds"
  exit 1
fi

# Load configuration
echo "Loading configuration from $CONFIG_FILE..."
RESPONSE=$(curl -X PUT --data-binary @"$CONFIG_FILE" \
  --unix-socket /usr/local/var/run/unit/control.unit.sock \
  http://localhost/config 2>&1)

echo "Response: $RESPONSE"

if echo "$RESPONSE" | grep -q "error"; then
  echo "WARNING: Configuration failed to load!"
  echo "Please check your unit-config.json file"
  echo "Unit is still running, you can configure it manually"
else
  echo "Configuration loaded successfully"
fi

echo ""
echo "========================================"
echo "Services are ready!"
echo "NGINX Unit: http://0.0.0.0:8080"
echo "MySQL: localhost:3306"
echo "  - Database: ${MYSQL_DATABASE}"
echo "  - User: ${MYSQL_USER}"
echo "========================================"
echo ""

# Wait for both processes
wait -n $UNITD_PID $MYSQL_PID
exit $?
