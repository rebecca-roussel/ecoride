<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Centralise la création et la réutilisation de la connexion PDO vers PostgreSQL.
 *
 * Ce service joue le rôle de point d'entrée technique vers la base relationnelle.
 * Il évite à chaque service de persistance de recréer sa propre connexion et
 * garantit l'utilisation des mêmes options PDO dans toute l'application.
 *
 * Le fonctionnement repose sur une initialisation paresseuse :
 * la connexion n'est créée qu'au premier appel de la méthode obtenirPdo(),
 * puis elle est conservée dans l'instance du service pour être réutilisée.
 */
final class ConnexionPostgresql
{
    /**
     * Connexion PDO mémorisée après la première initialisation.
     *
     * Tant que cette propriété vaut null, aucune connexion n'a encore été créée.
     * Dès que PDO est instancié une première fois, cette même connexion est
     * renvoyée aux appels suivants.
     */
    private ?\PDO $pdo = null;

    /**
     * Fournit une connexion PDO prête à l'emploi pour PostgreSQL.
     *
     * La méthode commence par vérifier si une connexion existe déjà dans la
     * propriété $pdo. Si oui, elle la renvoie immédiatement. Sinon, elle lit
     * la configuration depuis les variables d'environnement, construit le DSN,
     * puis crée une nouvelle instance de PDO avec les options retenues pour EcoRide.
     *
     * Les options choisies sont importantes :
     * - ERRMODE_EXCEPTION : une erreur SQL déclenche une exception claire ;
     * - FETCH_ASSOC : les résultats sont récupérés sous forme de tableaux associatifs ;
     * - EMULATE_PREPARES à false : les requêtes préparées sont gérées par PostgreSQL.
     *
     * @return \PDO Connexion PDO configurée pour PostgreSQL.
     *
     * @throws \RuntimeException Quand la variable POSTGRES_PASSWORD est vide.
     * @throws \PDOException Quand la création de la connexion échoue.
     */
    public function obtenirPdo(): \PDO
    {
        // Si la connexion a déjà été créée plus tôt, on la réutilise telle quelle.
        // Cela évite de recréer inutilement un nouvel objet PDO.
        if ($this->pdo instanceof \PDO) {
            return $this->pdo;
        }

        // Lecture de la configuration de connexion.
        // Chaque appel demande une clé précise et prévoit une valeur de secours
        // si la variable d'environnement n'existe pas ou est vide.
        $hote = $this->lireEnv('POSTGRES_HOST', 'postgresql');
        $port = $this->lireEnv('POSTGRES_PORT', '5432');
        $base = $this->lireEnv('POSTGRES_DB', 'ecoride');
        $utilisateur = $this->lireEnv('POSTGRES_USER', 'ecoride');
        $motDePasse = $this->lireEnv('POSTGRES_PASSWORD', '');

        // Ici, on choisit d'échouer immédiatement si le mot de passe est vide.
        // Le but est d'obtenir une erreur explicite dès le départ plutôt qu'une
        // tentative de connexion obscure plus loin dans l'exécution.
        if ('' === $motDePasse) {
            throw new \RuntimeException('POSTGRES_PASSWORD est vide : la connexion PDO échouera.');
        }

        // Construction du DSN PostgreSQL.
        // Le DSN décrit la cible de connexion : hôte, port et base de données.
        // Le mot de passe n'est pas inclus ici ; il est transmis séparément à PDO.
        $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

        // Création réelle de la connexion PDO.
        // À partir de cette ligne, l'application dispose d'un point d'accès concret
        // à PostgreSQL avec les options définies pour le projet.
        $this->pdo = new \PDO(
            $dsn,
            $utilisateur,
            $motDePasse,
            [
                // Toute erreur SQL sera levée sous forme d'exception.
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,

                // Les fetch() renverront des tableaux associatifs :
                // ['colonne' => valeur] au lieu d'index numériques.
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,

                // On désactive l'émulation pour laisser PostgreSQL gérer
                // les requêtes préparées de manière native.
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $this->pdo;
    }

    /**
     * Lit une variable d'environnement et applique une valeur par défaut si nécessaire.
     *
     * Cette méthode centralise la lecture des paramètres de configuration afin
     * d'éviter de répéter le même contrôle partout dans la classe.
     *
     * Une valeur par défaut est renvoyée dans trois cas :
     * - la variable n'existe pas ;
     * - la variable vaut null ;
     * - la variable contient une chaîne vide ou uniquement des espaces.
     *
     * La lecture tente d'abord $_ENV puis getenv(), ce qui améliore la compatibilité
     * selon la manière dont l'environnement est injecté dans l'application.
     *
     * @param string $cle Nom de la variable d'environnement recherchée.
     * @param string $valeurParDefaut Valeur utilisée si la variable est absente ou vide.
     *
     * @return string Valeur exploitable par le service.
     */
    private function lireEnv(string $cle, string $valeurParDefaut): string
    {
        // On essaie d'abord $_ENV, puis getenv().
        // Si rien n'est trouvé, on obtient null.
        $valeur = $_ENV[$cle] ?? getenv($cle) ?: null;

        // trim() permet de considérer comme "vide" une chaîne qui ne contient
        // que des espaces, ce qui évite des faux paramètres valides.
        if (null === $valeur || '' === trim((string) $valeur)) {
            return $valeurParDefaut;
        }

        return (string) $valeur;
    }
}
