mkdir -p /var/lib/nginx/body

chown -R www-data /var/lib/nginx

chmod 700 /var/lib/nginx

chmod 700 /var/lib/nginx/body

service nginx restart
