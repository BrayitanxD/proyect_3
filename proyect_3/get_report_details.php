<?php
// get_report_details.php

// --- Configuración de la Base de Datos (Cadena de Conexión) ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_reportes_llamadas';

// Directorio donde se guardan los archivos adjuntos (necesario para enlaces de descarga)
$upload_dir = 'uploads/'; 

// --- Conexión a la Base de Datos usando PDO ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión a la base de datos: ' . $e->getMessage()]);
    exit();
}

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $report_id = $_GET['id'];

    try {
        // Obtener detalles del reporte
        $stmt_reporte = $pdo->prepare("SELECT * FROM reportes_llamadas WHERE id_reporte = :id");
        $stmt_reporte->execute([':id' => $report_id]);
        $reporte = $stmt_reporte->fetch();

        if ($reporte) {
            // Obtener archivos adjuntos para este reporte
            $stmt_adjuntos = $pdo->prepare("SELECT nombre_archivo, ruta_archivo FROM adjuntos_reporte WHERE id_reporte = :id");
            $stmt_adjuntos->execute([':id' => $report_id]);
            $adjuntos = $stmt_adjuntos->fetchAll();

            echo json_encode(['reporte' => $reporte, 'adjuntos' => $adjuntos]);
        } else {
            echo json_encode(['error' => 'Reporte no encontrado.']);
        }

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al obtener los detalles del reporte: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'ID de reporte inválido.']);
}
?>