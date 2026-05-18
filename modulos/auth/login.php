<?php
/**
 * login.php — Módulo de autenticación con diseño Duolingo + pingüino
 * RF01, RF02, RF06
 */
require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';
require_once '../../includes/correo.php';
require_once '../../vendor/autoload.php';
use Firebase\JWT\JWT;

// En un entorno de producción, forzar HTTPS
// if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
//     exit();
// }

iniciarSesion();

if (estaAutenticado()) {
    $rol = obtenerRolSesion();
    if ($rol === 'admin')       redirigir('modulos/admin/dashboard.php');
    elseif ($rol === 'instructor') redirigir('modulos/instructor/dashboard.php');
    else                           redirigir('index.php');
}

$error  = '';
$exito  = '';
$accion = $_GET['accion'] ?? 'ingresar';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Solicitud inválida. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? 'ingresar';

        if ($accion === 'ingresar') {
            $correo    = limpiar($_POST['correo'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            if (empty($correo) || empty($contrasena)) {
                $error = 'Completa todos los campos.';
            } else {
                $pdo  = obtenerConexion();
                $stmt = $pdo->prepare('SELECT id, nombre_completo, contrasena, rol, activo, bloqueado, intentos_fallidos FROM usuarios WHERE correo = ? LIMIT 1');
                $stmt->execute([$correo]);
                $usuario = $stmt->fetch();

                if (!$usuario) {
                    $error = 'Correo o contraseña incorrectos.';
                } elseif ($usuario['bloqueado']) {
                    $error = 'Cuenta bloqueada. Revisa tu correo.';
                } elseif (!$usuario['activo']) {
                    $error = 'Cuenta suspendida. Contacta al administrador.';
                } elseif (!password_verify($contrasena, $usuario['contrasena'])) {
                    $intentos = $usuario['intentos_fallidos'] + 1;
                    $bloquear = $intentos >= 5 ? 1 : 0;
                    $pdo->prepare('UPDATE usuarios SET intentos_fallidos=?, bloqueado=? WHERE id=?')
                        ->execute([$intentos, $bloquear, $usuario['id']]);
                    if ($bloquear) {
                        $error = 'Cuenta bloqueada por demasiados intentos.';
                        enviarCorreo(
                            $correo,
                            'Alerta de Seguridad - Cuenta Bloqueada',
                            '<h1>Cuenta Bloqueada</h1><p>Tu cuenta ha sido bloqueada tras 5 intentos fallidos de inicio de sesión. Por favor, restablece tu contraseña para recuperar el acceso.</p>'
                        );
                    } else {
                        $error = 'Contraseña incorrecta. Intento ' . $intentos . ' de 5.';
                    }
                } else {
                    $pdo->prepare('UPDATE usuarios SET intentos_fallidos=0 WHERE id=?')->execute([$usuario['id']]);
                    session_regenerate_id(true);
                    $_SESSION['usuario_id']       = $usuario['id'];
                    $_SESSION['nombre']           = $usuario['nombre_completo'];
                    $_SESSION['rol']              = $usuario['rol'];
                    $_SESSION['ultima_actividad'] = time();

                    // Generar token JWT (secreto en producción debe ser variable de entorno)
                    $secret_key = 'smashcode_super_secret_key';
                    $payload = [
                        'iss' => 'smashcode',
                        'aud' => 'smashcode_users',
                        'iat' => time(),
                        'nbf' => time(),
                        'exp' => time() + 1800, // 30 min
                        'data' => [
                            'id' => $usuario['id'],
                            'rol' => $usuario['rol']
                        ]
                    ];
                    $jwt = JWT::encode($payload, $secret_key, 'HS256');
                    $_SESSION['jwt_token'] = $jwt;

                    if ($usuario['rol'] === 'admin')       redirigir('modulos/admin/dashboard.php');
                    elseif ($usuario['rol'] === 'instructor') redirigir('modulos/instructor/dashboard.php');
                    else                                      redirigir('index.php');
                }
            }
        } elseif ($accion === 'registrar') {
            $nombre    = limpiar($_POST['nombre_completo'] ?? '');
            $correo    = limpiar($_POST['correo'] ?? '');
            $ficha     = limpiar($_POST['ficha_sena'] ?? '');
            $programa  = limpiar($_POST['programa_id'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            if (empty($nombre) || empty($correo) || empty($contrasena)) {
                $error = 'Nombre, correo y contraseña son obligatorios.';
            } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $error = 'El correo no tiene un formato válido.';
            } elseif (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena)) {
                $error = 'La contraseña debe tener mínimo 8 caracteres, 1 mayúscula y 1 número.';
            } else {
                $pdo  = obtenerConexion();
                $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE correo=? LIMIT 1');
                $stmt->execute([$correo]);
                if ($stmt->fetch()) {
                    $error = 'Este correo ya está registrado.';
                } else {
                    $hash = password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 12]);
                    $id   = generarUUID();
                    $pdo->prepare('INSERT INTO usuarios (id,nombre_completo,correo,contrasena,ficha_sena,programa_id,rol) VALUES (?,?,?,?,?,?,"aprendiz")')
                        ->execute([$id, $nombre, $correo, $hash, $ficha ?: null, $programa ?: null]);
                    $exito  = '¡Cuenta creada! Ya puedes iniciar sesión.';
                    $accion = 'ingresar';
                }
            }
        }
    }
}

