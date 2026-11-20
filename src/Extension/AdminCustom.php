<?php

/**
 * @package     plg_system_fgadmincustom
 * @version     1.0.1
 * @license     GNU General Public License version 2 or later
 *
 * Visual customization of the Joomla administrator (Atum template, Joomla 5/6)
 * in two contexts that share one brand: the login page and the logged-in
 * administrator.
 *
 * The plugin class only decides which context a request belongs to and wires
 * the events; the CSS and HTML work is done by the helpers in Login/ and
 * Admin/. Every handler is wrapped so that no error in one context can ever
 * break a page, least of all the login page.
 */

declare(strict_types=1);

namespace FG\Plugin\System\AdminCustom\Extension;

use FG\Plugin\System\AdminCustom\Admin\CssBuilder as AdminCssBuilder;
use FG\Plugin\System\AdminCustom\Admin\LogoReplacer;
use FG\Plugin\System\AdminCustom\Admin\MenuScript;
use FG\Plugin\System\AdminCustom\Helper\ImportExport;
use FG\Plugin\System\AdminCustom\Helper\LegacyMapper;
use FG\Plugin\System\AdminCustom\Helper\MediaUrl;
use FG\Plugin\System\AdminCustom\Helper\SettingsSanitizer;
use FG\Plugin\System\AdminCustom\Helper\StyleContext;
use FG\Plugin\System\AdminCustom\Login\BodyModifier;
use FG\Plugin\System\AdminCustom\Login\CssBuilder as LoginCssBuilder;
use FG\Plugin\System\AdminCustom\Login\ThemePresets;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

final class AdminCustom extends CMSPlugin implements SubscriberInterface
{
    /** Shown in the generated CSS comment. */
    public const VERSION = '1.0.1';

    /** Value of the "schema" marker in the settings export. */
    public const EXPORT_SCHEMA = 'fgadmincustom/1';

    private const CONTEXT_LOGIN = 'login';
    private const CONTEXT_ADMIN = 'admin';

    /** Session key of the recovery switch of the logged-in administrator. */
    private const SAFE_STATE = 'plg_system_fgadmincustom.safe';

    /** Session key: elements of the replaced plugins the administrator was already warned about. */
    private const WARNED_STATE = 'plg_system_fgadmincustom.warned';

