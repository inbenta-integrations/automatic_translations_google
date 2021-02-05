<?php

namespace EditorKM;

use EditorKM\Services\Translate;
use GuzzleHttp\Client as Guzzle;


class InbentaEditorKM extends Translate
{
    private $authUrl;
    private $kmEditorUrl;
    private $env;
    private $currentLanguage;

    private $id;
    private $categories;
    private $categoriesTarget;
    private $status;
    private $title;
    private $userTypesSource;
    private $userTypesTarget;
    private $mainText;
    private $alternativeTitle;
    private $relatedContentsTargetOriginal;
    private $publicationDate;
    private $expirationDate;
    private $useForPopular;

    public function __construct(array $env, string $currentLanguage)
    {
        parent::__construct($env);

        $this->env = $env;
        $this->currentLanguage = $currentLanguage;
        $this->authUrl = isset($env['AUTH_URL']) ? $env['AUTH_URL'] : '';

        $json = file_get_contents('php://input');
        $request = json_decode($json);
        $this->validateOriginalContent($request);
    }


    /**
     * VALIDATE THE ORIGINAL CONTENT
     * @param object $request
     */
    private function validateOriginalContent(object $request)
    {
        $currLang = strtoupper($this->currentLanguage);
        $apiKey = isset($this->env['KM_API_KEY_' . $currLang]) ? $this->env['KM_API_KEY_' . $currLang] : '';
        $secret = isset($this->env['KM_SECRET_' . $currLang]) ? $this->env['KM_SECRET_' . $currLang] : '';
        $accessToken = $this->makeAuth($apiKey, $secret);

        if ($accessToken !== "" && isset($request->currentContent)) {
            $this->id = $request->currentContent->id;
            $headers = [
                'x-inbenta-key' => $apiKey,
                'Authorization' => 'Bearer ' . $accessToken
            ];
            $content = $this->remoteRequest($this->kmEditorUrl . "/contents/" . $this->id, "get", [], $headers, "data");

            if (isset($content->id)) {
                $this->title = $content->title;
                $this->categories = $content->categories;
                $this->status = $content->status;
                $this->useForPopular = isset($content->useForPopular) ? $content->useForPopular : null;

                $this->userTypesSource = [];

                foreach ($content->userTypes as $userType) {
                    if ($userType->id == 0) {
                        foreach ($userType->attributes as $data) {
                            if ($data->name == "ANSWER_TEXT") {
                                $this->mainText = $data->objects[0]->value;
                            } else if ($data->name == "ALTERNATIVE_TITLE") {
                                $this->alternativeTitle = $data->objects;
                            }
                        }
                        $this->publicationDate = isset($userType->publicationDate) ? $userType->publicationDate : null;
                        $this->expirationDate = isset($userType->expirationDate) ? $userType->expirationDate : null;
                    }
                    unset($userType->attributes[0]->objects[0]->id);
                    unset($userType->attributes[0]->objects[0]->creationDate);
                    unset($userType->attributes[0]->objects[0]->modificationDate);
                    unset($userType->attributes[1]->objects[0]->id);
                    unset($userType->attributes[1]->objects[0]->creationDate);
                    unset($userType->attributes[1]->objects[0]->modificationDate);
                    unset($userType->name);

                    $this->userTypesSource[] = $userType;
                }
            }
        }
        if (is_null($this->id) || is_null($this->title) || is_null($this->mainText)) {
            http_response_code(400);
            echo json_encode(["error" => "Bad request"]);
            die;
        }
    }


    /**
     * EXECUTE THE REMOTE REQUEST
     * @param string $url
     * @param string $method
     * @param array $params
     * @param array $headers
     * @param string $dataResponse
     */
    private function remoteRequest(string $url, string $method, array $params, array $headers, string $dataResponse = "")
    {
        $response = null;

        $client = new Guzzle();
        $clientParams = ['headers' => $headers];
        if ($method !== 'get') {
            $clientParams['body'] = json_encode($params);
        }
        $serverOutput = $client->$method($url, $clientParams);

        if (method_exists($serverOutput, 'getBody')) {
            $responseBody = $serverOutput->getBody();
            if (method_exists($responseBody, 'getContents')) {
                $result = json_decode($responseBody->getContents());

                if ($dataResponse == "") {
                    $response = $result;
                } else if (isset($result->$dataResponse)) {
                    $response = $result->$dataResponse;
                }
            }
        }
        return $response;
    }


