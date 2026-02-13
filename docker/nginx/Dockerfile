FROM nginx:1.27-alpine

# Install OpenSSL for certificate generation
RUN apk add --no-cache openssl

# Copy custom entrypoint
COPY docker/nginx/entrypoint.sh /docker-entrypoint-custom.sh
RUN chmod +x /docker-entrypoint-custom.sh

ENTRYPOINT ["/docker-entrypoint-custom.sh"]
