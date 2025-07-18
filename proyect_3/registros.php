<?php
// registros.php

// --- Configuración de la Base de Datos (Cadena de Conexión) ---
$db_host = 'localhost'; // O la IP de tu servidor de base de datos
$db_user = 'root';      // Tu usuario de MySQL
$db_pass = '';          // Tu contraseña de MySQL (vacío si no tienes)
$db_name = 'db_reportes_llamadas'; // El nombre de la base de datos

// Directorio donde se guardan los archivos adjuntos (necesario para enlaces de descarga)
$upload_dir = 'uploads/'; 

// --- Conexión a la Base de Datos usando PDO ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}

// --- Lógica de Acciones (Eliminar, Cambiar Estado) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);

    if ($report_id) {
        try {
            if ($action === 'delete') {
                // Eliminar archivos adjuntos primero (ON DELETE CASCADE en DB se encarga, pero buena práctica)
                $stmt_attachments = $pdo->prepare("SELECT ruta_archivo FROM adjuntos_reporte WHERE id_reporte = :id_reporte");
                $stmt_attachments->execute([':id_reporte' => $report_id]);
                $attachments = $stmt_attachments->fetchAll();
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['ruta_archivo'])) {
                        unlink($attachment['ruta_archivo']); // Elimina el archivo físico
                    }
                }

                // Eliminar el registro del reporte
                $stmt_delete = $pdo->prepare("DELETE FROM reportes_llamadas WHERE id_reporte = :id_reporte");
                $stmt_delete->execute([':id_reporte' => $report_id]);
                $_SESSION['message'] = 'Reporte eliminado correctamente.';
                $_SESSION['message_type'] = 'success';
            } elseif (in_array($action, ['en_proceso', 'pausado', 'atendido', 'archivado'])) {
                // Cambiar estado
                $estado = $action;
                // Si el estado es 'atendido', automáticamente archivarlo
                if ($estado === 'atendido') {
                    $estado = 'archivado';
                }
                
                $stmt_update = $pdo->prepare("UPDATE reportes_llamadas SET estado_reporte = :estado WHERE id_reporte = :id_reporte");
                $stmt_update->execute([':estado' => $estado, ':id_reporte' => $report_id]);
                $_SESSION['message'] = 'Estado del reporte actualizado a ' . str_replace('_', ' ', $estado) . '.';
                $_SESSION['message_type'] = 'success';
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = 'Error al realizar la acción: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $_SESSION['message'] = 'ID de reporte inválido.';
        $_SESSION['message_type'] = 'error';
    }
    // Redireccionar para evitar reenvío del formulario
    header("Location: registros.php");
    exit();
}

// Iniciar sesión para mensajes
session_start();

// --- Lógica de Paginación y Filtrado ---
$limit_per_page = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT, array("options" => array("default" => 10, "min_range" => 1)));
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, array("options" => array("default" => 1, "min_range" => 1)));
$filter_status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);

$offset = ($current_page - 1) * $limit_per_page;

// Construir la consulta SQL
$sql_count = "SELECT COUNT(*) FROM reportes_llamadas";
$sql_select = "SELECT * FROM reportes_llamadas";
$where_clause = "";
$params = [];

if (!empty($filter_status) && $filter_status !== 'todos') {
    $where_clause = " WHERE estado_reporte = :status_filter";
    $params[':status_filter'] = $filter_status;
}

$sql_count .= $where_clause;
$sql_select .= $where_clause . " ORDER BY report_creation_date DESC LIMIT :limit OFFSET :offset";

// Contar el total de registros
$stmt_count = $pdo->prepare($sql_count);
foreach ($params as $key => $val) {
    $stmt_count->bindValue($key, $val);
}
$stmt_count->execute();
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit_per_page);

// Obtener los registros para la página actual
$stmt_select = $pdo->prepare($sql_select);
foreach ($params as $key => $val) {
    $stmt_select->bindValue($key, $val);
}
$stmt_select->bindValue(':limit', $limit_per_page, PDO::PARAM_INT);
$stmt_select->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_select->execute();
$reportes = $stmt_select->fetchAll();

