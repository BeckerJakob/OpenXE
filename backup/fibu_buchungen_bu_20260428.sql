/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.25-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: openxe
-- ------------------------------------------------------
-- Server version	10.6.25-MariaDB-ubu2204

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `fibu_buchungen`
--

DROP TABLE IF EXISTS `fibu_buchungen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `fibu_buchungen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `von_typ` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `von_id` int(11) NOT NULL,
  `nach_typ` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `nach_id` int(11) NOT NULL,
  `betrag` decimal(10,2) NOT NULL,
  `waehrung` varchar(10) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT 'EUR',
  `benutzer` int(11) NOT NULL,
  `zeit` datetime NOT NULL,
  `internebemerkung` varchar(128) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `datum` date NOT NULL,
  `buchungsschluessel` varchar(32) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fibu_buchungen`
--
-- WHERE:  id in (785,786,787,840,841)

LOCK TABLES `fibu_buchungen` WRITE;
/*!40000 ALTER TABLE `fibu_buchungen` DISABLE KEYS */;
INSERT INTO `fibu_buchungen` VALUES (785,'kontoauszuege',574,'kontorahmen',855,29.46,'EUR',4,'2026-03-23 17:06:00','Zahlung Unbekannt | 19% Umsatzsteuer auf EUR 155,04- Abrechnung per 31.01.2026 von Konto 25890213','2026-01-30','90'),(786,'kontoauszuege',575,'kontorahmen',855,155.04,'EUR',4,'2026-03-23 17:06:00','Zahlung Unbekannt | ABSCHLUSS PER 31.01.2026','2026-01-30','90'),(787,'kontoauszuege',337,'kontorahmen',735,351.00,'EUR',4,'2026-03-23 17:18:00','','2026-01-08','90'),(840,'kontoauszuege',309,'kontorahmen',906,79.00,'EUR',4,'2026-03-25 16:14:00','','2026-01-07','90'),(841,'kontoauszuege',431,'kontorahmen',906,259.00,'EUR',4,'2026-03-25 16:14:00','','2026-01-15','90');
/*!40000 ALTER TABLE `fibu_buchungen` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-28 21:10:41
