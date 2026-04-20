<?php
/**
 * API: Revisado Crudo - Fase 1 + 2
 * MES Hermen Ltda.
 *
 * Acciones GET:
 *   lotes_disponibles  - Lotes elegibles para revisado (excluye hijos de revisión)
 *   obtener            - Registro completo (cabecera + detalle)
 *
 * Acciones POST:
 *   guardar_borrador   - Crea o actualiza un registro en BORRADOR
 *   confirmar          - Confirma, ejecuta split real y registra historial WIP
 */

ob_start();
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once '../config/database.php';

    if (!isLoggedIn()) {
        jsonResponse(['success' => false, 'message' => 'No autorizado'], 401);
    }

    $db     = getDB();
    $method = $_SERVER['REQUEST_METHOD'];

    // ================================================================
    // GET
    // ================================================================
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'lotes_disponibles';

        if ($action === 'lotes_disponibles') {
            $lotes = obtenerLotesDisponibles($db);
            jsonResponse(['success' => true, 'lotes' => $lotes, 'total' => count($lotes)]);
        }

        if ($action === 'obtener') {
            $idRegistro = (int) ($_GET['id_registro'] ?? 0);
            if ($idRegistro <= 0) {
                jsonResponse(['success' => false, 'message' => 'id_registro requerido'], 400);
            }
            $registro = obtenerRegistro($db, $idRegistro);
            if (!$registro) {
                jsonResponse(['success' => false, 'message' => 'Registro no encontrado'], 404);
            }
            jsonResponse(['success' => true, 'registro' => $registro]);
        }

        jsonResponse(['success' => false, 'message' => 'Acción GET no reconocida'], 400);
    }

    // ================================================================
    // POST
    // ================================================================
    if ($method === 'POST') {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            jsonResponse(['success' => false, 'message' => 'Payload JSON inválido'], 400);
        }

        $action = $data['action'] ?? '';

        if ($action === 'guardar_borrador') {
            $resultado = guardarBorrador($db, $data);
            jsonResponse($resultado, $resultado['success'] ? 200 : 422);
        }

        if ($action === 'confirmar') {
            $resultado = confirmarRegistro($db, $data);
            jsonResponse($resultado, $resultado['success'] ? 200 : 422);
        }

        jsonResponse(['success' => false, 'message' => 'Acción POST no reconocida'], 400);
    }

    jsonResponse(['success' => false, 'message' => 'Método HTTP no soportado'], 405);

} catch (PDOException $e) {
    error_log('[revisado_crudo] PDOException: ' . $e->getMessage());
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Error de base de datos'], 500);
} catch (Throwable $e) {
    error_log('[revisado_crudo] Error: ' . $e->getMessage());
    ob_clean();
    jsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
}


// ====================================================================
// LOTES DISPONIBLES
// ====================================================================

/**
 * Retorna lotes elegibles para revisado crudo.
 *
 * Criterios de elegibilidad:
 *  - estado_lote NOT IN ('ANULADO','CERRADO')
 *  - estado_revision IS NULL  (primer revisado)
 *    OR estado_revision = 'REVISION_PARCIAL'
 *  - No es un lote hijo creado por revisión (motivo_derivacion IS NULL o no es REVISION_*)
 *  - cantidad_base_unidades > 0
 *
 * Filtros opcionales GET: lote, producto, familia, estado, id_area
 */
