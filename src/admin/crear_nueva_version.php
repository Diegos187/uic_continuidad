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
            // Mantener texto plano sin entidades para evitar mostrar &quot; y &#039; en vistas/exportaciones
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
                'contenidos' => isset($fila['contenidos']) ? (is_string($fila['contenidos']) ? trim($fila['contenidos']) : $fila['contenidos']) : null,
                'bibliografia' => isset($fila['bibliografia']) ? (is_string($fila['bibliografia']) ? trim($fila['bibliografia']) : $fila['bibliografia']) : null,
                'metodologias' => isset($fila['metodologias']) ? (is_string($fila['metodologias']) ? trim($fila['metodologias']) : $fila['metodologias']) : null,
                'estrategias' => isset($fila['estrategias']) ? (is_string($fila['estrategias']) ? trim($fila['estrategias']) : $fila['estrategias']) : null,
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
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
                                <strong>Matriz actual:</strong> <?php echo htmlspecialchars($matrizActual['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-12">
                                        <label for="perfil_id" class="form-label">Perfil de egreso</label>
                                        <select class="form-select" id="perfil_id" name="perfil_id" required disabled onchange="onPerfilChange()">
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

                                <!-- Botón adicional al final para agregar más filas -->
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFila()">Agregar otra fila</button>
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
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Dominios</label>
                                <div id="dominios-${index}" class="dominios-checkboxes" data-index="${index}" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 6px; background: #f7f9fc;">
                                    <p class="text-muted mb-0 small">Seleccione un área primero</p>
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Competencias, Resultados y Criterios por Dominio</label>
                                <ul class="nav nav-tabs" id="domTabs-${index}" role="tablist" style="margin-bottom:8px;"></ul>
                                <div class="tab-content" id="domTabsContent-${index}">
                                    <div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>
                                </div>
                                    <div class="tab-content" id="domTabsContent-${index}"></div>
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

        function agregarFila(doScroll = true) {
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

            // Scroll suave al inicio de la nueva fila (header button)
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

        // Reset completo al cambiar Perfil de egreso
        function onPerfilChange() {
            // Limpiar todas las filas: área, dominios, pestañas, selecciones dependientes
            document.querySelectorAll('#filas-container .accordion-item').forEach(item => {
                const idx = item.getAttribute('data-index');
                // Reset área y deshabilitar hasta cargar
                const selArea = item.querySelector('.campo-area');
                if (selArea) { selArea.value = ''; selArea.disabled = true; selArea.innerHTML = '<option value="">Seleccione un área</option>'; }
                // Dominios
                const domCont = item.querySelector(`#dominios-${idx}`);
                if (domCont) domCont.innerHTML = '';
                // Tabs
                const tabs = item.querySelector(`#domTabs-${idx}`);
                const tabsContent = item.querySelector(`#domTabsContent-${idx}`);
                if (tabs) tabs.innerHTML = '';
                if (tabsContent) tabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>';
                    if (tabsContent) tabsContent.innerHTML = '';
                // Desmarcar selecciones
                item.querySelectorAll('.dominio-checkbox, .competencia-checkbox, .resultado-checkbox, .criterio-check').forEach(cb => { cb.checked = false; });
                // Limpiar selección de actividad curricular, mantener opciones
                const selAct = item.querySelector('.campo-actividad');
                if (selAct) { selAct.value = ''; selAct.disabled = false; }
            });
            // Cargar áreas del nuevo perfil
            cargarAreasPorPerfil();
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
                if (tabsContent) tabsContent.innerHTML = '';

            // Limpiar dominios
            const domCont = item.querySelector('.dominios-checkboxes');
            if (domCont) domCont.innerHTML = '<div class="text-muted">Cargando dominios…</div>';

            // Desmarcar selecciones previas
            item.querySelectorAll('.competencia-checkbox, .resultado-checkbox, .criterio-check').forEach(cb => { cb.checked = false; });

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
                return `<div class="form-check" style="margin:6px 0; display:flex; align-items:center; gap:8px;">
                    <input class="form-check-input dominio-checkbox" type="checkbox" value="${id}" id="dom_${index}_${id}" data-index="${index}" data-detalle-id="${id}" data-descripcion="${desc}">
                    <label class="form-check-label" for="dom_${index}_${id}" style="margin:0;">${desc}</label>
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
            let label = descripcionTab ? descripcionTab : `Dominio ${detalleId}`;
            const maxLen = 18;
            if (label.length > maxLen) label = label.substring(0, maxLen - 3) + '...';
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
                    <div id="resultados-${index}-${detalleId}" class="resultados-checkboxes"><div class="text-muted">Seleccione competencias primero.</div></div>
                </div>
                <div class="mb-2"><strong>Criterios de Logro</strong></div>
                <div class="alert alert-light border p-3 mb-3">
                    <div id="criterios-${index}-${detalleId}" class="criterios-checkboxes"><div class="text-muted">Seleccione resultados de aprendizaje primero.</div></div>
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
                        let desc = (c.descripcion || '').toString();
                        const code = (c.codigo || '').toString();
                        // Quitar código duplicado al inicio
                        const esc = code.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        const patterns = [new RegExp('^' + esc + '\\s*-\\s*', 'i'), new RegExp('^' + esc + '\\s+', 'i')];
                        patterns.forEach(p => { desc = desc.replace(p, ''); });
                        const safeDesc = desc.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        return `<div class="form-check">
                            <input class="form-check-input competencia-checkbox" type="checkbox" value="${id}" id="comp_${index}_${detalleId}_${id}" name="filas[${index}][competencias_ids][]">
                            <label class="form-check-label" for="comp_${index}_${detalleId}_${id}"><strong>${code}</strong> - ${safeDesc}</label>
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
            // Siempre mostrar placeholder inicial de criterios hasta que se seleccionen resultados
            critCont.innerHTML = '<div class="text-muted">Seleccione resultados de aprendizaje primero.</div>';
            if (compIds.length === 0) {
                resCont.innerHTML = '<div class="text-muted">Seleccione competencias primero.</div>';
                return;
            }
            fetch(`../../src/api/atributos.php?action=resultados&perfil_id=${document.getElementById('perfil_id').value}&competencia_ids=${encodeURIComponent(compIds.join(','))}`)
                .then(r => r.json())
                .then(data => {
                    const resultados = data.resultados || [];
                    // Construir labels de competencias seleccionadas
                    const competenciasLabels = {};
                    compChecks.forEach(check => {
                        const label = pane.querySelector(`label[for="${check.id}"]`);
                        if (label) competenciasLabels[check.value] = label.textContent.trim();
                    });

                    const porCompetencia = {};
                    const competenciasCodigos = {};
                    resultados.forEach(res => {
                        const cid = res.competencia_dominio_id;
                        if (!porCompetencia[cid]) {
                            porCompetencia[cid] = [];
                            const label = competenciasLabels[cid] || '';
                            const match = label.match(/^([^-]+)\s*-/);
                            competenciasCodigos[cid] = match ? match[1].trim() : '';
                        }
                        porCompetencia[cid].push(res);
                    });

                    let html = '<div>';
                    Object.keys(porCompetencia).forEach(compId => {
                        const compLabel = competenciasLabels[compId] || '';
                        const compCodigo = competenciasCodigos[compId] || '';
                        let compHeader = compCodigo ? `<strong>${compCodigo}</strong>` : '';
                        if (compLabel) {
                            const escCode = (compCodigo || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
                                    <input class="form-check-input resultado-checkbox" type="checkbox" value="${res.id}" id="res_${index}_${detalleId}_${res.id}" name="filas[${index}][resultados_ids][]">
                                    <label class="form-check-label" for="res_${index}_${detalleId}_${res.id}" style="margin-bottom: 0; cursor: pointer;">
                                        <strong>${res.codigo}</strong> - ${res.descripcion}
                                    </label>
                                </div>
                            </div>`;
                        });
                        html += `</div>`;
                    });
                    html += '</div>';

                    resCont.innerHTML = html || '<div class="text-muted">Sin resultados.</div>';

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
            const critCont = pane.querySelector(`#criterios-${index}-${detalleId}`);
            critCont.innerHTML = '';
            if (resIds.length === 0) {
                critCont.innerHTML = '<div class="text-muted">Seleccione resultados de aprendizaje primero.</div>';
                return;
            }
            fetch(`../../src/api/atributos.php?action=criterios&perfil_id=${document.getElementById('perfil_id').value}&resultado_ids=${encodeURIComponent(resIds.join(','))}`)
                .then(r => r.json())
                .then(data => {
                    const criterios = data.criterios || [];
                    const porResultado = {};
                    criterios.forEach(crit => {
                        const rid = crit.resultado_aprendizaje_ref_id;
                        if (!porResultado[rid]) {
                            porResultado[rid] = {
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
                        const escRes = (resData.codigo || '').toString().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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
                                    <input class="form-check-input criterio-check" type="checkbox" value="${crit.id}" id="crit_${index}_${detalleId}_${crit.id}" name="filas[${index}][criterios_ids][]">
                                    <label class="form-check-label" for="crit_${index}_${detalleId}_${crit.id}" style="margin-bottom: 0; cursor: pointer;">
                                        <strong style=\"color: #0c63e4;\">${codigoCompleto}</strong> - ${crit.descripcion}
                                    </label>
                                </div>
                            </div>`;
                        });
                        html += `</div>`;
                    });
                    html += '</div>';

                    critCont.innerHTML = html || '<div class="text-muted">Sin criterios.</div>';
                })
                .catch(err => console.error('Error cargando criterios:', err));
        }

        // Helpers de visualización y scroll
        function marcarError(el) { if (!el) return; el.classList.add('campo-error','shake-error'); setTimeout(()=>el.classList.remove('shake-error'), 600); }
        function clearErroresGlobal() { document.querySelectorAll('.campo-error').forEach(el => el.classList.remove('campo-error','shake-error')); }
        function attachClearOnChange(root=document) {
            root.querySelectorAll('input, select, textarea').forEach(el => {
                const handler = () => { el.classList.remove('campo-error','shake-error'); el.style.borderColor=''; el.style.borderWidth=''; };
                el.addEventListener('input', handler); el.addEventListener('change', handler);
            });
        }
        function expandirItem(item) {
            if (!item) return;
            const collapse = item.querySelector('.accordion-collapse');
            if (collapse && !collapse.classList.contains('show')) collapse.classList.add('show');
            item.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        function scrollToField(el) {
            if (!el) return;
            const accordionItem = el.closest('.accordion-item');
            if (accordionItem) expandirItem(accordionItem);
            const rect = el.getBoundingClientRect();
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            const offset = 80; // header fijo aprox.
            const targetY = rect.top + currentScroll - offset;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }

        // Validación del formulario antes de submit (con shake + scroll)
        function validarFormulario() {
            const descripcionVersion = document.getElementById('descripcion_version').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();

            if (!perfilId) {
                const el = document.getElementById('perfil_id');
                marcarError(el); el.focus(); scrollToField(el);
                mostrarToastError('Debe seleccionar un Perfil de egreso.');
                return false;
            }

            if (!descripcionVersion) {
                const el = document.getElementById('descripcion_version');
                marcarError(el); el.focus(); scrollToField(el);
                mostrarToastError('Debe ingresar un Nombre/Descripción para la nueva versión.');
                return false;
            }

            const items = document.querySelectorAll('.accordion-item');
            if (items.length === 0) { mostrarToastError('Debe agregar al menos una fila con datos.'); return false; }

            clearErroresGlobal();
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const idx = parseInt(item.getAttribute('data-index'), 10);
                const areaEl = item.querySelector('.campo-area');
                const actividadEl = item.querySelector('.campo-actividad');

                // 1) Dominios: al menos uno seleccionado
                const dominiosSeleccionadosEls = item.querySelectorAll(`#dominios-${isNaN(idx)?'':idx} .dominio-checkbox:checked`);
                if (dominiosSeleccionadosEls.length === 0) {
                    const domCont = item.querySelector(`#dominios-${isNaN(idx)?'':idx}`);
                    expandirItem(item); marcarError(domCont); scrollToField(domCont);
                    mostrarToastError(`Fila ${i+1}: Debe seleccionar al menos un Dominio.`);
                    return false;
                }

                // 2) Por cada dominio: competencia, resultado y criterio al menos uno
                for (let d = 0; d < dominiosSeleccionadosEls.length; d++) {
                    const detId = dominiosSeleccionadosEls[d].value;
                    const compWrap = item.querySelector(`#competencias-${idx}-${detId}`);
                    const resWrap = item.querySelector(`#resultados-${idx}-${detId}`);
                    const critWrap = item.querySelector(`#criterios-${idx}-${detId}`);

                    const compChecked = compWrap ? compWrap.querySelectorAll('.competencia-checkbox:checked') : [];
                    if (!compChecked || compChecked.length === 0) {
                        expandirItem(item); marcarError(compWrap); scrollToField(compWrap);
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos una Competencia.`);
                        return false;
                    }

                    const resChecked = resWrap ? resWrap.querySelectorAll('.resultado-checkbox:checked') : [];
                    if (!resChecked || resChecked.length === 0) {
                        expandirItem(item); marcarError(resWrap); scrollToField(resWrap);
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Resultado de Aprendizaje.`);
                        return false;
                    }

                    const critChecked = critWrap ? critWrap.querySelectorAll('.criterio-check:checked') : [];
                    if (!critChecked || critChecked.length === 0) {
                        expandirItem(item); marcarError(critWrap); scrollToField(critWrap);
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Criterio de Logro.`);
                        return false;
                    }
                }

                // 3) Área obligatoria
                if (!areaEl || !areaEl.value.trim()) {
                    expandirItem(item); marcarError(areaEl); areaEl && areaEl.focus(); scrollToField(areaEl);
                    mostrarToastError(`Fila ${i+1}: Debe seleccionar un Área de formación.`);
                    return false;
                }

                // 4) Actividad obligatoria
                if (!actividadEl || !actividadEl.value.trim()) {
                    expandirItem(item); marcarError(actividadEl); actividadEl && actividadEl.focus(); scrollToField(actividadEl);
                    mostrarToastError(`Fila ${i+1}: Debe seleccionar una Actividad Curricular.`);
                    return false;
                }

                // 5) Textos obligatorios: contenidos, bibliografía, metodologías, estrategias
                const contenidosEl = item.querySelector(`textarea[name^="filas"][name$="[contenidos]"]`);
                const bibliografiaEl = item.querySelector(`textarea[name^="filas"][name$="[bibliografia]"]`);
                const metodologiasEl = item.querySelector(`textarea[name^="filas"][name$="[metodologias]"]`);
                const estrategiasEl = item.querySelector(`textarea[name^="filas"][name$="[estrategias]"]`);
                const sctEl = item.querySelector(`input[name^="filas"][name$="[sct_chile]"]`);

                if (!contenidosEl || !contenidosEl.value.trim()) {
                    expandirItem(item); marcarError(contenidosEl); contenidosEl && contenidosEl.focus(); scrollToField(contenidosEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Contenidos/Saberes.`);
                    return false;
                }
                if (!bibliografiaEl || !bibliografiaEl.value.trim()) {
                    expandirItem(item); marcarError(bibliografiaEl); bibliografiaEl && bibliografiaEl.focus(); scrollToField(bibliografiaEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Bibliografía.`);
                    return false;
                }
                if (!metodologiasEl || !metodologiasEl.value.trim()) {
                    expandirItem(item); marcarError(metodologiasEl); metodologiasEl && metodologiasEl.focus(); scrollToField(metodologiasEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Metodologías Activas.`);
                    return false;
                }
                if (!estrategiasEl || !estrategiasEl.value.trim()) {
                    expandirItem(item); marcarError(estrategiasEl); estrategiasEl && estrategiasEl.focus(); scrollToField(estrategiasEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Estrategias.`);
                    return false;
                }
                const sctVal = sctEl ? String(sctEl.value || '').trim() : '';
                if (!sctVal || isNaN(Number(sctVal)) || Number(sctVal) <= 0) {
                    expandirItem(item); marcarError(sctEl); sctEl && sctEl.focus(); scrollToField(sctEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar SCT-Chile (número mayor a 0).`);
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
                attachClearOnChange(document);
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (validarFormulario()) {
                        const formData = new FormData(form);
                        fetch('crear_nueva_version.php?matriz_id=<?php echo $matriz_id; ?>', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Éxito!',
                                        text: data.message || 'Nueva versión creada correctamente',
                                        timer: 1200,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.href = `matrices.php?carrera_id=${encodeURIComponent(data.carrera_id)}`;
                                    });
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

            // Agregar automáticamente la primera fila para facilitar el llenado inicial
            try { agregarFila(false); } catch (e) { console.error('No se pudo agregar la fila inicial:', e); }

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
                        // Cargar áreas después de elegir perfil con reset completo
                        perfil_id_select.addEventListener('change', onPerfilChange);
                    })
                    .catch(err => console.error('Error cargando perfiles:', err));
            }
        });

        // Nota: validarFormulario definido arriba con shake + scroll
    </script>
</body>

</html>