<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;
use Rector\Symfony\Symfony73\Rector\Class_\GetFiltersAndFunctionsToAsTwigAttributeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // Machine-wide cap: at most 2 worker processes; generous timeout for slow (WSL2 / CI) hosts.
    ->withParallel(300, 2, 10)
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withComposerBased(symfony: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        __DIR__ . '/demo',
        __DIR__ . '/vendor',
        // Keep AbstractExtension + getFunctions() for broad Twig compatibility and YAML `twig.extension` tagging.
        GetFiltersAndFunctionsToAsTwigAttributeRector::class,
        // Public API: both subscribers are `final` but public contract classes; the rule drops `implements
        // EventSubscriberInterface` and the public static getSubscribedEvents() that callers and tests (and
        // services.yaml autoconfiguration, REQ-SF-003) rely on. Not changing that in a minor.
        EventSubscriberToAttributeRector::class => [
            __DIR__ . '/src/EventSubscriber/HotReloadResponseSubscriber.php',
            __DIR__ . '/src/EventSubscriber/HotReloadAssetSubscriber.php',
        ],
    ]);
