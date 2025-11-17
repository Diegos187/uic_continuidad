<?php
session_start();
require_once '../../config/database.php';
require_once '../../src/models/Carrera.php';
require_once '../../src/models/PerfilEgreso.php';
require_once '../../src/models/PerfilEgresoDetalle.php';
require_once '../../src/models/AreaFormacion.php';
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

$carrera = $carreraModel->obtenerPorId($carreraId);
if (!$carrera) {
    redirigir('carreras.php');
}

// Cargar todas las áreas globales para permitir reutilización inmediata; la asociación a carrera se hace al guardar
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
        // Validar que haya al menos una fila válida y que cada fila tenga área de formación
        $filasValidas = [];
        $errorArea = false;
        $errorCampos = false;
        foreach ($filas as $f) {
            $dom = isset($f['dominio']) ? trim($f['dominio']) : '';
            $comp = isset($f['competencia']) ? trim($f['competencia']) : '';
            $areaId = isset($f['area_formacion_id']) ? (int)$f['area_formacion_id'] : 0;

            // Si hay contenido en al menos uno de los campos (dominio o competencia)
            if ($dom !== '' || $comp !== '') {
                // Validar que AMBOS campos estén llenos
                if ($dom === '' || $comp === '') {
                    $errorCampos = true;
                    break;
                }

                // Validar que haya área seleccionada
                if ($areaId <= 0) {
                    $errorArea = true;
                    break;
                }

                $filasValidas[] = [
                    'area_formacion_id' => $areaId > 0 ? $areaId : null,
                    'dominio' => $dom,
                    'competencia' => $comp,
                ];
            }
        }

        if ($errorCampos) {
            $error = 'Los campos Dominio y Competencias son obligatorios en cada fila.';
        } elseif ($errorArea) {
            $error = 'Debe seleccionar un área de formación en cada fila ingresada.';
        } elseif (empty($filasValidas)) {
            $error = 'Debe completar al menos una fila (Dominio y Competencia).';
        } else {
            try {
                $conn->beginTransaction();
                // Guardar el perfil con el nombre ingresado en la columna descripcion
                $perfilId = $perfilModel->crear($carreraId, $nombrePerfil);
                if (!$perfilId) {
                    throw new Exception('No se pudo crear el perfil de egreso.');
                }
                // Guardar detalles
                $detalleModel->crearMultiple($perfilId, $filasValidas);

                // Asociar áreas seleccionadas a la carrera si no están asociadas
                $areasUsadas = array_unique(array_values(array_filter(array_map(function ($f) {
                    return isset($f['area_formacion_id']) ? (int)$f['area_formacion_id'] : 0;
                }, $filasValidas))));
                foreach ($areasUsadas as $aid) {
                    if ($aid > 0) {
                        $areaModel->asociarACarrera($carreraId, $aid);
                    }
                }
                $conn->commit();

                // Si es AJAX, devolver JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Perfil de egreso creado correctamente', 'redirect' => 'perfiles_egreso.php?carrera_id=' . $carreraId]);
                    exit;
                }
                // Si no es AJAX, redirigir
                header('Location: perfiles_egreso.php?carrera_id=' . $carreraId . '&success=1');
                exit;
            } catch (Exception $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $error = 'Error al guardar: ' . $e->getMessage();
                // Si es AJAX, devolver error en JSON
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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Agregar Perfil de Egreso - UTEM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>

<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2>Nuevo Perfil de egreso <strong><?php echo htmlspecialchars($carrera['nombre']); ?></strong></h2>
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
                                <input type="text" class="form-control" name="nombre_perfil" required />
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="m-0">Detalles (mínimo una fila)</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="agregarFila()">Agregar fila</button>
                            </div>

                            <div class="accordion" id="filas-container"></div>

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
                        Fila ${index + 1} <span class="ms-2 text-muted resumen-fila"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headerId}" data-bs-parent="#filas-container">
                    <div class="accordion-body pt-3">
                        <div class="mb-3">
                            <label class="form-label">Área de formación</label>
                            <select class="form-select campo-area" name="filas[${index}][area_formacion_id]" required>
                                ${optionAreas()}
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Dominio <span class="text-danger">*</span></label>
                            <textarea class="form-control campo-dominio textarea-resize" name="filas[${index}][dominio]" rows="3" placeholder="Escriba el dominio"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Competencias <span class="text-danger">*</span></label>
                            <textarea class="form-control campo-competencia textarea-resize" name="filas[${index}][competencia]" rows="3" placeholder="Escriba las competencias"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="colapsarFila(${index})">Colapsar</button>
                            <button type="button" class="btn btn-outline-danger" onclick="eliminarFila(${index})">Eliminar fila</button>
                        </div>
                    </div>
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

            // Obtener todos los collapses y sus botones
            const allCollapses = Array.from(cont.querySelectorAll('.accordion-collapse'));
            const allButtons = Array.from(cont.querySelectorAll('.accordion-button'));

            // Colapsar todos los demás (con transición)
            allCollapses.forEach((c, idx) => {
                if (c !== node.querySelector('.accordion-collapse') && c.classList.contains('show')) {
                    c.classList.remove('show');
                    if (allButtons[idx]) {
                        allButtons[idx].classList.add('collapsed');
                        allButtons[idx].setAttribute('aria-expanded', 'false');
                    }
                }
            });

            // Expandir la nueva fila después de la transición de cierre
            setTimeout(() => {
                const newCollapse = node.querySelector('.accordion-collapse');
                const newButton = node.querySelector('.accordion-button');
                newCollapse.classList.add('show');
                newButton.classList.remove('collapsed');
                newButton.setAttribute('aria-expanded', 'true');
                // Scroll suave hacia la nueva fila
                node.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }, 100);

            // Listeners para resumen
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

        // Inserta una fila por defecto
        document.addEventListener('DOMContentLoaded', agregarFila);

        function reindexFilas() {
            const cont = document.getElementById('filas-container');
            const items = Array.from(cont.querySelectorAll('.accordion-item'));
            items.forEach((item, i) => {
                // Actualizar data-index
                item.setAttribute('data-index', String(i));

                // Actualizar IDs y atributos de acordeón
                const newHeaderId = `filaHeader_${i}`;
                const newBodyId = `filaBody_${i}`;
                const header = item.querySelector('.accordion-header');
                const button = item.querySelector('.accordion-button');
                const collapse = item.querySelector('.accordion-collapse');
                if (header) header.id = newHeaderId;
                if (button) {
                    button.setAttribute('data-bs-target', `#${newBodyId}`);
                    button.setAttribute('aria-controls', newBodyId);
                    // Actualizar texto "Fila N"
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

                // Actualizar names de campos
                const area = item.querySelector('.campo-area');
                const dom = item.querySelector('.campo-dominio');
                const comp = item.querySelector('.campo-competencia');
                if (area) area.name = `filas[${i}][area_formacion_id]`;
                if (dom) dom.name = `filas[${i}][dominio]`;
                if (comp) comp.name = `filas[${i}][competencia]`;

                // Actualizar botones inline
                const btnCollapse = item.querySelector('button.btn-outline-secondary');
                const btnDelete = item.querySelector('button.btn-outline-danger');
                if (btnCollapse) btnCollapse.setAttribute('onclick', `colapsarFila(${i})`);
                if (btnDelete) btnDelete.setAttribute('onclick', `eliminarFila(${i})`);
            });
        }
    </script>

    <style>
        .textarea-resize {
            resize: vertical;
            min-height: 80px;
        }

        /* Animación suave para accordion con transición de altura */
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
    </style>

    <!-- Toast container -->
    <div id="toast-container" class="toast-container"></div>

    <script>
        function mostrarToastExito(mensaje = 'Perfil de egreso creado correctamente', duracion = 1500) {
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

        // Interceptar envío del formulario
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();

                    // Validar que haya al menos una fila
                    const filasContainer = document.getElementById('filas-container');
                    const filasItems = filasContainer.querySelectorAll('.accordion-item');
                    if (filasItems.length === 0) {
                        mostrarToastError('Debe completar al menos una fila');
                        return;
                    }

                    // Validar que cada fila tenga dominio y competencia (ambos obligatorios)
                    let filasValidas = 0;
                    for (let item of filasItems) {
                        const dominio = item.querySelector('.campo-dominio')?.value.trim() || '';
                        const competencia = item.querySelector('.campo-competencia')?.value.trim() || '';
                        const area = item.querySelector('.campo-area')?.value || '';

                        // Si hay contenido en al menos un campo
                        if (dominio || competencia) {
                            // Ambos campos son obligatorios
                            if (!dominio || !competencia) {
                                mostrarToastError('Los campos Dominio y Competencias son obligatorios en cada fila');
                                return;
                            }

                            if (!area) {
                                mostrarToastError('Debe seleccionar un área de formación en cada fila ingresada');
                                return;
                            }
                            filasValidas++;
                        }
                    }

                    if (filasValidas === 0) {
                        mostrarToastError('Debe completar al menos una fila con dominio y competencia');
                        return;
                    }

                    // Enviar por AJAX
                    const formData = new FormData(form);
                    fetch(form.action || '', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                return response.json().then(data => Promise.reject(data));
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                mostrarToastExito(data.message, 1500);
                                // Redirigir después de 1.5 segundos
                                setTimeout(() => {
                                    window.location.href = data.redirect;
                                }, 1500);
                            } else {
                                mostrarToastError(data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            mostrarToastError(error.message || 'Error al guardar el perfil');
                        });
                });
            }
        });

        // Mostrar toast si viene del éxito (fallback para navegación no-AJAX)
        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            if (params.has('success')) {
                mostrarToastExito();
            }
        });
    </script>
</body>

</html>