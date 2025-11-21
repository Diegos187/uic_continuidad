/**
 * Validador de Estructura de Campos Curriculares (Cliente)
 * Valida en tiempo real la estructura de los campos mientras se escriben
 */

const ValidadorEstructura = {
    // Verbos en infinitivo comunes
    verbosInfinitivo: [
        'identificar', 'analizar', 'sintetizar', 'evaluar', 'aplicar', 'crear',
        'diseñar', 'elaborar', 'desarrollar', 'resolver', 'interpretar', 'comprender',
        'describir', 'explicar', 'demostrar', 'ilustrar', 'calcular', 'medir',
        'clasificar', 'comparar', 'contrastar', 'diferenciar', 'relacionar', 'integrar',
        'argumentar', 'justificar', 'criticar', 'valorar', 'juzgar', 'apreciar',
        'reconocer', 'recordar', 'memorizar', 'retener', 'reproducir', 'repetir',
        'aplicar', 'usar', 'emplear', 'ejecutar', 'practicar', 'entrenar',
        'combinar', 'organizar', 'estructurar', 'reconfigurar', 'planificar', 'proyectar',
        'generar', 'inventar', 'idear', 'producir', 'construir', 'fabricar',
        'componer', 'formular', 'redactar', 'escribir', 'comunicar', 'expresar',
        'interpretar', 'traducir', 'transformar', 'adaptar', 'modificar', 'alterar',
        'seleccionar', 'elegir', 'optar', 'escoger', 'determinar', 'decidir',
        'proponer', 'sugerir', 'recomendar', 'aconsejar', 'indicar', 'señalar',
        'verificar', 'comprobar', 'confirmar', 'validar', 'contrastar', 'revisar',
        'reflexionar', 'meditar', 'considerar', 'pensar', 'razonar', 'deducir',
        'inferir', 'inducir', 'extrapolar', 'generalizar', 'particularizar', 'especificar'
    ],

    // Palabras de contexto/condición
    palabrasContexto: [
        'mediante', 'a través', 'por medio', 'utilizando', 'empleando', 'haciendo uso',
        'en', 'dentro', 'bajo', 'durante', 'ante', 'frente', 'con el fin',
        'para', 'a fin', 'con el propósito', 'con la finalidad', 'buscando',
        'considerando', 'tomando en cuenta', 'teniendo en cuenta', 'de acuerdo',
        'según', 'conforme', 'en base', 'basándose', 'partiendo',
        'cuando', 'si', 'una vez', 'después', 'antes', 'mientras',
        'dado', 'establecido', 'definido', 'caracterizado', 'especificado',
        'en el contexto', 'en el marco', 'dentro del ámbito', 'en el ámbito'
    ],

    /**
     * Validar Resultado de Aprendizaje
     */
    validarResultadoAprendizaje: function(texto) {
        const resultado = {
            valido: false,
            errores: [],
            advertencias: [],
            componentes: {
                verbo: false,
                contenido: false,
                contexto: false
            }
        };

        if (!texto || texto.trim().length === 0) {
            resultado.errores.push('El campo no puede estar vacío');
            return resultado;
        }

        const textoBajo = texto.toLowerCase();
        const primeraPalabra = textoBajo.split(' ')[0].replace(/[.,;:]/g, '');

        // Validar verbo
        if (this.verbosInfinitivo.includes(primeraPalabra)) {
            resultado.componentes.verbo = true;
        } else {
            resultado.errores.push(`No comienza con un verbo en infinitivo. Detectado: "${primeraPalabra}"`);
        }

        // Validar contenido
        const palabras = texto.trim().split(/\s+/);
        if (palabras.length >= 2) {
            resultado.componentes.contenido = true;
        } else {
            resultado.errores.push('Falta contenido/objeto. Especifica QUÉ aprenderá el estudiante.');
        }

        // Validar contexto
        const tieneContexto = this.palabrasContexto.some(palabra => 
            textoBajo.includes(palabra)
        );

        if (tieneContexto) {
            resultado.componentes.contexto = true;
        } else {
            resultado.advertencias.push('Se recomienda añadir el CONTEXTO/CONDICIÓN (mediante, utilizando, en, durante, etc.)');
        }

        resultado.valido = resultado.componentes.verbo && resultado.componentes.contenido;

        return resultado;
    },

    /**
     * Crear elemento visual de alerta
     */
    crearAlerta: function(elementoId, validacion) {
        const contenedor = document.getElementById(elementoId);
        if (!contenedor) return;

        // Limpiar alertas anteriores
        const alertasExistentes = contenedor.parentElement.querySelector('.campo-alerta');
        if (alertasExistentes) {
            alertasExistentes.remove();
        }

        if (validacion.errores.length === 0 && validacion.advertencias.length === 0) {
            // Sin problemas, sin alerta
            return;
        }

        const div = document.createElement('div');
        div.className = 'campo-alerta mt-2';

        let html = '';

        if (validacion.errores.length > 0) {
            div.classList.add('alerta-error');
            html += '<div class="alerta-titulo">⚠️ Errores detectados:</div>';
            html += '<ul class="alerta-lista">';
            validacion.errores.forEach(error => {
                html += `<li>${error}</li>`;
            });
            html += '</ul>';
        } else if (validacion.advertencias.length > 0) {
            div.classList.add('alerta-advertencia');
            html += '<div class="alerta-titulo">💡 Sugerencias:</div>';
            html += '<ul class="alerta-lista">';
            validacion.advertencias.forEach(adv => {
                html += `<li>${adv}</li>`;
            });
            html += '</ul>';
        }

        div.innerHTML = html;
        contenedor.parentElement.appendChild(div);
    },

    /**
     * Inicializar validación para un campo
     */
    inicializarCampo: function(elementoId, tipo = 'resultado') {
        const campo = document.getElementById(elementoId);
        if (!campo) return;

        campo.addEventListener('blur', () => {
            let validacion;
            if (tipo === 'resultado') {
                validacion = this.validarResultadoAprendizaje(campo.value);
            }
            this.crearAlerta(elementoId, validacion);
        });

        // Validación en tiempo real (después de 1 segundo de inactividad)
        let timeout;
        campo.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                let validacion;
                if (tipo === 'resultado') {
                    validacion = this.validarResultadoAprendizaje(campo.value);
                }
                this.crearAlerta(elementoId, validacion);
            }, 1000);
        });
    }
};

