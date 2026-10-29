<?php
require_once "config/conexion.php";

function h($valor)
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

function fecha_cl($fecha)
{
    return $fecha ? date("d/m/Y", strtotime($fecha)) : "-";
}

function fecha_hora_cl($fecha)
{
    return $fecha ? date("d/m/Y H:i", strtotime($fecha)) : "-";
}

$vista = $_GET["vista"] ?? "inicio";
$mensaje = $_GET["mensaje"] ?? "";
$tipo_mensaje = $_GET["tipo"] ?? "ok";
$menu_activo = $vista;

if ($vista === "actividad") {
    $menu_activo = "actividades";
}

if ($vista === "implemento" || $vista === "editar_implemento") {
    $menu_activo = "implementos";
}

$resumen = $conexion->query(
    "SELECT
        (SELECT COUNT(*) FROM implemento) AS implementos,
        (SELECT COALESCE(SUM(cantidad_stock), 0) FROM implemento) AS stock,
        (SELECT COUNT(*) FROM actividad) AS actividades,
        (
            SELECT COUNT(*)
            FROM asignacion
            WHERE estado IN ('Reservada', 'Entregada', 'Parcial', 'En custodia')
        ) AS asignaciones,
        (
            SELECT COUNT(*)
            FROM incidencia
            WHERE estado = 'Pendiente'
        ) AS incidencias"
)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de implementos</title>
    <link rel="stylesheet" href="css/estilos.css">

    <style>
        .titulo-items { margin: 4px 0 12px; }
        .titulo-items strong { display:block; font-size:13px; margin-bottom:3px; }
        .titulo-items span { color:#64748b; font-size:11px; }
        .asignacion-items { display:grid; gap:10px; margin-bottom:12px; }
        .fila-implemento { display:grid; grid-template-columns:minmax(0,1fr) 95px auto; gap:9px; align-items:end; padding:12px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .fila-implemento label { margin-bottom:0; }
        .quitar-fila { min-height:41px; padding:0 11px; border:1px solid #fecaca; background:#fff1f2; color:#b91c1c; border-radius:8px; cursor:pointer; font-size:11px; font-weight:700; }
        .boton-agregar-item { width:100%; margin-bottom:18px; }
        .grupos-responsable { padding:18px; display:grid; gap:14px; }
        .grupo-responsable { border:1px solid #e2e8f0; border-radius:11px; overflow:hidden; background:#fff; }
        .responsable-head { display:flex; justify-content:space-between; gap:14px; align-items:flex-start; padding:15px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
        .responsable-head h3 { font-size:15px; margin:3px 0 4px; }
        .responsable-head p { color:#64748b; font-size:11px; line-height:1.4; }
        .mini-etiqueta { color:#2563eb; font-size:9px; text-transform:uppercase; letter-spacing:.7px; font-weight:700; }
        .items-responsable { display:grid; }
        .item-responsable { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:13px 16px; border-top:1px solid #e2e8f0; }
        .item-responsable:first-child { border-top:0; }
        .item-responsable strong { display:block; font-size:12px; margin-bottom:4px; }
        .item-responsable span:not(.badge) { color:#64748b; font-size:10px; }
        .resumen-devolucion { display:grid; grid-template-columns:repeat(2,1fr); gap:7px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; padding:11px; margin:12px 0; }
        .resumen-devolucion span { color:#64748b; font-size:10px; }
        .resumen-devolucion strong { color:#0f172a; }
        .ayuda-formulario { color:#64748b; font-size:10px; line-height:1.45; margin-top:9px; }
        .acciones-tabla { display:flex; flex-direction:column; align-items:flex-start; gap:6px; min-width:95px; }
        .accion-mini { display:inline-flex; align-items:center; justify-content:center; padding:6px 9px; border-radius:7px; font-size:10px; font-weight:700; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .accion-mini:hover { background:#dbeafe; }
        .datos-implemento { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; padding:20px; }
        .dato-implemento { border:1px solid #e2e8f0; border-radius:10px; padding:14px; background:#f8fafc; }
        .dato-implemento span { display:block; color:#64748b; font-size:10px; margin-bottom:5px; }
        .dato-implemento strong { font-size:16px; }
        .buscador-seccion { padding:14px 18px; border-bottom:1px solid #e2e8f0; background:#fff; }
        .buscador-seccion input { width:100%; min-height:42px; padding:10px 13px; border:1px solid #cbd5e1; border-radius:9px; background:#f8fafc; color:#0f172a; outline:none; }
        .buscador-seccion input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.10); background:#fff; }
        .filtros-historial { display:grid; grid-template-columns:minmax(0,1fr) 190px 170px auto; gap:9px; align-items:end; }
        .filtros-historial label { color:#334155; font-size:9px; font-weight:700; }
        .filtros-historial input, .filtros-historial select { width:100%; min-height:38px; margin-top:5px; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#0f172a; outline:none; }
        .filtros-historial input:focus, .filtros-historial select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
        .boton-limpiar-filtros { min-height:38px; padding:0 12px; border:1px solid #cbd5e1; border-radius:8px; background:#f8fafc; color:#334155; font-size:10px; font-weight:700; cursor:pointer; }
        .boton-limpiar-filtros:hover { background:#f1f5f9; }
        .acordeon-lista { padding:14px 18px 18px; display:grid; gap:10px; }
        .acordeon-responsable { border:1px solid #e2e8f0; border-radius:11px; background:#fff; overflow:hidden; }
        .acordeon-responsable[open] { box-shadow:0 5px 18px rgba(15,23,42,.05); }
        .acordeon-resumen { list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:16px; padding:15px 16px; background:#f8fafc; user-select:none; }
        .acordeon-resumen::-webkit-details-marker { display:none; }
        .acordeon-resumen h3 { margin:3px 0 4px; font-size:14px; }
        .acordeon-resumen p { color:#64748b; font-size:10px; line-height:1.45; }
        .acordeon-derecha { display:flex; align-items:center; gap:10px; flex-shrink:0; }
        .flecha-acordeon { width:25px; height:25px; border-radius:7px; display:flex; align-items:center; justify-content:center; background:#e2e8f0; color:#475569; font-size:13px; transition:transform .18s ease; }
        details[open] > summary .flecha-acordeon { transform:rotate(180deg); }
        .acordeon-contenido { border-top:1px solid #e2e8f0; }
        .item-gestion { border-top:1px solid #e2e8f0; background:#fff; }
        .item-gestion:first-child { border-top:0; }
        .item-gestion[open] { background:#fbfdff; }
        .item-gestion-resumen { list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:14px; padding:12px 16px; user-select:none; }
        .item-gestion-resumen::-webkit-details-marker { display:none; }
        .item-gestion-resumen:hover { background:#f8fafc; }
        .item-gestion-info { min-width:0; }
        .item-gestion-info strong { display:block; font-size:12px; margin-bottom:4px; }
        .item-gestion-info span { color:#64748b; font-size:10px; }
        .item-gestion-derecha { display:flex; align-items:center; gap:9px; flex-shrink:0; }
        .mini-flecha { width:23px; height:23px; border-radius:7px; display:flex; align-items:center; justify-content:center; background:#e2e8f0; color:#475569; font-size:12px; transition:transform .18s ease; }
        .item-gestion[open] > summary .mini-flecha { transform:rotate(180deg); }
        .item-gestion-contenido { border-top:1px solid #e2e8f0; padding:13px 16px 15px; }

        .accion-directa { justify-content:flex-end; }
        .estado-compacto { display:flex; flex-wrap:wrap; gap:7px; margin-bottom:12px; }
        .dato-compacto { min-width:92px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:7px 9px; }
        .dato-compacto span { display:block; color:#64748b; font-size:8px; margin-bottom:2px; text-transform:uppercase; letter-spacing:.35px; }
        .dato-compacto strong { font-size:11px; }
        .accion-entrega-compacta { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .accion-entrega-compacta p { color:#64748b; font-size:10px; }
        .gestion-compacta { display:grid; gap:11px; }
        .gestion-grid { display:grid; grid-template-columns:repeat(4,minmax(110px,1fr)); gap:9px; }
        .gestion-grid label, .gestion-observacion, .subacordeon label { color:#334155; font-size:10px; font-weight:700; }
        .gestion-grid select, .gestion-observacion textarea, .subacordeon select, .subacordeon textarea { width:100%; margin-top:5px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#0f172a; outline:none; }
        .gestion-grid select, .subacordeon select { height:36px; padding:0 9px; }
        .gestion-observacion textarea, .subacordeon textarea { min-height:54px; padding:8px 9px; resize:vertical; }
        .gestion-grid select:focus, .gestion-observacion textarea:focus, .subacordeon select:focus, .subacordeon textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
        .subacordeon { border:1px solid #e2e8f0; border-radius:9px; overflow:hidden; background:#f8fafc; }
        .subacordeon > summary { list-style:none; cursor:pointer; padding:10px 12px; color:#475569; font-size:10px; font-weight:700; }
        .subacordeon > summary::-webkit-details-marker { display:none; }
        .subacordeon[open] > summary { border-bottom:1px solid #e2e8f0; background:#f1f5f9; }
        .subacordeon-contenido { padding:11px 12px; display:grid; grid-template-columns:180px 1fr; gap:10px; align-items:start; }
        .gestion-acciones { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .gestion-acciones span { color:#64748b; font-size:9px; line-height:1.4; }
        .texto-secundario { color:#64748b; font-size:10px; }
        .oculto-busqueda { display:none !important; }

        .incidencias-lista { padding:14px 18px 18px; display:grid; gap:9px; }
        .incidencia-card { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; }
        .incidencia-card[open] { box-shadow:0 5px 18px rgba(15,23,42,.05); }
        .incidencia-resumen { list-style:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:14px; padding:13px 15px; user-select:none; }
        .incidencia-resumen::-webkit-details-marker { display:none; }
        .incidencia-resumen:hover { background:#f8fafc; }
        .incidencia-info { min-width:0; }
        .incidencia-info strong { display:block; font-size:12px; margin-bottom:4px; }
        .incidencia-info span { color:#64748b; font-size:9px; line-height:1.45; }
        .incidencia-estado { display:flex; align-items:center; gap:8px; flex-shrink:0; }
        .incidencia-card[open] > summary .mini-flecha { transform:rotate(180deg); }
        .incidencia-contenido { border-top:1px solid #e2e8f0; padding:14px 15px 15px; display:grid; gap:12px; background:#fbfdff; }
        .incidencia-descripcion { padding:10px 11px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; }
        .incidencia-descripcion span, .gestion-actual span { display:block; color:#64748b; font-size:8px; text-transform:uppercase; letter-spacing:.35px; margin-bottom:4px; }
        .incidencia-descripcion p { font-size:10px; line-height:1.5; color:#334155; }
        .gestion-actual { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
        .gestion-actual > div { padding:9px 10px; border:1px solid #dbeafe; border-radius:8px; background:#eff6ff; }
        .gestion-actual strong { font-size:10px; color:#1e3a8a; }
        .gestion-observacion-actual { grid-column:1 / -1; }
        .form-gestion-incidencia { display:grid; grid-template-columns:minmax(180px,240px) 1fr; gap:10px; align-items:start; }
        .form-gestion-incidencia label { color:#334155; font-size:10px; font-weight:700; }
        .form-gestion-incidencia select, .form-gestion-incidencia textarea { width:100%; margin-top:5px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#0f172a; outline:none; }
        .form-gestion-incidencia select { height:36px; padding:0 9px; }
        .form-gestion-incidencia textarea { min-height:58px; padding:8px 9px; resize:vertical; }
        .form-gestion-incidencia select:focus, .form-gestion-incidencia textarea:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.08); }
        .form-gestion-incidencia .gestion-acciones { grid-column:1 / -1; }

        @media (max-width:760px) { .fila-implemento { grid-template-columns:1fr; } .resumen-devolucion { grid-template-columns:1fr; } .gestion-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .subacordeon-contenido { grid-template-columns:1fr; } .item-gestion-resumen { align-items:flex-start; } .form-gestion-incidencia { grid-template-columns:1fr; } .gestion-actual { grid-template-columns:1fr; } .gestion-observacion-actual { grid-column:auto; } .filtros-historial { grid-template-columns:1fr; } }
    </style>
</head>
<body>

<div class="app">

    <aside class="sidebar">

        <div class="marca">
            <div class="marca-icono">GI</div>

            <div>
                <strong>Gestión de Implementos</strong>
                <span>Admisión UCM</span>
            </div>
        </div>

        <div class="grupo-menu">
            <p>General</p>

            <a
                class="<?php echo $menu_activo === 'inicio' ? 'activo' : ''; ?>"
                href="index.php?vista=inicio"
            >
                Inicio
            </a>
        </div>

        <div class="grupo-menu">
            <p>Gestión</p>

            <a
                class="<?php echo $menu_activo === 'implementos' ? 'activo' : ''; ?>"
                href="index.php?vista=implementos"
            >
                Implementos
            </a>

            <a
                class="<?php echo $menu_activo === 'actividades' ? 'activo' : ''; ?>"
                href="index.php?vista=actividades"
            >
                Actividades
            </a>

            <a
                class="<?php echo $menu_activo === 'entregas' ? 'activo' : ''; ?>"
                href="index.php?vista=entregas"
            >
                Entregas y devoluciones
            </a>
        </div>

        <div class="grupo-menu">
            <p>Seguimiento</p>

            <a
                class="<?php echo $menu_activo === 'historial' ? 'activo' : ''; ?>"
                href="index.php?vista=historial"
            >
                Historial
            </a>
        </div>

    </aside>

    <main class="main">

        <?php if ($mensaje !== ""): ?>
            <div class="alerta <?php echo $tipo_mensaje === 'error' ? 'error' : 'exito'; ?>">
                <?php echo h($mensaje); ?>
            </div>
        <?php endif; ?>

        <?php if ($vista === "inicio"): ?>

            <div class="cabecera">
                <div>
                    <span class="eyebrow">Resumen</span>
                    <h1>Panel de gestion</h1>
                    <p>Estado general de implementos, actividades y movimientos.</p>
                </div>
            </div>

            <section class="metricas cinco">
                <article>
                    <span>Tipos de implementos</span>
                    <strong><?php echo (int)$resumen["implementos"]; ?></strong>
                </article>

                <article>
                    <span>Unidades en stock</span>
                    <strong><?php echo (int)$resumen["stock"]; ?></strong>
                </article>

                <article>
                    <span>Actividades</span>
                    <strong><?php echo (int)$resumen["actividades"]; ?></strong>
                </article>

                <article>
                    <span>Asignaciones activas</span>
                    <strong><?php echo (int)$resumen["asignaciones"]; ?></strong>
                </article>

                <article>
                    <span>Incidencias pendientes</span>
                    <strong><?php echo (int)$resumen["incidencias"]; ?></strong>
                </article>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Flujo de trabajo</h2>
                        <p>Recorrido principal del sistema.</p>
                    </div>
                </div>

                <div class="flujo">
                    <div>
                        <b>1</b>
                        <strong>Bodega</strong>
                        <span>Registrar y revisar stock.</span>
                    </div>

                    <div>
                        <b>2</b>
                        <strong>Actividad</strong>
                        <span>Crear feria o actividad.</span>
                    </div>

                    <div>
                        <b>3</b>
                        <strong>Reserva</strong>
                        <span>Asignar varios implementos por responsable.</span>
                    </div>

                    <div>
                        <b>4</b>
                        <strong>Entrega</strong>
                        <span>Registrar cada salida por separado.</span>
                    </div>

                    <div>
                        <b>5</b>
                        <strong>Devolucion</strong>
                        <span>Registrar devoluciones parciales o completas.</span>
                    </div>

                    <div>
                        <b>6</b>
                        <strong>Historial</strong>
                        <span>Consultar trazabilidad.</span>
                    </div>
                </div>
            </section>

            <?php
            $proximas = $conexion->query(
                "SELECT
                    id_actividad,
                    nombre,
                    fecha,
                    hora,
                    lugar,
                    descripcion,
                    estado
                 FROM actividad
                 WHERE TIMESTAMP(fecha, COALESCE(hora, '23:59:59')) >= NOW()
                 ORDER BY fecha, hora
                 LIMIT 5"
            );
            ?>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Próximas actividades</h2>
                        <p>Actividades planificadas desde hoy.</p>
                    </div>

                    <a class="boton secundario" href="index.php?vista=actividades">
                        Ver actividades
                    </a>
                </div>

                <?php if ($proximas->num_rows === 0): ?>
                    <div class="vacio">No hay actividades próximas registradas.</div>
                <?php else: ?>
                    <div class="lista-simple">
                        <?php while ($a = $proximas->fetch_assoc()): ?>
                            <a href="index.php?vista=actividad&id=<?php echo (int)$a['id_actividad']; ?>">
                                <div class="actividad-resumen">
                                    <strong><?php echo h($a['nombre']); ?></strong>

                                    <span>
                                        <?php echo fecha_cl($a['fecha']); ?>

                                        <?php if ($a['hora']): ?>
                                            | <?php echo h(substr($a['hora'], 0, 5)); ?>
                                        <?php endif; ?>

                                        <?php if ($a['lugar']): ?>
                                            | <?php echo h($a['lugar']); ?>
                                        <?php endif; ?>
                                    </span>

                                    <?php if ($a['descripcion']): ?>
                                        <small class="descripcion-actividad">
                                            <?php echo h($a['descripcion']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <span class="badge azul">
                                    <?php echo h($a['estado']); ?>
                                </span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>

        <?php elseif ($vista === "implementos"): ?>

            <?php
            $implementos = $conexion->query(
                "SELECT
                    i.*,
                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.estado IN ('Entregada', 'Parcial', 'En custodia')
                                THEN GREATEST(
                                    a.cantidad_asignada
                                    - a.cantidad_devuelta
                                    - a.cantidad_utilizada
                                    - a.cantidad_danada
                                    - a.cantidad_perdida,
                                    0
                                )
                                ELSE 0
                            END
                        ),
                        0
                    ) AS fuera_bodega,
                    COALESCE(
                        SUM(
                            CASE
                                WHEN a.estado = 'Reservada'
                                 AND ac.fecha = CURDATE()
                                THEN a.cantidad_asignada
                                ELSE 0
                            END
                        ),
                        0
                    ) AS reservado_hoy
                 FROM implemento i
                 LEFT JOIN detalle_solicitud d
                    ON d.id_implemento = i.id_implemento
                 LEFT JOIN asignacion a
                    ON a.id_detalle = d.id_detalle
                 LEFT JOIN solicitud s
                    ON s.id_solicitud = d.id_solicitud
                 LEFT JOIN actividad ac
                    ON ac.id_actividad = s.id_actividad
                 GROUP BY i.id_implemento
                 ORDER BY i.nombre"
            );
            ?>

            <div class="cabecera">
                <div>
                    <span class="eyebrow">Bodega</span>
                    <h1>Implementos</h1>
                    <p>Inventario, stock actual y recursos que se encuentran fuera de bodega.</p>
                </div>
            </div>

            <div class="dos-columnas">

                <section class="panel formulario-lateral">
                    <div class="panel-head">
                        <div>
                            <h2>Registrar implemento</h2>
                            <p>Agrega un recurso a la bodega.</p>
                        </div>
                    </div>

                    <form class="form" action="acciones.php" method="POST">
                        <input type="hidden" name="accion" value="registrar_implemento">

                        <label>
                            Nombre
                            <input type="text" name="nombre" required>
                        </label>

                        <label>
                            Categoría
                            <select name="categoria" required>
                                <option value="">Seleccionar</option>
                                <option>Vestuario staff</option>
                                <option>Material promocional</option>
                                <option>Mobiliario y montaje</option>
                                <option>Contenedores y kits</option>
                                <option>Otros</option>
                            </select>
                        </label>

                        <label>
                            Tipo de control
                            <select name="tipo_control" required>
                                <option value="Reutilizable">Reutilizable</option>
                                <option value="Consumible">Consumible</option>
                                <option value="Contenedor">Contenedor</option>
                            </select>
                        </label>

                        <label>
                            Cantidad total
                            <input type="number" name="cantidad_total" min="1" value="1" required>
                        </label>

                        <label>
                            Descripción
                            <textarea name="descripcion"></textarea>
                        </label>

                        <button class="boton" type="submit">
                            Guardar implemento
                        </button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Inventario actual</h2>
                            <p><?php echo $implementos->num_rows; ?> registros encontrados.</p>
                        </div>
                    </div>

                    <div class="buscador-seccion">
                        <input
                            type="search"
                            placeholder="Buscar por codigo, nombre, categoria, tipo o estado..."
                            data-buscador="lista_inventario"
                        >
                    </div>

                    <?php if ($implementos->num_rows === 0): ?>
                        <div class="vacio">No hay implementos registrados.</div>
                    <?php else: ?>
                        <div class="tabla-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Implemento</th>
                                        <th>Categoría</th>
                                        <th>Tipo</th>
                                        <th>Stock</th>
                                        <th>Fuera de bodega</th>
                                        <th>Reservado hoy</th>
                                        <th>Disponible ahora</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>

                                <tbody id="lista_inventario">
                                <?php while ($i = $implementos->fetch_assoc()): ?>
                                    <?php
                                    $fuera = (int)$i['fuera_bodega'];
                                    $reservado_hoy = (int)$i['reservado_hoy'];
                                    $disponible = max(
                                        0,
                                        (int)$i['cantidad_stock'] - $fuera - $reservado_hoy
                                    );

                                    if ($i['estado'] === 'Fuera de servicio') {
                                        $estado = 'Fuera de servicio';
                                        $clase = 'rojo';
                                    } elseif ($disponible === 0) {
                                        $estado = 'No disponible';
                                        $clase = 'rojo';
                                    } elseif ($fuera > 0 || $reservado_hoy > 0) {
                                        $estado = 'Disponibilidad parcial';
                                        $clase = 'amarillo';
                                    } else {
                                        $estado = 'Disponible';
                                        $clase = 'verde';
                                    }
                                    ?>

                                    <tr
                                        class="elemento-busqueda"
                                        data-buscar="<?php echo h(
                                            'IMP-' . str_pad((string)$i['id_implemento'], 3, '0', STR_PAD_LEFT) . ' ' .
                                            $i['nombre'] . ' ' .
                                            $i['descripcion'] . ' ' .
                                            $i['categoria'] . ' ' .
                                            $i['tipo_control'] . ' ' .
                                            $estado
                                        ); ?>"
                                    >
                                        <td>
                                            IMP-<?php echo str_pad(
                                                (string)$i['id_implemento'],
                                                3,
                                                '0',
                                                STR_PAD_LEFT
                                            ); ?>
                                        </td>

                                        <td>
                                            <strong><?php echo h($i['nombre']); ?></strong>
                                            <small><?php echo h($i['descripcion']); ?></small>
                                        </td>

                                        <td><?php echo h($i['categoria']); ?></td>
                                        <td><?php echo h($i['tipo_control']); ?></td>

                                        <td>
                                            <?php echo (int)$i['cantidad_stock']; ?>
                                            /
                                            <?php echo (int)$i['cantidad_total']; ?>
                                        </td>

                                        <td><?php echo $fuera; ?></td>
                                        <td><?php echo $reservado_hoy; ?></td>
                                        <td><strong><?php echo $disponible; ?></strong></td>

                                        <td>
                                            <span class="badge <?php echo $clase; ?>">
                                                <?php echo h($estado); ?>
                                            </span>

                                            <?php if ($i['tipo_control'] === 'Contenedor'): ?>
                                                <a
                                                    class="link-mini"
                                                    href="index.php?vista=implemento&id=<?php echo (int)$i['id_implemento']; ?>"
                                                >
                                                    Ver contenido
                                                </a>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="acciones-tabla">
                                                <a
                                                    class="accion-mini"
                                                    href="index.php?vista=editar_implemento&id=<?php echo (int)$i['id_implemento']; ?>"
                                                >
                                                    Editar
                                                </a>

                                                <?php if ($i['tipo_control'] === 'Contenedor'): ?>
                                                    <a
                                                        class="accion-mini"
                                                        href="index.php?vista=implemento&id=<?php echo (int)$i['id_implemento']; ?>"
                                                    >
                                                        Contenido
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

            </div>

        <?php elseif ($vista === "editar_implemento"): ?>

            <?php
            $id = (int)($_GET['id'] ?? 0);

            $q = $conexion->prepare(
                "SELECT *
                 FROM implemento
                 WHERE id_implemento = ?"
            );

            $q->bind_param("i", $id);
            $q->execute();
            $implemento = $q->get_result()->fetch_assoc();
            $q->close();
            ?>

            <?php if (!$implemento): ?>

                <div class="alerta error">
                    Implemento no encontrado.
                </div>

            <?php else: ?>

                <div class="cabecera">
                    <div>
                        <span class="eyebrow">Bodega</span>
                        <h1>Editar implemento</h1>
                        <p>Actualiza sus datos o registra nuevas unidades ingresadas a bodega.</p>
                    </div>

                    <a
                        class="boton secundario"
                        href="index.php?vista=implementos"
                    >
                        Volver a implementos
                    </a>
                </div>

                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2><?php echo h($implemento['nombre']); ?></h2>
                            <p>Resumen actual del inventario.</p>
                        </div>
                    </div>

                    <div class="datos-implemento">
                        <div class="dato-implemento">
                            <span>Cantidad total registrada</span>
                            <strong><?php echo (int)$implemento['cantidad_total']; ?></strong>
                        </div>

                        <div class="dato-implemento">
                            <span>Stock registrado</span>
                            <strong><?php echo (int)$implemento['cantidad_stock']; ?></strong>
                        </div>

                        <div class="dato-implemento">
                            <span>Estado base</span>
                            <strong><?php echo h($implemento['estado']); ?></strong>
                        </div>
                    </div>
                </section>

                <div class="dos-columnas">

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <h2>Editar datos</h2>
                                <p>Modifica la informacion descriptiva del implemento.</p>
                            </div>
                        </div>

                        <form class="form" action="acciones.php" method="POST">
                            <input type="hidden" name="accion" value="actualizar_implemento">
                            <input type="hidden" name="id_implemento" value="<?php echo $id; ?>">

                            <label>
                                Nombre
                                <input
                                    type="text"
                                    name="nombre"
                                    value="<?php echo h($implemento['nombre']); ?>"
                                    required
                                >
                            </label>

                            <label>
                                Categoría
                                <select name="categoria" required>
                                    <?php
                                    $categorias = [
                                        'Vestuario staff',
                                        'Material promocional',
                                        'Mobiliario y montaje',
                                        'Contenedores y kits',
                                        'Otros'
                                    ];
                                    ?>

                                    <?php foreach ($categorias as $categoria): ?>
                                        <option
                                            value="<?php echo h($categoria); ?>"
                                            <?php echo $implemento['categoria'] === $categoria ? 'selected' : ''; ?>
                                        >
                                            <?php echo h($categoria); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Tipo de control
                                <select name="tipo_control" required>
                                    <?php foreach (['Reutilizable', 'Consumible', 'Contenedor'] as $tipo): ?>
                                        <option
                                            value="<?php echo h($tipo); ?>"
                                            <?php echo $implemento['tipo_control'] === $tipo ? 'selected' : ''; ?>
                                        >
                                            <?php echo h($tipo); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                Descripción
                                <textarea name="descripcion"><?php echo h($implemento['descripcion']); ?></textarea>
                            </label>

                            <button class="boton" type="submit">
                                Guardar cambios
                            </button>
                        </form>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <h2>Agregar stock</h2>
                                <p>Usa esta opcion cuando ingresen nuevas unidades a la bodega.</p>
                            </div>
                        </div>

                        <form class="form" action="acciones.php" method="POST">
                            <input type="hidden" name="accion" value="agregar_stock">
                            <input type="hidden" name="id_implemento" value="<?php echo $id; ?>">

                            <label>
                                Nuevas unidades
                                <input
                                    type="number"
                                    name="cantidad_agregar"
                                    min="1"
                                    value="1"
                                    required
                                >
                            </label>

                            <label>
                                Motivo
                                <textarea
                                    name="motivo"
                                    placeholder="Ejemplo: Compra de nuevas poleras para actividades de Admision"
                                ></textarea>
                            </label>

                            <button class="boton" type="submit">
                                Agregar unidades a bodega
                            </button>

                            <p class="ayuda-formulario">
                                Esta accion aumenta tanto la cantidad total como el stock y deja registro en el historial.
                            </p>
                        </form>
                    </section>

                </div>

            <?php endif; ?>

        <?php elseif ($vista === "implemento"): ?>

            <?php
            $id = (int)($_GET['id'] ?? 0);

            $q = $conexion->prepare(
                "SELECT *
                 FROM implemento
                 WHERE id_implemento = ?"
            );

            $q->bind_param("i", $id);
            $q->execute();
            $implemento = $q->get_result()->fetch_assoc();
            $q->close();
            ?>

            <?php if (!$implemento): ?>
                <div class="alerta error">Implemento no encontrado.</div>
            <?php else: ?>

                <div class="cabecera">
                    <div>
                        <span class="eyebrow">Bodega</span>
                        <h1><?php echo h($implemento['nombre']); ?></h1>
                        <p><?php echo h($implemento['descripcion']); ?></p>
                    </div>

                    <a class="boton secundario" href="index.php?vista=implementos">
                        Volver a implementos
                    </a>
                </div>

                <?php if ($implemento['tipo_control'] !== 'Contenedor'): ?>
                    <div class="alerta error">
                        Este implemento no esta definido como contenedor.
                    </div>
                <?php else: ?>

                    <?php
                    $contenido = $conexion->prepare(
                        "SELECT
                            c.cantidad,
                            i.nombre,
                            i.categoria
                         FROM contenido_contenedor c
                         INNER JOIN implemento i
                            ON i.id_implemento = c.id_implemento
                         WHERE c.id_contenedor = ?
                         ORDER BY i.nombre"
                    );

                    $contenido->bind_param("i", $id);
                    $contenido->execute();
                    $items = $contenido->get_result();

                    $opciones = $conexion->prepare(
                        "SELECT
                            id_implemento,
                            nombre,
                            cantidad_stock
                         FROM implemento
                         WHERE id_implemento <> ?
                         ORDER BY nombre"
                    );

                    $opciones->bind_param("i", $id);
                    $opciones->execute();
                    $lista = $opciones->get_result();
                    ?>

                    <div class="dos-columnas">
                        <section class="panel formulario-lateral">
                            <div class="panel-head">
                                <div>
                                    <h2>Agregar contenido</h2>
                                    <p>Define los elementos que contiene este kit.</p>
                                </div>
                            </div>

                            <form class="form" action="acciones.php" method="POST">
                                <input type="hidden" name="accion" value="agregar_contenido">
                                <input type="hidden" name="id_contenedor" value="<?php echo $id; ?>">

                                <label>
                                    Implemento
                                    <select name="id_implemento" required>
                                        <option value="">Seleccionar</option>

                                        <?php while ($o = $lista->fetch_assoc()): ?>
                                            <option value="<?php echo (int)$o['id_implemento']; ?>">
                                                <?php echo h($o['nombre']); ?>
                                                | Stock: <?php echo (int)$o['cantidad_stock']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </label>

                                <label>
                                    Cantidad
                                    <input type="number" min="1" name="cantidad" required>
                                </label>

                                <button class="boton" type="submit">
                                    Guardar contenido
                                </button>
                            </form>
                        </section>

                        <section class="panel">
                            <div class="panel-head">
                                <div>
                                    <h2>Contenido del contenedor</h2>
                                    <p>Detalle registrado para este kit.</p>
                                </div>
                            </div>

                            <?php if ($items->num_rows === 0): ?>
                                <div class="vacio">
                                    El contenedor aun no tiene contenido registrado.
                                </div>
                            <?php else: ?>
                                <div class="lista-simple sin-link">
                                    <?php while ($it = $items->fetch_assoc()): ?>
                                        <div>
                                            <div>
                                                <strong><?php echo h($it['nombre']); ?></strong>
                                                <span><?php echo h($it['categoria']); ?></span>
                                            </div>

                                            <span class="badge azul">
                                                <?php echo (int)$it['cantidad']; ?> unidades
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <?php
                    $contenido->close();
                    $opciones->close();
                    ?>

                <?php endif; ?>

            <?php endif; ?>

        <?php elseif ($vista === "actividades"): ?>

            <?php
            $actividades = $conexion->query(
                "SELECT
                    ac.*,
                    COUNT(DISTINCT a.id_asignacion) AS asignaciones
                 FROM actividad ac
                 LEFT JOIN solicitud s
                    ON s.id_actividad = ac.id_actividad
                 LEFT JOIN detalle_solicitud d
                    ON d.id_solicitud = s.id_solicitud
                 LEFT JOIN asignacion a
                    ON a.id_detalle = d.id_detalle
                 GROUP BY ac.id_actividad
                 ORDER BY ac.fecha DESC,
                          ac.hora DESC,
                          ac.id_actividad DESC"
            );
            ?>

            <div class="cabecera">
                <div>
                    <span class="eyebrow">Planificación</span>
                    <h1>Actividades</h1>
                    <p>Crea una actividad y define en ella los implementos necesarios.</p>
                </div>
            </div>

            <div class="dos-columnas">

                <section class="panel formulario-lateral">
                    <div class="panel-head">
                        <div>
                            <h2>Nueva actividad</h2>
                            <p>Registra el tipo, fecha, hora, lugar y detalle de la actividad.</p>
                        </div>
                    </div>

                    <form class="form" action="acciones.php" method="POST">
                        <input type="hidden" name="accion" value="crear_actividad">

                        <label>
                            Categoría
                            <select
                                name="categoria_actividad"
                                id="categoria_actividad"
                                required
                            >
                                <option value="">Seleccionar categoria</option>
                                <option value="Visita">Visita</option>
                                <option value="Feria">Feria</option>
                                <option value="Matriculas">Matrículas</option>
                                <option value="Charla">Charla</option>
                                <option value="Call center">Call center</option>
                                <option value="Expo UCM">Expo UCM</option>
                                <option value="Stand">Stand</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </label>

                        <label id="campo_otro_actividad" class="campo-otro">
                            Especificar otro
                            <input
                                type="text"
                                name="otro_actividad"
                                id="otro_actividad"
                                maxlength="120"
                                placeholder="Ejemplo: Reunion informativa"
                            >
                        </label>

                        <label>
                            Fecha
                            <input type="date" name="fecha" required>
                        </label>

                        <label>
                            Hora
                            <input type="time" name="hora" required>
                        </label>

                        <label>
                            Lugar
                            <input type="text" name="lugar" maxlength="150" required>
                        </label>

                        <label>
                            Descripción
                            <textarea
                                name="descripcion"
                                maxlength="255"
                                placeholder="Anota aqui informacion importante de la actividad"
                            ></textarea>
                        </label>

                        <button class="boton" type="submit">
                            Guardar actividad
                        </button>
                    </form>
                </section>

                <section class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Actividades registradas</h2>
                            <p>Abre una actividad para asignar implementos.</p>
                        </div>
                    </div>

                    <div class="buscador-seccion">
                        <input
                            type="search"
                            placeholder="Buscar por categoria, fecha, lugar, descripcion o estado..."
                            data-buscador="lista_actividades"
                        >
                    </div>

                    <?php if ($actividades->num_rows === 0): ?>
                        <div class="vacio">No hay actividades registradas.</div>
                    <?php else: ?>
                        <div class="lista-simple" id="lista_actividades">
                            <?php while ($ac = $actividades->fetch_assoc()): ?>
                                <a
                                    class="elemento-busqueda"
                                    data-buscar="<?php echo h(
                                        $ac['nombre'] . ' ' .
                                        $ac['fecha'] . ' ' .
                                        $ac['hora'] . ' ' .
                                        $ac['lugar'] . ' ' .
                                        $ac['descripcion'] . ' ' .
                                        $ac['estado']
                                    ); ?>"
                                    href="index.php?vista=actividad&id=<?php echo (int)$ac['id_actividad']; ?>"
                                >
                                    <div class="actividad-resumen">
                                        <strong><?php echo h($ac['nombre']); ?></strong>

                                        <span>
                                            <?php echo fecha_cl($ac['fecha']); ?>

                                            <?php if ($ac['hora']): ?>
                                                | <?php echo h(substr($ac['hora'], 0, 5)); ?>
                                            <?php endif; ?>

                                            <?php if ($ac['lugar']): ?>
                                                | <?php echo h($ac['lugar']); ?>
                                            <?php endif; ?>
                                        </span>

                                        <?php if ($ac['descripcion']): ?>
                                            <small class="descripcion-actividad">
                                                <?php echo h($ac['descripcion']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="alinear-derecha">
                                        <span class="badge azul">
                                            <?php echo (int)$ac['asignaciones']; ?> implementos
                                        </span>

                                        <small><?php echo h($ac['estado']); ?></small>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>

        <?php elseif ($vista === "actividad"): ?>

            <?php
            $id = (int)($_GET['id'] ?? 0);

            $q = $conexion->prepare(
                "SELECT *
                 FROM actividad
                 WHERE id_actividad = ?"
            );

            $q->bind_param("i", $id);
            $q->execute();
            $actividad = $q->get_result()->fetch_assoc();
            $q->close();
            ?>

            <?php if (!$actividad): ?>
                <div class="alerta error">Actividad no encontrada.</div>
            <?php else: ?>

                <?php
                $opciones = $conexion->prepare(
                    "SELECT
                        i.id_implemento,
                        i.nombre,
                        i.tipo_control,
                        i.cantidad_stock,
                        i.estado,
                        COALESCE(
                            (
                                SELECT SUM(
                                    a3.cantidad_asignada
                                    - a3.cantidad_devuelta
                                    - a3.cantidad_utilizada
                                    - a3.cantidad_danada
                                    - a3.cantidad_perdida
                                )
                                FROM asignacion a3
                                INNER JOIN detalle_solicitud d3
                                    ON d3.id_detalle = a3.id_detalle
                                WHERE d3.id_implemento = i.id_implemento
                                  AND a3.estado IN ('Entregada', 'Parcial', 'En custodia')
                            ),
                            0
                        ) AS fuera_bodega,
                        COALESCE(
                            (
                                SELECT SUM(a2.cantidad_asignada)
                                FROM asignacion a2
                                INNER JOIN detalle_solicitud d2
                                    ON d2.id_detalle = a2.id_detalle
                                INNER JOIN solicitud s2
                                    ON s2.id_solicitud = d2.id_solicitud
                                INNER JOIN actividad ac2
                                    ON ac2.id_actividad = s2.id_actividad
                                WHERE d2.id_implemento = i.id_implemento
                                  AND ac2.fecha = ?
                                  AND a2.estado = 'Reservada'
                            ),
                            0
                        ) AS reservado_fecha
                     FROM implemento i
                     ORDER BY i.nombre"
                );

                $opciones->bind_param("s", $actividad['fecha']);
                $opciones->execute();
                $implementos_fecha = $opciones->get_result();

                $opciones_html = '<option value="">Seleccionar</option>';

                while ($imp = $implementos_fecha->fetch_assoc()) {
                    $disp = max(
                        0,
                        (int)$imp['cantidad_stock']
                        - (int)$imp['fuera_bodega']
                        - (int)$imp['reservado_fecha']
                    );

                    $no =
                        $disp <= 0 ||
                        $imp['estado'] === 'Fuera de servicio';

                    $texto =
                        h($imp['nombre']) .
                        ' | Disponible: ' .
                        $disp;

                    if ($no) {
                        $texto .= ' | NO DISPONIBLE';
                    }

                    $opciones_html .=
                        '<option value="' .
                        (int)$imp['id_implemento'] .
                        '" ' .
                        ($no ? 'disabled' : '') .
                        '>' .
                        $texto .
                        '</option>';
                }

                $opciones->close();

                $asignaciones = $conexion->prepare(
                    "SELECT
                        s.id_solicitud,
                        s.observacion,
                        a.id_asignacion,
                        a.cantidad_asignada,
                        a.cantidad_devuelta,
                        a.cantidad_utilizada,
                        a.cantidad_danada,
                        a.cantidad_perdida,
                        a.responsable,
                        a.estado,
                        i.nombre AS implemento,
                        i.tipo_control
                     FROM solicitud s
                     INNER JOIN detalle_solicitud d
                        ON d.id_solicitud = s.id_solicitud
                     INNER JOIN asignacion a
                        ON a.id_detalle = d.id_detalle
                     INNER JOIN implemento i
                        ON i.id_implemento = d.id_implemento
                     WHERE s.id_actividad = ?
                     ORDER BY s.id_solicitud DESC,
                              a.id_asignacion ASC"
                );

                $asignaciones->bind_param("i", $id);
                $asignaciones->execute();
                $resultado_asignaciones = $asignaciones->get_result();

                $grupos = [];

                while ($fila = $resultado_asignaciones->fetch_assoc()) {
                    $clave = $fila['id_solicitud'];

                    if (!isset($grupos[$clave])) {
                        $grupos[$clave] = [
                            'responsable' => $fila['responsable'],
                            'observacion' => $fila['observacion'],
                            'items' => []
                        ];
                    }

                    $grupos[$clave]['items'][] = $fila;
                }

                $asignaciones->close();
                ?>

                <div class="cabecera">
                    <div>
                        <span class="eyebrow">Actividad</span>
                        <h1><?php echo h($actividad['nombre']); ?></h1>

                        <p>
                            <?php echo fecha_cl($actividad['fecha']); ?>

                            <?php if ($actividad['hora']): ?>
                                | <?php echo h(substr($actividad['hora'], 0, 5)); ?>
                            <?php endif; ?>

                            <?php if ($actividad['lugar']): ?>
                                | <?php echo h($actividad['lugar']); ?>
                            <?php endif; ?>
                        </p>

                        <?php if ($actividad['descripcion']): ?>
                            <p class="detalle-actividad">
                                <?php echo h($actividad['descripcion']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <a class="boton secundario" href="index.php?vista=actividades">
                        Volver a actividades
                    </a>
                </div>

                <div class="dos-columnas">

                    <section class="panel formulario-lateral">
                        <div class="panel-head">
                            <div>
                                <h2>Asignar implementos</h2>
                                <p>
                                    Agrega varios implementos al mismo responsable y valida disponibilidad para
                                    <?php echo fecha_cl($actividad['fecha']); ?>.
                                </p>
                            </div>
                        </div>

                        <form
                            class="form"
                            action="acciones.php"
                            method="POST"
                            id="form_asignacion"
                        >
                            <input type="hidden" name="accion" value="asignar_implementos">
                            <input type="hidden" name="id_actividad" value="<?php echo $id; ?>">

                            <label>
                                Responsable
                                <input type="text" name="responsable" required>
                            </label>

                            <div class="titulo-items">
                                <strong>Implementos</strong>
                                <span>Agrega todos los implementos que se llevara esta persona.</span>
                            </div>

                            <div id="contenedor_items" class="asignacion-items">
                                <div class="fila-implemento">
                                    <div class="campo-implemento">
                                        <label>
                                            Implemento
                                            <select name="id_implemento[]" required>
                                                <?php echo $opciones_html; ?>
                                            </select>
                                        </label>
                                    </div>

                                    <div class="campo-cantidad">
                                        <label>
                                            Cantidad
                                            <input
                                                type="number"
                                                name="cantidad[]"
                                                min="1"
                                                value="1"
                                                required
                                            >
                                        </label>
                                    </div>

                                    <button
                                        type="button"
                                        class="quitar-fila"
                                        onclick="quitarFila(this)"
                                        title="Quitar implemento"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            </div>

                            <button
                                class="boton secundario boton-agregar-item"
                                type="button"
                                onclick="agregarFila()"
                            >
                                + Agregar otro implemento
                            </button>

                            <label>
                                Observación
                                <textarea
                                    name="observacion"
                                    placeholder="Ejemplo: Implementos para el staff asignado a esta visita"
                                ></textarea>
                            </label>

                            <button class="boton" type="submit">
                                Validar y reservar implementos
                            </button>
                        </form>

                        <template id="plantilla_item">
                            <div class="fila-implemento">
                                <div class="campo-implemento">
                                    <label>
                                        Implemento
                                        <select name="id_implemento[]" required>
                                            <?php echo $opciones_html; ?>
                                        </select>
                                    </label>
                                </div>

                                <div class="campo-cantidad">
                                    <label>
                                        Cantidad
                                        <input
                                            type="number"
                                            name="cantidad[]"
                                            min="1"
                                            value="1"
                                            required
                                        >
                                    </label>
                                </div>

                                <button
                                    type="button"
                                    class="quitar-fila"
                                    onclick="quitarFila(this)"
                                    title="Quitar implemento"
                                >
                                    Quitar
                                </button>
                            </div>
                        </template>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div>
                                <h2>Implementos de la actividad</h2>
                                <p>Selecciona un responsable para revisar sus implementos reservados.</p>
                            </div>
                        </div>

                        <div class="buscador-seccion">
                            <input
                                type="search"
                                placeholder="Buscar responsable o implemento..."
                                data-buscador="lista_responsables_actividad"
                            >
                        </div>

                        <?php if (count($grupos) === 0): ?>
                            <div class="vacio">Aún no se han asignado implementos.</div>
                        <?php else: ?>
                            <div class="acordeon-lista" id="lista_responsables_actividad">
                                <?php foreach ($grupos as $grupo): ?>
                                    <?php
                                    $nombres_items = implode(
                                        ' ',
                                        array_map(
                                            fn($item) => $item['implemento'] . ' ' . $item['estado'],
                                            $grupo['items']
                                        )
                                    );
                                    ?>

                                    <details
                                        class="acordeon-responsable elemento-busqueda"
                                        data-buscar="<?php echo h(
                                            $grupo['responsable'] . ' ' .
                                            $grupo['observacion'] . ' ' .
                                            $nombres_items
                                        ); ?>"
                                    >
                                        <summary class="acordeon-resumen">
                                            <div>
                                                <span class="mini-etiqueta">Responsable</span>
                                                <h3><?php echo h($grupo['responsable']); ?></h3>

                                                <?php if ($grupo['observacion']): ?>
                                                    <p><?php echo h($grupo['observacion']); ?></p>
                                                <?php else: ?>
                                                    <p>Presiona para revisar los implementos asignados.</p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="acordeon-derecha">
                                                <span class="badge azul">
                                                    <?php echo count($grupo['items']); ?> implementos
                                                </span>
                                                <span class="flecha-acordeon">⌄</span>
                                            </div>
                                        </summary>

                                        <div class="acordeon-contenido">
                                            <?php foreach ($grupo['items'] as $item): ?>
                                                <?php
                                                $registrado =
                                                    (int)$item['cantidad_devuelta'] +
                                                    (int)$item['cantidad_utilizada'] +
                                                    (int)$item['cantidad_danada'] +
                                                    (int)$item['cantidad_perdida'];

                                                $pendiente = max(
                                                    0,
                                                    (int)$item['cantidad_asignada'] - $registrado
                                                );
                                                ?>

                                                <div class="item-responsable">
                                                    <div>
                                                        <strong><?php echo h($item['implemento']); ?></strong>
                                                        <span>
                                                            Cantidad: <?php echo (int)$item['cantidad_asignada']; ?>
                                                            | <?php echo h($item['tipo_control']); ?>

                                                            <?php if ($item['estado'] === 'Parcial'): ?>
                                                                | Pendiente: <?php echo $pendiente; ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>

                                                    <span class="badge <?php echo $item['estado'] === 'Cerrada' ? 'verde' : ($item['estado'] === 'Parcial' ? 'amarillo' : 'azul'); ?>">
                                                        <?php echo h($item['estado']); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                </div>

            <?php endif; ?>

        <?php elseif ($vista === "entregas"): ?>

            <?php
            $pendientes = $conexion->query(
                "SELECT
                    a.id_asignacion,
                    a.cantidad_asignada,
                    a.cantidad_devuelta,
                    a.cantidad_utilizada,
                    a.cantidad_danada,
                    a.cantidad_perdida,
                    a.responsable,
                    a.estado,
                    i.nombre AS implemento,
                    i.tipo_control,
                    ac.id_actividad,
                    ac.nombre AS actividad,
                    ac.fecha,
                    ac.hora,
                    ac.lugar
                 FROM asignacion a
                 INNER JOIN detalle_solicitud d
                    ON d.id_detalle = a.id_detalle
                 INNER JOIN implemento i
                    ON i.id_implemento = d.id_implemento
                 INNER JOIN solicitud s
                    ON s.id_solicitud = d.id_solicitud
                 INNER JOIN actividad ac
                    ON ac.id_actividad = s.id_actividad
                 WHERE a.estado IN ('Reservada', 'Entregada', 'Parcial', 'En custodia')
                 ORDER BY ac.fecha,
                          ac.hora,
                          a.responsable,
                          a.id_asignacion"
            );

            $grupos_entrega = [];

            while ($fila_entrega = $pendientes->fetch_assoc()) {
                $clave =
                    $fila_entrega['id_actividad'] . '|' .
                    $fila_entrega['responsable'];

                if (!isset($grupos_entrega[$clave])) {
                    $grupos_entrega[$clave] = [
                        'responsable' => $fila_entrega['responsable'],
                        'actividad' => $fila_entrega['actividad'],
                        'fecha' => $fila_entrega['fecha'],
                        'hora' => $fila_entrega['hora'],
                        'lugar' => $fila_entrega['lugar'],
                        'items' => []
                    ];
                }

                $grupos_entrega[$clave]['items'][] = $fila_entrega;
            }

            $incidencias = $conexion->query(
                "SELECT
                    inc.id_incidencia,
                    inc.tipo,
                    inc.descripcion,
                    inc.estado,
                    inc.accion_tomada,
                    inc.observacion_gestion,
                    inc.fecha_registro,
                    inc.fecha_gestion,
                    i.nombre AS implemento,
                    a.responsable,
                    ac.nombre AS actividad,
                    ac.lugar
                 FROM incidencia inc
                 INNER JOIN asignacion a
                    ON a.id_asignacion = inc.id_asignacion
                 INNER JOIN detalle_solicitud d
                    ON d.id_detalle = a.id_detalle
                 INNER JOIN implemento i
                    ON i.id_implemento = d.id_implemento
                 INNER JOIN solicitud s
                    ON s.id_solicitud = d.id_solicitud
                 INNER JOIN actividad ac
                    ON ac.id_actividad = s.id_actividad
                 ORDER BY
                    CASE
                        WHEN inc.estado = 'Pendiente' THEN 0
                        ELSE 1
                    END,
                    inc.fecha_registro DESC"
            );
            ?>

            <div class="cabecera">
                <div>
                    <span class="eyebrow">Seguimiento</span>
                    <h1>Entregas y devoluciones</h1>
                    <p>
                        Selecciona una persona para revisar qué implementos tiene y registrar cada entrega o devolución por separado.
                    </p>
                </div>
            </div>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Responsables con implementos activos</h2>
                        <p>
                            Presiona el nombre de una persona para ver lo que tiene reservado, entregado o pendiente de devolver.
                        </p>
                    </div>
                </div>

                <div class="buscador-seccion">
                    <input
                        type="search"
                        placeholder="Buscar persona, actividad, lugar o implemento..."
                        data-buscador="lista_entregas"
                    >
                </div>

                <?php if (count($grupos_entrega) === 0): ?>
                    <div class="vacio">No hay entregas pendientes.</div>
                <?php else: ?>
                    <div class="acordeon-lista" id="lista_entregas">
                        <?php foreach ($grupos_entrega as $grupo): ?>
                            <?php
                            $texto_items = implode(
                                ' ',
                                array_map(
                                    fn($item) => $item['implemento'] . ' ' . $item['estado'],
                                    $grupo['items']
                                )
                            );
                            ?>

                            <details
                                class="acordeon-responsable elemento-busqueda"
                                data-buscar="<?php echo h(
                                    $grupo['responsable'] . ' ' .
                                    $grupo['actividad'] . ' ' .
                                    $grupo['fecha'] . ' ' .
                                    $grupo['lugar'] . ' ' .
                                    $texto_items
                                ); ?>"
                            >
                                <summary class="acordeon-resumen">
                                    <div>
                                        <span class="mini-etiqueta">Responsable</span>
                                        <h3><?php echo h($grupo['responsable']); ?></h3>
                                        <p>
                                            <?php echo h($grupo['actividad']); ?>
                                            | <?php echo fecha_cl($grupo['fecha']); ?>

                                            <?php if ($grupo['hora']): ?>
                                                | <?php echo h(substr($grupo['hora'], 0, 5)); ?>
                                            <?php endif; ?>

                                            <?php if ($grupo['lugar']): ?>
                                                | <?php echo h($grupo['lugar']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>

                                    <div class="acordeon-derecha">
                                        <span class="badge azul">
                                            <?php echo count($grupo['items']); ?> implementos
                                        </span>
                                        <span class="flecha-acordeon">⌄</span>
                                    </div>
                                </summary>

                                <div class="acordeon-contenido">
                                    <?php foreach ($grupo['items'] as $p): ?>
                                        <?php
                                        $registrado =
                                            (int)$p['cantidad_devuelta'] +
                                            (int)$p['cantidad_utilizada'] +
                                            (int)$p['cantidad_danada'] +
                                            (int)$p['cantidad_perdida'];

                                        $pendiente = max(
                                            0,
                                            (int)$p['cantidad_asignada'] - $registrado
                                        );
                                        ?>

                                        <details class="item-gestion">
                                            <summary class="item-gestion-resumen">
                                                <div class="item-gestion-info">
                                                    <strong><?php echo h($p['implemento']); ?></strong>
                                                    <span>
                                                        <?php echo h($p['tipo_control']); ?>
                                                        | Asignado: <?php echo (int)$p['cantidad_asignada']; ?>
                                                        | Pendiente: <?php echo $pendiente; ?>
                                                    </span>
                                                </div>

                                                <div class="item-gestion-derecha">
                                                    <span class="badge <?php echo $p['estado'] === 'Parcial' ? 'amarillo' : 'azul'; ?>">
                                                        <?php echo h($p['estado']); ?>
                                                    </span>
                                                    <span class="mini-flecha">⌄</span>
                                                </div>
                                            </summary>

                                            <div class="item-gestion-contenido">

                                                <?php if ($p['estado'] === 'Reservada'): ?>

                                                    <div class="estado-compacto">
                                                        <div class="dato-compacto">
                                                            <span>Asignado</span>
                                                            <strong><?php echo (int)$p['cantidad_asignada']; ?></strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Devuelto</span>
                                                            <strong>0</strong>
                                                        </div>

                                                        <?php if ($p['tipo_control'] === 'Consumible'): ?>
                                                            <div class="dato-compacto">
                                                                <span>Utilizado</span>
                                                                <strong>0</strong>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="dato-compacto">
                                                            <span>Dañado</span>
                                                            <strong>0</strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Perdido</span>
                                                            <strong>0</strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Pendiente</span>
                                                            <strong><?php echo $pendiente; ?></strong>
                                                        </div>
                                                    </div>

                                                    <form
                                                        class="gestion-compacta"
                                                        action="acciones.php"
                                                        method="POST"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="registrar_resultado"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id_asignacion"
                                                            value="<?php echo (int)$p['id_asignacion']; ?>"
                                                        >

                                                        <div class="gestion-grid">
                                                            <label>
                                                                Cantidad

                                                                <select name="cantidad_resultado" required>
                                                                    <?php for ($n = 1; $n <= $pendiente; $n++): ?>
                                                                        <option value="<?php echo $n; ?>">
                                                                            <?php echo $n; ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </label>

                                                            <label>
                                                                Resultado

                                                                <select name="resultado" required>
                                                                    <option value="Entregado">Entregado al responsable</option>
                                                                    <option value="Devuelto">Devuelto</option>

                                                                    <?php if ($p['tipo_control'] === 'Consumible'): ?>
                                                                        <option value="Consumido">Consumido / entregado</option>
                                                                    <?php endif; ?>

                                                                    <option value="Danado">Dañado</option>
                                                                    <option value="Perdido">Perdido</option>
                                                                    <option value="Custodia">Mantiene en custodia</option>
                                                                </select>
                                                            </label>
                                                        </div>

                                                        <label class="gestion-observacion">
                                                            Observación opcional
                                                            <textarea
                                                                name="observacion"
                                                                placeholder="Ejemplo: Se entrega para la actividad programada"
                                                            ></textarea>
                                                        </label>

                                                        <div class="gestion-acciones">
                                                            <button class="boton" type="submit">
                                                                Registrar
                                                            </button>

                                                            <span>
                                                                Registra el resultado seleccionado para este implemento.
                                                            </span>
                                                        </div>
                                                    </form>

                                                <?php else: ?>

                                                    <div class="estado-compacto">
                                                        <div class="dato-compacto">
                                                            <span>Asignado</span>
                                                            <strong><?php echo (int)$p['cantidad_asignada']; ?></strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Devuelto</span>
                                                            <strong><?php echo (int)$p['cantidad_devuelta']; ?></strong>
                                                        </div>

                                                        <?php if ($p['tipo_control'] === 'Consumible'): ?>
                                                            <div class="dato-compacto">
                                                                <span>Utilizado</span>
                                                                <strong><?php echo (int)$p['cantidad_utilizada']; ?></strong>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="dato-compacto">
                                                            <span>Dañado</span>
                                                            <strong><?php echo (int)$p['cantidad_danada']; ?></strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Perdido</span>
                                                            <strong><?php echo (int)$p['cantidad_perdida']; ?></strong>
                                                        </div>

                                                        <div class="dato-compacto">
                                                            <span>Pendiente</span>
                                                            <strong><?php echo $pendiente; ?></strong>
                                                        </div>
                                                    </div>

                                                    <form
                                                        class="gestion-compacta"
                                                        action="acciones.php"
                                                        method="POST"
                                                    >
                                                        <input type="hidden" name="accion" value="registrar_resultado">
                                                        <input type="hidden" name="id_asignacion" value="<?php echo (int)$p['id_asignacion']; ?>">

                                                        <div class="gestion-grid">
                                                            <label>
                                                                Cantidad
                                                                <select name="cantidad_resultado" required>
                                                                    <?php for ($n = 1; $n <= $pendiente; $n++): ?>
                                                                        <option value="<?php echo $n; ?>">
                                                                            <?php echo $n; ?>
                                                                        </option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </label>

                                                            <label>
                                                                Resultado
                                                                <select name="resultado" required>
                                                                    <option value="Devuelto">Devuelto</option>

                                                                    <?php if ($p['tipo_control'] === 'Consumible'): ?>
                                                                        <option value="Consumido">Consumido / entregado</option>
                                                                    <?php endif; ?>

                                                                    <option value="Danado">Dañado</option>
                                                                    <option value="Perdido">Perdido</option>
                                                                    <option value="Custodia">Mantiene en custodia</option>
                                                                </select>
                                                            </label>
                                                        </div>

                                                        <label class="gestion-observacion">
                                                            Observación opcional
                                                            <textarea
                                                                name="observacion"
                                                                placeholder="Ejemplo: Mantiene el gorro para otra visita"
                                                            ></textarea>
                                                        </label>

                                                        <div class="gestion-acciones">
                                                            <button class="boton" type="submit">
                                                                Registrar
                                                            </button>

                                                            <span>
                                                                Si queda algo pendiente, seguirá asociado a esta persona.
                                                            </span>
                                                        </div>
                                                    </form>

                                                <?php endif; ?>

                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Incidencias registradas</h2>
                        <p>
                            Revisa cada problema y deja registrada la decisión tomada.
                        </p>
                    </div>
                </div>

                <div class="buscador-seccion">
                    <input
                        type="search"
                        placeholder="Buscar responsable, implemento, actividad o incidencia..."
                        data-buscador="lista_incidencias"
                    >
                </div>

                <?php if ($incidencias->num_rows === 0): ?>
                    <div class="vacio">No hay incidencias registradas.</div>
                <?php else: ?>

                    <div class="incidencias-lista" id="lista_incidencias">

                        <?php while ($inc = $incidencias->fetch_assoc()): ?>

                            <?php
                            $tipo_visible =
                                $inc['tipo'] === 'Dano'
                                ? 'Daño'
                                : (
                                    $inc['tipo'] === 'Perdida'
                                    ? 'Pérdida'
                                    : $inc['tipo']
                                );

                            $acciones_incidencia = [
                                'Reparado' => 'Reparado',
                                'Dado de baja' => 'Dado de baja',
                                'Repuesto' => 'Repuesto',
                                'No requiere reposicion' => 'No requiere reposición',
                                'Pendiente de reposicion' => 'Pendiente de reposición',
                                'Informado a coordinacion' => 'Informado a coordinación',
                                'Otro' => 'Otro'
                            ];
                            ?>

                            <details
                                class="incidencia-card elemento-busqueda"
                                data-buscar="<?php echo h(
                                    $inc['responsable'] . ' ' .
                                    $tipo_visible . ' ' .
                                    $inc['implemento'] . ' ' .
                                    $inc['actividad'] . ' ' .
                                    $inc['lugar'] . ' ' .
                                    $inc['descripcion'] . ' ' .
                                    $inc['estado'] . ' ' .
                                    ($inc['accion_tomada'] ?? '') . ' ' .
                                    ($inc['observacion_gestion'] ?? '')
                                ); ?>"
                            >

                                <summary class="incidencia-resumen">

                                    <div class="incidencia-info">

                                        <strong>
                                            <?php echo h($tipo_visible); ?>
                                            |
                                            <?php echo h($inc['implemento']); ?>
                                        </strong>

                                        <span>
                                            <?php echo h($inc['responsable']); ?>
                                            |
                                            <?php echo h($inc['actividad']); ?>

                                            <?php if ($inc['lugar']): ?>
                                                |
                                                <?php echo h($inc['lugar']); ?>
                                            <?php endif; ?>

                                            |
                                            <?php echo fecha_hora_cl($inc['fecha_registro']); ?>
                                        </span>

                                    </div>

                                    <div class="incidencia-estado">

                                        <span class="badge <?php echo $inc['estado'] === 'Gestionada' ? 'verde' : 'amarillo'; ?>">
                                            <?php echo h($inc['estado']); ?>
                                        </span>

                                        <span class="mini-flecha">
                                            ⌄
                                        </span>

                                    </div>

                                </summary>

                                <div class="incidencia-contenido">

                                    <div class="incidencia-descripcion">

                                        <span>Detalle registrado</span>

                                        <p>
                                            <?php echo h($inc['descripcion']); ?>
                                        </p>

                                    </div>

                                    <?php if ($inc['estado'] === 'Gestionada'): ?>

                                        <div class="gestion-actual">

                                            <div>
                                                <span>Acción tomada</span>

                                                <strong>
                                                    <?php echo h(
                                                        $acciones_incidencia[$inc['accion_tomada']]
                                                        ?? $inc['accion_tomada']
                                                        ?? '-'
                                                    ); ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>Fecha de gestión</span>

                                                <strong>
                                                    <?php echo fecha_hora_cl($inc['fecha_gestion']); ?>
                                                </strong>
                                            </div>

                                            <?php if (!empty($inc['observacion_gestion'])): ?>

                                                <div class="gestion-observacion-actual">
                                                    <span>Observación</span>

                                                    <strong>
                                                        <?php echo h($inc['observacion_gestion']); ?>
                                                    </strong>
                                                </div>

                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>

                                    <form
                                        class="form-gestion-incidencia"
                                        action="acciones.php"
                                        method="POST"
                                    >

                                        <input
                                            type="hidden"
                                            name="accion"
                                            value="gestionar_incidencia"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_incidencia"
                                            value="<?php echo (int)$inc['id_incidencia']; ?>"
                                        >

                                        <label>
                                            Acción tomada

                                            <select
                                                name="accion_tomada"
                                                required
                                            >

                                                <option value="">
                                                    Seleccionar
                                                </option>

                                                <?php foreach ($acciones_incidencia as $valor => $texto): ?>

                                                    <option
                                                        value="<?php echo h($valor); ?>"
                                                        <?php echo $inc['accion_tomada'] === $valor ? 'selected' : ''; ?>
                                                    >
                                                        <?php echo h($texto); ?>
                                                    </option>

                                                <?php endforeach; ?>

                                            </select>
                                        </label>

                                        <label>
                                            Observación de gestión

                                            <textarea
                                                name="observacion_gestion"
                                                placeholder="Ejemplo: Se informó a coordinación y queda pendiente de reposición."
                                            ><?php echo h($inc['observacion_gestion'] ?? ''); ?></textarea>
                                        </label>

                                        <div class="gestion-acciones">

                                            <button
                                                class="boton"
                                                type="submit"
                                            >
                                                <?php echo $inc['estado'] === 'Gestionada'
                                                    ? 'Actualizar gestión'
                                                    : 'Guardar gestión'; ?>
                                            </button>

                                            <span>
                                                Gestionada significa que el problema fue revisado y se dejó registrada una decisión.
                                            </span>

                                        </div>

                                    </form>

                                </div>

                            </details>

                        <?php endwhile; ?>

                    </div>

                <?php endif; ?>
            </section>

        <?php elseif ($vista === "historial"): ?>

            <?php
            $movimientos = $conexion->query(
                "SELECT
                    m.*,
                    i.nombre AS implemento,
                    ac.nombre AS actividad,
                    ac.lugar
                 FROM movimiento m
                 INNER JOIN implemento i
                    ON i.id_implemento = m.id_implemento
                 LEFT JOIN actividad ac
                    ON ac.id_actividad = m.id_actividad
                 ORDER BY m.fecha_movimiento DESC,
                          m.id_movimiento DESC
                 LIMIT 300"
            );

            $tipos_historial = $conexion->query(
                "SELECT DISTINCT tipo
                 FROM movimiento
                 ORDER BY tipo"
            );
            ?>

            <div class="cabecera">
                <div>
                    <span class="eyebrow">Trazabilidad</span>
                    <h1>Historial</h1>
                    <p>Registro cronológico de reservas, entregas y devoluciones.</p>
                </div>
            </div>

            <section class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Movimientos</h2>
                        <p>Reservas, entregas, devoluciones, cierres e incidencias.</p>
                    </div>
                </div>

                <div class="buscador-seccion">
                    <div class="filtros-historial">
                        <label>
                            Buscar
                            <input
                                type="search"
                                id="busqueda_historial"
                                placeholder="Implemento, actividad, responsable o detalle..."
                            >
                        </label>

                        <label>
                            Movimiento
                            <select id="filtro_tipo_historial">
                                <option value="">Todos los movimientos</option>

                                <?php while ($tipo_historial = $tipos_historial->fetch_assoc()): ?>
                                    <option value="<?php echo h($tipo_historial['tipo']); ?>">
                                        <?php echo h($tipo_historial['tipo']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </label>

                        <label>
                            Fecha
                            <input
                                type="date"
                                id="filtro_fecha_historial"
                            >
                        </label>

                        <button
                            type="button"
                            class="boton-limpiar-filtros"
                            id="limpiar_filtros_historial"
                        >
                            Limpiar
                        </button>
                    </div>
                </div>

                <?php if ($movimientos->num_rows === 0): ?>
                    <div class="vacio">No hay movimientos registrados.</div>
                <?php else: ?>
                    <div class="tabla-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Movimiento</th>
                                    <th>Implemento</th>
                                    <th>Actividad</th>
                                    <th>Cantidad</th>
                                    <th>Responsable</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>

                            <tbody id="lista_historial">
                            <?php while ($m = $movimientos->fetch_assoc()): ?>
                                <tr
                                    class="elemento-busqueda fila-historial"
                                    data-tipo="<?php echo h($m['tipo']); ?>"
                                    data-fecha="<?php echo h(substr($m['fecha_movimiento'], 0, 10)); ?>"
                                    data-buscar="<?php echo h(
                                        $m['fecha_movimiento'] . ' ' .
                                        $m['tipo'] . ' ' .
                                        $m['implemento'] . ' ' .
                                        $m['actividad'] . ' ' .
                                        $m['lugar'] . ' ' .
                                        $m['responsable'] . ' ' .
                                        $m['detalle']
                                    ); ?>"
                                >
                                    <td><?php echo fecha_hora_cl($m['fecha_movimiento']); ?></td>

                                    <td>
                                        <span class="badge azul">
                                            <?php echo h($m['tipo']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <strong><?php echo h($m['implemento']); ?></strong>
                                    </td>

                                    <td>
                                        <?php echo h($m['actividad'] ?: '-'); ?>

                                        <?php if ($m['lugar']): ?>
                                            <small><?php echo h($m['lugar']); ?></small>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php echo $m['cantidad'] !== null
                                            ? (int)$m['cantidad']
                                            : '-'; ?>
                                    </td>

                                    <td><?php echo h($m['responsable'] ?: '-'); ?></td>
                                    <td><?php echo h($m['detalle'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php else: ?>

            <div class="alerta error">Vista no encontrada.</div>

        <?php endif; ?>

    </main>
</div>

<script>
const categoriaActividad = document.getElementById("categoria_actividad");
const campoOtroActividad = document.getElementById("campo_otro_actividad");
const otroActividad = document.getElementById("otro_actividad");

function actualizarCampoOtro()
{
    if (!categoriaActividad || !campoOtroActividad || !otroActividad) {
        return;
    }

    const mostrar = categoriaActividad.value === "Otro";

    campoOtroActividad.style.display = mostrar ? "block" : "none";
    otroActividad.required = mostrar;

    if (!mostrar) {
        otroActividad.value = "";
    }
}

if (categoriaActividad) {
    actualizarCampoOtro();
    categoriaActividad.addEventListener("change", actualizarCampoOtro);
}

function agregarFila()
{
    const contenedor = document.getElementById("contenedor_items");
    const plantilla = document.getElementById("plantilla_item");

    if (!contenedor || !plantilla) {
        return;
    }

    contenedor.appendChild(plantilla.content.cloneNode(true));
}

function quitarFila(boton)
{
    const contenedor = document.getElementById("contenedor_items");

    if (!contenedor) {
        return;
    }

    const filas = contenedor.querySelectorAll(".fila-implemento");

    if (filas.length <= 1) {
        return;
    }

    boton.closest(".fila-implemento").remove();
}

function normalizarBusqueda(texto)
{
    return (texto || "")
        .toString()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
}

document.querySelectorAll("[data-buscador]").forEach((campo) => {
    campo.addEventListener("input", () => {
        const contenedor = document.getElementById(campo.dataset.buscador);

        if (!contenedor) {
            return;
        }

        const termino = normalizarBusqueda(campo.value);
        const elementos = contenedor.querySelectorAll(".elemento-busqueda");

        elementos.forEach((elemento) => {
            const contenido = normalizarBusqueda(
                elemento.dataset.buscar || elemento.textContent
            );

            const coincide =
                termino === "" || contenido.includes(termino);

            elemento.classList.toggle(
                "oculto-busqueda",
                !coincide
            );
        });
    });
});

const busquedaHistorial = document.getElementById("busqueda_historial");
const filtroTipoHistorial = document.getElementById("filtro_tipo_historial");
const filtroFechaHistorial = document.getElementById("filtro_fecha_historial");
const limpiarFiltrosHistorial = document.getElementById("limpiar_filtros_historial");

function filtrarHistorial()
{
    const lista = document.getElementById("lista_historial");

    if (!lista) {
        return;
    }

    const termino = normalizarBusqueda(
        busquedaHistorial ? busquedaHistorial.value : ""
    );

    const tipo = filtroTipoHistorial
        ? filtroTipoHistorial.value
        : "";

    const fecha = filtroFechaHistorial
        ? filtroFechaHistorial.value
        : "";

    lista.querySelectorAll(".fila-historial").forEach((fila) => {
        const contenido = normalizarBusqueda(
            fila.dataset.buscar || fila.textContent
        );

        const coincideTexto =
            termino === "" || contenido.includes(termino);

        const coincideTipo =
            tipo === "" || fila.dataset.tipo === tipo;

        const coincideFecha =
            fecha === "" || fila.dataset.fecha === fecha;

        fila.classList.toggle(
            "oculto-busqueda",
            !(coincideTexto && coincideTipo && coincideFecha)
        );
    });
}

if (busquedaHistorial) {
    busquedaHistorial.addEventListener("input", filtrarHistorial);
}

if (filtroTipoHistorial) {
    filtroTipoHistorial.addEventListener("change", filtrarHistorial);
}

if (filtroFechaHistorial) {
    filtroFechaHistorial.addEventListener("change", filtrarHistorial);
}

if (limpiarFiltrosHistorial) {
    limpiarFiltrosHistorial.addEventListener("click", () => {
        if (busquedaHistorial) {
            busquedaHistorial.value = "";
        }

        if (filtroTipoHistorial) {
            filtroTipoHistorial.value = "";
        }

        if (filtroFechaHistorial) {
            filtroFechaHistorial.value = "";
        }

        filtrarHistorial();
    });
}
</script>

</body>
</html>
