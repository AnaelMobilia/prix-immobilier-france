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

use davidredgar\polyline\RDP;
use MartinezRueda\Algorithm;
use MartinezRueda\Polygon;

class geoApiGouvFr
{
    // TODO : générer les contours des départements et des régions
    // => Implique travaille sur l'algo d'union des polygones ?

    // PATH pour les fichiers
    private const basePath = __BASE_PATH__ . "datas/" . self::class . "/";
    private const pathDepartements = self::basePath . "departements/";
    private const pathRegions = self::basePath . "regions/";
    private const listeDepartements = self::pathDepartements . "liste";
    private const listeRegions = self::pathRegions . "liste";
    private const contoursDepartements = self::pathDepartements . "contours";
    private const contoursRegions = self::pathRegions . "contours";

    // Epsilon : simplification des contours des polygônes
    private const epsilonFactor = 0.003;

    // URL de l'API
    private const apiBaseUrl = "https://geo.api.gouv.fr/";
    private const apiUrlRegions = "regions/";
    private const apiUrlDepartements = "departements/";
    private const apiContoursCommunes = "communes?fields=contour,centre,surface,population";
    private const apiListeDepartements = "?fields=region";


    /**
     * Récupérer la liste des régions
     * @return string JSON
     */
    public static function getListeRegions(): string
    {
        return api::getContenuFichier(self::listeRegions);
    }

    /**
     * Récupérer la liste des départements
     * @param int $region Filtrer sur une région
     * @return string JSON
     */
    public static function getListeDepartements(int $region = 0): string
    {
        $datas = api::getContenuFichier(self::listeDepartements);
        $listeDep = [];
        foreach (json_decode($datas) as $unDep) {
            if ($region !== 0 && $unDep->region->code != $region) {
                // Filtrage sur une région
                continue;
            }
            // Définir si le département est actif ou non
            $actif = true;
            if (in_array($unDep->code, etalabDvf::departementsHs)) {
                $actif = false;
            }
            $unDep->actif = ($actif ? "1" : "0");
            $listeDep[] = $unDep;
        }
        return json_encode($listeDep);
    }

    /**
     * Récupérer la liste et contours des communes d'un département
     * @param array $departement Département(s) concerné(s)
     * @return string JSON
     */
    public static function getContoursCommunes(array $departement): string
    {
        $monRetour = [];
        foreach ($departement as $unDep) {
            $datas = api::getContenuFichier(self::pathDepartements . $unDep);
            foreach (json_decode($datas) as $uneCommune) {
                $monRetour[] = $uneCommune;
            }
        }
        return json_encode($monRetour);
    }

    /**
     * Récupérer les contours des régions
     * @return string JSON
     */
    public static function getContoursRegions(): string
    {
        return api::getContenuFichier(self::contoursRegions);
    }

    /**
     * Récupérer les contours des départements
     * @param int $region Filtrer sur une région
     * @param int $departement Filtrer sur un département
     * @return string JSON
     */
    public static function getContoursDepartements(int $region = 0, int $departement = 0): string
    {
        $datas = api::getContenuFichier(self::contoursDepartements);
        if ($region !== 0 || $departement !== 0) {
            // Filtrage sur un département ou une région
            $contourDep = [];
            foreach (json_decode($datas) as $unDep) {
                if ($unDep->region->code == $region || $unDep->code == $departement) {
                    $contourDep[$unDep->code] = $unDep;
                }
            }
            $result = json_encode($contourDep);
        } else {
            $result = $datas;
        }
        return $result;
    }

    /**
     * Mettre à jour les données (appel par tâche cron)
     */
    public static function mettreAJourLesDonnees()
    {
        // Mettre à jour la liste des régions
        echo "Liste des régions<br />";
        self::telechargerListeRegions();

        // Récupérer via l'API la liste des départements
        echo "Liste des département<br />";
        $jsonDepartements = self::telechargerListeDepartements();

        // Pour chaque département
        foreach (json_decode($jsonDepartements) as $unDep) {
            // Pause de 0,1 secondes pour ne pas saturer l'API en face
            usleep(100000);

            // Mettre à jour la liste des communes et les contours
            echo "Liste des communes de " . $unDep->nom . "<br />";
            self::telechargerContoursCommunes($unDep->code);
        }
    }

