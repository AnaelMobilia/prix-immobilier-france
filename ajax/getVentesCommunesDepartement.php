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

// TODO : passage dans la classe ?
// TODO : gérer les codes arrondissements paris / marseille / lyon / ...

/**
 * Récupération des prix de ventes des communes d'un département
 */
require "../config/config.php";

/**
 * Traite un fichier CSV
 * @param int $annee Année des ventes
 * @param string $departement Département concerné
 * @param string $typeBien Type de bien (Maison / Appartement)
 * @param array $tableau Passage en argument - Valeurs récupérées
 */
function parseCSV(int $annee, string $departement, string $typeBien, array &$tableau)
{
    // Chemin du fichier sur le disque
    $fichier = etalabDvf::getFileListeVentes($departement, $annee);

    if (is_file($fichier)) {
        $handle = fopen($fichier, "r");
        while (($data = fgetcsv($handle)) !== false) {
            // Nature de mutation
            if (true) {
                if (substr($data[3], 0, 5) != "Vente") {
                    continue;
                }
            }
            // Type de local
            if ($typeBien != "" && $typeBien != "null") {
                if ($data[30] != $typeBien) {
                    continue;
                }
            }

            // https://sites.google.com/site/oplusimple/Home/outilspratiques/excel-1/excel
            // Caractéristiques
            $idTransaction = $data[0];
            $superficieBien = (int)$data[31];
            $superficieTerrain = (int)$data[37];
            $prix = (int)$data[4];
            $codeInsee = $data[10];

            if ($superficieBien > 0) {
                // Cette transaction concerne-t-elle plusieurs biens ?
                if (isset($datasCsv[$idTransaction])) {
                    // On additionne les superficies (le prix est global)
                    $superficieBien += $tableau[$idTransaction]["superficieBien"];
                    $superficieTerrain += $tableau[$idTransaction]["superficieTerrain"];
                }
                // Stocker le résultat
                $tableau[$idTransaction]["prix"] = $prix;
                $tableau[$idTransaction]["superficieBien"] = $superficieBien;
                $tableau[$idTransaction]["superficieTerrain"] = $superficieTerrain;
                $tableau[$idTransaction]["codeInsee"] = $codeInsee;
            }
        }
        fclose($handle);
    }
}

$departement = $_REQUEST["departement"];
$typeBien = $_REQUEST["typeBien"];
// Cas d'erreur
if (!ctype_alnum($departement) || (!empty($typeBien) && !ctype_alnum($typeBien))) {
    header("HTTP/1.1 404 Not Found");
    die("ERREUR");
}

// Traitement du fichier CSV
$datasCsv = [];
parseCSV(2020, $departement, $typeBien, $datasCsv);
parseCSV(2019, $departement, $typeBien, $datasCsv);

// Synthétiser les données par commune
$communes = [];
foreach ($datasCsv as $uneTransaction) {
    $codeInsee = $uneTransaction["codeInsee"];
    // Je créée la commune si nécessaire
    if (!isset($communes[$codeInsee])) {
        $communes[$codeInsee] = [
            "prix" => 0,
            "superficieBien" => 0,
            "superficieTerrain" => 0,
            "valeurs" => 0,
        ];
    }
    // On somme les valeurs pour lisser les écarts avec une moyenne
    $communes[$codeInsee]["prix"] += $uneTransaction["prix"];
    $communes[$codeInsee]["superficieBien"] += $uneTransaction["superficieBien"];
    $communes[$codeInsee]["superficieTerrain"] += $uneTransaction["superficieTerrain"];
    $communes[$codeInsee]["valeurs"]++;
}

// Calculer €/m² par commune + fourchette de prix
$prixMinBien = 999999;
$prixMaxBien = 0;
$prixMinTerrain = 999999;
$prixMaxTerrain = 0;
foreach ($communes as $codeInsee => $valeurs) {
    $prixBienM2 = 0;
    $prixTerrainM2 = 0;
    if ($valeurs["superficieBien"] > 0) {
        $prixBienM2 = (int)round($valeurs["prix"] / $valeurs["superficieBien"]);
    }
    if ($valeurs["superficieTerrain"] > 0) {
        $prixTerrainM2 = (int)round($valeurs["prix"] / $valeurs["superficieTerrain"]);
    }

    $communes[$codeInsee]["prixBienM2"] = $prixBienM2;
    $communes[$codeInsee]["prixTerrainM2"] = $prixTerrainM2;
    $communes[$codeInsee]["valeurs"] = $valeurs["valeurs"];

    // Fourchettes de prix - Exclusion des communes avec 1 seule valeur
    if ($valeurs["valeurs"] > 1) {
        if ($prixBienM2 > $prixMaxBien) {
            $prixMaxBien = $prixBienM2;
        }
        if ($prixBienM2 !== 0 && $prixBienM2 < $prixMinBien) {
            $prixMinBien = $prixBienM2;
        }
        if ($prixTerrainM2 > $prixMaxTerrain) {
            $prixMaxTerrain = $prixTerrainM2;
        }
        if ($prixTerrainM2 !== 0 && $prixTerrainM2 < $prixMinTerrain) {
            $prixMinTerrain = $prixTerrainM2;
        }
    }
}

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
foreach ($communes as $codeInsee => $valeurs) {
    $tabTmp = [
        "codeInsee" => $codeInsee,
        "prixBienM2" => $valeurs["prixBienM2"],
        "prixTerrainM2" => $valeurs["prixTerrainM2"],
        "couleurBien" => "#" . heatmapColor($valeurs["prixBienM2"], $prixMinBien, $prixMaxBien),
        "couleurTerrain" => "#" . heatmapColor($valeurs["prixTerrainM2"], $prixMinTerrain, $prixMaxTerrain),
        "valeurs" => $valeurs["valeurs"],
    ];
    $resultats[$codeInsee] = $tabTmp;
}

$resultats[0] = [
    "prixMinBien" => $prixMinBien,
    'prixMaxBien' => $prixMaxBien,
    "prixMinTerrain" => $prixMinTerrain,
    'prixMaxTerrain' => $prixMaxTerrain,
];

header('Content-Type: application/json');
echo json_encode($resultats);