<?php

class Recipient extends Model {

	public $Id;
	public $Names;
	public $Email;

	protected static $instances  = array();

	protected function __construct() {
		parent::__construct();
	}

	/**
	 * Load from DB a newsletter with ID $id
	 *
	 * @param in $id
	 * @param mixed $columns A comma separated string of column names or an array of column names
	 * @return Newsletter;
	 * 
	 */
	public static function load($id, $columns = null) {
		if (!isset(self::$instances[$id])) {
			self::$instances[$id] = parent::loadById($id, __CLASS__, $columns);
		}
		return self::$instances[$id];
	}

	public static function getBatch($offset = 0, $limit = 20) {
		$self = new self();

		$select = $self->db->select($self->select_columns());
		$select->from($self->table);
		$select->limit($limit, $offset);
		$rows = $select->fetchAll();
		return self::db_maps($rows, __CLASS__);
	}

	protected function define() {
		$this->db = DB::getInstance('recipients');
		$this->table = Config::get('database.recipients.table');
		$this->primary[] = Config::get('database.recipients.id_field');
		$this->define_field(Config::get('database.recipients.id_field'), 'Id');
		$this->define_field(Config::get('database.recipients.names_field'), 'Names');
		$this->define_field(Config::get('database.recipients.email_field'), 'Email');
	}

}