    /**
     * Regénérer les contours des départements et des régions
     * Mets vraiment trop de temps pour être viable... :-)
     */
    public static function genererContours()
    {
        // Contours des départements et régions
        $contoursDep = [];
        $contoursRegion = [];

        // Récupérer la liste des départements
        $jsonDepartements = self::getListeDepartements();

        // Pour chaque département
        foreach (json_decode($jsonDepartements) as $unDep) {
            // Préparer les contours des régions
            if (!isset($contoursRegion[$unDep->region->code])) {
                $contoursRegion[$unDep->region->code] = new Polygon([]);
            }
            // Récupérer la liste des communes et les contours
            $jsonCommunes = self::getListeCommunes($unDep->code);
            // Générer le contour du département
            $contourDep = new Polygon([]);
            foreach (json_decode($jsonCommunes) as $uneCommune) {
                // Charger le polygone de la commune
                $contourCommune = new Polygon($uneCommune->contour->coordinates);
                // L'associer au polygone du département
                $contourDep = (new Algorithm())->getUnion($contourDep, $contourCommune);
            }
            // Associer le polygone du département à sa région
            $contoursRegion[$unDep->region->code] = (new Algorithm())->getUnion(
                $contourDep,
                $contoursRegion[$unDep->region->code]
            );

            // Simplifier le contour du département
            $contoursDep[$unDep->code] = RDP::RamerDouglasPeucker2d($contourDep->toArray()[0], self::epsilonFactor);
        }
        // Enregistrer les contours des départements
        file_put_contents(self::contoursDepartements, json_encode($contoursDep));

        // Simplifier les contours des régions
        $resultRegion = [];
        foreach ($contoursRegion as $key => $uneRegion) {
            $resultRegion[$key] = RDP::RamerDouglasPeucker2d($uneRegion->toArray()[0], self::epsilonFactor);
        }
        // Enregistrer les contours des régions
        file_put_contents(self::contoursRegions, json_encode($resultRegion));
    }

    /**
     * Télécharger la liste des communes d'un département et leurs contours (stockage sur le disque)
     *
     * {
     *      "contour": {
     *          "type": "Polygon",
     *          "coordinates": [[
     *                  [4.904782, 46.160867],
     *                  [4.904844, 46.161078],
     *          ]]
     *      },
     *      "centre": {
     *          "type": "Point",
     *          "coordinates": [4.926, 46.1567]
     *      },
     *      "surface": 1566.86,
     *      "population": 767,
     *      "nom": "L'Abergement-Clémenciat",
     *      "code": "01001"
     * },
     * @param string $departement Numéro du département
     * @return string JSON de l'API
     */
    private static function telechargerContoursCommunes(string $departement): string
    {
        // URL de l'API à appeler
        $url = self::apiBaseUrl . self::apiUrlDepartements . $departement . '/' . self::apiContoursCommunes;
        // Fichier de stockage
        $fichier = self::pathDepartements . $departement;

        return api::telecharger($url, $fichier);
    }

    /**
     * Télécharger la liste des départements (stockage sur le disque)
     *
     * {
     *      "nom": "Ain",
     *      "code": "01",
     *      "region": {
     *          "code": "84",
     *          "nom": "Auvergne-Rhône-Alpes"
     *      }
     * }
     * @return string JSON de l'API
     */
    private static function telechargerListeDepartements(): string
    {
        // URL de l'API à appeler
        $url = self::apiBaseUrl . self::apiUrlDepartements . self::apiListeDepartements;
        // Fichier de stockage
        $fichier = self::listeDepartements;

        return api::telecharger($url, $fichier);
    }

    /**
     * Télécharger la liste des régions (stockage sur le disque)
     *
     * {
     *      "nom": "Guadeloupe",
     *      "code": "01"
     * }
     * @return string JSON de l'API
     */
    private static function telechargerListeRegions(): string
    {
        // URL de l'API à appeler
        $url = self::apiBaseUrl . self::apiUrlRegions;
        // Fichier de stockage
        $fichier = self::listeRegions;

        return api::telecharger($url, $fichier);
    }
}