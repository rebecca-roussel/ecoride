/* public/js/geolocalisation_publier.js */
/*
  PLAN (geolocalisation_publier.js) :
  1) Brancher les suggestions (villes + adresses) via suggestions_geocodage.js
  2) Pour les adresses : remplir latitude/longitude (champs hidden)
  3) Garde-fou au submit : si une adresse est saisie mais coords vides -> tentative de géocodage (limite=1)
*/

(function () {
  "use strict";

  function id(x) {
    return document.getElementById(x);
  }

  // 1) Sécurité : le moteur de suggestions doit être chargé avant
  if (
    !window.ecoride_geocodage ||
    typeof window.ecoride_geocodage.configurerSuggestions !== "function"
  ) {

    return;
  }

  const formulaire = document.querySelector("form.formulaire_publier");
  const adresseDepart = id("adresse_depart");
  const villeDepart = id("ville_depart");
  const adresseArrivee = id("adresse_arrivee");
  const villeArrivee = id("ville_arrivee");

  const latDepart = id("latitude_depart");
  const lonDepart = id("longitude_depart");
  const latArrivee = id("latitude_arrivee");
  const lonArrivee = id("longitude_arrivee");

  // 2) Villes : suggestions (sans coordonnées)
  window.ecoride_geocodage.configurerSuggestions({
    idChamp: "ville_depart",
    idPanneau: "suggestions_ville_depart",
    limite: 8,
    minCaracteres: 2,
    // On tente de limiter aux types “ville” quand l’API fournit un type.
    // (Si type vide, le moteur conserve le résultat.)
    filtreTypes: ["municipality", "city", "town", "village", "administrative"],
  });

  window.ecoride_geocodage.configurerSuggestions({
    idChamp: "ville_arrivee",
    idPanneau: "suggestions_ville_arrivee",
    limite: 8,
    minCaracteres: 2,
    filtreTypes: ["municipality", "city", "town", "village", "administrative"],
  });

  // 3) Adresses : suggestions + coordonnées (avec contexte = ville)
  window.ecoride_geocodage.configurerSuggestions({
    idChamp: "adresse_depart",
    idPanneau: "suggestions_depart",
    limite: 6,
    minCaracteres: 3,
    idChampContexte: "ville_depart",
    idLatitude: "latitude_depart",
    idLongitude: "longitude_depart",
  });

  window.ecoride_geocodage.configurerSuggestions({
    idChamp: "adresse_arrivee",
    idPanneau: "suggestions_arrivee",
    limite: 6,
    minCaracteres: 3,
    idChampContexte: "ville_arrivee",
    idLatitude: "latitude_arrivee",
    idLongitude: "longitude_arrivee",
  });

  // 4) Garde-fou si une adresse est saisie mais coords vides
  async function demanderPremierPoint(texte) {
    const url =
      "/api/geocodage/adresse?q=" +
      encodeURIComponent(texte) +
      "&limite=1";

    const reponse = await fetch(url, { headers: { Accept: "application/json" } });
    if (!reponse.ok) return null;

    const data = await reponse.json();
    if (!data || !Array.isArray(data.suggestions) || data.suggestions.length === 0) {
      return null;
    }

    const s = data.suggestions[0];
    if (typeof s.latitude !== "number" || typeof s.longitude !== "number") {
      return null;
    }

    return { latitude: s.latitude, longitude: s.longitude };
  }

  function construireTexteAdresse(adresse, ville) {
    const a = (adresse || "").trim();
    const v = (ville || "").trim();
    return v ? a + ", " + v : a;
  }

  function coordsVides(lat, lon) {
    return (lat.value || "").trim() === "" || (lon.value || "").trim() === "";
  }

  if (formulaire && adresseDepart && adresseArrivee && latDepart && lonDepart && latArrivee && lonArrivee) {
    formulaire.addEventListener("submit", async function (e) {
      const aDep = (adresseDepart.value || "").trim();
      const aArr = (adresseArrivee.value || "").trim();

      const besoinDepart = aDep.length >= 3 && coordsVides(latDepart, lonDepart);
      const besoinArrivee = aArr.length >= 3 && coordsVides(latArrivee, lonArrivee);

      if (!besoinDepart && !besoinArrivee) {
        return;
      }

      // On bloque l’envoi, on tente de compléter, puis on renvoie.
      e.preventDefault();

      try {
        if (besoinDepart) {
          const texte = construireTexteAdresse(aDep, villeDepart ? villeDepart.value : "");
          const p = await demanderPremierPoint(texte);
          if (p) {
            latDepart.value = String(p.latitude);
            lonDepart.value = String(p.longitude);
          }
        }

        if (besoinArrivee) {
          const texte = construireTexteAdresse(aArr, villeArrivee ? villeArrivee.value : "");
          const p = await demanderPremierPoint(texte);
          if (p) {
            latArrivee.value = String(p.latitude);
            lonArrivee.value = String(p.longitude);
          }
        }
      } finally {
        // On renvoie le formulaire quoi qu’il arrive :
        // - si coords trouvées : OK
        // - sinon : le contrôleur renverra une erreur “coordonnées incomplètes”
        formulaire.submit();
      }
    });
  }
})();
