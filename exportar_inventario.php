<?php
require_once __DIR__ . "/config/conexion.php";

$sql = "SELECT
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
ORDER BY i.nombre";

$implementos = $conexion->query($sql);

if ($implementos === false) {
    http_response_code(500);
    exit("No se pudo exportar el inventario.");
}

function valor_csv_seguro($valor)
{
    $texto = (string)($valor ?? "");

    if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $texto)) {
        return "'" . $texto;
    }

    return $texto;
}

header("Content-Type: text/csv; charset=UTF-8");
header(
    'Content-Disposition: attachment; filename="inventario_implementos_'
    . date("Y-m-d") . '.csv"'
);
header("Cache-Control: no-store");

$salida = fopen("php://output", "w");

fwrite($salida, "\xEF\xBB\xBF");

fputcsv($salida, [
    "Codigo",
    "Implemento",
    "Descripcion",
    "Categoria",
    "Tipo de control",
    "Stock actual",
    "Stock total",
    "Fuera de bodega",
    "Reservado hoy",
    "Disponible ahora",
    "Estado"
], ";", '"', "");

while ($i = $implementos->fetch_assoc()) {
    $fuera = (int)$i["fuera_bodega"];
    $reservado = (int)$i["reservado_hoy"];

    $disponible = max(
        0,
        (int)$i["cantidad_stock"] - $fuera - $reservado
    );

    if ($i["estado"] === "Fuera de servicio") {
        $estado = "Fuera de servicio";
    } elseif ($disponible === 0) {
        $estado = "No disponible";
    } elseif ($fuera > 0 || $reservado > 0) {
        $estado = "Disponibilidad parcial";
    } else {
        $estado = "Disponible";
    }

    fputcsv($salida, [
        "IMP-" . str_pad(
            (string)$i["id_implemento"],
            3,
            "0",
            STR_PAD_LEFT
        ),
        valor_csv_seguro($i["nombre"]),
        valor_csv_seguro($i["descripcion"]),
        valor_csv_seguro($i["categoria"]),
        valor_csv_seguro($i["tipo_control"]),
        (int)$i["cantidad_stock"],
        (int)$i["cantidad_total"],
        $fuera,
        $reservado,
        $disponible,
        $estado
    ], ";", '"', "");
}

fclose($salida);
$conexion->close();
exit;