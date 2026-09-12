#!/bin/bash

set -e

echo "Starting AI Interview System..."

# Create required folders
mkdir -p /var/www/html/uploads/tts
mkdir -p /var/www/html/uploads/processing
mkdir -p /var/www/html/uploads/interviews

# Set permissions
chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Render provides the PORT environment variable
PORT=${PORT:-10000}

echo "Starting Apache on port $PORT"

# Configure Apache to listen on Render's port
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf

sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/000-default.conf

# Start Apache
apache2-foreground