#!/bin/sh

echo "\n${COLOR_GREEN}Шаг 1. Установка программного обеспечения и ISPManager. После установки необходимо авторизоваться в панели управления, активировать лицензию ISPManager и перезагрузить сервер ${COLOR_NORMAL}\n\n"

systemctl stop apparmor
systemctl disable apparmor
apt remove -y --purge apparmor
apt autoremove -y
apt update
apt upgrade -y
apt install -y wget curl mc net-tools apt-utils apt-transport-https tuned lm-sensors aptitude

mkdir /etc/tuned/my-profile
cp $COREDIR/config/tuned.conf /etc/tuned/my-profile/tuned.conf
tuned-adm profile my-profile
echo "

vm.swappiness = 10
vm.vfs_cache_pressure = 50
vm.overcommit_memory = 1
net.ipv6.conf.all.disable_ipv6 = 1
" >> /etc/sysctl.conf
sysctl -p

echo "\n"
read -p "${COLOR_GREEN}Укажите имя сервера (вида srv01.MY_DOMAIN.ru). Имя сервера будет прописано в файлах /etc/hostname /etc/hosts и доступно для команды hostname --fqdn: ${COLOR_NORMAL}" sname
echo $sname > /etc/hostname
hostnamectl set-hostname $sname
hosts=$(cat /etc/hosts | grep "$sname")
if [ ! -n "$hosts" ]; then
	echo "\n127.0.1.1 $sname\n" >> /etc/hosts
fi

apt install -y ca-certificates curl gnupg
mkdir -p /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
NODE_MAJOR=22
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_MAJOR.x nodistro main" | tee /etc/apt/sources.list.d/nodesource.list
apt update
apt install -y psmisc pv htop iotop iftop nethogs iptstate apachetop dnstop powertop atop nodejs git-core jpegoptim optipng pngquant gifsicle webp autoconf gcc make libzstd1 libzstd-dev zstd libpcre2-dev libssl-dev linux-headers-generic linux-headers-generic build-essential catdoc poppler-utils poppler-data pkg-config libbrotli-dev libcurl4-openssl-dev lz4 liblz4-1 liblz4-dev sqlite3 libsqlite3-dev libc-ares-dev libc-ares2 liburing2 liburing-dev

npm install yarn npm-check-updates gyp node-gyp node-pre-gyp -g

echo "\n"
read -p "${COLOR_GREEN}После нажатия enter выберите необходимые языки системы и укажите язык по умолчанию (например для русского языка - ru_RU.UTF-8)${COLOR_NORMAL}" tmp
dpkg-reconfigure locales

wget -O /opt/install.ispmanager.sh http://download.ispmanager.com/install.sh

echo "\n"
read -p "${COLOR_GREEN}Установить сервер базы данных mysql? При отрицательном ответе будет установлен сервер базы данных mariadb (y/N): ${COLOR_NORMAL}" server
if [ "$server" = "y" ] || [ "$server" = "Y" ]; then
	mysqltype="mysql"
else
	mysqltype="mariadb"
fi

read -p "${COLOR_GREEN}Тип системной базы данных ISPManager (MySql/Sqlite). Установить mysql(y/N)?: ${COLOR_NORMAL}" dbtype
if [ "$dbtype" = "y" ] || [ "$dbtype" = "Y" ]; then
	dbtype="mysql"
else
	dbtype="sqlite"
fi

sh /opt/install.ispmanager.sh --ispmgr6 --ignore-hostname --dbtype $dbtype --mysql-server $mysqltype --release stable ISPmanager-Lite

## Устанавливаем PHP83 для инсталлятора
/usr/local/mgr5/sbin/mgrctl -m ispmgr -o text feature.edit elid=altphp83 package_ispphp83_fpm=on package_ispphp83_mod_apache=off packagegroup_altphp83gr=ispphp83 sok=ok clicked_button=ok
