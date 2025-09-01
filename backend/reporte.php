<?php
header('Content-Type: application/json; charset=UTF-8');
require 'db.php';

try {
    $db = conectarBD();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $days = max(0, min(3650, $days));

    $cond = '';
    $args = [];
    if ($days > 0) {
        $cond = "WHERE date(fecha) >= date('now','localtime', ?)";
        $args[] = "-{$days} days";
    }

    $fetchAgg = function (PDO $db, string $campo, string $cond, array $args) {
        $sql = "
            SELECT COALESCE($campo, 'Sin $campo') AS etiqueta, COUNT(*) AS total
            FROM denuncias
            $cond
            GROUP BY etiqueta
            ORDER BY total DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = [];
        $values = [];
        foreach ($rows as $r) {
            $labels[] = ucfirst((string)$r['etiqueta']);
            $values[] = (int)$r['total'];
        }
        return [$labels, $values];
    };

    [$labelsTipo, $valuesTipo] = $fetchAgg($db, 'tipo', $cond, $args);
    [$labelsGrav, $valuesGrav] = $fetchAgg($db, 'gravedad', $cond, $args);

    $nota = null;
    if (count($valuesTipo) === 0 && count($valuesGrav) === 0 && $days > 0) {
        [$labelsTipo, $valuesTipo] = $fetchAgg($db, 'tipo', '', []);
        [$labelsGrav, $valuesGrav] = $fetchAgg($db, 'gravedad', '', []);
        $nota = "Sin datos en los últimos $days días; mostrando totales históricos.";
        $days = 0; 
    }

    echo json_encode([
        "ok"           => true,
        "rango_dias"   => $days, 
        "nota"         => $nota, 
        "vacio"        => (count($valuesTipo) === 0 && count($valuesGrav) === 0),
        "por_tipo"     => ["labels" => $labelsTipo, "values" => $valuesTipo],
        "por_gravedad" => ["labels" => $labelsGrav, "values" => $valuesGrav],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "ok"      => false,
        "error"   => "Error al generar reporte",
        "detalle" => $e->getMessage()
    ]);
}
