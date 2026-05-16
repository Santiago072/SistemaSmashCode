<?php
/**
 * cerrar-sesion.php — Cierre seguro de sesión
 */
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
cerrarSesion();
redirigir('modulos/auth/login.php');
