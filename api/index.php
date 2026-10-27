
<?php
header('Content-Type: application/json');
echo json_encode([
    'status' => 'berhasil',
    'pesan' => 'Endpoint PHP aktif'
]);
