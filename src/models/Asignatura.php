<?php
// Asignatura.php
class Asignatura {
    private $conexion;
    private $tabla = 'asignaturas';

    public function __construct($db) {
        $this->conexion = $db;
    }

    // Obtener todas las asignaturas
    public function obtenerTodas() {
        try {
            $query = "SELECT a.*, c.nombre as nombre_carrera 
                     FROM " . $this->tabla . " a
                     JOIN carreras c ON a.carrera_id = c.id";
            $stmt = $this->conexion->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener asignaturas: " . $e->getMessage());
            return [];
        }
    }

    // Obtener asignaturas por carrera
    public function obtenerPorCarrera($carreraId) {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE carrera_id = :carrera_id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':carrera_id', $carreraId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener asignaturas por carrera: " . $e->getMessage());
            return [];
        }
    }

    // Obtener una asignatura por ID
    public function obtenerPorId($id) {
        try {
            $query = "SELECT a.*, c.nombre as nombre_carrera 
                     FROM " . $this->tabla . " a
                     JOIN carreras c ON a.carrera_id = c.id
                     WHERE a.id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error al obtener asignatura por ID: " . $e->getMessage());
            return null;
        }
    }

    // Crear nueva asignatura
    public function crear($datos) {
        try {
            $query = "INSERT INTO " . $this->tabla . "
                    (nombre, carrera_id, semestre, duracion_semanas)
                    VALUES (:nombre, :carrera_id, :semestre, :duracion_semanas)";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':carrera_id', $datos['carrera_id'], PDO::PARAM_INT);
            $stmt->bindParam(':semestre', $datos['semestre'], PDO::PARAM_INT);
            $stmt->bindParam(':duracion_semanas', $datos['duracion_semanas'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return $this->conexion->lastInsertId();
            }
            return false;
        } catch(PDOException $e) {
            error_log("Error al crear asignatura: " . $e->getMessage());
            return false;
        }
    }

    // Actualizar asignatura
    public function actualizar($id, $datos) {
        try {
            $query = "UPDATE " . $this->tabla . "
                    SET nombre = :nombre,
                        carrera_id = :carrera_id,
                        semestre = :semestre,
                        duracion_semanas = :duracion_semanas
                    WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':nombre', $datos['nombre']);
            $stmt->bindParam(':carrera_id', $datos['carrera_id'], PDO::PARAM_INT);
            $stmt->bindParam(':semestre', $datos['semestre'], PDO::PARAM_INT);
            $stmt->bindParam(':duracion_semanas', $datos['duracion_semanas'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al actualizar asignatura: " . $e->getMessage());
            return false;
        }
    }

    // Eliminar asignatura
    public function eliminar($id) {
        try {
            $query = "DELETE FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Error al eliminar asignatura: " . $e->getMessage());
            return false;
        }
    }
}
?>