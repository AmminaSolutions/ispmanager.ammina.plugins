# ISPManager with Ammina plugins
ISPManager с автоматической установкой и настройкой и плагинами Ammina

## Установка

### Для всех поддерживаемых операционных систем:
```shell

apt install -y git-core
git clone https://github.com/AmminaSolutions/ispmanager.ammina.plugins.git /opt/ispmanager.ammina.plugins
cd /opt/ispmanager.ammina.plugins
sh step1.sh

```
В процессе установки будет задан вопрос об имени сервера. Необходимо указать полное имя сервера. Например:
```
srv01.ammina-isp.ru
```

### Debian 12

### Ubuntu 22.04
Дополнительно будет задан вопрос об устанавливаемом сервере базы данных. По умолчанию будет установлен сервер MySQL. При отрицательном ответе на вопрос - MariaDB.

___Данная возможность доступно только для операционных систем Ubuntu___

