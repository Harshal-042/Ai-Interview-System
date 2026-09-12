FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    python3 \
    python3-pip \
    python3-venv \
    curl \
    wget \
    zstd \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Ollama
RUN curl -fsSL https://ollama.com/install.sh | sh

# Enable Apache rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy project files FIRST
COPY . /var/www/html/

# Install Python AI packages AFTER requirements.txt is copied
RUN pip3 install --no-cache-dir --break-system-packages \
    -r /var/www/html/requirements.txt

# Create required folders
RUN mkdir -p \
    /var/www/html/uploads/tts \
    /var/www/html/uploads/processing \
    /var/www/html/uploads/interviews \
    /opt/piper/voices

# Download Piper voice model
RUN wget -q -O /opt/piper/voices/en_US-lessac-medium.onnx \
    https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx

RUN wget -q -O /opt/piper/voices/en_US-lessac-medium.onnx.json \
    https://huggingface.co/rhasspy/piper-voices/resolve/main/en/en_US/lessac/medium/en_US-lessac-medium.onnx.json

# Set permissions
RUN chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 775 /var/www/html/uploads

# Make startup script executable
RUN chmod +x /var/www/html/start.sh

EXPOSE 10000

CMD ["/var/www/html/start.sh"]