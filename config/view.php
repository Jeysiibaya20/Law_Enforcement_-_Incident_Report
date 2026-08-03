<?php

$compiledPath = env('VIEW_COMPILED_PATH');
if (!$compiledPath) {
    $compiledPath = storage_path('framework/views');
}

if (!is_dir($compiledPath)) {
    @mkdir($compiledPath, 0755, true);
}

return [
    'paths' => [
        resource_path('views'),
    ],
    'compiled' => $compiledPath,
];
