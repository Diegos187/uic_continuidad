<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/PerfilEgreso.php';
require_once '../../src/models/PerfilEgresoDetalle.php';
require_once '../../src/models/AreaFormacion.php';
require_once '../../includes/functions.php';

verificarSesion();

$perfilId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$carreraId = isset($_GET['carrera_id']) ? (int)$_GET['carrera_id'] : 0;
if (!$perfilId || !$carreraId) {
    header('Location: carreras.php');
    exit;
}

$db = new Database();
$conn = $db->conectar();
$carreraModel = new Carrera($conn);
$perfilModel = new PerfilEgreso($conn);
$detalleModel = new PerfilEgresoDetalle($conn);
$areaModel = new AreaFormacion($conn);

$carrera = $carreraModel->obtenerPorId($carreraId);
$perfil = $perfilModel->obtenerPorId($perfilId);
if (!$carrera || !$perfil || (int)$perfil['carrera_id'] !== $carreraId) {
    header('Location: carreras.php');
    exit;
}

// Áreas disponibles: primero las asociadas a la carrera; si no hay, todas.
try {
    $areas = $areaModel->listarPorCarrera($carreraId);
    if (!$areas || count($areas) === 0) {
        $areas = $areaModel->obtenerTodas();
    }
} catch (Exception $e) {
    $areas = [];
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombrePerfil = isset($_POST['nombre_perfil']) ? trim($_POST['nombre_perfil']) : '';
    $filas = isset($_POST['filas']) && is_array($_POST['filas']) ? $_POST['filas'] : [];

    if ($nombrePerfil === '') {
        $error = 'Debe ingresar un nombre (versión) para el perfil de egreso.';
    } else {
        $filasValidas = [];
        $errorArea = false;
        foreach ($filas as $f) {
            $dom = isset($f['dominio']) ? trim($f['dominio']) : '';
            $comp = isset($f['competencia']) ? trim($f['competencia']) : '';
            $areaId = isset($f['area_formacion_id']) ? (int)$f['area_formacion_id'] : 0;
            if ($dom !== '' || $comp !== '') {
                if ($areaId <= 0) {
                    $errorArea = true;
                }
                $filasValidas[] = [
                    'area_formacion_id' => $areaId > 0 ? $areaId : null,
                    'dominio' => $dom,
                    'competencia' => $comp,
                ];
            }
        }

        if ($errorArea) {
            $error = 'Debe seleccionar un área de formación en cada fila ingresada.';
        } elseif (empty($filasValidas)) {
            $error = 'Debe completar al menos una fila (Dominio o Competencia).';
        } else {
            try {
                $conn->beginTransaction();
                // Actualizar perfil (descripcion = nombre versión)
                $perfilModel->actualizar($perfilId, $nombrePerfil);
                // Reemplazar detalles: borrar e insertar
                $detalleModel->borrarPorPerfil($perfilId);
                $detalleModel->crearMultiple($perfilId, $filasValidas);

                // Asociar áreas a la carrera (evitar duplicados)
                $areasUsadas = array_unique(array_values(array_filter(array_map(function ($f) {
                    return isset($f['area_formacion_id']) ? (int)$f['area_formacion_id'] : 0;
                }, $filasValidas))));
                foreach ($areasUsadas as $aid) {
                    if ($aid > 0) {
                        $areaModel->asociarACarrera($carreraId, $aid);
                    }
                }

                $conn->commit();
                header('Location: perfiles_egreso.php?carrera_id=' . $carreraId);
                exit;
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    }
}

// Cargar filas existentes para pintar el formulario inicial si no hay POST válido
$detalles = $detalleModel->listarPorPerfil($perfilId);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Editar Perfil de Egreso - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>Editar Perfil de egreso (<?php echo htmlspecialchars($carrera['nombre']); ?>)</h2>
                        <a class="btn btn-secondary" href="perfiles_egreso.php?carrera_id=<?php echo $carreraId; ?>">Volver</a>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($error)) echo mostrarMensaje($error, 'error');
                        ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Nombre del perfil de egreso (versión)</label>
                                <input type="text" class="form-control" name="nombre_perfil" value="<?php echo htmlspecialchars($perfil['descripcion']); ?>" required />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Detalles</h5>
                                <button type="button" class="btn btn-sm btn-success" onclick="agregarFila()">+ Agregar fila</button>
                            </div>

                            <div class="accordion" id="filas-container"></div>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let filaCounter = 0;
        const AREAS = <?php echo json_encode($areas); ?>;
        const DETALLES = <?php echo json_encode($detalles); ?>;

        function optionAreas(selectedId = '') {
            const opts = ["<option value='' disabled selected>Seleccione un área</option>"];
            AREAS.forEach(a => {
                const sel = String(selectedId) === String(a.id) ? 'selected' : '';
                opts.push(`<option value='${a.id}' ${sel}>${a.nombre.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</option>`);
            });
            return opts.join('');
        }

        function plantillaFila(index, preset = null) {
            const collapseId = `filaBody_${index}`;
            const headerId = `filaHeader_${index}`;
            const selArea = preset ? preset.area_formacion_id : '';
            const dom = preset ? (preset.dominio || '') : '';
            const comp = preset ? (preset.competencia || '') : '';
            return `
            <div class="accordion-item" data-index="${index}">
                <h2 class="accordion-header" id="${headerId}">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
                        Fila ${index + 1} <span class="ms-2 text-muted resumen-fila"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headerId}" data-bs-parent="#filas-container">
                    <div class="accordion-body pt-3">
                        <div class="mb-3">
                            <label class="form-label">Área de formación</label>
                            <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" required>
                                ${optionAreas(selArea)}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dominio</label>
                            <textarea class="form-control campo-dominio textarea-resize" name="filas[${index}][dominio]" rows="3" placeholder="Escriba el dominio">${dom.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Competencias</label>
                            <textarea class="form-control campo-competencia textarea-resize" name="filas[${index}][competencia]" rows="3" placeholder="Escriba las competencias">${comp.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="colapsarFila(${index})">Colapsar</button>
                            <button type="button" class="btn btn-outline-danger" onclick="eliminarFila(${index})">Eliminar fila</button>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function agregarFila(preset = null) {
            const cont = document.getElementById('filas-container');
            const html = plantillaFila(filaCounter, preset);
            const tmp = document.createElement('div');
            tmp.innerHTML = html.trim();
            const node = tmp.firstChild;
            cont.appendChild(node);

            cont.querySelectorAll('.accordion-collapse').forEach(c => c.classList.remove('show'));
            node.querySelector('.accordion-collapse').classList.add('show');

            habilitarResumenFila(node);
            filaCounter++;
            reindexFilas();
        }

        function eliminarFila(index) {
            const node = document.querySelector(`[data-index='${index}']`);
            if (node) node.remove();
            reindexFilas();
        }

        function colapsarFila(index) {
            const sel = `#filas-container .accordion-item[data-index='${index}']`;
            const item = document.querySelector(sel);
            if (!item) return;
            actualizarResumenFila(item);
            const body = item.querySelector('.accordion-collapse');
            body.classList.remove('show');
        }

        function habilitarResumenFila(item) {
            const selects = item.querySelectorAll('.campo-area');
            const textos = item.querySelectorAll('.campo-dominio, .campo-competencia');
            selects.forEach(s => s.addEventListener('change', () => actualizarResumenFila(item)));
            textos.forEach(t => t.addEventListener('blur', () => actualizarResumenFila(item)));
            actualizarResumenFila(item);
        }

        function actualizarResumenFila(item) {
            const areaSel = item.querySelector('.campo-area');
            const areaTxt = areaSel ? (areaSel.options[areaSel.selectedIndex]?.text || '').trim() : '';
            const dom = (item.querySelector('.campo-dominio')?.value || '').trim();
            const comp = (item.querySelector('.campo-competencia')?.value || '').trim();
            const resumen = item.querySelector('.resumen-fila');
            const partes = [];
            if (areaTxt) partes.push(areaTxt);
            if (dom) partes.push(dom.substring(0, 50) + (dom.length > 50 ? '…' : ''));
            if (!partes.length && comp) partes.push(comp.substring(0, 50) + (comp.length > 50 ? '…' : ''));
            resumen.textContent = partes.length ? `— ${partes.join(' | ')}` : '';
        }

        function reindexFilas() {
            const cont = document.getElementById('filas-container');
            const items = Array.from(cont.querySelectorAll('.accordion-item'));
            items.forEach((item, i) => {
                item.setAttribute('data-index', String(i));
                const newHeaderId = `filaHeader_${i}`;
                const newBodyId = `filaBody_${i}`;
                const header = item.querySelector('.accordion-header');
                const button = item.querySelector('.accordion-button');
                const collapse = item.querySelector('.accordion-collapse');
                if (header) header.id = newHeaderId;
                if (button) {
                    button.setAttribute('data-bs-target', `#${newBodyId}`);
                    button.setAttribute('aria-controls', newBodyId);
                    const firstTextNode = button.firstChild;
                    if (firstTextNode && firstTextNode.nodeType === Node.TEXT_NODE) {
                        firstTextNode.textContent = `Fila ${i + 1} `;
                    } else {
                        button.insertBefore(document.createTextNode(`Fila ${i + 1} `), button.firstChild);
                    }
                }
                if (collapse) {
                    collapse.id = newBodyId;
                    collapse.setAttribute('aria-labelledby', newHeaderId);
                }
                const area = item.querySelector('.campo-area');
                const dom = item.querySelector('.campo-dominio');
                const comp = item.querySelector('.campo-competencia');
                if (area) area.name = `filas[${i}][area_formacion_id]`;
                if (dom) dom.name = `filas[${i}][dominio]`;
                if (comp) comp.name = `filas[${i}][competencia]`;
                const btnCollapse = item.querySelector('button.btn-outline-secondary');
                const btnDelete = item.querySelector('button.btn-outline-danger');
                if (btnCollapse) btnCollapse.setAttribute('onclick', `colapsarFila(${i})`);
                if (btnDelete) btnDelete.setAttribute('onclick', `eliminarFila(${i})`);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (Array.isArray(DETALLES) && DETALLES.length) {
                DETALLES.forEach(d => agregarFila(d));
            } else {
                agregarFila();
            }
        });
    </script>

    <style>
        .textarea-resize {
            resize: vertical;
            min-height: 80px;
        }
    </style>
</body>

</html>