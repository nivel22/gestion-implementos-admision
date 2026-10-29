<?php
require_once "config/conexion.php";

function volver($vista, $mensaje = "", $tipo = "ok", $extra = [])
{
    header(
        "Location: index.php?" .
        http_build_query(
            array_merge(
                [
                    "vista" => $vista,
                    "mensaje" => $mensaje,
                    "tipo" => $tipo
                ],
                $extra
            )
        )
    );

    exit;
}

function disponibilidad_fecha($conexion, $id_implemento, $fecha)
{
    $q = $conexion->prepare(
        "SELECT cantidad_stock, estado
         FROM implemento
         WHERE id_implemento = ?"
    );

    $q->bind_param("i", $id_implemento);
    $q->execute();
    $implemento = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$implemento || $implemento["estado"] === "Fuera de servicio") {
        return 0;
    }

    $q = $conexion->prepare(
        "SELECT
            COALESCE(
                SUM(
                    a.cantidad_asignada
                    - a.cantidad_devuelta
                    - a.cantidad_utilizada
                    - a.cantidad_danada
                    - a.cantidad_perdida
                ),
                0
            ) AS fuera_bodega
         FROM asignacion a
         INNER JOIN detalle_solicitud d
            ON d.id_detalle = a.id_detalle
         WHERE d.id_implemento = ?
           AND a.estado IN ('Entregada', 'Parcial', 'En custodia')"
    );

    $q->bind_param("i", $id_implemento);
    $q->execute();
    $uso = $q->get_result()->fetch_assoc();
    $q->close();

    $q = $conexion->prepare(
        "SELECT
            COALESCE(SUM(a.cantidad_asignada), 0) AS reservado_fecha
         FROM asignacion a
         INNER JOIN detalle_solicitud d
            ON d.id_detalle = a.id_detalle
         INNER JOIN solicitud s
            ON s.id_solicitud = d.id_solicitud
         INNER JOIN actividad ac
            ON ac.id_actividad = s.id_actividad
         WHERE d.id_implemento = ?
           AND ac.fecha = ?
           AND a.estado = 'Reservada'"
    );

    $q->bind_param("is", $id_implemento, $fecha);
    $q->execute();
    $reserva = $q->get_result()->fetch_assoc();
    $q->close();

    return max(
        0,
        (int)$implemento["cantidad_stock"]
        - (int)$uso["fuera_bodega"]
        - (int)$reserva["reservado_fecha"]
    );
}


