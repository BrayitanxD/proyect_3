<?php
// procesar_reporte.php

// --- Configuración de la Base de Datos (Cadena de Conexión) ---
$db_host = 'localhost'; // O la IP de tu servidor de base de datos
$db_user = 'root';      // Tu usuario de MySQL
$db_pass = '';          // Tu contraseña de MySQL (vacío si no tienes)
$db_name = 'db_reportes_llamadas'; // El nombre de la base de datos que creaste

// Directorio donde se guardarán los archivos adjuntos.
// ASEGÚRATE DE QUE ESTE DIRECTORIO TENGA PERMISOS DE ESCRITURA (ej: 755 o 777 para pruebas)
$upload_dir = 'uploads/'; 
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true); // Crea el directorio si no existe
}

// --- Conexión a la Base de Datos usando PDO (Recomendado) ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    // Configura PDO para que lance excepciones en caso de errores
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configura el modo de obtención de resultados por defecto (ej. array asociativo)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// --- Procesamiento de los Datos del Formulario ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Recoger y Sanear los datos del formulario
    // Usa filter_input para mayor seguridad y sanear los datos
    $call_start_time = filter_input(INPUT_POST, 'callStartTime', FILTER_SANITIZE_STRING);
    $operator_name = filter_input(INPUT_POST, 'operatorName', FILTER_SANITIZE_STRING);
    $call_id = filter_input(INPUT_POST, 'callId', FILTER_SANITIZE_STRING);
    $call_duration = filter_input(INPUT_POST, 'callDuration', FILTER_SANITIZE_STRING);

    $customer_name = filter_input(INPUT_POST, 'customerName', FILTER_SANITIZE_STRING);
    $customer_phone = filter_input(INPUT_POST, 'customerPhone', FILTER_SANITIZE_STRING);
    $customer_email = filter_input(INPUT_POST, 'customerEmail', FILTER_SANITIZE_EMAIL); // Sanear email
    $customer_id_contrato = filter_input(INPUT_POST, 'customerID', FILTER_SANITIZE_STRING);
    $customer_address = filter_input(INPUT_POST, 'customerAddress', FILTER_SANITIZE_STRING);

    $problem_type = filter_input(INPUT_POST, 'problemType', FILTER_SANITIZE_STRING);
    $affected_service = filter_input(INPUT_POST, 'affectedService', FILTER_SANITIZE_STRING);
    $problem_date = filter_input(INPUT_POST, 'problemDate', FILTER_SANITIZE_STRING); // Formato datetime-local
    $problem_location = filter_input(INPUT_POST, 'problemLocation', FILTER_SANITIZE_STRING);
    $problem_description = filter_input(INPUT_POST, 'problemDescription', FILTER_SANITIZE_STRING);

    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);
    $immediate_action = filter_input(INPUT_POST, 'immediateAction', FILTER_SANITIZE_STRING);
    $next_step = filter_input(INPUT_POST, 'nextStep', FILTER_SANITIZE_STRING);
    $technical_notes = filter_input(INPUT_POST, 'technicalNotes', FILTER_SANITIZE_STRING);

    // Validación básica de datos obligatorios (puedes añadir más validaciones aquí)
    if (empty($customer_name) || empty($customer_phone) || empty($problem_type) || 
        empty($affected_service) || empty($problem_date) || empty($problem_description) || empty($priority)) {
        die("Error: Faltan campos obligatorios. Por favor, vuelve atrás y rellena todos los campos marcados con *.");
    }

    // Asegurarse de que los ENUMs tengan valores válidos o un valor predeterminado si es 'ninguna' y la base de datos no lo permite
    // En tu DB, 'ninguna' ya está en los ENUMs. Si el usuario no selecciona nada, el valor será vacío,
    // por lo que podemos convertirlo a 'no_aplica' si es necesario para el ENUM de la DB.
    if (empty($immediate_action)) {
        $immediate_action = 'no_aplica';
    }
    if (empty($next_step)) {
        $next_step = 'no_aplica';
    }


    // 2. Insertar datos en la tabla `reportes_llamadas`
    $sql_insert_reporte = "INSERT INTO `reportes_llamadas` (
        `call_start_time`, `operator_name`, `call_id`, `call_duration`, 
        `customer_name`, `customer_phone`, `customer_email`, `customer_id_contrato`, `customer_address`,
        `problem_type`, `affected_service`, `problem_date`, `problem_location`, `problem_description`,
        `priority`, `immediate_action`, `next_step`, `technical_notes`
    ) VALUES (
        :call_start_time, :operator_name, :call_id, :call_duration,
        :customer_name, :customer_phone, :customer_email, :customer_id_contrato, :customer_address,
        :problem_type, :affected_service, :problem_date, :problem_location, :problem_description,
        :priority, :immediate_action, :next_step, :technical_notes
    )";

    try {
        $stmt_reporte = $pdo->prepare($sql_insert_reporte);
        $stmt_reporte->execute([
            ':call_start_time' => date('Y-m-d H:i:s', strtotime($call_start_time)), // Convertir a formato DATETIME de MySQL
            ':operator_name' => $operator_name,
            ':call_id' => $call_id,
            ':call_duration' => $call_duration,
            ':customer_name' => $customer_name,
            ':customer_phone' => $customer_phone,
            ':customer_email' => $customer_email,
            ':customer_id_contrato' => $customer_id_contrato,
            ':customer_address' => $customer_address,
            ':problem_type' => $problem_type,
            ':affected_service' => $affected_service,
            ':problem_date' => date('Y-m-d H:i:s', strtotime($problem_date)), // Convertir a formato DATETIME de MySQL
            ':problem_location' => $problem_location,
            ':problem_description' => $problem_description,
            ':priority' => $priority,
            ':immediate_action' => $immediate_action,
            ':next_step' => $next_step,
            ':technical_notes' => $technical_notes
        ]);

        $id_reporte_generado = $pdo->lastInsertId(); // Obtener el ID del reporte recién insertado
        
        // 3. Manejar Archivos Adjuntos
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $key => $name) {
                $file_name = $_FILES['attachments']['name'][$key];
                $file_tmp = $_FILES['attachments']['tmp_name'][$key];
                $file_size = $_FILES['attachments']['size'][$key];
                $file_type = $_FILES['attachments']['type'][$key];
                $file_error = $_FILES['attachments']['error'][$key];

                if ($file_error === UPLOAD_ERR_OK) {
                    // Generar un nombre único para el archivo para evitar sobrescrituras
                    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_file_name = uniqid('adjunto_') . '.' . $file_ext;
                    $destination_path = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp, $destination_path)) {
                        // Insertar información del adjunto en la tabla `adjuntos_reporte`
                        $sql_insert_adjunto = "INSERT INTO `adjuntos_reporte` (
                            `id_reporte`, `nombre_archivo`, `ruta_archivo`, `tipo_mime`, `tamano_bytes`
                        ) VALUES (
                            :id_reporte, :nombre_archivo, :ruta_archivo, :tipo_mime, :tamano_bytes
                        )";
                        $stmt_adjunto = $pdo->prepare($sql_insert_adjunto);
                        $stmt_adjunto->execute([
                            ':id_reporte' => $id_reporte_generado,
                            ':nombre_archivo' => $file_name, // Nombre original
                            ':ruta_archivo' => $destination_path, // Ruta guardada
                            ':tipo_mime' => $file_type,
                            ':tamano_bytes' => $file_size
                        ]);
                    } else {
                        error_log("Error moviendo el archivo subido: " . $file_tmp . " a " . $destination_path);
                    }
                } else {
                    error_log("Error en la subida del archivo: " . $file_error);
                }
            }
        }

        // --- Redireccionar al usuario o mostrar mensaje de éxito ---
        // Puedes redirigir a una página de confirmación o volver al formulario con un mensaje
        header("Location: index.html?status=success&report_id=" . $id_reporte_generado);
        exit();

    } catch (PDOException $e) {
        // En caso de error en la inserción
        error_log("Error al guardar el reporte: " . $e->getMessage()); // Registrar el error
        // Redirigir a una página de error o mostrar un mensaje al usuario
        header("Location: index.html?status=error&message=" . urlencode($e->getMessage()));
        exit();
    }

} else {
    // Si se accede al script directamente sin un POST
    echo "Acceso no permitido.";
}
?>