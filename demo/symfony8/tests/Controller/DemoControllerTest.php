<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DemoControllerTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'FrankenPHP Hot Reload demo');
    }

    public function testLivePageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/live');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#generated-at');
    }

    public function testHealthJsonDoesNotInjectScript(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health.json');

        self::assertResponseIsSuccessful();
        self::assertSame('application/json', $client->getResponse()->headers->get('Content-Type'));
        self::assertStringNotContainsString('data-nowo-hot-reload', (string) $client->getResponse()->getContent());
    }
}
