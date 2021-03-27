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

class etalabDvf
{
    // PATH pour les fichiers
    private const basePath = __BASE_PATH__ . "datas/" . self::class . "/";
    private const pathDepartements = self::basePath . "departements/";
    private const pathRegions = self::basePath . "regions/";

    // URL de l'API
    private const apiBaseUrl = "https://cadastre.data.gouv.fr/data/etalab-dvf/latest/csv/";
    private const apiUrlParamDepartements = "/departements/";
    private const apiEndUrl = ".csv.gz";

    // Départements non concernés
    public const departementsHs = [57, 67, 68, 976];

    /**
     * Chemin du fichier de liste des ventes
     * @param int $annee Année
     * @param string $departement Département
     * @return string CSV
     */
    public static function getFileListeVentes(string $departement, int $annee): string
    {
        if (!in_array($departement, self::departementsHs)) {
            return self::pathDepartements . $departement . "-" . $annee;
        }
        return "";
    }

    /**
     * Télécharger les données pour un département et une année
     * @param int $annee Année
     * @param string $departement Département
     */
    public static function telechargerVentes(int $annee, string $departement): void
    {
        if (!in_array($departement, self::departementsHs)) {
            // URL de l'API à appeler
            $url = self::apiBaseUrl . $annee . self::apiUrlParamDepartements . $departement . self::apiEndUrl;
            // Fichier de stockage
            $fichier = self::pathDepartements . $departement . "-" . $annee;

            echo "DVF " . $annee . "/" . $departement;
            api::telecharger($url, $fichier, true);
        }
    }
}