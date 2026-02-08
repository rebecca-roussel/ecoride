<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MentionsLegalesController extends AbstractController
{
    #[Route('/mentions-legales', name: 'mentions_legales')]
    public function index(): Response
    {
        return $this->render('mentions_legales/index.html.twig');
    }
}
