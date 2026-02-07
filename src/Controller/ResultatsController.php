<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ResultatsController extends AbstractController
{
    #[Route('/resultats', name: 'resultats_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('resultats/index.html.twig');
    }
}
