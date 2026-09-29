<?php

return [
    'frontend_path' => dirname(__DIR__),
    'storage' => storage_path('app/documentation'),
    'public_link' => env('DOCUMENTATION_PUBLIC_LINK', public_path('docs')),
    'base' => env('DOCUMENTATION_BASE', '/docs/'),
    'node' => env('DOCUMENTATION_NODE', 'node'),
    'queue_connection' => env('DOCUMENTATION_QUEUE_CONNECTION', 'documentation'),
    'build_timeout' => min(240, max(1, (int) env('DOCUMENTATION_BUILD_TIMEOUT', 240))),
    'image_max_kb' => (int) env('DOCUMENTATION_IMAGE_MAX_KB', 10240),
    // SVG stays excluded: private images are served inline and must not carry scripts.
    'image_mimes' => ['image/png', 'image/jpeg', 'image/webp', 'image/gif'],
    'revisions_limit' => (int) env('DOCUMENTATION_REVISIONS_LIMIT', 200),
    'publications_limit' => (int) env('DOCUMENTATION_PUBLICATIONS_LIMIT', 100),
    'locales' => ['fr', 'en'],
    'code_languages' => ['text', 'bash', 'shell', 'php', 'javascript', 'typescript', 'jsx', 'tsx', 'vue', 'html', 'css', 'json', 'yaml', 'toml', 'ini', 'sql', 'python', 'go', 'rust', 'java', 'csharp', 'c', 'docker', 'nginx', 'powershell', 'diff', 'markdown', 'mermaid'],
];
