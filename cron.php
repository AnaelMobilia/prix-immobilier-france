<?php
/*
 * prix-immobilier-france permet de visusaliser le prix de l'immobilier
 * en France.
 * Copyright (C) 2021 - 2022 - Anael MOBILIA
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Fichier appelé chaque semaine pour mettre à jour les sources de données
 */

require "config/config.php";

// Mise à jour des données "géographiques"
geoApiGouvFr::mettreAJourLesDonnees();

// Récupération des prix des dernières années pour chaque département
foreach (json_decode(geoApiGouvFr::getListeDepartements()) as $unDep) {
    foreach (json_decode(etalabDvf::getPossibleYears()) as $uneAnnee) {
        etalabDvf::telechargerVentes($uneAnnee, $unDep->code);
    }
}