<?php

/** Stand-ins for the CMS application, used by the plugin-class tests. */

declare(strict_types=1);

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\Registry\Registry;

class FakeInput
{
    public function __construct(private array $values)
    {
    }

    public function getCmd(string $name, string $default = ''): string
    {
        return (string) ($this->values[$name] ?? $default);
    }
}

class FakeApp implements CMSApplicationInterface
{
    public array $messages = [];

    /** Does the user hold the global core.admin right (a Super User)? */
    public bool $superUser = true;

    /** Joomla renders the message area before the page head: a message queued after that is lost. */
    public bool $messagesRendered = false;
    public array $state    = [];
    public HtmlDocument $doc;

    public function __construct(public string $client = 'administrator', public bool $guest = true, public array $query = [], public string $template = 'atum', public string $body = '')
    {
        $this->doc = new HtmlDocument();
    }

    public function isClient(string $name): bool
    {
        return $this->client === $name;
    }

    public function getIdentity(): object
    {
        $super = $this->superUser;

        return new class ($this->guest ? 1 : 0, $super) {
            public function __construct(public int $guest, private bool $super)
            {
            }

            public function authorise(string $action, $asset = null): bool
            {
                return $action === 'core.admin' && $asset === null && $this->super;
            }
        };
    }

    public function getInput(): FakeInput
    {
        return new FakeInput($this->query);
    }

    public function getTemplate(bool $params = false): object
    {
        return (object) ['template' => $this->template, 'parent' => ''];
    }

    public function getDocument(): HtmlDocument
    {
        return $this->doc;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function enqueueMessage(string $msg, string $type = 'message'): void
    {
        if ($this->messagesRendered) {
            return;
        }

        $this->messages[] = [$type, $msg];
    }

    public function getUserState(string $key, $default = null)
    {
        return $this->state[$key] ?? $default;
    }

    public function setUserState(string $key, $value): void
    {
        $this->state[$key] = $value;
    }
}

/** A Registry whose chosen keys blow up, to simulate a broken code path. */
class ExplodingRegistry extends Registry
{
    public function __construct(array $d, private string $prefix)
    {
        parent::__construct($d);
    }

    public function get(string $k, $default = null)
    {
        if (str_starts_with($k, $this->prefix)) {
            throw new \TypeError('simulated fatal error in ' . $k);
        }

        return parent::get($k, $default);
    }
}
