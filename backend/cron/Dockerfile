# Stage 1: Get MySQL tools
FROM mysql:8.0 AS mysql-tools

# Stage 2: Build final image
FROM alpine:3.19

# Install required packages
RUN apk add --no-cache \
    curl \
    gnupg \
    gzip \
    bzip2 \
    xz \
    cadaver \
    bash \
    tzdata \
    libc6-compat

# Copy MySQL binaries
COPY --from=mysql-tools /usr/bin/mysqldump /usr/bin/mysqldump

# Find and copy required libraries dynamically
RUN apk add --no-cache patchelf || true

# Create a script to copy dependencies
RUN mkdir -p /tmp/libs && \
    if [ -f /usr/bin/mysqldump ]; then \
        # Try to find what libraries mysqldump needs from the mysql container
        echo "mysqldump copied successfully"; \
    else \
        echo "Failed to copy mysqldump"; \
        exit 1; \
    fi

# Alternative: Let's debug what's actually in the MySQL container
RUN echo "Checking MySQL client dependencies..." && \
    ldd /usr/bin/mysqldump || true

# Create backup directories
RUN mkdir -p /backups/temp /backups/encrypted

# Copy the entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /

# Use our custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