    /**
     * MAKE THE AUTHORIZATION ON INSTANCE
     * @param string $apiKey
     * @param string $secret
     */
    private function makeAuth(string $apiKey, string $secret)
    {
        $accessToken = "";
        $upsToken = isset($this->env['KM_UPST']) ? $this->env['KM_UPST'] : '';

        if ($this->authUrl !== "" && $apiKey !== "" && $secret !== "" && $upsToken !== "") {
            $params = [
                "secret" => $secret,
                "user_personal_secret" => $upsToken
            ];

            $headers = ['x-inbenta-key' => $apiKey];
            $response = $this->remoteRequest($this->authUrl, "post", $params, $headers);

            $accessToken = isset($response->accessToken) ? $response->accessToken : null;
            $kmEditor = "km-editor"; //This is needed because the object name with middle dash is an error in PHP
            $this->kmEditorUrl = isset($response->apis) && isset($response->apis->$kmEditor) ? $response->apis->$kmEditor . "/v1" : null;
        }
        return $accessToken;
    }


    /**
     * SEND THE NEEDED TEXTS TO TRANSLATE WITH THE TARGET LANGUAGE
     * @param string $newLang
     */
    private function sendToTranslate(string $newLang)
    {
        $title = $this->translate($this->currentLanguage, $newLang, $this->title);
        $mainText = $this->translate($this->currentLanguage, $newLang, $this->mainText);

        $userTypes = [];
        foreach ($this->userTypesSource as $key => $userType) {
            if ($key == 0 || $userType->attributes[0]->objects[0]->value == $this->mainText) {
                $text = $mainText["text"] !== "" ? $mainText["text"] : $this->mainText;
            } else {
                $text = null;
                if (!is_null($userType->attributes[0]->objects[0]->value)) {
                    $tmpTrans = $this->translate($this->currentLanguage, $newLang, $userType->attributes[0]->objects[0]->value);
                    $text = $tmpTrans["text"] !== "" ? $tmpTrans["text"] : $userType->attributes[0]->objects[0]->value;
                }
            }
            $userTypes[] = $text;
        }

        $alternativeText = [];
        if (!is_null($this->alternativeTitle)) {
            foreach ($this->alternativeTitle as $alt) {
                if ($alt->value != "") {
                    $tmpTrans = $this->translate($this->currentLanguage, $newLang, $alt->value);
                    $alternativeText[] = $tmpTrans["text"] !== "" ? $tmpTrans["text"] : $alt->value;
                }
            }
        }
        $response = [
            "title" => $title["text"] !== "" ? $title["text"] : $this->title,
            "mainText" => $mainText["text"] !== "" ? $mainText["text"] : $this->mainText,
            "userTypes" => $userTypes,
            "alternativeText" => $alternativeText
        ];
        return $response;
    }


    /**
     * CHECK THE EXISTING CATEGORIES ON THE TARGET LANGUAGE, AND COMPARE THEM WITH THE SOURCE TO INSERT THE COINCIDENCE
     * @param string $apiKey
     * @param string $accessToken
     */
    private function checkCategories(string $apiKey, string $accessToken)
    {
        $this->categoriesTarget = [];

        $headers = [
            'x-inbenta-key' => $apiKey,
            'Authorization' => 'Bearer ' . $accessToken
        ];
        $categories = $this->remoteRequest($this->kmEditorUrl . "/categories", "get", [], $headers, "data");
        if (count($categories) > 0) {

            $idsExisting = [];
            foreach ($categories as $category) {
                $idsExisting[] = $category->id;
            }
            foreach ($this->categories as $category) {
                if (in_array($category, $idsExisting)) {
                    $this->categoriesTarget[] = $category;
                }
            }
        }
    }


