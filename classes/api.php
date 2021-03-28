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
 * Gestion des appels aux API
 */
class api
{
    /**
     * Télécharger une ressource via l'API
     * @param string $url URL de la ressource
     * @param string $filename Path & nom du fichier
     * @param bool $estGz Est-ce un fichier .gz (le décompresser)
     * @return string|array données (string JSON encodé ou array de texte)
     */
    public static function telecharger(string $url, string $filename = "", bool $estGz = false): string|array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        // Gestion des fichiers gz
        if ($estGz) {
            // Fichier temporaire
            $fichier = tempnam(sys_get_temp_dir(), 'telecharger');
            // Enregistrer le résultat
            file_put_contents($fichier . ".gz", $result);
            // Décompresser le résultat
            $result = gzfile($fichier . ".gz");
            unlink($fichier . ".gz");
        }

        // Enregistrer le résultat
        if ($filename != "") {
            file_put_contents($filename, $result);
        }

        return $result;
    }

    /**
     * Récupérer le contenu d'un fichier
     * @param string $path Path du fichier
     * @return string Contenu
     */
    public static function getContenuFichier(string $path): string
    {
        $monRetour = json_encode([]);

        if (file_exists($path)) {
            $monRetour = file_get_contents($path);
        }

        return $monRetour;
    }

}