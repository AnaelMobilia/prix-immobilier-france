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

class etalabDvf
{
    // PATH pour les fichiers
    private const basePath = __BASE_PATH__.'datas/'.self::class.'/';
    private const pathDepartements = self::basePath.'departements/';
    private const pathRegions = self::basePath.'regions/';

    // URL de l'API
    private const apiBaseUrl = 'https://files.data.gouv.fr/geo-dvf/latest/csv/';
    private const apiUrlParamDepartements = '/departements/';
    private const apiEndUrl = '.csv.gz';

    // Départements non concernés
    public const departementsHs = [57, 67, 68, 976];
    // Liste des locaux pris en compte (exclusion de "Local industriel. commercial ou assimilé")
    private const DVFtypeLocalAppart = 'Appartement';
    private const DVFtypeLocalMaison = 'Maison';
    private const DVFtypeLocal = ['', 'Dépendance', self::DVFtypeLocalAppart, self::DVFtypeLocalMaison];

    /**
     * Chemin du fichier de liste des ventes
     * @param int[] $annee Année
     * @param string[] $departement Département
     * @param string $typeBien Type de bien
     * @param int[] $superficeHabitable Superficie habitable
     * @param int[] $superficeTerrain Superficie du terrain
     * @return string JSON
     */
    public static function getListeVentes(array $departement, array $annee, string $typeBien, array $superficeHabitable, array $superficeTerrain): string
    {
        $monRetour = [];

        // Superficie Habitable
        $checkSuperficieHabitable = false;
        if (isset($superficeHabitable[0]) && is_numeric($superficeHabitable[0])) {
            $superficeHabitableMin = $superficeHabitable[0];
            $checkSuperficieHabitable = true;
        }
        if (isset($superficeHabitable[1]) && is_numeric($superficeHabitable[1])) {
            $superficeHabitableMax = $superficeHabitable[1];
            $checkSuperficieHabitable = true;
        }

        // Superficie du terrain
        $checkSuperficieTerrain = false;
        if (isset($superficeTerrain[0]) && is_numeric($superficeTerrain[0])) {
            $superficeTerrainMin = $superficeTerrain[0];
            $checkSuperficieTerrain = true;
        }
        if (isset($superficeTerrain[1]) && is_numeric($superficeTerrain[1])) {
            $superficeTerrainMax = $superficeTerrain[1];
            $checkSuperficieTerrain = true;
        }

        foreach ($departement as $unDep) {
            // Ne pas prendre les départements HS
            if (in_array($unDep, self::departementsHs, true)) {
                continue;
            }

            foreach ($annee as $uneAnee) {
                $path = self::pathDepartements.$unDep.'-'.$uneAnee;
                $datas = api::getContenuFichier($path);

                foreach (json_decode($datas, false, 512, JSON_THROW_ON_ERROR) as $uneMutation) {
                    // Filtre sur le type de bien
                    if ($typeBien !== '' && $typeBien !== $uneMutation->type_local) {
                        continue;
                    }
                    // Filter sur la surface réelle bâtie
                    if ($checkSuperficieHabitable) {
                        // Bien trop petit
                        if (isset($superficeHabitableMin) && $uneMutation->surface_reelle_bati < $superficeHabitableMin) {
                            continue;
                        }
                        // Bien trop grand
                        if (isset($superficeHabitableMax) && $uneMutation->surface_reelle_bati > $superficeHabitableMax) {
                            continue;
                        }
                    }
                    // Filter sur la surface du terrain
                    if ($checkSuperficieTerrain) {
                        // Terrain trop petit
                        if (isset($superficeTerrainMin) && $uneMutation->surface_terrain < $superficeTerrainMin) {
                            continue;
                        }
                        // Terrain trop grand
                        if (isset($superficeTerrainMax) && $uneMutation->surface_terrain > $superficeTerrainMax) {
                            continue;
                        }
                    }
                    $monRetour[] = $uneMutation;
                }
            }
        }

        usort($monRetour, function (object $a, object $b): int {
            // Avoir les mutations les plus récentes en premier
            return strcmp($a->date_mutation, $b->date_mutation) * -1;
        });

        return json_encode($monRetour, JSON_THROW_ON_ERROR);
    }

