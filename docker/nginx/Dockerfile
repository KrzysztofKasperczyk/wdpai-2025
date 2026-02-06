
# Oficjalny obraz Nginx w wersji 1.17.8 oparty na Alpine Linux
FROM nginx:1.17.8-alpine

# Copy the public directory
# COPY ./public/ /app/public/
COPY . /app/

# Copy the nginx config file
COPY ./docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf