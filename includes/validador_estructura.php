<?php

/**
 * Validador de Estructura de Campos Curriculares
 * Valida que los campos sigan las estructuras requeridas
 */

class ValidadorEstructura
{
    /**
     * Verbos en infinitivo comunes en educación
     */
    private static $verbosInfinitivo = [
        'identificar',
        'analizar',
        'sintetizar',
        'evaluar',
        'aplicar',
        'crear',
        'diseñar',
        'elaborar',
        'desarrollar',
        'resolver',
        'interpretar',
        'comprender',
        'describir',
        'explicar',
        'demostrar',
        'ilustrar',
        'calcular',
        'medir',
        'clasificar',
        'comparar',
        'contrastar',
        'diferenciar',
        'relacionar',
        'integrar',
        'argumentar',
        'justificar',
        'criticar',
        'valorar',
        'juzgar',
        'apreciar',
        'reconocer',
        'recordar',
        'memorizar',
        'retener',
        'reproducir',
        'repetir',
        'aplicar',
        'usar',
        'emplear',
        'ejecutar',
        'practicar',
        'entrenar',
        'combinar',
        'organizar',
        'estructurar',
        'reconfigurar',
        'planificar',
        'proyectar',
        'generar',
        'inventar',
        'idear',
        'producir',
        'construir',
        'fabricar',
        'componer',
        'formular',
        'redactar',
        'escribir',
        'comunicar',
        'expresar',
        'interpretar',
        'traducir',
        'transformar',
        'adaptar',
        'modificar',
        'alterar',
        'seleccionar',
        'elegir',
        'optar',
        'escoger',
        'determinar',
        'decidir',
        'proponer',
        'sugerir',
        'recomendar',
        'aconsejar',
        'indicar',
        'señalar',
        'verificar',
        'comprobar',
        'confirmar',
        'validar',
        'contrastar',
        'revisar',
        'reflexionar',
        'meditar',
        'considerar',
        'pensar',
        'razonar',
        'deducir',
        'inferir',
        'inducir',
        'extrapolar',
        'generalizar',
        'particularizar',
        'especificar'
    ];

    /**
     * Palabras de contexto/condición comunes
     */
    private static $palabrasContexto = [
        'mediante',
        'a través',
        'por medio',
        'utilizando',
        'empleando',
        'haciendo uso',
        'en',
        'dentro',
        'bajo',
        'durante',
        'ante',
        'frente',
        'con el fin',
        'para',
        'a fin',
        'con el propósito',
        'con la finalidad',
        'buscando',
        'considerando',
        'tomando en cuenta',
        'teniendo en cuenta',
        'de acuerdo',
        'según',
        'conforme',
        'en base',
        'basándose',
        'partiendo',
        'cuando',
        'si',
        'una vez',
        'después',
        'antes',
        'mientras',
        'dado',
        'establecido',
        'definido',
        'caracterizado',
        'especificado',
        'en el contexto',
        'en el marco',
        'dentro del ámbito',
        'en el ámbito'
    ];

    /**
     * Validar estructura de Resultado de Aprendizaje
     * Estructura esperada: Verbo (Infinitivo) + Contenido/Objeto + Contexto/Condición
     */
    public static function validarResultadoAprendizaje($texto)
    {
        $resultado = [
            'valido' => false,
            'errores' => [],
            'advertencias' => [],
            'componentes' => [
                'verbo' => false,
                'contenido' => false,
                'contexto' => false
            ]
        ];

        if (empty(trim($texto))) {
            $resultado['errores'][] = 'El campo no puede estar vacío';
            return $resultado;
        }

        $textoBajo = mb_strtolower($texto, 'UTF-8');

        // 1. Buscar verbo en infinitivo al inicio
        $primeraPalabra = explode(' ', trim($textoBajo))[0];
        $primeraPalabra = rtrim($primeraPalabra, '.,;:');

        if (in_array($primeraPalabra, self::$verbosInfinitivo)) {
            $resultado['componentes']['verbo'] = true;
        } else {
            $resultado['errores'][] = 'No comienza con un verbo en infinitivo válido. Primera palabra detectada: "' . $primeraPalabra . '"';
        }

        // 2. Verificar que hay contenido después del verbo
        $palabras = preg_split('/\s+/', trim($textoBajo));
        if (count($palabras) >= 2) {
            // Si hay al menos 2 palabras después del verbo, consideramos que hay contenido
            $resultado['componentes']['contenido'] = true;
        } else {
            $resultado['errores'][] = 'Falta contenido/objeto después del verbo. Debe especificar QUÉ aprenderá el estudiante.';
        }

        // 3. Buscar palabras de contexto/condición
        $contieneContexto = false;
        foreach (self::$palabrasContexto as $palabra) {
            if (strpos($textoBajo, $palabra) !== false) {
                $contieneContexto = true;
                break;
            }
        }

        if ($contieneContexto) {
            $resultado['componentes']['contexto'] = true;
        } else {
            $resultado['advertencias'][] = 'Parece que falta el contexto/condición. Se recomienda especificar BAJO QUÉ CIRCUNSTANCIAS realiza esta acción.';
        }

        // Determinar si es válido
        // Mínimo: Verbo + Contenido (requeridos)
        // Contexto: recomendado
        $resultado['valido'] = $resultado['componentes']['verbo'] && $resultado['componentes']['contenido'];

        return $resultado;
    }

