<?php

/**
 * Copyright (C) 2017  James Dimitrov (Jimok82)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *
 */
class miningpoolhubstats
{

	private $debug = FALSE;
	public $api_key = null;
	public $fiat = null;
	public $crypto = null;
	public $coin_data = array();
	public $worker_data = array();

	public $confirmed_total = 0;
	public $unconfirmed_total = 0;
	public $confirmed_total_c = 0;
	public $unconfirmed_total_c = 0;
	public $delta_total = 0;

	public $is_cached_data = 0;
	public $is_cached_conversion = 0;
	public $is_cached_workers = 0;
	public $cache_time_data = 0;
	public $cache_time_conversion = 0;
	public $cache_time_workers = 0;

	private $stats = 0;
	private $stats_time = 0;
	private $daily_stats = 0;
	public $minutes = 0;

	private $crypto_prices = null;
	private $crypto_api_coin_list = null;
	public $full_coin_list = null;

	public $all_coins = null;

	private $mysqli = null;

	public function __construct($db_host, $db_username, $db_password, $db_name)
	{
		$this->mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);

		if ($this->mysqli->connect_errno) {
			echo "Errno: " . $this->mysqli->connect_errno . "\n";
			echo "Error: " . $this->mysqli->connect_error . "\n";
			die();
		}

	}

	function strip_api_key()
	{
		return substr($this->api_key, 0, 10) . substr($this->api_key, -10, 10);
	}

	public function init_and_execute($api_key, $fiat)
	{
		$this->api_key = $api_key;
		$this->fiat = $fiat;
		$this->init_all_coins();
		$this->stats = $this->get_stats_for_last_x_hours(1);
		$this->daily_stats = $this->get_stats_for_last_day();
		$this->execute();
	}

	private function init_all_coins()
	{
		$this->all_coins = (object)array(
			'bitcoin' => (object)array('code' => 'BTC', 'min_payout' => '0.002'),
			'ethereum' => (object)array('code' => 'ETH', 'min_payout' => '0.01'),
			'monero' => (object)array('code' => 'XMR', 'min_payout' => '0.05'),
			'zcash' => (object)array('code' => 'ZEC', 'min_payout' => '0.002'),
			'vertcoin' => (object)array('code' => 'VTC', 'min_payout' => '0.1'),
			'feathercoin' => (object)array('code' => 'FTC', 'min_payout' => '0.002'),
			'digibyte-skein' => (object)array('code' => 'DGB', 'min_payout' => '0.01'),
			'musicoin' => (object)array('code' => 'MUSIC', 'min_payout' => '0.002'),
			'ethereum-classic' => (object)array('code' => 'ETC', 'min_payout' => '0002'),
			'siacoin' => (object)array('code' => 'SC', 'min_payout' => '0.002'),
			'zcoin' => (object)array('code' => 'XZC', 'min_payout' => '0.002'),
			'bitcoin-gold' => (object)array('code' => 'BTG', 'min_payout' => '0.002'),
			'bitcoin-cash' => (object)array('code' => 'BCH', 'min_payout' => '0.0005'),
			'zencash' => (object)array('code' => 'ZEN', 'min_payout' => '0.002'),
			'litecoin' => (object)array('code' => 'LTC', 'min_payout' => '0.002'),
			'monacoin' => (object)array('code' => 'MONA', 'min_payout' => '0.1'),
			'groestlcoin' => (object)array('code' => 'GRS', 'min_payout' => '0.002')
		);
	}

	private function make_api_call($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close($ch);
		return json_decode($output);
	}

	private function generate_coin_string()
	{

		//Set up the conversion string for all coins we need from the cryptocompare API
		$all_coin_list = array();
		foreach ($this->all_coins as $coin) {
			$all_coin_list[] = $coin->code;
		}
		$this->crypto_api_coin_list = implode(",", $all_coin_list);
	}

	private function get_prices()
	{

		$this->generate_coin_string();

		$this->crypto_prices = $this->make_api_call("https://min-api.cryptocompare.com/data/pricemulti?fsyms=" . $this->crypto_api_coin_list . "&tsyms=" . $this->fiat . "," . $this->crypto);

	}

	private function get_coin_list()
	{
		$this->full_coin_list = $this->make_api_call("https://miningpoolhub.com/index.php?page=api&action=getuserallbalances&api_key=" . $this->api_key);

	}

	private function generate_worker_stats($coin)
	{
		$workers = $this->make_api_call("https://" . $coin . ".miningpoolhub.com/index.php?page=api&action=getuserworkers&api_key=" . $this->api_key);

		//Get the stats for every active worker with hashrate > 0
		$worker_list = $workers->getuserworkers->data;
		foreach ($worker_list as $worker) {
			if ($worker->hashrate != 0) {
				$worker->coin = $coin;
				$this->worker_data[] = $worker;
			}
		}

	}

	public function execute()
	{


		$this->get_miner_stats_from_cache();
		$this->get_conversions_from_cache();
		$this->get_worker_stats_from_cache();

		//Main loop - Get all the coin info we can get
		foreach ($this->full_coin_list->getuserallbalances->data as $row) {

			$coin = (object)array();

			foreach ($this->stats as $coin_stat) {
				if ($coin_stat->coin == $row->coin) {
					$coin->last_stat = $coin_stat->confirmed;
				}
			}

			$coin->coin = $row->coin;
			$coin->confirmed = number_format($row->confirmed + $row->ae_confirmed + $row->exchange, 8);
			$coin->unconfirmed = number_format($row->unconfirmed + $row->ae_unconfirmed, 8);
			$coin->total = number_format($row->confirmed + $row->ae_confirmed + $row->exchange + $row->unconfirmed + $row->ae_unconfirmed, 8);
			$coin->delta = number_format($row->confirmed + $row->ae_confirmed + $row->exchange - $coin->last_stat, 8);


			//If a conversion rate was returned by API, set it

			$code = $this->all_coins->{$row->coin}->code;

			//Get fiat prices
			$price = $this->crypto_prices->{$code}->{$this->fiat};

			$coin->confirmed_value = $price * ($row->confirmed + $row->ae_confirmed + $row->exchange);
			$coin->unconfirmed_value = $price * ($row->unconfirmed + $row->ae_unconfirmed);
			$coin->total_value = $price * ($row->confirmed + $row->ae_confirmed + $row->exchange + $row->unconfirmed + $row->ae_unconfirmed);
			$coin->delta_value = $price * $coin->delta;

			//Add the coin data into the main array we build the table with
			$this->coin_data[] = $coin;


		}

		$this->get_sums();
		$this->update_cache();
	}

	public
	function get_sums()
	{
		//Sum up the totals by traversing the coin data loop and summing everything up
		foreach ($this->coin_data as $coin_datum) {
			if ($coin_datum->confirmed_value > 0) {
				$this->confirmed_total += $coin_datum->confirmed_value;
			}
			if ($coin_datum->unconfirmed_value > 0) {
				$this->unconfirmed_total += $coin_datum->unconfirmed_value;
			}
			if ($coin_datum->delta_value > 0) {
				$this->delta_total += $coin_datum->delta_value;
			}
		}

	}

	public
	function get_min_payout($coin)
	{
		return $this->all_coins->{$coin}->min_payout;
	}

	public
	function get_code($coin)
	{
		return $this->all_coins->{$coin}->code;
	}

	public
	function get_decimal_for_conversion()
	{

		$decimal = 2;

		foreach ($this->all_coins as $coin) {
			if ($this->fiat == $coin->code) {
				$decimal = 8;
			}
		}

		return $decimal;
	}

	public function daily_stats()
	{

		$result_data = (object)array();
		$daily_delta = 0;
		foreach ($this->full_coin_list->getuserallbalances->data as $row) {
			$exchange_rate = 0;
			$current_total = number_format($row->confirmed + $row->ae_confirmed + $row->unconfirmed + $row->ae_unconfirmed + $row->exchange, 8);

			foreach ($this->daily_stats as $stat) {
				if ($row->coin == $stat->coin) {
					$interval_data = $stat;
				}
			}

			$interval_total = number_format($interval_data->confirmed + $interval_data->ae_confirmed + $interval_data->unconfirmed + $interval_data->ae_unconfirmed + $interval_data->exchange, 8);

			if (key_exists($row->coin, (array)$this->all_coins)) {

				$code = $this->all_coins->{$row->coin}->code;
				$exchange_rate = $this->crypto_prices->{$code}->{$this->fiat};

			}

			$result_data->{$row->coin} = (object)array(
				'total' => $current_total,
				'previous_total' => $interval_total,
				'delta' => $current_total - $interval_total,
				'value' => ($current_total - $interval_total) * $exchange_rate
			);
		}

		foreach ($result_data as $row) {
			$daily_delta += $row->value;
		}

		foreach ($this->coin_data as $coin) {
			if ($coin->delta_value < 0) {
				$daily_delta += -1 * $coin->delta_value;
			}
		}

		return (number_format($daily_delta, $this->get_decimal_for_conversion()));

	}


	function perform_estimate()
	{
		$hours = number_format((time() - strtotime($this->stats_time)) / (3600), 2);
		$this->minutes = number_format($hours * 60);
		return (number_format($this->delta_total / $hours, $this->get_decimal_for_conversion()));
	}

	public function get_stats_for_last_x_hours($hour_count)
	{

		$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . $this->strip_api_key() . "' AND time < DATE_SUB(NOW(), INTERVAL " . $hour_count . " HOUR) ORDER BY id DESC LIMIT 1";

		$result = $this->mysqli->query($sql);
		$object = $result->fetch_object();
		$this->stats_time = $object->time;
		$stats = json_decode($object->payload);
		return (object)$stats->getuserallbalances->data;
	}

	public function get_stats_for_last_day()
	{

		$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . $this->strip_api_key() . "' AND time < DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY id DESC LIMIT 1";

		$result = $this->mysqli->query($sql);
		$object = $result->fetch_object();
		$stats = json_decode($object->payload);
		return (object)$stats->getuserallbalances->data;
	}

	private function get_miner_stats_from_cache()
	{
		//Check for cache, make API call if there is no data
		$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . $this->strip_api_key() . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";

		$result = $this->mysqli->query($sql);
		$object = $result->fetch_object();
		$result->close();

		if ($object->payload != null) {
			$this->full_coin_list = json_decode($object->payload);
			$this->is_cached_data = 1;
			$this->cache_time_data = round(abs(time() - strtotime($object->time)), 0) . " seconds ago";

		} else {
			$this->get_coin_list();
		}

	}

	private function get_worker_stats_from_cache()
	{
		//Check for worker cache, make API call if there is no data
		$sql = "SELECT time,payload FROM workers WHERE apikey = '" . $this->strip_api_key() . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";

		$result = $this->mysqli->query($sql);
		$object = $result->fetch_object();
		$result->close();

		if ($object->payload != null) {
			$this->worker_data = json_decode($object->payload);
			$this->is_cached_workers = 1;
			$this->cache_time_workers = round(abs(time() - strtotime($object->time)), 0) . " seconds ago";
		} else {
			//Get all of the worker stats - Separate API call for each coin...gross...
			foreach ($this->all_coins as $coin => $coin_data) {
				$this->generate_worker_stats($coin);
			}
		}
	}

	private function get_conversions_from_cache()
	{
		//Check for conversion cache, make API call if there is no data
		$sql = "SELECT time,payload FROM conversions WHERE fiat = '" . $this->fiat . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";
		$result = $this->mysqli->query($sql);
		$object = $result->fetch_object();
		$result->close();

		if ($object->payload != null) {
			$this->crypto_prices = json_decode($object->payload);
			$this->is_cached_conversion = 1;
			$this->cache_time_conversion = round(abs(time() - strtotime($object->time)), 0) . " seconds ago";

		} else {
			$this->get_prices();
		}

	}

	private function update_cache()
	{
		//Insert data only if not cached
		if ($this->is_cached_data == 0) {
			$sql = "INSERT INTO minerstats VALUES ('', '" . $this->strip_api_key() . "', '" . json_encode($this->full_coin_list) . "', NOW())";
			$this->mysqli->query($sql);
		}
		if ($this->is_cached_conversion == 0) {
			$sql = "INSERT INTO conversions VALUES ('', '" . $this->fiat . "', '" . json_encode($this->crypto_prices) . "', NOW())";
			$this->mysqli->query($sql);
		}

		if ($this->is_cached_workers == 0) {
			$sql = "INSERT INTO workers VALUES ('', '" . $this->strip_api_key() . "', '" . json_encode($this->worker_data) . "', NOW())";
			$this->mysqli->query($sql);
		}

	}


}