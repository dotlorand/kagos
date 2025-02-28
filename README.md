# Installáció (debian):

Webserver:
```bash
apt install apache2 
```

PHP:
```bash
apt install php libapache2-mod-php php-mysql php-gd
```

MySQL server:
```bash
apt install mysql-server
mysql_secure_installation

apt install phpmyadmin
```

# Konfiguráció:

Virtual host config:
```bash
nano /etc/apache2/sites-available/kagos.conf
```

```
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

Virtual host és mod_rewrite (.htaccess) engedélyezése:
```bash
a2ensite kagos.conf
a2enmod rewrite
```

### Mysql config:

MySQL (mariadb) belépési adatok létrehozása:
```bash
mysql -u root

ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

Adatbázis:
...

### Dependencies

composer.phar letöltése:
```bash
curl -sS https://getcomposer.org/installer | php
```

Phpdotenv hozzáadása
```bash
php composer.phar require vlucas/phpdotenv
```