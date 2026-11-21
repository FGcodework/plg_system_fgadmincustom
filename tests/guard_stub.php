<?php

/** Stand-ins for the database and the factory the settings-save guard reads the stored params with (extension tests only). */

namespace Joomla\Database {
    interface DatabaseInterface
    {
    }

    class ParameterType
    {
        public const INTEGER = 'int';
    }

    /** Stands in for the database: it only knows the params of one extension row. */
    class FakeParamsDb implements DatabaseInterface
    {
        public static string $params = '{}';
        public static bool $broken   = false;
        public static int $queries   = 0;

        public function getQuery(bool $new = false): self
        {
            return $this;
        }

        public function quoteName($name): string
        {
            return (string) $name;
        }

        public function select($x): self
        {
            return $this;
        }

        public function from($x): self
        {
            return $this;
        }

        public function where($x): self
        {
            return $this;
        }

        public function bind($key, &$value, $type = null): self
        {
            return $this;
        }

        public function setQuery($query): self
        {
            return $this;
        }

        public function loadResult()
        {
            self::$queries++;

            if (self::$broken) {
                throw new \RuntimeException('database is gone');
            }

            return self::$params;
        }
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static function getContainer(): object
        {
            return new class () {
                public function get(string $id)
                {
                    return new \Joomla\Database\FakeParamsDb();
                }
            };
        }
    }
}
