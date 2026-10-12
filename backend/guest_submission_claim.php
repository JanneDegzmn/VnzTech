<?php

header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
http_response_code(410);

echo json_encode([
    'success' => false,
    'message' => 'Manual submission claims are no longer used. Verify your Seller email to link eligible guest submissions automatically.'
], JSON_THROW_ON_ERROR);
