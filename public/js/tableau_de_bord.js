/* public/js/tableau_de_bord.js
   But : si je reviens sur cette page via "retour arrière",
   je force un rechargement pour récupérer la photo à jour.
*/

(function () {
  window.addEventListener("pageshow", function (event) {
    // event.persisted : la page vient du cache "retour arrière"
    if (event.persisted) {
      window.location.reload();
    }
  });
})();
