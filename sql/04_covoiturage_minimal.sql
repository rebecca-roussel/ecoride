CREATE TABLE IF NOT EXISTS covoiturage (
  id_covoiturage INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  ville_depart VARCHAR(80) NOT NULL,
  ville_arrivee VARCHAR(80) NOT NULL,
  date_heure_depart TIMESTAMPTZ NOT NULL,
  prix_credits INTEGER NOT NULL CHECK (prix_credits >= 0),
  cree_le TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
