<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.1
 * @license     GNU General Public License version 2 or later
 *
 * Install script. On a fresh installation it takes over the settings of the
 * two plugins this one replaces (FG Admin Login Customizer and FG Admin Page
 * Customizer), switches this plugin on and the old ones off - so a site keeps
 * its look without a manual import. The old plugins are only disabled, never
 * uninstalled. Nothing here can make the installation fail.
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use FG\Plugin\System\AdminCustom\Helper\LegacyMapper;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

return new class () implements InstallerScriptInterface {
    private const ELEMENT = 'fgadmincustom';
    private const LOGIN   = 'fgadminlogincustom';
    private const PAGE    = 'fgadminpagecustom';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Only a fresh installation migrates; an update never touches saved settings.
        if ($type !== 'install' && $type !== 'discover_install') {
            return true;
        }

        try {
            $this->loadClasses();
            $this->migrate();
        } catch (\Throwable $e) {
            $this->message(
                'PLG_SYSTEM_FGADMINCUSTOM_MIGRATION_FAILED',
                'The settings of the old FG Admin Login/Page Customizer plugins could not be copied (%s). Nothing was changed; paste their Export JSON into the Import field of this plugin instead.',
                'warning',
                [$e->getMessage()]
            );
        }

        return true;
    }

    /**
     * The plugin's autoloader may not be registered yet while its own
     * installation runs, so the few classes needed here are loaded directly.
     */
    private function loadClasses(): void
    {
        foreach (['Schema', 'ColorHelper', 'StyleContext', 'LegacyMapper'] as $class) {
            if (!class_exists('FG\\Plugin\\System\\AdminCustom\\Helper\\' . $class, false)) {
                require_once __DIR__ . '/src/Helper/' . $class . '.php';
            }
        }
    }

    private function migrate(): void
    {
        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $rows = $this->fetchRows($db, [self::LOGIN, self::PAGE, self::ELEMENT]);

        $login = $rows[self::LOGIN] ?? null;
        $page  = $rows[self::PAGE] ?? null;
        $own   = $rows[self::ELEMENT] ?? null;

        if (($login === null && $page === null) || $own === null) {
            return;
        }

        $loginOn = $login !== null && (int) $login['enabled'] === 1;
        $pageOn  = $page !== null && (int) $page['enabled'] === 1;

        $result = LegacyMapper::fromLegacy(
            $login === null ? null : $this->decode((string) $login['params']),
            $page === null ? null : $this->decode((string) $page['params']),
            $loginOn,
            $pageOn
        );

        // Saved settings of the old plugins on top of the defaults the installer just stored.
        $params = array_merge($this->decode((string) $own['params']), $result['params']);

        // Switch this plugin on only when an old plugin was doing something.
        $enable = $loginOn || $pageOn;

        $this->save($db, $params, $enable);

        // Only after the new settings are safely stored: the old plugins step aside.
        $this->disableOld($db, array_values(array_filter([
            $login === null ? null : self::LOGIN,
            $page === null ? null : self::PAGE,
        ])));

        if ($enable) {
            $this->message(
                'PLG_SYSTEM_FGADMINCUSTOM_MIGRATION_DONE',
                'The settings of FG Admin Login Customizer and FG Admin Page Customizer were copied into this plugin and it was switched on. The old plugins were switched off, not uninstalled - uninstall them once everything works.',
                'message'
            );
        } else {
            $this->message(
                'PLG_SYSTEM_FGADMINCUSTOM_MIGRATION_DONE_OFF',
                'The settings of FG Admin Login Customizer and FG Admin Page Customizer were copied into this plugin. Both were disabled, so this plugin was left disabled as well.',
                'message'
            );
        }

        if (\in_array('GENERATOR_WAS_ON', $result['notes'], true) && !$this->isEnabled($db, 'fgremovegenerator')) {
            $this->message(
                'PLG_SYSTEM_FGADMINCUSTOM_MIGRATION_GENERATOR',
                'FG Admin Login Customizer used to remove the generator meta tag from the login page. This plugin does not; install and enable the plugin FG Remove Generator to keep it hidden.',
                'warning'
            );
        }

        if (\in_array('LOGIN_BRAND_ISOLATED', $result['notes'], true)) {
            $this->message(
                'PLG_SYSTEM_FGADMINCUSTOM_MIGRATION_ISOLATED',
                'The login page did not share the brand settings of the administrator before, so "Use Brand settings" is off for it (tab Login: Card & colors). Switch it on to give both the same look.',
                'notice'
            );
        }
    }

    /**
     * @param string[] $elements
     * @return array<string, array{element: string, params: string, enabled: int|string}>
     */
    private function fetchRows(DatabaseInterface $db, array $elements): array
    {
        $type   = 'plugin';
        $folder = 'system';
        $query  = $db->getQuery(true)
            ->select($db->quoteName(['element', 'params', 'enabled']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->whereIn($db->quoteName('element'), $elements, ParameterType::STRING)
            ->bind(':type', $type)
            ->bind(':folder', $folder);

        $out = [];

        foreach ((array) $db->setQuery($query)->loadAssocList() as $row) {
            $out[(string) $row['element']] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function save(DatabaseInterface $db, array $params, bool $enable): void
    {
        $json    = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $enabled = $enable ? 1 : 0;
        $type    = 'plugin';
        $folder  = 'system';
        $element = self::ELEMENT;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':params', $json)
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $element);

        if ($enable) {
            $query->set($db->quoteName('enabled') . ' = :enabled')->bind(':enabled', $enabled, ParameterType::INTEGER);
        }

        $db->setQuery($query)->execute();
    }

    /**
     * @param string[] $elements
     */
    private function disableOld(DatabaseInterface $db, array $elements): void
    {
        if ($elements === []) {
            return;
        }

        $type    = 'plugin';
        $folder  = 'system';
        $enabled = 0;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = :enabled')
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->whereIn($db->quoteName('element'), $elements, ParameterType::STRING)
            ->bind(':enabled', $enabled, ParameterType::INTEGER)
            ->bind(':type', $type)
            ->bind(':folder', $folder);

        $db->setQuery($query)->execute();
    }

    private function isEnabled(DatabaseInterface $db, string $element): bool
    {
        $rows = $this->fetchRows($db, [$element]);

        return isset($rows[$element]) && (int) $rows[$element]['enabled'] === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = $json === '' ? [] : json_decode($json, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Queues a message; the plugin's language files may not be loaded yet, so
     * an English fallback is used when the key does not translate.
     *
     * @param string[] $args
     */
    private function message(string $key, string $fallback, string $type, array $args = []): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_system_fgadmincustom', JPATH_ADMINISTRATOR);
        $lang->load('plg_system_fgadmincustom', JPATH_PLUGINS . '/system/' . self::ELEMENT);

        $text = Text::_($key);
        $text = $text === $key ? $fallback : $text;

        Factory::getApplication()->enqueueMessage($args === [] ? $text : vsprintf($text, $args), $type);
    }
};
