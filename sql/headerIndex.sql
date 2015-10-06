-- phpMyAdmin SQL Dump

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Table structure for table `headerIndex`
--

CREATE TABLE IF NOT EXISTS `headerIndex` (
  `id` int(9) AUTO_INCREMENT PRIMARY KEY,
  `height` bigint(20) NOT NULL,
  `work` varchar(64) NOT NULL,
  `version` varchar(20) NOT NULL,
  `prevBlock` varchar(64) NOT NULL,
  `merkleRoot` varchar(64) NOT NULL,
  `nBits` varchar(11) NOT NULL,
  `nTimestamp` varchar(11) NOT NULL,
  `nNonce` varchar(11) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `lft` int(9) NOT NULL,
  `rgt` int(9) NOT NULL
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;

-- --------------------------------------------------------


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;