function registrar_entrega_interna($conexion, $d, $id)
{
    $q = $conexion->prepare(
        "UPDATE asignacion
         SET
            estado = 'Entregada',
            fecha_entrega = NOW()
         WHERE id_asignacion = ?"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $q->close();

    $detalle =
        "Entrega para " .
        $d["actividad"] .
        " en " .
        $d["lugar"];

    $q = $conexion->prepare(
        "INSERT INTO movimiento
        (
            id_implemento,
            id_actividad,
            id_asignacion,
            tipo,
            cantidad,
            responsable,
            detalle
        )
        VALUES (?, ?, ?, 'Entrega', ?, ?, ?)"
    );

    $q->bind_param(
        "iiiiss",
        $d["id_implemento"],
        $d["id_actividad"],
        $id,
        $d["cantidad_asignada"],
        $d["responsable"],
        $detalle
    );

    $q->execute();
    $q->close();
}

$accion = $_POST["accion"] ?? "";

if ($accion === "registrar_implemento") {

    $nombre = trim($_POST["nombre"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $tipo = trim($_POST["tipo_control"] ?? "");
    $cantidad = (int)($_POST["cantidad_total"] ?? 0);
    $descripcion = trim($_POST["descripcion"] ?? "");

    if (
        $nombre === "" ||
        $categoria === "" ||
        $cantidad < 1 ||
        !in_array($tipo, ["Reutilizable", "Consumible", "Contenedor"], true)
    ) {
        volver("implementos", "Revisa los datos del implemento.", "error");
    }

    $q = $conexion->prepare(
        "INSERT INTO implemento
        (
            nombre,
            categoria,
            tipo_control,
            cantidad_total,
            cantidad_stock,
            descripcion,
            estado
        )
        VALUES (?, ?, ?, ?, ?, ?, 'Disponible')"
    );

    $q->bind_param(
        "sssiis",
        $nombre,
        $categoria,
        $tipo,
        $cantidad,
        $cantidad,
        $descripcion
    );

    $q->execute();
    $id = $q->insert_id;
    $q->close();

    $detalle = "Registro inicial. Stock: " . $cantidad;

    $q = $conexion->prepare(
        "INSERT INTO movimiento
        (
            id_implemento,
            tipo,
            cantidad,
            detalle
        )
        VALUES (?, 'Registro', ?, ?)"
    );

    $q->bind_param("iis", $id, $cantidad, $detalle);
    $q->execute();
    $q->close();

    volver("implementos", "Implemento registrado correctamente.");
}

if ($accion === "actualizar_implemento") {

    $id =
        (int)($_POST["id_implemento"] ?? 0);

    $nombre =
        trim($_POST["nombre"] ?? "");

    $categoria =
        trim($_POST["categoria"] ?? "");

    $tipo =
        trim($_POST["tipo_control"] ?? "");

    $descripcion =
        trim($_POST["descripcion"] ?? "");

    $tipos_validos = [
        "Reutilizable",
        "Consumible",
        "Contenedor"
    ];

    if (
        $id < 1 ||
        $nombre === "" ||
        $categoria === "" ||
        !in_array($tipo, $tipos_validos, true)
    ) {
        volver(
            "editar_implemento",
            "Revisa los datos del implemento.",
            "error",
            ["id" => $id]
        );
    }

    $q = $conexion->prepare(
        "SELECT tipo_control
         FROM implemento
         WHERE id_implemento = ?"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $actual = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$actual) {
        volver(
            "implementos",
            "El implemento no existe.",
            "error"
        );
    }

    if (
        $actual["tipo_control"] === "Contenedor" &&
        $tipo !== "Contenedor"
    ) {
        $q = $conexion->prepare(
            "SELECT COUNT(*) AS total
             FROM contenido_contenedor
             WHERE id_contenedor = ?"
        );

        $q->bind_param("i", $id);
        $q->execute();
        $contenido = $q->get_result()->fetch_assoc();
        $q->close();

        if ((int)$contenido["total"] > 0) {
            volver(
                "editar_implemento",
                "No puedes cambiar el tipo porque este contenedor tiene contenido registrado.",
                "error",
                ["id" => $id]
            );
        }
    }

    $q = $conexion->prepare(
        "UPDATE implemento
         SET
            nombre = ?,
            categoria = ?,
            tipo_control = ?,
            descripcion = ?
         WHERE id_implemento = ?"
    );

    $q->bind_param(
        "ssssi",
        $nombre,
        $categoria,
        $tipo,
        $descripcion,
        $id
    );

    $q->execute();
    $q->close();

    volver(
        "editar_implemento",
        "Datos del implemento actualizados correctamente.",
        "ok",
        ["id" => $id]
    );
}

if ($accion === "agregar_stock") {

    $id =
        (int)($_POST["id_implemento"] ?? 0);

    $cantidad =
        (int)($_POST["cantidad_agregar"] ?? 0);

    $motivo =
        trim($_POST["motivo"] ?? "");

    if (
        $id < 1 ||
        $cantidad < 1
    ) {
        volver(
            "editar_implemento",
            "La cantidad a agregar debe ser mayor que cero.",
            "error",
            ["id" => $id]
        );
    }

    $q = $conexion->prepare(
        "SELECT
            nombre,
            estado,
            cantidad_total,
            cantidad_stock
         FROM implemento
         WHERE id_implemento = ?"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $implemento = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$implemento) {
        volver(
            "implementos",
            "El implemento no existe.",
            "error"
        );
    }

    $nuevo_estado =
        $implemento["estado"] === "Fuera de servicio"
        ? "Fuera de servicio"
        : "Disponible";

    $conexion->begin_transaction();

    try {

        $q = $conexion->prepare(
            "UPDATE implemento
             SET
                cantidad_total = cantidad_total + ?,
                cantidad_stock = cantidad_stock + ?,
                estado = ?
             WHERE id_implemento = ?"
        );

        $q->bind_param(
            "iisi",
            $cantidad,
            $cantidad,
            $nuevo_estado,
            $id
        );

        $q->execute();
        $q->close();

        $detalle =
            "Ingreso de stock: +" .
            $cantidad .
            " unidades";

        if ($motivo !== "") {
            $detalle .=
                " | " .
                $motivo;
        }

        $q = $conexion->prepare(
            "INSERT INTO movimiento
            (
                id_implemento,
                tipo,
                cantidad,
                detalle
            )
            VALUES (?, 'Ajuste', ?, ?)"
        );

        $q->bind_param(
            "iis",
            $id,
            $cantidad,
            $detalle
        );

        $q->execute();
        $q->close();

        $conexion->commit();

        volver(
            "editar_implemento",
            "Se agregaron " .
            $cantidad .
            " unidades al stock de " .
            $implemento["nombre"] .
            ".",
            "ok",
            ["id" => $id]
        );

    } catch (Throwable $e) {

        $conexion->rollback();

        volver(
            "editar_implemento",
            $e->getMessage(),
            "error",
            ["id" => $id]
        );
    }
}

if ($accion === "crear_actividad") {

    $categoria = trim($_POST["categoria_actividad"] ?? "");
    $otro = trim($_POST["otro_actividad"] ?? "");
    $fecha = trim($_POST["fecha"] ?? "");
    $hora = trim($_POST["hora"] ?? "");
    $lugar = trim($_POST["lugar"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    $validas = [
        "Visita",
        "Feria",
        "Matriculas",
        "Charla",
        "Call center",
        "Expo UCM",
        "Stand",
        "Otro"
    ];

    if (
        !in_array($categoria, $validas, true) ||
        $fecha === "" ||
        $hora === "" ||
        $lugar === ""
    ) {
        volver(
            "actividades",
            "Completa categoria, fecha, hora y lugar.",
            "error"
        );
    }

    if ($categoria === "Otro") {
        if ($otro === "") {
            volver(
                "actividades",
                "Debes especificar el tipo de actividad.",
                "error"
            );
        }

        $nombre = "Otro: " . $otro;
    } else {
        $nombre = $categoria;
    }

    $q = $conexion->prepare(
        "INSERT INTO actividad
        (
            nombre,
            fecha,
            hora,
            lugar,
            descripcion
        )
        VALUES (?, ?, ?, ?, ?)"
    );

    $q->bind_param(
        "sssss",
        $nombre,
        $fecha,
        $hora,
        $lugar,
        $descripcion
    );

    $q->execute();
    $id = $q->insert_id;
    $q->close();

    volver(
        "actividad",
        "Actividad creada correctamente.",
        "ok",
        ["id" => $id]
    );
}

if ($accion === "asignar_implementos") {

    $id_actividad = (int)($_POST["id_actividad"] ?? 0);
    $responsable = trim($_POST["responsable"] ?? "");
    $observacion = trim($_POST["observacion"] ?? "");
    $ids = $_POST["id_implemento"] ?? [];
    $cantidades = $_POST["cantidad"] ?? [];

    if (!is_array($ids) || !is_array($cantidades)) {
        volver(
            "actividad",
            "Los implementos enviados no son validos.",
            "error",
            ["id" => $id_actividad]
        );
    }

    if ($id_actividad < 1 || $responsable === "") {
        volver(
            "actividad",
            "Completa el responsable y los implementos.",
            "error",
            ["id" => $id_actividad]
        );
    }

    $items = [];
    $repetidos = [];

    foreach ($ids as $indice => $valor) {
        $id_implemento = (int)$valor;
        $cantidad = (int)($cantidades[$indice] ?? 0);

        if ($id_implemento < 1 || $cantidad < 1) {
            volver(
                "actividad",
                "Cada implemento debe tener una cantidad valida.",
                "error",
                ["id" => $id_actividad]
            );
        }

        if (isset($repetidos[$id_implemento])) {
            volver(
                "actividad",
                "No repitas el mismo implemento dentro de una asignación.",
                "error",
                ["id" => $id_actividad]
            );
        }

        $repetidos[$id_implemento] = true;

        $items[] = [
            "id_implemento" => $id_implemento,
            "cantidad" => $cantidad
        ];
    }

    if (count($items) === 0) {
        volver(
            "actividad",
            "Agrega al menos un implemento.",
            "error",
            ["id" => $id_actividad]
        );
    }

    $q = $conexion->prepare(
        "SELECT fecha, nombre, lugar
         FROM actividad
         WHERE id_actividad = ?"
    );

    $q->bind_param("i", $id_actividad);
    $q->execute();
    $actividad = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$actividad) {
        volver("actividades", "La actividad no existe.", "error");
    }

    foreach ($items as $item) {
        $disponible = disponibilidad_fecha(
            $conexion,
            $item["id_implemento"],
            $actividad["fecha"]
        );

        if ($item["cantidad"] > $disponible) {
            $q = $conexion->prepare(
                "SELECT nombre
                 FROM implemento
                 WHERE id_implemento = ?"
            );

            $q->bind_param("i", $item["id_implemento"]);
            $q->execute();
            $nombre_item = $q->get_result()->fetch_assoc();
            $q->close();

            $nombre = $nombre_item["nombre"] ?? "Implemento";

            volver(
                "actividad",
                $nombre .
                " no tiene stock suficiente para esa fecha. Disponible: " .
                $disponible .
                ".",
                "error",
                ["id" => $id_actividad]
            );
        }
    }

    $conexion->begin_transaction();

    try {
        $q = $conexion->prepare(
            "INSERT INTO solicitud
            (
                id_actividad,
                solicitante,
                estado,
                observacion
            )
            VALUES (?, ?, 'Aprobada', ?)"
        );

        $q->bind_param(
            "iss",
            $id_actividad,
            $responsable,
            $observacion
        );

        $q->execute();
        $id_solicitud = $q->insert_id;
        $q->close();

        foreach ($items as $item) {
            $id_implemento = $item["id_implemento"];
            $cantidad = $item["cantidad"];

            $q = $conexion->prepare(
                "INSERT INTO detalle_solicitud
                (
                    id_solicitud,
                    id_implemento,
                    cantidad
                )
                VALUES (?, ?, ?)"
            );

            $q->bind_param(
                "iii",
                $id_solicitud,
                $id_implemento,
                $cantidad
            );

            $q->execute();
            $id_detalle = $q->insert_id;
            $q->close();

            $q = $conexion->prepare(
                "INSERT INTO asignacion
                (
                    id_detalle,
                    cantidad_asignada,
                    responsable,
                    estado
                )
                VALUES (?, ?, ?, 'Reservada')"
            );

            $q->bind_param(
                "iis",
                $id_detalle,
                $cantidad,
                $responsable
            );

            $q->execute();
            $id_asignacion = $q->insert_id;
            $q->close();

            $detalle =
                "Reservado para " .
                $actividad["nombre"] .
                " en " .
                $actividad["lugar"];

            $q = $conexion->prepare(
                "INSERT INTO movimiento
                (
                    id_implemento,
                    id_actividad,
                    id_asignacion,
                    tipo,
                    cantidad,
                    responsable,
                    detalle
                )
                VALUES (?, ?, ?, 'Reserva', ?, ?, ?)"
            );

            $q->bind_param(
                "iiiiss",
                $id_implemento,
                $id_actividad,
                $id_asignacion,
                $cantidad,
                $responsable,
                $detalle
            );

            $q->execute();
            $q->close();
        }

        $conexion->commit();

        volver(
            "actividad",
            "Implementos reservados correctamente para " . $responsable . ".",
            "ok",
            ["id" => $id_actividad]
        );
    } catch (Throwable $e) {
        $conexion->rollback();

        volver(
            "actividad",
            $e->getMessage(),
            "error",
            ["id" => $id_actividad]
        );
    }
}

if ($accion === "registrar_entrega") {

    $id = (int)($_POST["id_asignacion"] ?? 0);
    $observacion = trim($_POST["observacion"] ?? "");

    $q = $conexion->prepare(
        "SELECT
            a.id_asignacion,
            a.cantidad_asignada,
            a.responsable,
            d.id_implemento,
            s.id_actividad,
            ac.nombre AS actividad,
            ac.lugar
         FROM asignacion a
         INNER JOIN detalle_solicitud d
            ON d.id_detalle = a.id_detalle
         INNER JOIN solicitud s
            ON s.id_solicitud = d.id_solicitud
         INNER JOIN actividad ac
            ON ac.id_actividad = s.id_actividad
         WHERE a.id_asignacion = ?
           AND a.estado = 'Reservada'"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$d) {
        volver(
            "entregas",
            "La asignación no está disponible para entrega.",
            "error"
        );
    }

    $q = $conexion->prepare(
        "UPDATE asignacion
         SET
            estado = 'Entregada',
            fecha_entrega = NOW()
         WHERE id_asignacion = ?"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $q->close();

    $detalle =
        "Entrega para " .
        $d["actividad"] .
        " en " .
        $d["lugar"];

    if ($observacion !== "") {
        $detalle .= " | " . $observacion;
    }

    $q = $conexion->prepare(
        "INSERT INTO movimiento
        (
            id_implemento,
            id_actividad,
            id_asignacion,
            tipo,
            cantidad,
            responsable,
            detalle
        )
        VALUES (?, ?, ?, 'Entrega', ?, ?, ?)"
    );

    $q->bind_param(
        "iiiiss",
        $d["id_implemento"],
        $d["id_actividad"],
        $id,
        $d["cantidad_asignada"],
        $d["responsable"],
        $detalle
    );

    $q->execute();
    $q->close();

    volver("entregas", "Entrega registrada correctamente.");
}

if ($accion === "registrar_resultado") {

    $id = (int)($_POST["id_asignacion"] ?? 0);
    $cantidad = max(0, (int)($_POST["cantidad_resultado"] ?? 0));
    $resultado = trim($_POST["resultado"] ?? "");
    $observacion = trim($_POST["observacion"] ?? "");

    $resultados_validos = [
        "Entregado",
        "Devuelto",
        "Consumido",
        "Danado",
        "Perdido",
        "Custodia"
    ];

    if (
        $id < 1 ||
        $cantidad < 1 ||
        !in_array($resultado, $resultados_validos, true)
    ) {
        volver(
            "entregas",
            "Revisa la cantidad y el resultado seleccionado.",
            "error"
        );
    }

    $q = $conexion->prepare(
        "SELECT
            a.*,
            d.id_implemento,
            i.tipo_control,
            i.cantidad_stock,
            i.nombre AS implemento,
            s.id_actividad,
            ac.nombre AS actividad,
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
         WHERE a.id_asignacion = ?
           AND a.estado IN ('Reservada', 'Entregada', 'Parcial', 'En custodia')"
    );

    $q->bind_param("i", $id);
    $q->execute();
    $d = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$d) {
        volver(
            "entregas",
            "La asignación no está disponible para registrar movimientos.",
            "error"
        );
    }

    $estaba_reservada =
        $d["estado"] === "Reservada";

    if ($resultado === "Entregado") {

        if (!$estaba_reservada) {
            volver(
                "entregas",
                "Este implemento ya fue entregado.",
                "error"
            );
        }

        if ($cantidad !== (int)$d["cantidad_asignada"]) {
            volver(
                "entregas",
                "Para registrar la entrega debes seleccionar toda la cantidad asignada.",
                "error"
            );
        }

        $conexion->begin_transaction();

        try {
            registrar_entrega_interna(
                $conexion,
                $d,
                $id
            );

            $conexion->commit();

            volver(
                "entregas",
                "Entrega registrada correctamente."
            );

        } catch (Throwable $e) {
            $conexion->rollback();

            volver(
                "entregas",
                $e->getMessage(),
                "error"
            );
        }
    }

    $registrado_antes =
        (int)$d["cantidad_devuelta"] +
        (int)$d["cantidad_utilizada"] +
        (int)$d["cantidad_danada"] +
        (int)$d["cantidad_perdida"];

    $pendiente =
        (int)$d["cantidad_asignada"] -
        $registrado_antes;

    if ($cantidad > $pendiente) {
        volver(
            "entregas",
            "No puedes registrar más unidades que las pendientes. Pendientes: " .
            $pendiente .
            ".",
            "error"
        );
    }

    if (
        $resultado === "Consumido" &&
        $d["tipo_control"] !== "Consumible"
    ) {
        volver(
            "entregas",
            "Solo los implementos consumibles pueden registrarse como consumidos o entregados.",
            "error"
        );
    }

    if (
        $resultado === "Custodia" &&
        $cantidad !== $pendiente
    ) {
        volver(
            "entregas",
            "Para mantener en custodia debes seleccionar todas las unidades pendientes de este implemento.",
            "error"
        );
    }

    if ($resultado === "Custodia") {

        $conexion->begin_transaction();

        try {
            if ($estaba_reservada) {
                registrar_entrega_interna(
                    $conexion,
                    $d,
                    $id
                );
            }

            $q = $conexion->prepare(
                "UPDATE asignacion
                 SET
                    estado = 'En custodia',
                    observacion_cierre = ?
                 WHERE id_asignacion = ?"
            );

            $q->bind_param(
                "si",
                $observacion,
                $id
            );

            $q->execute();
            $q->close();

            $detalle =
                "Mantiene en custodia: " .
                $cantidad .
                " unidad(es)";

            if ($observacion !== "") {
                $detalle .= " | " . $observacion;
            }

            $q = $conexion->prepare(
                "INSERT INTO movimiento
                (
                    id_implemento,
                    id_actividad,
                    id_asignacion,
                    tipo,
                    cantidad,
                    responsable,
                    detalle
                )
                VALUES (?, ?, ?, 'Custodia', ?, ?, ?)"
            );

            $q->bind_param(
                "iiiiss",
                $d["id_implemento"],
                $d["id_actividad"],
                $id,
                $cantidad,
                $d["responsable"],
                $detalle
            );

            $q->execute();
            $q->close();

            $conexion->commit();

            volver(
                "entregas",
                "Custodia registrada. El implemento sigue asociado a " .
                $d["responsable"] .
                "."
            );

        } catch (Throwable $e) {
            $conexion->rollback();

            volver(
                "entregas",
                $e->getMessage(),
                "error"
            );
        }
    }

    $devuelta = 0;
    $utilizada = 0;
    $danada = 0;
    $perdida = 0;
    $tipo_inc = "";

    if ($resultado === "Devuelto") {
        $devuelta = $cantidad;
    } elseif ($resultado === "Consumido") {
        $utilizada = $cantidad;
    } elseif ($resultado === "Danado") {
        $danada = $cantidad;
        $tipo_inc = "Dano";
    } elseif ($resultado === "Perdido") {
        $perdida = $cantidad;
        $tipo_inc = "Perdida";
    }

    $nuevo_devuelto =
        (int)$d["cantidad_devuelta"] +
        $devuelta;

    $nuevo_utilizado =
        (int)$d["cantidad_utilizada"] +
        $utilizada;

    $nuevo_danado =
        (int)$d["cantidad_danada"] +
        $danada;

    $nuevo_perdido =
        (int)$d["cantidad_perdida"] +
        $perdida;

    $nuevo_total_registrado =
        $nuevo_devuelto +
        $nuevo_utilizado +
        $nuevo_danado +
        $nuevo_perdido;

    $completa =
        $nuevo_total_registrado ===
        (int)$d["cantidad_asignada"];

    $descuento =
        $utilizada +
        $danada +
        $perdida;

    $nuevo_stock = max(
        0,
        (int)$d["cantidad_stock"] -
        $descuento
    );

    $conexion->begin_transaction();

    try {
        if ($estaba_reservada) {
            registrar_entrega_interna(
                $conexion,
                $d,
                $id
            );
        }

        $q = $conexion->prepare(
            "INSERT INTO devolucion
            (
                id_asignacion,
                cantidad_devuelta,
                cantidad_utilizada,
                cantidad_danada,
                cantidad_perdida,
                observacion
            )
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $q->bind_param(
            "iiiiis",
            $id,
            $devuelta,
            $utilizada,
            $danada,
            $perdida,
            $observacion
        );

        $q->execute();
        $q->close();

        if ($completa) {
            $q = $conexion->prepare(
                "UPDATE asignacion
                 SET
                    cantidad_devuelta = ?,
                    cantidad_utilizada = ?,
                    cantidad_danada = ?,
                    cantidad_perdida = ?,
                    observacion_cierre = ?,
                    fecha_cierre = NOW(),
                    estado = 'Cerrada'
                 WHERE id_asignacion = ?"
            );
        } else {
            $q = $conexion->prepare(
                "UPDATE asignacion
                 SET
                    cantidad_devuelta = ?,
                    cantidad_utilizada = ?,
                    cantidad_danada = ?,
                    cantidad_perdida = ?,
                    observacion_cierre = ?,
                    estado = 'Parcial'
                 WHERE id_asignacion = ?"
            );
        }

        $q->bind_param(
            "iiiisi",
            $nuevo_devuelto,
            $nuevo_utilizado,
            $nuevo_danado,
            $nuevo_perdido,
            $observacion,
            $id
        );

        $q->execute();
        $q->close();

        if ($descuento > 0) {
            $estado_stock =
                $nuevo_stock > 0
                ? "Disponible"
                : "Sin stock";

            $q = $conexion->prepare(
                "UPDATE implemento
                 SET
                    cantidad_stock = ?,
                    estado = ?
                 WHERE id_implemento = ?"
            );

            $q->bind_param(
                "isi",
                $nuevo_stock,
                $estado_stock,
                $d["id_implemento"]
            );

            $q->execute();
            $q->close();
        }

        if ($resultado === "Devuelto") {
            $resultado_texto = "Devuelto";
        } elseif ($resultado === "Consumido") {
            $resultado_texto = "Consumido / entregado";
        } elseif ($resultado === "Danado") {
            $resultado_texto = "Dañado";
        } else {
            $resultado_texto = "Perdido";
        }

        $detalle =
            "Resultado: " .
            $resultado_texto .
            " | Cantidad: " .
            $cantidad;

        if ($observacion !== "") {
            $detalle .= " | " . $observacion;
        }

        $q = $conexion->prepare(
            "INSERT INTO movimiento
            (
                id_implemento,
                id_actividad,
                id_asignacion,
                tipo,
                cantidad,
                responsable,
                detalle
            )
            VALUES (?, ?, ?, 'Devolucion', ?, ?, ?)"
        );

        $q->bind_param(
            "iiiiss",
            $d["id_implemento"],
            $d["id_actividad"],
            $id,
            $cantidad,
            $d["responsable"],
            $detalle
        );

        $q->execute();
        $q->close();

        if ($tipo_inc !== "") {

            if ($observacion !== "") {
                $detalle_inc = $observacion;
            } elseif ($tipo_inc === "Dano") {
                $detalle_inc = "Se registró un daño durante la devolución.";
            } else {
                $detalle_inc = "Se registró una pérdida durante la devolución.";
            }

            $q = $conexion->prepare(
                "INSERT INTO incidencia
                (
                    id_asignacion,
                    tipo,
                    descripcion
                )
                VALUES (?, ?, ?)"
            );

            $q->bind_param(
                "iss",
                $id,
                $tipo_inc,
                $detalle_inc
            );

            $q->execute();
            $q->close();

            $q = $conexion->prepare(
                "INSERT INTO movimiento
                (
                    id_implemento,
                    id_actividad,
                    id_asignacion,
                    tipo,
                    responsable,
                    detalle
                )
                VALUES (?, ?, ?, 'Incidencia', ?, ?)"
            );

            $q->bind_param(
                "iiiss",
                $d["id_implemento"],
                $d["id_actividad"],
                $id,
                $d["responsable"],
                $detalle_inc
            );

            $q->execute();
            $q->close();
        }

        if ($completa) {
            $q = $conexion->prepare(
                "INSERT INTO movimiento
                (
                    id_implemento,
                    id_actividad,
                    id_asignacion,
                    tipo,
                    cantidad,
                    responsable,
                    detalle
                )
                VALUES (?, ?, ?, 'Cierre', ?, ?, ?)"
            );

            $detalle_cierre =
                "Asignacion cerrada. No quedan unidades pendientes.";

            $q->bind_param(
                "iiiiss",
                $d["id_implemento"],
                $d["id_actividad"],
                $id,
                $d["cantidad_asignada"],
                $d["responsable"],
                $detalle_cierre
            );

            $q->execute();
            $q->close();
        }

        $conexion->commit();

        if ($completa) {
            volver(
                "entregas",
                "Movimiento registrado y asignación cerrada."
            );
        }

        $restante =
            (int)$d["cantidad_asignada"] -
            $nuevo_total_registrado;

        volver(
            "entregas",
            "Movimiento registrado. Aún quedan " .
            $restante .
            " unidades pendientes."
        );

    } catch (Throwable $e) {
        $conexion->rollback();

        volver(
            "entregas",
            $e->getMessage(),
            "error"
        );
    }
}

if ($accion === "gestionar_incidencia") {

    $id =
        (int)($_POST["id_incidencia"] ?? 0);

    $accion_tomada =
        trim($_POST["accion_tomada"] ?? "");

    $observacion_gestion =
        trim($_POST["observacion_gestion"] ?? "");

    $acciones_validas = [
        "Reparado",
        "Dado de baja",
        "Repuesto",
        "No requiere reposicion",
        "Pendiente de reposicion",
        "Informado a coordinacion",
        "Otro"
    ];

    if (
        $id < 1 ||
        !in_array(
            $accion_tomada,
            $acciones_validas,
            true
        )
    ) {
        volver(
            "entregas",
            "Selecciona una accion valida para la incidencia.",
            "error"
        );
    }

    if (
        $accion_tomada === "Otro" &&
        $observacion_gestion === ""
    ) {
        volver(
            "entregas",
            "Si seleccionas Otro, describe la gestion realizada.",
            "error"
        );
    }

    $q = $conexion->prepare(
        "SELECT
            inc.id_incidencia,
            inc.tipo,
            d.id_implemento,
            s.id_actividad,
            a.id_asignacion,
            a.responsable,
            i.nombre AS implemento
         FROM incidencia inc
         INNER JOIN asignacion a
            ON a.id_asignacion = inc.id_asignacion
         INNER JOIN detalle_solicitud d
            ON d.id_detalle = a.id_detalle
         INNER JOIN implemento i
            ON i.id_implemento = d.id_implemento
         INNER JOIN solicitud s
            ON s.id_solicitud = d.id_solicitud
         WHERE inc.id_incidencia = ?"
    );

    $q->bind_param(
        "i",
        $id
    );

    $q->execute();

    $incidencia =
        $q
        ->get_result()
        ->fetch_assoc();

    $q->close();

    if (!$incidencia) {
        volver(
            "entregas",
            "La incidencia no existe.",
            "error"
        );
    }

    $conexion->begin_transaction();

    try {

        $q = $conexion->prepare(
            "UPDATE incidencia
             SET
                estado = 'Gestionada',
                accion_tomada = ?,
                observacion_gestion = ?,
                fecha_gestion = NOW()
             WHERE id_incidencia = ?"
        );

        $q->bind_param(
            "ssi",
            $accion_tomada,
            $observacion_gestion,
            $id
        );

        $q->execute();
        $q->close();

        $acciones_texto = [
            "Reparado" => "Reparado",
            "Dado de baja" => "Dado de baja",
            "Repuesto" => "Repuesto",
            "No requiere reposicion" => "No requiere reposicion",
            "Pendiente de reposicion" => "Pendiente de reposicion",
            "Informado a coordinacion" => "Informado a coordinacion",
            "Otro" => "Otro"
        ];

        $detalle =
            "Gestion de incidencia: " .
            $acciones_texto[$accion_tomada];

        if ($observacion_gestion !== "") {
            $detalle .=
                " | " .
                $observacion_gestion;
        }

        $q = $conexion->prepare(
            "INSERT INTO movimiento
            (
                id_implemento,
                id_actividad,
                id_asignacion,
                tipo,
                responsable,
                detalle
            )
            VALUES (?, ?, ?, 'Incidencia', ?, ?)"
        );

        $q->bind_param(
            "iiiss",
            $incidencia["id_implemento"],
            $incidencia["id_actividad"],
            $incidencia["id_asignacion"],
            $incidencia["responsable"],
            $detalle
        );

        $q->execute();
        $q->close();

        $conexion->commit();

        volver(
            "entregas",
            "Gestion de incidencia guardada correctamente."
        );

    } catch (Throwable $e) {

        $conexion->rollback();

        volver(
            "entregas",
            $e->getMessage(),
            "error"
        );
    }
}

if ($accion === "agregar_contenido") {

    $id_contenedor = (int)($_POST["id_contenedor"] ?? 0);
    $id_implemento = (int)($_POST["id_implemento"] ?? 0);
    $cantidad = (int)($_POST["cantidad"] ?? 0);

    if (
        $id_contenedor < 1 ||
        $id_implemento < 1 ||
        $cantidad < 1 ||
        $id_contenedor === $id_implemento
    ) {
        volver(
            "implemento",
            "Revisa el contenido seleccionado.",
            "error",
            ["id" => $id_contenedor]
        );
    }

    $q = $conexion->prepare(
        "SELECT tipo_control
         FROM implemento
         WHERE id_implemento = ?"
    );

    $q->bind_param("i", $id_contenedor);
    $q->execute();
    $c = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$c || $c["tipo_control"] !== "Contenedor") {
        volver(
            "implementos",
            "El implemento seleccionado no es un contenedor.",
            "error"
        );
    }

    $q = $conexion->prepare(
        "INSERT INTO contenido_contenedor
        (
            id_contenedor,
            id_implemento,
            cantidad
        )
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            cantidad = VALUES(cantidad)"
    );

    $q->bind_param(
        "iii",
        $id_contenedor,
        $id_implemento,
        $cantidad
    );

    $q->execute();
    $q->close();

    volver(
        "implemento",
        "Contenido actualizado correctamente.",
        "ok",
        ["id" => $id_contenedor]
    );
}

volver(
    "inicio",
    "Accion no reconocida.",
    "error"
);
