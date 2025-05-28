# Use MySQL 8.0 as base for reliable MySQL tools
FROM mysql:8.0

# Install additional packages needed for backup operations
RUN apt-get update && apt-get install -y \
    curl \
    gnupg \
    gzip \
    bzip2 \
    xz-utils \
    cadaver \
    cron \
    && rm -rf /var/lib/apt/lists/*

# Create backup directories
RUN mkdir -p /backups/temp /backups/encrypted

# Copy the entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /

# Use our custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
