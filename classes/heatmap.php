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
 * Gestion des cartes de chaleurs
 */
class heatmap
{
    /**
     * Valeur minimale pour la heatmap
     * @param array $tabPrix int[]
     * @return int  5ème percentile pour supprimer l'impact des extrêmes
     */
    public static function getMinValue(array $tabPrix): int
    {
        $monRetour = 0;
        if (sizeof($tabPrix) > 0) {
            // Trier la liste des prix
            sort($tabPrix);
            $monRetour = $tabPrix[floor(5 / 100 * sizeof($tabPrix))];
        }
        return $monRetour;
    }

    /**
     * Valeur maximale pour la heatmap
     * @param array $tabPrix int[]
     * @return int  97ème percentile pour supprimer l'impact des extrêmes
     */
    public static function getMaxValue(array $tabPrix): int
    {
        $monRetour = 0;
        if (sizeof($tabPrix) > 0) {
            // Trier la liste des prix
            sort($tabPrix);
            $monRetour = $tabPrix[floor(97 / 100 * sizeof($tabPrix))];
        }
        return $monRetour;
    }


    /**
     * Calcule la valeur de la couleur pour la heatmap
     * @param int $value valeur actuelle
     * @param int $minValue valeur minimale
     * @param int $maxValue valeur maximale
     * @return string RGB à utiliser
     */
    public static function heatmapColor(int $value, int $minValue, int $maxValue): string
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
}