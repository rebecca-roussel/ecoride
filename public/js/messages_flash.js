/* public/js/messages_flash.js

   Ce fichier gère uniquement le comportement visuel des messages flash
   déjà affichés dans la page.

   Les messages flash sont :
   - créés côté Symfony ;
   - rendus dans le HTML par Twig ;
   - stylisés dans global.css.

   Ici, le JavaScript ne crée donc aucun message.
   Il repère simplement les messages déjà présents dans le DOM,
   attend quelques secondes,
   déclenche leur animation de disparition
   puis les retire de la page.

   PLAN :
   1) attendre que le HTML soit complètement chargé ;
   2) repérer tous les messages flash déjà présents dans la page ;
   3) appliquer un léger décalage entre plusieurs messages affichés en même temps ;
   4) lancer l'animation de disparition après 3 secondes ;
   5) retirer le message du DOM une fois l'animation terminée.
*/

document.addEventListener('DOMContentLoaded', () => {
  /*
   * À ce moment-là, le HTML de la page est chargé.
   * Les messages flash générés par Symfony puis affichés par Twig
   * existent donc déjà dans le DOM.
   *
   * On récupère uniquement les éléments qui portent
   * l'attribut data-message-flash.
   */
  const messages = document.querySelectorAll('[data-message-flash]');

  /*
   * On parcourt tous les messages trouvés.
   * "index" sert ici à décaler légèrement leur disparition
   * quand plusieurs messages sont affichés en même temps.
   */
  messages.forEach((message, index) => {
    /*
     * Délai avant disparition :
     * - 3000 ms = 3 secondes pour le premier message ;
     * - +150 ms par message suivant pour éviter
     *   que tout disparaisse exactement au même instant.
     */
    const delaiAvantDisparition = 3000 + (index * 150);

    /*
     * Premier timer :
     * après le délai prévu, on ajoute une classe CSS
     * qui déclenche l'animation de disparition
     * définie dans global.css.
     */
    window.setTimeout(() => {
      message.classList.add('message_flash_disparition');

      /*
       * Deuxième timer :
       * on laisse d'abord le temps à l'animation CSS de se jouer,
       * puis on retire réellement l'élément du DOM.
       *
       * Ici, 350 ms correspond à la durée de transition
       * définie dans le CSS.
       */
      window.setTimeout(() => {
        message.remove();
      }, 350);
    }, delaiAvantDisparition);
  });
});