// --- Función para obtener adjuntos (si se necesita verlos en la tabla principal) ---
function get_attachments($pdo, $report_id) {
    $stmt = $pdo->prepare("SELECT nombre_archivo, ruta_archivo FROM adjuntos_reporte WHERE id_reporte = :id_reporte");
    $stmt->execute([':id_reporte' => $report_id]);
    return $stmt->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Reportes de Llamadas</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .controls label {
            font-weight: 600;
            margin-right: 10px;
            color: #555;
        }
        .controls select, .controls button {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .controls select:focus, .controls button:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        .controls .btn {
            background-color: #007bff;
            color: white;
            border: none;
        }
        .controls .btn:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #e9ecef;
            color: #495057;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        tr:hover {
            background-color: #f8f9fa;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: capitalize;
        }
        .status-pendiente { background-color: #ffc107; color: #333; }
        .status-en_proceso { background-color: #17a2b8; color: white; }
        .status-pausado { background-color: #6c757d; color: white; }
        .status-atendido { background-color: #28a745; color: white; }
        .status-archivado { background-color: #6f42c1; color: white; } /* Púrpura para archivado */


        .actions-cell button, .actions-cell a {
            display: inline-block;
            padding: 8px 12px;
            margin: 3px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            text-decoration: none;
            color: white;
            transition: background-color 0.2s ease;
        }
        .btn-view { background-color: #007bff; }
        .btn-view:hover { background-color: #0056b3; }
        .btn-process { background-color: #17a2b8; }
        .btn-process:hover { background-color: #138496; }
        .btn-pause { background-color: #ffc107; color: #333; } /* Naranja para pausar */
        .btn-pause:hover { background-color: #e0a800; }
        .btn-complete { background-color: #28a745; }
        .btn-complete:hover { background-color: #218838; }
        .btn-delete { background-color: #dc3545; }
        .btn-delete:hover { background-color: #c82333; }
        .btn-archive { background-color: #6f42c1; } /* Púrpura para archivar */
        .btn-archive:hover { background-color: #5a2e9e; }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #007bff;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .pagination a:hover {
            background-color: #e9ecef;
            color: #0056b3;
        }
        .pagination .current-page {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #bbb;
            pointer-events: none;
            background-color: #f8f9fa;
        }

        /* Modal para ver detalles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1002; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 700px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            animation: slideIn 0.3s forwards;
            max-height: 90vh; /* Max height to allow scrolling within modal */
            overflow-y: auto; /* Enable scrolling for content */
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 15px;
            right: 25px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .modal-body h3 {
            color: #2c3e50;
            margin-top: 20px;
            margin-bottom: 15px;
            border-bottom: 2px solid #eee;
            padding-bottom: 5px;
        }
        .modal-body p {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        .modal-body strong {
            color: #555;
            display: inline-block;
            min-width: 150px;
        }
        .modal-body ul {
            list-style: none;
            padding-left: 0;
        }
        .modal-body ul li {
            margin-bottom: 5px;
        }

        /* Mensajes de notificación */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            animation: fadeOut 4s forwards;
        }
        .notification.success { background-color: #28a745; }
        .notification.error { background-color: #dc3545; }

        @keyframes fadeOut {
            0% { opacity: 1; transform: translateY(0); }
            80% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px auto;
            }
            .controls {
                flex-direction: column;
                align-items: flex-start;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead tr {
                position: absolute;
                top: -9999px;
                left: -9999px;
            }
            tr {
                border: 1px solid #eee;
                margin-bottom: 15px;
                border-radius: 8px;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%;
                text-align: right;
            }
            td:before {
                position: absolute;
                top: 0;
                left: 6px;
                width: 45%;
                padding-right: 10px;
                white-space: nowrap;
                text-align: left;
                font-weight: 600;
                color: #555;
            }
            /* Label de las celdas para mobile */
            td:nth-of-type(1):before { content: "ID:"; }
            td:nth-of-type(2):before { content: "Cliente:"; }
            td:nth-of-type(3):before { content: "Teléfono:"; }
            td:nth-of-type(4):before { content: "Problema:"; }
            td:nth-of-type(5):before { content: "Prioridad:"; }
            td:nth-of-type(6):before { content: "Estado:"; }
            td:nth-of-type(7):before { content: "Fecha Creación:"; }
            td:nth-of-type(8):before { content: "Acciones:"; }

            .actions-cell {
                text-align: center;
                padding-top: 15px;
            }
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gestión de Reportes de Llamadas</h1>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="notification <?php echo $_SESSION['message_type']; ?>">
                <?php echo $_SESSION['message']; ?>
            </div>
            <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
        <?php endif; ?>

        <div class="controls">
            <form method="GET" action="registros.php" class="filter-form">
                <label for="limit">Registros por página:</label>
                <select name="limit" id="limit" onchange="this.form.submit()">
                    <option value="5" <?php echo ($limit_per_page == 5) ? 'selected' : ''; ?>>5</option>
                    <option value="10" <?php echo ($limit_per_page == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="20" <?php echo ($limit_per_page == 20) ? 'selected' : ''; ?>>20</option>
                    <option value="50" <?php echo ($limit_per_page == 50) ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo ($limit_per_page == 100) ? 'selected' : ''; ?>>100</option>
                </select>
                <label for="status">Filtrar por estado:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="todos" <?php echo (empty($filter_status) || $filter_status == 'todos') ? 'selected' : ''; ?>>Todos</option>
                    <option value="pendiente" <?php echo ($filter_status == 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="en_proceso" <?php echo ($filter_status == 'en_proceso') ? 'selected' : ''; ?>>En Proceso</option>
                    <option value="pausado" <?php echo ($filter_status == 'pausado') ? 'selected' : ''; ?>>Pausado</option>
                    <option value="atendido" <?php echo ($filter_status == 'atendido') ? 'selected' : ''; ?>>Atendido</option>
                    <option value="archivado" <?php echo ($filter_status == 'archivado') ? 'selected' : ''; ?>>Archivado</option>
                </select>
            </form>
            <a href="index.html" class="btn">Crear Nuevo Reporte</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Problema</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportes)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">No hay reportes para mostrar.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportes as $reporte): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($reporte['id_reporte']); ?></td>
                            <td><?php echo htmlspecialchars($reporte['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($reporte['customer_phone']); ?></td>
                            <td><?php echo htmlspecialchars(str_replace('_', ' ', $reporte['problem_type'])); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($reporte['priority'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($reporte['estado_reporte']); ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($reporte['estado_reporte']))); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($reporte['report_creation_date']); ?></td>
                            <td class="actions-cell">
                                <button class="btn-view" onclick="showReportDetails(<?php echo $reporte['id_reporte']; ?>)">Ver</button>
                                <?php if ($reporte['estado_reporte'] === 'pendiente'): ?>
                                    <button class="btn-process" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'en_proceso')">En Proceso</button>
                                <?php elseif ($reporte['estado_reporte'] === 'en_proceso'): ?>
                                    <button class="btn-pause" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'pausado')">Pausar</button>
                                    <button class="btn-complete" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'atendido')">Atendido</button>
                                <?php elseif ($reporte['estado_reporte'] === 'pausado'): ?>
                                    <button class="btn-process" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'en_proceso')">Reanudar</button>
                                    <button class="btn-complete" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'atendido')">Atendido</button>
                                <?php elseif ($reporte['estado_reporte'] === 'atendido' || $reporte['estado_reporte'] === 'archivado'): ?>
                                    <button class="btn-archive" onclick="changeStatus(<?php echo $reporte['id_reporte']; ?>, 'archivado')">Archivar</button>
                                <?php endif; ?>
                                <button class="btn-delete" onclick="confirmDelete(<?php echo $reporte['id_reporte']; ?>)">Eliminar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($current_page > 1): ?>
                <a href="?page=<?php echo $current_page - 1; ?>&limit=<?php echo $limit_per_page; ?><?php echo (!empty($filter_status) ? '&status=' . $filter_status : ''); ?>">Anterior</a>
            <?php else: ?>
                <span class="disabled">Anterior</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit_per_page; ?><?php echo (!empty($filter_status) ? '&status=' . $filter_status : ''); ?>"
                   class="<?php echo ($i == $current_page) ? 'current-page' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($current_page < $total_pages): ?>
                <a href="?page=<?php echo $current_page + 1; ?>&limit=<?php echo $limit_per_page; ?><?php echo (!empty($filter_status) ? '&status=' . $filter_status : ''); ?>">Siguiente</a>
            <?php else: ?>
                <span class="disabled">Siguiente</span>
            <?php endif; ?>
        </div>
    </div>

    <div id="reportDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <div id="modal-body-content">
                </div>
        </div>
    </div>

    <script>
        // Función para mostrar/ocultar notificaciones (del PHP)
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'block'; // Make it visible
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 4000); // Animation is 4s, so hide after that
            }
        });

        // Función para confirmar eliminación
        function confirmDelete(id) {
            if (confirm('¿Estás seguro de que quieres eliminar este reporte? Esta acción es irreversible.')) {
                sendActionForm(id, 'delete');
            }
        }

        // Función para cambiar el estado (sin confirmación, se asume intención)
        function changeStatus(id, newStatus) {
             if (confirm(`¿Cambiar el estado del reporte #${id} a "${newStatus.replace('_', ' ').toUpperCase()}"?`)) {
                sendActionForm(id, newStatus);
             }
        }

        // Función genérica para enviar formularios de acción (POST)
        function sendActionForm(id, action) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'registros.php'; // Envía a sí mismo

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'report_id';
            idInput.value = id;
            form.appendChild(idInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }

        // --- Funcionalidad de Modal para Ver Detalles ---
        const modal = document.getElementById("reportDetailsModal");
        const modalBodyContent = document.getElementById("modal-body-content");

        function showReportDetails(id) {
            // Realizar una petición AJAX para obtener los detalles del reporte
            fetch(`get_report_details.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 404) {
                            throw new Error('Reporte no encontrado.');
                        }
                        throw new Error('Error al cargar los detalles del reporte.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        modalBodyContent.innerHTML = `<p style="color: red;">${data.error}</p>`;
                    } else {
                        // Construir el contenido del modal
                        let content = `
                            <h2>Detalles del Reporte #${data.reporte.id_reporte}</h2>
                            <p class="status-badge status-${data.reporte.estado_reporte}">${data.reporte.estado_reporte.replace('_', ' ').toUpperCase()}</p>
                            
                            <h3>Información de la Llamada</h3>
                            <p><strong>Inicio Llamada:</strong> ${data.reporte.call_start_time}</p>
                            <p><strong>Operador:</strong> ${data.reporte.operator_name}</p>
                            <p><strong>ID Llamada:</strong> ${data.reporte.call_id}</p>
                            <p><strong>Duración Llamada:</strong> ${data.reporte.call_duration}</p>
                            <p><strong>Fecha Creación Reporte:</strong> ${data.reporte.report_creation_date}</p>

                            <h3>Datos del Cliente</h3>
                            <p><strong>Nombre:</strong> ${data.reporte.customer_name}</p>
                            <p><strong>Teléfono:</strong> ${data.reporte.customer_phone}</p>
                            <p><strong>Email:</strong> ${data.reporte.customer_email || 'N/A'}</p>
                            <p><strong>ID Cliente/Contrato:</strong> ${data.reporte.customer_id_contrato || 'N/A'}</p>
                            <p><strong>Dirección:</strong> ${data.reporte.customer_address || 'N/A'}</p>

                            <h3>Información del Problema</h3>
                            <p><strong>Tipo de Problema:</strong> ${data.reporte.problem_type.replace(/_/g, ' ').toUpperCase()}</p>
                            <p><strong>Servicio Afectado:</strong> ${data.reporte.affected_service.replace(/_/g, ' ').toUpperCase()}</p>
                            <p><strong>Fecha del Problema:</strong> ${data.reporte.problem_date}</p>
                            <p><strong>Ubicación:</strong> ${data.reporte.problem_location || 'N/A'}</p>
                            <p><strong>Descripción:</strong></p>
                            <p style="white-space: pre-wrap;">${data.reporte.problem_description}</p>
                            <p><strong>Prioridad:</strong> <span class="status-badge status-${data.reporte.priority}">${data.reporte.priority.toUpperCase()}</span></p>

                            <h3>Acciones y Notas</h3>
                            <p><strong>Acción Inmediata:</strong> ${data.reporte.immediate_action.replace(/_/g, ' ').toUpperCase()}</p>
                            <p><strong>Próximo Paso:</strong> ${data.reporte.next_step.replace(/_/g, ' ').toUpperCase()}</p>
                            <p><strong>Notas Técnicas:</strong></p>
                            <p style="white-space: pre-wrap;">${data.reporte.technical_notes || 'N/A'}</p>
                        `;

                        if (data.adjuntos && data.adjuntos.length > 0) {
                            content += `
                                <h3>Archivos Adjuntos</h3>
                                <ul>
                            `;
                            data.adjuntos.forEach(adj => {
                                content += `<li><a href="${adj.ruta_archivo}" target="_blank" download>${adj.nombre_archivo}</a></li>`;
                            });
                            content += `</ul>`;
                        } else {
                            content += `<p>No hay archivos adjuntos.</p>`;
                        }

                        modalBodyContent.innerHTML = content;
                    }
                    modal.style.display = "flex"; // Usa flex para centrar
                })
                .catch(error => {
                    console.error('Error fetching report details:', error);
                    modalBodyContent.innerHTML = `<p style="color: red;">Error al cargar los detalles: ${error.message}</p>`;
                    modal.style.display = "flex";
                });
        }

        function closeModal() {
            modal.style.display = "none";
        }

        // Cierra el modal si el usuario hace clic fuera de él
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>