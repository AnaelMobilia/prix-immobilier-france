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

define("__BASE_PATH__", dirname(__FILE__) . "/../");

// Gestion du chargement des classes
spl_autoload_register(
    function ($class) {
        if (is_file(__BASE_PATH__ . 'classes/' . $class . '.php')) {
            // Fichiers du site
            include __BASE_PATH__ . 'classes/' . $class . '.php';
        } elseif (is_file(
            __BASE_PATH__ . 'libs/martinez-rueda-php/' . str_replace('MartinezRueda\\', '', $class) . '.php'
        )) {
            // Classe martinez-rueda-php
            include(__BASE_PATH__ . 'libs/martinez-rueda-php/' . str_replace('MartinezRueda\\', '', $class) . '.php');
        } elseif (is_file(
            __BASE_PATH__ . 'libs/RDP-PHP/' . str_replace('davidredgar\polyline\\', '', $class) . '.php'
        )) {
            // Classe RDP-PHP
            include(__BASE_PATH__ . 'libs/RDP-PHP/' . str_replace('davidredgar\polyline\\', '', $class) . '.php');
        }
    }
);