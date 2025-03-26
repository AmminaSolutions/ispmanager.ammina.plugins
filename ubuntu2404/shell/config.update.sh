#!/bin/sh

echo "\n${COLOR_GREEN}Обновление настроек программного обеспечения и ISPManager. Установка плагинов и дополнительного ПО${COLOR_NORMAL}\n\n"

$PHP $ROOT/ubuntu2404/installer/config.update.php
