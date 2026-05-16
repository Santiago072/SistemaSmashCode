<?php
/**
 * sesion.php
 * Configuración y funciones de gestión de sesión.
 * Seguridad: HttpOnly, SameSite=Strict, expiración por inactividad (RF02).
 */

/* --- Configuración de cookies seguras --- */
ini_set('session.cookie_httponly', 1);       // Evita acceso JS a la cookie
ini_set('session.cookie_samesite', 'Strict'); // Protección CSRF
ini_set('session.use_strict_mode', 1);        // Solo IDs de sesión generados por el servidor
ini_set('session.gc_maxlifetime', 1800);      // 30 minutos de inactividad

define('TIEMPO_INACTIVIDAD', 1800); // 30 minutos en segundos

/**
 * Inicia la sesión de forma segura.
 */
function iniciarSesion(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Verificar expiración por inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        if (time() - $_SESSION['ultima_actividad'] > TIEMPO_INACTIVIDAD) {
            cerrarSesion();
            return;
        }
    }
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Cierra la sesión y destruye todos los datos.
 */
function cerrarSesion(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Verifica si el usuario está autenticado.
 */
function estaAutenticado(): bool {
    iniciarSesion();
    return isset($_SESSION['usuario_id']);
}

/**
 * Redirige al login si el usuario no está autenticado.
 */
function requerirAutenticacion(): void {
    if (!estaAutenticado()) {
        header('Location: /proyecto_smashcode/modulos/auth/login.php');
        exit;
    }
}

/**
 * Verifica si el usuario tiene el rol requerido.
 * @param string|array $roles Rol o arreglo de roles permitidos
 */
function requerirRol($roles): void {
    requerirAutenticacion();
    $roles = (array) $roles;
    if (!in_array($_SESSION['rol'] ?? '', $roles, true)) {
        http_response_code(403);
        header('Location: /proyecto_smashcode/index.php?error=acceso_denegado');
        exit;
    }
}

/**
 * Obtiene el rol del usuario en sesión.
 */
function obtenerRolSesion(): string {
    return $_SESSION['rol'] ?? '';
}
