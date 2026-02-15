<?php
/*
 * prix-immobilier-france permet de visusaliser le prix de l'immobilier
 * en France.
 * Copyright (C) 2021 - 2026 - Anael MOBILIA
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
 * Ajax - Récupération des prix de ventes des communes d'un département
 */

require '../config/config.php';

$departement = $_REQUEST['departement'];
$typeBien = $_REQUEST['typeBien'];
$periode = $_REQUEST['periode'];
$supHab = $_REQUEST['supHab'] ?? '';
$supTerrain = $_REQUEST['supTerrain'] ?? '';
// Cas d'erreur
if (
    !preg_match('#^[0-9AB-]$#', $departement)
    || ($typeBien !== '' && !ctype_alnum($typeBien))
    || !preg_match('#^[0-9-]$#', $periode)
    || ($supHab !== '' && !preg_match('#^[0-9-]$#', $supHab))
    || ($supTerrain !== '' && !preg_match('#^[0-9-]$#', $supTerrain))
) {
    header('HTTP/1.1 404 Not Found');
    die('ERREUR');
}

// Traitement du fichier CSV
$datasCsv = etalabDvf::getListeVentes(explode('-', $departement), explode('-', $periode), $typeBien, explode('-', $supHab), explode('-', $supTerrain));

// Synthétiser les données par commune
$communes = [];
foreach (json_decode($datasCsv, false, 512, JSON_THROW_ON_ERROR) as $uneTransaction) {
    $codeCommune = $uneTransaction->code_commune;
    // Je créée la commune si nécessaire
    if (!isset($communes[$codeCommune])) {
        $communes[$codeCommune] = [
            'prix'              => 0,
            'superficieBien'    => 0,
            'superficieTerrain' => 0,
            'valeurs'           => 0,
        ];
    }
    // On somme les valeurs pour lisser les écarts avec une moyenne
    $communes[$codeCommune]['prix'] += $uneTransaction->valeur_fonciere;
    $communes[$codeCommune]['superficieBien'] += $uneTransaction->surface_reelle_bati;
    $communes[$codeCommune]['superficieTerrain'] += $uneTransaction->surface_terrain;
    $communes[$codeCommune]['valeurs']++;
}

// Calculer €/m² par commune + fourchette de prix
$tabPrixBien = [];
$tabPrixTerrain = [];

foreach ($communes as $codeCommune => $valeurs) {
    $prixBienM2 = 0;
    $prixTerrainM2 = 0;
    if ($valeurs['superficieBien'] > 0) {
        $prixBienM2 = (int) round($valeurs['prix'] / $valeurs['superficieBien']);
    }
    if ($valeurs['superficieTerrain'] > 0) {
        $prixTerrainM2 = (int) round($valeurs['prix'] / $valeurs['superficieTerrain']);
    }

    $communes[$codeCommune]['prixBienM2'] = $prixBienM2;
    $communes[$codeCommune]['prixTerrainM2'] = $prixTerrainM2;
    $communes[$codeCommune]['valeurs'] = $valeurs['valeurs'];

    // Liste des prix
    $tabPrixBien[] = $prixBienM2;
    $tabPrixTerrain[] = $prixTerrainM2;
}

// Calcul des bornes de prix au 5ème et 97ème percentile pour supprimer l'impact des extrêmes
$prixMinBien = heatmap::getMinValue($tabPrixBien);
$prixMaxBien = heatmap::getMaxValue($tabPrixBien);
$prixMinTerrain = heatmap::getMinValue($tabPrixTerrain);
$prixMaxTerrain = heatmap::getMaxValue($tabPrixTerrain);


// Calcul des résultats
$resultats = [];

// Informations de chaque commune
foreach ($communes as $codeCommune => $valeurs) {
    $tabTmp = [
        'codeInsee'      => $codeCommune,
        'prixBienM2'     => $valeurs['prixBienM2'],
        'prixTerrainM2'  => $valeurs['prixTerrainM2'],
        'couleurBien'    => '#'.heatmap::heatmapColor($valeurs['prixBienM2'], $prixMinBien, $prixMaxBien),
        'couleurTerrain' => '#'.heatmap::heatmapColor($valeurs['prixTerrainM2'], $prixMinTerrain, $prixMaxTerrain),
        'valeurs'        => $valeurs['valeurs'],
    ];
    $resultats[$codeCommune] = $tabTmp;
}

$resultats[0] = [
    'prixMinBien'    => $prixMinBien,
    'prixMaxBien'    => $prixMaxBien,
    'prixMinTerrain' => $prixMinTerrain,
    'prixMaxTerrain' => $prixMaxTerrain,
];

header('Content-Type: application/json');
echo json_encode($resultats, JSON_THROW_ON_ERROR);