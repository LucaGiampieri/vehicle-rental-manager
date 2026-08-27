<?php

return [

    //Percorsi del backend che il frontend può chiamare attraverso CORS
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'forgot-password',
        'reset-password',
        'verify-email/*',
        'email/verification-notification',
    ],

    //Consente tutti i metodi HTTP, come GET, POST, PUT e DELETE
    'allowed_methods' => ['*'],

    //Legge dal file .env l'indirizzo del frontend autorizzato
    //Usa localhost:5173 se FRONTEND_URL non è impostato
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
    ],

    //Non autorizza altre origini tramite espressioni regolari
    'allowed_origins_patterns' => [],

    //Consente gli header necessari, compreso quello per la protezione CSRF
    'allowed_headers' => ['*'],

    //Non espone al frontend ulteriori header della risposta
    'exposed_headers' => [],

    //Non memorizza nel browser l'esito delle verifiche preliminari CORS
    'max_age' => 0,

    //Consente l'utilizzo dei cookie nelle richieste del frontend
    'supports_credentials' => true,

];
