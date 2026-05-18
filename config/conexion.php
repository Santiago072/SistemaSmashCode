<?php
/**
 * conexion.php
 * Configuración de la conexión a la base de datos usando PDO.
 * @return PDO Instancia de la conexión
 */

if (file_exists(__DIR__ . '/credenciales.php')) {
    require_once __DIR__ . '/credenciales.php';
} else {
    require_once __DIR__ . '/credenciales.example.php';
}

/**
 * Retorna una conexión PDO configurada con consultas preparadas.
 * Se lanza excepción ante cualquier error (modo ERRMODE_EXCEPTION).
 */
function obtenerConexion(): PDO {
    static $conexion = null; // Patrón singleton liviano

    if ($conexion === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NOMBRE
             . ';charset=' . DB_CHARSET;

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lanza excepciones ante errores SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Resultados como arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Consultas preparadas reales
        ];

        try {
            $conexion = new PDO($dsn, DB_USUARIO, DB_CLAVE, $opciones);
        } catch (PDOException $e) {
            /* En producción: registrar en log, nunca mostrar al usuario */
            error_log('[SmashCode] Error de conexión: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode(['error' => 'Error interno del servidor. Intenta más tarde.']));
        }
    }

    return $conexion;
}
