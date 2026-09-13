-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Set 13, 2026 alle 17:47
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `634692`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello`
--

CREATE TABLE `carrello` (
  `user_cliente` varchar(25) NOT NULL,
  `id_prodotto` int(10) UNSIGNED NOT NULL,
  `quantita` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `carrello`
--

INSERT INTO `carrello` (`user_cliente`, `id_prodotto`, `quantita`) VALUES
('clitest', 1, 1);

--
-- Trigger `carrello`
--
DELIMITER $$
CREATE TRIGGER `riserve` BEFORE INSERT ON `carrello` FOR EACH ROW BEGIN
IF(NEW.quantita > (Select disponibilita from prodotto where id = NEW.id_prodotto)) then
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT= 'Quantità non disponibile per questo prodotto!';
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `carta`
--

CREATE TABLE `carta` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_utente` varchar(25) DEFAULT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `nome_intestatario` varchar(20) DEFAULT NULL,
  `cognome_intestatario` varchar(20) DEFAULT NULL,
  `data_scadenza` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `carta`
--

INSERT INTO `carta` (`id`, `user_utente`, `numero`, `nome_intestatario`, `cognome_intestatario`, `data_scadenza`) VALUES
(1, 'cliente', '1111111111111111', 'Eustachio', 'Degola', '2026-08-07'),
(2, 'cliente', '1234567898765432', 'Vincenzo', 'Bianchi', '2026-06-29'),
(8, 'boy', '1111111111111111', 'Mario', 'Rossi', '2026-08-09');

-- --------------------------------------------------------

--
-- Struttura della tabella `categoria`
--

CREATE TABLE `categoria` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `nome` varchar(25) NOT NULL,
  `is_a` tinyint(3) UNSIGNED DEFAULT NULL,
  `totale_prodotti` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `categoria`
--

INSERT INTO `categoria` (`id`, `nome`, `is_a`, `totale_prodotti`) VALUES
(1, 'Abbigliamento', NULL, 4),
(2, 'Casa', NULL, 5),
(3, 'Elettronica', NULL, 4),
(4, 'dispositivi indossabili', 3, 0),
(19, 'Musica', NULL, 0),
(20, 'Hobby', NULL, 0),
(21, 'vinili', 19, 1),
(22, 'francobolli', 20, 1),
(23, 'puzzle', 20, 0),
(24, 'computer e accessori', 3, 0),
(25, 'videogiochi e console', 3, 1),
(26, 'smartphone e tablet', 3, 1),
(27, 'elettrodomestici', 2, 0),
(28, 'computer portatili', 24, 0),
(29, 'tastiere', 24, 0),
(30, 'esterni e giardino', 2, 0),
(57, 'vestiti', 1, 0),
(58, 'scarpe', 1, 0),
(59, 'Sport', NULL, 0),
(60, 'Libri', NULL, 1),
(61, 'cura personale', 3, 0),
(62, 'igiene orale', 61, 0),
(63, 'bilance', 61, 0);

-- --------------------------------------------------------

--
-- Struttura della tabella `immagine_prodotto`
--

