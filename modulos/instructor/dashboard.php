<?php
/**
 * dashboard.php — Panel del Instructor
 * Seguimiento de aprendices por nivel/RAP con exportación CSV (HU06, RF32, RF28).
 */

require_once '../../config/conexion.php';
require_once '../../config/sesion.php';
require_once '../../includes/funciones.php';

iniciarSesion();
requerirRol('instructor');

$pdo = obtenerConexion();

/* KPIs del instructor */
$totalAprendices = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'aprendiz' AND activo = 1")->fetchColumn();
$completaronAlgo = $pdo->query("SELECT COUNT(DISTINCT usuario_id) FROM progreso WHERE completado = 1")->fetchColumn();
$promedioQuiz    = $pdo->query("SELECT COALESCE(AVG(puntaje), 0) FROM intento_quiz")->fetchColumn();

/* Listado de aprendices con su progreso */
$aprendices = $pdo->query(
    "SELECT u.id, u.nombre_completo, u.correo, u.xp_puntos,
            COUNT(p.id) AS raps_iniciados,
            SUM(p.completado) AS raps_completados,
            COALESCE(AVG(p.porcentaje), 0) AS avance_promedio
     FROM usuarios u
     LEFT JOIN progreso p ON p.usuario_id = u.id
     WHERE u.rol = 'aprendiz' AND u.activo = 1
     GROUP BY u.id
     ORDER BY avance_promedio DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Instructor — SmashCode</title>
  <link rel="stylesheet" href="../../assets/css/estilos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .tabla-aprendices { width: 100%; border-collapse: collapse; }
    .tabla-aprendices th {
      text-align: left; font-size: 0.7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.07em;
      color: var(--texto-tenue); padding: 10px 14px;
      border-bottom: 1px solid var(--borde-sutil);
    }
    .tabla-aprendices td {
      padding: 12px 14px; font-size: var(--texto-sm);
      border-bottom: 1px solid var(--borde-sutil);
      color: var(--texto-secundario);
    }
    .tabla-aprendices tr:hover td { background: var(--fondo-hover); }
    .tabla-aprendices td:first-child { color: var(--texto-principal); font-weight: 500; }
    .progreso-mini {
      display: flex; align-items: center; gap: 8px;
    }
    .progreso-mini .barra {
      flex: 1; height: 6px; background: var(--fondo-input);
      border-radius: var(--radio-full); overflow: hidden;
    }
    .progreso-mini .barra .relleno {
      height: 100%;
      border-radius: var(--radio-full);
    }
    .nivel-bajo    { background: var(--acento-rojo); }
    .nivel-medio   { background: var(--acento-oro); }
    .nivel-alto    { background: var(--verde-acento); }
    .chip-estado {
      padding: 3px 10px; border-radius: var(--radio-full);
      font-size: 0.65rem; font-weight: 700;
    }
    .chip-activo   { background: rgba(46,204,113,0.15); color: var(--verde-acento); }
    .chip-riesgo   { background: rgba(231,76,60,0.15);  color: var(--acento-rojo); }
  </style>
</head>
<body>
<div class="contenedor-app">

  <!-- Barra lateral instructor -->
  <nav class="barra-lateral" aria-label="Navegación instructor">
    <div class="logo-app">
      <div class="logo-icono">🐧</div>
      <div>
        <div class="logo-nombre">Smash<span>Code</span></div>
        <div style="font-size:0.6rem; color:var(--gris-medio); padding-left:2px;">INSTRUCTOR</div>
      </div>
    </div>
    <ul class="nav-lateral">
      <li>
        <a href="dashboard.php" class="nav-enlace activo" aria-current="page">
          <i class="fas fa-gauge-high nav-icono"></i><span>Dashboard</span>
        </a>
      </li>
      <li>
        <a href="mis-aprendices.php" class="nav-enlace">
          <i class="fas fa-users nav-icono"></i><span>Mis Aprendices</span>
        </a>
      </li>
      <li>
        <a href="resultados-quiz.php" class="nav-enlace">
          <i class="fas fa-clipboard-list nav-icono"></i><span>Resultados Quiz</span>
        </a>
      </li>
      <li>
        <a href="exportar.php" class="nav-enlace">
          <i class="fas fa-file-csv nav-icono"></i><span>Exportar CSV</span>
        </a>
      </li>
      <li>
        <a href="../../modulos/auth/cerrar-sesion.php" class="nav-enlace" style="color:var(--rojo);">
          <i class="fas fa-right-from-bracket nav-icono"></i><span>Cerrar Sesión</span>
        </a>
      </li>
    </ul>
  </nav>

  <main class="contenido-principal">
    <header class="barra-superior">
      <div class="avatar-usuario" title="<?= limpiar($_SESSION['nombre']) ?>">
        <?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?>
      </div>
    </header>

    <div class="pagina-contenido">
      <h1 class="pagina-titulo">Panel del Instructor</h1>
      <p class="pagina-subtitulo">Seguimiento de aprendices — <?= limpiar($_SESSION['nombre']) ?></p>

      <!-- KPIs -->
      <div class="grid-estadisticas">
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono" style="background:rgba(46,134,193,0.15); color:var(--azul-claro);">
            <i class="fas fa-users"></i>
          </div>
          <span class="stat-etiqueta">Total Aprendices</span>
          <span class="stat-valor"><?= $totalAprendices ?></span>
        </div>
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono" style="background:rgba(30,132,73,0.15); color:var(--verde-acento);">
            <i class="fas fa-trophy"></i>
          </div>
          <span class="stat-etiqueta">Completaron RAP</span>
          <span class="stat-valor"><?= $completaronAlgo ?></span>
        </div>
        <div class="tarjeta tarjeta-stat">
          <div class="stat-icono" style="background:rgba(243,156,18,0.15); color:var(--acento-oro);">
            <i class="fas fa-star"></i>
          </div>
          <span class="stat-etiqueta">Promedio Quiz</span>
          <span class="stat-valor"><?= number_format($promedioQuiz, 1) ?>%</span>
        </div>
      </div>

      <!-- Tabla de aprendices -->
      <div class="tarjeta">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
          <span style="font-weight:600; font-size:var(--texto-sm);">
            <i class="fas fa-list-ul" style="color:var(--azul-claro);"></i>
            Lista de Aprendices
          </span>
          <a href="exportar.php" class="btn btn-primario" style="width:auto; padding: 8px 16px; font-size:0.8rem;">
            <i class="fas fa-download"></i> Exportar CSV
          </a>
        </div>

        <?php if (empty($aprendices)): ?>
          <p style="color: var(--texto-tenue); font-size: var(--texto-sm);">
            No hay aprendices registrados aún.
          </p>
        <?php else: ?>
        <div style="overflow-x: auto;">
          <table class="tabla-aprendices" id="tabla-aprendices">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>RAPs Iniciados</th>
                <th>RAPs Completados</th>
                <th>Avance Promedio</th>
                <th>XP</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aprendices as $a):
                $avance = (float) $a['avance_promedio'];
                $nivelClase = $avance >= 70 ? 'nivel-alto' : ($avance >= 40 ? 'nivel-medio' : 'nivel-bajo');
                $chipClase  = $avance >= 40 ? 'chip-activo' : 'chip-riesgo';
                $chipTexto  = $avance >= 40 ? 'En progreso' : 'En riesgo';
              ?>
              <tr>
                <td><?= limpiar($a['nombre_completo']) ?></td>
                <td><?= limpiar($a['correo']) ?></td>
                <td style="text-align:center;"><?= $a['raps_iniciados'] ?></td>
                <td style="text-align:center;"><?= $a['raps_completados'] ?></td>
                <td>
                  <div class="progreso-mini">
                    <div class="barra">
                      <div class="relleno <?= $nivelClase ?>" style="width:<?= min(100, $avance) ?>%"></div>
                    </div>
                    <span style="font-size:0.7rem; min-width:32px;"><?= number_format($avance,0) ?>%</span>
                  </div>
                </td>
                <td><?= formatearXP((int)$a['xp_puntos']) ?></td>
                <td><span class="chip-estado <?= $chipClase ?>"><?= $chipTexto ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
