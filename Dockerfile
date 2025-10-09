# Use official PHP 8.2 image with Apache
FROM php:8.2-apache

# Copy all files to the container
COPY . /var/www/html/

# Expose port 80
EXPOSE 80

# Set working directory
WORKDIR /var/www/html/

# Optional: Enable error reporting (for debugging)
RUN echo "display_errors=On" >> /usr/local/etc/php/php.ini-development

# Start Apache server
CMD ["php", "bot.php"]
