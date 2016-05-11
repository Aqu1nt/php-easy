<?php

namespace Framework\Database;

use Framework\Di\Injector;

abstract class Model
{
	/**
	 * The Object id, aka primary key
	 * @var int
	 */
	public $id;

	/**
	 * Called when this object was loaded
	 */
//    public function onload(){}
	
	/**
	 * Called before this object gets saved
	 */
//    public function prePersist(){}

    /**
     * Called after this object was saved
     */
//    public function postPersist(){}
	
	/**
	 * Called before this object gets deleted
	 */
//    public function preDelete(){}

    /**
     * Called after this object got deleted
     */
//    public function postDelete(){}


    /**
     * @return mixed
     */
    public function save()
    {
        return self::db()->save($this);
    }

    /**
     * @return mixed
     */
    public function delete()
    {
        return self::db()->delete($this);
    }

    /**
     * @param $targetClass
     * @param $mapping
     * @return mixed
     */
    public function many($targetClass, $mapping)
    {
        return self::db()->many($this, $targetClass, $mapping);
    }

    /**
     * @param $id
     * @return mixed
     */
    public static function findOneById($id)
    {
        return self::db()->findOneById(get_called_class(), $id);
    }

    /**
     * @param $filters
     * @return mixed
     */
    public static function findOne(... $filters)
    {
        return self::db()->findOne(get_called_class(), ... $filters);
    }

    /**
     * @param $filters
     * @return mixed
     */
    public static function findAll(... $filters)
    {
        return self::db()->findAll(get_called_class(), ... $filters);
    }

    /**
     * @return mixed
     */
    public static function deleteAll()
    {
       return self::db()->deleteAll(get_called_class());
    }

    /**
     * Returns the provided IDatabase bean
     * @return IDatabase
     */
    private static function db()
    {
        return Injector::bean(IDatabase::class);
    }
}