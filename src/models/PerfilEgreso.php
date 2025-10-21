<?php
class PerfilEgreso
{
    private $conexion;
    private $tabla = 'perfiles_egreso';

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function crear($carreraId, $descripcion)
    {
        $stmt = $this->conexion->prepare("INSERT INTO " . $this->tabla . " (carrera_id, descripcion) VALUES (:carrera_id, :descripcion)");
        $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
        $stmt->bindParam(':descripcion', $descripcion);
        if ($stmt->execute()) {
            return (int)$this->conexion->lastInsertId();
        }
        return false;
    }

    public function obtenerPorCarrera($carreraId)
    {
        $stmt = $this->conexion->prepare("SELECT id, descripcion, created_at FROM " . $this->tabla . " WHERE carrera_id = :carrera_id ORDER BY id DESC");
        $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id)
    {
        $stmt = $this->conexion->prepare("SELECT id, carrera_id, descripcion, created_at FROM " . $this->tabla . " WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function actualizar($id, $descripcion)
    {
        $stmt = $this->conexion->prepare("UPDATE " . $this->tabla . " SET descripcion = :descripcion WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
        return $stmt->execute();
    }
}
