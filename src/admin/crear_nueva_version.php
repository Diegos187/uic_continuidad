<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/VersionMatriz.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/Matriz.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);
$matriz = new MatrizCoherencia($conexion);
$versiones = new VersionMatriz($conexion);
$matrizGeneral = new Matriz($conexion);

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
            $todosVacios = true;
            foreach (['dominio', 'competencia', 'resultado_aprendizaje', 'actividad_curricular_id', 'criterios_logro', 'contenidos', 'bibliografia', 'metodologias', 'estrategias', 'sct_chile'] as $k) {
                if (!empty($valores[$k])) {
                    $todosVacios = false;
                    break;
                }
            }
            if ($todosVacios) {
                continue;
            }

            // Tomar perfil de egreso del nivel superior (si viene)
            $perfil_superior = isset($_POST['perfil_id']) ? (int)limpiarDatos($_POST['perfil_id']) : null;
            // Resolver asignatura de la fila desde actividad curricular seleccionada
            $asignaturaIdFila = isset($fila['actividad_curricular_id']) ? (int)limpiarDatos($fila['actividad_curricular_id']) : null;

            $filas[] = [
                'matriz_id' => $matriz_id,
                'asignatura_id' => $asignaturaIdFila,
                'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                'version_id' => $version_id,
                'dominio' => isset($fila['dominio']) ? limpiarDatos($fila['dominio']) : null,
                'competencia' => isset($fila['competencia']) ? limpiarDatos($fila['competencia']) : null,
                'resultado_aprendizaje' => isset($fila['resultado_aprendizaje']) ? limpiarDatos($fila['resultado_aprendizaje']) : null,
                'criterios_logro' => isset($fila['criterios_logro']) ? limpiarDatos($fila['criterios_logro']) : null,
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dominio</label>
                                <textarea class="form-control campo-dominio" name="filas[${index}][dominio]" rows="2" disabled style="background-color: #e9ecef;"></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Competencia</label>
                                <textarea class="form-control campo-competencia" name="filas[${index}][competencia]" rows="2" disabled style="background-color: #e9ecef;"></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Resultado de Aprendizaje</label>
                                <textarea class="form-control campo-resultado" id="campo-resultado-${index}" name="filas[${index}][resultado_aprendizaje]" rows="2" required></textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actividad Curricular</label>
                            <select class="form-select campo-actividad" name="filas[${index}][actividad_curricular_id]" required disabled>
                                <option value="">Seleccione una actividad curricular</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Criterios de Logro</label>
                            <textarea class="form-control" name="filas[${index}][criterios_logro]" rows="2" required></textarea>
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
            const areaId = select.value;
            const campoDominio = item.querySelector('.campo-dominio');
            const campoCompetencia = item.querySelector('.campo-competencia');

            if (!areaId) {
                if (campoDominio) campoDominio.value = '';
                if (campoCompetencia) campoCompetencia.value = '';
                autoResizeTextarea(campoDominio);
                autoResizeTextarea(campoCompetencia);
                actualizarResumenFila(item);
                return;
            }

            // Buscar área en caché
            const area = atributosCache.areasPorPerfil.find(a => String(a.id) === String(areaId));
            if (area) {
                const dominioText = area.dominio || '';
                const competenciaText = area.competencia || '';
                if (campoDominio) campoDominio.value = dominioText;
                if (campoCompetencia) campoCompetencia.value = competenciaText;
                // Auto-ajustar altura
                autoResizeTextarea(campoDominio);
                autoResizeTextarea(campoCompetencia);
                actualizarResumenFila(item);
            }
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
                    })
                    .catch(err => console.error('Error cargando perfiles:', err));
            }
        });
    </script>
</body>

</html>