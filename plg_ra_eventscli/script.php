<?php

/**
 * @version    1.0.0
 * @package    plg_ra_eventscli
 * @copyright  2026 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Ensures this plugin is enabled after install/update, so its console
 * commands (e.g. ra_events:sendemails) are available without a manual
 * step in the Plugin Manager.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Plgconsolera_eventscliInstallerScript {

    public function postflight($type, $parent) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('console'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('ra_eventscli'));
        $db->setQuery($query);
        $db->execute();
        return true;
    }

}
