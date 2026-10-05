<?php
$options = [
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"
    ]
];
$context = stream_context_create($options);

$bismillah_url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/27/Basmala.svg/1024px-Basmala.svg.png';
$bismillah_img = file_get_contents($bismillah_url, false, $context);
@mkdir(__DIR__ . '/public/images', 0777, true);
file_put_contents(__DIR__ . '/public/images/bismillah.png', $bismillah_img);

$salam_url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/29/Assalamualaikum.svg/1024px-Assalamualaikum.svg.png';
$salam_img = file_get_contents($salam_url, false, $context);
file_put_contents(__DIR__ . '/public/images/salam.png', $salam_img);

echo "Images downloaded.\n";
