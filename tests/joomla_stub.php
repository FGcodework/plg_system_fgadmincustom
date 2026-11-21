<?php

/**
 * Minimal stand-ins for the Joomla classes the plugin class and the install
 * script touch. Just enough to run them without a Joomla installation.
 */

namespace Joomla\Event {
    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }

    class Event
    {
        public function __construct(private string $name = '', private array $args = [])
        {
        }

        public function getArgument(string $name, $default = null)
        {
            return $this->args[$name] ?? $default;
        }

        public array $results = [];

        public function addResult($data): static
        {
            $this->results[] = $data;

            return $this;
        }
    }
}

namespace Joomla\CMS\Application {
    interface CMSApplicationInterface
    {
    }
}

namespace Joomla\CMS\Document {
    class AssetManagerStub
    {
        public array $styles  = [];
        public array $scripts = [];

        public function addInlineStyle(string $content, array $options = [], array $attribs = []): static
        {
            $this->styles[(string) ($options['name'] ?? count($this->styles))] = $content;

            return $this;
        }

        public function addInlineScript(string $content, array $options = [], array $attribs = []): static
        {
            $this->scripts[(string) ($options['name'] ?? count($this->scripts))] = $content;

            return $this;
        }
    }

    class HtmlDocument
    {
        public AssetManagerStub $wa;

        public function __construct()
        {
            $this->wa = new AssetManagerStub();
        }

        public ?string $generator = 'Joomla! - Open Source Content Management';

        public function getWebAssetManager(): AssetManagerStub
        {
            return $this->wa;
        }

        public function setGenerator(string $generator): static
        {
            $this->generator = $generator;

            return $this;
        }
    }
}

namespace Joomla\CMS\Form {
    class Form
    {
        public function __construct(private string $name = 'com_plugins.plugin')
        {
        }

        public function getName(): string
        {
            return $this->name;
        }

        /** @var array<string, string> field attributes set through setFieldAttribute(), "group.field.attribute" => value */
        public array $attributes = [];

        public function setFieldAttribute(string $name, string $attribute, $value, ?string $group = null): bool
        {
            $this->attributes[($group !== null ? $group . '.' : '') . $name . '.' . $attribute] = (string) $value;

            return true;
        }
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }

        public static function sprintf(string $key, ...$args): string
        {
            return $key . '|' . implode('|', $args);
        }
    }
}

namespace Joomla\CMS\Log {
    class Log
    {
        public const WARNING = 4;

        public static array $entries = [];

        public static function add(string $message, int $priority = 0, string $category = ''): void
        {
            self::$entries[] = [$category, $message];
        }
    }
}

namespace Joomla\CMS\Plugin {
    use Joomla\Registry\Registry;

    class CMSPlugin
    {
        public $params;
        protected $app;

        public function __construct($dispatcher, array $config = [])
        {
            $p            = $config['params'] ?? [];
            $this->params = $p instanceof Registry ? $p : new Registry(\is_string($p) ? (json_decode($p, true) ?: []) : $p);
        }

        public int $languageLoads = 0;

        public function loadLanguage($extension = '', $basePath = ''): bool
        {
            $this->languageLoads++;

            return true;
        }

        public function setApplication($app): void
        {
            $this->app = $app;
        }

        public function getApplication()
        {
            return $this->app;
        }
    }

    class PluginHelper
    {
        /** @var string[] "group/element" of enabled plugins */
        public static array $enabled = [];

        public static function getPlugin(string $type, ?string $plugin = null)
        {
            return null;
        }

        public static function isEnabled(string $type, ?string $plugin = null): bool
        {
            return \in_array($type . '/' . $plugin, self::$enabled, true);
        }
    }
}
