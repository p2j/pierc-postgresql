<?php


class db_class
{
	protected $_conn;

	public function __construct( $server, $port, $database, $user, $password, $timezone)
	{
		$this->_conn = pg_connect("host=$server port=$port dbname=$database user=$user password=$password");
		if (!$this->_conn){ die ("Could not connect: " + pg_last_error() ); }


		// Verify that we received a proper time zone, otherwise fall back to default
		$allZones = DateTimeZone::listIdentifiers();
		if(!in_array($timezone, $allZones)) {
			$timezone = "America/Chicago";
		}
		$this->timezone = new DateTimeZone($timezone);
	}

	public function __destruct( )
	{
		pg_close( $this->_conn );
	}

}

class pierc_db extends db_class
{

	protected function hashinate( $result )
	{
		$lines = array();
		$counter = 0;
		while( $row = pg_fetch_assoc($result) )
		{
			if( isset( $row['time'] ) )
			{
				date_default_timezone_set('UTC');
				$dt = date_create( $row['time']);
				$dt->setTimezone( $this->timezone );
				$row['time'] = $dt->format("Y-m-d H:i:s");
			}
			$lines[$counter] = $row;
			$counter++;
		}
		return $lines;
	}

	public function get_last_n_lines( $channel, $n )
	{
		$channel = pg_escape_string( $channel );
		$n = (int)$n;
		$query = "
			SELECT id, channel, name, time, message, type, hidden FROM main WHERE channel = '$channel' ORDER BY id DESC LIMIT $n;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return array_reverse($this->hashinate($results));
	}

	public function get_before( $channel, $id, $n )
	{
		$channel = pg_escape_string( $channel );
		$n = (int)$n;
		$id = (int)$id;
		$query = "
			SELECT id, channel, name, time, message, type, hidden FROM main WHERE channel = '$channel' AND id < $id ORDER BY id DESC LIMIT $n;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return $this->hashinate($results);
	}

	public function get_after( $channel, $id, $n )
	{
		$channel = pg_escape_string( $channel );
		$n = (int)$n;
		$id = (int)$id;
		$query = "
			SELECT id, channel, name, time, message, type, hidden FROM main WHERE channel = '$channel' AND id > $id ORDER BY time ASC, id DESC LIMIT $n;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return $this->hashinate($results);
	}

	public function get_lines_between_now_and_id( $channel, $id)
	{
		$channel = pg_escape_string( $channel );
		$id = (int)$id;
		$query = "
			SELECT id, channel, name, time, message, type, hidden FROM main WHERE channel = '$channel' AND id > $id ORDER BY id DESC LIMIT 500";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return array_reverse($this->hashinate($results));
	}

	// Returns the number of records in 'channel' with an ID below $id
	public function get_count( $channel, $id)
	{
		$channel = pg_escape_string( $channel );
		$id = (int)$id;
		$query = "
			SELECT COUNT(*) as count FROM main
				WHERE channel = '$channel'
				AND id < $id;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		$res = $this->hashinate($results);
		$count = $res[0]["count"];
		if ( $count < 0 )
		{
			return 0;
		}
		return $count;
	}

	public function get_context( $id, $n)
	{
		// Let's imagine that we have 800,000 records, divided
		// between two different channels, #hurf and #durf.
		// we want to select the $n (50) records surrounding
		// id-678809 in #durf. So, first we count the number
		// of records in # durf that are below id-678809.
		//
		// Remember: OFFSET is the number of records that MySQL
		// will skip when you do a SELECT statement -
		// So "SELECT * FROM main LIMIT 50 OFFSET 150 will select
		// rows 150-200.
		//
		// If we used the $count as an $offset, we'd have a conversation
		// _starting_ with id-678809 - but we want to capture the
		// conversation _surrounding_ id-678809, so we subtract
		// $n (50)/2, or 25.


		$id = (int)$id;
		$n = (int)$n;

		$query = "
			SELECT channel
				FROM main
				WHERE id = $id ;
				";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		while ($row = pg_fetch_assoc($results)) {
			$channel = $row['channel'];
		}

		$channel = pg_escape_string($channel);

		$count = $this->get_count( $channel, $id );

		$offset = $count - (int)($n/2);

		if( $offset < 0)
		{
			$offset = 0;
		}

		$query = "
			SELECT *
				FROM (SELECT * FROM main
						WHERE channel = '$channel'
						LIMIT $n OFFSET $offset) channel_table
				ORDER BY id DESC ;
				";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return array_reverse($this->hashinate($results));
	}

	public function get_search_results( $search, $n, $offset=0 )
	{
		$search = urldecode($search);
		$n = (int) $n;
		$offset = (int) $offset;

		$searchquery = " WHERE ";
		$searcharray = preg_split("/[ |]/", $search);
		foreach($searcharray as $searchterm )
		{
			$searchquery .= "(message LIKE '%".
				pg_escape_string($searchterm)."%' OR name LIKE '%".
				pg_escape_string($searchterm)."%' ) AND";
		}

		$n = (int)$n;
		$query = "
			SELECT id, channel, name, time, message, type, hidden
				FROM main
			$searchquery true ORDER BY id DESC LIMIT $n OFFSET $offset;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		$results = array_reverse($this->hashinate($results));
		return $results;
	}

	public function get_tag( $channel, $tag, $n )
	{
		$tag = pg_escape_string($tag);
		$channel = pg_escape_string($channel);
		$n = (int)$n;

		$query = "
			SELECT id, channel, name, time, message, type, hidden
				FROM main
			WHERE message LIKE '".$tag.":%' ORDER BY id DESC LIMIT $n;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return array_reverse($this->hashinate($results));
	}

	public function get_lastseen( $channel, $user )
	{
		$user = pg_escape_string($user);
		$channel = pg_escape_string($channel);

		$query = "
			SELECT time
				FROM main
			WHERE name = '".$user."' ORDER BY id DESC LIMIT 1;";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return $this->hashinate($results);
	}

	public function get_user( $channel, $user, $n )
	{
		$user = pg_escape_string($user);
		$channel = pg_escape_string($channel);
		$n = (int) $n;

		$query = "
			SELECT id, channel, name, time, message, type
				FROM main
			WHERE name = '".$user."' ORDER BY id DESC LIMIT ".$n.";";

		$results = pg_query( $this->_conn, $query );
		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		return $this->hashinate($results);
	}

	public function get_users( $channel )
	{
		$query = " SELECT DISTINCT name FROM main; ";
		$results = pg_query( $this->_conn, $query );

		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		$lines = $this->hashinate($results);
		$users = array();
		foreach($lines as $line)
		{
			$users[] = $line['name'];
		}
		return $users;
	}

	public function get_channels()
	{
		$str1 = pg_escape_string("name");
		$str2 = pg_escape_string("undefined");
		$query = " SELECT DISTINCT channel FROM main WHERE type <> '{$str1}' AND channel <> '{$str2}';";
		#$query = " SELECT DISTINCT channel FROM main WHERE type <> \"name\";"; #" AND channel <> \"undefined\";";
		$results = pg_query( $this->_conn, $query );

		if (!$results){ print pg_last_error(); return false; }
		if( pg_num_rows($results) == 0 ) { return false; }

		$lines = $this->hashinate($results);
		$channels = array();
		foreach($lines as $line)
		{
			$channels[] = $line['channel'];
		}
		return $channels;
	}
}
?>
