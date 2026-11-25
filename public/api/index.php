<?php
/**
 * OceanViewFlats - API Root Protective Shield
 * 
 * Prevents directory listing and returns a generic forbidden response
 * for unauthorized directory-level requests.
 */

declare(strict_types=1);

http_response_code(403);
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

echo json_encode([
    'success' => false,
    'error' => 'Access Denied',
    'message' => 'Direct directory access is not permitted.'
]);
exit;
