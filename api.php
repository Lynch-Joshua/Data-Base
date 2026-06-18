<?php
/**
 * API REST - Sistema de Matrícula Escolar
 * Colegio Ingeniero Tomás Guardia (2026-2027)
 * 
 * Descripción: API simulada para gestionar matrículas de estudiantes
 * Almacenamiento: Sesión PHP (en fase de desarrollo)
 * Próxima fase: PDO SQLite
 * 
 * @version 1.0
 * @since 2026-06-18
 */

// ============================================================================
// INICIALIZACIÓN
// ============================================================================

// Iniciar sesión PHP
session_start();

// Configurar headers CORS y formato JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Manejar solicitudes preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(json_encode(['status' => 'ok']));
}

// ============================================================================
// INICIALIZAR DATOS EN SESIÓN
// ============================================================================

if (!isset($_SESSION['mock_estudiantes'])) {
    $_SESSION['mock_estudiantes'] = [];
}

// ============================================================================
// ENRUTAMIENTO PRINCIPAL
// ============================================================================

$metodo = strtoupper($_SERVER['REQUEST_METHOD']);

try {
    switch ($metodo) {
        case 'GET':
            manejarGET();
            break;
        
        case 'POST':
            manejarPOST();
            break;
        
        default:
            enviarRespuesta(
                false,
                'Método HTTP no permitido',
                null,
                405
            );
    }
} catch (Exception $e) {
    enviarRespuesta(
        false,
        'Error interno: ' . $e->getMessage(),
        null,
        500
    );
}

// ============================================================================
// MANEJADORES DE MÉTODOS HTTP
// ============================================================================

/**
 * Maneja solicitudes GET
 * Retorna el estado 'success', conteo total y arreglo completo de estudiantes
 */
function manejarGET() {
    $estudiantes = $_SESSION['mock_estudiantes'];
    $totalEstudiantes = count($estudiantes);
    
    $datos = [
        'estudiantes' => $estudiantes,
        'total' => $totalEstudiantes
    ];
    
    enviarRespuesta(
        true,
        'Estudiantes recuperados exitosamente',
        $datos,
        200
    );
}

/**
 * Maneja solicitudes POST
 * Recibe datos del formulario, valida y agrega a la sesión
 */
function manejarPOST() {
    // Obtener datos JSON del cuerpo de la solicitud
    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);
    
    // Validar que se recibió JSON válido
    if ($datos === null) {
        enviarRespuesta(
            false,
            'JSON inválido en el cuerpo de la solicitud',
            null,
            400
        );
        return;
    }
    
    // Validar campos obligatorios
    $validacion = validarDatosEstudiante($datos);
    
    if (!$validacion['valido']) {
        enviarRespuesta(
            false,
            $validacion['mensaje'],
            null,
            400
        );
        return;
    }
    
    // Preparar datos del nuevo estudiante
    $nuevoEstudiante = prepararEstudiante($datos);
    
    // Agregar a la sesión
    $_SESSION['mock_estudiantes'][] = $nuevoEstudiante;
    
    // Retornar respuesta exitosa
    $datosRespuesta = [
        'estudiante_agregado' => $nuevoEstudiante,
        'total_estudiantes' => count($_SESSION['mock_estudiantes'])
    ];
    
    enviarRespuesta(
        true,
        'Estudiante registrado exitosamente',
        $datosRespuesta,
        201
    );
}

// ============================================================================
// FUNCIONES DE VALIDACIÓN
// ============================================================================

/**
 * Valida que los datos del formulario cumplan con los requisitos
 * 
 * @param array $datos Datos a validar
 * @return array Arreglo con 'valido' (bool) y 'mensaje' (string)
 */
function validarDatosEstudiante($datos) {
    // Campos obligatorios
    $camposObligatorios = ['cedula', 'nombres', 'apellidos', 'bachillerato', 'jornada'];
    
    // Opciones válidas para campos selectivos
    $bachilleratos = ['Ciencias', 'Informática', 'Turismo', 'Comercio'];
    $jornadas = ['Mañana', 'Tarde'];
    
    // Verificar que todos los campos requeridos existan
    foreach ($camposObligatorios as $campo) {
        if (!isset($datos[$campo])) {
            return [
                'valido' => false,
                'mensaje' => "Campo obligatorio faltante: $campo"
            ];
        }
    }
    
    // Validar Cédula (no puede estar vacía)
    if (empty(trim($datos['cedula']))) {
        return [
            'valido' => false,
            'mensaje' => 'La cédula no puede estar vacía'
        ];
    }
    
    // Validar Nombres (no puede estar vacío)
    if (empty(trim($datos['nombres']))) {
        return [
            'valido' => false,
            'mensaje' => 'Los nombres no pueden estar vacíos'
        ];
    }
    
    // Validar Apellidos (no puede estar vacío)
    if (empty(trim($datos['apellidos']))) {
        return [
            'valido' => false,
            'mensaje' => 'Los apellidos no pueden estar vacíos'
        ];
    }
    
    // Validar Bachillerato (debe ser una opción válida)
    if (!in_array($datos['bachillerato'], $bachilleratos)) {
        return [
            'valido' => false,
            'mensaje' => "Bachillerato inválido. Opciones válidas: " . implode(', ', $bachilleratos)
        ];
    }
    
    // Validar Jornada (debe ser una opción válida)
    if (!in_array($datos['jornada'], $jornadas)) {
        return [
            'valido' => false,
            'mensaje' => "Jornada inválida. Opciones válidas: " . implode(', ', $jornadas)
        ];
    }
    
    // Validar que la cédula no esté duplicada
    $cedula = trim($datos['cedula']);
    foreach ($_SESSION['mock_estudiantes'] as $estudiante) {
        if ($estudiante['cedula'] === $cedula) {
            return [
                'valido' => false,
                'mensaje' => "La cédula $cedula ya está registrada"
            ];
        }
    }
    
    return [
        'valido' => true,
        'mensaje' => 'Datos válidos'
    ];
}

// ============================================================================
// FUNCIONES DE PREPARACIÓN DE DATOS
// ============================================================================

/**
 * Prepara los datos del estudiante para almacenamiento
 * Limpia, normaliza y agrega metadatos
 * 
 * @param array $datos Datos crudos del formulario
 * @return array Datos procesados del estudiante
 */
function prepararEstudiante($datos) {
    return [
        'id' => generarIdEstudiante(),
        'cedula' => trim($datos['cedula']),
        'nombres' => trim($datos['nombres']),
        'apellidos' => trim($datos['apellidos']),
        'bachillerato' => trim($datos['bachillerato']),
        'jornada' => trim($datos['jornada']),
        'fecha_registro' => date('Y-m-d H:i:s'),
        'estado' => 'activo'
    ];
}

/**
 * Genera un ID único para cada estudiante
 * 
 * @return string ID único basado en timestamp y microtime
 */
function generarIdEstudiante() {
    return 'EST_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
}

// ============================================================================
// FUNCIONES DE RESPUESTA
// ============================================================================

/**
 * Envía una respuesta JSON estandarizada
 * 
 * @param bool $exito Estado de la operación
 * @param string $mensaje Mensaje descriptivo
 * @param mixed $datos Datos a incluir en la respuesta
 * @param int $codigoHttp Código HTTP de respuesta
 */
function enviarRespuesta($exito, $mensaje, $datos = null, $codigoHttp = 200) {
    http_response_code($codigoHttp);
    
    $respuesta = [
        'status' => $exito ? 'success' : 'error',
        'message' => $mensaje,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($datos !== null) {
        $respuesta['data'] = $datos;
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

?>
