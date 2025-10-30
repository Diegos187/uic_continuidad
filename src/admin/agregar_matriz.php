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
        // Crear versión de matriz para agrupar las filas
        $version_id = $versiones->crear($carrera_id, $descripcion_version);
        if (!$version_id) {
            $error = 'No se pudo crear la versión de la matriz.';
        } else {
            // Crear registro de matriz (grupo) para distinguir las filas
            $nombreMatriz = $descripcion_version !== '' ? $descripcion_version : ('Matriz versión ' . (int)$version_id);
            $matriz_id_creada = $matrizGeneral->crear($carrera_id, $version_id, $nombreMatriz, $descripcion_version);
            if (!$matriz_id_creada) {
                $error = 'No se pudo crear el registro de matriz.';
            }
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
                'matriz_id' => $matriz_id_creada ?? null,
                'asignatura_id' => $asignaturaIdFila,
                'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                // Usar el perfil seleccionado arriba (si existe)
                'perfil_egreso_id' => $perfil_superior ?: (isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null),
                // Asignar la versión recién creada
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
            // Insertar por fila para permitir actividad curricular por fila
            $ids = [];
            foreach ($filas as $f) {
                if (empty($f['asignatura_id'])) {
                    continue;
                }
                $id = $matriz->crear($f);
                if ($id === false) {
                    $error = 'Error al crear una fila de la matriz.';
                    break;
                }
                $ids[] = $id;
            }
            if (empty($error)) {
                // Redirigir al listado filtrado por carrera
                header('Location: matrices.php?carrera_id=' . urlencode((string)$carrera_id));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <title>Agregar Matriz de Coherencia - UTEM</title>
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
                            <h2>Nueva Matriz de Coherencia Curricular</h2>
                            <a href="matrices.php" class="btn btn-secondary">Volver</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        if (!empty($success)) echo mostrarMensaje($success, 'success');
                        ?>

                        <form method="POST" action="" class="needs-validation" novalidate>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="carrera_id" class="form-label">Carrera</label>
                                    <select class="form-select" id="carrera_id" name="carrera_id" required onchange="cargarAsignaturasYAnexos()">
                                        <option value="">Seleccione una carrera</option>
                                        <?php foreach ($carreras as $carr): ?>
                                            <option value="<?php echo $carr['id']; ?>"><?php echo htmlspecialchars($carr['nombre']); ?></option>
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
                                <input type="text" class="form-control" id="descripcion_version" name="descripcion_version" placeholder="Ej: Matriz cohorte 2026 - Plan diurno" required />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Filas de la Matriz</h5>
                                <button type="button" class="btn btn-sm btn-success" onclick="agregarFila()">+ Agregar otra fila</button>
                            </div>

                            <div class="accordion" id="filas-container">
                                <!-- Las filas dinámicas se insertan aquí por JS -->
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
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
                                <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" onchange="onAreaChange(this)" disabled required>
                                    ${optionMarkupId(atributosCache.areasPorPerfil, 'Seleccione un área')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dominio</label>
                                <textarea class="form-control campo-dominio" name="filas[${index}][dominio]" rows="2" readonly></textarea>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Competencia</label>
                                <textarea class="form-control campo-competencia" name="filas[${index}][competencia]" rows="2" readonly></textarea>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Resultado de Aprendizaje</label>
                                <textarea class="form-control campo-resultado" name="filas[${index}][resultado_aprendizaje]" rows="2" required></textarea>
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
            const partes = [dom, comp, ra].filter(Boolean).slice(0, 3);
            resumen.textContent = partes.length ? `— ${partes.join(' | ')}` : '';
        }

        function habilitarSelectsFila(item) {
            // Habilitar actividades curriculares si hay carrera seleccionada
            const carreraId = document.getElementById('carrera_id').value;
            const selActividad = item.querySelector('.campo-actividad');
            if (selActividad) {
                if (carreraId && atributosCache.asignaturas.length) {
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
                // limpiar select perfil
                const selPerfil = document.getElementById('perfil_id');
                selPerfil.innerHTML = '<option value="">Seleccione un perfil</option>';
                selPerfil.disabled = true;
                // limpiar selects de área y actividad curricular, y campos dominio/competencia
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
                // actualizar resúmenes
                document.querySelectorAll('#filas-container .accordion-item').forEach(item => actualizarResumenFila(item));
                poblarSelectsConAtributos();
                return;
            }
            // Poblar asignaturas locales por carrera
            atributosCache.asignaturas = <?php echo json_encode($asignaturasTodas); ?>.filter(a => String(a.carrera_id) === String(carreraId));
            // Resetear perfil mientras carga, y limpiar áreas/dominios/competencias y actividad curricular
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
            // Cargar anexos dependientes de carrera (perfiles, versiones)
            fetch(`../../src/api/atributos.php?carrera_id=${carreraId}`)
                .then(r => r.json())
                .then(data => {
                    atributosCache.perfiles = data.perfiles || [];
                    atributosCache.versiones = data.versiones || [];
                    atributosCache.resultados = [];
                    // Poblar select de perfil
                    const selPerfil = document.getElementById('perfil_id');
                    selPerfil.innerHTML = optionMarkupId(atributosCache.perfiles, 'Seleccione un perfil');
                    selPerfil.disabled = atributosCache.perfiles.length === 0;
                    atributosCache.areasPorPerfil = [];
                    // Deshabilitar áreas en filas hasta seleccionar perfil
                    document.querySelectorAll('.campo-area').forEach(sel => {
                        sel.disabled = true;
                        sel.innerHTML = '<option value="">Seleccione un área</option>';
                    });
                    // Poblar actividades curriculares en filas
                    poblarSelectsConAtributos();
                })
                .catch(err => console.error('Error cargar anexos:', err));
        }

        function cargarAtributos() {
            /* selector superior de asignatura eliminado */
        }

        function cargarAreasPorPerfil() {
            const perfilId = document.getElementById('perfil_id').value;
            atributosCache.areasPorPerfil = [];
            document.querySelectorAll('.campo-area').forEach(sel => {
                sel.disabled = true;
                sel.innerHTML = '<option value="">Seleccione un área</option>';
            });
            // Limpiar dominio/competencia en todas las filas para evitar datos obsoletos
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

        function onAreaChange(selectEl) {
            const areaId = selectEl.value;
            const perfilId = document.getElementById('perfil_id').value;
            const item = selectEl.closest('.accordion-item');
            const txtDom = item.querySelector('.campo-dominio');
            const txtComp = item.querySelector('.campo-competencia');
            if (!areaId || !perfilId) {
                txtDom.value = '';
                txtComp.value = '';
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
                    actualizarResumenFila(item);
                })
                .catch(err => console.error('Error cargar detalle perfil/área:', err));
        }

        // Inicializar con una fila al cargar
        document.addEventListener('DOMContentLoaded', () => {
            agregarFila();
        });
    </script>
</body>

</html>