    /**
     * VALIDATE THAT THE CONTENT EXISTS AND THE USERS IN THE TARGET INSTANCE
     * @param string $apiKey
     * @param string $accessToken
     */
    private function validateContentTarget(string $apiKey, string $accessToken)
    {
        $headers = [
            'x-inbenta-key' => $apiKey,
            'Authorization' => 'Bearer ' . $accessToken
        ];
        $content = $this->remoteRequest($this->kmEditorUrl . "/contents/" . $this->id, "get", [], $headers, "data");

        if (isset($content->id)) {

            $this->userTypesTarget = [];
            $this->relatedContentsTargetOriginal = [];

            if (isset($content->userTypes)) {
                $userTypesInInstance = [];
                foreach ($content->userTypes as $ut) {
                    $userTypesInInstance[] = $ut->id;
                    $this->relatedContentsTargetOriginal[$ut->id] = $ut->relatedContents;
                }
                //Only catch the user types that exist in both instances (original and target)
                foreach ($this->userTypesSource as $ut) {
                    if (in_array($ut->id, $userTypesInInstance)) {
                        $this->userTypesTarget[] = $ut;
                    }
                }
            }
            return true;
        }
        return false;
    }


    /**
     * SAVE THE RELATED CONTENT IF EXISTS, FIRST DELETE THE ONES THAT DO NOT EXIST, AND INSERT THE NEW, IF THERE IS NOTHING DIFFERENT DO NOTHING
     * @param string $apiKey
     * @param string $accessToken
     */
    private function saveRelatedContent(string $apiKey, string $accessToken)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'x-inbenta-key' => $apiKey,
            'Authorization' => 'Bearer ' . $accessToken
        ];

        if (count($this->userTypesTarget) > 0) {

            $paramsDelete = ["userTypes" => []];
            $paramsInsert = ["userTypes" => []];
            foreach ($this->userTypesTarget as $userType) {
                $contentsIdsInsert = [];
                $contentsIdsDelete = [];

                foreach ($userType->relatedContents as $relatedContent) {
                    $contentsIdsInsert[] = $relatedContent->id;
                }

                foreach ($this->relatedContentsTargetOriginal[$userType->id] as $relatedContents) {
                    if (!in_array($relatedContents->id, $contentsIdsInsert)) {
                        $contentsIdsDelete[] = $relatedContents->id;
                    }
                    if (in_array($relatedContents->id, $contentsIdsInsert)) {
                        if (($key = array_search($relatedContents->id, $contentsIdsInsert)) !== false) {
                            unset($contentsIdsInsert[$key]);
                        }
                    }
                }

                if (count($contentsIdsDelete) > 0) {
                    $paramsDelete["userTypes"][] = [
                        "id" => $userType->id,
                        "contentIds" => $contentsIdsDelete
                    ];
                }
                if (count($contentsIdsInsert) > 0) {
                    $paramsInsert["userTypes"][] = [
                        "id" => $userType->id,
                        "contentIds" => $contentsIdsInsert
                    ];
                }
            }
            if (count($paramsDelete["userTypes"]) > 0) {
                $responseDelete = $this->remoteRequest($this->kmEditorUrl . "/contents/" . $this->id . "/relatedContents", "delete", $paramsDelete, $headers, "data");
            }
            if (count($paramsInsert["userTypes"]) > 0) {
                $responseInsert = $this->remoteRequest($this->kmEditorUrl . "/contents/" . $this->id . "/relatedContents", "post", $paramsInsert, $headers, "data");
            }
        }
    }


    /**
     * SAVE THE CONTENT ON EDITOR KM
     * @param string $apiKey
     * @param string $accessToken
     * @param array $translation
     */
    private function saveContent(string $apiKey, string $accessToken, array $translation)
    {
        $params = [
            "title" => $translation["title"],
            "status" => $this->status,
            "attributes" => [
                [
                    "name" => "ANSWER_TEXT",
                    "objects" => [
                        [
                            "value" => $translation["mainText"]
                        ]
                    ]
                ]
            ]
        ];
        if (count($this->categoriesTarget) > 0) {
            $params["categories"] = $this->categoriesTarget;
        }
        if (!is_null($this->publicationDate)) {
            $params["publicationDate"] = $this->publicationDate;
        }
        if (!is_null($this->expirationDate)) {
            $params["expirationDate"] = $this->expirationDate;
        }
        if (!is_null($this->useForPopular)) {
            $params["useForPopular"] = $this->useForPopular;
        }

        if (count($this->userTypesTarget) > 0) {
            $params["userTypes"] = [];

            foreach ($this->userTypesTarget as $key => $userType) {
                if ($key > 0) {
                    $params["userTypes"][$key - 1] = $userType;
                    $params["userTypes"][$key - 1]->attributes[0]->objects[0]->value = $translation["userTypes"][$key];
                    unset($params["userTypes"][$key - 1]->relatedContents);
                    unset($params["userTypes"][$key - 1]->attributesGroups);
                }
            }
        }

        if (!is_null($translation["alternativeText"]) && count($translation["alternativeText"]) > 0) {
            $params["attributes"][1] = [
                "name" => "ALTERNATIVE_TITLE",
                "objects" => []
            ];
            foreach ($translation["alternativeText"] as $alt) {
                $params["attributes"][1]["objects"][] = [
                    "value" => $alt
                ];
            }
        }

        $headers = [
            'x-inbenta-key' => $apiKey,
            'Authorization' => 'Bearer ' . $accessToken
        ];
        $saveResponse = $this->remoteRequest($this->kmEditorUrl . "/contents/" . $this->id, "patch", $params, $headers, "data");
        return $saveResponse;
    }


    /**
     * HANDLE THE INCOMING REQUEST
     */
    public function handleRequest()
    {
        $languages = isset($this->env["KM_LANG_LIST"]) ? $this->env["KM_LANG_LIST"] : "";

        if ($languages !== "") {

            $languages = str_replace(" ", "", $languages);
            $languagesList = strpos($languages, ",") !== false ? explode(",", $languages) : [$languages];

            if (isset($languagesList[0])) {
                $error_no_match = [];
                $error_api_key = [];
                foreach ($languagesList as $newLang) {

                    if (strtolower($this->currentLanguage) != strtolower($newLang)) {
                        $newLang = strtoupper($newLang);

                        $apiKey = isset($this->env['KM_API_KEY_' . $newLang]) ? $this->env['KM_API_KEY_' . $newLang] : '';
                        $secret = isset($this->env['KM_SECRET_' . $newLang]) ? $this->env['KM_SECRET_' . $newLang] : '';

                        $accessToken = $this->makeAuth($apiKey, $secret);
                        if ($accessToken !== "") {

                            if ($this->validateContentTarget($apiKey, $accessToken)) {

                                $this->checkCategories($apiKey, $accessToken);
                                $this->saveRelatedContent($apiKey, $accessToken);
                                $translation = $this->sendToTranslate(strtolower($newLang));
                                $response = $this->saveContent($apiKey, $accessToken, $translation);
                            } else {
                                //No content id or users matched
                                $error_no_match[] = $newLang;
                            }
                        } else {
                            //Lang api key or secret with error
                            $error_api_key[] = $newLang;
                        }
                    }
                }
                if (count($error_api_key) === 0 && count($error_no_match) === 0) {
                    return ["message" => "Done"];
                } else {
                    return [
                        "message" => "Errors in ApiKey (check env file for configuration) or no matching content or users",
                        "error_api_key" => $error_api_key,
                        "error_no_match" => $error_no_match,
                    ];
                }
            }
        }
        http_response_code(500);
        return ["error" => "No lang list configured, in env file"];
    }
}
