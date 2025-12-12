<?php

if (getenv('IS_DDEV_PROJECT') == 'true') {
    // Generate and persist secrets for this DDEV instance
    $secretsFile = dirname(__DIR__, 2) . '/var/.secrets.php';
    if (!file_exists($secretsFile)) {
        @mkdir(dirname($secretsFile), 0755, true);
        $secrets = [
            'encryptionKey' => bin2hex(random_bytes(48)),
            'installToolPassword' => password_hash(bin2hex(random_bytes(16)), PASSWORD_ARGON2I),
        ];
        file_put_contents($secretsFile, '<?php return ' . var_export($secrets, true) . ';');
    }
    $secrets = include $secretsFile;

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $secrets['encryptionKey'];
    $GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword'] = $secrets['installToolPassword'];
    $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'dbname' => 'db',
                        'driver' => 'mysqli',
                        'host' => 'db',
                        'password' => 'db',
                        'port' => '3306',
                        'user' => 'db',
                    ],
                ],
            ],
            'GFX' => [
                'processor' => 'ImageMagick',
                'processor_path' => '/usr/bin/',
                'processor_path_lzw' => '/usr/bin/',
            ],
            'MAIL' => [
                'transport' => 'smtp',
                'transport_smtp_encrypt' => false,
                'transport_smtp_server' => 'localhost:1025',
            ],
            'SYS' => [
                'trustedHostsPattern' => '.*.*',
                'devIPmask' => '*',
                'displayErrors' => 1,
            ],
        ]
    );
}
