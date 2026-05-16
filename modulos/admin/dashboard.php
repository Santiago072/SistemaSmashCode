<?php
/**
 * dashboard.php — Panel de administrador
 * Estadísticas generales de la plataforma (HU04, RF04, RF32).
 */

require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requerirRol('admin');

$pdo = obtenerConexion();

/* ── KPIs principales ── */
$totalUsuarios   = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'aprendiz'")->fetchColumn();
$aprendicesActivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'aprendiz' AND activo = 1")->fetchColumn();
$totalXP         = $pdo->query("SELECT COALESCE(SUM(xp_puntos),0) FROM usuarios")->fetchColumn();
$quizzesCompletos = $pdo->query("SELECT COUNT(*) FROM intento_quiz WHERE aprobado = 1")->fetchColumn();

/* ── Actividad reciente: últimos intentos de quiz ── */
$actividad = $pdo->query(
    "SELECT u.nombre_completo, iq.puntaje, iq.aprobado, iq.creado_en, r.titulo AS rap_titulo
     FROM intento_quiz iq
     JOIN usuarios u ON u.id = iq.usuario_id
     JOIN quiz q     ON q.id = iq.quiz_id
     JOIN rap r      ON r.id = q.rap_id
     ORDER BY iq.creado_en DESC LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Admin SmashCode</title>
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .icono-azul   { background: rgba(46,134,193,0.15);  color: var(--azul-claro); }
    .icono-verde  { background: rgba(30,132,73,0.15);   color: var(--verde-acento); }
    .icono-oro    { background: rgba(243,156,18,0.15);  color: var(--acento-oro); }
    .icono-lila   { background: rgba(155,89,182,0.15);  color: var(--acento-lila); }
    .barra-chart {
      display: flex; align-items: flex-end; gap: 6px;
      height: 120px; padding-top: 10px;
    }
    .barra-chart .columna {
      flex: 1; background: var(--verde-salud);
      border-radius: 4px 4px 0 0;
      min-height: 8px;
      transition: background 0.3s;
      cursor: default;
      position: relative;
    }
    .barra-chart .columna:hover { background: var(--verde-acento); }
    .barra-chart .columna.destacada { background: var(--verde-acento); }
    .etiquetas-dias {
      display: flex; gap: 6px; margin-top: 6px;
    }
    .etiquetas-dias span {
      flex: 1; text-align: center;
      font-size: 0.65rem; color: var(--texto-tenue);
    }
    .punto-actividad {
      width: 10px; height: 10px; border-radius: 50%;
      display: inline-block; margin-right: 8px;
    }
    .punto-verde  { background: var(--verde-acento); }
    .punto-azul   { background: var(--azul-claro); }
    .punto-oro    { background: var(--acento-oro); }
    .item-actividad {
      padding: 10px 0;
      border-bottom: 1px solid var(--borde-sutil);
      font-size: var(--texto-xs);
      color: var(--texto-secundario);
    }
    .item-actividad:last-child { border-bottom: none; }
    .item-actividad strong { color: var(--texto-principal); }
    .tiempo-actividad { color: var(--texto-tenue); float: right; }
    .estado-operativo {
      background: rgba(30,132,73,0.15);
      border: 1px solid var(--borde-activo);
      color: var(--verde-acento);
      padding: 4px 12px;
      border-radius: var(--radio-full);
      font-size: var(--texto-xs);
      font-weight: 600;
    }
  </style>
</head>
<body>
<div class="contenedor-app">

  <!-- Barra lateral admin -->
  <nav class="barra-lateral" aria-label="Navegación administrador">
    <div class="logo-app">
      <div class="logo-icono">🐧</div>
      <div>
        <div class="logo-nombre">Smash<span>Code</span></div>
        <div style="font-size:0.6rem; color:var(--gris-medio); padding-left:2px;">PANEL ADMIN</div>
      </div>
    </div>

    <ul class="nav-lateral">
      <li><span class="nav-grupo-titulo">General</span></li>
      <li>
        <a href="dashboard.php" class="nav-enlace activo" aria-current="page">
          <i class="fas fa-gauge-high nav-icono"></i><span>Dashboard</span>
        </a>
      </li>

      <li><span class="nav-grupo-titulo">Plataforma</span></li>
      <li>
        <a href="usuarios.php" class="nav-enlace">
          <i class="fas fa-users nav-icono"></i><span>Usuarios</span>
          <span class="nav-badge"><?= $totalUsuarios ?></span>
        </a>
      </li>
      <li>
        <a href="niveles.php" class="nav-enlace">
          <i class="fas fa-layer-group nav-icono"></i><span>Niveles</span>
        </a>
      </li>
      <li>
        <a href="raps.php" class="nav-enlace">
          <i class="fas fa-file-lines nav-icono"></i><span>RAPs</span>
        </a>
      </li>
      <li>
        <a href="vocabulario.php" class="nav-enlace">
          <i class="fas fa-spell-check nav-icono"></i><span>Vocabulario</span>
        </a>
      </li>
      <li>
        <a href="quizzes.php" class="nav-enlace">
          <i class="fas fa-question-circle nav-icono"></i><span>Quizzes</span>
        </a>
      </li>
      <li>
        <a href="glosario.php" class="nav-enlace">
          <i class="fas fa-book-medical nav-icono"></i><span>Glosario</span>
        </a>
      </li>

      <li><span class="nav-grupo-titulo">Reportes</span></li>
      <li>
        <a href="analytics.php" class="nav-enlace">
          <i class="fas fa-chart-line nav-icono"></i><span>Analytics</span>
        </a>
      </li>
      <li>
        <a href="configuracion.php" class="nav-enlace">
          <i class="fas fa-gear nav-icono"></i><span>Configuración</span>
        </a>
      </li>
      <li>
        <a href="../../modulos/auth/cerrar-sesion.php" class="nav-enlace" style="color:var(--rojo);">
          <i class="fas fa-right-from-bracket nav-icono"></i><span>Cerrar Sesión</span>
        </a>
      </li>
    </ul>
  </nav>

  <!-- Contenido principal -->
  <main class="contenido-principal">
    <header class="barra-superior">
      <div class="stat-xp">
        <i class="fas fa-bolt"></i> <?= formatearXP((int)$totalXP) ?> XP Total
      </div>
      <div class="avatar-usuario" title="<?= limpiar($_SESSION['nombre']) ?>">
        <?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?>
      </div>
    </header>

    <div class="pagina-contenido">
      <!-- Encabezado -->
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px;">
        <div>
          <h1 class="pagina-titulo">Dashboard</h1>
          <p class="pagina-subtitulo" style="margin-bottom:0;">
            ¡Bienvenido, <?= limpiar(explode(' ', $_SESSION['nombre'])[0]) ?>! 👋
            &nbsp;— Resumen general de la plataforma Smash Code
          </p>
        </div>
        <span class="estado-operativo">Sistema operativo</span>
      </div>

      <!-- KPIs -->
      <div class="grid-estadisticas">
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono icono-azul"><i class="fas fa-users"></i></div>
          <span class="stat-etiqueta">Total Usuarios</span>
          <span class="stat-valor"><?= $totalUsuarios ?></span>
          <span class="stat-cambio">+12 este mes</span>
        </div>
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono icono-verde"><i class="fas fa-person-running"></i></div>
          <span class="stat-etiqueta">Aprendices Activos</span>
          <span class="stat-valor"><?= $aprendicesActivos ?></span>
          <span class="stat-cambio">+8 esta semana</span>
        </div>
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono icono-oro"><i class="fas fa-bolt"></i></div>
          <span class="stat-etiqueta">XP Total Generado</span>
          <span class="stat-valor"><?= $totalXP >= 1000 ? round($totalXP/1000,1).'K' : $totalXP ?></span>
          <span class="stat-cambio">+4.2K hoy</span>
        </div>
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono icono-lila"><i class="fas fa-clipboard-check"></i></div>
          <span class="stat-etiqueta">Quizzes Completados</span>
          <span class="stat-valor"><?= $quizzesCompletos ?></span>
          <span class="stat-cambio">+87 hoy</span>
        </div>
      </div>

      <!-- Gráfico + Actividad reciente -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">

        <!-- Gráfico de barras (quizzes por día — simulado) -->
        <div class="tarjeta">
          <div style="display:flex; justify-content:space-between; margin-bottom:12px;">
            <span style="font-size: var(--texto-sm); font-weight:600;">
              <i class="fas fa-chart-bar" style="color: var(--verde-acento);"></i>
              Quizzes por día
            </span>
            <span style="font-size: var(--texto-xs); color: var(--texto-tenue);">últimos 7 días</span>
          </div>
          <div class="barra-chart" id="grafico-quizzes">
            <!-- Barras generadas por JS -->
          </div>
          <div class="etiquetas-dias">
            <span>L</span><span>M</span><span>X</span>
            <span>J</span><span>V</span><span>S</span><span>D</span>
          </div>
        </div>

        <!-- Actividad reciente -->
        <div class="tarjeta">
          <div style="margin-bottom:12px;">
            <span style="font-size: var(--texto-sm); font-weight:600;">
              <i class="fas fa-clock" style="color: var(--azul-claro);"></i>
              Actividad reciente
            </span>
          </div>
          <?php if (empty($actividad)): ?>
            <p style="color: var(--texto-tenue); font-size: var(--texto-xs);">
              Aún no hay actividad registrada.
            </p>
          <?php else: ?>
            <?php foreach ($actividad as $a): ?>
            <div class="item-actividad">
              <span class="punto-actividad <?= $a['aprobado'] ? 'punto-verde' : 'punto-oro' ?>"></span>
              <strong><?= limpiar($a['nombre_completo']) ?></strong>
              completó <strong><?= limpiar($a['rap_titulo']) ?></strong> —
              <?= number_format($a['puntaje'], 0) ?>%
              <span class="tiempo-actividad">
                <?= date('d/m H:i', strtotime($a['creado_en'])) ?>
              </span>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div><!-- /grid -->
    </div><!-- /pagina-contenido -->
  </main>
</div>

<script>
  /* Gráfico de barras simple con datos simulados */
  const datos = [12, 8, 15, 22, 18, 5, 9];
  const max   = Math.max(...datos);
  const cont  = document.getElementById('grafico-quizzes');
  datos.forEach((v, i) => {
    const col = document.createElement('div');
    col.className = 'columna' + (i === 3 ? ' destacada' : '');
    col.style.height = (v / max * 100) + '%';
    col.title = v + ' quizzes';
    cont.appendChild(col);
  });
</script>
</body>
</html>
