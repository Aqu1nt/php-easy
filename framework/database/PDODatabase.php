<?php

namespace Framework\Database;

use Framework\Di\Injector;
use PDO, Exception, ReflectionClass;

class PDODatabase implements IDatabase
{

    /**
     * The database handle
     * @var PDO
     */
    private $DBH;

    /**
     * PDODatabase constructor.
     * @param $driver
     * @param $host
     * @param $dbname
     * @param $user
     * @param $password
     * @internal param $db
     */
    public function __construct($driver, $host, $dbname, $user, $password)
    {
        $this->DBH = new PDO("$driver:host=$host;dbname=$dbname", $user, $password);
        $this->DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @param Model $model
     * @return mixed
     */
    public function save(Model $model)
    {
        Injector::injectMethodIfExists("prePersist", $model);

        $fields = (array) $model;
        $fieldNames = array_keys($fields);

        $values = "";
        foreach ($fieldNames as $fieldName) {
            $values .= "$fieldName, ";
        }
        $values = substr($values, 0, strlen($values) - 2);
        $placeholders = ":".str_replace(", ", ", :", $values);

        $query = "REPLACE INTO ".$this->modelName($model)."($values) VALUES($placeholders);";
        $st = $this->DBH->prepare($query);

        $st->execute($fields);

        Injector::injectMethodIfExists("postPersist", $model);
    }

    /**
     * @param Model $model
     * @return mixed
     */
    public function delete(Model $model)
    {
        Injector::injectMethodIfExists("preDelete", $model);
        $query = "DELETE FROM ".$this->modelName($model)." WHERE id = ".$model->id.";";
        $this->DBH->exec($query);
        Injector::injectMethodIfExists("postDelete", $model);
    }

    /**
     * @param string $class
     * @return mixed
     */
    public function deleteAll($class)
    {
        $query = "DELETE FROM ".$this->modelName($class).";";;
        $this->DBH->exec($query);
    }

    /**
     * @param $class
     * @param $id
     * @return mixed
     */
    public function findOneById($class, $id)
    {
        return $this->findOne($class, Where::id()->eq($id));
    }

    /**
     * @param $class
     * @param $filters
     * @return mixed
     * @throws Exception if the $class is not of type Model
     */
    public function findOne($class, ... $filters)
    {
        $result = $this->findAll($class, ... $filters);
        if (count($result) == 0) {
            return null;
        }
        if (count($result) > 1) {
            throw new Exception("Query must return exactly 1 result, but returned ".count($result));
        }
        return $result[0];
    }

    /**
     * @param $class
     * @param $filters
     * @return mixed
     * @throws Exception if the $class is not of type Model
     */
    public function findAll($class, ... $filters)
    {
        if (! (new ReflectionClass($class))->isSubclassOf(Model::class)) {
            throw new Exception("$class is not of type ".Model::class);
        }

        $filters = implode(" ", $filters);
        $query = "SELECT * FROM ".$this->modelName($class)." $filters;";
        $sth = $this->DBH->query($query);
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $result = [];
        while ($row = $sth->fetch())
        {
            $model = Injector::newInstance($class);
            $result[] = $model;
            foreach ($row as $key => $value)
            {
                $model->$key = $value;
            }
            Injector::injectMethodIfExists("onload", $model);
        }
        return $result;
    }

    /**
     * Extracts the short name of this class
     * @param $class
     * @return string
     */
    private function modelName($class)
    {
        return (new ReflectionClass($class))->getShortName();
    }

    /**
     * @param Model $model
     * @param $target
     * @param $mapping
     * @return mixed
     * @internal param $class1
     * @internal param $class2
     */
    public function many(Model $model, $target, $mapping)
    {
        $mappingTokens = explode("\\", $mapping);
        $mapping = end($mappingTokens);
        $targetClass = new ReflectionClass($target);
        $targetName = $targetClass->getShortName();
        $modelName = (new ReflectionClass($model))->getShortName();

        //Extract the fields
        $fields = [];
        foreach ($targetClass->getProperties() as $property)
        {
            if ($property->isPublic())
            {
                $pname = $property->getName();
                $fields[] = "$targetName.$pname as $pname";
            }
        }
        $fields = implode(", ", $fields);

        $query = "SELECT $fields FROM $modelName JOIN $mapping ON $modelName.id = $mapping.id$modelName JOIN $targetName on $mapping.id$targetName = $targetName.id WHERE $modelName.id = ".$model->id;

        $sth = $this->DBH->query($query);
        $sth->setFetchMode(PDO::FETCH_ASSOC);

        $result = [];
        while ($row = $sth->fetch())
        {
            $targetModel = Injector::newInstance($targetClass);
            $result[] = $targetModel;
            foreach ($row as $key => $value)
            {
                $targetModel->$key = $value;
            }
            Injector::injectMethodIfExists("onload", $targetModel);
        }
        return $result;
    }

    /**
     * @param $str
     * @return mixed
     */
    public function escape($str)
    {
        return $this->DBH->quote($str);
    }
}