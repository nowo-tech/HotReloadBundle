<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route(path: '/', name: 'homepage', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig', [
            'generatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'frankenphpHotReload' => $_SERVER['FRANKENPHP_HOT_RELOAD'] ?? null,
        ]);
    }

    #[Route(path: '/live', name: 'demo_live', methods: ['GET'])]
    public function live(): Response
    {
        return $this->render('demo/live.html.twig', [
            'generatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'hint' => 'Edit this template or DemoController and save — the browser should update via FrankenPHP Hot Reload.',
        ]);
    }

    #[Route(path: '/health.json', name: 'demo_health_json', methods: ['GET'])]
    public function healthJson(): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'hot_reload_env' => isset($_SERVER['FRANKENPHP_HOT_RELOAD']),
        ]);
    }
}