    /**
     * Validar estructura de Criterios de Logro
     * Estructura esperada: Debe contener indicadores observables
     */
    public static function validarCriteriosLogro($texto)
    {
        $resultado = [
            'valido' => false,
            'errores' => [],
            'advertencias' => []
        ];

        if (empty(trim($texto))) {
            $resultado['errores'][] = 'El campo no puede estar vacío';
            return $resultado;
        }

        $textoBajo = mb_strtolower($texto, 'UTF-8');

        // Verificar que contiene elementos observables
        $palabrasObservables = ['identifica', 'demuestra', 'realiza', 'ejecuta', 'produce', 'aplica', 'resuelve', 'analiza', 'elabora'];

        $contieneObservable = false;
        foreach ($palabrasObservables as $palabra) {
            if (strpos($textoBajo, $palabra) !== false) {
                $contieneObservable = true;
                break;
            }
        }

        if (!$contieneObservable) {
            $resultado['advertencias'][] = 'Se recomienda usar verbos observables/medibles (identifica, demuestra, realiza, aplica, etc.)';
        }

        // Verificar longitud mínima
        $palabras = str_word_count($textoBajo);
        if ($palabras < 5) {
            $resultado['advertencias'][] = 'El criterio es muy breve. Se recomienda especificar mejor cómo se evalúa el logro.';
        }

        $resultado['valido'] = count($resultado['errores']) === 0;

        return $resultado;
    }

    /**
     * Validar estructura de Competencia
     * Debe contener habilidad + área + nivel
     */
    public static function validarCompetencia($texto)
    {
        $resultado = [
            'valido' => false,
            'errores' => [],
            'advertencias' => []
        ];

        if (empty(trim($texto))) {
            $resultado['errores'][] = 'El campo no puede estar vacío';
            return $resultado;
        }

        $textoBajo = mb_strtolower($texto, 'UTF-8');
        $palabras = str_word_count($textoBajo);

        // Verificar longitud mínima
        if ($palabras < 3) {
            $resultado['advertencias'][] = 'La competencia es muy breve. Se recomienda describirla con más detalle.';
        }

        // Verificar que no sea solo una palabra
        if ($palabras < 2) {
            $resultado['errores'][] = 'La competencia debe estar descrita con más de una palabra.';
        }

        $resultado['valido'] = count($resultado['errores']) === 0;

        return $resultado;
    }

    /**
     * Obtener sugerencias para mejorar un Resultado de Aprendizaje
     */
    public static function obtenerSugerencias($texto)
    {
        $sugerencias = [];

        $textoBajo = mb_strtolower($texto, 'UTF-8');
        $primeraPalabra = explode(' ', trim($textoBajo))[0];
        $primeraPalabra = rtrim($primeraPalabra, '.,;:');

        if (!in_array($primeraPalabra, self::$verbosInfinitivo)) {
            $sugerencias[] = '✓ Comienza con un verbo en infinitivo. Ejemplos: analizar, resolver, identificar, diseñar, etc.';
        }

        if (strlen($texto) < 30) {
            $sugerencias[] = '✓ Añade más detalles sobre QUÉ específicamente aprenderá el estudiante.';
        }

        $palabrasContextoEncontradas = [];
        foreach (self::$palabrasContexto as $palabra) {
            if (strpos($textoBajo, $palabra) !== false) {
                $palabrasContextoEncontradas[] = $palabra;
                break;
            }
        }

        if (empty($palabrasContextoEncontradas)) {
            $sugerencias[] = '✓ Especifica el CONTEXTO/CONDICIÓN: "mediante...", "utilizando...", "en...", "durante...", etc.';
        }

        return $sugerencias;
    }
}
