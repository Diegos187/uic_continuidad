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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $carrera_id = isset($_POST['carrera_id']) ? (int)limpiarDatos($_POST['carrera_id']) : null;
    $descripcion_version = isset($_POST['descripcion_version']) ? trim(limpiarDatos($_POST['descripcion_version'])) : '';
    $filasPost = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if (!$carrera_id) {
        $error = 'Debe seleccionar una carrera.';
    } elseif ($descripcion_version === '') {
        $error = 'Debe ingresar un nombre o descripción para la matriz.';
    } elseif (empty($filasPost)) {
        $error = 'Debe agregar al menos una fila a la matriz.';
    } else {
        try {
            // PASO 1: Actualizar nombre de la matriz
            $sql = "UPDATE matrices SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':nombre', $descripcion_version);
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
            $sql = "DELETE FROM matrices_coherencia WHERE matriz_id = :matriz_id AND version_id = :version_id";
            $stmt = $conexion->prepare($sql);
            $stmt->bindParam(':matriz_id', $matriz_id, PDO::PARAM_INT);
            $stmt->bindParam(':version_id', $version_id, PDO::PARAM_INT);
            $stmt->execute();

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
                $resultado = isset($fila['resultado_aprendizaje']) ? trim(limpiarDatos($fila['resultado_aprendizaje'])) : '';
                $criterios = isset($fila['criterios_logro']) ? trim(limpiarDatos($fila['criterios_logro'])) : '';

                // Si la fila tiene contenido, validar que todos los campos obligatorios estén llenos
                if (!$todosVacios) {
                    if (!$actividad_id) {
                        $error = 'Debe seleccionar una Actividad Curricular en todas las filas.';
                        break;
                    }
                    if (empty($resultado)) {
                        $error = 'El campo Resultado de Aprendizaje es obligatorio en todas las filas.';
                        break;
                    }
                    if (empty($criterios)) {
                        $error = 'El campo Criterios de Logro es obligatorio en todas las filas.';
                        break;
                    }
                }

                // Tomar perfil de egreso del nivel superior
                $perfil_superior = isset($_POST['perfil_id']) ? (int)limpiarDatos($_POST['perfil_id']) : null;
                $dominio = isset($fila['dominio']) ? trim(limpiarDatos($fila['dominio'])) : '';
                $competencia = isset($fila['competencia']) ? trim(limpiarDatos($fila['competencia'])) : '';

                $filas[] = [
                    'matriz_id' => $matriz_id,
                    'asignatura_id' => $actividad_id,
                    'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                    'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                    'version_id' => $version_id,
                    'dominio' => $dominio,
                    'competencia' => $competencia,
                    'resultado_aprendizaje' => $resultado,
                    'criterios_logro' => $criterios,
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
                                    <select class="form-select" id="perfil_id" name="perfil_id" required disabled onchange="cargarAreasPorPerfil()">
                                        <option value="">Seleccione un perfil</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion_version" class="form-label">Nombre/Descripción de la matriz</label>
                                <input type="text" class="form-control" id="descripcion_version" name="descripcion_version" placeholder="Ej: Matriz cohorte 2026 - Plan diurno" value="<?php echo htmlspecialchars($versionActual['descripcion'] ?? ''); ?>" required />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Filas de la Matriz</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar otra fila</button>
                            </div>

                            <div class="accordion" id="filas-container">
                                <!-- Las filas dinámicas se insertan aquí por JS -->
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

            return `
            <div class="accordion-item" data-index="${index}">
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dominio</label>
                                <textarea class="form-control campo-dominio" name="filas[${index}][dominio]" rows="2" disabled>${escapeHtml(dominio)}</textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Competencia</label>
                                <textarea class="form-control campo-competencia" name="filas[${index}][competencia]" rows="2" disabled>${escapeHtml(competencia)}</textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Resultado de Aprendizaje</label>
                                <textarea class="form-control campo-resultado" id="campo-resultado-${index}" name="filas[${index}][resultado_aprendizaje]" rows="2" required>${escapeHtml(resultadoAprendizaje)}</textarea>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actividad Curricular</label>
                            <select class="form-select campo-actividad" name="filas[${index}][actividad_curricular_id]" required>
                                <option value="${asignaturaId}" ${asignaturaId ? 'selected' : ''}>Seleccione una actividad curricular</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Criterios de Logro</label>
                            <textarea class="form-control" name="filas[${index}][criterios_logro]" rows="2" required>${escapeHtml(criteriosLogro)}</textarea>
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

        function agregarFila() {
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
            const dom = (item.querySelector('.campo-dominio')?.value || '').trim();
            const comp = (item.querySelector('.campo-competencia')?.value || '').trim();
            const ra = item.querySelector('.campo-resultado')?.value || '';
            const resumen = item.querySelector('.resumen-fila');

            // Truncar cada parte a 40 caracteres máximo
            const truncar = (texto, max = 40) => {
                if (!texto) return '';
                return texto.length > max ? texto.substring(0, max) + '…' : texto;
            };

            const partes = [truncar(dom), truncar(comp), truncar(ra)].filter(Boolean).slice(0, 2);
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
                document.querySelectorAll('.campo-dominio').forEach(el => el.value = '');
                document.querySelectorAll('.campo-competencia').forEach(el => el.value = '');
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
            document.querySelectorAll('.campo-dominio').forEach(el => el.value = '');
            document.querySelectorAll('.campo-competencia').forEach(el => el.value = '');
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
            document.querySelectorAll('.campo-dominio').forEach(el => el.value = '');
            document.querySelectorAll('.campo-competencia').forEach(el => el.value = '');
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
                                const perfilId = document.getElementById('perfil_id').value;
                                const txtDom = item.querySelector('.campo-dominio');
                                const txtComp = item.querySelector('.campo-competencia');

                                // Hacer fetch directamente y esperar a que complete
                                fetch(`../../src/api/atributos.php?perfil_id=${perfilId}&area_id=${areaId}&action=detalle`)
                                    .then(r => r.json())
                                    .then(data => {
                                        const det = data.detalle || {
                                            dominio: '',
                                            competencia: ''
                                        };
                                        txtDom.value = det.dominio || '';
                                        txtComp.value = det.competencia || '';
                                        // Auto-ajustar altura después de que el contenido esté actualizado
                                        autoResizeAllTextareasEnFila(item);
                                        actualizarResumenFila(item);
                                    })
                                    .catch(err => console.error('Error cargar detalle perfil/área:', err));
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
            const txtDom = item.querySelector('.campo-dominio');
            const txtComp = item.querySelector('.campo-competencia');
            if (!areaId || !perfilId) {
                txtDom.value = '';
                txtComp.value = '';
                autoResizeTextarea(txtDom);
                autoResizeTextarea(txtComp);
                actualizarResumenFila(item);
                return;
            }
            fetch(`../../src/api/atributos.php?perfil_id=${perfilId}&area_id=${areaId}&action=detalle`)
                .then(r => r.json())
                .then(data => {
                    const det = data.detalle || {
                        dominio: '',
                        competencia: ''
                    };
                    txtDom.value = det.dominio || '';
                    txtComp.value = det.competencia || '';
                    // Auto-ajustar altura
                    autoResizeTextarea(txtDom);
                    autoResizeTextarea(txtComp);
                    actualizarResumenFila(item);
                })
                .catch(err => console.error('Error cargar detalle perfil/área:', err));
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
        function validarFormulario() {
            const carreraId = document.getElementById('carrera_id').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();
            const descripcionVersion = document.getElementById('descripcion_version').value.trim();

            if (!carreraId) {
                mostrarToastError('Debe seleccionar una Carrera.');
                return false;
            }

            if (!perfilId) {
                mostrarToastError('Debe seleccionar un Perfil de egreso.');
                return false;
            }

            if (!descripcionVersion) {
                mostrarToastError('Debe ingresar un Nombre/Descripción para la matriz.');
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

                // Obtener el textarea de criterios de logro (es el primero sin clase específica después de actividad)
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

                        fetch('editar_matriz.php?id=<?php echo $matriz_id; ?>', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Mostrar toast de éxito
                                    mostrarToastExito(data.message, 1500);
                                    // Recargar la página después de 1.5 segundos
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1500);
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
                        agregarFila();
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
                    agregarFila();
                }
            }
        });
    </script>

    <style>
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
    </style>
</body>

</html>