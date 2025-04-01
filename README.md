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

> TLS-hez port 443 és certification kell <br>
> Segítség: https://serverfault.com/questions/744960/configuring-ssl-with-virtual-hosts-under-apache-and-centos

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
-- Generation Time: Apr 01, 2025 at 09:03 PM
-- Server version: 10.11.11-MariaDB-0+deb12u1
-- PHP Version: 8.2.28

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
-- Dumping data for table `csapatok`
--

INSERT INTO `csapatok` (`id`, `letrehozva`, `nev`, `allamforma`, `kontinens`, `bevetel`, `termeles`, `kutatasi_pontok`, `diplomaciai_pontok`, `katonai_pontok`, `bankok`, `gyarak`, `egyetemek`, `laktanyak`, `politikak`) VALUES
('f37c03f6-0f34-11f0-b730-00163e202b7e', '2025-04-01 20:07:34', 'dsadsaads', 'demokratikus', 'dsadsaads', 18, 39, 0, 0, 0, 6, 13, 0, 0, ''),
('f5ae11d1-0f34-11f0-b730-00163e202b7e', '2025-04-01 20:07:38', 'dsadsaadsdsadsaads', 'test', 'fds', 3, 3, 0, 0, 0, 1, 1, 0, 0, ''),
('f7a68dac-0f34-11f0-b730-00163e202b7e', '2025-04-01 20:07:41', '123', 'demokratikus', '123', 0, 0, 0, 72, 72, 0, 0, 0, 24, '');

-- --------------------------------------------------------

--
-- Table structure for table `jatekok`
--

CREATE TABLE `jatekok` (
  `id` int(11) NOT NULL,
  `current_round` int(11) NOT NULL DEFAULT 0,
  `phase` enum('init','active') NOT NULL DEFAULT 'init',
  `last_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- Dumping data for table `jatekok`
--

INSERT INTO `jatekok` (`id`, `current_round`, `phase`, `last_update`) VALUES
(4, 3, 'active', '2025-04-01 20:11:02');

-- --------------------------------------------------------

--
-- Table structure for table `jatekok_history`
--

CREATE TABLE `jatekok_history` (
  `id` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `team_id` varchar(36) NOT NULL,
  `nev` varchar(255) DEFAULT NULL,
  `allamforma` varchar(50) DEFAULT NULL,
  `kontinens` varchar(50) DEFAULT NULL,
  `bevetel` int(11) DEFAULT NULL,
  `termeles` int(11) DEFAULT NULL,
  `kutatasi_pontok` int(11) DEFAULT NULL,
  `diplomaciai_pontok` int(11) DEFAULT NULL,
  `katonai_pontok` int(11) DEFAULT NULL,
  `bankok` int(11) DEFAULT NULL,
  `gyarak` int(11) DEFAULT NULL,
  `egyetemek` int(11) DEFAULT NULL,
  `laktanyak` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

--
-- Dumping data for table `jatekok_history`
--

INSERT INTO `jatekok_history` (`id`, `round`, `team_id`, `nev`, `allamforma`, `kontinens`, `bevetel`, `termeles`, `kutatasi_pontok`, `diplomaciai_pontok`, `katonai_pontok`, `bankok`, `gyarak`, `egyetemek`, `laktanyak`, `created_at`) VALUES
(53, 0, 'f37c03f6-0f34-11f0-b730-00163e202b7e', 'dsadsaads', 'demokratikus', 'dsadsaads', 0, 0, 0, 0, 0, 6, 13, 0, 0, '2025-04-01 20:10:50'),
(54, 0, 'f5ae11d1-0f34-11f0-b730-00163e202b7e', 'dsadsaadsdsadsaads', 'test', 'fds', 0, 0, 0, 0, 0, 1, 1, 0, 0, '2025-04-01 20:10:50'),
(55, 0, 'f7a68dac-0f34-11f0-b730-00163e202b7e', '123', 'demokratikus', '123', 0, 0, 0, 0, 0, 0, 0, 0, 24, '2025-04-01 20:10:50'),
(56, 1, 'f37c03f6-0f34-11f0-b730-00163e202b7e', 'dsadsaads', 'demokratikus', 'dsadsaads', 6, 13, 0, 0, 0, 6, 13, 0, 0, '2025-04-01 20:10:58'),
(57, 1, 'f5ae11d1-0f34-11f0-b730-00163e202b7e', 'dsadsaadsdsadsaads', 'test', 'fds', 1, 1, 0, 0, 0, 1, 1, 0, 0, '2025-04-01 20:10:58'),
(58, 1, 'f7a68dac-0f34-11f0-b730-00163e202b7e', '123', 'demokratikus', '123', 0, 0, 0, 24, 24, 0, 0, 0, 24, '2025-04-01 20:10:58'),
(59, 2, 'f37c03f6-0f34-11f0-b730-00163e202b7e', 'dsadsaads', 'demokratikus', 'dsadsaads', 12, 26, 0, 0, 0, 6, 13, 0, 0, '2025-04-01 20:11:02'),
(60, 2, 'f5ae11d1-0f34-11f0-b730-00163e202b7e', 'dsadsaadsdsadsaads', 'test', 'fds', 2, 2, 0, 0, 0, 1, 1, 0, 0, '2025-04-01 20:11:02'),
(61, 2, 'f7a68dac-0f34-11f0-b730-00163e202b7e', '123', 'demokratikus', '123', 0, 0, 0, 48, 48, 0, 0, 0, 24, '2025-04-01 20:11:02'),
(62, 3, 'f37c03f6-0f34-11f0-b730-00163e202b7e', 'dsadsaads', 'demokratikus', 'dsadsaads', 18, 39, 0, 0, 0, 6, 13, 0, 0, '2025-04-01 20:11:02'),
(63, 3, 'f5ae11d1-0f34-11f0-b730-00163e202b7e', 'dsadsaadsdsadsaads', 'test', 'fds', 3, 3, 0, 0, 0, 1, 1, 0, 0, '2025-04-01 20:11:02'),
(64, 3, 'f7a68dac-0f34-11f0-b730-00163e202b7e', '123', 'demokratikus', '123', 0, 0, 0, 72, 72, 0, 0, 0, 24, '2025-04-01 20:11:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `csapatok`
--
ALTER TABLE `csapatok`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jatekok`
--
ALTER TABLE `jatekok`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jatekok_history`
--
ALTER TABLE `jatekok_history`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jatekok`
--
ALTER TABLE `jatekok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jatekok_history`
--
ALTER TABLE `jatekok_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```