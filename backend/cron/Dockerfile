# Use Alpine 3.19 for stability  
FROM alpine:3.19

# Install required packages (keep mysql-client for dependencies)
RUN apk add --no-cache \
    curl \
    gnupg \
    gzip \
    bzip2 \
    xz \
    cadaver \
    bash \
    tzdata \
    && rm -rf /var/cache/apk/*

# Copy real MySQL client tools from official MySQL image
COPY --from=mysql:8.0 /usr/bin/mysqldump /usr/bin/mysqldump
COPY --from=mysql:8.0 /usr/bin/mysql /usr/bin/mysql
COPY --from=mysql:8.0 /usr/lib/x86_64-linux-gnu/libssl.so.* /usr/lib/
COPY --from=mysql:8.0 /usr/lib/x86_64-linux-gnu/libcrypto.so.* /usr/lib/

# Create backup directories
RUN mkdir -p /backups/temp /backups/encrypted

# Copy the entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /

# Use our custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
