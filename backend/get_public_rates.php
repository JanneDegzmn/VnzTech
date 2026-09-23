<?php
/*
|--------------------------------------------------------------------------
| VNZ TECH - Public Buying Rates
|--------------------------------------------------------------------------
|
| Public, read-only endpoint. No login required.
| Returns only ACTIVE, NON-ARCHIVED rates so the public sell.php
| page always reflects exactly what Admin currently has enabled.
|
*/

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/database.php';

try {

    $stmt = $pdo->query("
        SELECT
            rate_type,
            category,
            description,
            price,
            unit
        FROM buying_rates
        WHERE is_active = 1
          AND archived_at IS NULL
        ORDER BY
            rate_type ASC,
            category ASC
    ");

    $rates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'rates' => array_map(
            function ($rate) {
                return [
                    'rate_type' => $rate['rate_type'],
                    'category' => $rate['category'],
                    'description' => $rate['description'],
                    'price' => (float) $rate['price'],
                    'unit' => $rate['unit']
                ];
            },
            $rates
        )
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load buying rates right now.'
    ]);

}