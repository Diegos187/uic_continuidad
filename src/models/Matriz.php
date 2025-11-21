<?php
class Matriz
{
    private $conexion;
    private $tabla = 'matrices';

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function crear($carrera_id, $version_id, $nombre, $descripcion = null)
    {
        try {
            $sql = "INSERT INTO " . $this->tabla . " (carrera_id, version_id, nombre, descripcion) VALUES (:carrera_id, :version_id, :nombre, :descripcion)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            if ($version_id === null) {
                $stmt->bindValue(':version_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':version_id', (int)$version_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':descripcion', $descripcion);
            if ($stmt->execute()) {
                return $this->conexion->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log('Error al crear matriz: ' . $e->getMessage());
            return false;
        }
    }

    public function obtenerPorCarrera($carrera_id)
    {
        try {
            $sql = "SELECT m.*, 
                    v.descripcion AS version_descripcion,
                    v.numero_version AS version_numero,
                    (
                        SELECT COUNT(*) FROM matrices_coherencia mc WHERE mc.matriz_id = m.id
                    ) AS filas_count
                    FROM " . $this->tabla . " m
                    LEFT JOIN versiones_matriz v ON v.id = m.version_id
                    WHERE m.carrera_id = :carrera_id
                    ORDER BY m.fecha_creacion DESC, m.id DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener matrices por carrera: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT m.*, c.nombre AS carrera_nombre
                    FROM " . $this->tabla . " m
                    JOIN carreras c ON c.id = m.carrera_id
                    WHERE m.id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener matriz por ID: ' . $e->getMessage());
            return null;
        }
    }

    public function eliminar($id)
    {
        try {
            $sql = "DELETE FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Error al eliminar matriz: ' . $e->getMessage());
            return false;
        }
    }
}
