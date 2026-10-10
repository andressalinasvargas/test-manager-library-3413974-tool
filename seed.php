<?php

/**
 * Sembrador de datos de demostración (T-21, RNF-1).
 * Usa EXCLUSIVAMENTE la API REST (data-model §9).
 * Siembra:
 *   - 1 usuario bibliotecario
 *   - 40 libros en las 5 categorías
 *   - 10 socios
 *   - 15 préstamos (algunos con líneas devueltas, otros activos, y 3 vencidos)
 */

$baseUrl = getenv('API_URL') ?: 'http://localhost:8000/api/v1';
$adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD') ?: '248bb29a7b9070fe'; // Contraseña autogenerada en infra/.env

echo "=== Iniciando Sembrador Manager Library (API: {$baseUrl}) ===\n";

function request($method, $url, $body = null, $token = null, $extraHeaders = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    foreach ($extraHeaders as $h) {
        $headers[] = $h;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $res = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    return ['status' => $status, 'data' => $json, 'raw' => $res];
}

// 1. Iniciar sesión como Admin
echo "1. Autenticando administrador...\n";
$auth = request('POST', "{$baseUrl}/auth/login", [
    'username' => $adminUser,
    'password' => $adminPass,
]);

if ($auth['status'] !== 200 || empty($auth['data']['access_token'])) {
    die("ERROR: No se pudo autenticar como admin ({$auth['status']}): {$auth['raw']}\n");
}
$token = $auth['data']['access_token'];
echo "   Autenticado con éxito (rol: {$auth['data']['role']}).\n";

// 2. Obtener Categorías
echo "2. Obteniendo categorías...\n";
$catRes = request('GET', "{$baseUrl}/categories", null, $token);
$categories = $catRes['data'];
if (empty($categories)) {
    die("ERROR: No se encontraron categorías.\n");
}
$catMap = [];
foreach ($categories as $c) {
    $catMap[$c['name']] = $c['id'];
}
echo "   " . count($categories) . " categorías encontradas.\n";

// 3. Crear Bibliotecario
echo "3. Creando bibliotecario 'ana'...\n";
$libRes = request('POST', "{$baseUrl}/users", [
    'username' => 'ana',
    'password' => 'Biblioteca2026*',
    'role' => 'librarian',
], $token);
if ($libRes['status'] === 201) {
    echo "   Bibliotecario creado.\n";
} elseif ($libRes['status'] === 409) {
    echo "   Bibliotecario ya existía (409).\n";
}

// 4. Crear 40 Libros
echo "4. Sembrando 40 libros...\n";
$createdBooks = [];
$catNames = array_keys($catMap);

for ($i = 1; $i <= 40; $i++) {
    $catName = $catNames[($i - 1) % count($catNames)];
    $catId = $catMap[$catName];
    $isbn = sprintf('978%010d', 1000000000 + $i);
    $copies = ($i % 5) + 2; // entre 2 y 6 copias

    $bookData = [
        'title' => "Libro Ejemplar #{$i} de {$catName}",
        'author' => "Autor Literario {$i}",
        'isbn' => $isbn,
        'category_id' => $catId,
        'total_copies' => $copies,
    ];

    $bRes = request('POST', "{$baseUrl}/books", $bookData, $token);
    if ($bRes['status'] === 201) {
        $createdBooks[] = $bRes['data'];
    } elseif ($bRes['status'] === 409) {
        // Si ya existe por ISBN, buscarlo
        $sRes = request('GET', "{$baseUrl}/books?q=" . urlencode($isbn), null, $token);
        if (!empty($sRes['data']['items'][0])) {
            $createdBooks[] = $sRes['data']['items'][0];
        }
    }
}
echo "   " . count($createdBooks) . " libros listos.\n";

// 5. Crear 10 Socios
echo "5. Sembrando 10 socios...\n";
$createdMembers = [];
for ($i = 1; $i <= 10; $i++) {
    $doc = sprintf('1098%06d', $i);
    $mData = [
        'full_name' => "Socio Lector #{$i}",
        'document_number' => $doc,
    ];
    $mRes = request('POST', "{$baseUrl}/members", $mData, $token);
    if ($mRes['status'] === 201) {
        $createdMembers[] = $mRes['data'];
    } elseif ($mRes['status'] === 409) {
        $sRes = request('GET', "{$baseUrl}/members?q=" . urlencode($doc), null, $token);
        if (!empty($sRes['data']['items'][0])) {
            $createdMembers[] = $sRes['data']['items'][0];
        }
    }
}
echo "   " . count($createdMembers) . " socios listos.\n";

// 6. Crear 15 Préstamos (con 3 vencidos usando simulación de tiempo si disponible)
echo "6. Sembrando 15 préstamos...\n";
$loansCreated = 0;
for ($i = 0; $i < 15; $i++) {
    $member = $createdMembers[$i % count($createdMembers)];
    // Asignar de 1 a 2 libros
    $bookA = $createdBooks[($i * 2) % count($createdBooks)];
    $bookB = $createdBooks[($i * 2 + 1) % count($createdBooks)];

    $items = [['book_id' => $bookA['id']]];
    if ($bookA['id'] !== $bookB['id']) {
        $items[] = ['book_id' => $bookB['id']];
    }

    $extraHeaders = [];
    // Para los primeros 3 préstamos, enviar fecha simulada en el pasado para que queden vencidos
    if ($i < 3) {
        $pastDate = date('Y-m-d\TH:i:s\Z', strtotime('-20 days'));
        $extraHeaders[] = "X-Simulated-Time: {$pastDate}";
    }

    $lRes = request('POST', "{$baseUrl}/loans", [
        'member_id' => $member['id'],
        'items' => $items,
    ], $token, $extraHeaders);

    if ($lRes['status'] === 201) {
        $loansCreated++;
        // Devolver una línea en algunos préstamos (ej. el #5 y #6)
        if ($i >= 5 && $i <= 6 && !empty($lRes['data']['items'][0])) {
            $loanId = $lRes['data']['id'];
            $itemId = $lRes['data']['items'][0]['id'];
            request('POST', "{$baseUrl}/loans/{$loanId}/items/{$itemId}/return", null, $token);
        }
    }
}
echo "   {$loansCreated} préstamos creados.\n";

echo "=== Sembrado completado con éxito ===\n";

