<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

// Ensure Doctrine loads entity metadata during tests
require_once dirname(__DIR__).'/src/Entity/Invoice.php';
require_once dirname(__DIR__).'/src/Entity/InvoiceItem.php';
require_once dirname(__DIR__).'/src/Entity/Subscription.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Use SQLite in-memory database for tests
$_ENV['DATABASE_URL'] = 'sqlite:///:memory:';
$_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
