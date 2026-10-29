<?php

$servidor = "127.0.0.1";
$usuario = "root";
$clave = "";
$base_datos = "gestion_implementos";

$conexion = new mysqli(
    $servidor,
    $usuario,
    $clave,
    $base_datos
);

if ($conexion->connect_error) {
    die(
        "Error de conexion: " .
        $conexion->connect_error
    );
}

$conexion->set_charset("utf8mb4");