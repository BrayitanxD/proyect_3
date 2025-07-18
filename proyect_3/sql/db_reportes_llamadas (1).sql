-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-07-2025 a las 02:40:42
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_reportes_llamadas`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adjuntos_reporte`
--

CREATE TABLE `adjuntos_reporte` (
  `id_adjunto` int(11) NOT NULL,
  `id_reporte` int(11) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamano_bytes` int(11) DEFAULT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `adjuntos_reporte`
--

INSERT INTO `adjuntos_reporte` (`id_adjunto`, `id_reporte`, `nombre_archivo`, `ruta_archivo`, `tipo_mime`, `tamano_bytes`, `fecha_subida`) VALUES
(1, 1, 'image.png', 'uploads/adjunto_68714f7881ad3.png', 'image/png', 4477, '2025-07-11 17:52:56'),
(2, 2, 'image.png', 'uploads/adjunto_6871500ff08f0.png', 'image/png', 4477, '2025-07-11 17:55:27'),
(3, 3, 'image.png', 'uploads/adjunto_687156e272bc5.png', 'image/png', 4477, '2025-07-11 18:24:34'),
(5, 5, 'image.png', 'uploads/adjunto_687541527a4f8.png', 'image/png', 4477, '2025-07-14 17:41:38');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_llamadas`
--

CREATE TABLE `reportes_llamadas` (
  `id_reporte` int(11) NOT NULL,
  `call_start_time` datetime NOT NULL,
  `operator_name` varchar(100) NOT NULL,
  `call_id` varchar(50) NOT NULL,
  `call_duration` varchar(20) NOT NULL,
  `report_creation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_email` varchar(150) DEFAULT NULL,
  `customer_id_contrato` varchar(100) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `problem_type` enum('fallo_tecnico','daño_fisico','mal_funcionamiento','interrupcion_servicio','problema_red','falla_electrica','otro') NOT NULL,
  `affected_service` enum('internet','telefonia','television','fibra_optica','wifi','email','aplicacion','otro') NOT NULL,
  `problem_date` datetime NOT NULL,
  `problem_location` varchar(255) DEFAULT NULL,
  `problem_description` text NOT NULL,
  `priority` enum('critica','alta','media','baja') NOT NULL,
  `immediate_action` enum('reinicio_equipo','verificacion_cables','reinicio_router','cambio_configuracion','escalamiento_tecnico','programar_visita','ninguna','no_aplica') DEFAULT 'no_aplica',
  `next_step` enum('seguimiento_24h','visita_tecnica','llamada_confirmacion','escalamiento_supervisor','cierre_caso','no_aplica') DEFAULT 'no_aplica',
  `technical_notes` text DEFAULT NULL,
  `estado_reporte` enum('pendiente','en_proceso','pausado','atendido','archivado') NOT NULL DEFAULT 'pendiente',
  `fecha_gestion` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `reportes_llamadas`
--

INSERT INTO `reportes_llamadas` (`id_reporte`, `call_start_time`, `operator_name`, `call_id`, `call_duration`, `report_creation_date`, `customer_name`, `customer_phone`, `customer_email`, `customer_id_contrato`, `customer_address`, `problem_type`, `affected_service`, `problem_date`, `problem_location`, `problem_description`, `priority`, `immediate_action`, `next_step`, `technical_notes`, `estado_reporte`, `fecha_gestion`) VALUES
(1, '2025-11-07 16:51:21', 'Juan Pérez', 'CLL-281449', '00:01:35', '2025-07-11 17:52:56', 'Brayan Hernandez', '601 3108415', 'soporteit@confiarseguridad.com.co', 'CONTRATO 01', 'Calle 63 D BIS # 22 &#39; 39', 'fallo_tecnico', 'internet', '2025-07-11 17:51:00', 'CONFIAR', 'Se cayo el internet', 'critica', 'reinicio_router', 'llamada_confirmacion', 'En espera de confirmar por parte del cliente', 'archivado', '2025-07-11 19:10:49'),
(2, '2025-11-07 16:54:40', 'Juan Pérez', 'CLL-480201', '00:00:47', '2025-07-11 17:55:27', 'Brayan Hernandez', '601 3108415', 'soporteit@confiarseguridad.com.co', 'CONTRATO 01', 'Calle 63 D BIS # 22 &#39; 39', 'fallo_tecnico', 'internet', '2025-07-11 17:51:00', 'CONFIAR', 'Se cayo el internet', 'critica', 'reinicio_router', 'llamada_confirmacion', 'En espera de confirmar por parte del cliente', 'en_proceso', '2025-07-11 19:10:41'),
(3, '2025-11-07 05:19:51', 'Juan Pérez', 'CLL-99115449', '00:04:43', '2025-07-11 18:24:34', 'RICHARD', '601 3108415', 'soporteit@confiarseguridad.com.co', 'CONTRATO 02', 'CALLE 63 D BIS # 22  - 39', 'otro', 'otro', '2025-07-11 18:19:00', 'CONFIAR 2', 'TEST 2', 'baja', 'escalamiento_tecnico', 'cierre_caso', 'CERRADO', 'pausado', '2025-07-11 19:10:08'),
(5, '1970-01-01 01:00:00', 'Juan Pérez', 'CLL-62169791', '00:04:36', '2025-07-14 17:41:38', 'USUARIO01', '3008914193', 'soporteit@confiarseguridad.com.co', 'CONTRATO01', 'CALLE 63 D BIS # 22  - 39', 'daño_fisico', 'television', '2025-07-14 17:37:00', 'CONFIAR01', 'PROBLEMA GRANDE', 'alta', 'cambio_configuracion', 'escalamiento_supervisor', 'SEGUIR INSTRUCCIONES', 'pendiente', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adjuntos_reporte`
--
ALTER TABLE `adjuntos_reporte`
  ADD PRIMARY KEY (`id_adjunto`),
  ADD KEY `idx_adjuntos_reporte_id_reporte` (`id_reporte`);

--
-- Indices de la tabla `reportes_llamadas`
--
ALTER TABLE `reportes_llamadas`
  ADD PRIMARY KEY (`id_reporte`),
  ADD UNIQUE KEY `call_id` (`call_id`),
  ADD KEY `idx_reportes_llamadas_problem_date` (`problem_date`),
  ADD KEY `idx_reportes_llamadas_priority` (`priority`),
  ADD KEY `idx_reportes_llamadas_customer_phone` (`customer_phone`),
  ADD KEY `idx_reportes_llamadas_call_start_time` (`call_start_time`),
  ADD KEY `idx_reportes_llamadas_estado` (`estado_reporte`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adjuntos_reporte`
--
ALTER TABLE `adjuntos_reporte`
  MODIFY `id_adjunto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `reportes_llamadas`
--
ALTER TABLE `reportes_llamadas`
  MODIFY `id_reporte` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `adjuntos_reporte`
--
ALTER TABLE `adjuntos_reporte`
  ADD CONSTRAINT `adjuntos_reporte_ibfk_1` FOREIGN KEY (`id_reporte`) REFERENCES `reportes_llamadas` (`id_reporte`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
