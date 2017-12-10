<?php
date_default_timezone_set('America/Chicago');
//error_reporting(E_ERROR);
//ini_set('display_errors', 1);
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


//EDIT BELOW THIS LINE!!!

//SET API KEY HERE
//You can get your API Key Here:
//https://miningpoolhub.com/?page=account&action=edit
$api_key = "INSERT_YOUR_API_KEY_HERE";

//Set FIAT code if you like (USD, EUR, GBP, etc.)
$fiat = "SET_FIAT_CODE_HERE";

//DO NOT EDIT BELOW THIS LINE!!!

require_once("../config.php");

$mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);

if ($mysqli->connect_errno) {
	echo "Errno: " . $mysqli->connect_errno . "\n";
	echo "Error: " . $mysqli->connect_error . "\n";
	die();
}


//PASTE BELOW THIS LINE

//Check to see we have an API key. Show an error if none is defined.
if ($_GET['api_key'] != null) {
	$api_key = filter_var($_GET['api_key'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
if ($api_key == null || $api_key == "INSERT_YOUR_API_KEY_HERE" || strlen($api_key) <= 32) {
	die("Please enter an API key: example: " . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?api_key=ENTER_YOUR_KEY_HERE");
}

//Check to see what we are converting to. Default to USD
if ($_GET['fiat'] != null) {
	$fiat = filter_var($_GET['fiat'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
if ($fiat == "SET_FIAT_CODE_HERE" || strlen($fiat) >= 4) {
	$fiat = "USD";
}

//Initialize some variables
$sum_of_confirmed = 0;
$sum_of_unconfirmed = 0;
$confirmed_total = 0;
$unconfirmed_total = 0;
$delta_total = 0;
$is_cached_data = 0;
$is_cached_conversion = 0;
$is_cached_workers = 0;
$cache_time_data = 0;
$cache_time_conversion = 0;
$cache_time_workers = 0;
$coin_data = array();
$worker_data = array();

//Define the coin codes and minimum payout thresholds for all coins
$all_coins = (object)array(
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
	'zencash' => (object)array('code' => 'ZEN', 'min_payout' => '0.002'),
	'litecoin' => (object)array('code' => 'LTC', 'min_payout' => '0.002'),
	'monacoin' => (object)array('code' => 'MONA', 'min_payout' => '0.1'),
	'groestlcoin' => (object)array('code' => 'GRS', 'min_payout' => '0.002')
);

function make_api_call($url)
{
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$output = curl_exec($ch);
	curl_close($ch);
	return json_decode($output);
}

function strip_api_key($api_key)
{
	return substr($api_key, 0, 10) . substr($api_key, -10, 10);
}

function daily_stats($result, $prices, $last_day_stats)
{
	global $all_coins, $fiat;

	$result_data = (object)array();
	$daily_delta = 0;
	$last_hour_stats = json_decode($last_day_stats->payload);
	foreach ($result->getuserallbalances->data as $row) {
		$exchange_rate = 0;
		$current_total = number_format($row->confirmed + $row->ae_confirmed + $row->unconfirmed + $row->ae_unconfirmed + $row->exchange, 8);
		$last_hour_data = $last_hour_stats->{$row->coin};
		$last_hour_total = number_format($last_hour_data->confirmed + $last_hour_data->ae_confirmed + $last_hour_data->unconfirmed + $last_hour_data->ae_unconfirmed + $last_hour_data->exchange, 8);

		if (key_exists($row->coin, (array)$all_coins)) {

			$code = $all_coins->{$row->coin}->code;
			$exchange_rate = $prices->{$code}->{$fiat};

		}


		$result_data->{$row->coin} = (object)array(
			'total' => $current_total,
			'previous_total' => $last_hour_total,
			'delta' => $current_total - $last_hour_total,
			'value' => ($current_total - $last_hour_total) * $exchange_rate
		);
	}

	foreach ($result_data as $row) {
		$daily_delta += $row->value;
	}

	return (number_format($daily_delta, 2));
}

function perform_estimate($hourly_results, $last_stats_time)
{

	$hours = number_format((time() - strtotime($last_stats_time)) / (3600), 2);

	return (number_format($hourly_results / $hours, 2));
}


//Set up the conversion string for all coins we need from the cryptocompare API
$all_coin_list = array();
foreach ($all_coins as $coin) {
	$all_coin_list[] = $coin->code;
}
$all_coin_string = implode(",", $all_coin_list);


//Get historical data for last hour, 3 hours and 24 hours

$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . strip_api_key($api_key) . "' AND time < DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY id DESC LIMIT 1";

$result = $mysqli->query($sql);
$last_hour_stats = $result->fetch_object();
$last_hour_stats_time = $last_hour_stats->time;
$last_hour_stats = json_decode($last_hour_stats->payload);
$last_hour_stats = (object)$last_hour_stats->getuserallbalances->data;

$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . strip_api_key($api_key) . "' AND time < DATE_SUB(NOW(), INTERVAL 3 HOUR) ORDER BY id DESC LIMIT 1";

$result = $mysqli->query($sql);
$last_3_hour_stats = $result->fetch_object();
$last_3_hour_stats_time = $last_3_hour_stats->time;
$last_3_hour_stats = json_decode($last_3_hour_stats->payload);
$last_3_hour_stats = (object)$last_3_hour_stats->getuserallbalances->data;


$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . strip_api_key($api_key) . "' AND time < DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY id DESC LIMIT 1";

$result = $mysqli->query($sql);
$last_day_stats = $result->fetch_object();
$last_day_stats = json_decode($last_day_stats->payload);
$last_day_stats = (object)$last_day_stats->getuserallbalances->data;


//Check for cache, make API call if there is no data
$sql = "SELECT time,payload FROM minerstats WHERE apikey = '" . strip_api_key($api_key) . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";

$mysqli_result = $mysqli->query($sql);
$cached_coin_stats = $mysqli_result->fetch_object();
$mysqli_result->close();

if ($cached_coin_stats->payload != null) {
	$result = json_decode($cached_coin_stats->payload);
	$is_cached_data = 1;
	$cache_time_data = $cached_coin_stats->time;
} else {
	$result = make_api_call("https://miningpoolhub.com/index.php?page=api&action=getuserallbalances&api_key=" . $api_key);
}


//Check for worker cache, make API call if there is no data
$sql = "SELECT time,payload FROM workers WHERE apikey = '" . strip_api_key($api_key) . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";
$mysqli_result = $mysqli->query($sql);
$cached_worker_stats = $mysqli_result->fetch_object();
$mysqli_result->close();

if ($cached_worker_stats->payload != null) {
	$is_cached_workers = 1;
	$cache_time_workers = $cached_worker_stats->time;
}

//Check for conversion cache, make API call if there is no data
$sql = "SELECT time,payload FROM conversions WHERE fiat = '" . $fiat . "' AND time > DATE_SUB(NOW(), INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1";
$mysqli_result = $mysqli->query($sql);
$cached_conversion_stats = $mysqli_result->fetch_object();
$mysqli_result->close();

if ($cached_conversion_stats->payload != null) {
	$prices = json_decode($cached_conversion_stats->payload);
	$is_cached_conversion = 1;
	$cache_time_conversion = $cached_conversion_stats->time;
} else {
	$prices = make_api_call("https://min-api.cryptocompare.com/data/pricemulti?fsyms=" . $all_coin_string . "&tsyms=" . $fiat);
}


//Main loop - Get all the coin info we can get
foreach ($result->getuserallbalances->data as $row) {

	$coin = (object)array();

	foreach ($last_hour_stats as $coin_stat) {
		if ($coin_stat->coin == $row->coin) {
			$coin->last_hour_stat = $coin_stat->confirmed;
		}
	}

	$coin->coin = $row->coin;
	$coin->confirmed = number_format($row->confirmed + $row->ae_confirmed + $row->exchange, 8);
	$coin->unconfirmed = number_format($row->unconfirmed + $row->ae_unconfirmed, 8);
	$coin->delta_hourly = number_format($coin->confirmed - $coin->last_hour_stat, 8);


	//If a conversion rate was returned by API, set it
	foreach ($prices as $price) {
		if (key_exists($row->coin, $all_coins)) {

			$code = $all_coins->{$row->coin}->code;

			$price = $prices->{$code}->{$fiat};

			$coin->confirmed_value = number_format($price * $coin->confirmed, 2);
			$coin->unconfirmed_value = number_format($price * $coin->unconfirmed, 2);
			$coin->value_delta_hourly = number_format($price * $coin->delta_hourly, 2);

		}

	}

	//Add the coin data into the main array we build the table with
	$coin_data[] = $coin;

	if ($is_cached_workers == 0) {
		//Get all of the worker stats - Separate API call for each coin...gross...
		$workers = make_api_call("https://" . $row->coin . ".miningpoolhub.com/index.php?page=api&action=getuserworkers&api_key=" . $api_key);


		//Get the stats for every active worker with hashrate > 0
		$worker_list = $workers->getuserworkers->data;
		$active_workers = array();
		foreach ($worker_list as $worker) {
			if ($worker->hashrate != 0) {
				$worker->coin = $row->coin;
				$worker_data[] = $worker;
			}
		}
	}

}

//Sum up the totals by traversing the coin data loop and summing everything up
foreach ($coin_data as $coin_datum) {
	$confirmed_total += $coin_datum->confirmed_value;
	$unconfirmed_total += $coin_datum->unconfirmed_value;
	$delta_total += $coin_datum->value_delta_hourly;
}

//Insert data only if not cached
if ($is_cached_data == 0) {
	$sql = "INSERT INTO minerstats VALUES ('', '" . strip_api_key($api_key) . "', '" . json_encode($result) . "', NOW())";
	$mysqli->query($sql);
}
if ($is_cached_conversion == 0) {
	$sql = "INSERT INTO conversions VALUES ('', '" . $fiat . "', '" . json_encode($prices) . "', NOW())";
	$mysqli->query($sql);
}

if ($is_cached_workers == 0) {
	$sql = "INSERT INTO workers VALUES ('', '" . strip_api_key($api_key) . "', '" . json_encode($worker_data) . "', NOW())";
	$mysqli->query($sql);
} else {
	$worker_data = json_decode($cached_worker_stats->payload);
}

$estimate = perform_estimate($delta_total, $last_hour_stats_time);


//GENERATE THE UI HERE
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <meta name="description" content="">
    <meta name="author" content="">
    <title>Miner Stats</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <style>
        * {
            font-size: 9px;
            line-height: 1.0;
        }
    </style>
</head>
<body>
<script language="JavaScript">
    var timerInit = function (cb, time, howOften) {
        // time passed in is seconds, we convert to ms
        var convertedTime = time * 1000;
        var convetedDuration = howOften * 1000;
        var args = arguments;
        var funcs = [];

        for (var i = convertedTime; i > 0; i -= convetedDuration) {
            (function (z) {
                // create our return functions, within a private scope to preserve the loop value
                // with ES6 we can use let i = convertedTime
                funcs.push(setTimeout.bind(this, cb.bind(this, args), z));

            })(i);
        }

        // return a function that gets called on load, or whatever our event is
        return function () {

            //execute all our functions with their corresponsing timeouts
            funcs.forEach(function (f) {
                f();
            });
        };

    };

    // our doer function has no knowledge that its being looped or that it has a timeout
    var doer = function () {
        var el = document.querySelector('#timer');
        var previousValue = Number(el.innerHTML);
        if (previousValue == 1) {
            location.reload();
        } else {
            document.querySelector('#timer').innerHTML = previousValue - 1;
        }
    };


    // call the initial timer function, with the cb, how many iterations we want (30 seconds), and what the duration between iterations is (1 second)
    window.onload = timerInit(doer, 60, 1);
</script>
<nav class="navbar navbar-inverse navbar-fixed-top">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#">MinerStats</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
            <ul class="nav navbar-nav">
                <li class="active"><a href="#">Stats</a></li>
            </ul>
            <ul class="nav navbar-nav pull-right">
                <li>
                    <a id="timer" class="nav">60</a>
                </li>
            </ul>
        </div><!--/.nav-collapse -->
    </div>
</nav>
<div class="container"><br><br><br><br><br>
    <h1>MiningPoolHub Stats</h1>
    <h3>24 Hr Earnings: <?php echo daily_stats($result, $prices, $last_day_stats) . " " . $fiat; ?></h3>
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered table-striped">
                <tr>
                    <th>Coin</th>
                    <th>Confirmed (% of min payout)</th>
                    <th>Unconfirmed</th>
                    <th>Changes Last Hour</th>
                    <th>Total</th>
                    <th>Value (Conf.)</th>
                    <th>Value (Unconf.)</th>
                    <th>Value (Total)</th>
                </tr>
				<?php

				foreach ($coin_data as $coin) {
					?>
                    <tr>
                        <td <?php if (array_key_exists($coin->coin, $payout_coins)) {
							echo 'class="info"';
						} ?>>
                            <span <?php if ($coin->confirmed >= $all_coins->{$coin->coin}->min_payout) {
	                            echo 'style="font-weight: bold; color: red;"';
                            } ?> ><?php echo $coin->coin; ?></span></td>
                        <td <?php if (array_key_exists($coin->coin, $payout_coins)) {
							echo 'class="info"';
						} ?>><?php echo $coin->confirmed; ?><?php echo " (" . number_format(100 * $coin->confirmed / $all_coins->{$coin->coin}->min_payout, 0) . "%)"; ?></td>
                        <td <?php if (array_key_exists($coin->coin, $payout_coins)) {
							echo 'class="info"';
						} ?>><?php echo $coin->unconfirmed; ?></td>
                        <td <?php if ($coin->delta_hourly > 0) {
							echo 'class="success"';
						}; ?>><?php echo number_format($coin->delta_hourly, 8); ?> (<?php echo number_format($coin->value_delta_hourly, 2) . " " . $fiat; ?>)
                        </td>
                        <td <?php if (array_key_exists($coin->coin, $payout_coins)) {
							echo 'class="info"';
						} ?>><b><?php echo number_format($coin->confirmed + $coin->unconfirmed, 8); ?></b></td>
                        <td <?php if ($coin->confirmed_value > 0) {
							echo 'class="success"';
						} ?>><?php echo number_format($coin->confirmed_value, 2) . " " . $fiat; ?></td>
                        <td <?php if ($coin->unconfirmed_value > 0) {
							echo 'class="success"';
						} ?>><?php echo number_format($coin->unconfirmed_value, 2) . " " . $fiat; ?></td>
                        <td <?php if ($coin->unconfirmed_value > 0) {
							echo 'class="success"';
						} ?>><?php echo number_format($coin->confirmed_value + $coin->unconfirmed_value, 2) . " " . $fiat; ?></td>
                    </tr>
					<?php
				}
				?>
                <tr>
                    <td>TOTAL</td>
                    <td></td>
                    <td></td>
                    <td><?php echo number_format($delta_total, 2) . " " . $fiat; ?></td>
                    <td></td>
                    <td><?php echo number_format($confirmed_total, 2) . " " . $fiat; ?></td>
                    <td><?php echo number_format($unconfirmed_total, 2) . " " . $fiat; ?></td>
                    <td><?php echo number_format($confirmed_total + $unconfirmed_total, 2) . " " . $fiat; ?></td>
                </tr>
                <tr>
                    <td>ESTIMATES (Based on last hour)</td>
                    <td></td>
                    <td><?php echo $estimate; ?> Hourly</td>
                    <td><?php echo number_format($estimate * 24, 2) . " " . $fiat; ?> Daily</td>
                    <td><?php echo number_format($estimate * 24 * 7, 2) . " " . $fiat; ?> Weekly</td>
                    <td><?php echo number_format($estimate * 24 * 30, 2) . " " . $fiat; ?> Monthly</td>
                    <td><?php echo number_format($estimate * 24 * 365, 2) . " " . $fiat; ?> Yearly</td>
                    <td></td>
                </tr>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered table-striped">
                <tr>
                    <th>Worker</th>
                    <th>Coin</th>
                    <th>Hashrate</th>
                    <th>Monitor</th>
                </tr>
				<?php foreach ($worker_data as $worker) { ?>
                    <tr>
                        <td><?php echo $worker->username; ?></td>
                        <td><?php echo $worker->coin; ?></td>
                        <td><?php echo number_format($worker->hashrate, 2); ?></td>
                        <td><?php echo $worker->monitor == 1 ? "Enabled" : "Disabled"; ?></td>
                    </tr>
				<?php } ?>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered table-striped">
                <tr>
                    <th>Cache</th>
                    <th>Hit/Miss</th>
                </tr>
                <tr>
                    <td>Coin Cache</td>
                    <td><?php echo $is_cached_data == 1 ? "hit  (Cached Data From: " . $cache_time_data . ")" : ""; ?></td>
                </tr>
                <tr>
                    <td>Conversion Cache</td>
                    <td><?php echo $is_cached_conversion == 1 ? "hit  (Cached Data From: " . $cache_time_conversion . ")" : ""; ?></td>
                </tr>
                <tr>
                    <td>Worker Cache</td>
                    <td><?php echo $is_cached_workers == 1 ? "hit  (Cached Data From: " . $cache_time_workers . ")" : ""; ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>