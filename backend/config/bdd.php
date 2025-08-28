<?php
    class BDD{
        private $host = "localhost";
        private $dbname = "appestacion";
        private $usuario = "root";
        private $password = "";
        public $conexion;

        public function Conexion(){
            $this->conexion = null;
            try{
                $this -> conexion = new PDO("mysql:host=".$this->host.";dbname=".$this->dbname,
                $this->usuario, $this->password);

                $this->conexion->exec("set names utf8");

            }catch(PDOException $exeption){
                echo "Error: ".$exeption->getMessage();
            }
             return $this->conexion;
        }

    }


?>