function obtenerLotesDisponibles(PDO $db): array
{
    $params = [];

    $sql = "
        SELECT
            l.id_lote_wip,
            l.codigo_lote,
            l.id_producto,
            p.codigo_producto,
            p.descripcion_completa                           AS producto,
            li.nombre_linea                                  AS familia,
            l.id_area_actual,
            a.nombre                                         AS area_actual,
            l.estado_lote,
            l.estado_revision,
            l.id_lote_padre,
            l.cantidad_base_unidades,
            l.pendiente_revision_unidades,
            CASE
                WHEN l.estado_revision IS NULL               THEN l.cantidad_base_unidades
                WHEN l.estado_revision = 'REVISION_PARCIAL' THEN l.pendiente_revision_unidades
                ELSE 0
            END                                              AS pendiente_para_revision,
            CASE WHEN l.id_lote_padre IS NOT NULL THEN 1 ELSE 0 END AS es_lote_derivado,
            l.fecha_inicio                                   AS fecha_ingreso,
            DATEDIFF(NOW(), l.fecha_inicio)                  AS antiguedad_dias
        FROM lote_wip l
        INNER JOIN productos_tejidos p  ON p.id_producto = l.id_producto
        LEFT  JOIN lineas_producto   li ON li.id_linea   = p.id_linea
        LEFT  JOIN areas_produccion  a  ON a.id_area     = l.id_area_actual
        WHERE l.estado_lote NOT IN ('ANULADO', 'CERRADO')
          AND (
                l.estado_revision IS NULL
             OR l.estado_revision = 'REVISION_PARCIAL'
          )
          AND l.cantidad_base_unidades > 0
          AND (
                l.motivo_derivacion IS NULL
             OR l.motivo_derivacion NOT IN ('REVISION_APTA', 'REVISION_OBSERVADA')
          )
    ";

    $filtroLote     = $_GET['lote']     ?? null;
    $filtroProducto = $_GET['producto'] ?? null;
    $filtroFamilia  = $_GET['familia']  ?? null;
    $filtroEstado   = $_GET['estado']   ?? null;
    $filtroArea     = isset($_GET['id_area']) ? (int)$_GET['id_area'] : null;

    if ($filtroLote) {
        $sql .= " AND l.codigo_lote LIKE ?";
        $params[] = '%' . $filtroLote . '%';
    }
    if ($filtroProducto) {
        $sql .= " AND (p.codigo_producto LIKE ? OR p.descripcion_completa LIKE ?)";
        $params[] = '%' . $filtroProducto . '%';
        $params[] = '%' . $filtroProducto . '%';
    }
    if ($filtroFamilia) {
        $sql .= " AND li.nombre_linea LIKE ?";
        $params[] = '%' . $filtroFamilia . '%';
    }
    if ($filtroEstado) {
        $sql .= " AND l.estado_lote = ?";
        $params[] = $filtroEstado;
    }
    if ($filtroArea) {
        $sql .= " AND l.id_area_actual = ?";
        $params[] = $filtroArea;
    }

    $sql .= " ORDER BY l.fecha_inicio ASC, l.id_lote_wip ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ====================================================================
// OBTENER REGISTRO
// ====================================================================

function obtenerRegistro(PDO $db, int $idRegistro): ?array
{
    $stmtCab = $db->prepare("
        SELECT
            r.*,
            u_rev.nombre_completo AS revisadora_nombre,
            u_sup.nombre_completo AS supervisor_nombre,
            u_cre.nombre_completo AS creado_por_nombre,
            t.nombre              AS turno_nombre
        FROM revisado_crudo_registros r
        LEFT JOIN usuarios u_rev ON u_rev.id_usuario = r.id_revisadora
        LEFT JOIN usuarios u_sup ON u_sup.id_usuario = r.id_supervisor
        LEFT JOIN usuarios u_cre ON u_cre.id_usuario = r.creado_por
        LEFT JOIN turnos   t     ON t.id_turno       = r.id_turno
        WHERE r.id_registro = ?
    ");
    /* usuarios usa nombre_completo (no nombre) */
    $stmtCab->execute([$idRegistro]);
    $cabecera = $stmtCab->fetch(PDO::FETCH_ASSOC);
    if (!$cabecera) {
        return null;
    }

    $stmtDet = $db->prepare("
        SELECT
            d.*,
            l.codigo_lote,
            l.estado_lote,
            l.estado_revision,
            l.motivo_derivacion,
            p.codigo_producto,
            p.descripcion_completa AS producto,
            li.nombre_linea        AS familia,
            a.nombre               AS area_destino_apta_nombre
        FROM revisado_crudo_registro_detalle d
        INNER JOIN lote_wip          l  ON l.id_lote_wip   = d.id_lote_wip
        INNER JOIN productos_tejidos p  ON p.id_producto   = d.id_producto
        LEFT  JOIN lineas_producto   li ON li.id_linea     = p.id_linea
        LEFT  JOIN areas_produccion  a  ON a.id_area       = d.id_area_destino_apta
        WHERE d.id_registro = ?
        ORDER BY d.orden_visual, d.id_detalle
    ");
    $stmtDet->execute([$idRegistro]);

    return [
        'cabecera' => $cabecera,
        'detalle'  => $stmtDet->fetchAll(PDO::FETCH_ASSOC),
    ];
}


// ====================================================================
// GUARDAR BORRADOR
// ====================================================================

function guardarBorrador(PDO $db, array $data): array
{
    $fecha        = sanitize($data['fecha'] ?? '');
    $idRevisadora = (int) ($data['id_revisadora'] ?? 0);
    $detalle      = $data['detalle'] ?? [];

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return ['success' => false, 'message' => 'El campo fecha es requerido (YYYY-MM-DD).'];
    }
    if ($idRevisadora <= 0) {
        return ['success' => false, 'message' => 'id_revisadora es requerido.'];
    }
    if (!is_array($detalle) || count($detalle) === 0) {
        return ['success' => false, 'message' => 'Debe incluir al menos un lote en el detalle.'];
    }

    $idsEnPayload = array_column($detalle, 'id_lote_wip');
    if (count($idsEnPayload) !== count(array_unique($idsEnPayload))) {
        return ['success' => false, 'message' => 'El payload contiene lotes duplicados.'];
    }

    foreach ($detalle as $idx => $fila) {
        $pos = $idx + 1;
        foreach (['cantidad_apta_unidades','cantidad_observada_unidades',
                  'cantidad_merma_unidades','cantidad_pendiente_restante_unidades'] as $campo) {
            if (isset($fila[$campo]) && (int)$fila[$campo] < 0) {
                return ['success' => false, 'message' => "Fila {$pos}: {$campo} no puede ser negativo."];
            }
        }
    }

    try {
        $db->beginTransaction();

        $idRegistro = (int) ($data['id_registro'] ?? 0);

        if ($idRegistro > 0) {
            $stmtCheck = $db->prepare(
                "SELECT id_registro, estado FROM revisado_crudo_registros WHERE id_registro = ? FOR UPDATE"
            );
            $stmtCheck->execute([$idRegistro]);
            $reg = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$reg) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Registro no encontrado.'];
            }
            if ($reg['estado'] !== 'BORRADOR') {
                $db->rollBack();
                return ['success' => false, 'message' => "No se puede editar un registro en estado {$reg['estado']}."];
            }

            guardarCabeceraRevision($db, $data, $idRegistro);
            $db->prepare("DELETE FROM revisado_crudo_registro_detalle WHERE id_registro = ?")->execute([$idRegistro]);
        } else {
            $idRegistro = guardarCabeceraRevision($db, $data, 0);
        }

        guardarDetalleRevision($db, $idRegistro, $detalle);

        $db->commit();
        return [
            'success'     => true,
            'message'     => 'Borrador guardado correctamente.',
            'id_registro' => $idRegistro,
        ];

    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[revisado_crudo/guardar_borrador] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error al guardar el borrador.'];
    }
}


