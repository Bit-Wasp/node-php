-- phpMyAdmin SQL Dump
-- version 4.4.13.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 22, 2016 at 04:34 PM
-- Server version: 5.6.30-0ubuntu0.15.10.1
-- PHP Version: 5.6.11-1ubuntu3.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `coin`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_fork`
--

CREATE TABLE IF NOT EXISTS `active_fork` (
  `id` int(9) NOT NULL,
  `header_id` int(11) NOT NULL,
  `bip30` tinyint(1) NOT NULL DEFAULT '0',
  `bip34` tinyint(1) NOT NULL DEFAULT '0',
  `cltv` tinyint(1) NOT NULL DEFAULT '0',
  `derSig` tinyint(1) NOT NULL DEFAULT '0',
  `p2sh` tinyint(1) NOT NULL DEFAULT '0',
  `witness` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `blockIndex`
--

CREATE TABLE IF NOT EXISTS `blockIndex` (
  `id` int(9) NOT NULL,
  `hash` int(19) NOT NULL,
  `flags` int(32) NOT NULL,
  `block` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `block_transactions`
--

CREATE TABLE IF NOT EXISTS `block_transactions` (
  `id` int(15) NOT NULL,
  `block_hash` int(15) NOT NULL,
  `transaction_hash` int(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `headerIndex`
--

CREATE TABLE IF NOT EXISTS `headerIndex` (
  `id` int(9) NOT NULL,
  `hash` varbinary(32) NOT NULL,
  `height` bigint(20) NOT NULL,
  `work` varchar(64) NOT NULL,
  `version` varchar(20) NOT NULL,
  `prevBlock` varbinary(32) NOT NULL,
  `merkleRoot` varbinary(32) NOT NULL,
  `nBits` varchar(11) NOT NULL,
  `nTimestamp` varchar(11) NOT NULL,
  `nNonce` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `iindex`
--

CREATE TABLE IF NOT EXISTS `iindex` (
  `header_id` int(11) NOT NULL,
  `lft` int(11) NOT NULL,
  `rgt` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `outpoints`
--

CREATE TABLE IF NOT EXISTS `outpoints` (
  `hashKey` varbinary(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `peers`
--

CREATE TABLE IF NOT EXISTS `peers` (
  `id` int(9) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `port` int(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `retarget`
--

CREATE TABLE IF NOT EXISTS `retarget` (
  `id` int(9) NOT NULL,
  `hash` varbinary(32) NOT NULL,
  `prevTime` int(12) NOT NULL,
  `difference` decimal(10,8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int(9) NOT NULL,
  `hash` varbinary(32) NOT NULL,
  `transaction` text NOT NULL,
  `nOut` int(9) NOT NULL,
  `valueOut` bigint(32) NOT NULL,
  `valueFee` bigint(32) NOT NULL,
  `version` int(11) NOT NULL,
  `nLockTime` int(11) NOT NULL,
  `isCoinbase` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_input`
--

CREATE TABLE IF NOT EXISTS `transaction_input` (
  `id` bigint(20) NOT NULL,
  `hashPrevOut` varbinary(32) NOT NULL,
  `nPrevOut` int(32) NOT NULL,
  `scriptSig` blob NOT NULL,
  `nSequence` bigint(19) NOT NULL,
  `parent_tx` int(15) NOT NULL,
  `nInput` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_output`
--

CREATE TABLE IF NOT EXISTS `transaction_output` (
  `id` int(9) NOT NULL,
  `value` bigint(21) NOT NULL,
  `scriptPubKey` blob NOT NULL,
  `parent_tx` int(11) NOT NULL,
  `nOutput` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `utxo`
--

CREATE TABLE IF NOT EXISTS `utxo` (
  `id` int(9) NOT NULL,
  `hashKey` varbinary(36) NOT NULL,
  `height` int(9) NOT NULL,
  `value` bigint(32) NOT NULL,
  `scriptPubKey` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- --------------------------------------------------------

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_fork`
--
ALTER TABLE `active_fork`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blockIndex`
--
ALTER TABLE `blockIndex`
ADD PRIMARY KEY (`id`),
ADD KEY `hash` (`hash`);

--
-- Indexes for table `block_transactions`
--
ALTER TABLE `block_transactions`
ADD PRIMARY KEY (`id`),
ADD KEY `idx` (`block_hash`,`transaction_hash`),
ADD KEY `txidx` (`transaction_hash`,`block_hash`);

--
-- Indexes for table `headerIndex`
--
ALTER TABLE `headerIndex`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `hash` (`hash`) USING HASH,
ADD KEY `prevBlock` (`prevBlock`);

--
-- Indexes for table `iindex`
--
ALTER TABLE `iindex`
ADD UNIQUE KEY `header_id` (`header_id`),
ADD KEY `lft` (`lft`,`rgt`);

--
-- Indexes for table `outpoints`
--
ALTER TABLE `outpoints`
ADD KEY `o` (`hashKey`);

--
-- Indexes for table `retarget`
--
ALTER TABLE `retarget`
ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
ADD PRIMARY KEY (`id`),
ADD KEY `hash` (`hash`);

--
-- Indexes for table `transaction_input`
--
ALTER TABLE `transaction_input`
ADD PRIMARY KEY (`id`),
ADD KEY `parent_tx` (`parent_tx`),
ADD KEY `prevout` (`hashPrevOut`,`nPrevOut`);

--
-- Indexes for table `transaction_output`
--
ALTER TABLE `transaction_output`
ADD PRIMARY KEY (`id`),
ADD KEY `parent_tx` (`parent_tx`);

--
-- Indexes for table `utxo`
--
ALTER TABLE `utxo`
ADD PRIMARY KEY (`id`),
ADD KEY `hashKeyIdx` (`hashKey`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_fork`
--
ALTER TABLE `active_fork`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `blockIndex`
--
ALTER TABLE `blockIndex`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `block_transactions`
--
ALTER TABLE `block_transactions`
MODIFY `id` int(15) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `headerIndex`
--
ALTER TABLE `headerIndex`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `retarget`
--
ALTER TABLE `retarget`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transaction_input`
--
ALTER TABLE `transaction_input`
MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `transaction_output`
--
ALTER TABLE `transaction_output`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
--
-- AUTO_INCREMENT for table `utxo`
--
ALTER TABLE `utxo`
MODIFY `id` int(9) NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;