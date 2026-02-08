<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DetailsController extends AbstractController
{
    #[Route('/details', name: 'details')]
    public function index(): Response
    {
        return $this->render('details/index.html.twig');
    }
}
