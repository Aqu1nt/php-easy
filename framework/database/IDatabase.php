<?php

namespace Framework\Database;

use Exception;

/**
 * 
 * @author Emil
 *
 */
interface IDatabase
{
    /**
     * @param Model $model
     * @return mixed
     */
	public function save(Model $model);

    /**
     * @param Model $model
     * @return mixed
     */
    public function delete(Model $model);

    /**
     * @param string $class
     * @return mixed
     */
    public function deleteAll($class);

    /**
     * @param $class
     * @param $id
     * @return mixed
     * @throws Exception if the $class is not of type Model
     */
    public function findOneById($class, $id);

    /**
     * @param $class
     * @param $filters
     * @return mixed
     * @throws Exception if the $class is not of type Model
     */
    public function findOne($class, ... $filters);

    /**
     * @param $class
     * @param $filters
     * @return mixed
     * @throws Exception if the $class is not of type Model
     */
    public function findAll($class, ... $filters);

    /**
     * @param Model $model
     * @param $target
     * @param $mapping
     * @return mixed
     */
    public function many(Model $model, $target, $mapping);

    /**
     * @param $str
     * @return mixed
     */
    public function escape($str);
}