/* public/js/suggestions_geocodage.js */
/*
  PLAN (suggestions_geocodage.js) :
  1) Fournir un moteur réutilisable de suggestions (API : /api/geocodage/adresse)
  2) Créer/afficher/masquer un panneau sous un champ
  3) Sélection d’une suggestion remplit le champ
  4) Remplir latitude/longitude 
  5) Gérer debounce + requêtes concurrentes + fermeture (Escape / clic hors panneau)
*/

(function () {
  "use strict";

  function id(x) {
    return document.getElementById(x);
  }

  function normaliserTexte(s) {
    return (s || "").trim();
  }

  function estNombre(n) {
    return typeof n === "number" && Number.isFinite(n);
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

  const cacheSuggestions = new Map();

  function cleCache(texte, limite) {
    return String(limite) + "|" + texte;
  }

  async function chercherSuggestions(texte, limite) {
    const t = normaliserTexte(texte);
    if (t === "") {
      return [];
    }

    const l = Math.max(1, Math.min(20, (Number.isFinite(limite) ? limite : 6) | 0));
    const cle = cleCache(t, l);

    if (cacheSuggestions.has(cle)) {
      return cacheSuggestions.get(cle);
    }

    const url =
      "/api/geocodage/adresse?q=" +
      encodeURIComponent(t) +
      "&limite=" +
      encodeURIComponent(String(l));

    const reponse = await fetch(url, { headers: { Accept: "application/json" } });
    if (!reponse.ok) {
      return [];
    }

    const data = await reponse.json();
    const suggestions = data && Array.isArray(data.suggestions) ? data.suggestions : [];

    cacheSuggestions.set(cle, suggestions);
    return suggestions;
  }

  const panneauxActifs = new Set();

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") return;

    panneauxActifs.forEach(function (panneau) {
      masquerPanneau(panneau);
    });
  });

  function configurerSuggestions(options) {
    const idChamp = options && options.idChamp ? String(options.idChamp) : "";
    if (!idChamp) return;

    const champ = id(idChamp);
    if (!champ) return;

    const idPanneau = options.idPanneau ? String(options.idPanneau) : "suggestions_" + idChamp;
    const limite = Number.isFinite(options.limite) ? (options.limite | 0) : 6;
    const minCaracteres = Number.isFinite(options.minCaracteres) ? (options.minCaracteres | 0) : 2;

    const champContexte = options.idChampContexte ? id(options.idChampContexte) : null;

    const champLatitude = options.idLatitude ? id(options.idLatitude) : null;
    const champLongitude = options.idLongitude ? id(options.idLongitude) : null;

    const filtreTypes = Array.isArray(options.filtreTypes) ? options.filtreTypes : null;

    const construireTexteRecherche =
      typeof options.construireTexteRecherche === "function"
        ? options.construireTexteRecherche
        : function (valeur, contexte) {
            if (contexte) return valeur + ", " + contexte;
            return valeur;
          };

    const panneau = creerPanneauSuggestions(idPanneau);
    champ.insertAdjacentElement("afterend", panneau);
    panneauxActifs.add(panneau);

    let minuteur = null;
    let requeteEnCours = 0;
    let dernierTexte = "";
    let dernierLimite = 0;

    function viderCoordonnees() {
      if (champLatitude) champLatitude.value = "";
      if (champLongitude) champLongitude.value = "";
    }

    function definirCoordonnees(latitude, longitude) {
      if (!champLatitude || !champLongitude) return;
      champLatitude.value = String(latitude);
      champLongitude.value = String(longitude);
    }

    function planifierRecherche() {
      if (minuteur) clearTimeout(minuteur);
      minuteur = setTimeout(lancerRecherche, 200);
    }

    async function lancerRecherche() {
      const valeur = normaliserTexte(champ.value);
      const contexte = champContexte ? normaliserTexte(champContexte.value) : "";

      if (valeur.length < Math.max(1, minCaracteres)) {
        masquerPanneau(panneau);
        viderCoordonnees();
        dernierTexte = "";
        dernierLimite = 0;
        return;
      }

      const texte = construireTexteRecherche(valeur, contexte);
      const limiteEffective = Math.max(1, Math.min(20, limite | 0));

      if (texte === dernierTexte && limiteEffective === dernierLimite) {
        return;
      }
      dernierTexte = texte;
      dernierLimite = limiteEffective;

      const numeroRequete = ++requeteEnCours;

      try {
        const suggestions = await chercherSuggestions(texte, limiteEffective);

        if (numeroRequete !== requeteEnCours) return;

        panneau.innerHTML = "";

        const suggestionsFiltrees = (suggestions || []).filter(function (s) {
          const libelle = normaliserTexte(s.libelle);
          if (libelle === "") return false;

          if (filtreTypes) {
            const type = normaliserTexte(s.type);
            if (type && !filtreTypes.includes(type)) return false;
          }

          return true;
        });

        if (suggestionsFiltrees.length === 0) {
          masquerPanneau(panneau);
          viderCoordonnees();
          return;
        }

        suggestionsFiltrees.forEach(function (s) {
          const libelle = normaliserTexte(s.libelle);

          const bouton = document.createElement("button");
          bouton.type = "button";
          bouton.className = "suggestion_item";
          bouton.textContent = libelle;

          bouton.addEventListener("mousedown", function (e) {
            e.preventDefault();

            champ.value = libelle;

            if (champLatitude && champLongitude) {
              const latitude = s.latitude;
              const longitude = s.longitude;

              if (estNombre(latitude) && estNombre(longitude)) {
                definirCoordonnees(latitude, longitude);
              } else {
                viderCoordonnees();
              }
            }

            masquerPanneau(panneau);
            champ.focus();
          });

          panneau.appendChild(bouton);
        });

        if (panneau.childElementCount === 0) {
          masquerPanneau(panneau);
          viderCoordonnees();
          return;
        }

        panneau.hidden = false;
        panneau.classList.add("is_visible");
      } catch (_e) {
        masquerPanneau(panneau);
        viderCoordonnees();
      }
    }

    document.addEventListener("mousedown", function (event) {
      const cible = event.target;
      if (!cible) return;

      if (cible === champ || panneau.contains(cible)) return;

      masquerPanneau(panneau);
    });

    champ.addEventListener("input", function () {
      viderCoordonnees();
      planifierRecherche();
    });

    champ.addEventListener("focus", function () {
      planifierRecherche();
    });

    if (champContexte) {
      champContexte.addEventListener("input", function () {
        viderCoordonnees();
        planifierRecherche();
      });
      champContexte.addEventListener("change", function () {
        viderCoordonnees();
        planifierRecherche();
      });
    }
  }

  window.ecoride_geocodage = {
    configurerSuggestions: configurerSuggestions,
  };
})();
