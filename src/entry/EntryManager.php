<?php declare(strict_types=1);
/**
* Copyright (c) 2015 · Kerem Güneş
* Apache License 2.0 · http://github.com/froq/froq-database
*/
namespace froq\database\entry;

use froq\database\{
    Database, DatabaseRegistry, DatabaseRegistryException,
    Transaction, Result, trait\DbTrait
};
use froq\reflection\Reflection;
use Throwable;

/**
 * Entry manager class for executing Entry queries and updating their data and states.
 *
* @package froq\database\entry
* @class   froq\database\entry\EntryManager
* @author  Kerem Güneş
* @since   7.1
*/
class EntryManager
{
    use DbTrait;

    /** Entry storage. */
    private EntryStorage $entries;

    /**
     * Constructor.
     *
     * @param  froq\database\Database|null $db
     * @throws froq\database\entry\EntryManagerException
     */
    public function __construct(Database $db = null)
    {
        if (!$db) try {
            $db = DatabaseRegistry::getDefault();
        } catch (DatabaseRegistryException $e) {
            throw new EntryManagerException($e);
        }

        $this->db      = $db;
        $this->entries = new EntryStorage();
    }

    /**
     * Get entries.
     *
     * @return froq\database\entry\EntryStorage
     */
    public function entries(): EntryStorage
    {
        return $this->entries;
    }

    /**
     * Attach an entry.
     *
     * @param  froq\database\entry\Entry $entry
     * @return void
     */
    public function attach(Entry $entry): void
    {
        $this->entries->attach($entry);
    }

    /**
     * Detach an entry.
     *
     * @param  froq\database\entry\Entry $entry
     * @return void
     */
    public function detach(Entry $entry): void
    {
        $this->entries->detach($entry);
    }

    /**
     * Commit stored entry queries and return them after updating their data and states.
     *
     * @return array<froq\database\entry\Entry>
     * @throws froq\database\entry\EntryManagerException
     */
    public function commit(): array
    {
        if (!$this->entries->count()) {
            throw new EntryManagerException('No entries yet, call attach()');
        }

        try {
            $transaction = new Transaction($this->db->pdo());
            $transaction->begin();

            static $options = ['fetch' => 'array'];

            $entries = [];

            foreach ($this->entries as $entry) {
                // For private properties defined in Entry (query, result).
                $ref = Reflection::reflectObject($entry)->getParent(top: true);

                $query = $ref->getProperty('query')->getValue($entry);

                /** @var Result */
                $result = $this->db->query($query, options: $options);

                // Update entry's okay state.
                $entry->okay((bool) $result->count());

                // Update entry's action state.
                $entry->action(match (true) {
                    $query->has('select') => 'select',
                    $query->has('insert') => 'insert',
                    $query->has('update') => 'update',
                    $query->has('delete') => 'delete',
                    default               => null,
                });

                // Update entry's data stack.
                if ($row = $result->first()) {
                    $entry->data()->update($row);
                }

                // Set entry's result property.
                $ref->getProperty('result')->setValue($entry, $result);

                $entries[] = $entry;
            }

            $transaction->commit();

            return $entries;
        } catch (Throwable $e) {
            $transaction->rollback();

            throw new EntryManagerException($e);
        }
    }

    /**
     * Count entries.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->entries->count();
    }

    /**
     * Empty entries.
     *
     * @return void
     */
    public function empty(): void
    {
        $this->entries->empty();
    }
}
