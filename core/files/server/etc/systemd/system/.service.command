systemctl daemon-reload

systemctl enable rc-local

echo "#!/bin/bash\n" > /etc/rc.local

chmod +x /etc/rc.local
