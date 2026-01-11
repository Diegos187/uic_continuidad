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

$carreras = $carrera->obtenerTodas();
$asignaturasTodas = $asignatura->obtenerTodas();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $carrera_id = isset($_POST['carrera_id']) ? (int)limpiarDatos($_POST['carrera_id']) : null;
    $nombre_matriz = isset($_POST['nombre_matriz']) ? trim(limpiarDatos($_POST['nombre_matriz'])) : '';
    $nombre_version = isset($_POST['nombre_version']) ? trim(limpiarDatos($_POST['nombre_version'])) : '';
    $filasPost = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if (!$carrera_id) {
        $error = 'Debe seleccionar una carrera.';
    } elseif ($nombre_matriz === '') {
        $error = 'Debe ingresar un nombre para la matriz.';
    } elseif ($nombre_version === '') {
        $error = 'Debe ingresar un nombre o descripción para la versión.';
    } elseif (empty($filasPost)) {
        $error = 'Debe agregar al menos una fila a la matriz.';
    } else {
        try {
            $sqlCheck = "SELECT COUNT(*) AS total FROM matrices WHERE nombre = :nombre AND carrera_id = :carrera_id";
            $stmtCheck = $conexion->prepare($sqlCheck);
            $stmtCheck->bindParam(':nombre', $nombre_matriz, PDO::PARAM_STR);
            $stmtCheck->bindParam(':carrera_id', $carrera_id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $rowCheck = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (($rowCheck['total'] ?? 0) > 0) {
                $error = 'Ya existe una matriz con ese nombre en la carrera seleccionada.';
            }
        } catch (Exception $e) {
            error_log('Error verificando unicidad de nombre de matriz: ' . $e->getMessage());
        }

        if (!empty($error)) {
            // Saltar creación si el nombre no es único
        } else {
        $matriz_id_creada = $matrizGeneral->crear($carrera_id, null, $nombre_matriz, $nombre_matriz);
        if (!$matriz_id_creada) {
            $error = 'No se pudo crear el registro de matriz.';
        } else {
            $version_id = $versiones->crear($matriz_id_creada, $carrera_id, $nombre_version);
            if (!$version_id) {
                $error = 'No se pudo crear la versión de la matriz.';
            } else {
                try {
                    $sql = "UPDATE matrices SET version_id = :version_id WHERE id = :matriz_id";
                    $stmt = $conexion->prepare($sql);
                    $stmt->bindParam(':version_id', $version_id, PDO::PARAM_INT);
                    $stmt->bindParam(':matriz_id', $matriz_id_creada, PDO::PARAM_INT);
                    $stmt->execute();
                } catch (Exception $e) {
                    error_log('Error actualizando version_id en matriz: ' . $e->getMessage());
                }

                $filas = [];
                foreach ($filasPost as $fila) {
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

                    // Validar campos obligatorios cuando la fila tiene contenido
                    $actividad_id = isset($fila['actividad_curricular_id']) ? (int)limpiarDatos($fila['actividad_curricular_id']) : 0;
                    $dominio = isset($fila['dominio']) ? trim(limpiarDatos($fila['dominio'])) : '';
                    $competencia = isset($fila['competencia']) ? trim(limpiarDatos($fila['competencia'])) : '';
                    $resultado = isset($fila['resultado_aprendizaje']) ? trim(limpiarDatos($fila['resultado_aprendizaje'])) : '';
                    if (!$todosVacios) {
                        if (!$actividad_id) {
                            $error = 'Debe seleccionar una Actividad Curricular en todas las filas.';
                            break;
                        }
                    }

                    $perfil_superior = isset($_POST['perfil_id']) ? (int)limpiarDatos($_POST['perfil_id']) : null;

                    $competenciasSeleccionadas = isset($fila['competencias_ids']) ? (is_array($fila['competencias_ids']) ? array_map('intval', $fila['competencias_ids']) : [ (int)$fila['competencias_ids'] ]) : [];
                    $resultadosSeleccionados = isset($fila['resultados_ids']) ? (is_array($fila['resultados_ids']) ? array_map('intval', $fila['resultados_ids']) : [ (int)$fila['resultados_ids'] ]) : [];
                    $criteriosSeleccionados = isset($fila['criterios_ids']) ? (is_array($fila['criterios_ids']) ? array_map('intval', $fila['criterios_ids']) : [ (int)$fila['criterios_ids'] ]) : [];

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
                        'matriz_id' => $matriz_id_creada,
                        'asignatura_id' => $actividad_id,
                        'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                        'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                        'perfil_egreso_detalle_id' => isset($fila['perfil_egreso_detalle_id']) ? (int)limpiarDatos($fila['perfil_egreso_detalle_id']) : null,
                        'version_id' => $version_id,
                        'dominio' => $dominio,
                        'competencia' => $competenciaAgregada,
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

                if (empty($filas) && empty($error)) {
                    $error = 'No hay filas válidas para guardar.';
                } elseif (!empty($filas) && empty($error)) {
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
            }
        }
    }

        }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json; charset=UTF-8');
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
                'message' => 'Matriz creada correctamente',
                'carrera_id' => $carrera_id
            ]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Agregar Matriz de Coherencia - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Nueva Matriz de Coherencia Curricular</h2>
                            <a href="matrices.php" class="btn btn-secondary">Volver</a>
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
                                            <option value="<?php echo htmlspecialchars($carr['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($carr['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
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
                                <label for="nombre_matriz" class="form-label">Nombre de la Matriz</label>
                                <input type="text" class="form-control" id="nombre_matriz" name="nombre_matriz" placeholder="Ej: Matriz de Coherencia Curricular 2026" required />
                            </div>

                            <div class="mb-3">
                                <label for="nombre_version" class="form-label">Nombre/Descripción de la Versión</label>
                                <input type="text" class="form-control" id="nombre_version" name="nombre_version" placeholder="Ej: v1.0 - Plan Diurno / Versión inicial aprobada" required />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Filas de la Matriz</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar otra fila</button>
                            </div>

                            <div class="accordion" id="filas-container">
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFila()">Agregar otra fila</button>
                            </div>

                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" class="btn btn-primary">Crear Matriz</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <div id="toast-container" class="toast-container" style="display:none"></div>

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
                                <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" onchange="cargarDominios(${index})" disabled required>
                                    ${optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área')}
                                </select>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Dominios <span class="text-muted">(puede seleccionar varios)</span></label>
                                <div class="dominios-checkboxes" id="dominios-${index}" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 6px; background: #f7f9fc;">
                                    <p class="text-muted mb-0 small">Seleccione un área primero</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Competencias, Resultados y Criterios por Dominio</label>
                            <ul class="nav nav-tabs" id="domTabs-${index}" role="tablist" style="margin-bottom:8px;"></ul>
                            <div class="tab-content" id="domTabsContent-${index}">
                                <div class="alert alert-light border" role="alert">Seleccione dominios para ver pestañas por dominio.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actividad Curricular</label>
                            <select class="form-select campo-actividad" name="filas[${index}][actividad_curricular_id]" required>
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

            cont.querySelectorAll('.accordion-collapse').forEach(c => c.classList.remove('show'));
            node.querySelector('.accordion-collapse').classList.add('show');

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
            const dom = '';
            const comp = (item.querySelector('.campo-competencia')?.value || '').trim();
            const ra = item.querySelector('.campo-resultado')?.value || '';
            const resumen = item.querySelector('.resumen-fila');

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
                const valorActual = selActividad.value; 
                if (carreraId && atributosCache.asignaturas.length) {
                    selActividad.innerHTML = optionAsignaturas(atributosCache.asignaturas, 'Seleccione una actividad curricular');
                    if (valorActual) {
                        selActividad.value = valorActual; 
                    }
                    selActividad.disabled = false;
                } else {
                    selActividad.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                    selActividad.disabled = true;
                }
            }
            // Habilitar áreas si se seleccionó un perfil
            const perfilId = document.getElementById('perfil_id').value;
            const selArea = item.querySelector('.campo-area');
            if (selArea) {
                const valorActualArea = selArea.value; 
                if (perfilId && atributosCache.areasPorPerfil.length) {
                    selArea.innerHTML = optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área');
                    if (valorActualArea) {
                        selArea.value = valorActualArea; 
                    }
                    selArea.disabled = false;
                } else {
                    selArea.innerHTML = '<option value="">Seleccione un área</option>';
                    selArea.disabled = true;
                }
            }

            const campoResultado = item.querySelector('.campo-resultado');
            if (campoResultado) {
                const index = item.getAttribute('data-index');
                if (index !== null) {
                    ValidadorEstructura.inicializarCampo(`campo-resultado-${index}`, 'resultado');
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
                document.querySelectorAll('.campo-area').forEach(sel => {
                    sel.disabled = true;
                    sel.innerHTML = '<option value="">Seleccione un área</option>';
                });
                document.querySelectorAll('.campo-actividad').forEach(sel => {
                    sel.disabled = true;
                    sel.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                });
                document.querySelectorAll('.campo-competencia').forEach(el => {
                    el.value = '';
                    autoResizeTextarea(el);
                });
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
            document.querySelectorAll('.campo-competencia').forEach(el => {
                el.value = '';
                autoResizeTextarea(el);
            });
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

        function cargarAtributos() {
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
                })
                .catch(err => console.error('Error cargar áreas por perfil:', err));
        }

        // Reset total al cambiar Perfil de egreso, se limpia todas las filas y deja estado por defecto
        function onPerfilChange() {
            atributosCache.areasPorPerfil = [];
            atributosCache.resultados = [];

            const items = document.querySelectorAll('#filas-container .accordion-item');
            items.forEach((item) => {
                item.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);

                const selArea = item.querySelector('.campo-area');
                if (selArea) {
                    selArea.value = '';
                    selArea.disabled = true;
                    selArea.innerHTML = '<option value="">Seleccione un área</option>';
                }
                const selActividad = item.querySelector('.campo-actividad');
                if (selActividad) {
                    const carreraId = document.getElementById('carrera_id').value;
                    if (carreraId && atributosCache.asignaturas.length) {
                        const prevVal = selActividad.value;
                        selActividad.innerHTML = optionAsignaturas(atributosCache.asignaturas, 'Seleccione una actividad curricular');
                        selActividad.disabled = false;
                        if (prevVal) selActividad.value = prevVal;
                    } else {
                        selActividad.disabled = true;
                        selActividad.innerHTML = '<option value="">Seleccione una actividad curricular</option>';
                    }
                }

                const domCont = item.querySelector('[id^="dominios-"]');
                if (domCont) {
                    domCont.innerHTML = '<p class="text-muted mb-0 small">Seleccione un área primero</p>';
                }

                item.querySelectorAll('[id^="domTabs-"]').forEach(el => el.innerHTML = '');
                item.querySelectorAll('[id^="domTabsContent-"]').forEach(el => {
                    el.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios primero</div>';
                });

                item.querySelectorAll('[id^="resultados-"]').forEach(el => {
                    el.innerHTML = '<p class="text-muted mb-0">Seleccione competencias primero</p>';
                });
                item.querySelectorAll('[id^="criterios-"]').forEach(el => {
                    el.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                });

                item.querySelectorAll('.campo-dominio, .campo-competencia, .campo-resultado').forEach(el => {
                    el.value = '';
                    try { autoResizeTextarea(el); } catch (e) {}
                });

                actualizarResumenFila(item);
            });

            cargarAreasPorPerfil();
        }

        function cargarDominios(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const areaSelect = item.querySelector('.campo-area');
            const areaId = areaSelect.value;
            const perfilId = document.getElementById('perfil_id').value;
            const dominiosContainer = document.getElementById(`dominios-${index}`);
            const domTabs = document.getElementById(`domTabs-${index}`);
            const domTabsContent = document.getElementById(`domTabsContent-${index}`);
            if (domTabs) domTabs.innerHTML = '';
            if (domTabsContent) domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios primero</div>';
            item.querySelectorAll('.competencia-checkbox, .resultado-checkbox, input[id^="crit_"]').forEach(cb => { if (cb instanceof HTMLInputElement) cb.checked = false; });
            
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
                        if (domTabs) domTabs.innerHTML = '';
                        if (domTabsContent) domTabsContent.innerHTML = '<div class="alert alert-light border" role="alert">Seleccione dominios primero</div>';
                    }
                })
                .catch(err => console.error('Error cargar dominios:', err));
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

            // Construir pestañas por dominio y cargar data de cada uno
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
                    if (domName.length > maxLen) {
                        domName = domName.substring(0, maxLen - 3) + '...';
                    }
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
                actualizarResumenFila(item);
            }).catch(err => console.error('Error construir pestañas dominios:', err));
        }

        function cargarResultados(index, detIdOverride = null) {
            const item = document.querySelector(`[data-index="${index}"]`);
            const detId = detIdOverride;
            const competenciasChecks = detId ? item.querySelectorAll(`#competencias-${index}-${detId} .competencia-checkbox:checked`) : item.querySelectorAll('.competencia-checkbox:checked');
            const competenciasIds = Array.from(competenciasChecks).map(cb => cb.value);
            const competenciasLabels = {};

            // Guardar las etiquetas de competencias para referencias
            competenciasChecks.forEach(check => {
                const label = item.querySelector(`label[for="${check.id}"]`);
                if (label) {
                    competenciasLabels[check.value] = label.textContent.trim();
                }
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

            // Enviar array de competencias seleccionadas
            const params = new URLSearchParams();
            params.append('perfil_id', perfilId);
            params.append('action', 'resultados');
            competenciasIds.forEach(id => params.append('competencia_ids[]', id));

            fetch(`../../src/api/atributos.php?${params.toString()}`)
                .then(r => r.json())
                .then(resultadosData => {
                    console.log('Debug API:', resultadosData.debug);
                    const resultados = resultadosData.resultados || [];
                    console.log('Resultados cargados:', resultados.length, resultados);
                    if (resultados.length === 0) {
                        resultadosContainer.innerHTML = '<p class="text-muted mb-0">No hay resultados disponibles</p>';
                        criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                    } else {
                        // Agrupar resultados por competencia para mejor visualización
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
                                const descTruncada = cleaned.length > 100 ?
                                    cleaned.substring(0, 100) + '...' :
                                    cleaned;
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
                        criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';

                        const accordionItem = resultadosContainer.closest('.accordion-item');
                        if (accordionItem) {
                            const button = accordionItem.querySelector('.accordion-button');
                            const body = accordionItem.querySelector('.accordion-body');
                            if (button && !button.classList.contains('collapsed')) {
                                body.style.minHeight = 'auto';
                                body.style.height = 'auto';
                            }
                        }
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
                if (label) {
                    resultadosLabels[check.value] = label.textContent.trim();
                }
            });

            const criteriosContainer = detId ? document.getElementById(`criterios-${index}-${detId}`) : document.getElementById(`criterios-${index}`);
            const perfilId = document.getElementById('perfil_id').value;

            if (resultadosIds.length === 0) {
                criteriosContainer.innerHTML = '<p class="text-muted mb-0">Seleccione resultados de aprendizaje primero</p>';
                actualizarResumenFila(item);
                return;
            }

            // Enviar array de resultados seleccionados
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
                        // Agrupar criterios por resultado para mejor visualización
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
                    }
                    actualizarResumenFila(item);

                    const accordionItem = criteriosContainer.closest('.accordion-item');
                    if (accordionItem) {
                        const button = accordionItem.querySelector('.accordion-button');
                        const body = accordionItem.querySelector('.accordion-body');
                        if (button && !button.classList.contains('collapsed')) {
                            body.style.minHeight = 'auto';
                            body.style.height = 'auto';
                            setTimeout(() => {
                                body.style.height = body.scrollHeight + 'px';
                            }, 0);
                        }
                    }
                })
                .catch(err => console.error('Error cargar criterios:', err));
        }

        function autoResizeTextarea(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(textarea.scrollHeight, 60) + 'px';
        }

        function limpiarBordes(item) {
            const inputs = item.querySelectorAll('select, textarea, input');
            inputs.forEach(el => {
                el.classList.remove('campo-error','shake-error');
                el.style.borderColor = '';
                el.style.borderWidth = '';
            });
        }

        function marcarError(el) {
            if (!el) return;
            el.classList.add('campo-error','shake-error');
            setTimeout(() => el.classList.remove('shake-error'), 600);
        }

        function expandirItem(item) {
            if (!item) return;
            const collapse = item.querySelector('.accordion-collapse');
            if (collapse && !collapse.classList.contains('show')) collapse.classList.add('show');
            item.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Desplaza la página exactamente al campo indicado, considerando encabezados fijos
        function scrollToField(el) {
            if (!el) return;
            const accordionItem = el.closest('.accordion-item');
            if (accordionItem) expandirItem(accordionItem);
            const rect = el.getBoundingClientRect();
            const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
            const offset = 80;
            const targetY = rect.top + currentScroll - offset;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }

        function clearErroresGlobal() {
            document.querySelectorAll('.campo-error').forEach(el => el.classList.remove('campo-error','shake-error'));
        }

        function attachClearOnChange(root = document) {
            const targets = root.querySelectorAll('input, select, textarea');
            targets.forEach(el => {
                const handler = () => {
                    el.classList.remove('campo-error','shake-error');
                    el.style.borderColor = '';
                    el.style.borderWidth = '';
                };
                el.addEventListener('input', handler);
                el.addEventListener('change', handler);
            });
        }

        // Validación del formulario antes de submit
        function validarFormulario() {
            const form = document.querySelector('form');
            const carreraId = document.getElementById('carrera_id').value.trim();
            const perfilId = document.getElementById('perfil_id').value.trim();
            const nombreMatriz = document.getElementById('nombre_matriz').value.trim();
            const nombreVersion = document.getElementById('nombre_version').value.trim();

            if (!carreraId) {
                const el = document.getElementById('carrera_id');
                marcarError(el);
                el.focus();
                scrollToField(el);
                mostrarToastError('Debe seleccionar una Carrera.');
                return false;
            }

            if (!perfilId) {
                const el = document.getElementById('perfil_id');
                marcarError(el);
                el.focus();
                scrollToField(el);
                mostrarToastError('Debe seleccionar un Perfil de egreso.');
                return false;
            }

            if (!nombreMatriz) {
                const el = document.getElementById('nombre_matriz');
                marcarError(el);
                el.focus();
                scrollToField(el);
                mostrarToastError('Debe ingresar un nombre para la Matriz.');
                return false;
            }

            if (!nombreVersion) {
                const el = document.getElementById('nombre_version');
                marcarError(el);
                el.focus();
                scrollToField(el);
                mostrarToastError('Debe ingresar un nombre/descripción para la Versión.');
                return false;
            }

            const items = document.querySelectorAll('.accordion-item');

            if (items.length === 0) {
                mostrarToastError('Debe agregar al menos una fila con datos.');
                return false;
            }

            clearErroresGlobal();
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                const areaEl = item.querySelector('.campo-area');
                const actividadEl = item.querySelector('.campo-actividad');
                const area = areaEl ? areaEl.value.trim() : '';
                const actividad = actividadEl ? actividadEl.value.trim() : '';
                const idx = parseInt(item.getAttribute('data-index'), 10);

                // Dominios seleccionados
                const dominiosSeleccionados = item.querySelectorAll('.dominio-checkbox:checked').length;
                if (dominiosSeleccionados === 0) {
                    expandirItem(item);
                    const domCont = item.querySelector('[id^="dominios-"]');
                    marcarError(domCont);
                    scrollToField(domCont);
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar al menos un Dominio.`);
                    return false;
                }

                const dominiosCheckedEls = Array.from(item.querySelectorAll(`#dominios-${isNaN(idx)?'':idx} .dominio-checkbox:checked`));
                for (let d = 0; d < dominiosCheckedEls.length; d++) {
                    const detId = dominiosCheckedEls[d].value;
                    const compWrap = item.querySelector(`#competencias-${idx}-${detId}`);
                    const resWrap = item.querySelector(`#resultados-${idx}-${detId}`);
                    const critWrap = item.querySelector(`#criterios-${idx}-${detId}`);

                    const compChecked = compWrap ? compWrap.querySelectorAll('.competencia-checkbox:checked') : [];
                    if (!compChecked || compChecked.length === 0) {
                        expandirItem(item);
                        if (compWrap) {
                            marcarError(compWrap);
                            const firstCompCb = compWrap.querySelector('.competencia-checkbox');
                            if (firstCompCb) firstCompCb.focus();
                            compWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos una Competencia.`);
                        return false;
                    }

                    const resChecked = resWrap ? resWrap.querySelectorAll('.resultado-checkbox:checked') : [];
                    if (!resChecked || resChecked.length === 0) {
                        expandirItem(item);
                        if (resWrap) {
                            marcarError(resWrap);
                            const firstResCb = resWrap.querySelector('.resultado-checkbox');
                            if (firstResCb) firstResCb.focus();
                            resWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Resultado de Aprendizaje.`);
                        return false;
                    }

                    const critChecked = critWrap ? critWrap.querySelectorAll('input[type="checkbox"]:checked') : [];
                    if (!critChecked || critChecked.length === 0) {
                        expandirItem(item);
                        if (critWrap) {
                            marcarError(critWrap);
                            const firstCritCb = critWrap.querySelector('input[type="checkbox"]');
                            if (firstCritCb) firstCritCb.focus();
                            critWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                        mostrarToastError(`Fila ${i + 1}: En cada dominio, marque al menos un Criterio de Logro.`);
                        return false;
                    }
                }

                // Validar área
                if (!area) {
                    limpiarBordes(item);
                    if (areaEl) {
                        marcarError(areaEl);
                        areaEl.focus();
                        scrollToField(areaEl);
                    }
                    expandirItem(item);
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar un Área de formación.`);
                    return false;
                }

                // Validar actividad
                if (!actividad) {
                    limpiarBordes(item);
                    if (actividadEl) {
                        marcarError(actividadEl);
                        actividadEl.focus();
                        scrollToField(actividadEl);
                    }
                    expandirItem(item);
                    mostrarToastError(`Fila ${i + 1}: Debe seleccionar una Actividad Curricular.`);
                    return false;
                }

                // Validar campos de texto obligatorios en la fila
                const contenidosEl = item.querySelector('textarea[name^="filas"][name$="[contenidos]"]');
                const bibliografiaEl = item.querySelector('textarea[name^="filas"][name$="[bibliografia]"]');
                const metodologiasEl = item.querySelector('textarea[name^="filas"][name$="[metodologias]"]');
                const estrategiasEl = item.querySelector('textarea[name^="filas"][name$="[estrategias]"]');
                const sctEl = item.querySelector('input[name^="filas"][name$="[sct_chile]"]');

                // contenidos
                if (!contenidosEl || !contenidosEl.value.trim()) {
                    expandirItem(item);
                    marcarError(contenidosEl);
                    if (contenidosEl) contenidosEl.focus();
                    scrollToField(contenidosEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Contenidos/Saberes.`);
                    return false;
                }
                // bibliografia
                if (!bibliografiaEl || !bibliografiaEl.value.trim()) {
                    expandirItem(item);
                    marcarError(bibliografiaEl);
                    if (bibliografiaEl) bibliografiaEl.focus();
                    scrollToField(bibliografiaEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Bibliografía.`);
                    return false;
                }
                // metodologias
                if (!metodologiasEl || !metodologiasEl.value.trim()) {
                    expandirItem(item);
                    marcarError(metodologiasEl);
                    if (metodologiasEl) metodologiasEl.focus();
                    scrollToField(metodologiasEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Metodologías Activas.`);
                    return false;
                }
                // estrategias
                if (!estrategiasEl || !estrategiasEl.value.trim()) {
                    expandirItem(item);
                    marcarError(estrategiasEl);
                    if (estrategiasEl) estrategiasEl.focus();
                    scrollToField(estrategiasEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar Estrategias.`);
                    return false;
                }
                // sct chile (numérico > 0)
                const sctVal = sctEl ? String(sctEl.value || '').trim() : '';
                if (!sctVal || isNaN(Number(sctVal)) || Number(sctVal) <= 0) {
                    expandirItem(item);
                    marcarError(sctEl);
                    if (sctEl) sctEl.focus();
                    scrollToField(sctEl);
                    mostrarToastError(`Fila ${i + 1}: Debe ingresar SCT-Chile (número mayor a 0).`);
                    return false;
                }
            }

            return true;
        }

        // Agregar validación al formulario
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.accordion-button').forEach(button => {
                button.addEventListener('shown.bs.collapse', function() {
                    const body = this.closest('.accordion-item').querySelector('.accordion-body');
                    if (body) {
                        body.style.minHeight = 'auto';
                        body.style.height = 'auto';
                        body.style.overflowY = 'visible';
                        console.log('Accordion abierto, altura:', body.offsetHeight);
                    }
                });

                button.addEventListener('hide.bs.collapse', function() {
                    const body = this.closest('.accordion-item').querySelector('.accordion-body');
                    if (body) {
                        body.style.minHeight = '0';
                    }
                });
            });

            const form = document.querySelector('form');
            if (form) {
                // limpiar error al cambiar campos
                attachClearOnChange(document);
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (validarFormulario()) {
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

                        const formData = new FormData(form);

                        fetch('agregar_matriz.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.success) {
                                    if (typeof Swal !== 'undefined') {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Matriz creada',
                                            text: data.message || 'Matriz creada correctamente',
                                            timer: 1800,
                                            showConfirmButton: false
                                        }).then(() => {
                                            window.location.href = `matrices.php?carrera_id=${encodeURIComponent(data.carrera_id || '')}`;
                                        });
                                    } else {
                                        mostrarToastExito(data.message || 'Matriz creada correctamente', 1500);
                                        setTimeout(() => { window.location.href = `matrices.php?carrera_id=${encodeURIComponent(data.carrera_id || '')}`; }, 1600);
                                    }
                                } else {
                                    mostrarToastError((data && data.message) || 'Error al crear la matriz');
                                }
                            })
                            .catch(err => {
                                console.error('Error:', err);
                                Swal.fire('Error', 'Ocurrió un error al crear la matriz.', 'error');
                            });
                    }
                });
            }

            const perfilSel = document.getElementById('perfil_id');
            if (perfilSel) {
                perfilSel.addEventListener('change', onPerfilChange);
            }

            agregarFila(false);
        });

        function mostrarToastExito(mensaje = 'Matriz creada correctamente', duracion = 1500) {
            const container = document.getElementById('toast-container');
            if (container && container.style.display === 'none') container.style.display = 'block';
            const toast = document.createElement('div');
            toast.className = 'toast-success';
            toast.textContent = mensaje;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hidden');
                setTimeout(() => toast.remove(), 300);
            }, duracion);
        }

        function mostrarToastError(mensaje = 'Error al guardar') {
            const container = document.getElementById('toast-container');
            if (container && container.style.display === 'none') container.style.display = 'block';
            const toast = document.createElement('div');
            toast.className = 'toast-error';
            toast.textContent = mensaje;
            container.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('hidden');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }
    </script>

    <style>
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

        textarea.auto-resize {
            height: auto;
            min-height: 60px;
            overflow-y: hidden;
        }

        .accordion-body {
            max-height: none !important;
            overflow: visible !important;
            height: auto !important;
            min-height: auto;
        }

        .accordion-item {
            overflow: visible !important;
            max-height: none !important;
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

        .competencias-checkboxes,
        .resultados-checkboxes,
        .criterios-checkboxes {
            display: block !important;
            background-color: transparent;
            max-height: none !important;
            overflow: visible !important;
            height: auto !important;
        }

        .competencias-checkboxes .form-check,
        .resultados-checkboxes .form-check,
        .criterios-checkboxes .form-check {
            display: block !important;
            max-height: none !important;
            overflow: visible !important;
            height: auto !important;
            min-height: auto;
            margin-bottom: 8px;
        }

        .form-check-label {
            cursor: pointer;
            user-select: none;
            padding: 0 0 0 2px;
            word-break: break-word;
            display: inline-block !important;
            max-height: none !important;
            overflow: visible !important;
            line-height: 1.25rem;
        }

        .form-check-label small {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 2px;
            font-weight: 500;
        }
    </style>
</body>

</html>