<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
  header("Location:../admin.php?id=login");
} else {


// get MikroTik system clock
  $getclock = $API->comm("/system/clock/print", array(".proplist" => "time,date,time-zone-name"));
  $clock = isset($getclock[0]) ? $getclock[0] : array();
  $timezone = isset($getclock[0]['time-zone-name']) ? $getclock[0]['time-zone-name'] : 'UTC';
  $_SESSION['timezone'] = $timezone;
  date_default_timezone_set($timezone);

// get system resource MikroTik
  $getresource = $API->comm("/system/resource/print", array(".proplist" => "uptime,board-name,version,cpu-load,free-memory,free-hdd-space,architecture-name"));
  $resource = isset($getresource[0]) ? $getresource[0] : array();

// get routeboard info (cached per router session)
  unset($_SESSION['routerboard']);
  if (isset($_SESSION[$session . '_routerboard']) && is_array($_SESSION[$session . '_routerboard'])) {
    $routerboard = $_SESSION[$session . '_routerboard'];
  } else {
    $getrouterboard = $API->comm("/system/routerboard/print");
    $routerboard = (isset($getrouterboard[0]) && is_array($getrouterboard[0])) ? $getrouterboard[0] : array();
    $_SESSION[$session . '_routerboard'] = $routerboard;
  }
/*
// move hotspot log to disk *
  $getlogging = $API->comm("/system/logging/print", array("?prefix" => "->", ));
  $logging = $getlogging[0];
  if ($logging['prefix'] == "->") {
  } else {
    $API->comm("/system/logging/add", array("action" => "disk", "prefix" => "->", "topics" => "hotspot,info,debug", ));
  }

// get hotspot log
  $getlog = $API->comm("/log/print", array("?topics" => "hotspot,info,debug", ));
  $log = array_reverse($getlog);
  $THotspotLog = count($getlog);
*/
// get & counting hotspot users
  $countallusers = $API->comm("/ip/hotspot/user/print", array("count-only" => ""));
  if (is_array($countallusers)) {
    $countallusers = isset($countallusers[0]['ret']) ? $countallusers[0]['ret'] : (isset($countallusers['ret']) ? $countallusers['ret'] : count($countallusers));
  }
  $countallusers = is_numeric($countallusers) ? (int)$countallusers : 0;
  $uunit = ($countallusers > 1) ? "items" : "item";

// get & counting hotspot active
  $counthotspotactive = $API->comm("/ip/hotspot/active/print", array("count-only" => ""));
  if (is_array($counthotspotactive)) {
    $counthotspotactive = isset($counthotspotactive[0]['ret']) ? $counthotspotactive[0]['ret'] : (isset($counthotspotactive['ret']) ? $counthotspotactive['ret'] : count($counthotspotactive));
  }
  $counthotspotactive = is_numeric($counthotspotactive) ? (int)$counthotspotactive : 0;
  $hunit = ($counthotspotactive > 1) ? "items" : "item";

  $logh = "350px";
  $lreport = "style='display:block;'";
/*
// get selling report
    $thisD = date("d");
    $thisM = strtolower(date("M"));
    $thisY = date("Y");

    if (strlen($thisD) == 1) {
      $thisD = "0" . $thisD;
    } else {
      $thisD = $thisD;
    }

    $idhr = $thisM . "/" . $thisD . "/" . $thisY;
    $idbl = $thisM . $thisY;

    $getSRHr = $API->comm("/system/script/print", array(
      "?source" => "$idhr",
    ));
    $TotalRHr = count($getSRHr);
    $getSRBl = $API->comm("/system/script/print", array(
      "?owner" => "$idbl",
    ));
    $TotalRBl = count($getSRBl);

    for ($i = 0; $i < $TotalRHr; $i++) {

      $tHr += explode("-|-", $getSRHr[$i]['name'])[3];

    }
    for ($i = 0; $i < $TotalRBl; $i++) {

      $tBl += explode("-|-", $getSRBl[$i]['name'])[3];
    }
  }*/
}
?>
    