// ====================================================================
// CONFIRMAR  (orquestador principal - Fase 2)
// ====================================================================

function confirmarRegistro(PDO $db, array $data): array
{
    $idRegistro = (int) ($data['id_registro'] ?? 0);
    if ($idRegistro <= 0) {
        return ['success' => false, 'message' => 'id_registro es requerido.'];
    }

    try {
        $db->beginTransaction();

        // 1. Bloquear y verificar cabecera
        $stmtReg = $db->prepare(
            "SELECT * FROM revisado_crudo_registros WHERE id_registro = ? FOR UPDATE"
        );
        $stmtReg->execute([$idRegistro]);
        $registro = $stmtReg->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Registro no encontrado.'];
        }
        if ($registro['estado'] !== 'BORRADOR') {
            $db->rollBack();
            return ['success' => false, 'message' => "El registro ya está en estado {$registro['estado']}."];
        }

        // 2. Obtener detalle
        $stmtDet = $db->prepare(
            "SELECT * FROM revisado_crudo_registro_detalle
             WHERE id_registro = ?
             ORDER BY orden_visual, id_detalle"
        );
        $stmtDet->execute([$idRegistro]);
        $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        if (empty($detalle)) {
            $db->rollBack();
            return ['success' => false, 'message' => 'El registro no tiene líneas de detalle.'];
        }

        // 3. Cargar y bloquear lotes padre
        $idLotes      = array_values(array_unique(array_column($detalle, 'id_lote_wip')));
        $placeholders = implode(',', array_fill(0, count($idLotes), '?'));

        $stmtLotes = $db->prepare(
            "SELECT id_lote_wip, codigo_lote, id_producto, id_linea_produccion,
                    cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
                    pendiente_revision_unidades, estado_lote, estado_revision,
                    id_lote_padre, id_area_actual, motivo_derivacion,
                    costo_mp_acumulado, costo_unitario_promedio,
                    id_documento_consumo, id_documento_salida, referencia_externa
             FROM lote_wip
             WHERE id_lote_wip IN ($placeholders)
             FOR UPDATE"
        );
        $stmtLotes->execute($idLotes);
        $lotesReal = [];
        foreach ($stmtLotes->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $lotesReal[$l['id_lote_wip']] = $l;
        }

        // 4. Pre-validación completa (incluye verificación de área destino apta)
        $detalleParaValidar = array_map(fn($f) => [
            'id_lote_wip'                          => $f['id_lote_wip'],
            'cantidad_apta_unidades'               => $f['cantidad_apta_unidades'],
            'cantidad_observada_unidades'          => $f['cantidad_observada_unidades'],
            'cantidad_merma_unidades'              => $f['cantidad_merma_unidades'],
            'cantidad_pendiente_restante_unidades' => $f['cantidad_pendiente_restante_unidades'],
            'observacion_lote'                     => $f['observacion_lote'],
            'id_area_destino_apta'                 => $f['id_area_destino_apta'],
        ], $detalle);

        $errorValidacion = validarDetalleRevision($detalleParaValidar, $lotesReal, $db);
        if ($errorValidacion !== null) {
            $db->rollBack();
            return ['success' => false, 'message' => $errorValidacion];
        }

        // 5. Procesar cada fila (split + historial)
        $idUsuario    = (int) ($_SESSION['user_id'] ?? 0);
        $resumenLotes = [];

        foreach ($detalle as $fila) {
            $resultado = procesarResultadoRevisionLote($db, $fila, $lotesReal[(int)$fila['id_lote_wip']], $idRegistro, $idUsuario);
            $resumenLotes[] = $resultado;
        }

        // 6. Confirmar cabecera
        $db->prepare("
            UPDATE revisado_crudo_registros
            SET estado              = 'CONFIRMADO',
                fecha_confirmacion  = NOW(),
                fecha_actualizacion = NOW()
            WHERE id_registro = ?
        ")->execute([$idRegistro]);

        $db->commit();

        return [
            'success'     => true,
            'message'     => 'Registro confirmado correctamente.',
            'id_registro' => $idRegistro,
            'lotes'       => $resumenLotes,
        ];

    } catch (InvalidArgumentException $e) {
        if ($db->inTransaction()) $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[revisado_crudo/confirmar] ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error al confirmar el registro.'];
    }
}


