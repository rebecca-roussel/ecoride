/* public/js/mot_de_passe_oublie.js */
/*
  PLAN (erreur email) :

  1) Je récupère le champ email et le message d'erreur.
  2) A chaque saisie, je vérifie le format HTML5.
  3) Si c'est valide -> je cache, sinon j'affiche (si l'utilisateur a commencé à saisir).
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

    erreurEmail.hidden = champEmail.validity.valid;
  });
})();
