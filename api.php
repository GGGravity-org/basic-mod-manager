<?php
header('Content-Type: application/json');

$mods_file = __DIR__ . '/mods.json';
if (!file_exists($mods_file)) file_put_contents($mods_file, json_encode([]));
$mods = json_decode(file_get_contents($mods_file), true);

$search = $_GET['q'] ?? '';
$limit = (int)($_GET['limit'] ?? 20);
$include_code = isset($_GET['code']) && $_GET['code'] == '1';

$results = [];
$count = 0;

function getUsername($id) {
    static $cache = [];
    if(isset($cache[$id])) return $cache[$id];
    $res = @file_get_contents('https://gggravity.org/gravity-accounts/getusernamefui.php?userid=' . urlencode($id));
    $cache[$id] = ($res && $res !== 'a') ? trim($res) : $id;
    return $cache[$id];
}

foreach ($mods as $mod_id => $mod) {
    if (!$mod['shared']) continue;
    if ($search && stripos($mod['name'], $search) === false) continue;

    $entry = [
        'id' => $mod_id,
        'name' => $mod['name'],
        'owner' => getUsername($mod['owner']),
        'description' => isset($mod['description']) ? $mod['description'] : ''
    ];
    if ($include_code) $entry['code'] = $mod['code'];

    $results[] = $entry;
    $count++;
    if ($count >= $limit) break;
}

echo json_encode([
    'count' => count($results),
    'results' => $results
]);
