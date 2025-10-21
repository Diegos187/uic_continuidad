<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/MatrizCoherencia.php';
require_once '../../src/models/Asignatura.php';
require_once '../../src/models/Carrera.php';
require_once '../../includes/functions.php';

verificarSesion();

$db = new Database();
$conexion = $db->conectar();
$asignatura = new Asignatura($conexion);
$carrera = new Carrera($conexion);
$matriz = new MatrizCoherencia($conexion);

$error = '';
$success = '';

// Obtener listas base
$carreras = $carrera->obtenerTodas();
$asignaturasTodas = $asignatura->obtenerTodas();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asignatura_id = isset($_POST['asignatura_id']) ? (int)limpiarDatos($_POST['asignatura_id']) : null;
    $filasPost = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if (!$asignatura_id) {
        $error = 'Debe seleccionar una carrera.';
    } elseif (empty($filasPost)) {
        $error = 'Debe agregar al menos una fila a la matriz.';
    } else {
        $filas = [];
        foreach ($filasPost as $fila) {
            // Saltar filas completamente vacías
            $valores = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $fila);
            $todosVacios = true;
            foreach (['dominio', 'competencia', 'resultado_aprendizaje', 'actividad_curricular', 'criterios_logro', 'contenidos', 'bibliografia', 'metodologias', 'estrategias', 'sct_chile'] as $k) {
                if (!empty($valores[$k])) {
                    $todosVacios = false;
                    break;
                }
            }
            if ($todosVacios) {
                continue;
            }

            $filas[] = [
                'area_formacion_id' => isset($fila['area_formacion_id']) ? limpiarDatos($fila['area_formacion_id']) : null,
                'perfil_egreso_id' => isset($fila['perfil_egreso_id']) ? limpiarDatos($fila['perfil_egreso_id']) : null,
                'version_id' => isset($fila['version_id']) ? limpiarDatos($fila['version_id']) : null,
                'dominio' => isset($fila['dominio']) ? limpiarDatos($fila['dominio']) : null,
                'competencia' => isset($fila['competencia']) ? limpiarDatos($fila['competencia']) : null,
                'resultado_aprendizaje' => isset($fila['resultado_aprendizaje']) ? limpiarDatos($fila['resultado_aprendizaje']) : null,
                'actividad_curricular' => isset($fila['actividad_curricular']) ? limpiarDatos($fila['actividad_curricular']) : null,
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
            $ids = $matriz->crearMultiple($asignatura_id, $filas);
            if ($ids && is_array($ids)) {
                $success = count($ids) . ' fila(s) de la matriz creadas exitosamente.';
            } else {
                $error = 'Error al crear la(s) fila(s) de la matriz.';
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
                                    <label for="asignatura_id" class="form-label">Asignatura</label>
                                    <select class="form-select" id="asignatura_id" name="asignatura_id" required onchange="cargarAtributos()" disabled>
                                        <option value="">Seleccione una asignatura</option>
                                    </select>
                                </div>
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
            dominios: [],
            competencias: [],
            resultados: [],
            areas: [],
            perfiles: [],
            versiones: []
        };

        function optionMarkupLabel(lista, placeholder) {
            const opts = [`<option value="">${placeholder}</option>`];
            lista.forEach(item => {
                const label = (item.descripcion ?? '').toString();
                opts.push(`<option value="${label.replace(/"/g, '&quot;')}">${label.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</option>`);
            });
            return opts.join('');
        }

        function optionMarkupId(lista, placeholder) {
            const opts = [`<option value="">${placeholder}</option>`];
            lista.forEach(item => {
                const id = String(item.id ?? '');
                const label = (item.descripcion ?? '').toString();
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
                                <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" >
                                    ${optionMarkupId(atributosCache.areas, 'Seleccione área (opcional)')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Perfil de egreso</label>
                                <select class="form-select campo-perfil" name="filas[${index}][perfil_egreso_id]" >
                                    ${optionMarkupId(atributosCache.perfiles, 'Seleccione perfil (opcional)')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Versión de matriz</label>
                                <select class="form-select campo-version" name="filas[${index}][version_id]" >
                                    ${optionMarkupId(atributosCache.versiones, 'Seleccione versión (opcional)')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dominio</label>
                                <select class="form-select campo-dominio" name="filas[${index}][dominio]" required disabled>
                                    ${optionMarkupLabel(atributosCache.dominios, 'Seleccione un dominio')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Competencia</label>
                                <select class="form-select campo-competencia" name="filas[${index}][competencia]" required disabled>
                                    ${optionMarkupLabel(atributosCache.competencias, 'Seleccione una competencia')}
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Resultado de Aprendizaje</label>
                                <select class="form-select campo-resultado" name="filas[${index}][resultado_aprendizaje]" required disabled>
                                    ${optionMarkupLabel(atributosCache.resultados, 'Seleccione un resultado de aprendizaje')}
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Actividad Curricular</label>
                            <input type="text" class="form-control" name="filas[${index}][actividad_curricular]" placeholder="Ej: Clase magistral, Taller, Laboratorio" />
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
            const dom = item.querySelector('.campo-dominio')?.value || '';
            const comp = item.querySelector('.campo-competencia')?.value || '';
            const ra = item.querySelector('.campo-resultado')?.value || '';
            const resumen = item.querySelector('.resumen-fila');
            const partes = [dom, comp, ra].filter(Boolean).slice(0, 3);
            resumen.textContent = partes.length ? `— ${partes.join(' | ')}` : '';
        }

        function habilitarSelectsFila(item) {
            const selects = item.querySelectorAll('.campo-dominio, .campo-competencia, .campo-resultado');
            const asignaturaId = document.getElementById('asignatura_id').value;
            if (asignaturaId) {
                selects.forEach(s => s.disabled = false);
            } else {
                selects.forEach(s => s.disabled = true);
            }

            // Actualizar resumen al cambiar valores
            selects.forEach(s => s.addEventListener('change', () => actualizarResumenFila(item)));
            const textos = item.querySelectorAll('input, textarea');
            textos.forEach(t => t.addEventListener('blur', () => actualizarResumenFila(item)));
        }

        function poblarSelectsConAtributos() {
            const items = document.querySelectorAll('#filas-container .accordion-item');
            items.forEach(item => {
                const selDom = item.querySelector('.campo-dominio');
                const selComp = item.querySelector('.campo-competencia');
                const selRA = item.querySelector('.campo-resultado');
                const prevDom = selDom.value;
                const prevComp = selComp.value;
                const prevRA = selRA.value;
                selDom.innerHTML = optionMarkupLabel(atributosCache.dominios, 'Seleccione un dominio');
                selComp.innerHTML = optionMarkupLabel(atributosCache.competencias, 'Seleccione una competencia');
                selRA.innerHTML = optionMarkupLabel(atributosCache.resultados, 'Seleccione un resultado de aprendizaje');
                selDom.disabled = selComp.disabled = selRA.disabled = false;
                // Intentar restaurar selección previa
                if (prevDom) selDom.value = prevDom;
                if (prevComp) selComp.value = prevComp;
                if (prevRA) selRA.value = prevRA;
            });
        }

        function cargarAsignaturasYAnexos() {
            const carreraId = document.getElementById('carrera_id').value;
            const selAsig = document.getElementById('asignatura_id');
            selAsig.innerHTML = '<option value="">Seleccione una asignatura</option>';
            selAsig.disabled = !carreraId;
            if (!carreraId) {
                atributosCache = {
                    dominios: [],
                    competencias: [],
                    resultados: [],
                    areas: [],
                    perfiles: [],
                    versiones: []
                };
                poblarSelectsConAtributos();
                return;
            }
            // Poblar asignaturas locales ya cargadas en PHP
            const asignaturas = <?php echo json_encode($asignaturasTodas); ?>.filter(a => String(a.carrera_id) === String(carreraId));
            asignaturas.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre;
                selAsig.appendChild(opt);
            });
            // Cargar anexos dependientes de carrera (áreas, perfiles, versiones, dominios, competencias, resultados)
            fetch(`../../src/api/atributos.php?carrera_id=${carreraId}`)
                .then(r => r.json())
                .then(data => {
                    atributosCache = data || {
                        dominios: [],
                        competencias: [],
                        resultados: [],
                        areas: [],
                        perfiles: [],
                        versiones: []
                    };
                    poblarSelectsConAtributos();
                })
                .catch(err => console.error('Error cargar anexos:', err));
        }

        function cargarAtributos() {
            const asignaturaId = document.getElementById('asignatura_id').value;
            if (!asignaturaId) {
                // no reset de áreas/perfiles/versiones al cambiar asignatura
                poblarSelectsConAtributos();
                return;
            }
            fetch(`../../src/api/atributos.php?asignatura_id=${asignaturaId}`)
                .then(response => response.json())
                .then(data => {
                    atributosCache.dominios = data.dominios || [];
                    atributosCache.competencias = data.competencias || [];
                    atributosCache.resultados = data.resultados || [];
                    poblarSelectsConAtributos();
                })
                .catch(error => {
                    console.error('Error al cargar los atributos:', error);
                });
        }

        // Inicializar con una fila al cargar
        document.addEventListener('DOMContentLoaded', () => {
            agregarFila();
        });
    </script>
</body>

</html>