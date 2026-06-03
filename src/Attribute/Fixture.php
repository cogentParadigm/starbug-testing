<?php
namespace Starbug\Testing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Fixture {

  public function __construct(
    public readonly string $path,
    public readonly string $type = 'yaml'
  ) {
  }
}
