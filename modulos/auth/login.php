<?php
/**
 * login.php — Módulo de autenticación
 * Maneja inicio de sesión y registro de aprendices.
 * Seguridad: bcrypt, bloqueo tras 5 intentos, tokens CSRF (RF01, RF02).
 */

require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';

iniciarSesion();

/* Si ya está autenticado, redirigir según rol */
if (estaAutenticado()) {
    $rol = obtenerRolSesion();
    if ($rol === 'admin')      redirigir('modulos/admin/dashboard.php');
    elseif ($rol === 'instructor') redirigir('modulos/instructor/dashboard.php');
    else                           redirigir('index.php');
}

$error   = '';
$exito   = '';
$accion  = $_GET['accion'] ?? 'ingresar'; // ingresar | registrar

/* ============================================================
   PROCESAR FORMULARIO
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Validar CSRF */
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Solicitud inválida. Recarga la página e intenta de nuevo.';
    } else {

        $accion = $_POST['accion'] ?? 'ingresar';

        /* --- INICIO DE SESIÓN --- */
        if ($accion === 'ingresar') {
            $correo    = limpiar($_POST['correo'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            if (empty($correo) || empty($contrasena)) {
                $error = 'Completa todos los campos.';
            } else {
                $pdo  = obtenerConexion();
                $stmt = $pdo->prepare(
                    'SELECT id, nombre_completo, contrasena, rol, activo, bloqueado, intentos_fallidos
                     FROM usuarios WHERE correo = ? LIMIT 1'
                );
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch();

                if (!$usuario) {
                    $error = 'Correo o contraseña incorrectos.';
                } elseif ($usuario['bloqueado']) {
                    $error = 'Tu cuenta está bloqueada. Revisa tu correo para desbloquearla.';
                } elseif (!$usuario['activo']) {
                    $error = 'Tu cuenta está suspendida. Contacta al administrador.';
                } elseif (!password_verify($contrasena, $usuario['contrasena'])) {
                    /* Incrementar intentos fallidos — bloquear tras 5 (RF02) */
                    $intentos = $usuario['intentos_fallidos'] + 1;
                    $bloquear = $intentos >= 5 ? 1 : 0;
                    $pdo->prepare(
                        'UPDATE usuarios SET intentos_fallidos = ?, bloqueado = ? WHERE id = ?'
                    )->execute([$intentos, $bloquear, $usuario['id']]);

                    $error = $bloquear
                        ? 'Cuenta bloqueada por demasiados intentos. Revisa tu correo.'
                        : 'Correo o contraseña incorrectos. Intento ' . $intentos . ' de 5.';
                } else {
                    /* Credenciales correctas — iniciar sesión */
                    $pdo->prepare(
                        'UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?'
                    )->execute([$usuario['id']]);

                    session_regenerate_id(true); // Prevenir secuestro de sesión
                    $_SESSION['usuario_id']     = $usuario['id'];
                    $_SESSION['nombre']         = $usuario['nombre_completo'];
                    $_SESSION['rol']            = $usuario['rol'];
                    $_SESSION['ultima_actividad'] = time();

                    /* Redirigir según rol */
                    if ($usuario['rol'] === 'admin')       redirigir('modulos/admin/dashboard.php');
                    elseif ($usuario['rol'] === 'instructor') redirigir('modulos/instructor/dashboard.php');
                    else                                      redirigir('index.php');
                }
            }
        }

        /* --- REGISTRO DE APRENDIZ (RF01) --- */
        elseif ($accion === 'registrar') {
            $nombre     = limpiar($_POST['nombre_completo'] ?? '');
            $correo     = limpiar($_POST['correo'] ?? '');
            $ficha      = limpiar($_POST['ficha_sena'] ?? '');
            $programa   = limpiar($_POST['programa_id'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            /* Validaciones básicas */
            if (empty($nombre) || empty($correo) || empty($contrasena)) {
                $error = 'Nombre, correo y contraseña son obligatorios.';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $error = 'El correo no tiene un formato válido.';
            } elseif (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena)) {
                $error = 'La contraseña debe tener mínimo 8 caracteres, 1 mayúscula y 1 número.';
            } else {
                $pdo = obtenerConexion();

                /* Verificar correo duplicado */
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ? LIMIT 1');
                $stmt->execute([$correo]);
                if ($stmt->fetch()) {
                    $error = 'Este correo ya está registrado. Intenta iniciar sesión.';
                } else {
                    /* Insertar nuevo aprendiz */
                    $hash = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);
                    $id   = generarUUID();
                    $pdo->prepare(
                        'INSERT INTO usuarios (id, nombre_completo, correo, contrasena, ficha_sena, programa_id, rol)
                         VALUES (?, ?, ?, ?, ?, ?, "aprendiz")'
                    )->execute([$id, $nombre, $correo, $hash, $ficha ?: null, $programa ?: null]);

                    $exito = '¡Cuenta creada! Ya puedes iniciar sesión.';
                    $accion = 'ingresar';
                }
            }
        }
    }
}

/* Obtener programas para el selector del registro */
$pdo      = obtenerConexion();
$programas = $pdo->query('SELECT id, nombre FROM programa_formacion ORDER BY nombre')->fetchAll();

$csrf = generarTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingresar — SmashCode Enfermería SENA</title>
  <meta name="description" content="Plataforma de inglés clínico para el programa de Enfermería SENA.">
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <!-- Iconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<main class="pagina-auth">
  <div class="contenedor-auth animar-entrada">

    <!-- Panel decorativo izquierdo -->
    <div class="panel-auth-izq">
      <div class="mascota">🦉</div>
      <h1 class="titulo-auth">Bienvenido a<br>Smash Code</h1>
      <p class="subtitulo-auth">
        Aprende inglés médico-clínico con Owly y mejora tu comunicación en enfermería.
      </p>
      <div class="etiquetas-auth">
        <span class="etiqueta-auth">⚡ Gamificado</span>
        <span class="etiqueta-auth">📚 6 Niveles</span>
        <span class="etiqueta-auth">✅ SENA</span>
      </div>
    </div>

    <!-- Panel del formulario -->
    <div class="panel-auth-der">
      <h2 class="titulo-formulario">
        <?= $accion === 'registrar' ? 'Crea tu cuenta' : 'Inicia sesión' ?>
      </h2>
      <p class="subtitulo-formulario">
        <?= $accion === 'registrar'
            ? 'Ingresa al mundo del inglés clínico para enfermería.'
            : 'Accede a tu plataforma de aprendizaje.' ?>
      </p>

      <!-- Tabs de rol -->
      <div class="tabs-rol" role="tablist" aria-label="Selección de rol">
        <button class="tab-rol activo" id="tab-aprendiz"   role="tab">Aprendiz</button>
        <button class="tab-rol"        id="tab-instructor" role="tab">Instructor</button>
        <button class="tab-rol"        id="tab-admin"      role="tab">Admin</button>
      </div>

      <!-- Tabs acción -->
      <div class="tabs-accion">
        <button class="tab-accion <?= $accion === 'ingresar'  ? 'activo' : '' ?>" id="btn-ingresar"  type="button">Ingresar</button>
        <button class="tab-accion <?= $accion === 'registrar' ? 'activo' : '' ?>" id="btn-registrar" type="button">Registrarse</button>
      </div>

      <!-- Mensajes de error / éxito -->
      <?php if ($error): ?>
        <div class="alerta alerta-error" role="alert">
          <i class="fas fa-circle-exclamation"></i> <?= $error ?>
        </div>
      <?php endif; ?>
      <?php if ($exito): ?>
        <div class="alerta alerta-exito" role="alert">
          <i class="fas fa-circle-check"></i> <?= $exito ?>
        </div>
      <?php endif; ?>

      <!-- ===================== FORMULARIO INGRESAR ===================== -->
      <form id="formulario-ingresar"
            method="POST"
            action="login.php"
            style="display: <?= $accion === 'ingresar' ? 'block' : 'none' ?>;"
            novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="accion"     value="ingresar">

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="correo-ingreso">Correo institucional</label>
          <div class="contenedor-input">
            <i class="fas fa-envelope icono-input"></i>
            <input type="email" id="correo-ingreso" name="correo"
                   class="campo-input"
                   placeholder="nombre@sena.edu.co"
                   required autocomplete="email">
          </div>
        </div>

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="clave-ingreso">Contraseña</label>
          <div class="contenedor-input">
            <i class="fas fa-lock icono-input"></i>
            <input type="password" id="clave-ingreso" name="contrasena"
                   class="campo-input"
                   placeholder="Mín. 8 caracteres"
                   required autocomplete="current-password">
          </div>
        </div>

        <div style="text-align:right; margin-bottom: 18px;">
          <a href="recuperar.php" style="font-size:0.8rem; color: var(--azul-institucional);">
            ¿Olvidaste tu contraseña?
          </a>
        </div>

        <button type="submit" class="btn btn-primario" id="btn-submit-ingreso">
          <i class="fas fa-right-to-bracket"></i> Ingresar
        </button>

        <div class="separador-o">o ingresa con</div>
        <div class="grupo-botones-social">
          <button type="button" class="btn btn-social">
            <i class="fab fa-google"></i> Google
          </button>
          <button type="button" class="btn btn-social">
            <i class="fab fa-facebook"></i> Facebook
          </button>
        </div>
      </form>

      <!-- ===================== FORMULARIO REGISTRO ===================== -->
      <form id="formulario-registro"
            method="POST"
            action="login.php?accion=registrar"
            style="display: <?= $accion === 'registrar' ? 'block' : 'none' ?>;"
            novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="accion"     value="registrar">

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="nombre-registro">Nombre completo</label>
          <div class="contenedor-input">
            <i class="fas fa-user icono-input"></i>
            <input type="text" id="nombre-registro" name="nombre_completo"
                   class="campo-input" placeholder="p. ej. Ana García"
                   required autocomplete="name">
          </div>
        </div>

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="correo-registro">Correo institucional</label>
          <div class="contenedor-input">
            <i class="fas fa-envelope icono-input"></i>
            <input type="email" id="correo-registro" name="correo"
                   class="campo-input" placeholder="nombre@sena.edu.co"
                   required autocomplete="email">
          </div>
        </div>

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="ficha-registro">Ficha SENA</label>
          <div class="contenedor-input">
            <i class="fas fa-id-card icono-input"></i>
            <input type="text" id="ficha-registro" name="ficha_sena"
                   class="campo-input" placeholder="p. ej. 2234891">
          </div>
          <span class="ayuda-campo">Número de ficha del programa técnico de enfermería SENA</span>
        </div>

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="programa-registro">Programa de formación</label>
          <div class="contenedor-input">
            <i class="fas fa-graduation-cap icono-input"></i>
            <select id="programa-registro" name="programa_id" class="campo-input" style="padding-left:38px;">
              <option value="">Selecciona tu programa</option>
              <?php foreach ($programas as $prog): ?>
                <option value="<?= limpiar($prog['id']) ?>"><?= limpiar($prog['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="clave-registro">Contraseña</label>
          <div class="contenedor-input">
            <i class="fas fa-lock icono-input"></i>
            <input type="password" id="clave-registro" name="contrasena"
                   class="campo-input" placeholder="Mín. 8 caracteres"
                   required autocomplete="new-password">
          </div>
          <span class="ayuda-campo">Incluye mayúscula y número</span>
        </div>

        <button type="submit" class="btn btn-primario" id="btn-submit-registro">
          <i class="fas fa-user-plus"></i> Crear cuenta
        </button>

        <div class="separador-o">o regístrate con</div>
        <div class="grupo-botones-social">
          <button type="button" class="btn btn-social">
            <i class="fab fa-google"></i> Google
          </button>
          <button type="button" class="btn btn-social">
            <i class="fab fa-facebook"></i> Facebook
          </button>
        </div>

        <p style="font-size:0.72rem; color:#8BADC8; text-align:center; margin-top:16px;">
          Al registrarte aceptas nuestros
          <a href="#" style="color: var(--azul-institucional);">Términos de Servicio</a> y
          <a href="#" style="color: var(--azul-institucional);">Política de Privacidad</a>.
        </p>
      </form>

    </div><!-- /panel-auth-der -->
  </div><!-- /contenedor-auth -->
</main>

<script src="../../assets/js/login.js"></script>
</body>
</html>
