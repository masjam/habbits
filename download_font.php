<?php
$url = 'https://github.com/aliftype/amiri/raw/main/fonts/Amiri-Regular.ttf';
$content = file_get_contents($url);
@mkdir(__DIR__ . '/storage/app/public/fonts', 0777, true);
file_put_contents(__DIR__ . '/storage/app/public/fonts/Amiri-Regular.ttf', $content);
echo "Font downloaded.\n";
