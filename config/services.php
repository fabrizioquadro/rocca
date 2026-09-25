<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'feegow' => [
        'base_url' => env('FEEGOW_BASE_URL', 'https://api.feegow.com/v1/api'),
        'token' => env('FEEGOW_TOKEN'),
        'timeout' => env('FEEGOW_TIMEOUT', 30),

        // Agendamento da aplicação (appoints/new-appoint).
        // Procedimento 13 = "Aplicação" na licença do Instituto Rocca.
        'procedimento_aplicacao_id' => env('FEEGOW_PROCEDIMENTO_APLICACAO', 13),
        // Local/agenda: 1 = Consultório Túlio, 2 = Consultório Breno, 3 = Consultório Ana.
        'local_aplicacao_id' => env('FEEGOW_LOCAL_APLICACAO', 1),
        'profissional_aplicacao_id' => env('FEEGOW_PROFISSIONAL_APLICACAO', 0),
        'especialidade_aplicacao_id' => env('FEEGOW_ESPECIALIDADE_APLICACAO', 0),
    ],

];
