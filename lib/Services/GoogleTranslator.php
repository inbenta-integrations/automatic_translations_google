<?php

class GoogleTranslator
{
    private $key;
    private $url;

    public function __construct($env)
    {
        $this->key = $env['GOOGLE_KEY'];
        $this->url = $env['GOOGLE_URL'];
    }


    /**
     * Translate the given text 
     * @param string $currentLanguage
     * @param string $targetLanguage
     * @param string $textToTranslate
     */
    public function translate(string $currentLanguage, string $targetLanguage, string $textToTranslate)
    {
        $response = [
            "error" => "",
            "text" => "",
            "code" => 200
        ];

        $params = [
            'q' => $textToTranslate,
            'source' => $currentLanguage,
            'target' => $targetLanguage,
            'format' => 'text'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url . "?key=" . $this->key);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = ['Content-Type: application/json'];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $serverOutput = curl_exec($ch);
        curl_close($ch);

        if ($serverOutput) {

            $result = json_decode($serverOutput, true);

            if (isset($result["data"])) {
                if (isset($result["data"]["translations"])) {
                    $response["text"] = $result["data"]["translations"][0]["translatedText"];
                }
            } else {
                $response["error"] = "Error on translate";
                $response["code"] = 400;
            }
        } else {
            $response["error"] = "Error on translate";
            $response["code"] = 400;
        }

        return $response;
    }
}
