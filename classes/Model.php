<?php

class Model {

	protected $table;

	protected $_fields = array();

	protected $primary = array();

	/**
	 * @var DB
	 */
	protected $db;

	protected function __construct() {
		$this->define();
	}

	protected function define() {
		
	}

	protected function define_field($key, $name) {
		if (!$key) {
			throw new Exception("Trying to defin an empty field");
		}
		$this->_fields[$key] = $name;
	}

	protected function get_field($name) {
		$key = array_search($name, $this->_fields);
		if (!$key) {
			throw new Exception("Trying to get undefined DB field '$name'");
		}
		return $key;
	}

	protected function map_columns(array $columns) {
		foreach ($columns as $i => $column) {
			$columns[$i] = $this->get_field($column);
		}
		return $columns;
	}

	protected function select_columns($columns = null) {
		if ($columns === null) {
			return $this->_fields;
		}

		$select = array();
		if (!is_array($columns)) {
			$columns = explode(',', $columns);
		}
		foreach ($this->map_columns($columns) as $db_field) {
			$select[$db_field] = $this->_fields[$db_field];
		}

		return $select;
	}

	protected static function loadById($id, $class = null, $columns = null) {
		$object = new $class;
		$row = $object->db->select($object->select_columns($columns))
					->from($object->table)
					->where(array($object->get_field('Id') => $id))
					->limit(1)
					->fetch();
		if ($row) {
			return self::db_map($row, $class);
		}
		return false;
	}

	protected static function db_map($row, $class) {
		$object = new $class;
		$reflector = new ReflectionClass($class);
		foreach ($row as $property => $value) {
			$prop = $reflector->getProperty($property);
			if ($prop->isPublic()) {
				$prop->setValue($object, $value);
			}
		}
		return $object;
	}

	protected static function db_maps($rows, $class) {
		foreach ($rows as $i => $row) {
			$rows[$i] = self::db_map($row, $class);
		}
		return $rows;
	}

	public function save() {
		$data = array();
		foreach ($this->_fields as $db_field => $property) {
			$data[$db_field] = $this->{$property};
		}

		$updates = array_keys($data);
		foreach ($this->primary as $db_field) {
			$i = array_search($db_field, $updates);
			if ($i !== false) {
				unset($updates[$i]);
			}
		}

		return $this->db->insert_update($this->table, $data, array_values($updates));
	}
}

