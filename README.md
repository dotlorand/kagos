# kagos

## Installáció:

Webszerver:
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

## Konfiguráció:

(Ha nem /var/www/html a projekt gyökere)

Virtual host config:
```bash
sudo nano /etc/apache2/sites-available/kagos.conf
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