CREATE TABLE `immagine_prodotto` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_prodotto` int(10) UNSIGNED NOT NULL,
  `percorso` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `immagine_prodotto`
--

INSERT INTO `immagine_prodotto` (`id`, `id_prodotto`, `percorso`) VALUES
(18, 30, 'assets/img/prodotti/p_30_6a4513b36b4b9.png'),
(25, 37, 'assets/img/prodotti/p_37_6a5168550037a.jpg'),
(26, 37, 'assets/img/prodotti/p_37_6a5168550144e.jpg'),
(27, 38, 'assets/img/prodotti/p_38_6a5168d18bf33.jpg'),
(34, 43, 'assets/img/prodotti/p_43_6a5296c731c60.jpg'),
(35, 44, 'assets/img/prodotti/p_44_6a5296f30762d.jpg'),
(36, 44, 'assets/img/prodotti/p_44_6a5296f308567.jpg'),
(37, 44, 'assets/img/prodotti/p_44_6a5296f30ae68.jpg'),
(38, 45, 'assets/img/prodotti/p_45_6a5297223898c.jpg'),
(39, 45, 'assets/img/prodotti/p_45_6a5297223a194.jpg'),
(40, 45, 'assets/img/prodotti/p_45_6a5297223b4ce.jpg'),
(41, 46, 'assets/img/prodotti/p_46_6a5297665a366.jpg'),
(42, 46, 'assets/img/prodotti/p_46_6a5297665b789.jpg'),
(45, 49, 'assets/img/prodotti/p_49_6a52981a987ef.jpg');

-- --------------------------------------------------------

--
-- Struttura della tabella `immagine_recensione`
--

CREATE TABLE `immagine_recensione` (
  `id` int(10) UNSIGNED NOT NULL,
  `id_recensione` int(10) UNSIGNED NOT NULL,
  `percorso` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `immagine_recensione`
--

INSERT INTO `immagine_recensione` (`id`, `id_recensione`, `percorso`) VALUES
(1, 12, 'assets/img/recensioni/r_12_6a516fb2ee79a.png'),
(4, 15, 'assets/img/recensioni/r_15_6a5253dccf05e.png');

-- --------------------------------------------------------

--
-- Struttura della tabella `notifica`
--

CREATE TABLE `notifica` (
  `id` int(11) NOT NULL,
  `tipo` enum('acquisto','oos','magazzino','aggiornamento_spedizione','recensione') NOT NULL,
  `testo` varchar(255) NOT NULL,
  `data_notifica` datetime NOT NULL,
  `user_cliente` varchar(20) NOT NULL,
  `letta` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `notifica`
--

INSERT INTO `notifica` (`id`, `tipo`, `testo`, `data_notifica`, `user_cliente`, `letta`) VALUES
(21, 'acquisto', 'cliente ha effettuato un ordine che include \"prodottotest\".', '2026-07-01 15:21:14', 'test', 1),
(25, 'aggiornamento_spedizione', 'Il tuo ordine #27 è ora: consegnato.', '2026-07-02 21:04:09', 'boy', 1),
(38, 'acquisto', 'Ordine #50: cliente ha effettuato un ordine che include \"Jeans indaco\".', '2026-07-06 18:52:42', 'pippo', 1),
(39, 'acquisto', 'Ordine #50: cliente ha annullato l\'ordine.', '2026-07-06 18:53:17', 'pippo', 1),
(40, 'acquisto', 'Ordine #49: cliente ha annullato l\'ordine.', '2026-07-06 19:00:25', 'pippo', 1),
(41, 'acquisto', 'Ordine #48: cliente ha annullato l\'ordine.', '2026-07-06 19:00:48', 'pippo', 1),
(42, 'acquisto', 'Ordine #43: cliente ha annullato l\'ordine.', '2026-07-06 22:47:29', 'pippo', 1),
(43, 'acquisto', 'Ordine #42: cliente ha annullato l\'ordine.', '2026-07-06 22:58:10', 'pippo', 1),
(44, 'acquisto', 'Ordine #41: cliente ha annullato l\'ordine.', '2026-07-06 22:58:56', 'pippo', 1),
(45, 'acquisto', 'Ordine #40: cliente ha annullato l\'ordine.', '2026-07-06 22:59:27', 'pippo', 1),
(46, 'acquisto', 'Ordine #39: cliente ha annullato l\'ordine.', '2026-07-06 22:59:42', 'pippo', 1),
(47, 'acquisto', 'Ordine #38: cliente ha annullato l\'ordine.', '2026-07-06 23:06:27', 'pippo', 1),
(48, 'acquisto', 'Ordine #24: cliente ha annullato l\'ordine.', '2026-07-06 23:11:51', 'pippo', 1),
(49, 'acquisto', 'Ordine #13: cliente ha annullato l\'ordine.', '2026-07-06 23:19:31', 'pippo', 1),
(50, 'acquisto', 'Ordine #37: cliente ha annullato l\'ordine.', '2026-07-06 23:20:07', 'pippo', 1),
(51, 'acquisto', 'Ordine #4: cliente ha annullato l\'ordine.', '2026-07-06 23:20:43', 'pippo', 1),
(52, 'magazzino', 'La quantità di \"Felpa lillas\" nel tuo carrello è stata ridotta a 7 (disponibilità massima attuale).', '2026-07-07 12:38:16', 'cliente', 1),
(53, 'acquisto', 'Ordine #51: boy ha effettuato un ordine che include \"Felpa lillas\".', '2026-07-07 12:38:16', 'pippo', 1),
(54, 'oos', '\"asd\" non è più disponibile ed è stato rimosso dal tuo carrello.', '2026-07-07 15:59:42', 'boy', 1),
(55, 'acquisto', 'Ordine #52: boy ha effettuato un ordine che include \"Felpa lillas\".', '2026-07-07 16:06:19', 'pippo', 1),
(56, 'acquisto', 'Ordine #52: boy ha annullato l\'ordine.', '2026-07-07 16:06:37', 'pippo', 1),
(57, 'aggiornamento_spedizione', 'Il tuo ordine #51 è ora: in transito.', '2026-07-07 16:07:03', 'boy', 1),
(58, 'aggiornamento_spedizione', 'Il tuo ordine #51 è ora: fallito.', '2026-07-07 16:15:54', 'boy', 1),
(59, 'acquisto', 'Ordine #53: cliente ha effettuato un ordine che include \"Power bank\".', '2026-07-07 23:46:56', 'pippo', 1),
(60, 'aggiornamento_spedizione', 'Il tuo ordine #53 è ora: consegnato.', '2026-07-07 23:47:07', 'cliente', 1),
(61, 'magazzino', 'Sono diminuite le nostre scorte per il prodotto \"Libro test\". La quantità di \"Libro test\" nel tuo carrello è stata ridotta a 6 (disponibilità massima attuale).', '2026-07-10 23:58:00', 'boy', 1),
(63, 'aggiornamento_spedizione', 'Il tuo ordine #54 è ora: in transito.', '2026-07-11 00:00:39', 'cliente', 1),
(65, 'aggiornamento_spedizione', 'Il tuo ordine #55 è ora: consegnato.', '2026-07-11 00:12:17', 'cliente', 1),
(71, 'magazzino', 'Sono diminuite le nostre scorte per il prodotto \"Libro test\". La quantità di \"Libro test\" nel tuo carrello è stata ridotta a 5 (disponibilità massima attuale).', '2026-07-11 01:21:14', 'boy', 1),
(74, 'magazzino', 'Sono diminuite le nostre scorte per il prodotto \"Libro test\". La quantità di \"Libro test\" nel tuo carrello è stata ridotta a 4 (disponibilità massima attuale).', '2026-07-11 01:41:37', 'boy', 1),
(80, 'aggiornamento_spedizione', 'Il tuo ordine #60 è ora: consegnato.', '2026-07-11 02:09:52', 'boy', 1),
(84, 'aggiornamento_spedizione', 'Il tuo ordine #62 è ora: consegnato.', '2026-07-11 10:24:00', 'boy', 1),
(85, 'aggiornamento_spedizione', 'Il tuo ordine #63 è ora: in transito.', '2026-07-11 10:24:05', 'boy', 1),
(87, 'aggiornamento_spedizione', 'Il tuo ordine #63 è ora: fallito.', '2026-07-11 11:01:53', 'boy', 1),
(88, 'recensione', 'boy ha recensito prodotto #1 \"Felpa lillas\" con ★★★★★.', '2026-07-11 16:31:56', 'pippo', 1),
(89, 'oos', 'Il prodotto \"Prodotto Multi immagine\" non è più disponibile ed è stato rimosso dal tuo carrello.', '2026-07-11 18:59:52', 'boy', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `ordine`
--

CREATE TABLE `ordine` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_cliente` varchar(25) DEFAULT NULL,
  `id_carta` int(10) UNSIGNED DEFAULT NULL,
  `data_ordine` datetime NOT NULL,
  `stato` enum('non_spedito','in_transito','consegnato','fallito','annullato') NOT NULL,
  `indirizzo_spedizione` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `ordine`
