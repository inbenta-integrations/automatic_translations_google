<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use EditorKM\InbentaEditorKM;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

//ADD IN BACKSTAGE, IN THE HEADER "Apply-Translation", AT THE END, THE MAIN LANGUAGE: LIKE secretKey|en
//AND VALIDATE WITH THE env THAT THAT LANGUAGE EXISTS IN ORDER TO IGNORE IT (THAT'S THE ORIGIN LANGUAGE AND WE DONT WANT TO TRANSLATE IT)

if (isset($_SERVER["HTTP_APPLY_TRANSLATION"]) && strpos($_SERVER["HTTP_APPLY_TRANSLATION"], $_ENV["KM_HEADER_KEY"]) !== false) {

    $tmp = explode("|", $_SERVER["HTTP_APPLY_TRANSLATION"]);
    $currentLanguage = trim($tmp[1]);

    if ($currentLanguage !== "") {

        $app = new InbentaEditorKM($_ENV, $currentLanguage);
        $response = $app->handleRequest();

        echo json_encode($response);
        die;
    }
}

http_response_code(417);
echo json_encode(["error" => "No header"]);
