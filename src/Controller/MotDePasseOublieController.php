<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MotDePasseOublieController extends AbstractController
{
    #[Route('/mot_de_passe_oublie', name: 'mot_de_passe_oublie', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('mot_de_passe_oublie/index.html.twig');
    }
}
