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
 * Ajax - Récupérer le contenu d'un select
 */

require "../config/config.php";

$type = $_REQUEST["type"];
// Types possibles : departements, annees
if (!ctype_alnum($type)
    || !in_array($type, ["departements", "annees"])) {
    header("HTTP/1.1 404 Not Found");
    die("ERREUR");
}

header('Content-Type: application/json');
switch ($type) {
    case "departements":
        echo geoApiGouvFr::getListeDepartements();
        break;
    case "annees":
        echo etalabDvf::getPossibleYears();
        break;
}
