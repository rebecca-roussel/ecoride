/* public/js/inscription.js */
/*
  PLAN (inscription, confort UI) :

  1. Photo :
     - clic sur "Ajouter une photo" -> ouvre le sélecteur de fichiers
     - si une image est choisie -> affiche une prévisualisation

  2. Mot de passe :
     - bouton "œil" -> bascule password/text
     - met aria-pressed + aria-label

  3. Confirmation :
     - compare mot de passe et confirmation en direct
     - affiche/masque l'erreur
*/

(function () {
  /* Prévisualisation de photo de profil */
  const boutonChoisirPhoto = document.querySelector("#bouton_choisir_photo");
  const champPhoto = document.querySelector("#photo_profil");
  const apercuPhoto = document.querySelector("#apercu_photo");
  const rondPhoto = document.querySelector(".photo_rond");

  if (boutonChoisirPhoto && champPhoto && apercuPhoto) {
    boutonChoisirPhoto.addEventListener("click", function () {
      champPhoto.click();
    });

    champPhoto.addEventListener("change", function () {
      const fichier = champPhoto.files && champPhoto.files[0];

      /* Cas 1 : si aucun fichier alors on revient à l'état "rond" */
      if (!fichier) {
        apercuPhoto.hidden = true;
        apercuPhoto.src = "";
        apercuPhoto.alt = "";

        if (rondPhoto) {
          rondPhoto.hidden = false;
        }
        return;
      }

      /* Cas 2 : si pas une image alors on revient aussi à l'état "rond" */
      if (!fichier.type || !fichier.type.startsWith("image/")) {
        apercuPhoto.hidden = true;
        apercuPhoto.src = "";
        apercuPhoto.alt = "";

        if (rondPhoto) {
          rondPhoto.hidden = false;
        }
        return;
      }

      /* Cas 3 : image valide alors on affiche l'aperçu et on cache le rond */
      const lecteur = new FileReader();
      lecteur.addEventListener("load", function () {
        apercuPhoto.src = String(lecteur.result);
        apercuPhoto.alt = "Aperçu de la photo de profil";
        apercuPhoto.hidden = false;

        if (rondPhoto) {
          rondPhoto.hidden = true;
        }
      });

      lecteur.readAsDataURL(fichier);
    });
  }

  /* Mot de passe : afficher/masquer */
  function brancherToggleMotDePasse(idChamp, idBouton, libelleAfficher, libelleMasquer) {
    const champ = document.querySelector(idChamp);
    const bouton = document.querySelector(idBouton);

    if (!champ || !bouton) {
      return;
    }

    bouton.addEventListener("click", function () {
      const estMasque = (champ.type === "password");
      champ.type = estMasque ? "text" : "password";

      bouton.setAttribute("aria-pressed", estMasque ? "true" : "false");
      bouton.setAttribute("aria-label", estMasque ? libelleMasquer : libelleAfficher);
    });
  }

  brancherToggleMotDePasse(
    "#mot_de_passe",
    "#toggle_mot_de_passe",
    "Afficher le mot de passe",
    "Masquer le mot de passe"
  );

  brancherToggleMotDePasse(
    "#mot_de_passe_confirmation",
    "#toggle_mot_de_passe_confirmation",
    "Afficher la confirmation",
    "Masquer la confirmation"
  );

  /* Confirmation du mot de passe et correspondance */
  const champMotDePasse = document.querySelector("#mot_de_passe");
  const champConfirmation = document.querySelector("#mot_de_passe_confirmation");
  const erreurConfirmation = document.querySelector("#erreur_confirmation");

  if (champMotDePasse && champConfirmation && erreurConfirmation) {
    function verifier() {
      const mdp = champMotDePasse.value;
      const confirmation = champConfirmation.value;

      if (confirmation === "") {
        erreurConfirmation.hidden = true;
        return;
      }

      erreurConfirmation.hidden = (mdp === confirmation);
    }

    champMotDePasse.addEventListener("input", verifier);
    champConfirmation.addEventListener("input", verifier);
  }
})();

