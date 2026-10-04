<?php
    $url = 'http://api.sanakan.pl/swagger/v2/swagger.json';
    $data = @json_decode(file_get_contents($url), true);

    header('Content-Type: application/json');
    
    $data['host'] = 'http://api.sanakan.pl/';
    
    echo json_encode($data, JSON_PRETTY_PRINT );
?>