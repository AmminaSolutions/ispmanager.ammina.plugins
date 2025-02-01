#!/bin/sh

if [ ! -d /var/log/push-server ]; then
    mkdir /var/log/push-server
    chown -R www-data:www-data /var/log/push-server
fi
rm -rf /opt/push-server/node_modules

cd /opt/push-server/
pushd /opt/push-server/ 2>/dev/null
npm install --production
chown -R www-data:www-data .
popd 2>/dev/null
