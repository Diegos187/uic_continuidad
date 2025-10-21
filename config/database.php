<?php
class Database {
    private $host = 'localhost';
    private $usuario = 'root';
    private $password = '';
    private $database = 'utem_curriculum';
    private $conexion;

    public function conectar() {
        try {
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->database}", 
                $this->usuario, 
                $this->password
            );
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $this->conexion;
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public function desconectar() {
        $this->conexion = null;
    }
}
?>