    /**
     * Télécharger les données pour un département et une année
     * @param int $annee Année
     * @param string $departement Département
     */
    public static function telechargerVentes(int $annee, string $departement): void
    {
        if (!in_array($departement, self::departementsHs, true)) {
            // URL de l'API à appeler
            $url = self::apiBaseUrl.$annee.self::apiUrlParamDepartements.$departement.self::apiEndUrl;
            // Fichier de stockage
            $fichierBrut = self::pathDepartements.$departement.'-'.$annee.'-brut';
            $fichierCompile = self::pathDepartements.$departement.'-'.$annee;

            echo 'DVF '.$annee.'/'.$departement.'<br />';
            api::telecharger($url, $fichierBrut, true);

            // Tableau des mutations
            $tabMutations = [];     // Mutations
            $tabExclusions = [];    // ID des mutations à exclure
            // Compiler les données du fichier
            $handle = fopen($fichierBrut, 'rb');
            // Sauter la ligne des entêtes
            fgets($handle);
            while (($data = fgetcsv($handle)) !== false) {
                // ID de la mutation
                $idMutation = $data[0];

                // Mutation à ne pas prendre
                if (in_array($idMutation, $tabExclusions, true)) {
                    continue;
                }
                // Mutation en plusieurs parties -> récupérer les données
                if (isset($tabMutations[$idMutation])) {
                    $uneMutation = $tabMutations[$idMutation];
                } else {
                    // Nouvelle mutation
                    $uneMutation = [
                        'date_mutation'             => '',
                        'nature_mutation'           => '',
                        'valeur_fonciere'           => 0,
                        'code_commune'              => '',
                        'type_local'                => '',
                        'surface_reelle_bati'       => 0,
                        'nombre_pieces_principales' => 0,
                        'surface_terrain'           => 0,
                        'longitude'                 => '',
                        'latitude'                  => '',
                    ];
                }

                // Vérification du type_local
                if (
                    !in_array($data[30], self::DVFtypeLocal, true)
                    || ($uneMutation['type_local'] === self::DVFtypeLocalAppart && $data[30] === self::DVFtypeLocalMaison)
                    || ($uneMutation['type_local'] === self::DVFtypeLocalMaison && $data[30] === self::DVFtypeLocalAppart)
                ) {
                    // Mauvais type de local -> ignorer cette mutation
                    $tabExclusions[] = $idMutation;
                    // Supprimer les éventuelles données déjà enregistrées
                    if (isset($tabMutations[$idMutation])) {
                        unset($tabMutations[$idMutation]);
                    }
                    // Ligne suivante
                    continue;
                }

                // Récupérer les informations sur la mutation
                if ($uneMutation['date_mutation'] === '' && $data[1] !== '') {
                    $uneMutation['date_mutation'] = $data[1];
                }
                if ($uneMutation['nature_mutation'] === '' && $data[3] !== '') {
                    $uneMutation['nature_mutation'] = $data[3];
                }
                if ($uneMutation['valeur_fonciere'] === 0 && (int) $data[4] !== 0) {
                    $uneMutation['valeur_fonciere'] = (int) $data[4];
                }
                if ($uneMutation['code_commune'] === '' && $data[10] !== '') {
                    $uneMutation['code_commune'] = $data[10];
                }
                // On veut Appart ou Maison au final pour le select
                if ($uneMutation['type_local'] === '' && ($data[30] === self::DVFtypeLocalAppart || $data[30] === self::DVFtypeLocalMaison)) {
                    $uneMutation['type_local'] = $data[30];
                }
                if ((int) $data[31] !== 0) {
                    $uneMutation['surface_reelle_bati'] += (int) $data[31];
                }
                if ((int) $data[32] !== 0) {
                    $uneMutation['nombre_pieces_principales'] += (int) $data[32];
                }
                if ((int) $data[37] !== 0) {
                    $uneMutation['surface_terrain'] += (int) $data[37];
                }
                if ($uneMutation['longitude'] === '' && $data[38] !== '') {
                    $uneMutation['longitude'] = $data[38];
                }
                if ($uneMutation['latitude'] === '' && $data[39] !== '') {
                    $uneMutation['latitude'] = $data[39];
                }

                // Enregistrer la mutation
                $tabMutations[$idMutation] = $uneMutation;
            }
            fclose($handle);

            // Supprimer les lignes incohérentes
            $tabFinal = [];
            foreach ($tabMutations as $uneMutation) {
                if ($uneMutation['type_local'] === '') {
                    continue;
                }
                $tabFinal[] = $uneMutation;
            }
            // Enregistrer les données compilées
            file_put_contents($fichierCompile, json_encode($tabFinal, JSON_THROW_ON_ERROR));
            // Supprimer le fichier "brut"
            unlink($fichierBrut);
        }
    }

    /**
     * Liste des années disponibles
     * Règles https://www.data.gouv.fr/fr/datasets/r/d573456c-76eb-4276-b91c-e6b9c89d6656
     * @return string JSON
     */
    public static function getPossibleYears(): string
    {
        // 5 années de données
        // A partir de mai (Chaque année, une première diffusion sera effectuée en avril) => année en cours
        $anneeFin = (date('m') >= 5 ? date('Y') : date('Y') - 1);

        return json_encode(range($anneeFin - 4, $anneeFin), JSON_THROW_ON_ERROR);
    }
}