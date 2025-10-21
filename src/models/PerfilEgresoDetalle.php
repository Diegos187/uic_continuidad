<?php
class PerfilEgresoDetalle
{
    private $conexion;
    private $tabla = 'perfiles_egreso_detalle';

    public function __construct($db)
    {
        $this->conexion = $db;
        $this->ensureTableExists();
    }

    private function ensureTableExists()
    {
        // Crea tabla si no existe (id, perfil_egreso_id FK, area_formacion_id FK nullable, dominio text, competencia text)
        $sql = "CREATE TABLE IF NOT EXISTS `" . $this->tabla . "` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `perfil_egreso_id` INT(11) NOT NULL,
            `area_formacion_id` INT(11) DEFAULT NULL,
            `dominio` TEXT NOT NULL,
            `competencia` TEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `perfil_egreso_id` (`perfil_egreso_id`),
            KEY `area_formacion_id` (`area_formacion_id`),
            CONSTRAINT `fk_pe_det_perfil` FOREIGN KEY (`perfil_egreso_id`) REFERENCES `perfiles_egreso` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pe_det_area` FOREIGN KEY (`area_formacion_id`) REFERENCES `areas_formacion` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $this->conexion->exec($sql);
    }

    public function crearMultiple($perfilId, $filas)
    {
        if (empty($filas)) return [];
        $stmt = $this->conexion->prepare("INSERT INTO " . $this->tabla . " (perfil_egreso_id, area_formacion_id, dominio, competencia) VALUES (:perfil_egreso_id, :area_formacion_id, :dominio, :competencia)");
        $ids = [];
        foreach ($filas as $f) {
            $areaId = isset($f['area_formacion_id']) && $f['area_formacion_id'] !== '' ? (int)$f['area_formacion_id'] : null;
            $stmt->bindValue(':perfil_egreso_id', (int)$perfilId, PDO::PARAM_INT);
            $stmt->bindValue(':area_formacion_id', $areaId, $areaId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':dominio', $f['dominio'] ?? '');
            $stmt->bindValue(':competencia', $f['competencia'] ?? '');
            if (!$stmt->execute()) {
                throw new Exception('No se pudo insertar detalle de perfil');
            }
            $ids[] = (int)$this->conexion->lastInsertId();
        }
        return $ids;
    }

    public function listarPorPerfil($perfilId)
    {
        $stmt = $this->conexion->prepare("SELECT id, area_formacion_id, dominio, competencia FROM " . $this->tabla . " WHERE perfil_egreso_id = :pid ORDER BY id ASC");
        $stmt->bindValue(':pid', (int)$perfilId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorPerfil($perfilId)
    {
        $stmt = $this->conexion->prepare("DELETE FROM " . $this->tabla . " WHERE perfil_egreso_id = :pid");
        $stmt->bindValue(':pid', (int)$perfilId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