// ====================================================================
// PROCESADOR PRINCIPAL POR FILA  (Fase 2)
// ====================================================================

/**
 * Ejecuta la segregación completa para una fila de detalle confirmada.
 *
 * Orden de operaciones:
 *  1. Crear lote hijo apto    (si cantidad_apta > 0)
 *  2. Crear lote hijo observado (si cantidad_observada > 0)
 *  3. Registrar merma         (si cantidad_merma > 0)
 *  4. Actualizar saldo del padre al remanente
 *  5. Actualizar estado_resultado y requiere_split en detalle
 *
 * @throws InvalidArgumentException si falla alguna regla de negocio
 */
function procesarResultadoRevisionLote(
    PDO   $db,
    array $fila,
    array $lotePadre,
    int   $idRegistro,
    int   $idUsuario
): array {
    $idLotePadre = (int) $lotePadre['id_lote_wip'];
    $apta        = (int) $fila['cantidad_apta_unidades'];
    $observada   = (int) $fila['cantidad_observada_unidades'];
    $merma       = (int) $fila['cantidad_merma_unidades'];
    $pendiente   = (int) $fila['cantidad_pendiente_restante_unidades'];
    $pendIni     = (int) $fila['pendiente_inicial_unidades'];
    $obsLote     = (string) ($fila['observacion_lote'] ?? '');
    $refReg      = "RC-REG-{$idRegistro}";

    $idLoteHijoApto      = null;
    $codigoHijoApto      = null;
    $idLoteHijoObservado = null;
    $codigoHijoObservado = null;

    // --- A. Lote hijo apto ----------------------------------------
    if ($apta > 0) {
        $idAreaApta = resolverAreaDestinoApta($db, (int)($fila['id_area_destino_apta'] ?? 0), $lotePadre['codigo_lote']);

        $hijoApto = crearLoteHijoDesdeRevision(
            $db, $lotePadre, $apta, 'REVISION_APTA', $idAreaApta
        );
        $idLoteHijoApto  = $hijoApto['id_lote_wip'];
        $codigoHijoApto  = $hijoApto['codigo_lote'];

        registrarEventoRevision($db, [
            'id_lote'          => $idLoteHijoApto,
            'id_lote_rel'      => $idLotePadre,
            'tipo'             => 'REVISION_CRUDO',
            'cantidad_unidades'=> $apta,
            'id_area_origen'   => (int) $lotePadre['id_area_actual'],
            'id_area_destino'  => $idAreaApta,
            'referencia'       => $refReg,
            'usuario'          => $idUsuario,
            'observaciones'    => "Split revisado crudo (APTA) desde lote {$lotePadre['codigo_lote']}",
        ]);
    }

    // --- B. Lote hijo observado -----------------------------------
    if ($observada > 0) {
        $idAreaObservados = resolverAreaObservados($db, (int) $lotePadre['id_area_actual']);

        $hijoObservado = crearLoteHijoDesdeRevision(
            $db, $lotePadre, $observada, 'REVISION_OBSERVADA', $idAreaObservados
        );
        $idLoteHijoObservado  = $hijoObservado['id_lote_wip'];
        $codigoHijoObservado  = $hijoObservado['codigo_lote'];

        registrarEventoRevision($db, [
            'id_lote'          => $idLoteHijoObservado,
            'id_lote_rel'      => $idLotePadre,
            'tipo'             => 'REVISION_CRUDO',
            'cantidad_unidades'=> $observada,
            'id_area_origen'   => (int) $lotePadre['id_area_actual'],
            'id_area_destino'  => $idAreaObservados,
            'referencia'       => $refReg,
            'usuario'          => $idUsuario,
            'observaciones'    => "Split revisado crudo (OBSERVADA) desde lote {$lotePadre['codigo_lote']}",
        ]);
    }

    // --- C. Merma ------------------------------------------------
    if ($merma > 0) {
        registrarEventoRevision($db, [
            'id_lote'          => $idLotePadre,
            'id_lote_rel'      => null,
            'tipo'             => 'RECHAZO_MERMA',
            'cantidad_unidades'=> $merma,
            'id_area_origen'   => (int) $lotePadre['id_area_actual'],
            'id_area_destino'  => null,
            'referencia'       => $refReg,
            'usuario'          => $idUsuario,
            'observaciones'    => "Merma en revisado crudo reg#{$idRegistro}" . ($obsLote ? ": {$obsLote}" : ''),
        ]);
    }

    // --- D. Actualizar padre ------------------------------------
    $nuevoEstadoRevision = $pendiente > 0 ? 'REVISION_PARCIAL' : 'REVISION_COMPLETA';
    actualizarSaldoPadrePostRevision($db, $idLotePadre, $pendiente, $nuevoEstadoRevision);

    // Evento en el padre que documenta la revisión sobre él
    registrarEventoRevision($db, [
        'id_lote'          => $idLotePadre,
        'id_lote_rel'      => null,
        'tipo'             => 'REVISION_CRUDO',
        'cantidad_unidades'=> $pendIni,
        'id_area_origen'   => (int) $lotePadre['id_area_actual'],
        'id_area_destino'  => null,
        'referencia'       => $refReg,
        'usuario'          => $idUsuario,
        'observaciones'    => sprintf(
            'Revisado crudo reg#%d | apta:%d obs:%d merma:%d pend:%d',
            $idRegistro, $apta, $observada, $merma, $pendiente
        ),
    ]);

    // --- E. Actualizar campos calculados en detalle -------------
    $estadoResultado = determinarEstadoResultado($apta, $observada, $merma, $pendiente);
    $requiereSplit   = ($apta > 0 || $observada > 0) ? 1 : 0; // fase 2 ya ejecutó el split, pero se mantiene como registro

    $db->prepare("
        UPDATE revisado_crudo_registro_detalle
        SET requiere_split   = ?,
            estado_resultado = ?
        WHERE id_detalle = ?
    ")->execute([$requiereSplit, $estadoResultado, (int)$fila['id_detalle']]);

    return [
        'id_lote_wip'           => $idLotePadre,
        'codigo_lote'           => $lotePadre['codigo_lote'],
        'pendiente_restante'    => $pendiente,
        'estado_revision'       => $nuevoEstadoRevision,
        'estado_resultado'      => $estadoResultado,
        'id_lote_hijo_apto'     => $idLoteHijoApto,
        'codigo_hijo_apto'      => $codigoHijoApto,
        'id_lote_hijo_observado'=> $idLoteHijoObservado,
        'codigo_hijo_observado' => $codigoHijoObservado,
        'merma_registrada'      => $merma,
    ];
}


// ====================================================================
// CREAR LOTE HIJO DESDE REVISIÓN
// ====================================================================

/**
 * Crea un lote hijo derivado por revisado crudo.
 *
 * - Hereda producto, linea, documentos y costo proporcional del padre
 * - Recibe motivo_derivacion para distinguirlo en lotes_disponibles
 * - Genera código y referencia_externa únicos con sufijos -RA / -RO
 *
 * @param PDO    $db
 * @param array  $lotePadre   Fila completa del lote padre (con FOR UPDATE ya aplicado)
 * @param int    $cantidad    Unidades del hijo
 * @param string $motivo      'REVISION_APTA' | 'REVISION_OBSERVADA'
 * @param int    $idAreaDestino
 * @return array  ['id_lote_wip' => int, 'codigo_lote' => string]
 */
function crearLoteHijoDesdeRevision(PDO $db, array $lotePadre, int $cantidad, string $motivo, int $idAreaDestino): array
{
    $cantidades = rc_normalizarUnidades($cantidad);

    $sufijo      = ($motivo === 'REVISION_APTA') ? 'RA' : 'RO';
    $codigoHijo  = rc_generarCodigoHijo($db, $lotePadre['codigo_lote'], $sufijo);
    $refHijo     = rc_generarReferenciaHijo($db, $lotePadre['referencia_externa'], $sufijo);

    $costoHijo   = rc_costoProporcion(
        (float) $lotePadre['costo_mp_acumulado'],
        (int)   $lotePadre['cantidad_base_unidades'],
        $cantidad
    );
    $costoUnitario = (float) $lotePadre['costo_unitario_promedio'];

    // id_documento_salida es NOT NULL en el esquema (script 08); siempre se hereda del padre
    $idDocSalida  = (int) ($lotePadre['id_documento_salida']  ?: ($lotePadre['id_documento_consumo'] ?? 0));
    $idDocConsumo = (int) ($lotePadre['id_documento_consumo'] ?: $idDocSalida);

    if ($idDocSalida <= 0) {
        throw new InvalidArgumentException(
            "El lote padre {$lotePadre['codigo_lote']} no tiene id_documento_salida; "
            . "no se puede crear lote hijo."
        );
    }

    $stmt = $db->prepare("
        INSERT INTO lote_wip (
            id_lote_padre,
            codigo_lote,
            id_producto,
            id_linea_produccion,
            cantidad_docenas,
            cantidad_unidades,
            cantidad_base_unidades,
            pendiente_revision_unidades,
            estado_revision,
            motivo_derivacion,
            id_area_actual,
            estado_lote,
            costo_mp_acumulado,
            costo_unitario_promedio,
            id_documento_consumo,
            id_documento_salida,
            referencia_externa,
            fecha_inicio,
            creado_por
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?,
            0, NULL, ?,
            ?, 'ACTIVO',
            ?, ?,
            ?, ?, ?,
            NOW(), ?
        )
    ");

    $stmt->execute([
        (int)  $lotePadre['id_lote_wip'],
        $codigoHijo,
        (int)  $lotePadre['id_producto'],
        $lotePadre['id_linea_produccion'] ? (int)$lotePadre['id_linea_produccion'] : null,
        $cantidades['docenas'],
        $cantidades['unidades'],
        $cantidad,
        $motivo,
        $idAreaDestino,
        round($costoHijo, 4),
        $costoUnitario,
        $idDocConsumo,
        $idDocSalida,
        $refHijo,
        (int) ($_SESSION['user_id'] ?? 0) ?: null,
    ]);

    return [
        'id_lote_wip' => (int) $db->lastInsertId(),
        'codigo_lote' => $codigoHijo,
    ];
}


// ====================================================================
// ACTUALIZAR PADRE POST-REVISIÓN
// ====================================================================

/**
 * Actualiza el lote padre después de segregar hijos y merma.
 * cantidad_base_unidades ← remanente real
 * pendiente_revision_unidades ← mismo remanente
 * estado_revision ← REVISION_PARCIAL | REVISION_COMPLETA
 */
function actualizarSaldoPadrePostRevision(PDO $db, int $idLotePadre, int $saldoRestante, string $estadoRevision): void
{
    $cantidades = rc_normalizarUnidades($saldoRestante);

    $db->prepare("
        UPDATE lote_wip
        SET cantidad_docenas             = ?,
            cantidad_unidades            = ?,
            cantidad_base_unidades       = ?,
            pendiente_revision_unidades  = ?,
            estado_revision              = ?,
            fecha_actualizacion          = NOW()
        WHERE id_lote_wip = ?
    ")->execute([
        $cantidades['docenas'],
        $cantidades['unidades'],
        $saldoRestante,
        $saldoRestante,
        $estadoRevision,
        $idLotePadre,
    ]);
}


// ====================================================================
// REGISTRAR EVENTO / HISTORIAL EN movimientos_wip
// ====================================================================

/**
 * Registra un movimiento WIP de trazabilidad.
 *
 * $data espera:
 *   id_lote          int
 *   id_lote_rel      int|null
 *   tipo             string  (REVISION_CRUDO | RECHAZO_MERMA | TRANSFERENCIA_ETAPA)
 *   cantidad_unidades int
 *   id_area_origen   int|null
 *   id_area_destino  int|null
 *   referencia       string
 *   usuario          int|null
 *   observaciones    string
 */
function registrarEventoRevision(PDO $db, array $data): void
{
    $cantidades = rc_normalizarUnidades((int)$data['cantidad_unidades']);

    $db->prepare("
        INSERT INTO movimientos_wip (
            id_lote_wip, id_lote_relacionado, tipo_movimiento,
            cantidad_docenas, cantidad_unidades,
            id_area_origen, id_area_destino,
            referencia_externa, fecha, usuario, observaciones
        ) VALUES (
            ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, NOW(), ?, ?
        )
    ")->execute([
        (int)   $data['id_lote'],
        isset($data['id_lote_rel']) && $data['id_lote_rel'] ? (int)$data['id_lote_rel'] : null,
        $data['tipo'],
        $cantidades['docenas'],
        $cantidades['unidades'],
        isset($data['id_area_origen'])  && $data['id_area_origen']  ? (int)$data['id_area_origen']  : null,
        isset($data['id_area_destino']) && $data['id_area_destino'] ? (int)$data['id_area_destino'] : null,
        (string) ($data['referencia']    ?? ''),
        isset($data['usuario']) && $data['usuario'] ? (int)$data['usuario'] : null,
        (string) ($data['observaciones'] ?? ''),
    ]);
}


