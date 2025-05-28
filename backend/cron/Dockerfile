# Stage 1: Get MySQL tools
FROM mysql:8.0 as mysql-tools

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

# Copy entire MySQL installation
COPY --from=mysql-tools /usr/bin/mysql* /usr/bin/
COPY --from=mysql-tools /usr/lib/x86_64-linux-gnu/ /usr/lib/

# Create backup directories
RUN mkdir -p /backups/temp /backups/encrypted

# Copy the entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set working directory
WORKDIR /

# Use our custom entrypoint
ENTRYPOINT ["/entrypoint.sh"]
