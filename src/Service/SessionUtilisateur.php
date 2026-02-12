<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class SessionUtilisateur
{
    private const CLE_ID = 'utilisateur_id';
    private const CLE_PSEUDO = 'utilisateur_pseudo';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function estConnecte(): bool
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return false;
        }

        $id = $session->get(self::CLE_ID);

        if (is_int($id)) {
            return $id > 0;
        }

        if (is_string($id) && ctype_digit($id)) {
            return (int) $id > 0;
        }

        return false;
    }

    public function pseudo(): ?string
    {
        $session = $this->requestStack->getSession();

        $pseudo = $session?->get(self::CLE_PSEUDO);

        return is_string($pseudo) && $pseudo !== '' ? $pseudo : null;
    }

    public function connecter(int $idUtilisateur, string $pseudo): void
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return;
        }

        $session->set(self::CLE_ID, $idUtilisateur);
        $session->set(self::CLE_PSEUDO, $pseudo);
    }

    public function deconnecter(): void
    {
        $session = $this->requestStack->getSession();
        if (null === $session) {
            return;
        }

        $session->remove(self::CLE_ID);
        $session->remove(self::CLE_PSEUDO);
    }
}
