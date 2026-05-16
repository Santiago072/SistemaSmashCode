<?php
/**
 * funciones.php
 * Funciones utilitarias globales del sistema.
 * Incluye sanitización, redirección y generación de tokens.
 */

/**
 * Sanitiza una cadena para prevenir XSS.
 * @param string $valor Cadena de entrada
 * @return string Cadena segura
 */
function limpiar(string $valor): string {
    return htmlspecialchars(strip_tags(trim($valor)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Genera un token CSRF y lo guarda en sesión.
 * @return string Token generado
 */
function generarTokenCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida el token CSRF enviado por el formulario.
 * @param string $token Token recibido del formulario
 * @return bool
 */
function validarTokenCSRF(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Genera un UUID v4 compatible con PHP 7+.
 * @return string UUID
 */
function generarUUID(): string {
    $datos = random_bytes(16);
    $datos[6] = chr(ord($datos[6]) & 0x0f | 0x40);
    $datos[8] = chr(ord($datos[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($datos), 4));
}

/**
 * Redirige a una URL relativa del proyecto.
 * @param string $ruta Ruta relativa
 */
function redirigir(string $ruta): void {
    header('Location: /proyecto_smashcode/' . ltrim($ruta, '/'));
    exit;
}

/**
 * Formatea un número para mostrar puntos XP (ej: 1240 → 1.240).
 * @param int $puntos
 * @return string
 */
function formatearXP(int $puntos): string {
    return number_format($puntos, 0, ',', '.');
}
