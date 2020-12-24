# KM AUTOMATIC TRANSLATION
 
### TABLE OF CONTENTS
* [OBJECTIVE](#objective)
* [FUNCTIONALITIES](#functionalities)
* [INSTALLATION](#installation)
* [DEPENDENCIES](#dependencies)
 
### OBJECTIVE
The objective for this project is to create automatic translations when a content in a KM instance is saved. As long as there is at least a linked instance in a different language.
 
### FUNCTIONALITIES
The translation service uses Google translation services. When a content is saved in a KM instance, the Webhook (previously configured) is triggered and start the translation for the next sections from the original content:
* Title
* Answer text
* Alternative titles (if exists)
* Answer text for every user type (as long as the user types ID's exist in target instance)

Additional to the translation, the service save another values setted in the original content:
* Publication date
* Expiration date
* Status
* Use for popular
* Related content
* Categories (as long as the ID exist in the target instance)

 
### INSTALLATION
The next steps are needed to configure the KM-Translation service:
* A Google Cloud account with the "Cloud Translation API" activated (Google Key needed).
* Webhook configuration on the main instance (Settings -> Static -> On save content webhook). Is necessary to add the url of the service, the header and value:
```env
    url: https://automatic_translations_url.com
    header: Apply-Translation
    value: secretKey|en
```
>**NOTE:** At the end of the header's value, after the pipe character (|), add the origin instance language. Example "en", "es", "fr", etc.
* A created "User Personal Secret Token". [Help Center Instructions](https://help.inbenta.com/en/general/administration/managing-credentials-for-developers/managing-your-ups-tokens/).
* Api Key and Secret of every instance (origin and target instances).
* Set all values in the **.env** file:
```env
AUTH_URL = 
KM_HEADER_KEY =
KM_UPST = 
KM_LANG_LIST = ES,EN
KM_API_KEY_ES = 
KM_SECRET_ES = 
KM_API_KEY_EN = 
KM_SECRET_EN = 
GOOGLE_KEY = 
GOOGLE_URL = 
```
 
### DEPENDENCIES
This application needs 2 dependencies: `guzzlehttp/guzzle` and `vlucas/phpdotenv` as a Composer dependency.
