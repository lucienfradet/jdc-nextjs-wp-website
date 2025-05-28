# Use Alpine 3.19 for stability  
FROM alpine:3.19

# Install required packages including MySQL client
RUN apk add --no-cache \
    mysql-client \
    curl \
    gnupg \
    gzip \
    bzip2 \
    xz \
    cadaver \
    bash \
    tzdata \
    && rm -rf /var/cache/apk/*

# Create backup directories
RUN mkdir -p /backups/temp /backups/encrypted

# Copy the entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /

# Use our custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
