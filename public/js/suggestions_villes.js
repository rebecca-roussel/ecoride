/* public/js/suggestions_villes.js */

/*
  Ce script ajoute des suggestions de villes sur les champs "départ" et "arrivée".

  Fonctionnement général :
  - l’utilisateur saisit une ville dans un champ ;
  - JavaScript attend un très court délai pour éviter d’envoyer une requête à chaque touche ;
  - le script appelle la route Symfony /api/geocodage/adresse avec fetch() ;
  - la route renvoie des suggestions au format JSON ;
  - JavaScript affiche les villes proposées dans un panneau sous le champ ;
  - quand l’utilisateur clique sur une suggestion, le champ est rempli automatiquement.
*/

(function () {
  "use strict";

  /*
    Fonction utilitaire courte.

    Au lieu d’écrire plusieurs fois :
    document.getElementById("depart")

    le code peut écrire :
    id("depart")

    Cela rend les appels plus courts, surtout quand le script manipule plusieurs champs.
  */
  function id(x) {
    return document.getElementById(x);
  }

  /*
    Nettoie un texte saisi ou reçu.

    (s || "") évite une erreur si la valeur est vide, null ou undefined.
    trim() retire les espaces au début et à la fin.

    Exemple :
    "  Annemasse  " devient "Annemasse".
  */
  function normaliserTexte(s) {
    return (s || "").trim();
  }

  /*
    Crée le panneau HTML qui contiendra les suggestions.

    Ce panneau n’existe pas dans le fichier Twig au départ.
    Il est créé directement par JavaScript, puis inséré sous le champ concerné.

    hidden = true signifie que le panneau est caché au chargement.
  */
  function creerPanneauSuggestions(idPanneau) {
    const panneau = document.createElement("div");
    panneau.id = idPanneau;
    panneau.className = "panneau_suggestions";
    panneau.hidden = true;
    return panneau;
  }

  /*
    Cache le panneau de suggestions et le vide.

    innerHTML = "" supprime les anciens boutons de suggestions.
    Cela évite de garder des résultats qui ne correspondent plus à la saisie actuelle.
  */
  function masquerPanneau(panneau) {
    panneau.hidden = true;
    panneau.innerHTML = "";
    panneau.classList.remove("is_visible");
  }

  /*
    Appelle la route Symfony qui interroge le service de géocodage.

    fetch() lance une requête HTTP depuis le navigateur.
    Ici, la requête demande une réponse JSON.

    Le paramètre q contient le texte saisi.
    Le paramètre limite indique le nombre maximum de suggestions souhaitées.

    encodeURIComponent protège les caractères spéciaux dans l’URL.
    Par exemple, un espace ou un accent est transformé dans un format compatible avec une URL.
  */
  async function chercherSuggestions(texte, limite) {
    const url =
      "/api/geocodage/adresse?q=" +
      encodeURIComponent(texte) +
      "&limite=" +
      encodeURIComponent(String(limite));

    const reponse = await fetch(url, { headers: { Accept: "application/json" } });

    /*
      Si la réponse HTTP n’est pas correcte, le script renvoie une liste vide.
      Cela évite d’afficher une erreur technique à l’utilisateur.
    */
    if (!reponse.ok) return [];

    /*
      La réponse est transformée en objet JavaScript.
      Le code attend une structure du type :
      {
        suggestions: [...]
      }
    */
    const data = await reponse.json();

    /*
      Sécurité défensive côté navigateur :
      si la donnée reçue n’a pas le format attendu, le script renvoie une liste vide.
    */
    if (!data || !Array.isArray(data.suggestions)) return [];

    return data.suggestions;
  }

  /*
    Extrait seulement le nom de la ville à partir du libellé complet.

    Le service de géocodage peut renvoyer un libellé plus long,
    par exemple :
    "Gare Annemasse, 74100 Annemasse"

    Le code découpe le texte sur les virgules et garde le dernier morceau.
    Il retire aussi un éventuel code postal placé au début.
  */
  function extraireVille(libelle) {
    const s = normaliserTexte(libelle);
    if (!s) return "";

    const morceaux = s.split(",");
    const dernier = normaliserTexte(morceaux[morceaux.length - 1]);

    /*
      Retire un code postal de 4 ou 5 chiffres au début du texte.

      Exemple :
      "74100 Annemasse" devient "Annemasse".
    */
    const sansCp = dernier.replace(/^\d{4,5}\s+/, "");

    return normaliserTexte(sansCp);
  }

  /*
    Fonction principale.

    Elle configure le système de suggestions pour un champ donné.
    Comme elle reçoit des options, elle peut être réutilisée pour le champ départ
    et pour le champ arrivée.
  */
  function configurerSuggestionsVille(options) {
    const champ = id(options.idChamp);

    /*
      Si le champ n’existe pas dans la page, le script s’arrête pour ce champ.
      Cela permet de charger le fichier sans casser les autres pages.
    */
    if (!champ) return;

    /*
      Prépare les paramètres de configuration.

      idPanneau :
      identifiant HTML du panneau de suggestions.

      limite :
      nombre maximum de suggestions affichées.

      minCaracteres :
      nombre minimum de caractères avant de lancer une recherche.
      Cela évite d’interroger l’API pour une saisie trop courte.
    */
    const idPanneau = options.idPanneau || "suggestions_" + options.idChamp;
    const limite = Number.isFinite(options.limite) ? options.limite : 8;
    const minCaracteres = Number.isFinite(options.minCaracteres) ? options.minCaracteres : 2;

    /*
      Crée le panneau de suggestions et l’insère juste après le champ.
    */
    const panneau = creerPanneauSuggestions(idPanneau);
    champ.insertAdjacentElement("afterend", panneau);

    /*
      minuteur sert à attendre un court délai avant d’appeler l’API.
      C’est une temporisation.

      requeteEnCours sert à éviter les réponses mélangées.
      Si l’utilisateur tape vite, plusieurs requêtes peuvent partir.
      Le compteur permet de garder seulement la réponse de la dernière requête.
    */
    let minuteur = null;
    let requeteEnCours = 0;

    /*
      Planifie la recherche.

      À chaque saisie, l’ancien minuteur est annulé.
      Un nouveau minuteur est lancé.

      Résultat :
      le navigateur attend 180 ms avant d’appeler la route.
      Cela évite d’envoyer une requête à chaque touche pressée.
    */
    function planifierRecherche() {
      if (minuteur) clearTimeout(minuteur);
      minuteur = setTimeout(lancerRecherche, 180);
    }

    /*
      Lance réellement la recherche de suggestions.
      Cette fonction est asynchrone car elle attend la réponse de fetch().
    */
    async function lancerRecherche() {
      const valeur = normaliserTexte(champ.value);

      /*
        Si la saisie est trop courte, on cache le panneau.
        Cela évite d’afficher des résultats trop vagues.
      */
      if (valeur.length < minCaracteres) {
        masquerPanneau(panneau);
        return;
      }

      /*
        On augmente le compteur avant chaque nouvelle requête.

        numeroRequete garde le numéro de la requête actuelle.
        Plus bas, le code vérifiera si cette requête est encore la plus récente.
      */
      const numeroRequete = ++requeteEnCours;

      try {
        const suggestions = await chercherSuggestions(valeur, limite);

        /*
          Si une requête plus récente a été lancée pendant l’attente,
          on ignore cette réponse.

          Cela évite un bug classique :
          une ancienne réponse arrive après une nouvelle et remplace les bons résultats.
        */
        if (numeroRequete !== requeteEnCours) return;

        /*
          On vide le panneau avant d’ajouter les nouvelles suggestions.
        */
        panneau.innerHTML = "";

        /*
          Set permet de mémoriser les villes déjà affichées.
          Cela évite les doublons dans la liste.
        */
        const vues = new Set();

        /*
          Parcourt les suggestions reçues depuis la route Symfony.
        */
        (suggestions || []).forEach(function (s) {
          /*
            Si le service fournit un type, on garde seulement les types proches d’une ville.

            Cela évite d’afficher des rues, des bâtiments ou des adresses trop précises
            quand le champ attend surtout une ville.
          */
          const type = normaliserTexte(s.type).toLowerCase();

          if (type && !["municipality", "city", "town", "village", "administrative"].includes(type)) {
            return;
          }

          /*
            Récupère un nom de ville propre à partir du libellé.
          */
          const ville = extraireVille(s.libelle);
          if (!ville) return;

          /*
            Prépare une clé en minuscules pour détecter les doublons.

            Exemple :
            "Annemasse" et "annemasse" seront considérés comme la même ville.
          */
          const cle = ville.toLowerCase();

          if (vues.has(cle)) return;
          vues.add(cle);

          /*
            Crée un bouton pour la suggestion.

            Un bouton est plus adapté qu’un simple texte cliquable,
            car il représente une action utilisateur.
          */
          const bouton = document.createElement("button");
          bouton.type = "button";
          bouton.className = "suggestion_item";
          bouton.textContent = ville;

          /*
            mousedown est utilisé avant le blur du champ.

            Si on utilisait seulement click, le champ pourrait perdre le focus,
            le panneau pourrait se fermer, puis le clic ne serait pas toujours pris en compte.
          */
          bouton.addEventListener("mousedown", function (e) {
            e.preventDefault();

            /*
              Quand l’utilisateur choisit une ville,
              le champ prend cette valeur.
            */
            champ.value = ville;

            /*
              Le panneau est caché après le choix.
            */
            masquerPanneau(panneau);

            /*
              Le focus revient sur le champ pour garder un comportement fluide.
            */
            champ.focus();
          });

          /*
            Ajoute le bouton dans le panneau de suggestions.
          */
          panneau.appendChild(bouton);
        });

        /*
          Si aucune suggestion utilisable n’a été ajoutée,
          le panneau reste caché.
        */
        if (panneau.childElementCount === 0) {
          masquerPanneau(panneau);
          return;
        }

        /*
          Affiche le panneau quand au moins une suggestion existe.
        */
        panneau.hidden = false;
        panneau.classList.add("is_visible");
      } catch (_e) {
        /*
          En cas d’erreur réseau ou d’erreur JSON,
          le script cache simplement le panneau.

          L’utilisateur peut continuer à saisir sa ville manuellement.
        */
        masquerPanneau(panneau);
      }
    }

    /*
      Ferme le panneau quand l’utilisateur clique ailleurs dans la page.

      Si le clic concerne le champ ou le panneau lui-même,
      on garde les suggestions visibles.
    */
    document.addEventListener("mousedown", function (event) {
      const cible = event.target;
      if (!cible) return;

      if (cible === champ || panneau.contains(cible)) return;

      masquerPanneau(panneau);
    });

    /*
      Lance une recherche quand l’utilisateur écrit dans le champ.
    */
    champ.addEventListener("input", planifierRecherche);

    /*
      Relance aussi une recherche quand l’utilisateur revient dans le champ.
      Cela permet de revoir les suggestions si une valeur est déjà saisie.
    */
    champ.addEventListener("focus", planifierRecherche);

    /*
      Quand le champ perd le focus, on ferme le panneau.

      Le setTimeout laisse d’abord le temps au clic sur une suggestion d’être traité.
      Sans ce délai très court, le panneau pourrait disparaître avant le choix.
    */
    champ.addEventListener("blur", function () {
      setTimeout(function () {
        const actif = document.activeElement;

        if (panneau.contains(actif)) return;

        masquerPanneau(panneau);
      }, 0);
    });
  }

  /*
    Application concrète du script.

    La même fonction est utilisée deux fois :
    - une fois pour le champ de départ ;
    - une fois pour le champ d’arrivée.

    Cela évite de dupliquer tout le code.
  */
  configurerSuggestionsVille({
    idChamp: "depart",
    idPanneau: "suggestions_ville_depart"
  });

  configurerSuggestionsVille({
    idChamp: "arrivee",
    idPanneau: "suggestions_ville_arrivee"
  });
})();