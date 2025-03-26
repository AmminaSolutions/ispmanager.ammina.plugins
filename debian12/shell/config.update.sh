#!/bin/sh

echo "\n${COLOR_GREEN}Обновление настроек программного обеспечения и ISPManager. Установка плагинов и дополнительного ПО${COLOR_NORMAL}\n\n"

$PHP $ROOT/debian12/installer/config.update.php
