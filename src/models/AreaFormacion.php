<?php
class AreaFormacion
{
    private $conexion;
    private $tabla = 'areas_formacion';

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function obtenerTodas()
    {
        $sql = "SELECT id, nombre, descripcion, created_at FROM {$this->tabla} ORDER BY nombre";
        $stmt = $this->conexion->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($nombre, $descripcion = null)
    {
        $stmt = $this->conexion->prepare("INSERT INTO {$this->tabla} (nombre, descripcion) VALUES (:nombre, :descripcion)");
        $stmt->bindValue(':nombre', trim($nombre));
        $stmt->bindValue(':descripcion', $descripcion !== null ? trim($descripcion) : null, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo crear el área de formación');
        }
        return (int)$this->conexion->lastInsertId();
    }

    public function obtenerPorId($id)
    {
        $stmt = $this->conexion->prepare("SELECT id, nombre, descripcion, created_at FROM {$this->tabla} WHERE id = :id");
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function actualizar($id, $nombre, $descripcion = null)
    {
        $stmt = $this->conexion->prepare("UPDATE {$this->tabla} SET nombre = :nombre, descripcion = :descripcion WHERE id = :id");
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        $stmt->bindValue(':nombre', trim($nombre));
        $stmt->bindValue(':descripcion', $descripcion !== null ? trim($descripcion) : null, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        return $stmt->execute();
    }

    public function eliminar($id)
    {
        $stmt = $this->conexion->prepare("DELETE FROM {$this->tabla} WHERE id = :id");
        $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Asociación con carreras
    public function asociarACarrera($carreraId, $areaId)
    {
        // Evitar duplicados
        $check = $this->conexion->prepare("SELECT id FROM carrera_area_formacion WHERE carrera_id = :carrera AND area_formacion_id = :area LIMIT 1");
        $check->bindValue(':carrera', (int)$carreraId, PDO::PARAM_INT);
        $check->bindValue(':area', (int)$areaId, PDO::PARAM_INT);
        $check->execute();
        if ($check->fetch(PDO::FETCH_ASSOC)) {
            return true;
        }
        $stmt = $this->conexion->prepare("INSERT INTO carrera_area_formacion (carrera_id, area_formacion_id) VALUES (:carrera, :area)");
        $stmt->bindValue(':carrera', (int)$carreraId, PDO::PARAM_INT);
        $stmt->bindValue(':area', (int)$areaId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function desasociarDeCarrera($carreraId, $areaId)
    {
        $stmt = $this->conexion->prepare("DELETE FROM carrera_area_formacion WHERE carrera_id = :carrera AND area_formacion_id = :area");
        $stmt->bindValue(':carrera', (int)$carreraId, PDO::PARAM_INT);
        $stmt->bindValue(':area', (int)$areaId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function listarPorCarrera($carreraId)
    {
        $sql = "SELECT af.id, af.nombre, af.descripcion
				FROM carrera_area_formacion caf
				INNER JOIN {$this->tabla} af ON af.id = caf.area_formacion_id
				WHERE caf.carrera_id = :carrera
				ORDER BY af.nombre";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bindValue(':carrera', (int)$carreraId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
