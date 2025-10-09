# Use official PHP image with extensions
FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy all project files to container
COPY . .

# Install required PHP extensions (if needed)
RUN docker-php-ext-install pcntl

# Expose a port (Render requires it even if it's not used)
EXPOSE 8080

# Start the bot when container starts
CMD ["php", "-S", "0.0.0.0:10000", "bot.php"]
