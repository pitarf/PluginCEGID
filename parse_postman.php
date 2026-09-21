<?php
$content = file_get_contents("c:/Git/Wordpress/ValedoPais/Postman.json");

$terms = ['armaz', 'exist', 'stock', 'invent', 'acert', 'movimento', 'lote', 'saldo'];
foreach ($terms as $t) {
    if (stripos($content, $t) !== false) {
        echo "Found '$t':\n";
        preg_match_all('/"([^"]*' . $t . '[^"]*)"/i', $content, $m);
        $matches = array_unique($m[1] ?? []);
        foreach (array_slice($matches, 0, 10) as $match) {
            echo "  - $match\n";
        }
    }
}
