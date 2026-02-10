/* public/js/connexion.js */
/*
  PLAN (erreur email) :

  1) Au chargement, je récupère le champ email et le message d'erreur.
  2) A chaque saisie, je vérifie le format HTML5 (validity).
  3) Si c'est valide -> je cache le message.
     Sinon -> je l'affiche (seulement si l'utilisateur a commencé à taper).
*/

(function () {
  const champEmail = document.querySelector("#email");
  const erreurEmail = document.querySelector("#erreur_email");

  if (!champEmail || !erreurEmail) {
    return;
  }

  champEmail.addEventListener("input", function () {
    const valeur = champEmail.value.trim();

    if (valeur === "") {
      erreurEmail.hidden = true;
      return;
    }

    if (champEmail.validity.valid) {
      erreurEmail.hidden = true;
    } else {
      erreurEmail.hidden = false;
    }
  });
})();
