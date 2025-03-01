# Installáció (debian):

Webserver:
```sh
apt install apache2 
```

PHP:
```sh
apt install php libapache2-mod-php php-mysql php-gd
```

MySQL server:
```sh
apt install mysql-server
mysql_secure_installation

apt install phpmyadmin
```

# Konfiguráció:

Virtual host config:
```sh
nano /etc/apache2/sites-available/kagos.conf
```

```xml
<VirtualHost *:80>
    ServerName kagos.intra
    DocumentRoot /home/projects/kagos/

    <Directory /home/projects/kagos/>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/kagos_error.log
    CustomLog ${APACHE_LOG_DIR}/kagos_access.log combined
</VirtualHost>
```

Virtual host config és mod_rewrite (.htaccess) engedélyezése:
```sh
a2ensite kagos.conf
a2enmod rewrite
```

### Mysql config:

MySQL (mariadb) belépési adatok létrehozása:
> E szerint kell majd a .env file-t megírni
```sql
mysql -u root

ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

Adatbázis:
...

### Dependencies

composer.phar letöltése és phpdotenv hozzáadása:
```sh
curl -sS https://getcomposer.org/installer | php
php composer.phar require vlucas/phpdotenv
```