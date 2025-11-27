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
$matriz_id = isset($_GET['matriz_id']) ? (int)$_GET['matriz_id'] : 0;

// Obtener datos de la matriz actual
$matrizActual = null;
$carrera_id = 0;
if ($matriz_id) {
    $matrizActual = $matrizGeneral->obtenerPorId($matriz_id);
    if ($matrizActual) {
        $carrera_id = (int)$matrizActual['carrera_id'];
    }
}

if (!$matrizActual) {
    $error = 'Matriz no encontrada.';
}

// Obtener listas base
$carreras = $carrera->obtenerTodas();
$asignaturasTodas = $asignatura->obtenerTodas();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $descripcion_version = isset($_POST['descripcion_version']) ? trim(limpiarDatos($_POST['descripcion_version'])) : '';
    $filasPost = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if ($descripcion_version === '') {
        $error = 'Debe ingresar un nombre o descripción para la nueva versión.';
    } elseif (empty($filasPost)) {
        $error = 'Debe agregar al menos una fila a la versión.';
    } else {
        // Crear nueva versión de matriz vinculada a la matriz específica
        $version_id = $versiones->crear($matriz_id, $carrera_id, $descripcion_version);
        if (!$version_id) {
            $error = 'No se pudo crear la versión de la matriz.';
        }
    }

    if (empty($error)) {
        $filas = [];
        foreach ($filasPost as $fila) {
            // Saltar filas completamente vacías
            $valores = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $fila);
            // Considerar selección de criterios como dato mínimo
            $todosVacios = true;
            foreach (['actividad_curricular_id', 'contenidos', 'bibliografia', 'metodologias', 'estrategias', 'sct_chile', 'criterios_ids'] as $k) {
                if (!empty($valores[$k])) { $todosVacios = false; break; }
            }
            if ($todosVacios) {
                continue;
            }

            // Tomar perfil de egreso del nivel superior (si viene)
            $perfil_superior = isset($_POST['perfil_id']) ? (int)limpiarDatos($_POST['perfil_id']) : null;
            // Resolver asignatura de la fila desde actividad curricular seleccionada
            $asignaturaIdFila = isset($fila['actividad_curricular_id']) ? (int)limpiarDatos($fila['actividad_curricular_id']) : null;

            // Selecciones de la UI (checkboxes)
            $competenciasSeleccionadas = isset($fila['competencias_ids']) ? (is_array($fila['competencias_ids']) ? array_map('intval', $fila['competencias_ids']) : [ (int)$fila['competencias_ids'] ]) : [];
            $resultadosSeleccionados = isset($fila['resultados_ids']) ? (is_array($fila['resultados_ids']) ? array_map('intval', $fila['resultados_ids']) : [ (int)$fila['resultados_ids'] ]) : [];
            $criteriosSeleccionados = isset($fila['criterios_ids']) ? (is_array($fila['criterios_ids']) ? array_map('intval', $fila['criterios_ids']) : [ (int)$fila['criterios_ids'] ]) : [];
            $perfil_detalle_id = isset($fila['perfil_egreso_detalle_id']) && $fila['perfil_egreso_detalle_id'] !== '' ? (int)limpiarDatos($fila['perfil_egreso_detalle_id']) : null;

            // Validación mínima
            if (empty($asignaturaIdFila)) { continue; }
            if (empty($criteriosSeleccionados)) { $error = 'Debe seleccionar al menos un Criterio de Logro por fila.'; break; }

            // Construir textos agregados
            $competenciasTexto = [];
            foreach ($competenciasSeleccionadas as $cid) {
                $cdata = $competenciaModel->obtenerPorId($cid);
                if ($cdata) { $competenciasTexto[] = trim(($cdata['codigo'] ?? '') . ' - ' . ($cdata['descripcion'] ?? '')); }
            }
            $resultadosTexto = [];
            foreach ($resultadosSeleccionados as $rid) {
                $rdata = $resultadoModel->obtenerPorId($rid);
                if ($rdata) { $resultadosTexto[] = trim(($rdata['codigo'] ?? '') . ' - ' . ($rdata['descripcion'] ?? '')); }
            }
            $criteriosTexto = [];
            foreach ($criteriosSeleccionados as $crid) {
                $crdata = $criterioModel->obtenerPorId($crid);
                if ($crdata) { $criteriosTexto[] = trim(($crdata['codigo'] ?? '') . ' - ' . ($crdata['descripcion'] ?? '')); }
            }
            // Recuperar texto de dominio/competencia base desde el detalle de perfil si está presente
            $dominioBase = null;
            $competenciaBase = null;
            if ($perfil_detalle_id) {
                try {
                    $stmtDet = $conexion->prepare('SELECT dominio, competencia FROM perfiles_egreso_detalle WHERE id = :did');
                    $stmtDet->bindValue(':did', (int)$perfil_detalle_id, PDO::PARAM_INT);
                    $stmtDet->execute();
                    $rowDet = $stmtDet->fetch(PDO::FETCH_ASSOC);
                    if ($rowDet) {
                        $dominioBase = $rowDet['dominio'] ?? null;
                        $competenciaBase = $rowDet['competencia'] ?? null;
                    }
                } catch (Exception $e) {
                    error_log('Error obteniendo detalle de perfil: ' . $e->getMessage());
                }
            }
            $competenciaAgregada = implode("\n", $competenciasTexto);
            $resultadosAgregados = implode("\n", $resultadosTexto);
            $criteriosAgregados = implode("\n", $criteriosTexto);

            $filas[] = [
                'matriz_id' => $matriz_id,
                'asignatura_id' => $asignaturaIdFila,
                'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                'perfil_egreso_detalle_id' => $perfil_detalle_id,
                'version_id' => $version_id,
                'dominio' => $dominioBase,
                'competencia' => $competenciaAgregada ?: $competenciaBase,
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

        if (empty($filas)) {
            $error = 'No hay filas válidas para guardar.';
        } else {
            // Insertar filas por versión
            $ids = [];
            foreach ($filas as $f) {
                if (empty($f['asignatura_id'])) {
                    continue;
                }
                $id = $matriz->crear($f);
                if ($id === false) {
                    $error = 'Error al crear una fila de la versión.';
                    break;
                }
                $ids[] = $id;
            }
            if (empty($error)) {
                // Responder con JSON para AJAX
                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    header('Content-Type: application/json');
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Nueva versión creada correctamente',
                        'carrera_id' => $carrera_id
                    ]);
                    exit;
                }
            }
        }
    }

    // Responder con JSON para AJAX si hay error
    if (!empty($error) && $_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $error
        ]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Nueva Versión de Matriz - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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

        .toast-success.hidden,
        .toast-error.hidden {
            animation: slideOut 0.3s ease-out forwards;
        }

        #toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
    </style>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Nueva Versión de Matriz</h2>
                            <a href="matrices.php?carrera_id=<?php echo $carrera_id; ?>" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>

                        <?php if ($matrizActual): ?>
                            <div class="alert alert-info mb-3">
                                <strong>Matriz actual:</strong> <?php echo htmlspecialchars($matrizActual['nombre']); ?>
                            </div>

                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-12">
                                        <label for="perfil_id" class="form-label">Perfil de egreso</label>
                                        <select class="form-select" id="perfil_id" name="perfil_id" required disabled onchange="cargarAreasPorPerfil()">
                                            <option value="">Seleccione un perfil</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="descripcion_version" class="form-label">Nombre/Descripción de la nueva versión</label>
                                    <input type="text" class="form-control" id="descripcion_version" name="descripcion_version" placeholder="Ej: Actualización 2026 - Plan mejorado" required />
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="m-0">Filas de la Versión</h5>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar otra fila</button>
                                </div>

                                <div class="accordion" id="filas-container">
                                    <!-- Las filas dinámicas se insertan aquí por JS -->
                                </div>

                                <div class="d-grid gap-2 mt-3">
                                    <button type="submit" class="btn btn-primary">Crear Nueva Versión</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                No se pudo cargar la matriz. Por favor, intente nuevamente.
                            </div>
                            <a href="matrices.php" class="btn btn-secondary">Volver al listado</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
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

        const carreraId = <?php echo $carrera_id; ?>;
        const asignaturasJSON = <?php echo json_encode(array_map(function ($a) {
                                    return ['id' => $a['id'], 'nombre' => $a['nombre']];
                                }, $asignaturasTodas)); ?>;

        // Inicializar asignaturas en caché
        atributosCache.asignaturas = asignaturasJSON.filter(a => true);

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

        function plantillaFila(index) {
            const collapseId = `filaBody_${index}`;
            const headerId = `filaHeader_${index}`;
            return `
            <div class="accordion-item" data-index="${index}">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
                        Fila ${index + 1} <span class="ms-2 text-muted resumen-fila"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headerId}" data-bs-parent="#filas-container">
                    <div class="accordion-body pt-3">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Área de formación</label>
                                <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" onchange="onAreaChange(this)" disabled required>
                                    ${optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área')}
                                </select>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Dominios</label>
                                <div id="dominios-${index}" class="dominios-checkboxes" data-index="${index}"></div>
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
                            <select class="form-select campo-actividad" name="filas[${index}][actividad_curricular_id]" required disabled>
                                <option value="">Seleccione una actividad curricular</option>
                            </select>
                        </div>

                        

                        <div class="mb-3">
                            <label class="form-label">Contenidos/Saberes</label>
                            <textarea class="form-control" name="filas[${index}][contenidos]" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bibliografía</label>
                            <textarea class="form-control" name="filas[${index}][bibliografia]" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Metodologías Activas</label>
                            <textarea class="form-control" name="filas[${index}][metodologias]" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estrategias</label>
                            <textarea class="form-control" name="filas[${index}][estrategias]" rows="2"></textarea>
                        </div>

                        <div class="row align-items-end">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">SCT-Chile</label>
                                <input type="number" class="form-control" name="filas[${index}][sct_chile]" min="0" />
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

        function refrescarIndiceFilas() {
            const items = document.querySelectorAll('#filas-container .accordion-item');
            items.forEach((item, i) => {
                item.querySelector('.accordion-button').firstChild.textContent = `Fila ${i + 1} `;
            });
        }

        function agregarFila() {
            const cont = document.getElementById('filas-container');
            const html = plantillaFila(filaCounter);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            const node = tmp.firstChild;
            cont.appendChild(node);
            habilitarSelectsFila(node);
            actualizarResumenFila(node);

            // Colapsar otras y expandir nueva
            cont.querySelectorAll('.accordion-collapse').forEach(c => c.classList.remove('show'));
            node.querySelector('.accordion-collapse').classList.add('show');

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

        function colapsarFila(index) {
            const sel = `#filas-container .accordion-item[data-index="${index}"]`;
            const item = document.querySelector(sel);
            if (!item) return;
            const body = item.querySelector('.accordion-collapse');
            actualizarResumenFila(item);
            body.classList.remove('show');
        }

        function actualizarResumenFila(item) {
            const dom = (item.querySelector('.campo-dominio')?.value || '').trim();
            const comp = (item.querySelector('.campo-competencia')?.value || '').trim();
            const ra = item.querySelector('.campo-resultado')?.value || '';
            const resumen = item.querySelector('.resumen-fila');

            // Truncar a 40 caracteres máximo por parte
            const truncar = (text) => text.length > 40 ? text.substring(0, 40) + '...' : text;

            // Usar dominio y resultado de aprendizaje como las 2 partes del resumen
            const partes = [];
            if (dom) partes.push(truncar(dom));
            if (ra) partes.push(truncar(ra));

            resumen.textContent = partes.length ? `— ${partes.join(' | ')}` : '';
        }

        function habilitarSelectsFila(item) {
            // Habilitar actividades curriculares
            const selActividad = item.querySelector('.campo-actividad');
            if (selActividad) {
                if (atributosCache.asignaturas.length) {
                    selActividad.innerHTML = optionAsignaturas(atributosCache.asignaturas, 'Seleccione una actividad curricular');
                    selActividad.disabled = false;
                } else {
                    selActividad.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                    selActividad.disabled = true;
                }
            }
            // Habilitar áreas si ya se seleccionó un perfil
            const perfilId = document.getElementById('perfil_id').value;
            const selArea = item.querySelector('.campo-area');
            if (selArea) {
                if (perfilId && atributosCache.areasPorPerfil.length) {
                    selArea.innerHTML = optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área');
                    selArea.disabled = false;
                } else {
                    selArea.innerHTML = '<option value="">Seleccione un área</option>';
                    selArea.disabled = true;
                }
            }

            // Actualizar resumen al cambiar valores
            const textos = item.querySelectorAll('input, textarea, select');
            textos.forEach(t => t.addEventListener('blur', () => actualizarResumenFila(item)));
            textos.forEach(t => t.addEventListener('change', () => actualizarResumenFila(item)));
        }

        function cargarAreasPorPerfil() {
            const perfilId = document.getElementById('perfil_id').value;
            if (!perfilId) return;

            fetch(`../../src/api/atributos.php?perfil_id=${perfilId}`)
                .then(r => r.json())
                .then(data => {
                    atributosCache.areasPorPerfil = data.areas || [];

                    // Habilitar selects de área en todas las filas
                    const items = document.querySelectorAll('#filas-container .accordion-item');
                    items.forEach(item => {
                        const selArea = item.querySelector('.campo-area');
                        if (selArea) {
                            selArea.innerHTML = optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área');
                            selArea.disabled = false;
                        }
                    });
                })
                .catch(err => console.error('Error cargando áreas:', err));
        }

        function onAreaChange(select) {
            const item = select.closest('.accordion-item');
            const index = item.getAttribute('data-index');
            const perfilId = document.getElementById('perfil_id').value;
            const areaId = select.value;

            // Limpiar tabs
            const tabs = item.querySelector(`#domTabs-${index}`);
            const tabsContent = item.querySelector(`#domTabsContent-${index}`);
            if (tabs) tabs.innerHTML = '';
            if (tabsContent) tabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>';

            // Limpiar dominios
            const domCont = item.querySelector('.dominios-checkboxes');
            if (domCont) domCont.innerHTML = '';

            if (!perfilId || !areaId) return;

            // Cargar dominios por perfil y área
            fetch(`../../src/api/atributos.php?action=dominios&perfil_id=${perfilId}&area_id=${areaId}`)
                .then(r => r.json())
                .then(data => {
                    const dominios = data.dominios || [];
                    renderDominios(item, dominios);
                })
                .catch(err => console.error('Error cargando dominios:', err));
        }

        function renderDominios(item, dominios) {
            const index = item.getAttribute('data-index');
            const cont = item.querySelector(`#dominios-${index}`);
            const tabs = item.querySelector(`#domTabs-${index}`);
            const tabsContent = item.querySelector(`#domTabsContent-${index}`);
            if (!cont || !tabs || !tabsContent) return;

            const html = dominios.map(d => {
                const id = String(d.id);
                const desc = (d.dominio || d.descripcion || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                return `<div class="form-check form-check-inline me-3">
                    <input class="form-check-input dominio-checkbox" type="checkbox" value="${id}" id="dom_${index}_${id}" data-index="${index}" data-detalle-id="${id}" data-descripcion="${desc}">
                    <label class="form-check-label" for="dom_${index}_${id}">${desc}</label>
                </div>`;
            }).join('');
            cont.innerHTML = html || '<div class="text-muted">No hay dominios para el área seleccionada.</div>';

            // Evento de cambio para crear pestañas al seleccionar dominios
            cont.querySelectorAll('.dominio-checkbox').forEach(chk => {
                chk.addEventListener('change', function() {
                    const detalleId = this.getAttribute('data-detalle-id');
                    const domId = `dom_${index}_${detalleId}`;
                    const desc = this.getAttribute('data-descripcion') || `Dominio ${detalleId}`;
                    if (this.checked) {
                        addDominioTab(index, detalleId, domId, tabs, tabsContent, desc);
                        cargarCompetenciasResultadosCriterios(item, detalleId);
                    } else {
                        removeDominioTab(index, detalleId, tabs, tabsContent);
                    }
                });
            });
        }

        function addDominioTab(index, detalleId, domId, tabs, tabsContent, descripcionTab = null) {
            const tabId = `tab_${index}_${detalleId}`;
            const paneId = `pane_${index}_${detalleId}`;
            // Tab header
            const li = document.createElement('li');
            li.className = 'nav-item';
            const label = descripcionTab ? descripcionTab : `Dominio ${detalleId}`;
            li.innerHTML = `<button class="nav-link" id="${tabId}" data-bs-toggle="tab" data-bs-target="#${paneId}" type="button" role="tab" aria-controls="${paneId}" aria-selected="false">${label}</button>`;
            tabs.appendChild(li);
            // Tab content pane
            const div = document.createElement('div');
            div.className = 'tab-pane fade p-2';
            div.id = paneId;
            div.setAttribute('role', 'tabpanel');
            div.innerHTML = `
                <div class="mb-2"><strong>Competencias</strong></div>
                <div class="alert alert-light border p-3 mb-3">
                    <div id="competencias-${index}-${detalleId}" class="competencias-checkboxes"></div>
                </div>
                <div class="mb-2"><strong>Resultados de Aprendizaje</strong></div>
                <div class="alert alert-light border p-3 mb-3">
                    <div id="resultados-${index}-${detalleId}" class="resultados-checkboxes"></div>
                </div>
                <div class="mb-2"><strong>Criterios de Logro</strong></div>
                <div class="alert alert-light border p-3 mb-3">
                    <div id="criterios-${index}-${detalleId}" class="criterios-checkboxes"></div>
                </div>
                <input type="hidden" name="filas[${index}][perfil_egreso_detalle_id]" value="${detalleId}">
            `;
            tabsContent.appendChild(div);

            // Activar nueva pestaña
            const triggerEl = div.previousElementSibling ? li.querySelector('.nav-link') : li.querySelector('.nav-link');
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }

        function removeDominioTab(index, detalleId, tabs, tabsContent) {
            const tabId = `tab_${index}_${detalleId}`;
            const paneId = `pane_${index}_${detalleId}`;
            const tabBtn = tabs.querySelector(`#${tabId}`);
            const pane = tabsContent.querySelector(`#${paneId}`);
            if (tabBtn) tabBtn.closest('li').remove();
            if (pane) pane.remove();
        }

        function cargarCompetenciasResultadosCriterios(item, detalleId) {
            const index = item.getAttribute('data-index');
            const paneId = `pane_${index}_${detalleId}`;
            const pane = item.querySelector(`#${paneId}`);
            if (!pane) return;

            // Competencias por detalle (alineado con agregar_matriz)
            fetch(`../../src/api/atributos.php?action=competencias&perfil_id=${document.getElementById('perfil_id').value}&area_id=${item.querySelector('.campo-area').value}&detalle_id=${detalleId}`)
                .then(r => r.json())
                .then(data => {
                    const comps = data.competencias || [];
                    const compCont = pane.querySelector(`#competencias-${index}-${detalleId}`);
                    compCont.innerHTML = (comps.map(c => {
                        const id = String(c.id);
                        const desc = (c.descripcion || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        const codigo = (c.codigo || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        return `<div class="form-check">
                            <input class="form-check-input competencia-checkbox" type="checkbox" value="${id}" id="comp_${index}_${detalleId}_${id}" name="filas[${index}][competencias_ids][]">
                            <label class="form-check-label" for="comp_${index}_${detalleId}_${id}"><strong>${codigo}</strong> — ${desc}</label>
                        </div>`;
                    }).join('')) || '<div class="text-muted">Sin competencias.</div>';

                    // Después de competencias, cargar resultados en función de seleccionadas
                    pane.querySelectorAll('.competencia-checkbox').forEach(chk => {
                        chk.addEventListener('change', () => cargarResultados(item, detalleId));
                    });
                })
                .catch(err => console.error('Error cargando competencias:', err));
        }

        function cargarResultados(item, detalleId) {
            const index = item.getAttribute('data-index');
            const paneId = `pane_${index}_${detalleId}`;
            const pane = item.querySelector(`#${paneId}`);
            if (!pane) return;
            const compChecks = pane.querySelectorAll('.competencia-checkbox:checked');
            const compIds = Array.from(compChecks).map(el => el.value);
            const resCont = pane.querySelector(`#resultados-${index}-${detalleId}`);
            resCont.innerHTML = '';
            const critCont = pane.querySelector(`#criterios-${index}-${detalleId}`);
            critCont.innerHTML = '';
            if (compIds.length === 0) {
                resCont.innerHTML = '<div class="text-muted">Seleccione competencias para ver resultados.</div>';
                return;
            }
            fetch(`../../src/api/atributos.php?action=resultados&perfil_id=${document.getElementById('perfil_id').value}&competencia_ids=${encodeURIComponent(compIds.join(','))}`)
                .then(r => r.json())
                .then(data => {
                    const resultados = data.resultados || [];
                    resCont.innerHTML = (resultados.map(r => {
                        const id = String(r.id);
                        const desc = (r.descripcion || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        const codigo = (r.codigo || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        return `<div class="form-check">
                            <input class="form-check-input resultado-checkbox" type="checkbox" value="${id}" id="res_${index}_${detalleId}_${id}" name="filas[${index}][resultados_ids][]">
                            <label class="form-check-label" for="res_${index}_${detalleId}_${id}"><strong>${codigo}</strong> — ${desc}</label>
                        </div>`;
                    }).join('')) || '<div class="text-muted">Sin resultados.</div>';

                    pane.querySelectorAll('.resultado-checkbox').forEach(chk => {
                        chk.addEventListener('change', () => cargarCriterios(item, detalleId));
                    });
                })
                .catch(err => console.error('Error cargando resultados:', err));
        }

        function cargarCriterios(item, detalleId) {
            const index = item.getAttribute('data-index');
            const paneId = `pane_${index}_${detalleId}`;
            const pane = item.querySelector(`#${paneId}`);
            if (!pane) return;
            const resChecks = pane.querySelectorAll('.resultado-checkbox:checked');
            const resIds = Array.from(resChecks).map(el => el.value);
            const critCont = pane.querySelector('.criterios-checkboxes');
            critCont.innerHTML = '';
            if (resIds.length === 0) {
                critCont.innerHTML = '<div class="text-muted">Seleccione resultados para ver criterios.</div>';
                return;
            }
            fetch(`../../src/api/atributos.php?action=criterios&perfil_id=${document.getElementById('perfil_id').value}&resultado_ids=${encodeURIComponent(resIds.join(','))}`)
                .then(r => r.json())
                .then(data => {
                    const criterios = data.criterios || [];
                    critCont.innerHTML = (criterios.map(c => {
                        const id = String(c.id);
                        const desc = (c.descripcion || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        const codigo = (c.codigo || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        return `<div class="form-check">
                            <input class="form-check-input criterio-check" type="checkbox" value="${id}" id="crit_${index}_${detalleId}_${id}" name="filas[${index}][criterios_ids][]">
                            <label class="form-check-label" for="crit_${index}_${detalleId}_${id}"><strong>${codigo}</strong> — ${desc}</label>
                        </div>`;
                    }).join('')) || '<div class="text-muted">Sin criterios.</div>';
                })
                .catch(err => console.error('Error cargando criterios:', err));
        }

        // Validación del formulario antes de submit
        function validarFormulario() {
            const descripcionVersion = document.getElementById('descripcion_version').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();

            if (!perfilId) {
                mostrarToastError('Debe seleccionar un Perfil de egreso.');
                return false;
            }

            if (!descripcionVersion) {
                mostrarToastError('Debe ingresar un Nombre/Descripción para la nueva versión.');
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
                const resultadoEl = item.querySelector('.campo-resultado');
                const actividadEl = item.querySelector('.campo-actividad');

                // Obtener el textarea de criterios de logro
                const criteriosEl = item.querySelectorAll('textarea')[2]; // índice 2 es criterios de logro

                const area = areaEl.value.trim();
                const resultado = resultadoEl.value.trim();
                const actividad = actividadEl.value.trim();
                const criterios = criteriosEl ? criteriosEl.value.trim() : '';

                // Validar que todos los campos obligatorios estén llenos
                if (!area) {
                    limpiarBordes(item);
                    areaEl.style.borderColor = '#dc3545';
                    areaEl.style.borderWidth = '2px';
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar un Área de formación.`);
                    areaEl.focus();
                    return false;
                }

                if (!resultado) {
                    limpiarBordes(item);
                    resultadoEl.style.borderColor = '#dc3545';
                    resultadoEl.style.borderWidth = '2px';
                    mostrarToastError(`Fila ${i + 1}: El campo Resultado de Aprendizaje es obligatorio.`);
                    resultadoEl.focus();
                    return false;
                }

                if (!actividad) {
                    limpiarBordes(item);
                    actividadEl.style.borderColor = '#dc3545';
                    actividadEl.style.borderWidth = '2px';
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar una Actividad Curricular.`);
                    actividadEl.focus();
                    return false;
                }

                if (!criterios) {
                    limpiarBordes(item);
                    criteriosEl.style.borderColor = '#dc3545';
                    criteriosEl.style.borderWidth = '2px';
                    mostrarToastError(`Fila ${i + 1}: El campo Criterios de Logro es obligatorio.`);
                    criteriosEl.focus();
                    return false;
                }
            }

            return true;
        }

        // Funciones de toast
        function mostrarToastExito(mensaje = 'Nueva versión creada correctamente', duracion = 1500) {
            const container = document.getElementById('toast-container') || crearContenedorToast();
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
            const container = document.getElementById('toast-container') || crearContenedorToast();
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

        function crearContenedorToast() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
                document.body.appendChild(container);
            }
            return container;
        }

        // Cargar perfiles al abrir la página
        window.addEventListener('DOMContentLoaded', () => {
            // No hay campos de resultado tipo textarea en nueva versión

            // Agregar manejador del formulario para AJAX
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (validarFormulario()) {
                        // No necesitamos copiar textareas deshabilitadas

                        // Enviar por AJAX
                        const formData = new FormData(form);

                        fetch('crear_nueva_version.php?matriz_id=<?php echo $matriz_id; ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Mostrar toast de éxito
                                    mostrarToastExito(data.message, 1500);
                                    // Redirigir a matrices.php después de 1.5 segundos
                                    setTimeout(() => {
                                        window.location.href = `matrices.php?carrera_id=${encodeURIComponent(data.carrera_id)}`;
                                    }, 1500);
                                } else {
                                    mostrarToastError(data.message);
                                }
                            })
                            .catch(err => {
                                console.error('Error:', err);
                                mostrarToastError('Ocurrió un error al crear la nueva versión.');
                            });
                    }
                });
            }

            const perfil_id_select = document.getElementById('perfil_id');
            if (perfil_id_select) {
                fetch(`../../src/api/atributos.php?carrera_id=${carreraId}`)
                    .then(r => r.json())
                    .then(data => {
                        atributosCache.perfiles = data.perfiles || [];
                        const opts = ['<option value="">Seleccione un perfil</option>'];
                        atributosCache.perfiles.forEach(p => {
                            opts.push(`<option value="${p.id}">${p.descripcion}</option>`);
                        });
                        perfil_id_select.innerHTML = opts.join('');
                        perfil_id_select.disabled = false;
                        // Cargar áreas después de elegir perfil
                        perfil_id_select.addEventListener('change', cargarAreasPorPerfil);
                    })
                    .catch(err => console.error('Error cargando perfiles:', err));
            }
        });

        // Validación actualizada para checkboxes
        function validarFormulario() {
            const descripcionVersion = document.getElementById('descripcion_version').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();

            if (!perfilId) { mostrarToastError('Debe seleccionar un Perfil de egreso.'); return false; }
            if (!descripcionVersion) { mostrarToastError('Debe ingresar un Nombre/Descripción para la nueva versión.'); return false; }

            const items = document.querySelectorAll('.accordion-item');
            if (items.length === 0) { mostrarToastError('Debe agregar al menos una fila con datos.'); return false; }

            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const areaEl = item.querySelector('.campo-area');
                const actividadEl = item.querySelector('.campo-actividad');
                const criteriosChecks = item.querySelectorAll('.criterios-checkboxes input.criterio-check:checked');
                if (!areaEl || !areaEl.value) { mostrarToastError(`Fila ${i+1}: Debe seleccionar un Área de formación.`); areaEl && areaEl.focus(); return false; }
                if (!actividadEl || !actividadEl.value) { mostrarToastError(`Fila ${i+1}: Debe seleccionar una Actividad Curricular.`); actividadEl && actividadEl.focus(); return false; }
                if (criteriosChecks.length === 0) { mostrarToastError(`Fila ${i+1}: Debe seleccionar al menos un Criterio de Logro.`); return false; }
            }
            return true;
        }
    </script>
</body>

</html>