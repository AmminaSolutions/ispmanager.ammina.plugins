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
apt install -y psmisc pv htop iotop iftop nethogs iptstate apachetop dnstop powertop atop nodejs git-core jpegoptim optipng pngquant gifsicle webp autoconf gcc make libzstd1 libzstd-dev zstd libpcre2-dev libssl-dev linux-headers-generic linux-headers-generic build-essential catdoc poppler-utils poppler-data

npm install yarn npm-check-updates gyp node-gyp node-pre-gyp -g

echo "\n"
read -p "${COLOR_GREEN}После нажатия enter выберите необходимые языки системы и укажите язык по умолчанию (например для русского языка - ru_RU.UTF-8)${COLOR_NORMAL}" tmp
dpkg-reconfigure locales

wget -O /opt/install.ispmanager.sh http://download.ispmanager.com/install.sh
sh /opt/install.ispmanager.sh --ispmgr6 --ignore-hostname --dbtype mysql --release stable ISPmanager-Lite

## Устанавливаем PHP83 для инсталлятора
/usr/local/mgr5/sbin/mgrctl -m ispmgr -o text feature.edit elid=altphp83 package_ispphp83_fpm=on package_ispphp83_mod_apache=off packagegroup_altphp83gr=ispphp83 sok=ok clicked_button=ok
