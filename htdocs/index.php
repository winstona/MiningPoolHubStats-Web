<?php
date_default_timezone_set('America/Chicago');
//error_reporting(E_ALL);
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

$api_key = null;
$fiat = null;

require_once("../config.php");
require_once("miningpoolhubstats.class.php");

$mph_stats = new miningpoolhubstats($db_host, $db_username, $db_password, $db_name);

//PASTE BELOW THIS LINE

//Check to see we have an API key. Show an error if none is defined.
if ($_GET['api_key'] != null) {
	$api_key = filter_var($_GET['api_key'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
if ($api_key == null || strlen($api_key) <= 32) {
	die("Please enter an API key: example: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "/USD/ENTER_YOUR_KEY_HERE");
}

//Check to see what we are converting to. Default to USD
if ($_GET['fiat'] != null) {
	$fiat = filter_var($_GET['fiat'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
}
if ($fiat == null || strlen($fiat) >= 5) {
	$fiat = "USD";
}

$mph_stats->init_and_execute($api_key, $fiat);


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
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-PsH8R72JQ3SOdhVi3uxftmaW6Vc51MKb0q5P2rRUpPvrszuE4W1povHYgTpBfshb" crossorigin="anonymous">
    <link href="https://cdn.datatables.net/1.10.16/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <style>
        html {
            position: relative;
            min-height: 100%;
        }

        body {
            padding-top: 4.5rem;
            margin-bottom: 60px; /* Margin bottom by footer height */
        }

        .footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            height: 60px; /* Set the fixed height of the footer here */
            line-height: 60px; /* Vertically center the text there */
            background-color: #f5f5f5;
        }

        .footer > .container {
            padding-right: 15px;
            padding-left: 15px;
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
    window.onload = timerInit(doer, 300, 1);
</script>
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="#">MPHStats</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item active"><a class="nav-link" href="#">Stats</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="http://example.com" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">FIAT Conversion</a>
                <div class="dropdown-menu" aria-labelledby="dropdown01">
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/USD/<?php echo $api_key; ?>">USD
                    </a>
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/EUR/<?php echo $api_key; ?>">EUR
                    </a>
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/GBP/<?php echo $api_key; ?>">GBP
                    </a>
                </div>
            </li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="http://example.com" id="dropdown01" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Crypto Conversion</a>
                <div class="dropdown-menu" aria-labelledby="dropdown01">
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/BTC/<?php echo $api_key; ?>">BTC
                    </a>
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/LTC/<?php echo $api_key; ?>">LTC
                    </a>
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/ETH/<?php echo $api_key; ?>">ETH
                    </a>
                    <a class="dropdown-item" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/XMR/<?php echo $api_key; ?>">XMR
                    </a>
                </div>
            </li>
        </ul>
        <ul class="navbar-nav pull-right">
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#changelog">Changelog</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#how_to_use">How To Use</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-toggle="modal" data-target="#about_donate">About/Donate</a>
            </li>
            <ul class="nav navbar-nav"></ul>
            <ul class="nav navbar-nav pull-right">
                <li class="nav-item">
                    <a id="timer" class="nav-link">300</a>
                </li>
            </ul>
        </ul>
    </div>
</nav>
<main role="main" class="container">
    <h1>MiningPoolHub Stats</h1>
    <h3>24 Hr Earnings: <?php echo $mph_stats->daily_stats() . " " . $fiat; ?></h3>
    <div class="row">
        <div class="col-md-12">
            <table class="table table-bordered table-striped" cellspacing="0" id="wallet_table">
                <thead>
                <tr>
                    <th>Coin</th>
                    <th>Confirmed (% of min payout)</th>
                    <th>Unconfirmed</th>
                    <th>Payout Last 24 Hours</th>
                    <th>Total</th>
                    <th>Hash Rate</th>
                    <th>Hourly Estimate</th>
                </tr>
                </thead>
                <tbody>
				<?php

				foreach ($mph_stats->coin_data as $coin) {
					?>
                    <tr>
                        <td>
                            <span <?php if ($coin->confirmed >= $mph_stats->all_coins->{$coin->coin}->min_payout * 20) {
	                            echo 'style="font-weight: bold; color: red;"';
                            } else if ($coin->confirmed >= $mph_stats->all_coins->{$coin->coin}->min_payout * 5) {
	                            echo 'style="font-weight: bold; color: orange;"';
                            } else if ($coin->confirmed >= $mph_stats->all_coins->{$coin->coin}->min_payout) {
	                            echo 'style="font-weight: bold; color: green;"';
                            } ?>><?php echo $coin->coin; ?></span></td>
                        <td <?php if ($coin->confirmed_value > 0) {
							echo 'class="table-success"';
						} ?>><?php echo $coin->confirmed; ?><?php echo " (" . number_format(100 * $coin->confirmed / $mph_stats->all_coins->{$coin->coin}->min_payout, 0) . "%)"; ?>
                            <br><?php echo number_format($coin->confirmed_value, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        </td>
                        <td <?php if ($coin->unconfirmed_value > 0) {
							echo 'class="table-success"';
						} ?>><?php echo $coin->unconfirmed; ?>
                            <br><?php echo number_format($coin->unconfirmed_value, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        </td>
                        <td <?php if ($coin->payout_last_24 > 0) {
							echo 'class="table-success"';
						}; ?>><?php echo $coin->payout_last_24; ?>
                            <br>(<?php echo number_format($coin->payout_last_24_value, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>)
                        </td>
                        <td <?php if ($coin->total_value > 0) {
							echo 'class="table-success"';
						} ?>><b><?php echo $coin->total; ?>
                                <br><?php echo number_format($coin->total_value, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                            </b></td>
                        </td>
                        <td <?php if ($coin->hashrate > 0) {
							echo 'class="table-primary"';
						} ?>><?php echo number_format($coin->hashrate, 4); ?></td>
                        <td <?php if ($coin->hourly_estimate_value > 0) {
							echo 'class="table-success"';
						} ?>><?php echo number_format($coin->hourly_estimate_value, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                    </tr>
					<?php
				}
				?>
                </tbody>
                <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td><?php echo number_format($mph_stats->confirmed_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                    <td><?php echo number_format($mph_stats->unconfirmed_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                    <td><?php echo number_format($mph_stats->payout_last_24_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                    <td><?php echo number_format($mph_stats->confirmed_total + $mph_stats->unconfirmed_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                    <td></td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?></td>
                </tr>
                <tr>
                    <td>ESTIMATES (Based on API block info)</td>
                    <td></td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        <br>Hourly
                    </td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total * 24, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        <br>Daily
                    </td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total * 24 * 7, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        <br>Weekly
                    </td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total * 24 * 30, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        <br>Monthly
                    </td>
                    <td><?php echo number_format($mph_stats->hourly_estimate_total * 24 * 365, $mph_stats->get_decimal_for_conversion()) . " " . $fiat; ?>
                        <br>Yearly
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <br><br>
            <table class="table table-bordered table-striped" cellspacing="0" id="worker_table">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Worker</th>
                    <th>Coin</th>
                    <th>Hashrate</th>
                    <th>Monitor</th>
                </tr>
                </thead>
                <tbody>
				<?php $count = 1;
				foreach ($mph_stats->worker_data as $worker) { ?>
                    <tr>
                        <td><?php echo $count; ?></td>
                        <td><?php echo $worker->username; ?></td>
                        <td><?php echo $worker->coin; ?></td>
                        <td><?php echo number_format($worker->hashrate, 4); ?></td>
                        <td><?php echo $worker->monitor == 1 ? "Enabled" : "Disabled"; ?></td>
                    </tr>
					<?php $count++;
				}; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <br><br>
            <table class="table table-bordered table-striped">
                <tr>
                    <th>Cache</th>
                    <th>Hit/Miss</th>
                </tr>
                <tr>
                    <td>Coin Cache</td>
                    <td><?php echo $mph_stats->is_cached_data == 1 ? "hit  (Cached Data From: " . $mph_stats->cache_time_data . ")" : ""; ?></td>
                </tr>
                <tr>
                    <td>Conversion Cache</td>
                    <td><?php echo $mph_stats->is_cached_conversion == 1 ? "hit  (Cached Data From: " . $mph_stats->cache_time_conversion . ")" : ""; ?></td>
                </tr>
                <tr>
                    <td>Worker Cache</td>
                    <td><?php echo $mph_stats->is_cached_workers == 1 ? "hit  (Cached Data From: " . $mph_stats->cache_time_workers . ")" : ""; ?></td>
                </tr>
            </table>
        </div>
    </div>
</main>
<footer class="footer">
    <div class="container">
        <span class="text-muted">If you feel like this site has helped you, please consider <a href="#" data-toggle="modal" data-target="#about_donate">donating</a> to help cover server/hosting costs. Thank you!  </span>
        <span class="text-muted">  &copy; <?php echo date("Y"); ?> Mindbrite LLC.</span>
    </div>
</footer>
<div class="modal fade" id="about_donate" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">About / How to Donate</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h2>&copy; <?php echo date("Y"); ?> Mindbrite LLC</h2>
                Thank you for your support. If you would like to donate to project to help assist with domain/server/etc. costs, you can do so at the following addresses:
                <div class="input-group">
                    <span class="input-group-addon" id="basic-addon1">BTC</span>
                    <input type="text" class="form-control" value="17ZjS6ZJTCNWrd17kkZpgRHYZJjkq5qT5A" aria-describedby="basic-addon1" disabled>
                </div>
                <div class="input-group">
                    <span class="input-group-addon" id="basic-addon1">LTC</span>
                    <input type="text" class="form-control" value="LdGQgurUKH2J7iBBPcXWyLKUb8uUgXCfFF" aria-describedby="basic-addon1" disabled>
                </div>
                <div class="input-group">
                    <span class="input-group-addon" id="basic-addon1">ETH</span>
                    <input type="text" class="form-control" value="0x6e259a08a1596653cbf66b2ae2c36c46ca123523" aria-describedby="basic-addon1" disabled>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="changelog" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Changelog</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h2>Changes 12/13/2017</h2>
                <ul>
                    <li>Added Table sorting/filtering</li>
                </ul>
                <h2>Changes 12/12/2017</h2>
                <ul>
                    <li>Added Changelog</li>
                    <li>Changed payout color to three colors (green, orange and red based on percentage of threshold</li>
                    <li>Earnings and estimates are now pulled directly from pool API</li>
                </ul>
                <br><br>
                <h4>See <a href="#" data-toggle="modal" data-target="#how_to_use">How To Use</a> for more info.</h4>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="how_to_use" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">How to Use</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="accordion" role="tablist">
                    <div class="card">
                        <div class="card-header" role="tab" id="headingOne">
                            <h5 class="mb-0">
                                <a class="collapsed" data-toggle="collapse" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    How can I view stats in alternate currencies? (USD,GBP,CAD) or in crypto-currenies?
                                </a>
                            </h5>
                        </div>
                        <div id="collapseOne" class="collapse" role="tabpanel" aria-labelledby="headingOne" data-parent="#accordion">
                            <div class="card-body">
                                MiningPoolStats supports most available currences and cryptocurrencies. If you would like to view an alternate currency, you can by modifying the URL<br>
                                For example:<br>
                                <br><br>
                                For USD:
                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/USD/<?php echo $api_key; ?>">https://<?php echo $_SERVER['HTTP_HOST']; ?>/USD/<?php echo $api_key; ?></a><br>
                                For GBP:
                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/GBP/<?php echo $api_key; ?>">https://<?php echo $_SERVER['HTTP_HOST']; ?>/GBP/<?php echo $api_key; ?></a><br>
                                For BTC:
                                <a href="https://<?php echo $_SERVER['HTTP_HOST']; ?>/BTC/<?php echo $api_key; ?>">https://<?php echo $_SERVER['HTTP_HOST']; ?>/BTC/<?php echo $api_key; ?></a><br>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header" role="tab" id="headingTwo">
                            <h5 class="mb-0">
                                <a class="collapsed" data-toggle="collapse" href="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    What does it mean when a coin is in green, orange, or red text?
                                </a>
                            </h5>
                        </div>
                        <div id="collapseTwo" class="collapse" role="tabpanel" aria-labelledby="headingTwo" data-parent="#accordion">
                            <div class="card-body">
                                We have implemented some recommended values for coins in order to prevent keeping too much in the pool wallet.<br>
                                <br>
                                <span style="font-weight: bold; color: green;">GREEN</span>: This means that you have reached the minimum payout threshold and you can "cash out" if you want to.<br>
                                <br>
                                <span style="font-weight: bold; color: orange;">ORANGE</span>: This means that you are at 5x the minimum payout and you should consider saving your funds to a local wallet soon.<br>
                                <br>
                                <span style="font-weight: bold; color: red;">RED</span>: This means that you are at 20x the minimum payout and you are probably holding too many coins in an online wallet. You should move coins to a local wallet ASAP.
                                <br>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header" role="tab" id="headingThree">
                            <h5 class="mb-0">
                                <a class="collapsed" data-toggle="collapse" href="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    What is the percentage next to the confirmed value for a coin?
                                </a>
                            </h5>
                        </div>
                        <div id="collapseThree" class="collapse" role="tabpanel" aria-labelledby="headingThree" data-parent="#accordion">
                            <div class="card-body">
                                The percentage next to the coin name indicates how many percent of the minimum payout you have. Once it hits 100% you can "cash out" your coins.
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header" role="tab" id="headingFour">
                            <h5 class="mb-0">
                                <a class="collapsed" data-toggle="collapse" href="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    Why are my stats so inaccurate?
                                </a>
                            </h5>
                        </div>
                        <div id="collapseFour" class="collapse" role="tabpanel" aria-labelledby="headingFour" data-parent="#accordion">
                            <div class="card-body">
                                Stats are based on the last three hours of stats. Unfortunately, PPNLS pools like MPH are based on luck. You can earn a lot one hour, and nothing the next. If your last few hours were luckier, your stats will be better than expected. If you had an unlucky hour, your stats will be lower...
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header" role="tab" id="headingFive">
                            <h5 class="mb-0">
                                <a class="collapsed" data-toggle="collapse" href="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                    Why does it show "Stats in Last X Minutes"?
                                </a>
                            </h5>
                        </div>
                        <div id="collapseFive" class="collapse" role="tabpanel" aria-labelledby="headingFive" data-parent="#accordion">
                            <div class="card-body">
                                Since the application relies on storing some data when you last view the app, it looks for the first record over an hour old. If you haven't visited in 4 hours, it will show you the difference for the last 4 hours (and will calculate an hourly estimate accordingly)
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.1.1.slim.min.js" integrity="sha384-A7FZj7v+d/sdmMqp/nOQwliLvUsJfDHW+k9Omg/a/EheAdgtzNs3hpfag6Ed950n" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/js/bootstrap.bundle.min.js" integrity="sha384-3ziFidFTgxJXHMDttyPJKDuTlmxJlwbSkojudK/CkRqKDOmeSbN6KLrGdrBQnT2n" crossorigin="anonymous"></script>
<script src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.16/js/dataTables.bootstrap4.min.js"></script>
<script language="javascript">
    $(document).ready(function () {
        $('#wallet_table').DataTable();
        $('#worker_table').DataTable();
    });
</script>
</body>