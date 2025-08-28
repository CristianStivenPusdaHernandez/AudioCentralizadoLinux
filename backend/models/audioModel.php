<?php
require_once '../config/bdd.php';

class Audio{
    private $conexion;
    private $tabla = "audio";

    public function __construct(){
        $bdd = new BDD();
        $this -> conexion = $bdd->Conexion();
    }

    public function Audios(){
        $consulta = "select * from ".$this->tabla;
        $stmt = $this->conexion->prepare($consulta);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?>