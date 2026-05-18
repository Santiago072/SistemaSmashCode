<?php
/**
 * credenciales.example.php
 * Plantilla de credenciales. Copia este archivo como 'credenciales.php' 
 * y ajusta las constantes a tu entorno local.
 */

// Base de Datos
define('DB_HOST', 'localhost');
define('DB_NOMBRE', 'smash_code');
define('DB_USUARIO', 'root');
define('DB_CLAVE', '');
define('DB_CHARSET', 'utf8mb4');

// Configuración de SMTP (Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'tu_correo_gmail@gmail.com');
define('SMTP_PASS', 'tu_contrasena_de_aplicacion'); 
define('SMTP_PORT', 587);

// Configuración de JWT (Cualquier clave de al menos 32 caracteres)
define('JWT_SECRET', 'AQUI_COLOCA_UNA_CLAVE_DE_MINIMO_32_CARACTERES');
