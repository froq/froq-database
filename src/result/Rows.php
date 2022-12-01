<?php declare(strict_types=1);
/**
 * Copyright (c) 2015 · Kerem Güneş
 * Apache License 2.0 · http://github.com/froq/froq-database
 */
namespace froq\database\result;

use froq\common\interface\Arrayable;

/**
 * A class, for collecting `Result` rows as list.
 *
 * @package froq\database\result
 * @class   froq\database\result\Rows
 * @author  Kerem Güneş
 * @since   6.0
 */
class Rows extends \ItemList
{
    /**
     * @override
     */
    public function toArray(bool $deep = false): array
    {
        $items = $this->items();

        if ($deep) foreach ($items as &$item) {
            if ($item instanceof Arrayable) {
                $item = $item->toArray();
            } elseif ($item instanceof \stdClass) {
                $item = (array) $item;
            }
        }

        return $items;
    }
}
