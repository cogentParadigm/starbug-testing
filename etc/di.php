<?php
namespace Starbug\Testing;

use function DI\autowire;
use function DI\get;

return [
  DirectDriver::class => autowire()
    ->constructorParameter('jar', get('http.cookie_jar'))
    ->constructorParameter('baseUrl', get("website_host"))
    ->constructorParameter('basePath', get("website_url")),
  WebDriverInterface::class => get(DirectDriver::class)
];
