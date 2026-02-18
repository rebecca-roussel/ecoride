/* public/js/profil.js
   PLAN :
   1) Quand je choisis une photo
   2) J’envoie automatiquement le formulaire (upload direct)
*/

(function () {
  const champ = document.getElementById("photo_profil");
  if (!champ) {
    return;
  }

  champ.addEventListener("change", function () {
    if (!champ.files || champ.files.length === 0) {
      return;
    }

    const formulaire = champ.closest("form");
    if (formulaire) {
      formulaire.submit();
    }
  });
})();
