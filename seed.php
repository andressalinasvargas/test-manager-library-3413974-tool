<?php

/**
 * Demonstration database seeder (T-21, RNF-1).
 * Uses EXCLUSIVELY the REST API without direct SQL (data-model §9).
 * Seeds:
 *   - 1 librarian user
 *   - 40 books across the 5 categories
 *   - 10 members
 *   - 15 loans (including returned items, active items, and 3 overdue items)
 */

$baseUrl = getenv('API_URL') ?: 'http://localhost:8000/api/v1';
$adminUser = getenv('ADMIN_USERNAME') ?: 'admin';
$adminPass = getenv('ADMIN_PASSWORD') ?: '248bb29a7b9070fe'; // Auto-generated password from infra/.env

echo "=== Starting Manager Library Seeder (API: {$baseUrl}) ===\n";

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

// 1. Authenticate as Admin
echo "1. Authenticating administrator...\n";
$auth = request('POST', "{$baseUrl}/auth/login", [
    'username' => $adminUser,
    'password' => $adminPass,
]);

if ($auth['status'] !== 200 || empty($auth['data']['access_token'])) {
    die("ERROR: Could not authenticate as admin ({$auth['status']}): {$auth['raw']}\n");
}
$token = $auth['data']['access_token'];
echo "   Authenticated successfully (role: {$auth['data']['role']}).\n";

// 2. Fetch Categories
echo "2. Fetching categories...\n";
$catRes = request('GET', "{$baseUrl}/categories", null, $token);
$categories = $catRes['data'];
if (empty($categories)) {
    die("ERROR: No categories found.\n");
}
$catMap = [];
foreach ($categories as $c) {
    $catMap[$c['name']] = $c['id'];
}
echo "   " . count($categories) . " categories found.\n";

// 3. Create Librarian
echo "3. Creating librarian 'ana'...\n";
$libRes = request('POST', "{$baseUrl}/users", [
    'username' => 'ana',
    'password' => 'Biblioteca2026*',
    'role' => 'librarian',
], $token);
if ($libRes['status'] === 201) {
    echo "   Librarian created.\n";
} elseif ($libRes['status'] === 409) {
    echo "   Librarian already exists (409).\n";
}

// 4. Seed 40 Books
echo "4. Seeding 40 books...\n";
$createdBooks = [];
$catNames = array_keys($catMap);

for ($i = 1; $i <= 40; $i++) {
    $catName = $catNames[($i - 1) % count($catNames)];
    $catId = $catMap[$catName];
    $isbn = sprintf('978%010d', 1000000000 + $i);
    $copies = ($i % 5) + 2; // Between 2 and 6 copies

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
        // If already exists by ISBN, look it up
        $sRes = request('GET', "{$baseUrl}/books?q=" . urlencode($isbn), null, $token);
        if (!empty($sRes['data']['items'][0])) {
            $createdBooks[] = $sRes['data']['items'][0];
        }
    }
}
echo "   " . count($createdBooks) . " books ready.\n";

// 5. Seed 10 Members
echo "5. Seeding 10 members...\n";
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
echo "   " . count($createdMembers) . " members ready.\n";

// 6. Seed 15 Loans (with 3 overdue using time simulation if supported)
echo "6. Seeding 15 loans...\n";
$loansCreated = 0;
for ($i = 0; $i < 15; $i++) {
    $member = $createdMembers[$i % count($createdMembers)];
    // Assign 1 to 2 books
    $bookA = $createdBooks[($i * 2) % count($createdBooks)];
    $bookB = $createdBooks[($i * 2 + 1) % count($createdBooks)];

    $items = [['book_id' => $bookA['id']]];
    if ($bookA['id'] !== $bookB['id']) {
        $items[] = ['book_id' => $bookB['id']];
    }

    $extraHeaders = [];
    // For first 3 loans, send simulated past timestamp to generate overdue state
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
        // Return a line item in specific loans
        if ($i >= 5 && $i <= 6 && !empty($lRes['data']['items'][0])) {
            $loanId = $lRes['data']['id'];
            $itemId = $lRes['data']['items'][0]['id'];
            request('POST', "{$baseUrl}/loans/{$loanId}/items/{$itemId}/return", null, $token);
        }
    }
}
echo "   {$loansCreated} loans created.\n";

echo "=== Seeding finished successfully ===\n";
