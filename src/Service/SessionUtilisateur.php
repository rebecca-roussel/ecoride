<?php
declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class SessionUtilisateur
{
    public function __construct(private RequestStack $requestStack) {}

    public function estConnecte(): bool
    {
        $session = $this->requestStack->getSession();
        return $session !== null && $session->has('utilisateur_id');
    }

    public function pseudo(): ?string
    {
        $session = $this->requestStack->getSession();
        return $session?->get('utilisateur_pseudo');
    }

    public function connecter(int $idUtilisateur, string $pseudo): void
    {
        $session = $this->requestStack->getSession();
        if ($session === null) {
            return;
        }
        $session->set('utilisateur_id', $idUtilisateur);
        $session->set('utilisateur_pseudo', $pseudo);
    }

    public function deconnecter(): void
    {
        $session = $this->requestStack->getSession();
        $session?->clear();
    }
}
