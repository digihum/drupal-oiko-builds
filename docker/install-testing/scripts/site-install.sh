#!/bin/bash -xe

cd /app

php --version

composer --version

# Install the composer dependencies.
composer install --no-dev

# Wait for the DB server to be online.
dockerize -wait tcp://database:3306 -timeout 1m

cd webroot

# Run the site install command.
drush site-install --yes --debug oiko_profile --db-url='mysql://root:root@database:3306/oiko'

# See if we can bootstrap.
drush status

# Make sure we can get a login link.
drush uli

# Log any emails that have been sent.
cat /var/log/php-mail.log || true
