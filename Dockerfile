FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    python3 \
    python3-pip \
    python3-venv \
    curl \
    wget \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Ollama
RUN curl -fsSL https://ollama.com/install.sh | sh
    
# Install Python AI packages
RUN pip3 install --no-cache-dir --break-system-packages \
    -r /var/www/html/requirements.txt

# Enable Apache rewrite module
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Create required upload folders
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

# Permissions
RUN chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 775 /var/www/html/uploads

# Copy startup script
COPY start.sh /start.sh

RUN chmod +x /start.sh

EXPOSE 10000

CMD ["/start.sh"]