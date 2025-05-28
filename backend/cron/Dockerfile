FROM debian:bullseye-slim

# Install MySQL client and other tools
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    curl \
    gnupg \
    gzip \
    bzip2 \
    xz-utils \
    cadaver \
    bash \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Create backup directories  
RUN mkdir -p /backups/temp /backups/encrypted

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /
ENTRYPOINT ["/entrypoint.sh"]
