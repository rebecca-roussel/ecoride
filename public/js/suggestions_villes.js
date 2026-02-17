/* public/js/suggestions_villes.js */

(function () {
  "use strict";

  function id(x) {
    return document.getElementById(x);
  }

  function normaliserTexte(s) {
    return (s || "").trim();
  }

  function creerPanneauSuggestions(idPanneau) {
    const panneau = document.createElement("div");
    panneau.id = idPanneau;
    panneau.className = "panneau_suggestions";
    panneau.hidden = true;
    return panneau;
  }

  function masquerPanneau(panneau) {
    panneau.hidden = true;
    panneau.innerHTML = "";
    panneau.classList.remove("is_visible");
  }

  async function chercherSuggestions(texte, limite) {
    const url =
      "/api/geocodage/adresse?q=" +
      encodeURIComponent(texte) +
      "&limite=" +
      encodeURIComponent(String(limite));

    const reponse = await fetch(url, { headers: { Accept: "application/json" } });
    if (!reponse.ok) return [];

    const data = await reponse.json();
    if (!data || !Array.isArray(data.suggestions)) return [];

    return data.suggestions;
  }

  function extraireVille(libelle) {

    const s = normaliserTexte(libelle);
    if (!s) return "";

    const morceaux = s.split(",");
    const dernier = normaliserTexte(morceaux[morceaux.length - 1]);

    // Retire un éventuel code postal au début du dernier morceau
    const sansCp = dernier.replace(/^\d{4,5}\s+/, "");

    return normaliserTexte(sansCp);
  }

  function configurerSuggestionsVille(options) {
    const champ = id(options.idChamp);
    if (!champ) return;

    const idPanneau = options.idPanneau || "suggestions_" + options.idChamp;
    const limite = Number.isFinite(options.limite) ? options.limite : 8;
    const minCaracteres = Number.isFinite(options.minCaracteres) ? options.minCaracteres : 2;

    const panneau = creerPanneauSuggestions(idPanneau);
    champ.insertAdjacentElement("afterend", panneau);

    let minuteur = null;
    let requeteEnCours = 0;

    function planifierRecherche() {
      if (minuteur) clearTimeout(minuteur);
      minuteur = setTimeout(lancerRecherche, 180); 
    }

    async function lancerRecherche() {
      const valeur = normaliserTexte(champ.value);

      if (valeur.length < minCaracteres) {
        masquerPanneau(panneau);
        return;
      }

      const numeroRequete = ++requeteEnCours;

      try {
        const suggestions = await chercherSuggestions(valeur, limite);

        if (numeroRequete !== requeteEnCours) return;

        panneau.innerHTML = "";

        const vues = new Set();

        (suggestions || []).forEach(function (s) {
          // On tente d'utiliser le type si le service le fournit,
          // sinon on retombe sur l’extraction depuis le libellé.
          const type = normaliserTexte(s.type).toLowerCase();
          if (type && !["municipality", "city", "town", "village", "administrative"].includes(type)) {
            return;
          }

          const ville = extraireVille(s.libelle);
          if (!ville) return;

          const cle = ville.toLowerCase();
          if (vues.has(cle)) return;
          vues.add(cle);

          const bouton = document.createElement("button");
          bouton.type = "button";
          bouton.className = "suggestion_item";
          bouton.textContent = ville;

          bouton.addEventListener("mousedown", function (e) {
            e.preventDefault();
            champ.value = ville;
            masquerPanneau(panneau);
            champ.focus();
          });

          panneau.appendChild(bouton);
        });

        if (panneau.childElementCount === 0) {
          masquerPanneau(panneau);
          return;
        }

        panneau.hidden = false;
        panneau.classList.add("is_visible");
      } catch (_e) {
        masquerPanneau(panneau);
      }
    }

    document.addEventListener("mousedown", function (event) {
      const cible = event.target;
      if (!cible) return;
      if (cible === champ || panneau.contains(cible)) return;
      masquerPanneau(panneau);
    });

    champ.addEventListener("input", planifierRecherche);
    champ.addEventListener("focus", planifierRecherche);

    champ.addEventListener("blur", function () {
      setTimeout(function () {
        const actif = document.activeElement;
        if (panneau.contains(actif)) return;
        masquerPanneau(panneau);
      }, 0);
    });
  }

  configurerSuggestionsVille({ idChamp: "depart", idPanneau: "suggestions_ville_depart" });
  configurerSuggestionsVille({ idChamp: "arrivee", idPanneau: "suggestions_ville_arrivee" });
})();
