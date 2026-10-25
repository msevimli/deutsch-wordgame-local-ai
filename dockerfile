# ---------- step 1: convert NLLB model to CTranslate2 format ----------
FROM python:3.11-slim AS converter

RUN pip install --no-cache-dir ctranslate2 "transformers[sentencepiece]" torch \
    --extra-index-url https://download.pytorch.org/whl/cpu

RUN ct2-transformers-converter \
    --model facebook/nllb-200-distilled-600M \
    --quantization int8 \
    --output_dir /models/nllb-600m-ct2


RUN python -c "from transformers import AutoTokenizer; AutoTokenizer.from_pretrained('facebook/nllb-200-distilled-600M').save_pretrained('/models/nllb-600m-ct2')"


# ---------- step 2: Base image ----------
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive
ENV PHPMYADMIN_VERSION=5.2.1

# Web + DB + Python
RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    php \
    php-mysql \
    libapache2-mod-php \
    mysql-server \
    unzip \
    wget \
    curl \
    ca-certificates \
    git \
    nano \
    php-zip \
    php-mbstring \
    php-bcmath \
    php-curl \
    php-xml \
    php-intl \
    php-gd \
    php-sqlite3 \
    python3 \
    python3-venv && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

# Translate Service for separate venv (doesn't touch system Python)
RUN python3 -m venv /opt/venv && \
    /opt/venv/bin/pip install --no-cache-dir \
    ctranslate2 "transformers[sentencepiece]" fastapi "uvicorn[standard]"

# Converted model
COPY --from=converter /models /models

# Apache modules
RUN a2enmod php8.3 && a2enmod rewrite

# MySQL initialization
RUN mkdir -p /var/run/mysqld && \
    chown -R mysql:mysql /var/run/mysqld && \
    chmod 755 /var/run/mysqld

# phpMyAdmin
RUN wget https://files.phpmyadmin.net/phpMyAdmin/${PHPMYADMIN_VERSION}/phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.zip && \
    unzip phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.zip && \
    mv phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages /var/www/phpmyadmin && \
    rm phpMyAdmin-${PHPMYADMIN_VERSION}-all-languages.zip && \
    chown -R www-data:www-data /var/www/phpmyadmin

# Translate Service
COPY app.py /app/app.py

# Configuration files
COPY phpmyadmin.conf /etc/apache2/sites-available/phpmyadmin.conf
COPY php.ini /etc/php/8.3/apache2/php.ini
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
RUN a2ensite phpmyadmin.conf

COPY start.sh /start.sh
RUN chmod +x /start.sh

WORKDIR /var/www/html

# 80: web, 3306: MySQL, 8000: Translate API
EXPOSE 80 3306 8000

CMD ["/start.sh"]