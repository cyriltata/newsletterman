<?php

class Newsletter extends Model {

	public $Id;
	public $Subject;
	public $Message;
	public $Schedule;
	public $Triggered;
	public $SenderName;
	public $SenderEmail;
	public $Deliveries;
	public $Failures;

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
	public static function load($id) {
		if (!isset(self::$instances[$id])) {
			self::$instances[$id] = parent::loadById($id, __CLASS__);
		}
		return self::$instances[$id];
	}

	public static function getScheduled($offset = 0, $limit = 20) {
		$nl = new self();
		$scheduled = $nl->get_field('Schedule') . ' < ';
		$triggered = $nl->get_field('Triggered') . ' <= ';

		$select = $nl->db->select($nl->select_columns());
		$select->from($nl->table);
		$select->where(array($scheduled => time(), $triggered => 0));
		$select->limit($limit, $offset);
		$rows = $select->fetchAll();
		return self::db_maps($rows, __CLASS__);
	}

	protected function define() {
		$this->db = DB::getInstance('newsletters');
		$this->table = Config::get('database.newsletters.table');
		$this->primary[] = Config::get('database.newsletters.id_field');
		$this->define_field(Config::get('database.newsletters.id_field'), 'Id');
		$this->define_field(Config::get('database.newsletters.subject_field'), 'Subject');
		$this->define_field(Config::get('database.newsletters.message_field'), 'Message');
		$this->define_field(Config::get('database.newsletters.schedule_field'), 'Schedule');
		$this->define_field(Config::get('database.newsletters.triggered_field'), 'Triggered');
		$this->define_field(Config::get('database.newsletters.sender_name_field'), 'SenderName');
		$this->define_field(Config::get('database.newsletters.sender_email_field'), 'SenderEmail');
		$this->define_field(Config::get('database.newsletters.deliveries_field'), 'Deliveries');
		$this->define_field(Config::get('database.newsletters.failures_field'), 'Failures');
	}

	public function trigger() {
		$this->Triggered = time();
		$this->save();
	}

}

