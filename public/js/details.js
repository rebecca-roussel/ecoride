document.addEventListener('DOMContentLoaded', () => {
  const boutonOuvrir = document.getElementById('bouton_ouvrir_confirmation_participation');
  const fenetre = document.getElementById('fenetre_confirmation_participation');
  const boutonFermer = document.getElementById('bouton_fermer_confirmation_participation');
  const caseConfirmation = document.getElementById('case_confirmation_credits');
  const boutonConfirmer = document.getElementById('bouton_confirmer_participation');

  if (!boutonOuvrir || !fenetre) return;

  boutonOuvrir.addEventListener('click', () => {
    if (typeof fenetre.showModal === 'function') fenetre.showModal();
  });

  boutonFermer?.addEventListener('click', () => fenetre.close());

  const maj = () => {
    if (!caseConfirmation || !boutonConfirmer) return;
    boutonConfirmer.disabled = !caseConfirmation.checked;
  };

  caseConfirmation?.addEventListener('change', maj);
  maj();
});