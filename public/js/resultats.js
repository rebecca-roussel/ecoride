/* public/js/resultats.js
   Objectif :
   - clic sur "Filtrer les résultats" : afficher/masquer le panneau
   - Échap : fermer
   - clic en dehors : fermer 
*/

(function () {
  const bouton = document.querySelector("#bouton_filtrer");
  const panneau = document.querySelector("#panneau_filtres");

  if (!bouton || !panneau) {
    return;
  }

  function ouvrir() {
    panneau.hidden = false;
    bouton.setAttribute("aria-expanded", "true");

    const premierChamp = panneau.querySelector("input, select, textarea, button");
    if (premierChamp) {
      premierChamp.focus();
    }
  }

  function fermer() {
    panneau.hidden = true;
    bouton.setAttribute("aria-expanded", "false");
    bouton.focus();
  }

  function estOuvert() {
    return panneau.hidden === false;
  }

  bouton.addEventListener("click", function () {
    if (estOuvert()) {
      fermer();
    } else {
      ouvrir();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && estOuvert()) {
      fermer();
    }
  });

  // clic en dehors = fermer
  document.addEventListener("click", function (event) {
    if (!estOuvert()) {
      return;
    }

    const clicDansBouton = bouton.contains(event.target);
    const clicDansPanneau = panneau.contains(event.target);

    if (!clicDansBouton && !clicDansPanneau) {
      fermer();
    }
  });
})();