--

INSERT INTO `ordine` (`id`, `user_cliente`, `id_carta`, `data_ordine`, `stato`, `indirizzo_spedizione`) VALUES
(4, 'cliente', 1, '2026-06-27 00:23:58', 'annullato', 'Via Ciao'),
(5, 'cliente', 1, '2026-06-27 00:28:02', 'in_transito', 'Via Roma 12, Milano'),
(12, 'cliente', 2, '2026-06-27 02:47:52', 'consegnato', 'as'),
(13, 'cliente', 1, '2026-06-27 02:50:56', 'annullato', 'Via Ciao'),
(24, 'cliente', 1, '2026-06-27 17:41:38', 'annullato', 'Via A'),
(27, 'boy', NULL, '2026-06-27 23:43:13', 'consegnato', 'via gol'),
(33, 'cliente', 2, '2026-06-28 17:56:24', 'annullato', 'Via G'),
(34, 'cliente', 2, '2026-06-28 18:10:40', 'annullato', 'VIa A'),
(35, 'cliente', 2, '2026-06-28 19:11:12', 'consegnato', 'Via a'),
(36, 'cliente', 1, '2026-07-01 15:21:14', 'consegnato', 'Via Test'),
(37, 'cliente', 1, '2026-07-02 19:58:44', 'annullato', 'Via A'),
(38, 'cliente', 2, '2026-07-02 23:51:02', 'annullato', 'as'),
(39, 'cliente', 2, '2026-07-02 23:51:31', 'annullato', 'cc'),
(40, 'cliente', 2, '2026-07-02 23:51:48', 'annullato', 'd'),
(41, 'cliente', 2, '2026-07-02 23:52:12', 'annullato', 'vv'),
(42, 'cliente', 2, '2026-07-03 00:09:53', 'annullato', 'asd'),
(43, 'cliente', 2, '2026-07-03 00:10:07', 'annullato', 'qq'),
(44, 'cliente', 2, '2026-07-03 00:10:20', 'annullato', 'cccc'),
(45, 'cliente', 1, '2026-07-03 00:10:32', 'annullato', 'ssad'),
(46, 'cliente', 1, '2026-07-03 00:10:46', 'annullato', 'cq'),
(47, 'cliente', 2, '2026-07-03 01:20:44', 'annullato', 'asf'),
(48, 'cliente', 2, '2026-07-03 01:31:01', 'annullato', 'ad'),
(49, 'cliente', 1, '2026-07-03 01:35:05', 'annullato', 'adsm'),
(50, 'cliente', 2, '2026-07-06 18:52:42', 'annullato', 'Via test'),
(51, 'boy', NULL, '2026-07-07 12:38:16', 'fallito', 'via Milano'),
(52, 'boy', NULL, '2026-07-07 16:06:19', 'annullato', 'via Milano'),
(53, 'cliente', 2, '2026-07-07 23:46:56', 'consegnato', 'via a'),
(54, 'cliente', 2, '2026-07-10 23:58:00', 'in_transito', 'Via Roma, 2'),
(55, 'cliente', 1, '2026-07-11 00:12:05', 'consegnato', 'sda'),
(62, 'boy', 8, '2026-07-11 10:23:23', 'consegnato', 'via Milano'),
(63, 'boy', 8, '2026-07-11 10:23:37', 'fallito', 'via Milano');

