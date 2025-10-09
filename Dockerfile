FROM php:8.2-cli
WORKDIR /app
COPY . .
EXPOSE 10000
CMD ["python3", "bot.py"]
