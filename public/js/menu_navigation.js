/* public/js/menu_navigation.js
  PLAN (menu mobile) :

  1) Mettre un bouton "Menu" en version mobile.

  2) Quand je clique sur "Menu" :
     - si la liste des liens n’est pas visible -> je la montre
     - si la liste des liens est visible -> je la cache

  3) Si je clique ailleurs sur la page :
     - je cache la liste des liens

  4) Si j’appuie sur la touche Échap :
     - je cache la liste des liens

  5) Si je clique sur un lien du menu :
     - je cache la liste des liens (pour revenir à la page)
*/

(function () {
  const boutonMenu = document.querySelector(".bouton_menu");
  const navigation = document.querySelector("#navigation_principale");

  // Sécurité : si le bouton ou la navigation n'existe pas, on arrête d'exécuter le script
  if (!boutonMenu || !navigation) return;

  function ouvrirMenu() {
    navigation.setAttribute("data-ouvert", "true");
    boutonMenu.setAttribute("aria-expanded", "true");
  }

  function fermerMenu() {
    navigation.setAttribute("data-ouvert", "false");
    boutonMenu.setAttribute("aria-expanded", "false");
  }

  function basculerMenu() {
    const estOuvert = navigation.getAttribute("data-ouvert") === "true";
    if (estOuvert) fermerMenu();
    else ouvrirMenu();
  }

  // Clic sur le bouton = ouvrir/fermer
  boutonMenu.addEventListener("click", function (e) {
    e.stopPropagation(); // évite que le clic "remonte" et déclenche la fermeture globale
    basculerMenu();
  });

  // Clic ailleurs = fermer si c'est ouvert
  document.addEventListener("click", function (e) {
    const estOuvert = navigation.getAttribute("data-ouvert") === "true";
    if (!estOuvert) return;

    const clicDansMenu = navigation.contains(e.target);
    const clicDansBouton = boutonMenu.contains(e.target);

    if (!clicDansMenu && !clicDansBouton) fermerMenu();
  });

  // Échap = fermer si c'est ouvert + remettre le focus sur le bouton
  document.addEventListener("keydown", function (e) {
    const estOuvert = navigation.getAttribute("data-ouvert") === "true";
    if (!estOuvert) return;

    if (e.key === "Escape") {
      fermerMenu();
      boutonMenu.focus();
    }
  });

  // Clic sur un lien = fermer
  navigation.querySelectorAll("a").forEach(function (lien) {
    lien.addEventListener("click", fermerMenu);
  });
})();
