/* public/js/connexion.js */
/*
  PLAN (connexion, confort UI) :

  1. Erreur email :
     - je récupère le champ email et le message d'erreur
     - à chaque saisie, je vérifie le format HTML5 (validity)
     - si c'est valide -> je cache le message
       sinon -> je l'affiche (seulement si l'utilisateur a commencé à taper)

  2. Mot de passe :
     - bouton "œil" -> bascule password/text
     - met aria-pressed + aria-label
*/

(function () {
  /* Email : erreur en direct */
  const champEmail = document.querySelector("#email");
  const erreurEmail = document.querySelector("#erreur_email");

  if (champEmail && erreurEmail) {
    champEmail.addEventListener("input", function () {
      const valeur = champEmail.value.trim();

      if (valeur === "") {
        erreurEmail.hidden = true;
        return;
      }

      erreurEmail.hidden = champEmail.validity.valid;
    });
  }

  /* Mot de passe : afficher / masquer */
  const champMotDePasse = document.querySelector("#mot_de_passe");
  const boutonOeil = document.querySelector("#toggle_mot_de_passe");

  if (champMotDePasse && boutonOeil) {
    boutonOeil.addEventListener("click", function () {
      const estMasque = (champMotDePasse.type === "password");
      champMotDePasse.type = estMasque ? "text" : "password";

      boutonOeil.setAttribute("aria-pressed", estMasque ? "true" : "false");
      boutonOeil.setAttribute(
        "aria-label",
        estMasque ? "Masquer le mot de passe" : "Afficher le mot de passe"
      );
    });
  }
})();
