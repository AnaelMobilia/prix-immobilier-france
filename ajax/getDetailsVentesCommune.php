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
 * Ajax - Récupérer les ventes d'une commune
 */

require "../config/config.php";

$departement = $_REQUEST["departement"];
$typeBien = $_REQUEST["typeBien"];
$codeCommune = $_REQUEST["codeCommune"];
$periode = $_REQUEST["periode"];
// Cas d'erreur
if (!ctype_alnum(str_replace('-', '', $departement))
    || (!empty($typeBien) && !ctype_alnum($typeBien))
    || !ctype_alnum($codeCommune)
    || !ctype_alnum(str_replace('-', '', $periode))
) {
    header("HTTP/1.1 404 Not Found");
    die("ERREUR");
}

// Traitement du fichier CSV
$datasCsv = etalabDvf::getListeVentes(explode('-', $departement), explode('-', $periode), $typeBien);

// Récupérer les données de la commune
$transactions = [];
$tabPrixBien = [];
foreach (json_decode($datasCsv) as $uneTransaction) {
    // Filtrer sur la commune
    if ($codeCommune !== $uneTransaction->code_commune) {
        continue;
    }
    $transactions[] = $uneTransaction;
    // Enregistrer la valeur du m²
    if ($uneTransaction->surface_reelle_bati > 0) {
        $tabPrixBien[] = (int)round($uneTransaction->valeur_fonciere / $uneTransaction->surface_reelle_bati);
    }
}

// Calcul des bornes de prix au 5ème et 97ème percentile pour supprimer l'impact des extrêmes
$prixMinBien = heatmap::getMinValue($tabPrixBien);
$prixMaxBien = heatmap::getMaxValue($tabPrixBien);

// Calcul des résultats
$resultats = [];
foreach ($transactions as $uneTransaction) {
    $prixBienM2 = (int)round($uneTransaction->valeur_fonciere / $uneTransaction->surface_reelle_bati);
    $uneTransaction->couleur = heatmap::heatmapColor($prixBienM2, $prixMinBien, $prixMaxBien);
    $uneTransaction->prixBienM2 = $prixBienM2;
    $uneTransaction->prixMinBien = $prixMinBien;
    $uneTransaction->prixMaxBien = $prixMaxBien;
    $resultats[] = $uneTransaction;
}

header('Content-Type: application/json');
echo json_encode($resultats);