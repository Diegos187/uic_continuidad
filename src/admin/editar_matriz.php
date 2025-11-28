<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/Matriz.php';
require_once '../../src/models/CompetenciaDominio.php';
require_once '../../src/models/ResultadoAprendizajeRef.php';
require_once '../../src/models/CriterioLogroRef.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);
$matriz = new MatrizCoherencia($conexion);
$versiones = new VersionMatriz($conexion);
$matrizGeneral = new Matriz($conexion);
$competenciaModel = new CompetenciaDominio($conexion);
$resultadoModel = new ResultadoAprendizajeRef($conexion);
$criterioModel = new CriterioLogroRef($conexion);

$error = '';
$success = '';

// Obtener ID de la matriz a editar
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: matrices.php');
    exit;
}

$matriz_id = (int)$_GET['id'];
$matrizInfo = $matrizGeneral->obtenerPorId($matriz_id);

if (!$matrizInfo) {
    header('Location: matrices.php');
    exit;
}

// Obtener la versión actual (la que vamos a editar)
$version_id = (int)($matrizInfo['version_id'] ?? 0);
$versionInfo = $conexion->prepare("SELECT * FROM versiones_matriz WHERE id = :id");
$versionInfo->bindParam(':id', $version_id, PDO::PARAM_INT);
$versionInfo->execute();
$versionActual = $versionInfo->fetch(PDO::FETCH_ASSOC);

if (!$versionActual) {
    $error = 'No se encontró la versión actual de la matriz.';
}

// Obtener filas actuales de la matriz y versión
$filasActuales = $matriz->obtenerPorMatrizYVersion($matriz_id, $version_id);

// Obtener el perfil de egreso de las filas existentes (si existen)
$perfilEgresoActual = null;
if (!empty($filasActuales)) {
    $perfilEgresoActual = $filasActuales[0]['perfil_egreso_id'] ?? null;
}

// Obtener listas base
$carreras = $carrera->obtenerTodas();
$asignaturasTodas = $asignatura->obtenerTodas();

