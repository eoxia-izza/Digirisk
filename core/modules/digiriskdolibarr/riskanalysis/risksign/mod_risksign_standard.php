<?php
/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       ore/modules/digiriskdolibarr/riskanalysis/risksign/mod_risksign_standard.php
 * \ingroup     digiriskdolibarr
 *	\brief      File containing class for risksign numbering module Standard
 */

require_once __DIR__ . '/../modules_riskanalysis.php';

/**
 * 	Class to manage risksign numbering rules Standard
 */
class mod_risksign_standard extends ModeleNumRefRiskAnalysis
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	/**
	 * @var string document prefix
	 */
	public $prefix = 'RS';

	/**
	 * @var string model name
	 */
	public $name = 'Narvi';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 *  Returns the description of the numbering model
	 *
	 *  @return     string      Texte descriptif
	 */
	public function info()
	{
		global $langs;
		$langs->load("digiriskdolibarr@digiriskdolibarr");
		return $langs->trans('DigiriskRiskSignStandardModel', $this->prefix);
	}

	/**
	 *  Return an example of numbering
	 *
	 *  @return     string      Example
	 */
	public function getExample()
	{
		return $this->prefix . "1";
	}

	/**
	 * 	Return next free value
	 *
	 *	@param Object $object Object we need next value for
	 * 	@return string                Value if KO, <0 if KO
	 * 	@throws Exception
	 */
	public function getNextValue($object)
	{
		global $db, $conf;

		// first we get the max value
		$posindice = strlen($this->prefix) + 1;
		$sql       = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $posindice . ") AS SIGNED)) as max";
		$sql      .= " FROM " . MAIN_DB_PREFIX . "digiriskdolibarr_risksign";
		$sql      .= " WHERE ref LIKE '" . $db->escape($this->prefix) . "%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = " . $conf->entity;
		}

		$resql = $db->query($sql);
		if ($resql) {
			$obj           = $db->fetch_object($resql);
			if ($obj) $max = intval($obj->max);
			else $max      = 0;
		} else {
			dol_syslog("mod_risksign_standard::getNextValue", LOG_DEBUG);
			return -1;
		}

		if ($max >= (pow(10, 4) - 1)) $num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		else $num                          = sprintf("%s", $max + 1);

		dol_syslog("mod_risksign_standard::getNextValue return " . $this->prefix . $num);
		return $this->prefix . $num;
	}

	/**
	 *    Return next free value
	 *
	 * @param Object $object Object we need next value for
	 * @return string                Value if KO, <0 if KO
	 * @throws Exception
	 */
	public function getLastValue($object)
	{
		global $db, $conf;

		// first we get the max value
		$posindice = strlen($this->prefix) + 1;
		$sql       = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $posindice . ") AS SIGNED)) as max";
		$sql      .= " FROM " . MAIN_DB_PREFIX . "digiriskdolibarr_risksign";
		$sql      .= " WHERE ref LIKE '" . $db->escape($this->prefix) . "%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = " . $conf->entity;
		}
		$sql .= " ORDER BY rowid DESC LIMIT 1";

		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
		} else {
			dol_syslog("mod_risksign_standard::getlastValue", LOG_DEBUG);
			return -1;
		}
		return $this->prefix . $obj->max;
	}
}
