<?php
/*
 * prix-immobilier-france permet de visusaliser le prix de l'immobilier
 * en France.
 * Copyright (C) 2021 - Anael MOBILIA
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
 * Récupération des prix de ventes des communes d'un département
 */
require "../config/config.php";

$departement = $_REQUEST["departement"];
$typeBien = $_REQUEST["typeBien"];
$periode = $_REQUEST["periode"];
// Cas d'erreur
if (!ctype_alnum(str_replace('-', '', $departement))
    || (!empty($typeBien) && !ctype_alnum($typeBien))
    || !ctype_alnum(str_replace('-', '', $periode))
) {
    header("HTTP/1.1 404 Not Found");
    die("ERREUR");
}

// Traitement du fichier CSV
$datasCsv = etalabDvf::getListeVentes(explode('-', $departement), explode('-', $periode), $typeBien);
// Synthétiser les données par commune
$communes = [];
foreach (json_decode($datasCsv) as $uneTransaction) {
    $codeCommune = $uneTransaction->code_commune;
    // Je créée la commune si nécessaire
    if (!isset($communes[$codeCommune])) {
        $communes[$codeCommune] = [
            "prix" => 0,
            "superficieBien" => 0,
            "superficieTerrain" => 0,
            "valeurs" => 0,
        ];
    }
    // On somme les valeurs pour lisser les écarts avec une moyenne
    $communes[$codeCommune]["prix"] += $uneTransaction->valeur_fonciere;
    $communes[$codeCommune]["superficieBien"] += $uneTransaction->surface_reelle_bati;
    $communes[$codeCommune]["superficieTerrain"] += $uneTransaction->surface_terrain;
    $communes[$codeCommune]["valeurs"]++;
}

// Calculer €/m² par commune + fourchette de prix
$prixMinBien = 999999;
$prixMaxBien = 0;
$prixMinTerrain = 999999;
$prixMaxTerrain = 0;
$tabPrix = [];

foreach ($communes as $codeCommune => $valeurs) {
    $prixBienM2 = 0;
    $prixTerrainM2 = 0;
    if ($valeurs["superficieBien"] > 0) {
        $prixBienM2 = (int)round($valeurs["prix"] / $valeurs["superficieBien"]);
    }
    if ($valeurs["superficieTerrain"] > 0) {
        $prixTerrainM2 = (int)round($valeurs["prix"] / $valeurs["superficieTerrain"]);
    }

    $communes[$codeCommune]["prixBienM2"] = $prixBienM2;
    $communes[$codeCommune]["prixTerrainM2"] = $prixTerrainM2;
    $communes[$codeCommune]["valeurs"] = $valeurs["valeurs"];

    // Liste des prix
    $tabPrix[] = $prixBienM2;
}

// Trier la liste des prix
sort($tabPrix);

// Calcul des bornes de prix au 5ème et 97ème percentile pour supprimer l'impact des extrêmes
$prixMinBien = $tabPrix[floor(5/100*sizeof($tabPrix))];
$prixMaxBien = $tabPrix[floor(97/100*sizeof($tabPrix))];

/**
 * Calcule la valeur de la couleur pour la heatmap
 * @param int $value valeur actuelle
 * @param int $minValue valeur minimale
 * @param int $maxValue valeur maximale
 * @return string RGB à utiliser
 */
function heatmapColor(int $value, int $minValue, int $maxValue): string
{
    // Gestion des valeurs extrêmes
    if ($value <= $minValue) {
        return "00FF00";
    }
    if ($value >= $maxValue) {
        return "FF0000";
    }

    // On va de FF0000 à 00FF00 => 510 valeurs possibles
    // Calcul de la valeur d'un palier de couleur
    $palier = ($maxValue - $minValue) / 510;

    // Calcul du nombre de paliers à effectuer
    $nbPaliers = round(($value - $minValue) / $palier);

    if ($nbPaliers <= 255) {
        // Nuances de verts
        return "00" . dechex(255 - $nbPaliers) . "00";
    } else {
        // Nuances de rouge
        return dechex($nbPaliers - 255) . "0000";
    }
}


// Calcul des résultats
$resultats = [];

// Informations de chaque commune
foreach ($communes as $codeCommune => $valeurs) {
    $tabTmp = [
        "codeInsee" => $codeCommune,
        "prixBienM2" => $valeurs["prixBienM2"],
        "prixTerrainM2" => $valeurs["prixTerrainM2"],
        "couleurBien" => "#" . heatmapColor($valeurs["prixBienM2"], $prixMinBien, $prixMaxBien),
        "couleurTerrain" => "#" . heatmapColor($valeurs["prixTerrainM2"], $prixMinTerrain, $prixMaxTerrain),
        "valeurs" => $valeurs["valeurs"],
    ];
    $resultats[$codeCommune] = $tabTmp;
}

$resultats[0] = [
    "prixMinBien" => $prixMinBien,
    "prixMaxBien" => $prixMaxBien,
    "prixMinTerrain" => $prixMinTerrain,
    "prixMaxTerrain" => $prixMaxTerrain,
];

header('Content-Type: application/json');
echo json_encode($resultats);