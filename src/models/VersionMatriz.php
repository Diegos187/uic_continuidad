<?php
class VersionMatriz
{
    private $conexion;
    private $tabla = 'versiones_matriz';

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function crear($matriz_id, $carrera_id, $descripcion)
    {
        try {
            // Determinar el próximo número de versión para la matriz específica
            $sqlMax = "SELECT COALESCE(MAX(numero_version), 0) AS maxv FROM " . $this->tabla . " WHERE matriz_id = :matriz_id";
            $stMax = $this->conexion->prepare($sqlMax);
            $stMax->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stMax->execute();
            $row = $stMax->fetch(PDO::FETCH_ASSOC);
            $num = (int)($row['maxv'] ?? 0) + 1;

            $sql = "INSERT INTO " . $this->tabla . " (matriz_id, carrera_id, numero_version, descripcion) VALUES (:matriz_id, :carrera_id, :numero_version, :descripcion)";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            $stmt->bindParam(':numero_version', $num, PDO::PARAM_INT);
            $stmt->bindParam(':descripcion', $descripcion);
            if ($stmt->execute()) {
                return $this->conexion->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log('Error crear versión de matriz: ' . $e->getMessage());
            return false;
        }
    }

    public function obtenerPorMatriz($matriz_id)
    {
        try {
            $sql = "SELECT v.*, (
                        SELECT COUNT(*) FROM matrices_coherencia mc WHERE mc.version_id = v.id
                    ) AS filas_count
                    FROM " . $this->tabla . " v
                    WHERE v.matriz_id = :matriz_id
                    ORDER BY v.fecha_creacion DESC, v.numero_version DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error obtener versiones por matriz: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerPorCarrera($carrera_id)
    {
        try {
            $sql = "SELECT v.*, (
                        SELECT COUNT(*) FROM matrices_coherencia mc WHERE mc.version_id = v.id
                    ) AS filas_count
                    FROM " . $this->tabla . " v
                    WHERE v.carrera_id = :carrera_id
                    ORDER BY v.fecha_creacion DESC, v.numero_version DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error obtener versiones por carrera: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $sql = "SELECT v.*, (
                        SELECT COUNT(*) FROM matrices_coherencia mc WHERE mc.version_id = v.id
                    ) AS filas_count
                    FROM " . $this->tabla . " v
                    WHERE v.id = :id";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener versión por ID: ' . $e->getMessage());
            return null;
        }
    }

    public function obtenerVersionesPorMatriz($matriz_id)
    {
        try {
            // Obtener todas las versiones de la matriz específica
            $sql = "SELECT v.*, (
                        SELECT COUNT(*) FROM matrices_coherencia mc WHERE mc.version_id = v.id
                    ) AS filas_count
                    FROM " . $this->tabla . " v
                    WHERE v.matriz_id = :matriz_id
                    ORDER BY v.fecha_creacion DESC, v.numero_version DESC";
            $stmt = $this->conexion->prepare($sql);
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error al obtener versiones de matriz: ' . $e->getMessage());
            return [];
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
            error_log('Error al eliminar versión de matriz: ' . $e->getMessage());
            return false;
        }
    }
}
