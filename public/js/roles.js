// public/js/roles.js
/*
  Objectif :
  - autoriser ON/ON -> OFF/ON ou ON/OFF
  - interdire OFF/OFF
  - message visible seulement quand on bloque l’action
*/

document.addEventListener("DOMContentLoaded", function () {
  const formulaire = document.querySelector(".formulaire_roles");
  if (!formulaire) return;

  const casePassager = formulaire.querySelector('input[name="role_passager"]');
  const caseChauffeur = formulaire.querySelector('input[name="role_chauffeur"]');
  if (!casePassager || !caseChauffeur) return;

  // Zone message 
  let message = document.querySelector("#message_roles_js");
  if (!message) {
    message = document.createElement("div");
    message.id = "message_roles_js";
    message.className = "message_erreur";
    message.hidden = true;
    message.textContent = "Veuillez garder au moins un rôle.";
    formulaire.prepend(message);
  }

  function cacherMessage() {
    message.hidden = true;
  }

  function afficherMessage() {
    message.hidden = false;
  }

  function aucunRoleCoche() {
    return !casePassager.checked && !caseChauffeur.checked;
  }

  function proteger(event) {
    // Après le changement, si on arrive à 0 rôle, on corrige immédiatement.
    if (aucunRoleCoche()) {
      // On réactive la case que l’utilisateur vient de désactiver
      event.target.checked = true;
      afficherMessage();
      return;
    }

    // Sinon l'état est valide
    cacherMessage();
  }

  casePassager.addEventListener("change", proteger);
  caseChauffeur.addEventListener("change", proteger);

  // Au chargement, on cache le message 
  cacherMessage();
});

