<?php

namespace EditorKM\Services;

use GoogleTranslator;

require_once(__DIR__ . '/GoogleTranslator.php');


class Translate
{
    private $env;
    //private $serviceRequested;

    public function __construct($env)
    {
        $this->env = $env;
    }


    /**
     * APPLY THE TRANSLATION
     * @param 
     * @return array
     */
    public function translate(string $currentLanguage, string $targetLanguage, string $textToTranslate)
    {
        $response = [
            "error" => "Incorrect input",
            "code" => 400
        ];

        if ($currentLanguage !== "" && $targetLanguage !== "" && $textToTranslate !== "") {
            $service = new GoogleTranslator($this->env);
            $response = $service->translate($currentLanguage, $targetLanguage, $textToTranslate);
        }
        if ($response["error"] == "") {
            unset($response["error"]);
        }

        return $response;
    }
}
