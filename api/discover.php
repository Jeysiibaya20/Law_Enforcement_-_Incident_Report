<?php
header('Content-Type: application/json; charset=utf-8');

$apiBase = dirname($_SERVER['SCRIPT_NAME']);
if ($apiBase === '/' || $apiBase === '\\') {
    $apiBase = '';
}

$endpoints = [
    [
        'path' => $apiBase . '/api/chatbot_api.php',
        'method' => 'POST',
        'description' => 'Multi-language AI chatbot endpoint. Accepts JSON with message and optional language.',
        'example' => [
            'message' => 'Hello, how can I file an incident?',
            'language' => 'en'
        ]
    ],
    [
        'path' => $apiBase . '/modules/incident_submit.php',
        'method' => 'POST',
        'description' => 'Submit a new incident report using authenticated session. Accepts JSON body or form data.',
        'example' => [
            'reporter_name' => 'Juan Dela Cruz',
            'incident_type' => 'Theft',
            'narrative' => 'Suspect entered the store...',
            'location' => 'Barangay 1'
        ]
    ],
    [
        'path' => $apiBase . '/modules/crime_mapping.php?action=get_incident_data',
        'method' => 'GET',
        'description' => 'Retrieve incident data for the mapping dashboard with optional filters.',
        'query_parameters' => [
            'incident_type', 'urgency', 'barangay', 'start_date', 'end_date'
        ]
    ],
    [
        'path' => $apiBase . '/modules/crime_mapping.php?action=get_stats',
        'method' => 'GET',
        'description' => 'Retrieve overall crime statistics for the mapping dashboard.',
        'query_parameters' => [
            'start_date', 'end_date'
        ]
    ],
    [
        'path' => $apiBase . '/modules/crime_mapping.php?action=get_trends&days=30',
        'method' => 'GET',
        'description' => 'Retrieve crime trend data for charting.',
        'query_parameters' => [
            'days'
        ]
    ],
    [
        'path' => $apiBase . '/modules/crime_mapping.php?action=get_incident_detail&incident_id={id}',
        'method' => 'GET',
        'description' => 'Retrieve detailed incident information for the map detail modal.',
        'query_parameters' => [
            'incident_id'
        ]
    ],
    [
        'path' => $apiBase . '/modules/Blotter.php?action=view&id={id}',
        'method' => 'GET',
        'description' => 'Retrieve blotter entry details in JSON format.',
        'query_parameters' => [
            'id'
        ]
    ],
    [
        'path' => $apiBase . '/auth/check_email.php',
        'method' => 'GET',
        'description' => 'Check whether an email is available for registration.',
        'query_parameters' => [
            'email'
        ]
    ],
    [
        'path' => $apiBase . '/auth/check_username.php',
        'method' => 'GET',
        'description' => 'Check whether a username is available for registration.',
        'query_parameters' => [
            'username'
        ]
    ],
    [
        'path' => $apiBase . '/config/LanguageManager.php',
        'method' => 'POST',
        'description' => 'Set the current language for the user session.',
        'example' => [
            'set_language' => 'en'
        ]
    ],
    [
        'path' => $apiBase . '/api/external_integration.php?action=receive',
        'method' => 'POST',
        'description' => 'Receive JSON payloads from external systems. Optional X-External-Secret header may be used for security.',
        'example' => [
            'incident_id' => 'INC-20260708-ABCDE',
            'status' => 'received',
            'notes' => 'Payload received from external service.'
        ]
    ],
    [
        'path' => $apiBase . '/api/external_integration.php?action=send',
        'method' => 'POST',
        'description' => 'Forward JSON data from this app to another external system. Provide target_url and payload in the request body.',
        'example' => [
            'target_url' => 'https://example.com/external-webhook',
            'payload' => [
                'case_no' => 'INC-20260708-ABCDE',
                'status' => 'new',
                'remarks' => 'Forwarding incident data.'
            ]
        ]
    ]
];

$documentation = [
    'description' => 'System API discovery endpoint. Returns a list of known JSON and AJAX endpoints across the app.',
    'version' => '1.0',
    'generated_at' => date('c'),
    'endpoints' => $endpoints
];

echo json_encode($documentation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
