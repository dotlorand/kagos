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
apt install mariadb-server
mysql_secure_installation

apt install phpmyadmin
```

# Konfiguráció:

### Virtual host config:
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

Virtual host file és mod_rewrite (.htaccess) engedélyezése:
```sh
a2ensite kagos.conf
a2enmod rewrite
```

### Dependencies

composer.phar letöltése és phpdotenv hozzáadása:
```sh
curl -sS https://getcomposer.org/installer | php
php composer.phar require vlucas/phpdotenv
```

### Mysql config:
> E szerint kell majd a .env file-t megírni

MySQL (mariadb) belépési adatok létrehozása:
```sql
mysql -u root

ALTER USER 'root'@'localhost' IDENTIFIED BY 'new_password';
FLUSH PRIVILEGES;
```

Tables:
```
csapatok
jatekmenet
```

Import:
```
-- phpMyAdmin SQL Dump
-- version 5.2.1deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 12, 2025 at 05:41 PM
-- Server version: 10.11.6-MariaDB-0+deb12u1
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kagos`
--

-- --------------------------------------------------------

--
-- Table structure for table `csapatok`
--

CREATE TABLE `csapatok` (
  `id` char(36) NOT NULL DEFAULT uuid(),
  `letrehozva` timestamp NOT NULL DEFAULT current_timestamp(),
  `nev` varchar(255) NOT NULL,
  `allamforma` varchar(255) NOT NULL,
  `kontinens` varchar(255) NOT NULL,
  `bevetel` int(11) NOT NULL DEFAULT 0,
  `termeles` int(11) NOT NULL DEFAULT 0,
  `kutatasi_pontok` int(11) NOT NULL DEFAULT 0,
  `diplomaciai_pontok` int(11) NOT NULL DEFAULT 0,
  `katonai_pontok` int(11) NOT NULL DEFAULT 0,
  `bankok` int(11) NOT NULL DEFAULT 0,
  `gyarak` int(11) NOT NULL DEFAULT 0,
  `egyetemek` int(11) NOT NULL DEFAULT 0,
  `laktanyak` int(11) NOT NULL DEFAULT 0,
  `politikak` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `csapatok`
--
ALTER TABLE `csapatok`
  ADD PRIMARY KEY (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```