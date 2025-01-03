#!/bin/sh

# Colors
COLOR_NORMAL="$(tput sgr0 2>/dev/null || echo '')"
COLOR_GREEN="$(tput setaf 2 2>/dev/null || echo '')"
COLOR_RED="$(tput setaf 1 2>/dev/null || echo '')"
FONT_BOLD="$(tput bold 2>/dev/null || echo '')"
FONT_ITALIC="$(tput sitm 2>/dev/null || echo '')"

detect_os() {
	osrelease="/etc/os-release"

	if [ ! -f "$osrelease" ]; then
		echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Невозможно определить операционную систему!"
		exit 1
	fi

	. "$osrelease"
	if [ -n "${ID}" ]; then
		osid="$ID"
	else
		osid="$ID_LIKE"
	fi
	if [ -z "$osid" ]; then
		echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Невозможно определить операционную систему!"
		exit 1
	fi

	osversion="$VERSION_ID"

	osid_debian=$(echo "$osid" | grep "debian")
	osid_ubuntu=$(echo "$osid" | grep "ubuntu")
	if [ -n "$osid_debian" ]; then
		if [ "$osversion" = "12" ]; then
			useos="debian12"
		else
			echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Неизвестная операционная система: $osid, версия: $osversion"
            exit 1
		fi
	elif [ -n "$osid_ubuntu" ]; then
		if [ "$osversion" = "22.04" ]; then
			useos="ubuntu2204"
		else
			echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Неизвестная операционная система: $osid, версия: $osversion"
			exit 1
		fi
	else
		echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Неизвестная операционная система: $osid"
		exit 1
	fi
}

make_osdir() {
	COREDIR="$ROOT/core"
	if [ $useos = "debian12" ]; then
    	OSDIR="$ROOT/debian12"
    elif [ $useos = "ubuntu2204" ]; then
    	OSDIR="$ROOT/ubuntu2204"
    fi
    if [ -f "/opt/ispmanager.root" ]; then
		test=`cat /opt/ispmanager.root`
		if [ $test != $ROOT ]; then
			echo $ROOT > /opt/ispmanager.root
		fi
    else
		echo $ROOT > /opt/ispmanager.root
    fi
}

get_php() {
	PHP=/opt/php83/bin/php
	if [ ! -f $PHP ]; then
		PHP=/opt/php82/bin/php
		if [ ! -f $PHP ]; then
			echo "${COLOR_RED}Ошибка:${COLOR_NORMAL} Не установлена ни одна из версий PHP: 8.3, 8.2!"
			exit 1
		fi
	fi
	PHP="${PHP} -d disable_functions= "
}