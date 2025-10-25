<?php
class MatrizCoherencia
{
    private $conexion;
    private $tabla = 'matrices_coherencia';

    public function __construct($db)
    {
        $this->conexion = $db;
    }

    public function crear($datos)
    {
        try {
            $query = "INSERT INTO " . $this->tabla . " 
        (asignatura_id, area_formacion_id, perfil_egreso_id, version_id,
        dominio, competencia, resultado_aprendizaje,
        criterios_logro, contenidos, bibliografia,
        metodologias, estrategias, sct_chile) 
                    VALUES 
        (:asignatura_id, :area_formacion_id, :perfil_egreso_id, :version_id,
        :dominio, :competencia, :resultado_aprendizaje,
        :criterios_logro, :contenidos, :bibliografia,
        :metodologias, :estrategias, :sct_chile)";

            $stmt = $this->conexion->prepare($query);

            $stmt->bindParam(':asignatura_id', $datos['asignatura_id'], PDO::PARAM_INT);
            $stmt->bindValue(':area_formacion_id', $datos['area_formacion_id'] ?? null, $datos['area_formacion_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':perfil_egreso_id', $datos['perfil_egreso_id'] ?? null, $datos['perfil_egreso_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':version_id', $datos['version_id'] ?? null, $datos['version_id'] !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindParam(':dominio', $datos['dominio']);
            $stmt->bindParam(':competencia', $datos['competencia']);
            $stmt->bindParam(':resultado_aprendizaje', $datos['resultado_aprendizaje']);
            $stmt->bindParam(':criterios_logro', $datos['criterios_logro']);
            $stmt->bindParam(':contenidos', $datos['contenidos']);
            $stmt->bindParam(':bibliografia', $datos['bibliografia']);
            $stmt->bindParam(':metodologias', $datos['metodologias']);
            $stmt->bindParam(':estrategias', $datos['estrategias']);
            $stmt->bindParam(':sct_chile', $datos['sct_chile']);

            if ($stmt->execute()) {
                return $this->conexion->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error al crear matriz de coherencia: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inserta múltiples filas de matriz de coherencia en una sola transacción.
     * @param int $asignatura_id ID de la carrera/asignatura a asociar
     * @param array $filas Array de arrays con llaves:
     *  area_formacion_id, perfil_egreso_id, version_id, dominio, competencia,
     *  resultado_aprendizaje, criterios_logro,
     *  contenidos, bibliografia, metodologias, estrategias, sct_chile
     * @return array|false Lista de IDs insertados o false en caso de error
     */
    public function crearMultiple($asignatura_id, $filas)
    {
        try {
            $this->conexion->beginTransaction();

            $query = "INSERT INTO " . $this->tabla . " 
                    (asignatura_id, area_formacion_id, perfil_egreso_id, version_id,
                    dominio, competencia, resultado_aprendizaje,
                    criterios_logro, contenidos, bibliografia,
                    metodologias, estrategias, sct_chile) 
                    VALUES 
                    (:asignatura_id, :area_formacion_id, :perfil_egreso_id, :version_id,
                    :dominio, :competencia, :resultado_aprendizaje,
                    :criterios_logro, :contenidos, :bibliografia,
                    :metodologias, :estrategias, :sct_chile)";

            $stmt = $this->conexion->prepare($query);
            $ids = [];

            foreach ($filas as $fila) {
                // Valores por defecto y saneo mínimo
                $area_formacion_id = isset($fila['area_formacion_id']) && $fila['area_formacion_id'] !== '' ? (int)$fila['area_formacion_id'] : null;
                $perfil_egreso_id = isset($fila['perfil_egreso_id']) && $fila['perfil_egreso_id'] !== '' ? (int)$fila['perfil_egreso_id'] : null;
                $version_id = isset($fila['version_id']) && $fila['version_id'] !== '' ? (int)$fila['version_id'] : null;
                $dominio = $fila['dominio'] ?? null;
                $competencia = $fila['competencia'] ?? null;
                $resultado_aprendizaje = $fila['resultado_aprendizaje'] ?? null;
                $criterios_logro = $fila['criterios_logro'] ?? null;
                $contenidos = $fila['contenidos'] ?? null;
                $bibliografia = $fila['bibliografia'] ?? null;
                $metodologias = $fila['metodologias'] ?? null;
                $estrategias = $fila['estrategias'] ?? null;
                $sct_chile = isset($fila['sct_chile']) && $fila['sct_chile'] !== '' ? (int)$fila['sct_chile'] : 0;

                $stmt->bindValue(':asignatura_id', (int)$asignatura_id, PDO::PARAM_INT);
                if ($area_formacion_id === null) {
                    $stmt->bindValue(':area_formacion_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':area_formacion_id', $area_formacion_id, PDO::PARAM_INT);
                }
                if ($perfil_egreso_id === null) {
                    $stmt->bindValue(':perfil_egreso_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':perfil_egreso_id', $perfil_egreso_id, PDO::PARAM_INT);
                }
                if ($version_id === null) {
                    $stmt->bindValue(':version_id', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':version_id', $version_id, PDO::PARAM_INT);
                }
                $stmt->bindValue(':dominio', $dominio);
                $stmt->bindValue(':competencia', $competencia);
                $stmt->bindValue(':resultado_aprendizaje', $resultado_aprendizaje);
                $stmt->bindValue(':criterios_logro', $criterios_logro);
                $stmt->bindValue(':contenidos', $contenidos);
                $stmt->bindValue(':bibliografia', $bibliografia);
                $stmt->bindValue(':metodologias', $metodologias);
                $stmt->bindValue(':estrategias', $estrategias);
                $stmt->bindValue(':sct_chile', $sct_chile, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new PDOException('Fallo al insertar una fila de matriz');
                }
                $ids[] = $this->conexion->lastInsertId();
            }

            $this->conexion->commit();
            return $ids;
        } catch (PDOException $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            error_log('Error en crearMultiple: ' . $e->getMessage());
            return false;
        }
    }

    public function obtenerPorAsignatura($asignatura_id)
    {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE asignatura_id = :asignatura_id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':asignatura_id', $asignatura_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener matriz por asignatura: " . $e->getMessage());
            return [];
        }
    }

    public function obtenerPorCarrera($carrera_id)
    {
        try {
            $query = "SELECT mc.*, a.nombre AS asignatura_nombre, a.carrera_id,
                             af.nombre AS area_formacion_nombre
                      FROM " . $this->tabla . " mc
                      JOIN asignaturas a ON a.id = mc.asignatura_id
                      LEFT JOIN areas_formacion af ON af.id = mc.area_formacion_id
                      WHERE a.carrera_id = :carrera_id
                      ORDER BY mc.id DESC";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener matrices por carrera: " . $e->getMessage());
            return [];
        }
    }

    public function actualizar($id, $datos)
    {
        try {
            $query = "UPDATE " . $this->tabla . " SET 
                    dominio = :dominio,
                    competencia = :competencia,
            resultado_aprendizaje = :resultado_aprendizaje,
                    criterios_logro = :criterios_logro,
                    contenidos = :contenidos,
                    bibliografia = :bibliografia,
                    metodologias = :metodologias,
            estrategias = :estrategias,
                    sct_chile = :sct_chile
                    WHERE id = :id";

            $stmt = $this->conexion->prepare($query);

            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':dominio', $datos['dominio']);
            $stmt->bindParam(':competencia', $datos['competencia']);
            $stmt->bindParam(':resultado_aprendizaje', $datos['resultado_aprendizaje']);
            $stmt->bindParam(':criterios_logro', $datos['criterios_logro']);
            $stmt->bindParam(':contenidos', $datos['contenidos']);
            $stmt->bindParam(':bibliografia', $datos['bibliografia']);
            $stmt->bindParam(':metodologias', $datos['metodologias']);
            $stmt->bindParam(':estrategias', $datos['estrategias']);
            $stmt->bindParam(':sct_chile', $datos['sct_chile']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al actualizar matriz: " . $e->getMessage());
            return false;
        }
    }

    public function eliminar($id)
    {
        try {
            $query = "DELETE FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error al eliminar matriz: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerPorId($id)
    {
        try {
            $query = "SELECT * FROM " . $this->tabla . " WHERE id = :id";
            $stmt = $this->conexion->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener matriz por ID: " . $e->getMessage());
            return null;
        }
    }
}
