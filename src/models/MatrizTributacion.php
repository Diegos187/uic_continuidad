<?php

class MatrizTributacion
{
    private $conn;
    private $tabla = 'matriz_tributacion';

    public function __construct($conexion)
    {
        $this->conn = $conexion;
    }

    public function crear($matriz_coherencia_id, $criterio_logro_id, $marcado = true)
    {
        $sql = "INSERT INTO {$this->tabla} (matriz_coherencia_id, criterio_logro_id, marcado) 
                VALUES (:matriz_id, :criterio_id, :marcado)
                ON DUPLICATE KEY UPDATE marcado = :marcado";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':matriz_id', $matriz_coherencia_id);
        $stmt->bindParam(':criterio_id', $criterio_logro_id);
        $stmt->bindParam(':marcado', (int)$marcado);
        return $stmt->execute();
    }

    public function obtenerPorMatriz($matriz_coherencia_id)
    {
        $sql = "SELECT mt.*, cl.codigo as criterio_codigo, ra.codigo as resultado_codigo
                FROM {$this->tabla} mt
                INNER JOIN criterios_logro_ref cl ON mt.criterio_logro_id = cl.id
                INNER JOIN resultados_aprendizaje_ref ra ON cl.resultado_aprendizaje_id = ra.id
                WHERE mt.matriz_coherencia_id = :id
                ORDER BY cl.orden ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $matriz_coherencia_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerMarcadosPorMatriz($matriz_coherencia_id)
    {
        $sql = "SELECT criterio_logro_id FROM {$this->tabla} 
                WHERE matriz_coherencia_id = :id AND marcado = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $matriz_coherencia_id);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($resultados, 'criterio_logro_id');
    }

    public function obtenerPorId($id)
    {
        $sql = "SELECT * FROM {$this->tabla} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function actualizar($matriz_coherencia_id, $criterio_logro_id, $marcado)
    {
        $sql = "INSERT INTO {$this->tabla} (matriz_coherencia_id, criterio_logro_id, marcado) 
                VALUES (:matriz_id, :criterio_id, :marcado)
                ON DUPLICATE KEY UPDATE marcado = :marcado";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':matriz_id', $matriz_coherencia_id);
        $stmt->bindParam(':criterio_id', $criterio_logro_id);
        $stmt->bindParam(':marcado', (int)$marcado);
        return $stmt->execute();
    }

    public function eliminarPorMatriz($matriz_coherencia_id)
    {
        $sql = "DELETE FROM {$this->tabla} WHERE matriz_coherencia_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $matriz_coherencia_id);
        return $stmt->execute();
    }

    public function eliminar($id)
    {
        $sql = "DELETE FROM {$this->tabla} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function generarPorMatriz($matriz_coherencia_id)
    {
        // Obtiene todos los criterios de logro para esta matriz
        // y genera las filas automáticamente si no existen
        $sql = "SELECT DISTINCT cl.id as criterio_id
                FROM matrices_coherencia mc
                INNER JOIN resultados_aprendizaje_ref ra ON mc.id = :matriz_id
                INNER JOIN criterios_logro_ref cl ON ra.id = cl.resultado_aprendizaje_id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tabla} mt 
                    WHERE mt.matriz_coherencia_id = :matriz_id 
                    AND mt.criterio_logro_id = cl.id
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':matriz_id', $matriz_coherencia_id);
        $stmt->execute();
        $criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $contador = 0;
        foreach ($criterios as $criterio) {
            if ($this->crear($matriz_coherencia_id, $criterio['criterio_id'], true)) {
                $contador++;
            }
        }
        return $contador;
    }

    public function obtenerMatrizCompleta($matriz_coherencia_id)
    {
        // Obtiene la matriz tributación con toda la estructura jerárquica
        $sql = "SELECT 
                    cd.id as competencia_id,
                    cd.codigo as competencia_codigo,
                    cd.descripcion as competencia_descripcion,
                    ra.id as resultado_id,
                    ra.codigo as resultado_codigo,
                    ra.descripcion as resultado_descripcion,
                    cl.id as criterio_id,
                    cl.codigo as criterio_codigo,
                    cl.descripcion as criterio_descripcion,
                    COALESCE(mt.marcado, 0) as marcado,
                    mt.id as tributacion_id
                FROM matrices_coherencia mc
                INNER JOIN competencias_dominio cd ON cd.perfil_egreso_detalle_id = (
                    SELECT perfil_egreso_detalle_id FROM matrices_coherencia WHERE id = :matriz_id LIMIT 1
                )
                INNER JOIN resultados_aprendizaje_ref ra ON ra.competencia_dominio_id = cd.id
                INNER JOIN criterios_logro_ref cl ON cl.resultado_aprendizaje_id = ra.id
                LEFT JOIN {$this->tabla} mt ON mt.matriz_coherencia_id = :matriz_id 
                    AND mt.criterio_logro_id = cl.id
                WHERE mc.id = :matriz_id
                ORDER BY cd.orden ASC, ra.orden ASC, cl.orden ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':matriz_id', $matriz_coherencia_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
