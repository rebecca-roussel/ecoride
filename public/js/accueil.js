(function () {
  const bouton = document.querySelector(".bouton_menu");
  const nav = document.querySelector("#navigation_principale");

  if (!bouton || !nav) return;

  function ouvrir() {
    nav.setAttribute("data-ouvert", "true");
    bouton.setAttribute("aria-expanded", "true");
  }

  function fermer() {
    nav.setAttribute("data-ouvert", "false");
    bouton.setAttribute("aria-expanded", "false");
  }

  function basculer() {
    const ouvert = nav.getAttribute("data-ouvert") === "true";
    if (ouvert) fermer();
    else ouvrir();
  }

  bouton.addEventListener("click", basculer);

  // Fermer si clic en dehors
  document.addEventListener("click", function (e) {
    const ouvert = nav.getAttribute("data-ouvert") === "true";
    if (!ouvert) return;

    const clicDansMenu = nav.contains(e.target);
    const clicDansBouton = bouton.contains(e.target);

    if (!clicDansMenu && !clicDansBouton) fermer();
  });

  // Fermer avec Échap
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") fermer();
  });

  // Fermer après clic sur un lien (mobile)
  nav.querySelectorAll("a").forEach((lien) => {
    lien.addEventListener("click", fermer);
  });
})();
