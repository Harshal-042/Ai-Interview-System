#!/bin/bash

set -e

echo "Starting AI Interview System..."

# Create required folders
mkdir -p /var/www/html/uploads/tts
mkdir -p /var/www/html/uploads/processing
mkdir -p /var/www/html/uploads/interviews

chown -R www-data:www-data /var/www/html/uploads
chmod -R 775 /var/www/html/uploads

# Start Ollama in background
echo "Starting Ollama..."
ollama serve > /tmp/ollama.log 2>&1 &

# Wait for Ollama to start
sleep 5

# Download the AI model
echo "Checking Ollama model..."
ollama pull llama3.2:3b

# Render port
PORT=${PORT:-10000}

echo "Starting Apache on port $PORT"

sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/000-default.conf

apache2-foreground