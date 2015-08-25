<?php

class Recipient extends Model {

	public $Id;
	public $FirstName = '';
	public $LastName = '';
	public $Email = '';

	protected $mailist;

	protected static $instances  = array();

	protected function __construct($maillist = 'default') {
		$this->mailist = $maillist;
		parent::__construct();
	}

	public static function getBatch($offset = 0, $limit = 20, $maillist = 'default') {
		if (!$maillist) {
			$maillist = 'default';
		}
		$self = new self($maillist);

		$select = $self->db->select($self->select_columns());
		$select->from($self->table);
		$select->limit($limit, $offset);
		$rows = $select->fetchAll();
		return self::db_maps($rows, __CLASS__, $maillist);
	}

	protected function define() {
		$this->db = DB::getInstance("recipients.{$this->mailist}");
		$this->table = Config::get($this->db_field('table'));
		$this->primary[] = Config::get($this->db_field('id_field'));
		$this->define_field(Config::get($this->db_field('id_field')), 'Id');
		$this->define_field(Config::get($this->db_field('first_name_field')), 'FirstName');
		$this->define_field(Config::get($this->db_field('last_name_field')), 'LastName');
		$this->define_field(Config::get($this->db_field('email_field')), 'Email');
	}

	protected function db_field($field) {
		return "database.recipients.{$this->mailist}.{$field}";
	}

}

