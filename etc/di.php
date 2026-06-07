<?php
namespace Starbug\Testing;

use function DI\autowire;
use function DI\get;

return [
  DirectDriver::class => autowire()
    ->constructorParameter('jar', get('http.cookie_jar')),
  WebDriverInterface::class => get(DirectDriver::class),
  ShellDriverInterface::class => get(ShellDriver::class)
];
