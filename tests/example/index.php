<?php

use WilyCode\Template\FileLocator;
use WilyCode\Template\TemplateLoader;
use WilyCode\Template\TemplateEngine;

require __DIR__ . '/../vendor/autoload.php';

$locator = new FileLocator([
    '' => __DIR__ . '/views',
]);

$loader = new TemplateLoader($locator);
$engine = new TemplateEngine($loader, __DIR__ . '/cache/views');
$engine->addFilter('upper', 'mb_strtoupper');
$engine->addFilter('lower', 'mb_strtolower');

echo $engine->render('home', [
    'title' => 'Test @set',
    'name' => 'Simon',
    'loggedIn' => true,
    'items' => ['One', 'Two', 'Three'],
]);