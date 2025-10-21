<?php
class Atributo
{
    private $conexion;

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function obtenerTodos()
    {
        $stmt = $this->conexion->query("
            SELECT a.id, a.tipo, a.descripcion, c.nombre AS nombre_asignatura 
            FROM atributos a
            JOIN carreras c ON a.carrera_id = c.id
            ORDER BY a.id
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id)
    {
        $stmt = $this->conexion->prepare("
            SELECT a.id, a.tipo, a.descripcion, a.carrera_id 
            FROM atributos a
            WHERE a.id = :id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function crear($tipo, $descripcion, $carrera_id)
    {
        try {
            $stmt = $this->conexion->prepare("
                INSERT INTO atributos (tipo, descripcion, carrera_id) 
                VALUES (:tipo, :descripcion, :carrera_id)
            ");
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':carrera_id', $carrera_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function actualizar($id, $tipo, $descripcion, $carrera_id)
    {
        try {
            $stmt = $this->conexion->prepare("
                UPDATE atributos 
                SET tipo = :tipo, descripcion = :descripcion, carrera_id = :carrera_id
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':carrera_id', $carrera_id);
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminar($id)
    {
        $stmt = $this->conexion->prepare("DELETE FROM atributos WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function obtenerCarreras()
    {
        $stmt = $this->conexion->query("SELECT id, nombre FROM carreras");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDominios($asignaturaId)
    {
        $stmt = $this->conexion->prepare("\n            SELECT d.id, d.nombre AS descripcion\n            FROM dominios d\n            WHERE d.carrera_id = :carrera_id\n            ORDER BY d.nombre ASC\n        ");
        $stmt->bindParam(':carrera_id', $asignaturaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCompetencias($asignaturaId)
    {
        $stmt = $this->conexion->prepare("\n            SELECT c.id, c.descripcion\n            FROM competencias c\n            INNER JOIN dominios d ON c.dominio_id = d.id\n            WHERE d.carrera_id = :carrera_id\n            ORDER BY c.descripcion ASC\n        ");
        $stmt->bindParam(':carrera_id', $asignaturaId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerResultados($carreraId)
    {
        $stmt = $this->conexion->prepare("\n            SELECT id, descripcion\n            FROM atributos\n            WHERE tipo = 'Resultado Aprendizaje'\n              AND carrera_id = :carrera_id\n            ORDER BY id DESC\n        ");
        $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
