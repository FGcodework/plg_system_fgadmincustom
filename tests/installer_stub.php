<?php

/**
 * Stand-ins for the Joomla installer, factory and database used by script.php.
 * The fake database really executes the three statements script.php issues
 * (select / update params+enabled / update enabled) against an in-memory table.
 */

namespace Joomla\Database {
    interface DatabaseInterface
    {
    }

    final class ParameterType
    {
        public const STRING  = 'string';
        public const INTEGER = 'integer';
    }
}

namespace Joomla\CMS\Installer {
    class InstallerAdapter
    {
    }

    interface InstallerScriptInterface
    {
    }
}

namespace Joomla\CMS {
    class Version
    {
        public static string $short = '5.4.11';

        public function getShortVersion(): string
        {
            return self::$short;
        }
    }

    use Joomla\Database\DatabaseInterface;

    class FakeLang
    {
        public array $loaded = [];

        public function load(string $ext, string $base): bool
        {
            $this->loaded[] = $ext . '@' . $base;

            return true;
        }
    }

    class FakeQuery
    {
        public string $kind   = '';
        public array $select  = [];
        public array $set     = [];
        public array $where   = [];
        public array $whereIn = [];
        public array $bound   = [];

        public function select($cols): self
        {
            $this->kind   = 'select';
            $this->select = (array) $cols;

            return $this;
        }

        public function from($t): self
        {
            return $this;
        }

        public function update($t): self
        {
            $this->kind = 'update';

            return $this;
        }

        public function set($expr): self
        {
            $this->set[] = $expr;

            return $this;
        }

        public function where($expr): self
        {
            $this->where[] = $expr;

            return $this;
        }

        public function whereIn($col, array $values, $type = null): self
        {
            $this->whereIn = $values;

            return $this;
        }

        public function bind(string $name, $value, $type = null): self
        {
            $this->bound[$name] = $value;

            return $this;
        }
    }

    class FakeDb implements DatabaseInterface
    {
        /** @var array<string, array{element: string, params: string, enabled: int}> */
        public array $rows = [];
        public bool $failSelect = false;
        public bool $failOn     = false;   // fail the statement that stores the new params
        public bool $failDisable = false;  // fail the statement that switches the old plugins off
        public bool $failCommit  = false;
        public bool $failRollback = false;
        public int $executed    = 0;
        public int $depth       = 0;
        /** @var string[] */
        public array $log       = [];
        /** @var array<int, array> snapshots, one per open transaction level */
        private array $snapshots = [];
        private ?FakeQuery $query = null;

        public function getQuery(bool $new = false): FakeQuery
        {
            return new FakeQuery();
        }

        public function quoteName($n, $as = null)
        {
            return $n;
        }

        public function setQuery(FakeQuery $q): self
        {
            $this->query = $q;

            return $this;
        }

        public function transactionStart(bool $asSavepoint = false): void
        {
            $this->log[]       = 'start';
            $this->snapshots[] = $this->rows;
            $this->depth++;
        }

        public function transactionCommit(bool $toSavepoint = false): void
        {
            $this->log[] = 'commit';

            if ($this->failCommit) {
                throw new \RuntimeException('simulated commit failure');
            }

            array_pop($this->snapshots);
            $this->depth--;
        }

        public function transactionRollback(bool $toSavepoint = false): void
        {
            $this->log[] = 'rollback';

            if ($this->failRollback) {
                throw new \RuntimeException('simulated rollback failure');
            }

            $this->rows = array_pop($this->snapshots);
            $this->depth--;
        }

        public function loadAssocList(): array
        {
            if ($this->failSelect) {
                throw new \RuntimeException('simulated database failure');
            }

            $q = $this->query;

            return array_values(array_filter($this->rows, static fn (array $r) => \in_array($r['element'], $q->whereIn, true)));
        }

        public function execute(): bool
        {
            $q = $this->query;
            $this->executed++;

            if ($q->kind !== 'update') {
                return true;
            }

            $sets = implode(' ', $q->set);

            if ($this->failOn && str_contains($sets, 'params')) {
                throw new \RuntimeException('simulated update failure');
            }

            if ($this->failDisable && !str_contains($sets, 'params')) {
                throw new \RuntimeException('simulated disable failure');
            }

            $targets = $q->whereIn !== [] ? $q->whereIn : [$q->bound[':element']];

            foreach ($targets as $el) {
                if (!isset($this->rows[$el])) {
                    continue;
                }

                if (str_contains($sets, 'params')) {
                    $this->rows[$el]['params'] = $q->bound[':params'];
                }

                if (str_contains($sets, 'enabled')) {
                    $this->rows[$el]['enabled'] = $q->bound[':enabled'];
                }
            }

            return true;
        }
    }

    class FakeApplication
    {
        public array $messages = [];
        public FakeLang $lang;

        public function __construct()
        {
            $this->lang = new FakeLang();
        }

        public function getLanguage(): FakeLang
        {
            return $this->lang;
        }

        public function enqueueMessage(string $msg, string $type = 'message'): void
        {
            $this->messages[] = [$type, $msg];
        }
    }

    class FakeContainer
    {
        public function __construct(private FakeDb $db)
        {
        }

        public function get(string $id)
        {
            return $this->db;
        }
    }

    class Factory
    {
        public static FakeDb $db;
        public static FakeApplication $app;

        public static function getContainer(): FakeContainer
        {
            return new FakeContainer(self::$db);
        }

        public static function getApplication(): FakeApplication
        {
            return self::$app;
        }
    }
}