<div id="reloadHome">

    <div id="r_1" class="row">
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
            <div class="box-group-icon"><i class="fa fa-clock-o"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_time ?></b> : <span class="live-clock-time" data-date="<?= htmlspecialchars($clock['date'] ?? ''); ?>" data-time="<?= htmlspecialchars($clock['time'] ?? ''); ?>" style="font-weight: 600; font-size: 13px;"><?= htmlspecialchars($clock['time'] ?? ''); ?></span><br/>
                    <b><?= $_date ?></b> : <span class="live-clock-date" style="font-size: 12.5px;"><?= ucfirst(htmlspecialchars($clock['date'] ?? '')); ?></span><br/>
                    <b><?= $_uptime ?></b> : <?= formatDTM($resource['uptime']); ?>
                    <?php $_SESSION[$session.'sdate'] = $clock['date']; ?>
                </span>
              </div>
            </div>
          </div>
        </div>
      <div class="col-4">
        <div class="box bmh-75 box-bordered">
          <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-info-circle"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_board_name ?></b> : <?= htmlspecialchars($resource['board-name'] ?? '') ?><br/>
                    <b><?= !empty($_architecture) ? $_architecture : 'Arsitektur' ?></b> : <?= htmlspecialchars($resource['architecture-name'] ?? '-') ?><br/>
                    <b>Router OS</b> : <?= htmlspecialchars($resource['version'] ?? '') ?>
                </span>
              </div>
            </div>
          </div>
        </div>
    <div class="col-4">
      <div class="box bmh-75 box-bordered">
        <div class="box-group">
          <div class="box-group-icon"><i class="fa fa-server"></i></div>
              <div class="box-group-area">
                <span>
                    <b><?= $_cpu_load ?></b> : <?= htmlspecialchars($resource['cpu-load'] ?? '0') ?>%<br/>
                    <b><?= $_free_memory ?></b> : <?= formatBytes($resource['free-memory'], 2) ?><br/>
                    <b><?= $_free_hdd ?></b> : <?= formatBytes($resource['free-hdd-space'], 2) ?>
                </span>
                </div>
              </div>
            </div>
          </div> 
      </div>

        <div class="row">
          <div class="col-8">
            <div id="r_2" class="card">
              <div class="card-header"><h3><i class="fa fa-wifi"></i> Hotspot</h3></div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-3 col-box-6">
                      <div class="box bg-blue bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=active&session=<?= $session; ?>">
                          <h1><?= $counthotspotactive; ?> <span style="font-size: 14px;"><?= $hunit; ?></span></h1>
                          <div><i class="fa fa-laptop"></i> <?= $_hotspot_active ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-green bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot=users&profile=all&session=<?= $session; ?>">
                          <h1><?= $countallusers; ?> <span style="font-size: 14px;"><?= $uunit; ?></span></h1>
                          <div><i class="fa fa-users"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-yellow bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot-user=add&session=<?= $session; ?>">
                          <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_add ?></span></h1>
                          <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                    <div class="col-3 col-box-6">
                      <div class="box bg-red bmh-75">
                        <a onclick="cancelPage()" href="./?hotspot-user=generate&session=<?= $session; ?>">
                          <h1><i class="fa fa-user-plus"></i> <span style="font-size: 14px;"><?= $_generate ?></span></h1>
                          <div><i class="fa fa-user-plus"></i> <?= $_hotspot_users ?></div>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
            </div>

            <div class="card">
              <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <h3 style="margin: 0;"><i class="fa fa-area-chart"></i> <?= $_traffic ?> </h3>
                <?php
                $getallifaces = $API->comm("/interface/print", array(".proplist" => "name,disabled"));
                $getallifaces = is_array($getallifaces) ? $getallifaces : array();

                $savedIface = $_SESSION[$session . '_iface_name'] ?? '';
                $defaultIface = isset($getallifaces[$iface - 1]['name']) ? $getallifaces[$iface - 1]['name'] : (isset($getallifaces[0]['name']) ? $getallifaces[0]['name'] : ($interface ?? 'ether1'));

                if (!empty($savedIface)) {
                  $interface = $savedIface;
                } else {
                  $interface = $defaultIface;
                  $_SESSION[$session . '_iface_name'] = $interface;
                }
                ?>
                <select id="dashboard_interface_select" class="dropd pd-5" style="max-width: 220px; font-size: 13px; cursor: pointer;">
                  <option value="" disabled><?= !empty($_select_interface) ? $_select_interface : 'Select Interface'; ?></option>
                  <?php 
                  if (empty($getallifaces)) {
                    echo '<option value="' . htmlspecialchars($interface) . '" selected>' . htmlspecialchars($interface) . '</option>';
                  } else {
                    foreach ($getallifaces as $idx => $ifitem) {
                      $ifName = $ifitem['name'] ?? '';
                      $selected = ($ifName === $interface) ? 'selected' : '';
                      echo '<option value="' . htmlspecialchars($ifName) . '" ' . $selected . '>[' . ($idx + 1) . '] ' . htmlspecialchars($ifName) . '</option>';
                    }
                  }
                  ?>
                </select>
              </div>

              <div class="card-body">
                  <script type="text/javascript"> 
                    var chart;
                    var sessiondata = "<?= $session ?>";
                    var interface = "<?= $interface ?>";
                    var n = 3000;
                    function requestDatta(session,iface) {
                      $.ajax({
                        url: './traffic/traffic.php?session='+encodeURIComponent(session)+'&iface='+encodeURIComponent(iface),
                        dataType: "json",
                        success: function(midata) {
                          try {
                            if (typeof midata === 'string') {
                              midata = JSON.parse(midata);
                            }
                            if (Array.isArray(midata) && midata.length >= 2) {
                              var TX = parseInt((Array.isArray(midata[0].data) ? midata[0].data[0] : midata[0].data) || 0);
                              var RX = parseInt((Array.isArray(midata[1].data) ? midata[1].data[0] : midata[1].data) || 0);
                              var x = (new Date()).getTime(); 
                              if (chart && chart.series && chart.series[0] && chart.series[1]) {
                                var shift = chart.series[0].data.length > 19;
                                chart.series[0].addPoint([x, TX], true, shift);
                                chart.series[1].addPoint([x, RX], true, shift);
                              }
                            }
                          } catch(e) {}
                        },
                        error: function() {}       
                      });
                    }	

                    $(document).ready(function() {
                      Highcharts.setOptions({
                        global: {
                          useUTC: false
                        }
                      });

                      chart = new Highcharts.Chart({
                        chart: {
                          renderTo: 'trafficMonitor',
                          animation: Highcharts.svg,
                          type: 'areaspline',
                          events: {
                            load: function () {
                              setInterval(function () {
                                requestDatta(sessiondata,interface);
                              }, n);
                            }
                          }
                        },
                        title: {
                          text: '<?= $_interface ?> ' + interface
                        },
                        xAxis: {
                          type: 'datetime',
                          tickPixelInterval: 150,
                          maxZoom: 20 * 1000,
                        },
                        yAxis: {
                            minPadding: 0.2,
                            maxPadding: 0.2,
                            title: {
                              text: null
                            },
                            labels: {
                              formatter: function () {      
                                var bytes = this.value;                          
                                var sizes = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
                                if (bytes == 0) return '0 bps';
                                var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                                return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + ' ' + sizes[i];                    
                              },
                            },       
                        },
                        
                        series: [{
                          name: 'Tx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }, {
                          name: 'Rx',
                          data: [],
                          marker: {
                            symbol: 'circle'
                          }
                        }],

                        tooltip: {
                          formatter: function () { 
                            var _0x2f7f=["\x70\x6F\x69\x6E\x74\x73","\x79","\x62\x70\x73","\x6B\x62\x70\x73","\x4D\x62\x70\x73","\x47\x62\x70\x73","\x54\x62\x70\x73","\x3C\x73\x70\x61\x6E\x20\x73\x74\x79\x6C\x65\x3D\x22\x63\x6F\x6C\x6F\x72\x3A","\x63\x6F\x6C\x6F\x72","\x73\x65\x72\x69\x65\x73","\x3B\x20\x66\x6F\x6E\x74\x2D\x73\x69\x7A\x65\x3A\x20\x31\x2E\x35\x65\x6D\x3B\x22\x3E","\x73\x79\x6D\x62\x6F\x6C\x55\x6E\x69\x63\x6F\x64\x65","\x3C\x2F\x73\x70\x61\x6E\x3E\x3C\x62\x3E","\x6E\x61\x6D\x65","\x3A\x3C\x2F\x62\x3E\x20\x30\x20\x62\x70\x73","\x70\x75\x73\x68","\x6C\x6F\x67","\x66\x6C\x6F\x6F\x72","\x3A\x3C\x2F\x62\x3E\x20","\x74\x6F\x46\x69\x78\x65\x64","\x70\x6F\x77","\x20","\x65\x61\x63\x68","\x3C\x62\x3E\x4D\x69\x6B\x68\x6D\x6F\x6E\x20\x54\x72\x61\x66\x66\x69\x63\x20\x4D\x6F\x6E\x69\x74\x6F\x72\x3C\x2F\x62\x3E\x3C\x62\x72\x20\x2F\x3E\x3C\x62\x3E\x54\x69\x6D\x65\x3A\x20\x3C\x2F\x62\x3E","\x25\x48\x3A\x25\x4D\x3A\x25\x53","\x78","\x64\x61\x74\x65\x46\x6F\x72\x6D\x61\x74","\x3C\x62\x72\x20\x2F\x3E","\x20\x3C\x62\x72\x2F\x3E\x20","\x6A\x6F\x69\x6E"];var s=[];$[_0x2f7f[22]](this[_0x2f7f[0]],function(_0x3735x2,_0x3735x3){var _0x3735x4=_0x3735x3[_0x2f7f[1]];var _0x3735x5=[_0x2f7f[2],_0x2f7f[3],_0x2f7f[4],_0x2f7f[5],_0x2f7f[6]];if(_0x3735x4== 0){s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[14])};var _0x3735x2=parseInt(Math[_0x2f7f[17]](Math[_0x2f7f[16]](_0x3735x4)/ Math[_0x2f7f[16]](1024)));s[_0x2f7f[15]](_0x2f7f[7]+ this[_0x2f7f[9]][_0x2f7f[8]]+ _0x2f7f[10]+ this[_0x2f7f[9]][_0x2f7f[11]]+ _0x2f7f[12]+ this[_0x2f7f[9]][_0x2f7f[13]]+ _0x2f7f[18]+ parseFloat((_0x3735x4/ Math[_0x2f7f[20]](1024,_0x3735x2))[_0x2f7f[19]](2))+ _0x2f7f[21]+ _0x3735x5[_0x3735x2])});return _0x2f7f[23]+ Highcharts[_0x2f7f[26]](_0x2f7f[24], new Date(this[_0x2f7f[25]]))+ _0x2f7f[27]+ s[_0x2f7f[29]](_0x2f7f[28])
                          },
                          shared: true                                                      
                        },
                      });

                      $('#dashboard_interface_select').on('change', function() {
                        var newIface = $(this).val();
                        if (!newIface) return;
                        interface = newIface;
                        if (chart) {
                          chart.setTitle({ text: '<?= $_interface ?> ' + interface });
                          if (chart.series && chart.series[0]) chart.series[0].setData([], true);
                          if (chart.series && chart.series[1]) chart.series[1].setData([], true);
                        }
                        $.get('./traffic/traffic.php?session=' + encodeURIComponent(sessiondata) + '&set_iface=' + encodeURIComponent(newIface));
                      });
                    });
                  </script>
                  <div id="trafficMonitor"></div>
                </div> 
              </div>
            </div>  
            <div class="col-4">
              <div id="r_4" class="row">
                <div <?= $lreport; ?> class="box bmh-75 box-bordered">
                  <div class="box-group">
                    <div class="box-group-icon"><i class="fa fa-money"></i></div>
                      <div class="box-group-area">
                        <span>
                          <div id="reloadLreport">
                            <?php 
                            if ($_SESSION[$session.'sdate'] == $_SESSION[$session.'idhr']){
                              echo $_income." <br/>" . "
                            ".$_today." " . $_SESSION[$session.'totalHr'] . "vcr : " . $currency . " " . $_SESSION[$session.'dincome']. "<br/>
                            ".$_this_month." " . $_SESSION[$session.'totalBl'] . "vcr : " . $currency . " " . $_SESSION[$session.'mincome']; 
                            }else{
                              echo "<div id='loader' ><i><span> <i class='fa fa-circle-o-notch fa-spin'></i> ". $_processing." </i></div>";
                            }
                            ?>                       
                          </div>
                      </span>
                  </div>
                </div>
              </div>
              </div>
              <div id="r_3" class="row">
              <div class="card">
                <div class="card-header">
                  <h3><a onclick="cancelPage()" href="./?hotspot=log&session=<?= $session; ?>" title="Open Hotspot Log" ><i class="fa fa-align-justify"></i> <?= $_hotspot_log ?></a></h3></div>
                    <div class="card-body">
                      <div style="padding: 5px; height: <?= $logh; ?> ;" class="mr-t-10 overflow">
                        <table class="table table-sm table-bordered table-hover" style="font-size: 12px; td.padding:2px;">
                          <thead>
                            <tr>
                              <th><?= $_time ?></th>
                              <th><?= $_users ?> (IP)</th>
                              <th><?= $_messages ?></th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td colspan="3" class="text-center">
                              <div id="loader" ><i><i class='fa fa-circle-o-notch fa-spin'></i> <?= $_processing ?> </i></div>
                              </td>
                            </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
                </div>
              </div>
  </div>
</div>