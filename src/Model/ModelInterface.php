<?php
/**
 * Copyright (c) 2016 Kerem Güneş
 *     <k-gun@mail.com>
 *
 * GNU General Public License v3.0
 *     <http://www.gnu.org/licenses/gpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Froq\Database\Model;

/**
 * @package    Froq
 * @subpackage Froq\Database\Model
 * @object     Froq\Database\Model\ModelInterface
 * @author     Kerem Güneş <k-gun@mail.com>
 */
interface ModelInterface
{
    /**
     * Find.
     * @param int|string $id
     * @return any
     */
    public function find($id = null);

    /**
     * Find all.
     * @param  string|null $where
     * @param  array|null  $whereParams
     * @param  int|null    $limit
     * @param  int         $order
     * @return any
     */
    public function findAll(string $where = null, array $whereParams = null, int $limit = null,
        int $order = -1);

    /**
     * Find by.
     * @param  string $field
     * @param  any    $fieldParam
     * @param  int    $order
     * @return any
     */
    public function findBy(string $field, $fieldParam, int $order = -1);

    /**
     * Find by all.
     * @param  string     $field
     * @param  array|null $fieldParam
     * @param  int|null   $limit
     * @param  int        $order
     * @return any
     */
    public function findByAll(string $field, array $fieldParam = null, int $limit = null,
        int $order = -1);

    /**
     * Save.
     * @return any
     */
    public function save();

    /**
     * Remove.
     * @return int|bool
     */
    public function remove();

    /**
     * Count.
     * @return int
     */
    public function count(): int;
}
