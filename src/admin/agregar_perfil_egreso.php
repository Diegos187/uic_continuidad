<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/PerfilEgreso.php';
require_once '../../src/models/PerfilEgresoDetalle.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../src/models/CompetenciaDominio.php';
require_once '../../src/models/ResultadoAprendizajeRef.php';
require_once '../../src/models/CriterioLogroRef.php';
require_once '../../includes/functions.php';

verificarSesion();

$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : 0;
if (!$carreraId) {
    redirigir('carreras.php');
}

$db = new Database();
$conn = $db->conectar();
$carreraModel = new Carrera($conn);
$perfilModel = new PerfilEgreso($conn);
$detalleModel = new PerfilEgresoDetalle($conn);
$areaModel = new AreaFormacion($conn);
$competenciaModel = new CompetenciaDominio($conn);
$resultadoModel = new ResultadoAprendizajeRef($conn);
$criterioModel = new CriterioLogroRef($conn);

$carrera = $carreraModel->obtenerPorId($carreraId);
if (!$carrera) {
    redirigir('carreras.php');
}

// Cargar todas las áreas globales para permitir reutilización inmediata
try {
    $areas = $areaModel->obtenerTodas();
} catch (Exception $e) {
    $areas = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombrePerfil = isset($_POST['nombre_perfil']) ? trim($_POST['nombre_perfil']) : '';
    $filas = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if ($nombrePerfil === '') {
        $error = 'Debe ingresar un nombre (versión) para el perfil de egreso.';
    } else {
        $filasValidas = [];
        $errorArea = false; 
        $errorCampos = false; 
        $errorCompetencias = false; 
        $errorDominio = false; 
        $errorFaltanResultados = false;
        $errorResultados = false; 
        $errorFaltanCriterios = false; 
        $errorCriterios = false;

        foreach ($filas as $f) {
            $dom = isset($f['dominio']) ? trim($f['dominio']) : '';
            $areaId = isset($f['area_formacion_id']) ? (int)$f['area_formacion_id'] : 0;
            $competencias = isset($f['competencias']) && is_array($f['competencias']) ? $f['competencias'] : [];

            if ($areaId > 0 && $dom === '') { $errorDominio = true; break; }

            if ($dom !== '') {
                if ($areaId <= 0) { $errorArea = true; break; }

                $competenciasValidas = [];
                foreach ($competencias as $comp) {
                    $codigo = isset($comp['codigo']) ? trim($comp['codigo']) : '';
                    $descripcion = isset($comp['descripcion']) ? trim($comp['descripcion']) : '';
                    $resultados = isset($comp['resultados']) && is_array($comp['resultados']) ? $comp['resultados'] : [];

                    if ($codigo !== '' || $descripcion !== '') {
                        if ($codigo === '' || $descripcion === '') { $errorCompetencias = true; break 2; }
                        // Validar resultados
                        $resultadosValidos = [];
                        foreach ($resultados as $ra) {
                            $raCodigo = isset($ra['codigo']) ? trim($ra['codigo']) : '';
                            $raDescripcion = isset($ra['descripcion']) ? trim($ra['descripcion']) : '';
                            $criterios = isset($ra['criterios']) && is_array($ra['criterios']) ? $ra['criterios'] : [];
                            if ($raCodigo !== '' || $raDescripcion !== '') {
                                if ($raCodigo === '' || $raDescripcion === '') { $errorResultados = true; break 3; }
                                $criteriosValidos = [];
                                foreach ($criterios as $cl) {
                                    $clCodigo = isset($cl['codigo']) ? trim($cl['codigo']) : '';
                                    $clDescripcion = isset($cl['descripcion']) ? trim($cl['descripcion']) : '';
                                    if ($clCodigo !== '' || $clDescripcion !== '') {
                                        if ($clCodigo === '' || $clDescripcion === '') { $errorCriterios = true; break 4; }
                                        $criteriosValidos[] = $cl;
                                    }
                                }
                                if (empty($criteriosValidos)) { $errorFaltanCriterios = true; break 3; }
                                $resultadosValidos[] = [
                                    'codigo' => $raCodigo,
                                    'descripcion' => $raDescripcion,
                                    'criterios' => $criteriosValidos
                                ];
                            }
                        }
                        if (empty($resultadosValidos)) { $errorFaltanResultados = true; break 2; }
                        $competenciasValidas[] = [
                            'codigo' => $codigo,
                            'descripcion' => $descripcion,
                            'resultados' => $resultadosValidos
                        ];
                    }
                }
                if (empty($competenciasValidas)) { $errorCampos = true; break; }
                $filasValidas[] = [
                    'area_formacion_id' => $areaId,
                    'dominio' => $dom,
                    'competencias' => $competenciasValidas
                ];
            }
        }

        if ($errorDominio) {
            $error = 'Debe ingresar el dominio cuando selecciona un área de formación.';
        } elseif ($errorArea) {
            $error = 'Debe seleccionar un área de formación en cada fila ingresada.';
        } elseif ($errorCampos) {
            $error = 'Debe agregar al menos una competencia en cada dominio.';
        } elseif ($errorCompetencias) {
            $error = 'Los campos Código y Descripción son obligatorios en cada competencia.';
        } elseif ($errorFaltanResultados) {
            $error = 'Cada competencia debe tener al menos un resultado de aprendizaje.';
        } elseif ($errorResultados) {
            $error = 'Todos los resultados de aprendizaje deben tener código y descripción.';
        } elseif ($errorFaltanCriterios) {
            $error = 'Cada resultado de aprendizaje debe tener al menos un criterio de logro.';
        } elseif ($errorCriterios) {
            $error = 'Todos los criterios de logro deben tener código y descripción.';
        } elseif (empty($filasValidas)) {
            $error = 'Debe completar al menos una fila (Área, Dominio, Competencias, Resultados y Criterios).';
        } else {
            try {
                $conn->beginTransaction();

                $perfilId = $perfilModel->crear($carreraId, $nombrePerfil);
                if (!$perfilId) {
                    throw new Exception('No se pudo crear el perfil de egreso.');
                }

                foreach ($filasValidas as $fila) {
                    $areaId = $fila['area_formacion_id'];
                    $dominio = $fila['dominio'];

                    // Crear registro en perfiles_egreso_detalle
                    $sql = "INSERT INTO perfiles_egreso_detalle (perfil_egreso_id, area_formacion_id, dominio, competencia) 
                            VALUES (:perfil_id, :area_id, :dominio, :competencia)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':perfil_id', $perfilId);
                    $stmt->bindParam(':area_id', $areaId);
                    $stmt->bindParam(':dominio', $dominio);
                    $stmt->bindParam(':competencia', $dominio); 

                    if (!$stmt->execute()) {
                        throw new Exception('Error al guardar detalle de perfil.');
                    }
                    $detalleId = $conn->lastInsertId();

                    // Guardar competencias dinámicas
                    foreach ($fila['competencias'] as $competencia) {
                        $competenciaId = $competenciaModel->crear(
                            $detalleId,
                            $competencia['codigo'],
                            $competencia['descripcion']
                        );

                        if (!$competenciaId) {
                            throw new Exception('Error al guardar competencia.');
                        }

                        // Guardar resultados de aprendizaje
                        $resultados = $competencia['resultados'] ?? [];
                        foreach ($resultados as $resultado) {
                            $raCodigo = isset($resultado['codigo']) ? trim($resultado['codigo']) : '';
                            $raDescripcion = isset($resultado['descripcion']) ? trim($resultado['descripcion']) : '';

                            if ($raCodigo !== '' && $raDescripcion !== '') {
                                $resultadoId = $resultadoModel->crear(
                                    $competenciaId,
                                    $raCodigo,
                                    $raDescripcion
                                );

                                if (!$resultadoId) {
                                    throw new Exception('Error al guardar resultado de aprendizaje.');
                                }

                                // Guardar criterios de logro
                                $criterios = isset($resultado['criterios']) && is_array($resultado['criterios']) ? $resultado['criterios'] : [];
                                foreach ($criterios as $criterio) {
                                    $clCodigo = isset($criterio['codigo']) ? trim($criterio['codigo']) : '';
                                    $clDescripcion = isset($criterio['descripcion']) ? trim($criterio['descripcion']) : '';

                                    if ($clCodigo !== '' && $clDescripcion !== '') {
                                        $criterioId = $criterioModel->crear(
                                            $resultadoId,
                                            $clCodigo,
                                            $clDescripcion
                                        );

                                        if (!$criterioId) {
                                            throw new Exception('Error al guardar criterio de logro.');
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Asociar áreas seleccionadas a la carrera
                $areasUsadas = array_unique(array_column($filasValidas, 'area_formacion_id'));
                foreach ($areasUsadas as $aid) {
                    if ($aid > 0) {
                        $areaModel->asociarACarrera($carreraId, $aid);
                    }
                }

                $conn->commit();

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Perfil de egreso creado correctamente', 'redirect' => 'perfiles_egreso.php?carrera_id=' . $carreraId]);
                    exit;
                }
                header('Location: perfiles_egreso.php?carrera_id=' . $carreraId . '&success=1');
                exit;
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = 'Error al guardar: ' . $e->getMessage();
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Agregar Perfil de Egreso - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>Nuevo Perfil de egreso <strong><?php echo htmlspecialchars($carrera['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></h2>
                        <a class="btn btn-secondary" href="perfiles_egreso.php?carrera_id=<?php echo $carreraId; ?>">Volver</a>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nombre del perfil de egreso</label>
                                <input type="text" class="form-control" name="nombre_perfil" required autocomplete="off" />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Dominios (mínimo uno)</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar Dominio</button>
                            </div>

                            <div class="accordion" id="filas-container"></div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFila()">Agregar Dominio</button>
                            </div>

                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary">Guardar Perfil de Egreso</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let filaCounter = 0;
        const AREAS = <?php echo json_encode($areas); ?>;

        function optionAreas() {
            const opts = ["<option value=''>Seleccione un área</option>"];
            AREAS.forEach(a => opts.push(`<option value='${a.id}'>${a.nombre.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</option>`));
            return opts.join('');
        }

        function plantillaFila(index) {
            const collapseId = `filaBody_${index}`;
            const headerId = `filaHeader_${index}`;
            return `
            <div class="accordion-item" data-index="${index}">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
                        <span class="fila-title">Dominio ${index + 1}</span>
                        <span class="ms-2 text-muted resumen-fila"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headerId}" data-bs-parent="#filas-container">
                    <div class="accordion-body pt-3">
                        <div class="mb-3">
                            <label class="form-label">Área de formación <span class="text-danger">*</span></label>
                            <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" required>
                                ${optionAreas()}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dominio <span class="text-danger">*</span></label>
                            <textarea class="form-control campo-dominio textarea-resize" name="filas[${index}][dominio]" rows="2" placeholder="Escriba el dominio"></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label m-0">Competencias <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-xs btn-outline-primary" onclick="agregarCompetencia(${index})" style="padding: 2px 8px; font-size: 12px;">+ Competencia</button>
                            </div>
                            <div class="competencias-container" data-dominio-index="${index}">
                                <!-- Las competencias se agregarán aquí -->
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="colapsarFila(${index})">Colapsar</button>
                            <button type="button" class="btn btn-outline-danger" onclick="eliminarFila(${index})">Eliminar Dominio</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function plantillaCompetencia(filaIndex, compIndex) {
            return `
            <div class="card mb-2 competencia-card" data-fila="${filaIndex}" data-comp="${compIndex}">
                <div class="card-header bg-light py-2">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm campo-comp-codigo" 
                                   name="filas[${filaIndex}][competencias][${compIndex}][codigo]" 
                                   placeholder="Código (ej: C1)" value="">
                        </div>
                        <div class="col">
                            <textarea class="form-control form-control-sm campo-comp-desc auto-grow" 
                                   name="filas[${filaIndex}][competencias][${compIndex}][descripcion]" 
                                   placeholder="Descripción competencia" rows="1"></textarea>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-xs btn-outline-secondary" 
                                    onclick="agregarResultado(${filaIndex}, ${compIndex})" 
                                    style="padding: 2px 6px; font-size: 12px;">+ RA</button>
                            <button type="button" class="btn btn-xs btn-outline-danger" 
                                    onclick="eliminarCompetencia(${filaIndex}, ${compIndex})" 
                                    style="padding: 2px 6px; font-size: 12px;">✕</button>
                        </div>
                    </div>
                </div>
                <div class="card-body py-2 resultados-container" data-fila="${filaIndex}" data-comp="${compIndex}">
                    <!-- Los resultados de aprendizaje irán aquí -->
                </div>
            </div>`;
        }

        function plantillaResultado(filaIndex, compIndex, raIndex) {
            return `
            <div class="ps-3 mb-2 resultado-card" data-fila="${filaIndex}" data-comp="${compIndex}" data-ra="${raIndex}">
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm campo-ra-codigo" 
                               name="filas[${filaIndex}][competencias][${compIndex}][resultados][${raIndex}][codigo]" 
                               placeholder="Código (ej: RA1)" value="">
                    </div>
                    <div class="col">
                        <textarea class="form-control form-control-sm campo-ra-desc auto-grow" 
                               name="filas[${filaIndex}][competencias][${compIndex}][resultados][${raIndex}][descripcion]" 
                               placeholder="Descripción resultado" rows="1"></textarea>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-xs btn-outline-secondary" 
                                onclick="agregarCriterio(${filaIndex}, ${compIndex}, ${raIndex})" 
                                style="padding: 2px 6px; font-size: 12px;">+ CL</button>
                        <button type="button" class="btn btn-xs btn-outline-danger" 
                                onclick="eliminarResultado(${filaIndex}, ${compIndex}, ${raIndex})" 
                                style="padding: 2px 6px; font-size: 12px;">✕</button>
                    </div>
                </div>
                <div class="criterios-container ps-3" data-fila="${filaIndex}" data-comp="${compIndex}" data-ra="${raIndex}">
                    <!-- Los criterios de logro irán aquí -->
                </div>
            </div>`;
        }

        function plantillaCriterio(filaIndex, compIndex, raIndex, clIndex) {
            return `
            <div class="row g-2 mb-2 criterio-card" data-fila="${filaIndex}" data-comp="${compIndex}" data-ra="${raIndex}" data-cl="${clIndex}">
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm campo-cl-codigo" 
                           name="filas[${filaIndex}][competencias][${compIndex}][resultados][${raIndex}][criterios][${clIndex}][codigo]" 
                           placeholder="Código (ej: CL1)" value="">
                </div>
                <div class="col">
                    <textarea class="form-control form-control-sm campo-cl-desc auto-grow" 
                           name="filas[${filaIndex}][competencias][${compIndex}][resultados][${raIndex}][criterios][${clIndex}][descripcion]" 
                           placeholder="Descripción criterio" rows="1"></textarea>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-xs btn-outline-danger" 
                            onclick="eliminarCriterio(${filaIndex}, ${compIndex}, ${raIndex}, ${clIndex})" 
                            style="padding: 2px 6px; font-size: 12px;">✕</button>
                </div>
            </div>`;
        }

        function agregarFila() {
            const cont = document.getElementById('filas-container');
            const html = plantillaFila(filaCounter);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            const node = tmp.firstChild;
            cont.appendChild(node);

            const areaSelect = node.querySelector('.campo-area');
            const dominioTextarea = node.querySelector('.campo-dominio');
            const titleEl = node.querySelector('.fila-title');
            const updateTitle = () => {
                const areaText = areaSelect && areaSelect.value ? (areaSelect.options[areaSelect.selectedIndex].text || '').trim() : '';
                const dominioText = (dominioTextarea && dominioTextarea.value || '').trim();
                let title = `Dominio ${Array.from(document.querySelectorAll('.accordion-item')).indexOf(node) + 1}`;
                if (areaText || dominioText) {
                    title = `${areaText || 'Área sin seleccionar'} - ${dominioText || 'Dominio'}`;
                }
                if (titleEl) titleEl.textContent = title;
            };
            if (areaSelect) areaSelect.addEventListener('change', updateTitle);
            if (dominioTextarea) dominioTextarea.addEventListener('input', updateTitle);
            updateTitle();

            filaCounter++;
            colapsarTodas();
            expandirUltima();
        }

        function colapsarTodas() {
            const container = document.getElementById('filas-container');
            const collapses = container.querySelectorAll('.accordion-collapse.show');
            collapses.forEach(c => c.classList.remove('show'));
        }

        function expandirUltima() {
            const container = document.getElementById('filas-container');
            const items = container.querySelectorAll('.accordion-item');
            if (items.length > 0) {
                const lastItem = items[items.length - 1];
                const collapse = lastItem.querySelector('.accordion-collapse');
                if (collapse) collapse.classList.add('show');
                lastItem.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }

        function agregarCompetencia(filaIndex) {
            const compContainer = document.querySelector(`.competencias-container[data-dominio-index="${filaIndex}"]`);
            if (!compContainer) return;

            const compCount = compContainer.querySelectorAll('.competencia-card').length;
            const html = plantillaCompetencia(filaIndex, compCount);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            compContainer.appendChild(tmp.firstChild);
        }

        function eliminarCompetencia(filaIndex, compIndex) {
            const card = document.querySelector(`.competencia-card[data-fila="${filaIndex}"][data-comp="${compIndex}"]`);
            if (card) card.remove();
        }

        function agregarResultado(filaIndex, compIndex) {
            const resultadosContainer = document.querySelector(`.resultados-container[data-fila="${filaIndex}"][data-comp="${compIndex}"]`);
            if (!resultadosContainer) return;

            const raCount = resultadosContainer.querySelectorAll('.resultado-card').length;
            const html = plantillaResultado(filaIndex, compIndex, raCount);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            resultadosContainer.appendChild(tmp.firstChild);
        }

        function eliminarResultado(filaIndex, compIndex, raIndex) {
            const card = document.querySelector(`.resultado-card[data-fila="${filaIndex}"][data-comp="${compIndex}"][data-ra="${raIndex}"]`);
            if (card) card.remove();
        }

        function agregarCriterio(filaIndex, compIndex, raIndex) {
            const criteriosContainer = document.querySelector(`.criterios-container[data-fila="${filaIndex}"][data-comp="${compIndex}"][data-ra="${raIndex}"]`);
            if (!criteriosContainer) return;

            const clCount = criteriosContainer.querySelectorAll('.criterio-card').length;
            const html = plantillaCriterio(filaIndex, compIndex, raIndex, clCount);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            criteriosContainer.appendChild(tmp.firstChild);
        }

        function eliminarCriterio(filaIndex, compIndex, raIndex, clIndex) {
            const card = document.querySelector(`.criterio-card[data-fila="${filaIndex}"][data-comp="${compIndex}"][data-ra="${raIndex}"][data-cl="${clIndex}"]`);
            if (card) card.remove();
        }

        function eliminarFila(index) {
            const node = document.querySelector(`[data-index='${index}']`);
            if (node) node.remove();
        }

        function colapsarFila(index) {
            const item = document.querySelector(`.accordion-item[data-index='${index}']`);
            if (item) {
                const body = item.querySelector('.accordion-collapse');
                if (body) body.classList.remove('show');
            }
        }

        // Inserta una fila por defecto
        document.addEventListener('DOMContentLoaded', () => {
            agregarFila();
            const initAutoGrow = () => {
                const autos = document.querySelectorAll('.auto-grow');
                autos.forEach(el => {
                    const resize = () => { el.style.height = 'auto'; el.style.height = (el.scrollHeight) + 'px'; };
                    el.addEventListener('input', resize);
                    resize();
                });
            };
            initAutoGrow();
            document.addEventListener('click', (e) => {
                if (e.target && (e.target.matches('[onclick^="agregarCompetencia"]') || e.target.matches('[onclick^="agregarResultado"]') || e.target.matches('[onclick^="agregarCriterio"]'))) {
                    setTimeout(initAutoGrow, 50);
                }
            });
        });
    </script>

    <style>
        .textarea-resize {
            resize: vertical;
            min-height: 80px;
        }

        .campo-error {
            border: 2px solid #dc3545 !important;
            background-color: #fff5f5;
        }

        @keyframes shake {
            0% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
            100% { transform: translateX(0); }
        }

        .shake-error { animation: shake 0.45s ease; }

        .accordion-collapse {
            transition: height 0.35s ease, opacity 0.35s ease, margin 0.35s ease, padding 0.35s ease !important;
            overflow: hidden !important;
        }

        .accordion-button {
            transition: background-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
        }

        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
        }

        .btn-xs {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .competencia-card {
            border-left: 4px solid #0d6efd;
            background-color: #f8f9fa;
        }

        .competencia-card .card-header {
            border-bottom: 1px solid #dee2e6;
        }

        .resultado-card {
            border-left: 3px solid #0dcaf0;
            background-color: #f0f7ff;
            padding: 0.75rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .criterio-card {
            border-left: 2px solid #28a745;
            background-color: #f0fdf4;
            padding: 0.5rem 0;
        }

        .field-group {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 0.5rem;
            border-radius: 0.25rem;
        }

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
    </style>

    <div id="toast-container" class="toast-container" style="display:none"></div>

    <script>
        function mostrarToastExito(mensaje = 'Perfil de egreso creado correctamente', duracion = 1500, redirect = null) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Perfil creado',
                    text: mensaje,
                    timer: duracion,
                    showConfirmButton: false
                }).then(() => {
                    if (redirect) { window.location.href = redirect; }
                });
            } else {
                const container = document.getElementById('toast-container');
                if (container.style.display === 'none') container.style.display = 'block';
                const toast = document.createElement('div');
                toast.className = 'toast-success';
                toast.textContent = mensaje;
                container.appendChild(toast);
                setTimeout(() => {
                    toast.classList.add('hidden');
                    setTimeout(() => { toast.remove(); if (redirect) window.location.href = redirect; }, 300);
                }, duracion);
            }
        }

        function mostrarToastError(mensaje = 'Error al guardar') {
            const container = document.getElementById('toast-container');
            if (container.style.display === 'none') container.style.display = 'block';
            const toast = document.createElement('div');
            toast.className = 'toast-error';
            toast.textContent = mensaje;
            container.appendChild(toast);
            setTimeout(() => {
                toast.classList.add('hidden');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            if (!form) return;
            form.addEventListener('submit', (e) => {
                e.preventDefault();

                const filasContainer = document.getElementById('filas-container');
                const filasItems = filasContainer.querySelectorAll('.accordion-item');
                if (filasItems.length === 0) {
                    mostrarToastError('Debe completar al menos un dominio');
                    return;
                }

                const expandAndFocus = (item, el) => {
                    if (!item) return;
                    const collapse = item.querySelector('.accordion-collapse');
                    if (collapse && !collapse.classList.contains('show')) collapse.classList.add('show');
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (el && typeof el.focus === 'function') setTimeout(() => el.focus(), 150);
                };

                const clearErrores = () => {
                    document.querySelectorAll('.campo-error').forEach(n => n.classList.remove('campo-error','shake-error'));
                };

                const marcarError = (el) => {
                    if (!el) return;
                    el.classList.add('campo-error','shake-error');
                    setTimeout(() => el.classList.remove('shake-error'), 600);
                };

                const reportError = (msg, item, el) => {
                    expandAndFocus(item, el);
                    marcarError(el);
                    mostrarToastError(msg);
                };

                clearErrores();
                let filasValidas = 0;
                for (let item of filasItems) {
                    const dominio = item.querySelector('.campo-dominio')?.value.trim() || '';
                    const area = item.querySelector('.campo-area')?.value || '';

                    if (area && !dominio) { reportError('Debe ingresar el dominio cuando selecciona un área de formación', item, item.querySelector('.campo-dominio')); return; }
                    if (!dominio) continue; 
                    if (!area) { reportError('Debe seleccionar un área de formación en cada dominio', item, item.querySelector('.campo-area')); return; }

                    const competencias = item.querySelectorAll('.competencia-card');
                    if (competencias.length === 0) { reportError('Cada dominio debe tener al menos una competencia', item, item.querySelector('button[onclick^="agregarCompetencia"]')); return; }

                    for (let comp of competencias) {
                        const codigo = comp.querySelector('.campo-comp-codigo')?.value.trim() || '';
                        const desc = comp.querySelector('.campo-comp-desc')?.value.trim() || '';
                        if (!codigo || !desc) { reportError('Todas las competencias deben tener código y descripción', item, !codigo ? comp.querySelector('.campo-comp-codigo') : comp.querySelector('.campo-comp-desc')); return; }

                        const resultados = comp.querySelectorAll('.resultado-card');
                        if (resultados.length === 0) { reportError('Cada competencia debe tener al menos un resultado de aprendizaje', item, comp.querySelector('button[onclick^="agregarResultado"]')); return; }
                        for (let ra of resultados) {
                            const raCodigo = ra.querySelector('.campo-ra-codigo')?.value.trim() || '';
                            const raDesc = ra.querySelector('.campo-ra-desc')?.value.trim() || '';
                            if (!raCodigo || !raDesc) { reportError('Todos los resultados de aprendizaje deben tener código y descripción', item, !raCodigo ? ra.querySelector('.campo-ra-codigo') : ra.querySelector('.campo-ra-desc')); return; }

                            const criterios = ra.querySelectorAll('.criterio-card');
                            if (criterios.length === 0) { reportError('Cada resultado de aprendizaje debe tener al menos un criterio de logro', item, ra.querySelector('button[onclick^="agregarCriterio"]')); return; }
                            for (let cl of criterios) {
                                const clCodigo = cl.querySelector('.campo-cl-codigo')?.value.trim() || '';
                                const clDesc = cl.querySelector('.campo-cl-desc')?.value.trim() || '';
                                if (!clCodigo || !clDesc) { reportError('Todos los criterios de logro deben tener código y descripción', item, !clCodigo ? cl.querySelector('.campo-cl-codigo') : cl.querySelector('.campo-cl-desc')); return; }
                            }
                        }
                    }
                    filasValidas++;
                }

                if (filasValidas === 0) { mostrarToastError('Debe completar al menos un dominio con toda la jerarquía (Área, Dominio, Competencias, Resultados y Criterios)'); return; }

                const formData = new FormData(form);
                fetch(form.action || '', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    if (!response.ok) return response.json().then(data => Promise.reject(data));
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        mostrarToastExito(data.message, 1700, data.redirect);
                    } else {
                        mostrarToastError(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarToastError(error.message || 'Error al guardar el perfil');
                });
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.has('success')) {
                mostrarToastExito('Perfil de egreso creado correctamente');
            }
        });
    </script>
</body>

</html>