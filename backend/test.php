<?php
require_once 'config/bdd.php';

$dbb = new BDD();
$conexion = $dbb ->Conexion();

if($conexion){
    echo "Exito";
} else{
    echo "Fallo";
}