// Estilos para las alertas
const estilosAlerta = `
    .campo-alerta {
        padding: 1rem;
        border-radius: 0.5rem;
        border-left: 4px solid;
        background-color: #f8f9fa;
        font-size: 0.9rem;
        line-height: 1.6;
    }

    .campo-alerta.alerta-error {
        border-left-color: #dc3545;
        background-color: #fff5f5;
        color: #721c24;
    }

    .campo-alerta.alerta-error .alerta-titulo {
        color: #dc3545;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .campo-alerta.alerta-advertencia {
        border-left-color: #ffc107;
        background-color: #fffbf0;
        color: #856404;
    }

    .campo-alerta.alerta-advertencia .alerta-titulo {
        color: #ff9800;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .campo-alerta.alerta-exito {
        border-left-color: #28a745;
        background-color: #f0fff4;
        color: #155724;
    }

    .campo-alerta.alerta-exito .alerta-titulo {
        color: #28a745;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .alerta-lista {
        margin: 0;
        padding-left: 1.5rem;
        list-style-type: disc;
    }

    .alerta-lista li {
        margin-bottom: 0.3rem;
    }

    .alerta-lista li:last-child {
        margin-bottom: 0;
    }
`;

// Inyectar estilos en el documento
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        const style = document.createElement('style');
        style.textContent = estilosAlerta;
        document.head.appendChild(style);
    });
} else {
    const style = document.createElement('style');
    style.textContent = estilosAlerta;
    document.head.appendChild(style);
}
