<?php

class CompetenciaDominio
{
    private $conn;
    private $tabla = 'competencias_dominio';

    public function __construct($conexion)
    {
        $this->conn = $conexion;
    }

    public function crear($perfil_egreso_detalle_id, $codigo, $descripcion)
    {
        // Obtener orden máxima actual
        $sql = "SELECT MAX(orden) as max_orden FROM {$this->tabla} WHERE perfil_egreso_detalle_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $perfil_egreso_detalle_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nuevo_orden = ($result['max_orden'] ?? 0) + 1;

        $sql = "INSERT INTO {$this->tabla} (perfil_egreso_detalle_id, codigo, descripcion, orden) 
                VALUES (:perfil_id, :codigo, :descripcion, :orden)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':perfil_id', $perfil_egreso_detalle_id);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':descripcion', $descripcion);
        $stmt->bindParam(':orden', $nuevo_orden);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function obtenerPorDetalle($perfil_egreso_detalle_id)
    {
        $sql = "SELECT * FROM {$this->tabla} 
                WHERE perfil_egreso_detalle_id = :id 
                ORDER BY orden ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $perfil_egreso_detalle_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id)
    {
        $sql = "SELECT * FROM {$this->tabla} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizar($id, $codigo, $descripcion)
    {
        $sql = "UPDATE {$this->tabla} SET codigo = :codigo, descripcion = :descripcion WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':codigo', $codigo);
        $stmt->bindParam(':descripcion', $descripcion);
        return $stmt->execute();
    }

    public function eliminar($id)
    {
        $sql = "DELETE FROM {$this->tabla} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
