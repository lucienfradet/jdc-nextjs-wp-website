# Stage 1: Get MySQL tools
FROM mysql:8.0 AS mysql-tools

# Stage 2: Build final image
FROM alpine:3.19

# Install required packages (no mysql-client)
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
COPY --from=mysql-tools /usr/bin/mysql /usr/bin/mysql

# Copy only the specific libraries we need (check if they exist first)
COPY --from=mysql-tools /usr/lib/x86_64-linux-gnu/libmysqlclient.so* /usr/lib/ 2>/dev/null || true
COPY --from=mysql-tools /lib/x86_64-linux-gnu/libssl.so* /usr/lib/ 2>/dev/null || true
COPY --from=mysql-tools /lib/x86_64-linux-gnu/libcrypto.so* /usr/lib/ 2>/dev/null || true

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
