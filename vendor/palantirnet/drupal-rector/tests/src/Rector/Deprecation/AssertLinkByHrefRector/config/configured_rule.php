<?php declare(strict_types=1);

use DrupalRector\Rector\Deprecation\AssertLinkByHrefRector;
use DrupalRector\Rector\Deprecation\AssertNoLinkByHrefRector;
use DrupalRector\Tests\Rector\Deprecation\DeprecationBase;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    DeprecationBase::addClass(AssertLinkByHrefRector::class, $rectorConfig);
    DeprecationBase::addClass(AssertNoLinkByHrefRector::class, $rectorConfig);
};
