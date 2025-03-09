#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
until mysqladmin ping -h"localhost" -u"root" -p"$MYSQL_ROOT_PASSWORD" --silent; do
  sleep 1
done

echo "Granting additional permissions to MySQL user ${MYSQL_USER}..."
mysql -u root -p"$MYSQL_ROOT_PASSWORD" <<-EOSQL
-- Delete anonymous users
DELETE FROM mysql.user WHERE User='';

-- Disable remote root login
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Explicitly create root@127.0.0.1 user
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '$MYSQL_ROOT_PASSWORD';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;

-- Remove test database
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Ensure proper permissions for application user
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';

-- Grant GLOBAL permissions required specifically for Prisma migrations
GRANT CREATE, DROP, REFERENCES, ALTER ON *.* TO '${MYSQL_USER}'@'%';

-- Remove privileges that the app doesn't need
REVOKE SUPER, PROCESS, FILE, RELOAD, SHUTDOWN, REPLICATION CLIENT, REPLICATION SLAVE 
ON *.* FROM '${MYSQL_USER}'@'%';

-- Apply changes
FLUSH PRIVILEGES;
EOSQL

echo "MySQL user permissions configured successfully!"
