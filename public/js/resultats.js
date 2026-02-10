document.addEventListener('DOMContentLoaded', function () {

    // Affichage ou masquage du formulaire de filtrage des résultats
    const boutonFiltre = document.querySelector('.bouton_principal');
    const formulaireFiltre = document.getElementById('filtre_formulaire');

    boutonFiltre.addEventListener('click', function() {
        // Afficher ou masquer le formulaire de filtre
        if (formulaireFiltre.style.display === 'none' || !formulaireFiltre.style.display) {
            formulaireFiltre.style.display = 'block';
        } else {
            formulaireFiltre.style.display = 'none';
        }
    });

    // Soumettre le formulaire de filtre via AJAX (sans recharger la page)
    const formulaire = document.getElementById('formulaire_filtre');
    if (formulaire) {
        formulaire.addEventListener('submit', function(e) {
            e.preventDefault();  // Empêcher le comportement par défaut (rechargement de la page)

            const formData = new FormData(formulaire);  // Récupérer les données du formulaire
            const url = window.location.href.split('?')[0] + '?' + new URLSearchParams(formData).toString();

            // Envoi de la requête AJAX pour récupérer les résultats filtrés
            fetch(url)
                .then(response => response.text())
                .then(data => {
                    // Remplacer le contenu principal de la page avec les nouveaux résultats
                    document.querySelector('main').innerHTML = data;
                })
                .catch(error => {
                    console.error("Erreur lors du chargement des résultats :", error);
                });
        });
    }
});