--
-- Trigger `ordine`
--
DELIMITER $$
CREATE TRIGGER `SvuotaCarrello` AFTER INSERT ON `ordine` FOR EACH ROW delete from Carrello where user_cliente = new.user_cliente
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `ordine_prodotto`
--

CREATE TABLE `ordine_prodotto` (
  `id_ordine` int(10) UNSIGNED NOT NULL,
  `id_prodotto` int(10) UNSIGNED NOT NULL,
  `quantita` int(10) UNSIGNED NOT NULL,
  `prezzo` decimal(7,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `ordine_prodotto`
--

INSERT INTO `ordine_prodotto` (`id_ordine`, `id_prodotto`, `quantita`, `prezzo`) VALUES
(4, 1, 3, 39.90),
(4, 3, 1, 14.50),
(4, 7, 4, 12.00),
(5, 2, 1, 59.00),
(12, 1, 1, 39.90),
(13, 8, 4, 18.90),
(24, 10, 2, 24.90),
(27, 1, 1, 39.90),
(33, 25, 1, 8.00),
(34, 26, 1, 2.00),
(35, 27, 1, 2.00),
(36, 30, 3, 5.00),
(37, 3, 1, 14.50),
(37, 7, 1, 12.00),
(38, 2, 1, 59.00),
(39, 8, 1, 18.90),
(40, 8, 1, 18.90),
(41, 4, 1, 29.99),
(42, 1, 1, 39.90),
(43, 1, 1, 39.90),
(44, 3, 1, 14.50),
(45, 9, 1, 79.00),
(46, 10, 1, 24.90),
(47, 7, 2, 12.00),
(48, 2, 1, 59.00),
(49, 9, 1, 79.00),
(50, 2, 1, 59.00),
(51, 1, 4, 39.90),
(52, 1, 5, 39.90),
(53, 10, 1, 24.90),
(54, 37, 4, 10.00),
(55, 38, 1, 1.50),
(62, 41, 1, 5.00),
(63, 42, 1, 4.00);

--
-- Trigger `ordine_prodotto`
--
DELIMITER $$
CREATE TRIGGER `UpdateProdDisponibilita` AFTER INSERT ON `ordine_prodotto` FOR EACH ROW update Prodotto as p
SET p.disponibilita = p.disponibilita - NEW.quantita
    WHERE p.id = NEW.id_prodotto
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `prodotto`
--

CREATE TABLE `prodotto` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(50) NOT NULL,
  `prezzo` decimal(7,2) DEFAULT NULL,
  `descrizione` varchar(200) DEFAULT NULL,
  `disponibilita` int(10) UNSIGNED DEFAULT NULL,
  `user_negoziante` varchar(25) DEFAULT NULL,
  `id_categoria` tinyint(3) UNSIGNED DEFAULT NULL,
  `data_pubblicazione` date DEFAULT NULL,
  `visualizzazioni` int(10) UNSIGNED DEFAULT 0,
  `n_recensioni` int(10) UNSIGNED DEFAULT 0,
  `media_recensioni` decimal(3,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `prodotto`
--

INSERT INTO `prodotto` (`id`, `nome`, `prezzo`, `descrizione`, `disponibilita`, `user_negoziante`, `id_categoria`, `data_pubblicazione`, `visualizzazioni`, `n_recensioni`, `media_recensioni`) VALUES
(1, 'Felpa lilla', 39.90, 'Felpa morbida color lilla, taglia unica.', 11, 'pippo', 1, '2026-06-12', 74, 2, 4.50),
(2, 'Jeans indaco', 59.00, 'Jeans slim fit indaco scuro.', 8, 'pippo', 1, '2026-06-12', 27, 0, 0.00),
(3, 'Maglietta basic', 14.50, 'Tazza da tè in ceramica bianca 300ml.', 10, 'pippo', 2, '2026-06-12', 5, 0, 0.00),
(4, 'Sciarpa lana', 29.99, 'Plaid in cotone rosa pastello.', 10, 'pippo', 2, '2026-06-12', 3, 0, 0.00),
(6, 'Coperta plaid', 24.90, 'Batteria esterna 10000 mAh.', 10, 'pippo', 3, '2026-06-12', 1, 0, 0.00),
(7, 'Candela profumata', 12.00, 'Candela alla lavanda, durata 40 ore.', 12, 'pippo', 2, '2026-06-12', 4, 0, 0.00),
(8, 'Vaso decorativo', 18.90, 'Vaso in terracotta smaltata, stile minimal.', 10, 'pippo', 2, '2026-06-12', 5, 0, 0.00),
(9, 'Cuffie wireless', 79.00, 'Cuffie bluetooth con custodia e cancellazione rumore.', 8, 'pippo', 3, '2026-06-12', 6, 0, 0.00),
(10, 'Power bank', 24.90, 'Batteria esterna 10000 mAh, doppia USB.', 8, 'pippo', 3, '2026-06-12', 17, 1, 7.00),
(11, 'Mouse ergonomico', 34.50, '', 11, 'pippo', 3, '2026-06-12', 1, 0, 0.00),
(25, 'provatest', 8.00, 'descrizione test', 7, NULL, 1, '2026-06-28', 2, 0, 0.00),
(26, 'pizza', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'prodottotest', 5.00, 'descrizione test', 0, 'test', 1, '2026-07-01', 11, 1, 3.00),
(37, 'Libro test', 10.00, 'Libro generico', 3, NULL, 60, '2026-07-10', 11, 0, 0.00),
(38, 'Francobolli', 1.50, 'da collezione', 2, NULL, 22, '2026-07-10', 40, 2, 3.00),
(41, 'Libro test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(42, 'francobolli', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(43, 'Milk and Honey', 10.00, 'Libro di poesie indiano.', 3, 'pippo', 60, '2026-07-11', 0, 0, 0.00),
(44, 'Smartphone', 200.00, 'Smartphone generico.', 2, 'pippo', 26, '2026-07-11', 0, 0, 0.00),
(45, 'Videogioco', 88.00, 'Videogioco come da foto', 1, 'pippo', 25, '2026-07-11', 0, 0, 0.00),
(46, 'Francobolli', 2.50, 'Rari francobolli da collezione.', 10, 'test', 22, '2026-07-11', 0, 0, 0.00),
(49, 'Dischi in vinile', 140.00, 'Vendo vecchi vinili da collezione. Vedi foto per i titoli.', 1, 'test', 21, '2026-07-11', 0, 0, 0.00);

--
-- Trigger `prodotto`
--
DELIMITER $$
CREATE TRIGGER `DecrementaProdotti` AFTER DELETE ON `prodotto` FOR EACH ROW BEGIN
update utente as u
SET u.n_prodotti_in_vendita = u.n_prodotti_in_vendita - 1 WHERE u.username = OLD.user_negoziante;

update categoria as c
SET c.totale_prodotti = c.totale_prodotti - 1 
WHERE c.id = OLD.id_categoria;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `IncrementaProdotti` AFTER INSERT ON `prodotto` FOR EACH ROW BEGIN 
update utente as u
SET u.n_prodotti_in_vendita = u.n_prodotti_in_vendita + 1 WHERE u.username = NEW.user_negoziante;

update categoria as c
SET c.totale_prodotti = c.totale_prodotti + 1 
WHERE c.id = NEW.id_categoria;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `negoziante_vende` BEFORE INSERT ON `prodotto` FOR EACH ROW BEGIN
IF (NOT EXISTS (SELECT 1 FROM utente AS u WHERE u.username = NEW.user_negoziante AND u.tipo_utente = 'negoziante')) THEN
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'solo i negozianti possono vendere prodotti!';
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `recensione`
--

CREATE TABLE `recensione` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_cliente` varchar(25) DEFAULT NULL,
  `id_prodotto` int(10) UNSIGNED NOT NULL,
  `valutazione` tinyint(4) UNSIGNED NOT NULL,
  `testo` varchar(255) DEFAULT NULL,
  `data_inserimento` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `recensione`
--

INSERT INTO `recensione` (`id`, `user_cliente`, `id_prodotto`, `valutazione`, `testo`, `data_inserimento`) VALUES
(10, 'cliente', 1, 4, NULL, '2026-06-27 22:10:36'),
(11, 'cliente', 30, 3, '---', '2026-07-01 15:22:40'),
(12, 'cliente', 38, 4, 'tutto a posto', '2026-07-11 00:18:26'),
(15, 'boy', 1, 5, 'Ottimo prodotto', '2026-07-11 16:31:56');

--
-- Trigger `recensione`
--
DELIMITER $$
CREATE TRIGGER `recensione_valida` BEFORE INSERT ON `recensione` FOR EACH ROW BEGIN
IF( (New.user_cliente, New.id_prodotto) NOT IN (Select user_cliente, id_prodotto 
from ordine, ordine_prodotto 
where ordine.id = ordine_prodotto.id_ordine
and ordine.stato IN ('consegnato', 'fallito'))
) then signal sqlstate '45000'
set message_text = 'il cliente non ha acquistato il prodotto, quindi non può fare recensioni!';
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente`
--

CREATE TABLE `utente` (
  `username` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `psw` varchar(255) DEFAULT NULL,
  `indirizzo` varchar(50) DEFAULT NULL,
  `tipo_utente` enum('cliente','negoziante') DEFAULT NULL,
  `n_prodotti_in_vendita` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `utente`
--

INSERT INTO `utente` (`username`, `email`, `psw`, `indirizzo`, `tipo_utente`, `n_prodotti_in_vendita`) VALUES
('boy', 'b@b.com', '13f90a15e8e40f47d649a54b6', 'via Milano', 'cliente', NULL),
('cliente', 'c@cc.com', '1592d35a573454f6f700364d2', '', 'cliente', NULL),
('clitest', 'clitest@c.com', 'db18daf3264864544340486e7', NULL, 'cliente', NULL),
('mat', 'm@m.com', '65ef9348057e75fa535650f0b', NULL, 'negoziante', 0),
('pippo', 'p@p.com', 'fb30f27c51631e6a747b76b4a', 'via ROMA, 25', 'negoziante', 13),
('shop_demo', 'shop@demo.local', '9048b25cb924cb49fdfccce52', NULL, 'negoziante', 0),
('test', 't@t.com', 'f4e44f3014935e94b289abcb6', '', 'negoziante', 3);

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `carrello`
--
ALTER TABLE `carrello`
  ADD PRIMARY KEY (`user_cliente`,`id_prodotto`),
  ADD KEY `fk_carrello_prodotto` (`id_prodotto`);

--
-- Indici per le tabelle `carta`
--
ALTER TABLE `carta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_carta_utente` (`user_utente`);

--
-- Indici per le tabelle `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_Is_a` (`is_a`);

--
-- Indici per le tabelle `immagine_prodotto`
--
ALTER TABLE `immagine_prodotto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ip_prodotto` (`id_prodotto`);

--
-- Indici per le tabelle `immagine_recensione`
--
ALTER TABLE `immagine_recensione`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ir_recensione` (`id_recensione`);

--
-- Indici per le tabelle `notifica`
--
ALTER TABLE `notifica`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifica_utente` (`user_cliente`);

--
-- Indici per le tabelle `ordine`
--
ALTER TABLE `ordine`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ordine_stato` (`stato`),
  ADD KEY `fk_ordine_carta` (`id_carta`),
  ADD KEY `fk_ordine_cliente` (`user_cliente`);

--
-- Indici per le tabelle `ordine_prodotto`
--
ALTER TABLE `ordine_prodotto`
  ADD PRIMARY KEY (`id_ordine`,`id_prodotto`),
  ADD KEY `op_prodotto` (`id_prodotto`);

--
-- Indici per le tabelle `prodotto`
--
ALTER TABLE `prodotto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_categoria` (`id_categoria`),
  ADD KEY `fk_negoziante` (`user_negoziante`);

--
-- Indici per le tabelle `recensione`
--
ALTER TABLE `recensione`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_prodotto` (`id_prodotto`,`user_cliente`),
  ADD UNIQUE KEY `recensione_unica` (`id_prodotto`,`user_cliente`),
  ADD KEY `fk_recensione_cliente` (`user_cliente`);

--
-- Indici per le tabelle `utente`
--
ALTER TABLE `utente`
  ADD PRIMARY KEY (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_tipo_utente` (`tipo_utente`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `carta`
--
ALTER TABLE `carta`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT per la tabella `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT per la tabella `immagine_prodotto`
--
ALTER TABLE `immagine_prodotto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT per la tabella `immagine_recensione`
--
ALTER TABLE `immagine_recensione`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT per la tabella `notifica`
--
ALTER TABLE `notifica`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT per la tabella `ordine`
--
ALTER TABLE `ordine`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT per la tabella `prodotto`
--
ALTER TABLE `prodotto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT per la tabella `recensione`
--
ALTER TABLE `recensione`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `carrello`
--
ALTER TABLE `carrello`
  ADD CONSTRAINT `fk_carrello_cliente` FOREIGN KEY (`user_cliente`) REFERENCES `utente` (`username`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_carrello_prodotto` FOREIGN KEY (`id_prodotto`) REFERENCES `prodotto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `carta`
--
ALTER TABLE `carta`
  ADD CONSTRAINT `fk_carta_utente` FOREIGN KEY (`user_utente`) REFERENCES `utente` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `categoria`
--
ALTER TABLE `categoria`
  ADD CONSTRAINT `fk_Is_a` FOREIGN KEY (`is_a`) REFERENCES `categoria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `immagine_prodotto`
--
ALTER TABLE `immagine_prodotto`
  ADD CONSTRAINT `fk_ip_prodotto` FOREIGN KEY (`id_prodotto`) REFERENCES `prodotto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `immagine_recensione`
--
ALTER TABLE `immagine_recensione`
  ADD CONSTRAINT `fk_ir_recensione` FOREIGN KEY (`id_recensione`) REFERENCES `recensione` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `notifica`
--
ALTER TABLE `notifica`
  ADD CONSTRAINT `fk_notifica_utente` FOREIGN KEY (`user_cliente`) REFERENCES `utente` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `ordine`
--
ALTER TABLE `ordine`
  ADD CONSTRAINT `fk_ordine_carta` FOREIGN KEY (`id_carta`) REFERENCES `carta` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordine_cliente` FOREIGN KEY (`user_cliente`) REFERENCES `utente` (`username`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `ordine_prodotto`
--
ALTER TABLE `ordine_prodotto`
  ADD CONSTRAINT `op_ordine` FOREIGN KEY (`id_ordine`) REFERENCES `ordine` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `prodotto`
--
ALTER TABLE `prodotto`
  ADD CONSTRAINT `fk_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_negoziante` FOREIGN KEY (`user_negoziante`) REFERENCES `utente` (`username`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Limiti per la tabella `recensione`
--
ALTER TABLE `recensione`
  ADD CONSTRAINT `fk_recensione_cliente` FOREIGN KEY (`user_cliente`) REFERENCES `utente` (`username`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recensione_prodotto` FOREIGN KEY (`id_prodotto`) REFERENCES `prodotto` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