$pdo      = obtenerConexion();
$programas = $pdo->query('SELECT id, nombre FROM programa_formacion ORDER BY nombre')->fetchAll();
$csrf     = generarTokenCSRF();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingresar — SmashCode</title>
  <meta name="description" content="Plataforma de inglés clínico para Enfermería SENA.">
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<main class="pagina-auth">
  <div class="contenedor-auth animar-entrada">

    <!-- Panel izquierdo decorativo con pingüino -->
    <div class="panel-auth-izq">
      <!-- Pingüino SVG inline -->
      <div class="mascota" style="font-size:0;">
        <svg width="110" height="130" viewBox="0 0 110 130" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-bottom:12px;">
          <!-- Cuerpo -->
          <ellipse cx="55" cy="78" rx="38" ry="44" fill="#1A1A2E"/>
          <!-- Panza blanca -->
          <ellipse cx="55" cy="84" rx="24" ry="30" fill="#FFFFFF"/>
          <!-- Cabeza -->
          <ellipse cx="55" cy="38" rx="28" ry="28" fill="#1A1A2E"/>
          <!-- Cara blanca -->
          <ellipse cx="55" cy="42" rx="18" ry="18" fill="#FFFFFF"/>
          <!-- Ojo izq -->
          <circle cx="47" cy="36" r="6" fill="#FFFFFF"/>
          <circle cx="47" cy="36" r="3.5" fill="#1A1A2E"/>
          <circle cx="48.5" cy="34.5" r="1.2" fill="#FFFFFF"/>
          <!-- Ojo der -->
          <circle cx="63" cy="36" r="6" fill="#FFFFFF"/>
          <circle cx="63" cy="36" r="3.5" fill="#1A1A2E"/>
          <circle cx="64.5" cy="34.5" r="1.2" fill="#FFFFFF"/>
          <!-- Pico -->
          <ellipse cx="55" cy="48" rx="6" ry="4" fill="#FF9600"/>
          <!-- Ala izq -->
          <ellipse cx="20" cy="75" rx="10" ry="22" fill="#1A1A2E" transform="rotate(-15 20 75)"/>
          <!-- Ala der -->
          <ellipse cx="90" cy="75" rx="10" ry="22" fill="#1A1A2E" transform="rotate(15 90 75)"/>
          <!-- Pies -->
          <ellipse cx="43" cy="120" rx="12" ry="6" fill="#FF9600"/>
          <ellipse cx="67" cy="120" rx="12" ry="6" fill="#FF9600"/>
          <!-- Gorra (enfermería) -->
          <rect x="34" y="14" width="42" height="10" rx="5" fill="#FFFFFF"/>
          <rect x="51" y="8" width="8" height="20" rx="4" fill="#FF4B4B"/>
          <rect x="34" y="16" width="42" height="4" rx="2" fill="#E5E5E5"/>
        </svg>
      </div>
      <h1 class="titulo-auth">Bienvenido a<br>SmashCode</h1>
      <p class="subtitulo-auth">Aprende inglés médico-clínico y mejora tu comunicación en enfermería.</p>
      <div class="etiquetas-auth">
        <span class="etiqueta-auth">⚡ Gamificado</span>
        <span class="etiqueta-auth">📚 6 Niveles</span>
        <span class="etiqueta-auth">✅ SENA</span>
      </div>
    </div>

    <!-- Panel del formulario -->
    <div class="panel-auth-der">
      <h2 class="titulo-formulario"><?= $accion === 'registrar' ? 'Crea tu cuenta' : 'Inicia sesión' ?></h2>
      <p class="subtitulo-formulario">Ingresa al mundo del inglés clínico para enfermería.</p>

      <!-- Tabs rol -->
      <div class="tabs-rol" role="tablist">
        <button class="tab-rol activo" id="tab-aprendiz" role="tab">Aprendiz</button>
        <button class="tab-rol" id="tab-instructor" role="tab">Instructor</button>
        <button class="tab-rol" id="tab-admin" role="tab">Admin</button>
      </div>

      <!-- Tabs acción -->
      <div class="tabs-accion">
        <button class="tab-accion <?= $accion === 'ingresar'  ? 'activo' : '' ?>" id="btn-ingresar"  type="button">Ingresar</button>
        <button class="tab-accion <?= $accion === 'registrar' ? 'activo' : '' ?>" id="btn-registrar" type="button">Registrarse</button>
      </div>

      <?php if ($error): ?>
        <div class="alerta alerta-error"><i class="fas fa-circle-exclamation"></i><?= $error ?></div>
      <?php endif; ?>
      <?php if ($exito): ?>
        <div class="alerta alerta-exito"><i class="fas fa-circle-check"></i><?= $exito ?></div>
      <?php endif; ?>

      <!-- FORM INGRESAR -->
      <form id="formulario-ingresar" method="POST" action="login.php" style="display:<?= $accion==='ingresar'?'block':'none' ?>;" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="accion" value="ingresar">

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="correo-ingreso">Correo</label>
          <div class="contenedor-input">
            <i class="fas fa-envelope icono-input"></i>
            <input type="email" id="correo-ingreso" name="correo" class="campo-input" placeholder="nombre@sena.edu.co" required>
          </div>
        </div>
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="clave-ingreso">Contraseña</label>
          <div class="contenedor-input">
            <i class="fas fa-lock icono-input"></i>
            <input type="password" id="clave-ingreso" name="contrasena" class="campo-input" placeholder="Tu contraseña" required>
          </div>
        </div>
        <div style="text-align:right; margin-bottom:16px;">
          <a href="recuperar.php" style="font-size:0.8rem; color:var(--azul); font-weight:700;">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" class="btn btn-verde">
          <i class="fas fa-right-to-bracket"></i> Ingresar
        </button>
        <div class="separador-o">o ingresa con</div>
        <div class="grupo-botones-social">
          <button type="button" class="btn btn-social"><i class="fab fa-google"></i> Google</button>
          <button type="button" class="btn btn-social"><i class="fab fa-facebook-f"></i> Facebook</button>
        </div>
      </form>

      <!-- FORM REGISTRO -->
      <form id="formulario-registro" method="POST" action="login.php?accion=registrar" style="display:<?= $accion==='registrar'?'block':'none' ?>;" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="accion" value="registrar">

        <div class="grupo-campo">
          <label class="etiqueta-campo" for="nombre-registro">Nombre completo</label>
          <div class="contenedor-input">
            <i class="fas fa-user icono-input"></i>
            <input type="text" id="nombre-registro" name="nombre_completo" class="campo-input" placeholder="p. ej. Ana García" required>
          </div>
        </div>
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="correo-registro">Correo institucional</label>
          <div class="contenedor-input">
            <i class="fas fa-envelope icono-input"></i>
            <input type="email" id="correo-registro" name="correo" class="campo-input" placeholder="nombre@sena.edu.co" required>
          </div>
        </div>
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="ficha-registro">Ficha SENA</label>
          <div class="contenedor-input">
            <i class="fas fa-id-card icono-input"></i>
            <input type="text" id="ficha-registro" name="ficha_sena" class="campo-input" placeholder="p. ej. 2234891">
          </div>
          <span class="ayuda-campo">Número de ficha del programa técnico de enfermería</span>
        </div>
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="programa-registro">Programa</label>
          <div class="contenedor-input">
            <i class="fas fa-graduation-cap icono-input"></i>
            <select id="programa-registro" name="programa_id" class="campo-input" style="padding-left:38px;">
              <option value="">Selecciona tu programa</option>
              <?php foreach ($programas as $p): ?>
                <option value="<?= limpiar($p['id']) ?>"><?= limpiar($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grupo-campo">
          <label class="etiqueta-campo" for="clave-registro">Contraseña</label>
          <div class="contenedor-input">
            <i class="fas fa-lock icono-input"></i>
            <input type="password" id="clave-registro" name="contrasena" class="campo-input" placeholder="Mín. 8 caracteres" required>
          </div>
          <span class="ayuda-campo">Incluye al menos 1 mayúscula y 1 número</span>
        </div>

        <button type="submit" class="btn btn-verde">
          <i class="fas fa-user-plus"></i> Crear cuenta
        </button>
        <div class="separador-o">o regístrate con</div>
        <div class="grupo-botones-social">
          <button type="button" class="btn btn-social"><i class="fab fa-google"></i> Google</button>
          <button type="button" class="btn btn-social"><i class="fab fa-facebook-f"></i> Facebook</button>
        </div>
        <p style="font-size:0.72rem; color:var(--gris-medio); text-align:center; margin-top:14px;">
          Al registrarte aceptas nuestros <a href="#" style="color:var(--azul); font-weight:700;">Términos de Servicio</a> y <a href="#" style="color:var(--azul); font-weight:700;">Política de Privacidad</a>.
        </p>
      </form>
    </div>
  </div>
</main>
<script src="../../assets/js/login.js"></script>
</body>
</html>
