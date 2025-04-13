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
custom_rules
ensz_votes
ensz_winnerpoll
haboruk
jatekok
jatekok_history
```

Import:
```
-- phpMyAdmin SQL Dump
-- version 5.2.1deb1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 13, 2025 at 04:47 PM
-- Server version: 10.11.11-MariaDB-0+deb12u1
-- PHP Version: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

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
  `politikak` text DEFAULT NULL,
  `research_era` int(11) NOT NULL DEFAULT 1,
  `research_found` int(11) NOT NULL DEFAULT 0,
  `winner` int(11) NOT NULL DEFAULT 0,
  `alliance` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_rules`
--

CREATE TABLE `custom_rules` (
  `id` int(11) NOT NULL,
  `team_id` char(36) NOT NULL,
  `field` varchar(50) NOT NULL,
  `amount` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ensz_votes`
--

CREATE TABLE `ensz_votes` (
  `id` int(11) NOT NULL,
  `round` int(11) NOT NULL,
  `proposal_index` int(11) NOT NULL,
  `team_id` char(36) NOT NULL,
  `vote_option` enum('yes','no','skip','finalized','applied','targeted') NOT NULL,
  `vote_count` int(11) NOT NULL,
  `target` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ensz_winnerpoll`
--

CREATE TABLE `ensz_winnerpoll` (
  `id` int(11) NOT NULL,
  `candidate_team_id` char(36) NOT NULL,
  `yes_votes` int(11) NOT NULL DEFAULT 0,
  `no_votes` int(11) NOT NULL DEFAULT 0,
  `status` enum('ongoing','final') NOT NULL DEFAULT 'ongoing',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- --------------------------------------------------------

--
-- Table structure for table `haboruk`
--

CREATE TABLE `haboruk` (
  `id` int(11) NOT NULL,
  `winner_id` varchar(36) NOT NULL,
  `loser_id` varchar(36) NOT NULL,
  `conquered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

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
-- Indexes for dumped tables
--

--
-- Indexes for table `csapatok`
--
ALTER TABLE `csapatok`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_rules`
--
ALTER TABLE `custom_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `team_id` (`team_id`);

--
-- Indexes for table `ensz_votes`
--
ALTER TABLE `ensz_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_round_proposal_team` (`round`,`proposal_index`,`team_id`);

--
-- Indexes for table `ensz_winnerpoll`
--
ALTER TABLE `ensz_winnerpoll`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `haboruk`
--
ALTER TABLE `haboruk`
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
-- AUTO_INCREMENT for table `custom_rules`
--
ALTER TABLE `custom_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ensz_votes`
--
ALTER TABLE `ensz_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ensz_winnerpoll`
--
ALTER TABLE `ensz_winnerpoll`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `haboruk`
--
ALTER TABLE `haboruk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jatekok`
--
ALTER TABLE `jatekok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jatekok_history`
--
ALTER TABLE `jatekok_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

```