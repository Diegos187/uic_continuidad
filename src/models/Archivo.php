<?php
class Archivo {
    private $conexion;
    private $tabla = 'archivos';

    public function __construct($db) {
        $this->conexion = $db;
    }

    public function subir($nombre, $descripcion, $tipo, $contenido, $usuario_id) {
        try {
            $query = "INSERT INTO " . $this->tabla . " 
                     (nombre, descripcion, tipo, contenido, usuario_id) 
                     VALUES (:nombre, :descripcion, :tipo, :contenido, :usuario_id)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':contenido', $contenido);
            $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al subir archivo: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerTodos() {
        try {
            $query = "SELECT a.*, u.nombre as usuario_nombre 
                     FROM " . $this->tabla . " a 
                     JOIN usuarios u ON a.usuario_id = u.id 
                     ORDER BY a.created_at DESC";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener archivos: " . $e->getMessage());
            return [];
        }
    }

    public function obtener($id) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener archivo: " . $e->getMessage());
            return null;
        }
    }

    public function eliminar($id) {
        try {
            $query = "DELETE FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al eliminar archivo: " . $e->getMessage());
            return false;
        }
    }
}