// ====================================================================
// RESOLUCIÓN DE ÁREAS
// ====================================================================

/**
 * Resuelve el área destino para la parte apta.
 * Prioridad: id_area_destino_apta del detalle (obligatorio si apta > 0).
 *
 * @throws InvalidArgumentException si no se puede determinar el área
 */
function resolverAreaDestinoApta(PDO $db, int $idAreaExplicita, string $codigoLote): int
{
    if ($idAreaExplicita > 0) {
        $stmt = $db->prepare("SELECT id_area FROM areas_produccion WHERE id_area = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$idAreaExplicita]);
        if ($stmt->fetchColumn()) {
            return $idAreaExplicita;
        }
        throw new InvalidArgumentException(
            "Lote {$codigoLote}: el área destino apta (id={$idAreaExplicita}) no existe o está inactiva."
        );
    }

    throw new InvalidArgumentException(
        "Lote {$codigoLote}: hay unidades aptas pero no se indicó id_area_destino_apta. "
        . "Es obligatorio especificar el área destino para la parte apta."
    );
}

/**
 * Resuelve el área para lotes observados.
 * Busca área con código 'OBSERVADOS_RC'; si no existe, usa el área del padre.
 */
function resolverAreaObservados(PDO $db, int $idAreaPadreFallback): int
{
    $stmt = $db->prepare(
        "SELECT id_area FROM areas_produccion WHERE codigo = 'OBSERVADOS_RC' AND activo = 1 LIMIT 1"
    );
    $stmt->execute();
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : $idAreaPadreFallback;
}


// ====================================================================
// VALIDACIÓN DE DETALLE
// ====================================================================

/**
 * Valida todas las filas del detalle antes de confirmar.
 * En Fase 2 también verifica la resolución del área destino apta.
 *
 * @return string|null  null = OK, string = primer error encontrado
 */
function validarDetalleRevision(array $detalle, array $lotesReal, PDO $db): ?string
{
    if (empty($detalle)) {
        return 'El detalle no puede estar vacío.';
    }

    $idsSeen = [];

    foreach ($detalle as $idx => $fila) {
        $pos    = $idx + 1;
        $idLote = (int) ($fila['id_lote_wip'] ?? 0);

        if ($idLote <= 0) {
            return "Fila {$pos}: id_lote_wip inválido.";
        }
        if (isset($idsSeen[$idLote])) {
            return "Fila {$pos}: el lote #{$idLote} aparece duplicado.";
        }
        $idsSeen[$idLote] = true;

        if (!isset($lotesReal[$idLote])) {
            return "Fila {$pos}: lote #{$idLote} no encontrado.";
        }

        $lote = $lotesReal[$idLote];
        if (in_array($lote['estado_lote'], ['ANULADO', 'CERRADO'], true)) {
            return "Fila {$pos}: lote #{$idLote} está {$lote['estado_lote']}.";
        }

        $apta      = (int) ($fila['cantidad_apta_unidades']              ?? 0);
        $observada = (int) ($fila['cantidad_observada_unidades']         ?? 0);
        $merma     = (int) ($fila['cantidad_merma_unidades']             ?? 0);
        $pendiente = (int) ($fila['cantidad_pendiente_restante_unidades'] ?? 0);

        if ($apta < 0 || $observada < 0 || $merma < 0 || $pendiente < 0) {
            return "Fila {$pos} (lote #{$idLote}): ninguna cantidad puede ser negativa.";
        }

        $pendienteInicial = pendienteRealLote($lote);
        $suma = $apta + $observada + $merma + $pendiente;
        if ($suma !== $pendienteInicial) {
            return "Fila {$pos} (lote #{$idLote}): suma de cantidades ({$suma}) ≠ "
                 . "pendiente inicial ({$pendienteInicial}).";
        }

        if (($observada > 0 || $merma > 0) && empty(trim($fila['observacion_lote'] ?? ''))) {
            return "Fila {$pos} (lote #{$idLote}): observacion_lote es obligatoria "
                 . "cuando hay unidades observadas o merma.";
        }

        // Verificar área destino apta si aplica
        if ($apta > 0) {
            $idAreaApta = (int) ($fila['id_area_destino_apta'] ?? 0);
            if ($idAreaApta <= 0) {
                return "Fila {$pos} (lote #{$idLote}): hay unidades aptas pero falta id_area_destino_apta.";
            }
            $stmtArea = $db->prepare(
                "SELECT 1 FROM areas_produccion WHERE id_area = ? AND activo = 1 LIMIT 1"
            );
            $stmtArea->execute([$idAreaApta]);
            if (!$stmtArea->fetchColumn()) {
                return "Fila {$pos} (lote #{$idLote}): área destino apta (id={$idAreaApta}) inválida o inactiva.";
            }
        }
    }

    return null;
}


// ====================================================================
// HELPERS DE ESCRITURA (cabecera / detalle)
// ====================================================================

function guardarCabeceraRevision(PDO $db, array $data, int $idRegistro): int
{
    $fecha        = sanitize($data['fecha']               ?? date('Y-m-d'));
    $idTurno      = ($data['id_turno']     ?? null) ? (int)$data['id_turno']     : null;
    $idRevisadora = (int) ($data['id_revisadora']          ?? 0);
    $idSupervisor = ($data['id_supervisor'] ?? null) ? (int)$data['id_supervisor'] : null;
    $mesa         = sanitize($data['mesa']                ?? '');
    $obsGeneral   = sanitize($data['observacion_general'] ?? '');
    $creadoPor    = (int) ($_SESSION['user_id']            ?? 0);

    if ($idRegistro === 0) {
        $stmt = $db->prepare("
            INSERT INTO revisado_crudo_registros
                (fecha, id_turno, id_revisadora, id_supervisor,
                 mesa, observacion_general, estado, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, 'BORRADOR', ?)
        ");
        $stmt->execute([
            $fecha, $idTurno, $idRevisadora, $idSupervisor,
            $mesa ?: null, $obsGeneral ?: null, $creadoPor,
        ]);
        return (int) $db->lastInsertId();
    }

    $db->prepare("
        UPDATE revisado_crudo_registros
        SET fecha               = ?,
            id_turno            = ?,
            id_revisadora       = ?,
            id_supervisor       = ?,
            mesa                = ?,
            observacion_general = ?,
            fecha_actualizacion = NOW()
        WHERE id_registro = ?
    ")->execute([
        $fecha, $idTurno, $idRevisadora, $idSupervisor,
        $mesa ?: null, $obsGeneral ?: null, $idRegistro,
    ]);

    return $idRegistro;
}

function guardarDetalleRevision(PDO $db, int $idRegistro, array $detalle): void
{
    $stmt = $db->prepare("
        INSERT INTO revisado_crudo_registro_detalle (
            id_registro, id_lote_wip, id_producto,
            pendiente_inicial_unidades,
            cantidad_apta_unidades,
            cantidad_observada_unidades,
            cantidad_merma_unidades,
            cantidad_pendiente_restante_unidades,
            id_area_destino_apta,
            observacion_lote,
            orden_visual
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($detalle as $idx => $fila) {
        $idAreaDest = ($fila['id_area_destino_apta'] ?? null) ? (int)$fila['id_area_destino_apta'] : null;
        $obsLote    = sanitize($fila['observacion_lote'] ?? '');

        $stmt->execute([
            $idRegistro,
            (int) ($fila['id_lote_wip']                           ?? 0),
            (int) ($fila['id_producto']                           ?? 0),
            (int) ($fila['pendiente_inicial_unidades']            ?? 0),
            (int) ($fila['cantidad_apta_unidades']                ?? 0),
            (int) ($fila['cantidad_observada_unidades']           ?? 0),
            (int) ($fila['cantidad_merma_unidades']               ?? 0),
            (int) ($fila['cantidad_pendiente_restante_unidades']  ?? 0),
            $idAreaDest,
            $obsLote ?: null,
            $idx,
        ]);
    }
}


// ====================================================================
// HELPERS DE CONSULTA
// ====================================================================

function obtenerLoteRevision(PDO $db, int $idLote): ?array
{
    $stmt = $db->prepare("
        SELECT id_lote_wip, codigo_lote, id_producto,
               cantidad_docenas, cantidad_unidades, cantidad_base_unidades,
               pendiente_revision_unidades, estado_lote, estado_revision,
               motivo_derivacion, id_lote_padre, id_area_actual,
               costo_mp_acumulado, costo_unitario_promedio,
               id_documento_consumo, id_documento_salida, referencia_externa
        FROM lote_wip
        WHERE id_lote_wip = ?
        LIMIT 1
    ");
    $stmt->execute([$idLote]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Pendiente real disponible para el próximo ciclo de revisión:
 *  - NULL / '' → usa cantidad_base_unidades (primer revisado)
 *  - REVISION_PARCIAL → usa pendiente_revision_unidades
 *  - REVISION_COMPLETA → 0
 */
function pendienteRealLote(array $lote): int
{
    $estado = $lote['estado_revision'] ?? null;
    if ($estado === null || $estado === '') {
        return (int) $lote['cantidad_base_unidades'];
    }
    if ($estado === 'REVISION_PARCIAL') {
        return (int) $lote['pendiente_revision_unidades'];
    }
    return 0;
}

function determinarEstadoResultado(int $apta, int $observada, int $merma, int $pendiente): string
{
    $tieneApta      = $apta      > 0;
    $tieneObservada = $observada > 0;
    $tieneMerma     = $merma     > 0;

    if (!$tieneApta && !$tieneObservada && $tieneMerma && $pendiente === 0) {
        return 'MERMA_TOTAL';
    }
    if ($tieneApta && !$tieneObservada && !$tieneMerma && $pendiente === 0) {
        return 'APTA';
    }
    if (!$tieneApta && $tieneObservada && !$tieneMerma && $pendiente === 0) {
        return 'OBSERVADA';
    }
    return 'MIXTA';
}


// ====================================================================
// UTILIDADES LOCALES  (prefijadas rc_ para evitar colisión con wip.php)
// ====================================================================

/**
 * Convierte base_unidades a docenas + unidades residuales.
 */
function rc_normalizarUnidades(int $baseUnidades): array
{
    $base = max(0, $baseUnidades);
    return [
        'docenas'  => (int) floor($base / 12),
        'unidades' => (int) ($base % 12),
        'base'     => $base,
    ];
}

/**
 * Genera un código único para el hijo con sufijo -RA01 o -RO01.
 *
 * Si el padre ya tiene derivados con el mismo sufijo, incrementa el correlativo.
 */
function rc_generarCodigoHijo(PDO $db, string $codigoPadre, string $sufijo): string
{
    $base = substr($codigoPadre, 0, 24);
    $stmt = $db->prepare("SELECT COUNT(*) FROM lote_wip WHERE codigo_lote LIKE ?");
    $stmt->execute([$base . '-' . $sufijo . '%']);
    $correlativo = (int) $stmt->fetchColumn() + 1;
    return $base . '-' . $sufijo . str_pad((string)$correlativo, 2, '0', STR_PAD_LEFT);
}

/**
 * Genera una referencia_externa única para el hijo.
 */
function rc_generarReferenciaHijo(PDO $db, string $referenciaPadre, string $sufijo): string
{
    $base = substr($referenciaPadre !== '' ? $referenciaPadre : 'RC-SPLIT', 0, 40);
    $stmt = $db->prepare("SELECT COUNT(*) FROM lote_wip WHERE referencia_externa LIKE ?");
    $stmt->execute([$base . '-' . $sufijo . '%']);
    $correlativo = (int) $stmt->fetchColumn() + 1;
    return $base . '-' . $sufijo . str_pad((string)$correlativo, 2, '0', STR_PAD_LEFT);
}

/**
 * Calcula el costo proporcional de una parte del lote.
 * Evita división por cero; si el base es 0 devuelve 0.
 */
function rc_costoProporcion(float $costoTotal, int $baseTotal, int $parteUnidades): float
{
    if ($baseTotal <= 0 || $costoTotal <= 0) {
        return 0.0;
    }
    return $costoTotal * ($parteUnidades / $baseTotal);
}
