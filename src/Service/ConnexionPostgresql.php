<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Gère la création de la connexion PDO vers PostgreSQL pour l'application.
 *
 * Pourquoi ce service existe :
 * au lieu d'écrire une connexion PDO dans chaque service qui échange avec la base,
 * on regroupe cette logique dans une seule classe. Comme ça, la création de la
 * connexion se fait toujours au même endroit.
 *
 * Démarche retenue :
 * on veut une connexion simple à appeler, réutilisable, et configurée de la
 * même manière dans tout le projet. L'idée est donc de créer PDO une seule fois,
 * puis de renvoyer cette même connexion quand un autre service en a besoin.
 *
 * Ce que ce service fait concrètement :
 * IL lit les variables d'environnement utiles à PostgreSQL depuis .env, il construit la
 * chaîne de connexion, il crée PDO avec les options retenues pour EcoRide,
 * puis on conserve cette connexion pour éviter de la recréer inutilement.
 */
final class ConnexionPostgresql
{
  /**
   * Conserve la connexion PDO déjà créée.
   *
   * Au départ, cette propriété vaut null, donc aucune connexion n'existe encore.
   * Dès que la méthode obtenirPdo() crée un objet PDO, il est stocké ici.
   * Les appels suivants réutilisent alors cette même instance.
   */
  private ?\PDO $pdo = null;

  /**
   * Retourne une connexion PDO prête à être utilisée.
   *
   * La logique retenue ici est simple :
   * si une connexion a déjà été créée auparavant, on la renvoie directement.
   * Sinon, il lit la configuration, prépare le DSN PostgreSQL, puis crée PDO.
   *
   * Pourquoi ce choix :
   * cela évite de répéter partout le même code de connexion et cela garantit
   * que tous les accès à PostgreSQL utilisent les mêmes paramètres.
   *
   * Options PDO utilisées :
   * - ATTR_ERRMODE => ERRMODE_EXCEPTION :
   *   une erreur SQL déclenche une exception, ce qui rend les problèmes visibles ;
   * - ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC :
   *   les résultats SQL sont récupérés sous forme de tableaux associatifs ;
   * - ATTR_EMULATE_PREPARES => false :
   *   on laisse PostgreSQL gérer les requêtes préparées.
   *
   * Point de sécurité retenu :
   * si le mot de passe PostgreSQL est vide, on arrête tout de suite avec une
   * RuntimeException. Le but est d'obtenir une erreur claire dès le départ
   * au lieu d'une connexion qui échoue plus loin de manière moins lisible.
   *
   * @return \PDO Connexion PDO réutilisable dans les services du projet.
   *
   * @throws \RuntimeException Si POSTGRES_PASSWORD est vide.
   * @throws \PDOException Si PDO n'arrive pas à se connecter à PostgreSQL.
   */
  public function obtenirPdo(): \PDO
  {
    // Si la connexion existe déjà, on la réutilise telle quelle.
    if ($this->pdo instanceof \PDO) {
      return $this->pdo;
    }

    // On récupère la configuration PostgreSQL depuis l'environnement.
    // Si une variable manque, une valeur par défaut est utilisée.
    $hote = $this->lireEnv('POSTGRES_HOST', 'postgresql');
    $port = $this->lireEnv('POSTGRES_PORT', '5432');
    $base = $this->lireEnv('POSTGRES_DB', 'ecoride');
    $utilisateur = $this->lireEnv('POSTGRES_USER', 'ecoride');
    $motDePasse = $this->lireEnv('POSTGRES_PASSWORD', '');

    // On préfère bloquer tout de suite si le mot de passe est vide.
    // Cela évite de lancer une tentative de connexion vouée à échouer.
    if ('' === $motDePasse) {
      throw new \RuntimeException('POSTGRES_PASSWORD est vide : la connexion PDO échouera.');
    }

    // On construit la chaîne DSN attendue par PDO pour PostgreSQL.
    $dsn = "pgsql:host={$hote};port={$port};dbname={$base}";

    // On crée enfin la connexion PDO avec les options retenues pour le projet.
    $this->pdo = new \PDO(
      $dsn,
      $utilisateur,
      $motDePasse,
      [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );

    return $this->pdo;
  }

  /**
   * Lit une variable d'environnement et renvoie une valeur de secours si besoin.
   *
   * Démarche retenue :
   * on centralise cette lecture dans une petite méthode dédiée au lieu de
   * répéter partout la même logique. Cela rend obtenirPdo() plus lisible.
   *
   * Ordre de recherche :
   * on regarde d'abord dans $_ENV, puis avec getenv().
   *
   * Si la variable est absente, nulle ou vide après nettoyage, la valeur
   * par défaut fournie en argument est renvoyée.
   *
   * @param string $cle Nom de la variable d'environnement à lire.
   * @param string $valeurParDefaut Valeur utilisée si la variable est absente ou vide.
   *
   * @return string Valeur exploitable pour construire la configuration PostgreSQL.
   */
  private function lireEnv(string $cle, string $valeurParDefaut): string
  {
    $valeur = $_ENV[$cle] ?? getenv($cle) ?: null;

    if (null === $valeur || '' === trim((string) $valeur)) {
      return $valeurParDefaut;
    }

    return (string) $valeur;
  }
}