<?php
// Carrera.php
class Carrera {
    private $conexion;
    private $tabla = 'carreras';

    public function __construct($db) {
        $this->conexion = $db;
    }

    // Obtener todas las carreras
    public function obtenerTodas() {
        try {
            $query = "SELECT * FROM " . $this->tabla;
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener carreras: " . $e->getMessage());
            return [];
        }
    }

    // Obtener una carrera por ID
    public function obtenerPorId($id) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener carrera por ID: " . $e->getMessage());
            return null;
        }
    }

    // Crear nueva carrera
    public function crear($datos) {
        try {
            $query = "INSERT INTO " . $this->tabla . "
                    (nombre, jornada, duracion_semestres, anio)
                    VALUES (:nombre, :jornada, :duracion_semestres, :anio)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':jornada', $datos['jornada']);
            $stmt->bindParam(':duracion_semestres', $datos['duracion_semestres'], PDO::PARAM_INT);
            $stmt->bindParam(':anio', $datos['anio'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return $this->conexion->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error al crear carrera: " . $e->getMessage());
            return false;
        }
    }

    // Actualizar carrera
    public function actualizar($id, $datos) {
        try {
            $query = "UPDATE " . $this->tabla . "
                    SET nombre = :nombre,
                        jornada = :jornada,
                        duracion_semestres = :duracion_semestres,
                        anio = :anio
                    WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':jornada', $datos['jornada']);
            $stmt->bindParam(':duracion_semestres', $datos['duracion_semestres'], PDO::PARAM_INT);
            $stmt->bindParam(':anio', $datos['anio'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al actualizar carrera: " . $e->getMessage());
            return false;
        }
    }

    // Eliminar carrera
    public function eliminar($id) {
        try {
            $query = "DELETE FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al eliminar carrera: " . $e->getMessage());
            return false;
        }
    }
}

?>