// Construir selecciones por fila desde matriz_tributacion (criterios marcados)
// Mapear selecciones por fila usando el ID real de matrices_coherencia
$seleccionesPorFila = [];
if (!empty($filasActuales)) {
    foreach ($filasActuales as $fila) {
        $mcid = (int)($fila['id'] ?? 0);
        if ($mcid > 0) {
            $stmtSel = $conexion->prepare("SELECT mt.criterio_logro_id AS crit_id,
                                     clr.resultado_aprendizaje_id AS res_id,
                                     rar.competencia_dominio_id AS comp_id,
                                     cd.perfil_egreso_detalle_id AS detalle_id
                                 FROM matriz_tributacion mt
                                 JOIN criterios_logro_ref clr ON clr.id = mt.criterio_logro_id
                                 JOIN resultados_aprendizaje_ref rar ON rar.id = clr.resultado_aprendizaje_id
                                 JOIN competencias_dominio cd ON cd.id = rar.competencia_dominio_id
                                 WHERE mt.matriz_coherencia_id = :mcid");
            $stmtSel->bindValue(':mcid', $mcid, PDO::PARAM_INT);
            $stmtSel->execute();
            $rows = $stmtSel->fetchAll(PDO::FETCH_ASSOC);
            $criterios = [];
            $resultados = [];
            $competencias = [];
            $detalles = [];
            foreach ($rows as $r) {
                $criterios[] = (int)$r['crit_id'];
                $resultados[] = (int)$r['res_id'];
                $competencias[] = (int)$r['comp_id'];
                if (!empty($r['detalle_id'])) {
                    $detalles[] = (int)$r['detalle_id'];
                }
            }
            $seleccionesPorFila[$mcid] = [
                'perfil_detalle_id' => (int)($fila['perfil_egreso_detalle_id'] ?? 0),
                'detalles' => isset($detalles) ? array_values(array_unique($detalles)) : [],
                'criterios' => array_values(array_unique($criterios)),
                'resultados' => array_values(array_unique($resultados)),
                'competencias' => array_values(array_unique($competencias))
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $carrera_id = isset($_POST['carrera_id']) ? (int)limpiarDatos($_POST['carrera_id']) : null;
    $nombre_matriz = isset($_POST['nombre_matriz']) ? trim(limpiarDatos($_POST['nombre_matriz'])) : '';
    $descripcion_version = isset($_POST['descripcion_version']) ? trim(limpiarDatos($_POST['descripcion_version'])) : '';
    $filasPost = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if (!$carrera_id) {
        $error = 'Debe seleccionar una carrera.';
    } elseif ($nombre_matriz === '') {
        $error = 'Debe ingresar un nombre para la matriz.';
    } elseif ($descripcion_version === '') {
        $error = 'Debe ingresar un nombre o descripción para la versión.';
    } elseif (empty($filasPost)) {
        $error = 'Debe agregar al menos una fila a la matriz.';
    } else {
        try {
            // PASO 1: Actualizar nombre y descripción de la matriz
            $sql = "UPDATE matrices SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':nombre', $nombre_matriz);
            $stmt->bindParam(':descripcion', $descripcion_version);
            $stmt->bindParam(':id', $matriz_id, PDO::PARAM_INT);
            $stmt->execute();

            // PASO 2: Actualizar descripción de la versión
            $sql = "UPDATE versiones_matriz SET descripcion = :descripcion WHERE id = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':descripcion', $descripcion_version);
            $stmt->bindParam(':id', $version_id, PDO::PARAM_INT);
            $stmt->execute();

            // PASO 3: Eliminar filas existentes de esta versión
            // Primero obtener los IDs actuales para limpiar matriz_tributacion
            $stmt = $conexion->prepare("SELECT id FROM matrices_coherencia WHERE matriz_id = :matriz_id AND version_id = :version_id");
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->bindParam(':version_id', $version_id, PDO::PARAM_INT);
            $stmt->execute();
            $idsActuales = array_map(function($r){ return (int)$r['id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            if (!empty($idsActuales)) {
                $inPlaceholders = implode(',', array_fill(0, count($idsActuales), '?'));
                $sqlDelTrib = "DELETE FROM matriz_tributacion WHERE matriz_coherencia_id IN ($inPlaceholders)";
                $stmtDelTrib = $conexion->prepare($sqlDelTrib);
                foreach ($idsActuales as $i => $val) { $stmtDelTrib->bindValue($i+1, $val, PDO::PARAM_INT); }
                $stmtDelTrib->execute();
            }
            // Luego eliminar las filas de coherencia
            $sqlDelMc = "DELETE FROM matrices_coherencia WHERE matriz_id = :matriz_id AND version_id = :version_id";
            $stmtDelMc = $conexion->prepare($sqlDelMc);
            $stmtDelMc->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmtDelMc->bindParam(':version_id', $version_id, PDO::PARAM_INT);
            $stmtDelMc->execute();

            // PASO 4: Insertar nuevas filas
            $filas = [];
            foreach ($filasPost as $fila) {
                // Obtener y limpiar valores
                $valores = array_map(function ($v) {
                    return is_string($v) ? trim($v) : $v;
                }, $fila);

                // Validar que al menos haya contenido
                $todosVacios = true;
                foreach (['dominio', 'competencia', 'resultado_aprendizaje', 'actividad_curricular_id', 'criterios_logro', 'contenidos', 'bibliografia', 'metodologias', 'estrategias', 'sct_chile'] as $k) {
                    if (!empty($valores[$k])) {
                        $todosVacios = false;
                        break;
                    }
                }
                if ($todosVacios) {
                    continue; // Saltar filas vacías
                }

                // Validar campos obligatorios cuando la fila tiene contenido
                $actividad_id = isset($fila['actividad_curricular_id']) ? (int)limpiarDatos($fila['actividad_curricular_id']) : 0;
                // Recoger selecciones de la UI
                $competenciasSeleccionadas = isset($fila['competencias_ids']) ? (is_array($fila['competencias_ids']) ? array_map('intval', $fila['competencias_ids']) : [ (int)$fila['competencias_ids'] ]) : [];
                $resultadosSeleccionados = isset($fila['resultados_ids']) ? (is_array($fila['resultados_ids']) ? array_map('intval', $fila['resultados_ids']) : [ (int)$fila['resultados_ids'] ]) : [];
                $criteriosSeleccionados = isset($fila['criterios_ids']) ? (is_array($fila['criterios_ids']) ? array_map('intval', $fila['criterios_ids']) : [ (int)$fila['criterios_ids'] ]) : [];

                // Si la fila tiene contenido, validar que los requeridos estén presentes
                if (!$todosVacios) {
                    if (!$actividad_id) {
                        $error = 'Debe seleccionar una Actividad Curricular en todas las filas.';
                        break;
                    }
                    if (empty($criteriosSeleccionados)) {
                        $error = 'Debe seleccionar al menos un Criterio de Logro por fila.';
                        break;
                    }
                }

                // Tomar perfil de egreso del nivel superior
                $perfil_superior = isset($_POST['perfil_id']) ? (int)limpiarDatos($_POST['perfil_id']) : null;
                $dominio = isset($fila['dominio']) ? trim(limpiarDatos($fila['dominio'])) : '';
                // Selección de dominio (perfil_egreso_detalle) desde checkboxes: tomar el primero marcado
                $perfil_detalle_id = null;
                if (isset($fila['perfil_egreso_detalle_id']) && $fila['perfil_egreso_detalle_id'] !== '') {
                    $perfil_detalle_id = (int)limpiarDatos($fila['perfil_egreso_detalle_id']);
                }

                // Construir textos agregados a partir de selecciones
                $competenciasTexto = [];
                foreach ($competenciasSeleccionadas as $cid) {
                    $cdata = $competenciaModel->obtenerPorId($cid);
                    if ($cdata) {
                        $competenciasTexto[] = trim(($cdata['codigo'] ?? '') . ' - ' . ($cdata['descripcion'] ?? ''));
                    }
                }
                $resultadosTexto = [];
                foreach ($resultadosSeleccionados as $rid) {
                    $rdata = $resultadoModel->obtenerPorId($rid);
                    if ($rdata) {
                        $resultadosTexto[] = trim(($rdata['codigo'] ?? '') . ' - ' . ($rdata['descripcion'] ?? ''));
                    }
                }
                $criteriosTexto = [];
                foreach ($criteriosSeleccionados as $crid) {
                    $crdata = $criterioModel->obtenerPorId($crid);
                    if ($crdata) {
                        $criteriosTexto[] = trim(($crdata['codigo'] ?? '') . ' - ' . ($crdata['descripcion'] ?? ''));
                    }
                }
                $competenciaAgregada = implode("\n", $competenciasTexto);
                $resultadosAgregados = implode("\n", $resultadosTexto);
                $criteriosAgregados = implode("\n", $criteriosTexto);

                $filas[] = [
                    'matriz_id' => $matriz_id,
                    'asignatura_id' => $actividad_id,
                    'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                    'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                    'perfil_egreso_detalle_id' => $perfil_detalle_id,
                    'version_id' => $version_id,
                    'dominio' => $dominio,
                    'competencia' => $competenciaAgregada,
                    'resultado_aprendizaje' => $resultadosAgregados,
                    'criterios_logro' => $criteriosAgregados,
                    'competencias_ids' => $competenciasSeleccionadas,
                    'resultados_ids' => $resultadosSeleccionados,
                    'criterios_ids' => $criteriosSeleccionados,
                    'contenidos' => isset($fila['contenidos']) ? limpiarDatos($fila['contenidos']) : null,
                    'bibliografia' => isset($fila['bibliografia']) ? limpiarDatos($fila['bibliografia']) : null,
                    'metodologias' => isset($fila['metodologias']) ? limpiarDatos($fila['metodologias']) : null,
                    'estrategias' => isset($fila['estrategias']) ? limpiarDatos($fila['estrategias']) : null,
                    'sct_chile' => isset($fila['sct_chile']) ? (int)limpiarDatos($fila['sct_chile']) : 0,
                ];
            }

            if (empty($filas) && empty($error)) {
                $error = 'No hay filas válidas para guardar.';
            } elseif (!empty($filas) && empty($error)) {
                // Insertar por fila
                $ids = [];
                foreach ($filas as $f) {
                    $id = $matriz->crear($f);
                    if ($id === false) {
                        $error = 'Error al crear una fila de la matriz. Por favor, verifica que todos los datos sean correctos.';
                        break;
                    }
                    $ids[] = $id;
                }
            }
        } catch (Exception $e) {
            error_log('Error al editar matriz: ' . $e->getMessage());
            $error = 'Error al guardar los cambios: ' . $e->getMessage();
        }
    }

    // Responder con JSON para AJAX
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        if (!empty($error)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $error
            ]);
        } else {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Matriz editada correctamente',
                'carrera_id' => $matrizInfo['carrera_id']
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Editar Matriz de Coherencia - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Editar Matriz: <?php echo htmlspecialchars($matrizInfo['nombre'] ?: ('Matriz #' . (int)$matrizInfo['id'])); ?></h2>
                            <a href="matrices.php?carrera_id=<?php echo (int)$matrizInfo['carrera_id']; ?>" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="carrera_id" class="form-label">Carrera</label>
                                    <select class="form-select" id="carrera_id" name="carrera_id" required onchange="cargarAsignaturasYAnexos()">
                                        <option value="">Seleccione una carrera</option>
                                        <?php foreach ($carreras as $carr): ?>
                                            <option value="<?php echo $carr['id']; ?>" <?php echo $carr['id'] == $matrizInfo['carrera_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($carr['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="perfil_id" class="form-label">Perfil de egreso</label>
                                    <select class="form-select" id="perfil_id" name="perfil_id" required disabled onchange="onPerfilChange()">
                                        <option value="">Seleccione un perfil</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="nombre_matriz" class="form-label">Nombre de la Matriz</label>
                                <input type="text" class="form-control mb-3" id="nombre_matriz" name="nombre_matriz" placeholder="Ej: Matriz de Coherencia Curricular 2026" value="<?php echo htmlspecialchars($matrizInfo['nombre'] ?? ''); ?>" required />
                                <label for="descripcion_version" class="form-label">Nombre/Descripción de la Versión</label>
                                <input type="text" class="form-control mb-3" id="descripcion_version" name="descripcion_version" placeholder="Ej: v1.0 - Plan Diurno / Versión inicial aprobada" value="<?php echo htmlspecialchars($versionActual['descripcion'] ?? ''); ?>" required />
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">Filas de la Matriz</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar otra fila</button>
                                </div>
                            </div>

                            <div class="accordion" id="filas-container">
                                <!-- Las filas dinámicas se insertan aquí por JS -->
                            </div>

                            <!-- Botón adicional al final para agregar más filas -->
                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFila()">Agregar otra fila</button>
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <!-- Toast container -->
    <div id="toast-container" class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../public/js/validador_estructura.js"></script>
    <script>
        let filaCounter = 0;
        let atributosCache = {
            perfiles: [],
            versiones: [],
            resultados: [],
            areasPorPerfil: [],
            asignaturas: []
        };

        // Datos de filas existentes para precargar
        const filasExistentes = <?php echo json_encode($filasActuales); ?>;
        const seleccionesPorFila = <?php echo json_encode($seleccionesPorFila); ?>;
        const perfilEgresoActual = <?php echo json_encode($perfilEgresoActual); ?>;

        function optionMarkupId(lista, placeholder) {
            const opts = [`<option value="">${placeholder}</option>`];
            lista.forEach(item => {
                const id = String(item.id ?? '');
                const label = (item.descripcion ?? '').toString();
                opts.push(`<option value="${id.replace(/"/g, '&quot;')}">${label.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</option>`);
            });
            return opts.join('');
        }

        function optionAsignaturas(lista, placeholder) {
            const opts = [`<option value="">${placeholder}</option>`];
            lista.forEach(item => {
                const id = String(item.id ?? '');
                const label = (item.nombre ?? '').toString();
                opts.push(`<option value="${id.replace(/"/g, '&quot;')}">${label.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</option>`);
            });
            return opts.join('');
        }

        function plantillaFila(index, datosExistentes = null, expanded = false) {
            const collapseId = `filaBody_${index}`;
            const headerId = `filaHeader_${index}`;

            // Valores por defecto o existentes
            const dominio = datosExistentes?.dominio || '';
            const competencia = datosExistentes?.competencia || '';
            const resultadoAprendizaje = datosExistentes?.resultado_aprendizaje || '';
            const criteriosLogro = datosExistentes?.criterios_logro || '';
            const contenidos = datosExistentes?.contenidos || '';
            const bibliografia = datosExistentes?.bibliografia || '';
            const metodologias = datosExistentes?.metodologias || '';
            const estrategias = datosExistentes?.estrategias || '';
            const sctChile = datosExistentes?.sct_chile || 0;
            const asignaturaId = datosExistentes?.asignatura_id || '';
            const areaId = datosExistentes?.area_formacion_id || '';
            const showClass = expanded ? 'show' : '';
            const collapsedClass = expanded ? '' : 'collapsed';

            const mcid = datosExistentes?.id || '';
            return `
            <div class="accordion-item" data-index="${index}" data-mcid="${mcid}">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button ${collapsedClass}" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="${expanded}" aria-controls="${collapseId}">
                        Fila ${index + 1} <span class="ms-2 text-muted resumen-fila"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse ${showClass}" aria-labelledby="${headerId}" data-bs-parent="#filas-container">
                    <div class="accordion-body pt-3">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Área de formación</label>
                                <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" onchange="onAreaChange(this)" disabled required>
                                    ${optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área')}
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Dominios <span class="text-muted">(puede seleccionar varios)</span></label>
                                <div class="dominios-checkboxes" id="dominios-${index}" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 6px; background: #f7f9fc;">
                                    <p class="text-muted mb-0 small">Seleccione un área primero</p>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Competencias, Resultados y Criterios por Dominio</label>
                                <ul class="nav nav-tabs" id="domTabs-${index}" role="tablist" style="margin-bottom:8px;"></ul>
                                <div class="tab-content" id="domTabsContent-${index}">
                                    <div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actividad Curricular</label>
                            <select class="form-select campo-actividad" name="filas[${index}][actividad_curricular_id]" required>
                                <option value="${asignaturaId}" ${asignaturaId ? 'selected' : ''}>Seleccione una actividad curricular</option>
                            </select>
                        </div>

                        

                        <div class="mb-3">
                            <label class="form-label">Contenidos/Saberes</label>
                            <textarea class="form-control" name="filas[${index}][contenidos]" rows="2">${escapeHtml(contenidos)}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bibliografía</label>
                            <textarea class="form-control" name="filas[${index}][bibliografia]" rows="2">${escapeHtml(bibliografia)}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Metodologías Activas</label>
                            <textarea class="form-control" name="filas[${index}][metodologias]" rows="2">${escapeHtml(metodologias)}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estrategias</label>
                            <textarea class="form-control" name="filas[${index}][estrategias]" rows="2">${escapeHtml(estrategias)}</textarea>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">SCT-Chile</label>
                                <input type="number" class="form-control" name="filas[${index}][sct_chile]" min="0" value="${sctChile}" />
                            </div>
                            <div class="col-md-8 mb-3 text-end">
                                <button type="button" class="btn btn-outline-secondary me-2" onclick="colapsarFila(${index})">Colapsar</button>
                                <button type="button" class="btn btn-outline-danger" onclick="eliminarFila(${index})">Eliminar fila</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function refrescarIndiceFilas() {
            const items = document.querySelectorAll('#filas-container .accordion-item');
            items.forEach((item, i) => {
                item.querySelector('.accordion-button').firstChild.textContent = `Fila ${i + 1} `;
            });
        }

        function agregarFila(doScroll = true) {
            const cont = document.getElementById('filas-container');
            const html = plantillaFila(filaCounter, null, true);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            const node = tmp.firstChild;
            cont.appendChild(node);
            habilitarSelectsFila(node);
            actualizarResumenFila(node);

            // Colapsar otras y expandir nueva
            cont.querySelectorAll('.accordion-collapse').forEach(c => c.classList.remove('show'));
            node.querySelector('.accordion-collapse').classList.add('show');

            // Scroll suave al header de la nueva fila
            if (doScroll) {
                const headerBtn = node.querySelector('.accordion-button');
                if (headerBtn) {
                    headerBtn.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            filaCounter++;
        }

        function eliminarFila(index) {
            const sel = `#filas-container .accordion-item[data-index="${index}"]`;
            const item = document.querySelector(sel);
            if (item) {
                item.remove();
                refrescarIndiceFilas();
            }
        }

        function colapsarFila(index) {
            const sel = `#filas-container .accordion-item[data-index="${index}"]`;
            const item = document.querySelector(sel);
            if (!item) return;
            const body = item.querySelector('.accordion-collapse');
            actualizarResumenFila(item);
            body.classList.remove('show');
        }

        function actualizarResumenFila(item) {
            const resumen = item.querySelector('.resumen-fila');
            if (!resumen) return;

            // Área de formación (texto de opción seleccionada)
            const areaSel = item.querySelector('.campo-area');
            let areaTxt = '';
            if (areaSel && areaSel.value) {
                const opt = areaSel.options[areaSel.selectedIndex];
                areaTxt = opt ? opt.text.trim() : '';
            }

            // Primer dominio seleccionado (label del checkbox)
            let dominioTxt = '';
            const idx = parseInt(item.getAttribute('data-index'), 10);
            const dominiosContainer = item.querySelector(`#dominios-${isNaN(idx)?'':idx}`);
            if (dominiosContainer) {
                const firstDom = dominiosContainer.querySelector('.dominio-checkbox:checked');
                if (firstDom) {
                    const lbl = dominiosContainer.querySelector(`label[for="${firstDom.id}"]`);
                    dominioTxt = lbl ? lbl.textContent.trim() : '';
                }
            }

            // Actividad Curricular (texto de opción seleccionada)
            const actSel = item.querySelector('.campo-actividad');
            let actTxt = '';
            if (actSel && actSel.value) {
                const optA = actSel.options[actSel.selectedIndex];
                actTxt = optA ? optA.text.trim() : '';
            }

            const truncar = (texto, max = 28) => {
                if (!texto) return '';
                return texto.length > max ? texto.substring(0, max) + '…' : texto;
            };

            const partes = [truncar(areaTxt), truncar(dominioTxt), truncar(actTxt)].filter(Boolean).slice(0, 3);
            resumen.textContent = partes.length ? `— ${partes.join(' | ')}` : '';
        }

        function habilitarSelectsFila(item) {
            const carreraId = document.getElementById('carrera_id').value;
            const selActividad = item.querySelector('.campo-actividad');
            if (selActividad) {
                const valorActual = selActividad.value; // Preservar valor actual
                if (carreraId && atributosCache.asignaturas.length) {
                    selActividad.innerHTML = optionAsignaturas(atributosCache.asignaturas, 'Seleccione una actividad curricular');
                    if (valorActual) {
                        selActividad.value = valorActual; // Restaurar valor
                    }
                    selActividad.disabled = false;
                } else {
                    selActividad.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                    selActividad.disabled = true;
                }
            }
            const perfilId = document.getElementById('perfil_id').value;
            const selArea = item.querySelector('.campo-area');
            if (selArea) {
                const valorActualArea = selArea.value; // Preservar valor actual
                if (perfilId && atributosCache.areasPorPerfil.length) {
                    selArea.innerHTML = optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área');
                    if (valorActualArea) {
                        selArea.value = valorActualArea; // Restaurar valor
                    }
                    selArea.disabled = false;
                } else {
                    selArea.innerHTML = '<option value="">Seleccione un área</option>';
                    selArea.disabled = true;
                }
            }

            const textos = item.querySelectorAll('input, textarea, select');
            textos.forEach(t => t.addEventListener('blur', () => actualizarResumenFila(item)));
            textos.forEach(t => t.addEventListener('change', () => actualizarResumenFila(item)));
        }

        function poblarSelectsConAtributos() {
            const items = document.querySelectorAll('#filas-container .accordion-item');
            items.forEach(item => {
                const selAct = item.querySelector('.campo-actividad');
                if (selAct) {
                    const prev = selAct.value;
                    selAct.innerHTML = optionAsignaturas(atributosCache.asignaturas, 'Seleccione una actividad curricular');
                    selAct.disabled = atributosCache.asignaturas.length === 0;
                    if (prev) selAct.value = prev;
                }
            });
        }

        function cargarAsignaturasYAnexos() {
            const carreraId = document.getElementById('carrera_id').value;
            if (!carreraId) {
                atributosCache = {
                    perfiles: [],
                    versiones: [],
                    resultados: [],
                    areasPorPerfil: [],
                    asignaturas: []
                };
                const selPerfil = document.getElementById('perfil_id');
                selPerfil.innerHTML = '<option value="">Seleccione un perfil</option>';
                selPerfil.disabled = true;
                document.querySelectorAll('.campo-area').forEach(sel => {
                    sel.disabled = true;
                    sel.innerHTML = '<option value="">Seleccione un área</option>';
                });
                document.querySelectorAll('.campo-actividad').forEach(sel => {
                    sel.disabled = true;
                    sel.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                });
                // limpiar vistas de dominio/competencia
                document.querySelectorAll('#filas-container .accordion-item').forEach(item => actualizarResumenFila(item));
                poblarSelectsConAtributos();
                return;
            }
            atributosCache.asignaturas = <?php echo json_encode($asignaturasTodas); ?>.filter(a => String(a.carrera_id) === String(carreraId));
            const selPerfil = document.getElementById('perfil_id');
            selPerfil.innerHTML = '<option value="">Seleccione un perfil</option>';
            selPerfil.disabled = true;
            document.querySelectorAll('.campo-area').forEach(sel => {
                sel.disabled = true;
                sel.innerHTML = '<option value="">Seleccione un área</option>';
            });
            document.querySelectorAll('.campo-actividad').forEach(sel => {
                sel.disabled = true;
                sel.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
            });
            // limpiar vistas de dominio/competencia
            document.querySelectorAll('#filas-container .accordion-item').forEach(item => actualizarResumenFila(item));
            fetch(`../../src/api/atributos.php?carrera_id=${carreraId}`)
                .then(r => r.json())
                .then(data => {
                    atributosCache.perfiles = data.perfiles || [];
                    atributosCache.versiones = data.versiones || [];
                    atributosCache.resultados = [];
                    const selPerfil = document.getElementById('perfil_id');
                    selPerfil.innerHTML = optionMarkupId(atributosCache.perfiles, 'Seleccione un perfil');
                    selPerfil.disabled = atributosCache.perfiles.length === 0;
                    atributosCache.areasPorPerfil = [];
                    document.querySelectorAll('.campo-area').forEach(sel => {
                        sel.disabled = true;
                        sel.innerHTML = '<option value="">Seleccione un área</option>';
                    });
                    poblarSelectsConAtributos();
                })
                .catch(err => console.error('Error cargar anexos:', err));
        }

        function cargarAreasPorPerfil() {
            const perfilId = document.getElementById('perfil_id').value;
            atributosCache.areasPorPerfil = [];
            document.querySelectorAll('.campo-area').forEach(sel => {
                sel.disabled = true;
                sel.innerHTML = '<option value="">Seleccione un área</option>';
            });
            // limpiar vistas de dominio/competencia
            if (!perfilId) return;
            fetch(`../../src/api/atributos.php?perfil_id=${perfilId}&action=areas`)
                .then(r => r.json())
                .then(data => {
                    atributosCache.areasPorPerfil = data.areas || [];
                    document.querySelectorAll('.campo-area').forEach(sel => {
                        sel.innerHTML = optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área');
                        sel.disabled = atributosCache.areasPorPerfil.length === 0;
                    });
                    // Preseleccionar áreas de las filas existentes
                    preseleccionarAreas();
                })
                .catch(err => console.error('Error cargar áreas por perfil:', err));
        }

        function preseleccionarAreas() {
            // Preseleccionar el área de cada fila si existe
            if (filasExistentes && filasExistentes.length > 0) {
                document.querySelectorAll('#filas-container .accordion-item').forEach((item, idx) => {
                    if (filasExistentes[idx]) {
                        const areaId = filasExistentes[idx].area_formacion_id;
                        if (areaId) {
                            const selArea = item.querySelector('.campo-area');
                            if (selArea) {
                                selArea.value = areaId;
                                // Disparar el evento de cambio para cargar dominio/competencia
                                // cargar dominios para esta área
                                const perfilId = document.getElementById('perfil_id').value;
                                cargarDominios(idx);
                            }
                        }
                    }
                });
            }
        }

        function onAreaChange(selectEl) {
            const areaId = selectEl.value;
            const perfilId = document.getElementById('perfil_id').value;
            const item = selectEl.closest('.accordion-item');
            // Reset dependent UI immediately
            const idx = parseInt(item.getAttribute('data-index'), 10);
            const domTabs = document.getElementById(`domTabs-${idx}`);
            const domTabsContent = document.getElementById(`domTabsContent-${idx}`);
            if (domTabs) domTabs.innerHTML = '';
            if (domTabsContent) domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>';
            // Uncheck any existing selections
            item.querySelectorAll('.competencia-checkbox, .resultado-checkbox, .criterios-checkboxes input[type="checkbox"]').forEach(cb => { cb.checked = false; });

            if (!areaId || !perfilId) {
                const dominiosContainer = item.querySelector(`#dominios-${item.getAttribute('data-index')}`);
                if (dominiosContainer) dominiosContainer.innerHTML = '<p class="text-muted mb-0 small">Seleccione un área primero</p>';
                actualizarResumenFila(item);
                return;
            }
            // cargar dominios
            const dominiosContainer = item.querySelector(`#dominios-${idx}`);
            if (dominiosContainer) dominiosContainer.innerHTML = '<p class="text-muted mb-0 small">Cargando dominios…</p>';
            cargarDominios(idx);
        }

        function cargarDominios(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const areaSelect = item.querySelector('.campo-area');
            const areaId = areaSelect.value;
            const perfilId = document.getElementById('perfil_id').value;
            const dominiosContainer = document.getElementById(`dominios-${index}`);
            const domTabs = document.getElementById(`domTabs-${index}`);
            const domTabsContent = document.getElementById(`domTabsContent-${index}`);
            // Reset tabs and selections immediately
            if (domTabs) domTabs.innerHTML = '';
            if (domTabsContent) domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>';
            item.querySelectorAll('.competencia-checkbox, .resultado-checkbox, .criterios-checkboxes input[type="checkbox"]').forEach(cb => { cb.checked = false; });
            if (!areaId || !perfilId) {
                dominiosContainer.innerHTML = '<p class="text-muted mb-0 small">Seleccione un área primero</p>';
                actualizarResumenFila(item);
                return;
            }
            dominiosContainer.innerHTML = '<p class="text-muted mb-0 small">Cargando dominios…</p>';
            fetch(`../../src/api/atributos.php?perfil_id=${perfilId}&area_id=${areaId}&action=dominios`)
                .then(r => r.json())
                .then(data => {
                    const dominios = data.dominios || [];
                    if (dominios.length === 0) {
                        dominiosContainer.innerHTML = '<p class="text-muted mb-0">No hay dominios para el área seleccionada</p>';
                    } else {
                        let html = '';
                        dominios.forEach(d => {
                            html += `
                            <div class="form-check" style="margin:6px 0; display:flex; align-items:center; gap:8px;">
                                <input class="form-check-input dominio-checkbox" type="checkbox" value="${d.id}" id="dom_${index}_${d.id}" onchange="cargarCompetencias(${index})">
                                <label class="form-check-label" for="dom_${index}_${d.id}" style="margin:0;">${(d.dominio||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</label>
                            </div>`;
                        });
                        dominiosContainer.innerHTML = html;
                        // Preseleccionar dominios guardados
                        const mcid = parseInt(item.getAttribute('data-mcid')||'0',10);
                        const sel = mcid ? seleccionesPorFila[mcid] : null;
                        if (sel) {
                            // Un único detalle guardado en la fila
                            if (sel.perfil_detalle_id) {
                                const chk = dominiosContainer.querySelector(`#dom_${index}_${sel.perfil_detalle_id}`);
                                if (chk) { chk.checked = true; }
                            }
                            // O múltiples detalles derivados de competencias guardadas
                            if (Array.isArray(sel.detalles)) {
                                sel.detalles.forEach(did => {
                                    const chk = dominiosContainer.querySelector(`#dom_${index}_${did}`);
                                    if (chk) { chk.checked = true; }
                                });
                            }
                        }
                        // Cargar competencias si hay dominio marcado
                        cargarCompetencias(index);
                    }
                })
                .catch(err => console.error('Error cargar dominios:', err));
        }

        // Reset completo al cambiar Perfil de egreso
        function onPerfilChange() {
            // Limpiar selects de área y dependencias por fila
            document.querySelectorAll('#filas-container .accordion-item').forEach(item => {
                const idx = parseInt(item.getAttribute('data-index'), 10);
                const selArea = item.querySelector('.campo-area');
                if (selArea) { selArea.value = ''; selArea.disabled = true; }
                const domCont = item.querySelector(`#dominios-${idx}`);
                if (domCont) domCont.innerHTML = '<p class="text-muted mb-0 small">Seleccione un área primero</p>';
                const tabs = document.getElementById(`domTabs-${idx}`);
                const tabsContent = document.getElementById(`domTabsContent-${idx}`);
                if (tabs) tabs.innerHTML = '';
                if (tabsContent) tabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>';
                // Desmarcar selecciones dependientes
                item.querySelectorAll('.dominio-checkbox, .competencia-checkbox, .resultado-checkbox, .criterios-checkboxes input[type="checkbox"]').forEach(cb => { cb.checked = false; });
                // Limpiar selección de actividad curricular pero mantener opciones
                const selAct = item.querySelector('.campo-actividad');
                if (selAct) selAct.value = '';
                actualizarResumenFila(item);
            });
            // Cargar áreas para el nuevo perfil
            cargarAreasPorPerfil();
        }

        function cargarCompetencias(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const areaSelect = item.querySelector('.campo-area');
            const areaId = areaSelect.value;
            const perfilId = document.getElementById('perfil_id').value;
            const detalleIds = Array.from(item.querySelectorAll('.dominio-checkbox:checked')).map(ch => ch.value);
            const domTabs = document.getElementById(`domTabs-${index}`);
            const domTabsContent = document.getElementById(`domTabsContent-${index}`);

            if (!areaId || !perfilId) {
                domTabs.innerHTML = '';
                domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione un área primero</div>';
                actualizarResumenFila(item);
                return;
            }

            if (!detalleIds.length) {
                domTabs.innerHTML = '';
                domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios primero</div>';
                return;
            }

            domTabs.innerHTML = '';
            domTabsContent.innerHTML = '';
            const fetches = detalleIds.map(detId => fetch(`../../src/api/atributos.php?perfil_id=${perfilId}&area_id=${areaId}&detalle_id=${detId}&action=competencias`).then(r => r.json().then(d => ({detId, data: d}))));
            Promise.all(fetches).then(resps => {
                resps.forEach((resp, idx) => {
                    const detId = resp.detId;
                    const compList = (resp.data.competencias || []);
                    const tabId = `tab-${index}-${detId}`;
                    const paneId = `pane-${index}-${detId}`;
                    const domLabelEl = item.querySelector(`label[for="dom_${index}_${detId}"]`);
                    let domName = domLabelEl ? domLabelEl.textContent.trim() : `Dominio ${detId}`;
                    const maxLen = 18;
                    if (domName.length > maxLen) { domName = domName.substring(0, maxLen - 3) + '...'; }
                    domTabs.insertAdjacentHTML('beforeend', `
                        <li class="nav-item" role="presentation">
                          <button class="nav-link ${idx===0?'active':''}" id="${tabId}" data-bs-toggle="tab" data-bs-target="#${paneId}" type="button" role="tab">${domName}</button>
                        </li>`);
                    let compHtml = '';
                    if (compList.length === 0) {
                        compHtml = '<p class="text-muted mb-0">No hay competencias disponibles</p>';
                    } else {
                        compList.forEach(comp => {
                            let desc = (comp.descripcion || '').toString();
                            const code = (comp.codigo || '').toString();
                            const normalized = desc.trim();
                            const patterns = [
                                new RegExp('^' + code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*-\\s*', 'i'),
                                new RegExp('^' + code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s+', 'i')
                            ];
                            patterns.forEach(p => { desc = desc.replace(p, ''); });
                            compHtml += `
                            <div class="form-check">
                                <input class="form-check-input competencia-checkbox" type="checkbox" value="${comp.id}" 
                                       id="comp_${index}_${detId}_${comp.id}" name="filas[${index}][competencias_ids][]" 
                                       data-detalle="${detId}" onchange="cargarResultados(${index}, ${detId})">
                                <label class="form-check-label" for="comp_${index}_${detId}_${comp.id}">
                                    ${code} - ${desc}
                                </label>
                            </div>`;
                        });
                    }
                    domTabsContent.insertAdjacentHTML('beforeend', `
                        <div class="tab-pane fade ${idx===0?'show active':''}" id="${paneId}" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Competencias</label>
                                <div class="competencias-checkboxes" id="competencias-${index}-${detId}" style="border: 1px solid #dee2e6; padding: 12px; border-radius: 6px; background-color: #f8f9fa;">${compHtml}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Resultados de Aprendizaje</label>
                                <div class="resultados-checkboxes" id="resultados-${index}-${detId}" style="border: 1px solid #dee2e6; padding: 12px; border-radius: 6px; background-color: #f8f9fa;">
                                    <p class="text-muted mb-0">Seleccione competencias primero</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Criterios de Logro</label>
                                <div class="criterios-checkboxes" id="criterios-${index}-${detId}" style="border: 1px solid #dee2e6; padding: 12px; border-radius: 6px; background-color: #f8f9fa;">
                                    <p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>
                                </div>
                            </div>
                        </div>`);
                });
                // Preselección de competencias
                const mcid = parseInt(item.getAttribute('data-mcid')||'0',10);
                const sel = mcid ? seleccionesPorFila[mcid] : null;
                if (sel && sel.competencias && sel.competencias.length) {
                    // Marcar competencias en cualquier pestaña del dominio
                    sel.competencias.forEach(cid => {
                        const cb = item.querySelector(`input.competencia-checkbox[value="${cid}"]`);
                        if (cb) cb.checked = true;
                    });
                    // Para cada dominio con al menos una competencia marcada, cargar resultados
                    const panes = item.querySelectorAll(`[id^="competencias-${index}-"]`);
                    panes.forEach(p => {
                        const idMatch = p.id.match(/^competencias-\d+-(\d+)$/);
                        const detIdForPane = idMatch ? parseInt(idMatch[1],10) : null;
                        if (!detIdForPane) return;
                        const anyChecked = p.querySelectorAll('input.competencia-checkbox:checked').length > 0;
                        if (anyChecked) {
                            cargarResultados(index, detIdForPane);
                        }
                    });
                }
                actualizarResumenFila(item);
            }).catch(err => console.error('Error construir pestañas dominios:', err));
        }

        function cargarResultados(index, detIdOverride = null) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const detId = detIdOverride;
            const competenciasChecks = detId ? item.querySelectorAll(`#competencias-${index}-${detId} .competencia-checkbox:checked`) : item.querySelectorAll('.competencia-checkbox:checked');
            const competenciasIds = Array.from(competenciasChecks).map(cb => cb.value);
            const competenciasLabels = {};

            competenciasChecks.forEach(check => {
                const label = item.querySelector(`label[for="${check.id}"]`);
                if (label) { competenciasLabels[check.value] = label.textContent.trim(); }
            });

            const resultadosContainer = detId ? document.getElementById(`resultados-${index}-${detId}`) : document.getElementById(`resultados-${index}`);
            const criteriosContainer = detId ? document.getElementById(`criterios-${index}-${detId}`) : document.getElementById(`criterios-${index}`);
            const perfilId = document.getElementById('perfil_id').value;

            if (competenciasIds.length === 0) {
                resultadosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione competencias primero</p>';
                criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                actualizarResumenFila(item);
                return;
            }

            const params = new URLSearchParams();
            params.append('perfil_id', perfilId);
            params.append('action', 'resultados');
            competenciasIds.forEach(id => params.append('competencia_ids[]', id));

            fetch(`../../src/api/atributos.php?${params.toString()}`)
                .then(r => r.json())
                .then(resultadosData => {
                    const resultados = resultadosData.resultados || [];
                    if (resultados.length === 0) {
                        resultadosContainer.innerHTML = '<p class="text-muted mb-0">No hay resultados disponibles</p>';
                        criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                    } else {
                        const porCompetencia = {};
                        const competenciasCodigos = {};
                        resultados.forEach(res => {
                            const cid = res.competencia_dominio_id;
                            if (!porCompetencia[cid]) {
                                porCompetencia[cid] = [];
                                const label = competenciasLabels[cid] || 'Competencia';
                                const match = label.match(/^([^-]+)\s*-/);
                                competenciasCodigos[cid] = match ? match[1].trim() : '';
                            }
                            porCompetencia[cid].push(res);
                        });

                        let html = '<div>';
                        Object.keys(porCompetencia).forEach(compId => {
                            const compLabel = competenciasLabels[compId] || 'Competencia';
                            const compCodigo = competenciasCodigos[compId];
                            let compHeader = compCodigo ? `<strong>${compCodigo}</strong>` : '';
                            if (compLabel) {
                                const escCode = compCodigo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                                let cleaned = compLabel.replace(new RegExp('^' + escCode + '\\s*-\\s*', 'i'), '').trim();
                                cleaned = cleaned.replace(new RegExp('^' + escCode + '\\s+', 'i'), '').trim();
                                const descTruncada = cleaned.length > 100 ? cleaned.substring(0, 100) + '...' : cleaned;
                                compHeader += compHeader ? ` - ${descTruncada}` : descTruncada;
                            }

                            html += `<div style="margin-bottom: 15px; padding: 12px; background: #f0f7ff; border-left: 4px solid #0dcaf0; border-radius: 4px;">
                                <div style="font-weight: 600; color: #0c63e4; margin-bottom: 10px; font-size: 0.95rem;">${compHeader}</div>`;

                            porCompetencia[compId].forEach(res => {
                                html += `<div style="margin-bottom: 8px; margin-left: 12px;">
                                    <div class="form-check">
                                             <input class="form-check-input resultado-checkbox" type="checkbox" value="${res.id}" 
                                                 id="res_${index}_${res.id}" name="filas[${index}][resultados_ids][]" 
                                               data-competencia="${res.competencia_dominio_id}"
                                               onchange="cargarCriterios(${index}, ${detId || 'null'})">
                                        <label class="form-check-label" for="res_${index}_${res.id}" style="margin-bottom: 0; cursor: pointer;">
                                            <strong>${res.codigo}</strong> - ${res.descripcion}
                                        </label>
                                    </div>
                                </div>`;
                            });
                            html += `</div>`;
                        });
                        html += '</div>';

                        resultadosContainer.innerHTML = html;
                        // Preselección de resultados
                        const mcid = parseInt(item.getAttribute('data-mcid')||'0',10);
                        const sel = mcid ? seleccionesPorFila[mcid] : null;
                        if (sel && sel.resultados && sel.resultados.length) {
                            sel.resultados.forEach(rid => {
                                const cb = resultadosContainer.querySelector(`#res_${index}_${rid}`);
                                if (cb) cb.checked = true;
                            });
                        }
                        // Con resultados preseleccionados, cargar criterios de inmediato para prechequearlos
                        cargarCriterios(index, detId || null);
                    }
                    actualizarResumenFila(item);
                })
                .catch(err => console.error('Error cargar resultados:', err));
        }

        function cargarCriterios(index, detIdOverride = null) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const detId = detIdOverride;
            const resultadosChecks = detId ? item.querySelectorAll(`#resultados-${index}-${detId} .resultado-checkbox:checked`) : item.querySelectorAll('.resultado-checkbox:checked');
            const resultadosIds = Array.from(resultadosChecks).map(cb => cb.value);
            const resultadosLabels = {};

            resultadosChecks.forEach(check => {
                const label = item.querySelector(`label[for="${check.id}"]`);
                if (label) { resultadosLabels[check.value] = label.textContent.trim(); }
            });

            const criteriosContainer = detId ? document.getElementById(`criterios-${index}-${detId}`) : document.getElementById(`criterios-${index}`);
            const perfilId = document.getElementById('perfil_id').value;

            if (resultadosIds.length === 0) {
                criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                actualizarResumenFila(item);
                return;
            }

            const params = new URLSearchParams();
            params.append('perfil_id', perfilId);
            params.append('action', 'criterios');
            resultadosIds.forEach(id => params.append('resultado_ids[]', id));

            fetch(`../../src/api/atributos.php?${params.toString()}`)
                .then(r => r.json())
                .then(criteriosData => {
                    const criterios = criteriosData.criterios || [];
                    if (criterios.length === 0) {
                        criteriosContainer.innerHTML = '<p class="text-muted mb-0">No hay criterios disponibles</p>';
                    } else {
                        const porResultado = {};
                        criterios.forEach(crit => {
                            const rid = crit.resultado_aprendizaje_ref_id;
                            if (!porResultado[rid]) {
                                porResultado[rid] = {
                                    label: resultadosLabels[rid] || 'Resultado',
                                    codigo: crit.resultado_codigo || '',
                                    descripcion: crit.resultado_descripcion || '',
                                    competencia_codigo: crit.competencia_codigo || '',
                                    criterios: []
                                };
                            }
                            porResultado[rid].criterios.push(crit);
                        });

                        let html = '<div>';
                        Object.keys(porResultado).forEach(resId => {
                            const resData = porResultado[resId];
                            const compCode = (resData.competencia_codigo || '').toString();
                            const escRes = resData.codigo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            let descClean = (resData.descripcion || '').toString();
                            descClean = descClean.replace(new RegExp('^' + escRes + '\\s*-\\s*', 'i'), '').trim();
                            descClean = descClean.replace(new RegExp('^' + escRes + '\\s+', 'i'), '').trim();
                            const headerLeft = compCode ? `<strong>${compCode}</strong> - <strong>${resData.codigo}</strong>` : `<strong>${resData.codigo}</strong>`;
                            const headerRight = descClean ? ` - ${descClean.length > 80 ? (descClean.substring(0,80) + '...') : descClean}` : '';
                            let resHeader = `${headerLeft}${headerRight}`;

                            html += `<div style="margin-bottom: 15px; padding: 12px; background: #f0f8f0; border-left: 4px solid #198754; border-radius: 4px;">
                                <div style="font-weight: 600; color: #155724; margin-bottom: 10px; font-size: 0.95rem;">${resHeader}</div>`;

                            resData.criterios.forEach(crit => {
                                const codigoCompleto = `${crit.codigo}`;
                                html += `<div style="margin-bottom: 8px; margin-left: 12px;">
                                    <div class="form-check">
                                             <input class="form-check-input" type="checkbox" value="${crit.id}" 
                                                 id="crit_${index}_${crit.id}" name="filas[${index}][criterios_ids][]"
                                               data-resultado="${crit.resultado_aprendizaje_ref_id}">
                                        <label class="form-check-label" for="crit_${index}_${crit.id}" style="margin-bottom: 0; cursor: pointer;">
                                            <strong style="color: #0c63e4;">${codigoCompleto}</strong> - ${crit.descripcion}
                                        </label>
                                    </div>
                                </div>`;
                            });
                            html += `</div>`;
                        });
                        html += '</div>';

                        criteriosContainer.innerHTML = html;
                        // Preselección de criterios
                        const mcid = parseInt(item.getAttribute('data-mcid')||'0',10);
                        const sel = mcid ? seleccionesPorFila[mcid] : null;
                        if (sel && sel.criterios && sel.criterios.length) {
                            sel.criterios.forEach(cid => {
                                const cb = criteriosContainer.querySelector(`#crit_${index}_${cid}`);
                                if (cb) cb.checked = true;
                            });
                        }
                    }
                    actualizarResumenFila(item);
                })
                .catch(err => console.error('Error cargar criterios:', err));
        }

        function autoResizeTextarea(textarea) {
            if (!textarea) return;
            // Reset height to auto to get the correct scrollHeight
            textarea.style.height = 'auto';
            // Set height based on scrollHeight
            textarea.style.height = Math.max(textarea.scrollHeight, 60) + 'px';
        }

        function autoResizeAllTextareasEnFila(item) {
            const textareas = item.querySelectorAll('textarea.campo-dominio, textarea.campo-competencia');
            textareas.forEach(ta => autoResizeTextarea(ta));
        }

        function limpiarBordes(item) {
            const inputs = item.querySelectorAll('select, textarea');
            inputs.forEach(el => {
                el.style.borderColor = '';
                el.style.borderWidth = '';
            });
        }

        // Validación del formulario antes de submit
        function expandirFila(item) {
            const body = item.querySelector('.accordion-collapse');
            const btn = item.querySelector('.accordion-button');
            if (body) body.classList.add('show');
            if (btn) btn.classList.remove('collapsed');
        }

        function validarFormulario() {
            const carreraId = document.getElementById('carrera_id').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();
            const nombreMatriz = document.getElementById('nombre_matriz').value.trim();
            const descripcionVersion = document.getElementById('descripcion_version').value.trim();

            if (!carreraId) {
                mostrarToastError('Debe seleccionar una Carrera.');
                return false;
            }

            if (!perfilId) {
                const perfilEl = document.getElementById('perfil_id');
                if (perfilEl) {
                    perfilEl.classList.add('campo-error','shake');
                    perfilEl.focus();
                    setTimeout(() => perfilEl.classList.remove('shake'), 500);
                }
                mostrarToastError('Debe seleccionar un Perfil de egreso.');
                return false;
            }

            if (!nombreMatriz) {
                const nombreEl = document.getElementById('nombre_matriz');
                if (nombreEl) {
                    nombreEl.classList.add('campo-error','shake');
                    nombreEl.focus();
                    setTimeout(() => nombreEl.classList.remove('shake'), 500);
                }
                mostrarToastError('Debe ingresar un Nombre de la matriz.');
                return false;
            }

            if (!descripcionVersion) {
                const versionEl = document.getElementById('descripcion_version');
                if (versionEl) {
                    versionEl.classList.add('campo-error','shake');
                    versionEl.focus();
                    setTimeout(() => versionEl.classList.remove('shake'), 500);
                }
                mostrarToastError('Debe ingresar una Descripción para la versión.');
                return false;
            }

            // Validar filas
            const items = document.querySelectorAll('.accordion-item');

            if (items.length === 0) {
                mostrarToastError('Debe agregar al menos una fila con datos.');
                return false;
            }

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const areaEl = item.querySelector('.campo-area');
                const actividadEl = item.querySelector('.campo-actividad');
                const area = (areaEl?.value || '').trim();
                const actividad = (actividadEl?.value || '').trim();

                if (!area) {
                    expandirFila(item);
                    limpiarBordes(item);
                    if (areaEl) {
                        areaEl.classList.add('campo-error','shake');
                        areaEl.focus();
                        setTimeout(() => areaEl.classList.remove('shake'), 500);
                    }
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar un Área de formación.`);
                    return false;
                }

                // Actividad Curricular se valida después de dominio/competencia/resultado/criterio

                // Validar que exista al menos un criterio seleccionado en la fila
                // Primero validar dependencias: dominios, competencias y resultados
                const idx = parseInt(item.getAttribute('data-index'), 10);
                const dominiosContainer = item.querySelector(`#dominios-${idx}`);
                const dominiosMarcados = item.querySelectorAll(`#dominios-${idx} .dominio-checkbox:checked`);
                if (!dominiosMarcados || dominiosMarcados.length === 0) {
                    expandirFila(item);
                    if (dominiosContainer) {
                        dominiosContainer.classList.add('campo-error','shake');
                        setTimeout(() => dominiosContainer.classList.remove('shake'), 500);
                        const firstDomCb = dominiosContainer.querySelector('.dominio-checkbox');
                        if (firstDomCb) { firstDomCb.focus(); }
                        dominiosContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar al menos un Dominio.`);
                    return false;
                }

                // Validación por DOMINIO: cada dominio debe tener al menos una competencia, un resultado y un criterio
                const selectedDominos = Array.from(item.querySelectorAll(`#dominios-${idx} .dominio-checkbox:checked`)).map(cb => cb.value);
                for (let d = 0; d < selectedDominos.length; d++) {
                    const detId = selectedDominos[d];
                    const compWrap = item.querySelector(`#competencias-${idx}-${detId}`);
                    const resWrap = item.querySelector(`#resultados-${idx}-${detId}`);
                    const critWrap = item.querySelector(`#criterios-${idx}-${detId}`);

                    const compChecked = compWrap ? compWrap.querySelectorAll('.competencia-checkbox:checked') : [];
                    if (!compChecked || compChecked.length === 0) {
                        expandirFila(item);
                        if (compWrap) {
                            compWrap.classList.add('campo-error','shake');
                            setTimeout(() => compWrap.classList.remove('shake'), 500);
                            const firstCompCb = compWrap.querySelector('.competencia-checkbox');
                            if (firstCompCb) { firstCompCb.focus(); }
                            compWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos una Competencia.`);
                        return false;
                    }

                    const resChecked = resWrap ? resWrap.querySelectorAll('.resultado-checkbox:checked') : [];
                    if (!resChecked || resChecked.length === 0) {
                        expandirFila(item);
                        if (resWrap) {
                            resWrap.classList.add('campo-error','shake');
                            setTimeout(() => resWrap.classList.remove('shake'), 500);
                            const firstResCb = resWrap.querySelector('.resultado-checkbox');
                            if (firstResCb) { firstResCb.focus(); }
                            resWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Resultado de Aprendizaje.`);
                        return false;
                    }

                    const critChecked = critWrap ? critWrap.querySelectorAll('input[type="checkbox"]:checked') : [];
                    if (!critChecked || critChecked.length === 0) {
                        expandirFila(item);
                        if (critWrap) {
                            critWrap.classList.add('campo-error','shake');
                            setTimeout(() => critWrap.classList.remove('shake'), 500);
                            const firstCritCb = critWrap.querySelector('input[type="checkbox"]');
                            if (firstCritCb) { firstCritCb.focus(); }
                            critWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Criterio de Logro.`);
                        return false;
                    }
                }

                if (!actividad) {
                    expandirFila(item);
                    limpiarBordes(item);
                    if (actividadEl) {
                        actividadEl.classList.add('campo-error','shake');
                        actividadEl.focus();
                        setTimeout(() => actividadEl.classList.remove('shake'), 500);
                    }
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar una Actividad Curricular.`);
                    return false;
                }
                // Validaciones adicionales: contenidos, bibliografía, metodologías, estrategias y SCT-Chile
                const contenidosEl = item.querySelector('textarea[name^="filas"][name$="[contenidos]"]');
                const bibliografiaEl = item.querySelector('textarea[name^="filas"][name$="[bibliografia]"]');
                const metodologiasEl = item.querySelector('textarea[name^="filas"][name$="[metodologias]"]');
                const estrategiasEl = item.querySelector('textarea[name^="filas"][name$="[estrategias]"]');
                const sctEl = item.querySelector('input[name^="filas"][name$="[sct_chile]"]');

                if (!contenidosEl || !bibliografiaEl || !metodologiasEl || !estrategiasEl || !sctEl) {
                    mostrarToastError(`Fila ${i + 1}: Campos faltantes en la estructura de la fila.`);
                    return false;
                }

                const contenidosVal = (contenidosEl.value || '').trim();
                const bibliografiaVal = (bibliografiaEl.value || '').trim();
                const metodologiasVal = (metodologiasEl.value || '').trim();
                const estrategiasVal = (estrategiasEl.value || '').trim();
                const sctVal = (sctEl.value || '').trim();

                if (!contenidosVal) {
                    expandirFila(item);
                    contenidosEl.classList.add('campo-error','shake');
                    contenidosEl.focus();
                    contenidosEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => contenidosEl.classList.remove('shake'), 500);
                    mostrarToastError(`Fila ${i + 1}: Debe completar Contenidos/Saberes.`);
                    return false;
                }
                if (!bibliografiaVal) {
                    expandirFila(item);
                    bibliografiaEl.classList.add('campo-error','shake');
                    bibliografiaEl.focus();
                    bibliografiaEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => bibliografiaEl.classList.remove('shake'), 500);
                    mostrarToastError(`Fila ${i + 1}: Debe completar Bibliografía.`);
                    return false;
                }
                if (!metodologiasVal) {
                    expandirFila(item);
                    metodologiasEl.classList.add('campo-error','shake');
                    metodologiasEl.focus();
                    metodologiasEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => metodologiasEl.classList.remove('shake'), 500);
                    mostrarToastError(`Fila ${i + 1}: Debe completar Metodologías Activas.`);
                    return false;
                }
                if (!estrategiasVal) {
                    expandirFila(item);
                    estrategiasEl.classList.add('campo-error','shake');
                    estrategiasEl.focus();
                    estrategiasEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => estrategiasEl.classList.remove('shake'), 500);
                    mostrarToastError(`Fila ${i + 1}: Debe completar Estrategias.`);
                    return false;
                }
                if (sctVal === '' || isNaN(Number(sctVal))) {
                    expandirFila(item);
                    sctEl.classList.add('campo-error','shake');
                    sctEl.focus();
                    sctEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => sctEl.classList.remove('shake'), 500);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar un valor numérico en SCT-Chile.`);
                    return false;
                }
            }

            return true;
        }

        // Funciones de toast
        function mostrarToastExito(mensaje = 'Matriz editada correctamente', duracion = 1500) {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast-success';
            toast.textContent = mensaje;
            container.appendChild(toast);

            // Auto-remover después del tiempo especificado
            setTimeout(() => {
                toast.classList.add('hidden');
                setTimeout(() => toast.remove(), 300);
            }, duracion);
        }

        function mostrarToastError(mensaje = 'Error al guardar') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast-error';
            toast.textContent = mensaje;
            container.appendChild(toast);

            // Auto-remover después de 4 segundos
            setTimeout(() => {
                toast.classList.add('hidden');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Clear error styles on input/change
            const clearOnEvents = ['input','change'];
            const clearError = (el) => el.classList.remove('campo-error');
            document.addEventListener('input', (e) => {
                const t = e.target;
                if (t && (t.matches('select') || t.matches('textarea') || t.matches('input'))) {
                    clearError(t);
                }
                // Clear container error for checkbox groups
                if (t && t.matches('input[type="checkbox"]')) {
                    const wrap = t.closest('.competencias-checkboxes, .resultados-checkboxes, .criterios-checkboxes');
                    if (wrap) clearError(wrap);
                    const dominiosWrap = t.closest('#dominios-' + (t.id?.split('_')[1] || ''));
                    if (dominiosWrap) clearError(dominiosWrap);
                }
            });
            document.addEventListener('change', (e) => {
                const t = e.target;
                if (t && (t.matches('select') || t.matches('textarea') || t.matches('input'))) {
                    clearError(t);
                }
                if (t && t.matches('input[type="checkbox"]')) {
                    const wrap = t.closest('.competencias-checkboxes, .resultados-checkboxes, .criterios-checkboxes');
                    if (wrap) clearError(wrap);
                    const dominiosWrap = t.closest('.dominios-checkboxes');
                    if (dominiosWrap) clearError(dominiosWrap);
                }
            });
            // Inicializar validación para todos los campos de Resultado de Aprendizaje
            document.querySelectorAll('textarea.campo-resultado').forEach((campo) => {
                if (campo.id) {
                    ValidadorEstructura.inicializarCampo(campo.id, 'resultado');
                }
            });

            // Agregar manejador del formulario para AJAX
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (validarFormulario()) {
                        // Adjuntar dominio seleccionado (primer checkbox marcado) como hidden por fila
                        document.querySelectorAll('#filas-container .accordion-item').forEach(item => {
                            const index = item.getAttribute('data-index');
                            if (index === null) return;
                            const checked = item.querySelector('.dominio-checkbox:checked');
                            const value = checked ? checked.value : '';
                            let hidden = form.querySelector(`input[name="filas[${index}][perfil_egreso_detalle_id]"]`);
                            if (!hidden) {
                                hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = `filas[${index}][perfil_egreso_detalle_id]`;
                                form.appendChild(hidden);
                            }
                            hidden.value = value;
                        });
                        // Copiar valores de campos disabled a campos hidden antes de enviar
                        document.querySelectorAll('textarea.campo-dominio, textarea.campo-competencia').forEach((campo, idx) => {
                            const hiddenDominio = document.querySelector(`input[name="filas[${campo.name.match(/\[(\d+)\]/)[1]}][dominio_value]"]`);
                            const hiddenCompetencia = document.querySelector(`input[name="filas[${campo.name.match(/\[(\d+)\]/)[1]}][competencia_value]"]`);

                            if (campo.classList.contains('campo-dominio') && !hiddenDominio) {
                                const index = campo.name.match(/\[(\d+)\]/)[1];
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = `filas[${index}][dominio]`;
                                hidden.value = campo.value;
                                form.appendChild(hidden);
                            }
                            if (campo.classList.contains('campo-competencia') && !hiddenCompetencia) {
                                const index = campo.name.match(/\[(\d+)\]/)[1];
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = `filas[${index}][competencia]`;
                                hidden.value = campo.value;
                                form.appendChild(hidden);
                            }
                        });

                        // Enviar por AJAX
                        const formData = new FormData(form);

                        fetch('editar_matriz.php?id=<?php echo $matriz_id; ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // SweetAlert2 de éxito con recarga
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Guardado',
                                        text: data.message || 'Matriz editada correctamente',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    mostrarToastError(data.message);
                                }
                            })
                            .catch(err => {
                                console.error('Error:', err);
                                mostrarToastError('Ocurrió un error al editar la matriz.');
                            });
                    }
                });
            }

            // Trigger cambio de carrera para cargar datos
            const carreraId = document.getElementById('carrera_id').value;
            if (carreraId) {
                cargarAsignaturasYAnexos();

                // Esperar un poco para que se carguen los perfiles
                setTimeout(() => {
                    // Preseleccionar el perfil de egreso si existe
                    if (perfilEgresoActual) {
                        const selPerfil = document.getElementById('perfil_id');
                        selPerfil.value = perfilEgresoActual;
                        // Disparar el evento de cambio para cargar las áreas
                        cargarAreasPorPerfil();

                        // Esperar a que se carguen las áreas y luego preseleccionar
                        setTimeout(() => {
                            preseleccionarAreas();
                        }, 500);
                    }

                    // Cargar filas existentes
                    if (filasExistentes && filasExistentes.length > 0) {
                        filasExistentes.forEach((fila, idx) => {
                            const cont = document.getElementById('filas-container');
                            const html = plantillaFila(filaCounter, fila, false);
                            const tmp = document.createElement('div');
                            tmp.innerHTML = html.trim();
                            const node = tmp.firstChild;
                            cont.appendChild(node);
                            habilitarSelectsFila(node);
                            actualizarResumenFila(node);
                            filaCounter++;
                        });
                    } else {
                        // Si no hay filas, agregar una vacía
                        agregarFila(false);
                    }
                }, 300);
            } else {
                // Si no hay carrera seleccionada, cargar filas sin perfiles
                if (filasExistentes && filasExistentes.length > 0) {
                    filasExistentes.forEach((fila, idx) => {
                        const cont = document.getElementById('filas-container');
                        const html = plantillaFila(filaCounter, fila, false);
                        const tmp = document.createElement('div');
                        tmp.innerHTML = html.trim();
                        const node = tmp.firstChild;
                        cont.appendChild(node);
                        habilitarSelectsFila(node);
                        actualizarResumenFila(node);
                        filaCounter++;
                    });
                } else {
                    agregarFila(false);
                }
            }
        });
    </script>

    <style>
        .campo-error {
            border-color: #dc3545 !important;
            border-width: 2px !important;
        }
        .shake {
            animation: shake 0.2s linear 0s 2;
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            50% { transform: translateX(3px); }
            75% { transform: translateX(-3px); }
            100% { transform: translateX(0); }
        }
        /* Toast flotante */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast-success {
            background-color: #28a745;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
            min-width: 300px;
        }

        .toast-error {
            background-color: #dc3545;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease-out;
            min-width: 300px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast-success.hidden {
            animation: slideOut 0.3s ease-out forwards;
        }

        .toast-error.hidden {
            animation: slideOut 0.3s ease-out forwards;
        }

        /* Auto-ajustar altura de textareas deshabilitadas para que crezcan con el contenido */
        textarea.campo-dominio,
        textarea.campo-competencia {
            min-height: 60px;
            resize: vertical;
            overflow-y: auto;
        }

        textarea:disabled {
            background-color: #e9ecef;
            color: #495057;
            cursor: not-allowed;
        }

        /* Auto-ajustar altura del textarea cuando cambia el contenido */
        textarea.auto-resize {
            height: auto;
            min-height: 60px;
            overflow-y: hidden;
        }

        /* Group container error visuals */
        .dominios-checkboxes.campo-error,
        .competencias-checkboxes.campo-error,
        .resultados-checkboxes.campo-error,
        .criterios-checkboxes.campo-error {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</body>

</html>