    /** Elements of the plugins this one replaces; both together with this one would style everything twice. */
    private const REPLACED_PLUGINS = [
        'fgadminlogincustom' => 'System - FG Admin Login Customizer',
        'fgadminpagecustom'  => 'System - FG Admin Page Customizer',
    ];

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterDispatch'      => 'announce',
            'onBeforeCompileHead'  => 'injectAssets',
            'onAfterRender'        => 'modifyBody',
            'onContentPrepareForm' => 'prepareAdminForm',
        ];
    }

    // ----------------------------------------------------------------------
    // Context
    // ----------------------------------------------------------------------

    /**
     * Which context the current request belongs to: the login page (guest on
     * com_login), a normal logged-in Atum administrator page (not an
     * iframe/popup view), or null (not ours: frontend, other templates ...).
     */
    private function context(): ?string
    {
        $app = $this->getApplication();

        if (!$app instanceof CMSApplicationInterface || !$app->isClient('administrator')) {
            return null;
        }

        $identity = $app->getIdentity();

        if ($identity === null || $identity->guest) {
            $option = $app->getInput()->getCmd('option', '');

            return $option === '' || $option === 'com_login' ? self::CONTEXT_LOGIN : null;
        }

        if ($app->getInput()->getCmd('tmpl', '') === 'component') {
            return null;
        }

        // Atum itself or a child template of Atum.
        try {
            $template = $app->getTemplate(true);
            $names    = [(string) ($template->template ?? ''), (string) ($template->parent ?? '')];
        } catch (\Throwable $e) {
            return null;
        }

        return \in_array('atum', $names, true) ? self::CONTEXT_ADMIN : null;
    }

    /**
     * Recovery switch, one parameter for both contexts.
     *
     * Login page: /administrator?fgac=off skips every customization for that
     * one request (a guest has nothing worth keeping state for).
     *
     * Logged-in administrator: ?fgac=off switches it off for the rest of the
     * session, ?fgac=on switches it back on - the state lives in the session
     * so that it survives the click from the plugin list to the plugin's own
     * settings. Nothing is ever saved.
     */
    private function isSafeMode(string $context, bool $announce = false): bool
    {
        $app   = $this->getApplication();
        $param = $app->getInput()->getCmd('fgac', '');

        if ($context === self::CONTEXT_LOGIN) {
            return $param === 'off';
        }

        if ($param === 'off' || $param === 'on') {
            $app->setUserState(self::SAFE_STATE, $param === 'off' ? 1 : 0);

            if ($announce && $param === 'off') {
                $this->loadLanguage();
                $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_SAFE_MODE_ACTIVE'), 'warning');
            }
        }

        return (int) $app->getUserState(self::SAFE_STATE, 0) === 1;
    }

    /**
     * Whether a context is switched on in the settings and not in safe mode.
     */
    private function isActive(string $context, bool $announce = false): bool
    {
        $switch = $context === self::CONTEXT_LOGIN ? 'login_enabled' : 'admin_enabled';

        return (int) $this->params->get($switch, 1) === 1 && !$this->isSafeMode($context, $announce);
    }

    /**
     * Errors in the customization must never break a page. They are written to
     * the Joomla log (category plg_system_fgadmincustom) and otherwise ignored.
     */
    private function fail(\Throwable $e): void
    {
        try {
            Log::add($e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine(), Log::WARNING, 'plg_system_fgadmincustom');
        } catch (\Throwable $ignored) {
            // Nothing else to do.
        }
    }

    // ----------------------------------------------------------------------
    // Page output
    // ----------------------------------------------------------------------

    /**
     * Messages for the logged-in administrator. They have to be queued here:
     * Joomla renders the message area before the page head, so a message
     * queued in onBeforeCompileHead (where the CSS is added) is too late and
     * is never shown.
     */
    public function announce(Event $event): void
    {
        try {
            if ($this->context() !== self::CONTEXT_ADMIN) {
                return;
            }

            // The recovery switch announces itself on the request that carries it.
            $this->isSafeMode(self::CONTEXT_ADMIN, true);
            $this->warnAboutReplacedPlugins();
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * Injects the inline CSS (and the optional scripts) of the current context.
     */
    public function injectAssets(Event $event): void
    {
        try {
            $context = $this->context();

            if ($context === null) {
                return;
            }

            $doc = $this->getApplication()->getDocument();

            if (!$doc instanceof HtmlDocument || !$this->isActive($context)) {
                return;
            }

            if ($context === self::CONTEXT_LOGIN) {
                $this->injectLogin($doc);
            } else {
                $this->injectAdmin($doc);
            }
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    /**
     * On any page of the logged-in administrator (not only on this plugin's own
     * settings): a replaced plugin is enabled. Each plugin is mentioned once per
     * session - and only once it really is enabled, so switching it on later in
     * the same session still warns.
     */
    private function warnAboutReplacedPlugins(): void
    {
        $app    = $this->getApplication();
        $warned = array_filter(explode(',', (string) $app->getUserState(self::WARNED_STATE, '')));
        $new    = [];

        foreach (self::REPLACED_PLUGINS as $replaced => $name) {
            if (!\in_array($replaced, $warned, true) && PluginHelper::isEnabled('system', $replaced)) {
                $this->loadLanguage();
                $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED', $name), 'warning');
                $new[] = $replaced;
            }
        }

        if ($new !== []) {
            $app->setUserState(self::WARNED_STATE, implode(',', array_merge($warned, $new)));
        }
    }

    private function warnReplacedPluginsEnabled(CMSApplicationInterface $app): void
    {
        foreach (self::REPLACED_PLUGINS as $replaced => $name) {
            if (PluginHelper::isEnabled('system', $replaced)) {
                $app->enqueueMessage(Text::sprintf('PLG_SYSTEM_FGADMINCUSTOM_REPLACED_PLUGIN_ENABLED', $name), 'warning');
            }
        }
    }

    private function injectLogin(HtmlDocument $doc): void
    {
        $wa  = $doc->getWebAssetManager();
        $css = (new LoginCssBuilder(StyleContext::login($this->params)))->build();

        if ($css !== '') {
            $wa->addInlineStyle(
                '/* FG Admin Customizer ' . self::VERSION . " - login */\n" . $css,
                ['name' => 'plg.system.fgadmincustom.login']
            );
        }

        $js = trim((string) $this->params->get('login_custom_js', ''));

        if ($js !== '') {
            $wa->addInlineScript($js, ['name' => 'plg.system.fgadmincustom.login.js'], ['defer' => true]);
        }
    }

    private function injectAdmin(HtmlDocument $doc): void
    {
        $wa  = $doc->getWebAssetManager();
        $css = (new AdminCssBuilder(StyleContext::admin($this->params)))->build();

        if ($css !== '') {
            $wa->addInlineStyle(
                '/* FG Admin Customizer ' . self::VERSION . " - admin */\n" . $css,
                ['name' => 'plg.system.fgadmincustom.admin']
            );
        }

        if ((int) $this->params->get('admin_menu_open_first', 0) === 1) {
            $wa->addInlineScript(
                MenuScript::build(),
                ['name' => 'plg.system.fgadmincustom.menu'],
                ['defer' => false]
            );
        }
    }

    /**
     * Server-side body modifications (no JS = no flicker): the login logo and
     * footer text, or the header logos of the administrator.
     */
    public function modifyBody(Event $event): void
    {
        try {
            $context = $this->context();

            if ($context === null || !$this->isActive($context)) {
                return;
            }

            $app  = $this->getApplication();
            $body = $app->getBody();

            if (!\is_string($body) || $body === '') {
                return;
            }

            if ($context === self::CONTEXT_LOGIN) {
                $newBody = BodyModifier::apply(
                    $body,
                    MediaUrl::clean((string) $this->params->get('login_logo', '')),
                    MediaUrl::clean((string) $this->params->get('login_logo_dark', '')),
                    trim((string) $this->params->get('login_footer_text', ''))
                );
            } else {
                $logos = [
                    'expanded'  => MediaUrl::clean((string) $this->params->get('admin_logo', '')),
                    'collapsed' => MediaUrl::clean((string) $this->params->get('admin_logo_small', '')),
                ];

                $newBody = $logos['expanded'] === '' && $logos['collapsed'] === ''
                    ? null
                    : LogoReplacer::replace($body, $logos);
            }

            if ($newBody !== null) {
                $app->setBody($newBody);
            }
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    // ----------------------------------------------------------------------
    // Plugin settings form: export, import, theme preset
    // ----------------------------------------------------------------------

    /**
     * Populates the read-only "Export" field with the plugin's current
     * settings as JSON, and applies a previously pasted+saved "Import" JSON
     * (or a chosen theme preset) onto the other fields' displayed values (the
     * admin still needs to hit Save again to persist the applied values).
     *
     * IMPORTANT: values are written onto the `data` argument, NOT via
     * `$form->setValue()`. onContentPrepareForm fires while the form is being
     * loaded, and the caller binds `$data` onto the form immediately
     * afterwards - that later bind() silently overwrites anything set directly
     * on the Form object. Writing onto `$data` (mutated in place, shape
     * preserved) is the reliable way to influence what is displayed.
     *
     * Deliberately does NOT use onExtensionBeforeSave: that event has a long
     * history of not firing reliably for plugin saves.
     *
     * A literal script tag in a field description would corrupt this form
     * (it broke the Save toolbar once, see the login plugin's history), so
     * descriptions never contain angle brackets.
     */
    public function prepareAdminForm(Event $event): void
    {
        try {
            $this->doPrepareAdminForm($event);
        } catch (\Throwable $e) {
            // Never break the admin page around the form.
            $this->fail($e);
        }
    }

    /**
     * @see prepareAdminForm()
     */
    private function doPrepareAdminForm(Event $event): void
    {
        $form = $event->getArgument('form');
        $data = $event->getArgument('data');

        if (!$form instanceof Form || $form->getName() !== 'com_plugins.plugin') {
            return;
        }

        $isDataArray = \is_array($data);
        $element     = $isDataArray ? ($data['element'] ?? '') : ($data->element ?? '');
        $folder      = $isDataArray ? ($data['folder'] ?? '') : ($data->folder ?? '');

        if ($element !== 'fgadmincustom' || $folder !== 'system') {
            return;
        }

        $app = $this->getApplication();

        // The plugins this one replaces would style everything a second time.
        $this->warnReplacedPluginsEnabled($app);

        $paramsData = $isDataArray ? ($data['params'] ?? null) : ($data->params ?? null);
        $params     = ImportExport::paramsToArray($paramsData);

        // ------------------------------------------------------------------
        // Export: always reflect the currently stored settings.
        // ------------------------------------------------------------------
        $exportable = $params;
        unset($exportable['export_json'], $exportable['import_json'], $exportable['login_theme_preset']);
        ksort($exportable);

        $updates = [
            'export_json' => json_encode(
                ['_schema' => self::EXPORT_SCHEMA] + $exportable,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
        ];

        // ------------------------------------------------------------------
        // Theme preset of the login page: a curated color combination.
        // ------------------------------------------------------------------
        $preset = trim((string) ($params['login_theme_preset'] ?? ''));

        if ($preset !== '') {
            $presets = ThemePresets::all();

            if (isset($presets[$preset])) {
                foreach ($presets[$preset] as $key => $value) {
                    $updates['login_' . $key] = $value;
                }

                $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_PRESET_APPLIED'), 'message');
            }

            $updates['login_theme_preset'] = '';
        }

        // ------------------------------------------------------------------
        // Import: a pasted JSON is applied onto the fields now and the field
        // is cleared so it does not reapply forever.
        // ------------------------------------------------------------------
        $importRaw = trim((string) ($params['import_json'] ?? ''));

        if ($importRaw !== '') {
            $updates = $this->applyImport($importRaw, $updates);
        }

        ImportExport::applyValuesToData($data, $updates);
    }

    /**
     * @param array<string, mixed> $updates
     * @return array<string, mixed>
     */
    private function applyImport(string $importRaw, array $updates): array
    {
        $app      = $this->getApplication();
        $imported = json_decode($importRaw, true);

        $updates['import_json'] = '';

        if (!\is_array($imported) || json_last_error() !== JSON_ERROR_NONE) {
            $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_INVALID_JSON'), 'warning');

            return $updates;
        }

        unset($imported['export_json'], $imported['import_json'], $imported['_schema']);

        // Never let imported JSON carry executable code into the login page. An
        // import is typically a settings blob passed between people/sites, so
        // the admin pasting it has usually not read every value in it, and the
        // custom script runs on the login screen where the username and
        // password fields live. Every other setting is declarative and safe.
        $skippedJs = \array_key_exists('custom_js', $imported) || \array_key_exists('login_custom_js', $imported);
        unset($imported['custom_js'], $imported['login_custom_js']);

        // JSON exported by one of the two plugins this one replaces.
        $legacy = LegacyMapper::fromLegacyJson($imported);

        if ($legacy !== null) {
            $imported = $legacy['params'];
            $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_LEGACY'), 'message');
        }

        // Only this plugin's own settings are accepted, each one validated.
        $result   = SettingsSanitizer::sanitize($imported);
        $updates += $result['values'];

        $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_APPLIED'), 'message');

        if ($result['rejected'] !== []) {
            $app->enqueueMessage(
                Text::sprintf('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_SKIPPED', implode(', ', $result['rejected'])),
                'warning'
            );
        }

        if ($skippedJs) {
            $app->enqueueMessage(Text::_('PLG_SYSTEM_FGADMINCUSTOM_IMPORT_JS_SKIPPED'), 'warning');
        }

        return $updates;
    }
}
